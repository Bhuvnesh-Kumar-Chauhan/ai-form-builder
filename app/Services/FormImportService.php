<?php

namespace App\Services;

use App\Exceptions\LlmException;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Deterministic, defensive parsers that convert Word (.docx) and Excel
 * (.xlsx) documents into builder-ready form fields, plus an optional
 * "hybrid" AI pass that refines types / required flags / validation for
 * ambiguous fields only - structure always comes from the file itself.
 */
class FormImportService
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public const CHOICE_TYPES = ['select', 'radio', 'checkbox'];

    public const MAX_FIELDS = 200;

    /** Highest confidence for types inferred from explicit signal. */
    public const CONFIDENCE_HIGH = 'high';
    public const CONFIDENCE_MEDIUM = 'medium';
    public const CONFIDENCE_LOW = 'low';

    /**
     * Parse a docx/xlsx file into a builder-ready schema-ish structure.
     *
     * @return array{
     *   title: string,
     *   description: string,
     *   layout: string|null,
     *   fields: array<int, array>,
     *   warnings: array<int, string>,
     * }
     */
    public function parseFile(string $path, string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        if (! in_array($extension, ['docx', 'xlsx', 'xls'], true)) {
            throw new \InvalidArgumentException("Unsupported file type '{$extension}'. Expected .docx or .xlsx.");
        }

        if (! is_file($path)) {
            throw new \InvalidArgumentException("Import file not found: {$path}");
        }

        return in_array($extension, ['docx'], true)
            ? $this->parseDocx($path)
            : $this->parseXlsx($path);
    }

    /* -----------------------------------------------------------------
     | DOCX
     | ----------------------------------------------------------------- */

    public function parseDocx(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The zip extension is required to read .docx files.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open the DOCX file (invalid or corrupt archive).');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || trim($xml) === '') {
            throw new \RuntimeException('The DOCX file contains no document body.');
        }

        $doc = new \DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);

        if (! $loaded) {
            throw new \RuntimeException('The DOCX XML could not be parsed.');
        }

        $body = $doc->getElementsByTagNameNS(self::W_NS, 'body')->item(0);
        $blocks = [];
        if ($body instanceof \DOMElement) {
            $this->collectBlocks($body, $blocks);
        }

        $converted = $this->blocksToFields($blocks);
        $title = $converted['title'] !== '' ? $converted['title'] : $this->titleFromFilename($path);

        return [
            'title' => Str::limit($title, 255),
            'description' => '',
            'layout' => 'docx',
            'fields' => $converted['fields'],
            'warnings' => $converted['warnings'],
        ];
    }

    /**
     * Walk body-level paragraphs / tables (recursing into structured
     * document tags) into a flat list of blocks.
     *
     * @param  array<int, array{type: string, text?: string, level?: int, checkbox?: bool, rows?: array}>  $blocks
     */
    protected function collectBlocks(\DOMNode $container, array &$blocks): void
    {
        foreach ($container->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $name = $node->localName;

            if ($name === 'p') {
                $block = $this->parseParagraphBlock($node);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            } elseif ($name === 'tbl') {
                $rows = $this->parseTable($node);
                if (! empty($rows)) {
                    $blocks[] = ['type' => 'table', 'rows' => $rows];
                }
            } elseif ($name === 'sdt') {
                // Content controls can wrap paragraphs/tables; dig inside.
                $this->collectBlocks($node, $blocks);
            }
            // w:sectPr and anything else is ignored.
        }
    }

    /**
     * @return array{type: string, text: string, level?: int, checkbox?: bool}|null
     */
    protected function parseParagraphBlock(\DOMElement $p): ?array
    {
        $raw = $this->paragraphText($p);
        $checkbox = false;

        // Content-control checkboxes (the common Word form element).
        if ($p->getElementsByTagNameNS(self::W_NS, 'checkBox')->length > 0) {
            $checkbox = true;
        }

        // Textual checkbox markers: ☐ ☑ ☒ ⬜ ❏ or [ ] / [x] prefixes.
        if (preg_match('/[\x{2610}\x{2611}\x{2612}\x{2B1C}\x{274F}]/u', $raw)) {
            $checkbox = true;
            $raw = preg_replace('/[\x{2610}\x{2611}\x{2612}\x{2B1C}\x{274F}]/u', '', $raw) ?? $raw;
        } elseif (preg_match('/^\s*\[\s*([xX]?)\s*\]\s*(.+)$/', $raw, $m)) {
            $checkbox = true;
            $raw = trim($m[2]);
        }

        $raw = trim($raw);

        $pPr = $p->getElementsByTagNameNS(self::W_NS, 'pPr')->item(0);
        $isList = false;
        $level = null;

        if ($pPr instanceof \DOMElement) {
            $isList = $pPr->getElementsByTagNameNS(self::W_NS, 'numPr')->length > 0;

            $pStyle = $pPr->getElementsByTagNameNS(self::W_NS, 'pStyle')->item(0);
            if ($pStyle instanceof \DOMElement) {
                $styleName = $pStyle->getAttributeNS(self::W_NS, 'val');
                if (preg_match('/^Heading\s?([1-9])$/i', $styleName, $m)) {
                    $level = (int) $m[1];
                }
            }
        }

        if ($raw === '') {
            return null; // empty paragraph, silently dropped
        }

        if ($level !== null) {
            return ['type' => 'heading', 'text' => $raw, 'level' => $level];
        }

        if ($isList) {
            return ['type' => 'list', 'text' => $raw, 'checkbox' => $checkbox];
        }

        return ['type' => 'paragraph', 'text' => $raw];
    }

    protected function paragraphText(\DOMElement $p): string
    {
        $text = '';
        foreach ($p->getElementsByTagNameNS(self::W_NS, 't') as $t) {
            $text .= $t->textContent;
        }

        // Tabs become spaces so "A \t B" reads as one line.
        $tabCount = $p->getElementsByTagNameNS(self::W_NS, 'tab')->length;
        if ($tabCount > 0) {
            $text = str_replace("\t", ' ', $text);
        }

        return trim($text);
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function parseTable(\DOMElement $tbl): array
    {
        $rows = [];

        foreach ($tbl->getElementsByTagNameNS(self::W_NS, 'tr') as $tr) {
            $cells = [];

            foreach ($tr->getElementsByTagNameNS(self::W_NS, 'tc') as $tc) {
                $cellText = '';
                foreach ($tc->getElementsByTagNameNS(self::W_NS, 'p') as $cp) {
                    $t = $this->paragraphText($cp);
                    if ($t === '') {
                        continue;
                    }
                    $cellText = $cellText === '' ? $t : $cellText . ' ' . $t;
                }

                if (trim($cellText) !== '') {
                    $cells[] = trim($cellText);
                }
            }

            if (! empty($cells)) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array>  $blocks
     * @return array{title: string, fields: array, warnings: array}
     */
    protected function blocksToFields(array $blocks): array
    {
        $fields = [];
        $warnings = [];
        $title = '';
        $lastFieldIndex = -1;
        $pendingList = [];

        $flushList = function () use (&$pendingList, &$fields, &$warnings, &$lastFieldIndex) {
            if (empty($pendingList)) {
                return;
            }

            $items = $pendingList;
            $pendingList = [];

            $hasCheckbox = collect($items)->contains(fn ($i) => $i['checkbox'] === true);
            $options = array_values(array_filter(
                array_map(fn ($i) => trim($i['text']), $items),
                fn ($t) => $t !== ''
            ));

            if (empty($options)) {
                return;
            }

            $label = 'Please select from the following';
            $attached = false;

            // Attach to the immediately preceding question field when its
            // type can legally accept options.
            if ($lastFieldIndex >= 0 && isset($fields[$lastFieldIndex])) {
                $prev = &$fields[$lastFieldIndex];
                if (in_array($prev['type'], self::CHOICE_TYPES, true) && empty($prev['options'])) {
                    $prev['options'] = $this->optionsFromLabels($options);
                    $prev['origin'] = 'docx:list';
                    $prev['validation'] = $this->validationFor('checkbox', $prev['options']);
                    $attached = true;
                } elseif (in_array($prev['type'], ['text', 'textarea'], true)) {
                    $prev['type'] = $hasCheckbox ? 'checkbox' : (count($options) > 6 ? 'select' : 'radio');
                    $prev['options'] = $this->optionsFromLabels($options);
                    $prev['origin'] = 'docx:list';
                    $prev['confidence'] = self::CONFIDENCE_HIGH;
                    $prev['validation'] = $this->validationFor($prev['type'], $prev['options']);
                    $attached = true;
                }
            }

            if (! $attached) {
                $label = $this->lastSectionLabel($fields) ?: $label;
                $warnings[] = 'A bulleted list with no preceding question was turned into a checkbox field ("' . Str::limit($label, 40) . '").';
                $type = $hasCheckbox ? 'checkbox' : (count($options) > 6 ? 'select' : 'radio');
                $fields[] = $this->buildField(
                    label: $label,
                    type: $type,
                    options: $options,
                    confidence: self::CONFIDENCE_MEDIUM,
                    origin: 'docx:list',
                    required: false,
                );
                $lastFieldIndex = count($fields) - 1;
            }
        };

        foreach ($blocks as $block) {
            switch ($block['type']) {
                case 'heading':
                    $flushList();

                    if ($title === '' && empty($fields)) {
                        $title = $block['text'];
                        break;
                    }

                    $fields[] = $this->buildField(
                        label: $block['text'],
                        type: 'section',
                        options: [],
                        confidence: self::CONFIDENCE_HIGH,
                        origin: 'docx:heading',
                        required: false,
                        settings: ['is_section' => true],
                    );
                    $lastFieldIndex = count($fields) - 1;
                    break;

                case 'paragraph':
                    $flushList();

                    $inferred = $this->inferFieldType($block['text']);
                    $fields[] = $this->buildField(
                        label: $block['text'],
                        type: $inferred['type'],
                        options: $inferred['options'],
                        confidence: $inferred['confidence'],
                        origin: 'docx:paragraph',
                        required: false,
                        helpText: '',
                    );
                    $lastFieldIndex = count($fields) - 1;
                    break;

                case 'list':
                    $pendingList[] = $block;
                    break;

                case 'table':
                    $flushList();

                    foreach ($block['rows'] as $cells) {
                        $label = array_shift($cells);
                        $options = $cells;

                        $checkbox = false;
                        foreach ($options as $o) {
                            if (preg_match('/[\x{2610}\x{2611}\x{2612}\x{2B1C}\x{274F}]/u', $o)) {
                                $checkbox = true;
                            }
                        }
                        $options = array_map(fn ($o) => trim(preg_replace('/[\x{2610}\x{2611}\x{2612}\x{2B1C}\x{274F}]/u', '', $o)), $options);

                        if (count($options) === 2) {
                            $joined = mb_strtolower(implode(' ', $options));
                            if (Str::contains($joined, ['yes', 'no', 'true', 'false', 'agree', 'disagree'])) {
                                $checkbox = false;
                            }
                        }

                        $fields[] = $this->buildField(
                            label: $label,
                            type: $checkbox ? 'checkbox' : 'radio',
                            options: $options,
                            confidence: self::CONFIDENCE_HIGH,
                            origin: 'docx:table',
                            required: false,
                        );
                        $lastFieldIndex = count($fields) - 1;
                    }
                    break;
            }

            if (count($fields) >= self::MAX_FIELDS) {
                $warnings[] = 'Stopped parsing after ' . self::MAX_FIELDS . ' fields to keep the form manageable.';
                break;
            }
        }

        $flushList();

        return ['title' => $title, 'fields' => $fields, 'warnings' => $warnings];
    }

    protected function lastSectionLabel(array $fields): string
    {
        for ($i = count($fields) - 1; $i >= 0; $i--) {
            if (($fields[$i]['type'] ?? '') === 'section') {
                return $fields[$i]['label'];
            }
        }

        return '';
    }

    /* -----------------------------------------------------------------
     | XLSX
     | ----------------------------------------------------------------- */

    public function parseXlsx(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not read the spreadsheet: ' . $e->getMessage());
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        // Trim trailing empty rows.
        while (! empty($rows) && $this->isRowEmpty(end($rows))) {
            array_pop($rows);
        }

        if (count($rows) < 2) {
            throw new \RuntimeException('The spreadsheet must contain at least a header row and one data row.');
        }

        $isTemplate = $this->detectTemplateLayout($rows[0]);

        return $isTemplate
            ? $this->parseTemplateSheet($rows)
            : $this->parseDataSheet($rows);
    }

    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function detectTemplateLayout(array $header): bool
    {
        $normalized = array_map(fn ($c) => mb_strtolower(trim((string) $c)), $header);
        $keys = ['type', 'label', 'required', 'options', 'placeholder', 'help_text', 'section'];

        foreach ($normalized as $cell) {
            if ($cell !== '' && in_array($cell, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    protected function parseTemplateSheet(array $rows): array
    {
        $header = array_map(fn ($c) => mb_strtolower(trim((string) $c)), $rows[0]);
        $col = array_flip($header);

        $fields = [];
        $warnings = [];
        $currentSection = null;

        foreach (array_slice($rows, 1) as $index => $row) {
            $label = trim((string) ($row[$col['label'] ?? -1] ?? ''));
            $isRequired = false;
            $label = $this->stripRequiredMarker($label, $isRequired);

            if ($label === '') {
                $warnings[] = 'Row ' . ($index + 2) . ' has no label and was skipped.';
                continue;
            }

            $type = $this->canonicalize((string) ($row[$col['type'] ?? -1] ?? 'text'));

            $section = trim((string) ($row[$col['section'] ?? -1] ?? ''));
            if ($section !== '' && $section !== $currentSection) {
                $fields[] = $this->buildField(
                    label: $section,
                    type: 'section',
                    options: [],
                    confidence: self::CONFIDENCE_HIGH,
                    origin: 'xlsx:template',
                    required: false,
                    settings: ['is_section' => true],
                );
                $currentSection = $section;
            }

            $rawOptions = trim((string) ($row[$col['options'] ?? -1] ?? ''));
            $options = $rawOptions === ''
                ? []
                : array_values(array_filter(
                    array_map('trim', preg_split('/\s*[,\n|]\s*/', $rawOptions) ?: []),
                    fn ($o) => $o !== ''
                ));

            if (in_array($type, self::CHOICE_TYPES, true) && empty($options)) {
                $warnings[] = "Field '{$label}' is type {$type} but has no options - treated as text.";
                $type = 'text';
            }

            $required = $this->parseTruthy($row[$col['required'] ?? -1] ?? '') || $isRequired;

            $fields[] = $this->buildField(
                label: $label,
                type: $type,
                options: $options,
                confidence: self::CONFIDENCE_HIGH,
                origin: 'xlsx:template',
                required: $required,
                placeholder: trim((string) ($row[$col['placeholder'] ?? -1] ?? '')),
                helpText: trim((string) ($row[$col['help_text'] ?? -1] ?? '')),
            );

            if (count($fields) >= self::MAX_FIELDS) {
                $warnings[] = 'Stopped after ' . self::MAX_FIELDS . ' fields.';
                break;
            }
        }

        return [
            'title' => '',
            'description' => '',
            'layout' => 'xlsx-template',
            'fields' => $fields,
            'warnings' => $warnings,
        ];
    }

    protected function parseDataSheet(array $rows): array
    {
        $headers = array_shift($rows);
        $warnings = [];
        $fields = [];
        $currentSection = null;

        foreach ($headers as $colIndex => $headerRaw) {
            $label = trim((string) $headerRaw);
            $requiredFlag = false;
            $label = $this->stripRequiredMarker($label, $requiredFlag);

            if ($label === '') {
                $hasData = false;
                foreach ($rows as $row) {
                    if (trim((string) ($row[$colIndex] ?? '')) !== '') {
                        $hasData = true;
                        break;
                    }
                }
                if ($hasData) {
                    $warnings[] = "Column " . $colIndex + 1 . " has no header but contains data - skipped.";
                }
                continue;
            }

            $values = [];
            foreach ($rows as $row) {
                $values[] = $this->normalizeCellValue($row[$colIndex] ?? null);
            }

            $nonEmptyValues = array_values(array_filter($values, fn ($v) => trim($v) !== ''));

            if (empty($nonEmptyValues)) {
                $warnings[] = "Column '{$label}' has no data and was skipped.";
                continue;
            }

            if (Str::lower($label) === 'section' || Str::lower($label) === 'group') {
                // A "section" column names the current section for subsequent columns.
                $section = '';
                foreach ($nonEmptyValues as $v) {
                    $section = $v;
                    break;
                }
                if ($section !== '' && $section !== $currentSection) {
                    $fields[] = $this->buildField(
                        label: $section,
                        type: 'section',
                        options: [],
                        confidence: self::CONFIDENCE_HIGH,
                        origin: 'xlsx:data',
                        required: false,
                        settings: ['is_section' => true],
                    );
                    $currentSection = $section;
                }
                continue;
            }

            $inferred = $this->inferTypeFromValues($label, $values);

            $fields[] = $this->buildField(
                label: $label,
                type: $inferred['type'],
                options: $inferred['options'],
                confidence: $inferred['confidence'],
                origin: 'xlsx:data',
                required: $requiredFlag || $inferred['required'],
            );

            if (count($fields) >= self::MAX_FIELDS) {
                $warnings[] = 'Stopped after ' . self::MAX_FIELDS . ' fields.';
                break;
            }
        }

        return [
            'title' => '',
            'description' => '',
            'layout' => 'xlsx-data',
            'fields' => $fields,
            'warnings' => $warnings,
        ];
    }

    protected function normalizeCellValue(mixed $cell): string
    {
        if ($cell === null) {
            return '';
        }

        if ($cell instanceof \DateTimeInterface) {
            return $cell->format('Y-m-d H:i:s');
        }

        if (is_bool($cell)) {
            return $cell ? 'yes' : 'no';
        }

        return trim((string) $cell);
    }

    /**
     * Infer type from a real set of respondent values (data layout).
     *
     * @param  array<int, string>  $values  includes '' for blank cells
     * @return array{type: string, options: array, confidence: string, required: bool}
     */
    protected function inferTypeFromValues(string $label, array $values): array
    {
        $nonEmpty = array_values(array_filter($values, fn ($v) => trim($v) !== ''));
        $required = count($nonEmpty) === count($values);

        // Distinct values in original casing (used as option labels), keyed by
        // their lowercase form so comparisons ignore case.
        $distinct = [];
        foreach ($nonEmpty as $v) {
            $key = mb_strtolower(trim($v));
            if (! array_key_exists($key, $distinct)) {
                $distinct[$key] = trim($v);
            }
        }

        $labels = array_values($distinct);
        $keys = array_keys($distinct);
        $distinctCount = count($distinct);
        $total = count($nonEmpty);

        // A strong label signal always wins over value-based guessing
        // (e.g. "Phone Number" is a phone even if values look numeric).
        $labelInference = $this->inferFieldType($label);
        if ($labelInference['confidence'] === self::CONFIDENCE_HIGH) {
            return [
                'type' => $labelInference['type'],
                'options' => [],
                'confidence' => self::CONFIDENCE_HIGH,
                'required' => $required,
            ];
        }

        // Medium label signals for free-text / numeric columns are trustworthy too.
        if (in_array($labelInference['type'], ['textarea', 'number'], true)) {
            return [
                'type' => $labelInference['type'],
                'options' => [],
                'confidence' => self::CONFIDENCE_MEDIUM,
                'required' => $required,
            ];
        }

        if ($this->allMatch($keys, '/^[^@\s]+@[^@\s]+\.[^@\s]+$/')) {
            return ['type' => 'email', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH, 'required' => $required];
        }

        if ($this->allMatch($keys, '/^[0-9+\-().\s]{7,}$/')) {
            return ['type' => 'phone', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH, 'required' => $required];
        }

        if ($this->allMatch($keys, '/^(\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})$/')) {
            return ['type' => 'date', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH, 'required' => $required];
        }

        if ($this->allMatch($keys, '/^-?\d+(\.\d+)?$/')) {
            return ['type' => 'number', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH, 'required' => $required];
        }

        // Boolean-ish columns become radio buttons with their distinct values.
        if ($distinctCount === 2) {
            $bool = ['yes', 'no', 'true', 'false', 'y', 'n', '1', '0', 'male', 'female', 'agree', 'disagree'];
            if (count(array_intersect($keys, $bool)) >= 2) {
                return ['type' => 'radio', 'options' => $labels, 'confidence' => self::CONFIDENCE_HIGH, 'required' => $required];
            }
        }

        // Categorical columns with repeated values become selects.
        if ($distinctCount >= 2 && $distinctCount <= 20 && ($distinctCount / $total) < 0.6) {
            return ['type' => 'select', 'options' => $labels, 'confidence' => self::CONFIDENCE_MEDIUM, 'required' => $required];
        }

        $longest = max(array_map('strlen', $values));
        $type = $longest > 200 ? 'textarea' : 'text';
        $confidence = $longest > 200 ? self::CONFIDENCE_MEDIUM : self::CONFIDENCE_LOW;

        return ['type' => $type, 'options' => [], 'confidence' => $confidence, 'required' => $required];
    }

    protected function allMatch(array $values, string $regex): bool
    {
        foreach ($values as $value) {
            if ($value === '' || ! preg_match($regex, $value)) {
                return false;
            }
        }

        return true;
    }

    /* -----------------------------------------------------------------
     | Field building / type inference
     | ----------------------------------------------------------------- */

    /**
     * Heuristic type inference for a question label (and optional options).
     *
     * @param  array<int, string>  $existingOptions
     * @return array{type: string, options: array, confidence: string}
     */
    public function inferFieldType(string $label, array $existingOptions = []): array
    {
        $s = mb_strtolower($label);

        if (preg_match('/\b(emails?|e-?mail)\b|@/', $s)) {
            return ['type' => 'email', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH];
        }
        if (preg_match('/\b(phones?|mobile|telephones?|cell)\b|contact\s*number/', $s)) {
            return ['type' => 'phone', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH];
        }
        if (preg_match('/\b(dates?|dob|birthday|birth\s*date)\b|\bwhen\b/', $s)) {
            return ['type' => 'date', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH];
        }
        if (preg_match('/\b(urls?|website|weblink|web\s*link|links?)\b/', $s)) {
            return ['type' => 'url', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH];
        }
        if (preg_match('/\b(uploads?|attach(ment|ments)?|resumes?|cv|files?|photo(s)?|images?|documents?|pdf)\b/', $s)) {
            return ['type' => 'file', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH];
        }
        if (preg_match('/\b(ratings?|satisfaction|how\s*satisfied|stars?)\b/', $s)) {
            return ['type' => 'rating', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH];
        }
        if (preg_match('/\b(password|passcode)\b/', $s)) {
            return ['type' => 'password', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH];
        }

        if (! empty($existingOptions)) {
            $joined = mb_strtolower(implode(' ', $existingOptions));
            $checkbox = Str::contains($joined, ['yes', 'no', 'true', 'false'])
                && preg_match('/\b(check|agree|accept|confirm|select all|which of)\b/', $s);
            $type = $checkbox ? 'checkbox' : (count($existingOptions) > 6 ? 'select' : 'radio');

            return ['type' => $type, 'options' => $existingOptions, 'confidence' => self::CONFIDENCE_HIGH];
        }

        if (preg_match('/\b(full\s*name|your\s*name|first\s*name|last\s*name|names?)\b/', $s) && ! preg_match('/\bteam\b|company\b|org\b/', $s)) {
            return ['type' => 'text', 'options' => [], 'confidence' => self::CONFIDENCE_HIGH];
        }

        if (preg_match('/\b(comments?|feedback|messages?|describe|description|details|notes?|tell\s*us|explain|suggestions?|opinions?|experience)\b/', $s)) {
            return ['type' => 'textarea', 'options' => [], 'confidence' => self::CONFIDENCE_MEDIUM];
        }

        if (preg_match('/\b(how\s*many|how\s*much|ages?|number\s*of|quantities?|years\s*of)\b/', $s)) {
            return ['type' => 'number', 'options' => [], 'confidence' => self::CONFIDENCE_MEDIUM];
        }

        if (preg_match('/^(are you|is |do you|would you|have you|did you)/i', $label)) {
            return ['type' => 'radio', 'options' => ['Yes', 'No'], 'confidence' => self::CONFIDENCE_MEDIUM];
        }

        return ['type' => 'text', 'options' => [], 'confidence' => self::CONFIDENCE_LOW];
    }

    /**
     * @return array{label: string, required: bool}
     */
    protected function stripRequiredMarker(string $label, bool &$required): string
    {
        $required = false;

        $label = preg_replace('/\s*\(\s*\*?\s*(required|mandatory)\s*\*?\s*\)\s*$/i', '', $label, -1, $n1);
        if ($n1 > 0) {
            $required = true;
        }

        $label = preg_replace('/\s+[*]\s*$/u', '', $label);
        $label = preg_replace('/\s+(required|mandatory)\s*$/i', '', $label, -1, $n2);
        if ($n2 > 0) {
            $required = true;
        }

        return trim($label);
    }

    protected function parseTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on', 'required', '✓', 'x'], true);
    }

    /**
     * @param  array<int, string>  $labels
     */
    protected function optionsFromLabels(array $labels): array
    {
        $options = [];
        $used = [];

        foreach ($labels as $label) {
            $value = Str::slug($label, '_');
            if ($value === '') {
                $value = 'option_' . Str::random(4);
            }

            $base = $value;
            $i = 1;
            while (in_array($value, $used, true)) {
                $value = $base . '_' . $i++;
            }
            $used[] = $value;

            $options[] = [
                'label' => Str::limit($label, 255),
                'value' => Str::limit($value, 255),
                'order' => count($options),
                'is_default' => false,
            ];
        }

        return $options;
    }

    protected function canonicalize(string $type): string
    {
        return app(FormSchemaService::class)->canonicalType($type === '' ? 'text' : $type);
    }

    /**
     * @param  array<int, string>  $options
     */
    protected function validationFor(string $type, array $options): array
    {
        switch ($type) {
            case 'email':
                return ['email' => true];
            case 'phone':
                return ['regex' => '/^[0-9+\-\s()]+$/'];
            case 'number':
            case 'range':
                return ['numeric' => true, 'min' => 0];
            case 'date':
                return ['date' => true];
            case 'url':
                return ['url' => true];
            case 'file':
                return ['file' => true, 'max' => 2048];
            case 'rating':
                return ['numeric' => true, 'min' => 1, 'max' => 5];
            case 'password':
                return ['min' => 8];
            case 'text':
                return ['min' => 1, 'max' => 255];
            case 'textarea':
                return ['min' => 1, 'max' => 1000];
            default:
                if (in_array($type, self::CHOICE_TYPES, true) && ! empty($options)) {
                    return ['in' => implode(',', array_column($options, 'value'))];
                }

                return [];
        }
    }

    /**
     * @param  array<int, string>  $options
     */
    protected function buildField(
        string $label,
        string $type,
        array $options,
        string $confidence,
        string $origin,
        bool $required = false,
        string $placeholder = '',
        string $helpText = '',
        array $settings = [],
    ): array {
        $optionObjects = ! empty($options) ? $this->optionsFromLabels($options) : [];

        return [
            'field_key' => $this->uniqueFieldKey($label),
            'label' => Str::limit($label, 255),
            'type' => $type,
            'placeholder' => Str::limit($placeholder, 255),
            'help_text' => Str::limit($helpText, 500),
            'default_value' => '',
            'validation' => $this->validationFor($type, $optionObjects),
            'settings' => $settings,
            'is_required' => $required,
            'is_visible' => true,
            'options' => $optionObjects,
            'order' => 0,
            'confidence' => $confidence,
            'origin' => $origin,
        ];
    }

    protected function uniqueFieldKey(string $candidate): string
    {
        $base = Str::slug($candidate, '_');
        $base = preg_replace('/[^a-z0-9_]/i', '', $base);

        if ($base === '') {
            $base = 'field';
        }

        return Str::limit(Str::lower($base), 100, '');
    }

    protected function titleFromFilename(string $path): string
    {
        $name = pathinfo($path, PATHINFO_FILENAME);

        return Str::title(str_replace(['_', '-'], ' ', $name));
    }

    /* -----------------------------------------------------------------
     | Hybrid AI refinement
     | ----------------------------------------------------------------- */

    /**
     * Optional second stage of the hybrid pipeline: ask the LLM to pick
     * types / required flags / validation for ambiguous fields only.
     * Structure (labels, options, order) always comes from the file.
     *
     * @param  array<int, array>  $fields
     * @return array{fields: array, changed: int, raw: string, model: string, attempts: int}
     */
    public function refineWithAi(array $fields, LlmClient $client, int $maxAttempts): array
    {
        $schemaService = app(FormSchemaService::class);
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $client->complete($this->refinementMessages($fields, $lastError));
            } catch (LlmException $e) {
                throw $e;
            }

            $decoded = $schemaService->extractJson($response['content']);

            if ($decoded === null || ! isset($decoded['fields']) || ! is_array($decoded['fields'])) {
                $lastError = 'The response was not valid JSON with a "fields" array.';
                continue;
            }

            [$updated, $changed] = $this->mergeRefinement($fields, $decoded['fields']);

            return [
                'fields' => $updated,
                'changed' => $changed,
                'raw' => $response['content'],
                'model' => $response['model'],
                'attempts' => $attempt,
            ];
        }

        throw new \RuntimeException(
            'AI refinement failed after ' . $maxAttempts . ' attempt(s). ' . ($lastError ?? '')
        );
    }

    /**
     * @param  array<int, array>  $fields
     * @return array<int, array{role: string, content: string}>
     */
    protected function refinementMessages(array $fields, ?string $lastError): array
    {
        $listing = array_map(function ($field) {
            return [
                'field_key' => $field['field_key'],
                'label' => $field['label'],
                'current_type' => $field['type'],
                'options' => array_column($field['options'] ?? [], 'label'),
            ];
        }, $fields);

        $system = <<<PROMPT
        You are an expert form designer refining an auto-parsed form. The form's structure
        (fields, labels, options, order) came from a Word/Excel document and is FINAL.
        Your only job is to improve each field's TYPE, REQUIRED flag and VALIDATION rules.

        RETURN ONLY A SINGLE VALID JSON OBJECT:
        {
          "fields": [
            {
              "field_key": "the unchanged key",
              "type": "one allowed type",
              "is_required": true,
              "validation": { "min": 1, "max": 255 }
            }
          ]
        }

        Allowed field types: text, email, number, phone, textarea, select, radio, checkbox,
        file, date, time, datetime, color, range, url, password, hidden, rating.

        Rules:
        - Do NOT invent new field_keys and do NOT reorder or drop fields.
        - Do NOT change a field to select/radio/checkbox unless the file already provides options.
        - "validation" keys are limited to: min, max, minlength, maxlength, email, numeric, url,
          regex, mimes, unique, in, date, array, file.
        - email -> {"email": true}; number -> {"numeric": true, "min": 0}; file -> {"file": true, "max": 2048}.
        - A heading/section/divider field stays as-is with empty validation.
        PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => 'Refine these parsed fields:' . "\n\n" . json_encode(['fields' => $listing], JSON_UNESCAPED_UNICODE)],
        ];

        if ($lastError) {
            $messages[] = ['role' => 'assistant', 'content' => '{}'];
            $messages[] = ['role' => 'user', 'content' => "Your previous response was rejected: {$lastError}\n\nReturn only a single valid JSON object matching the contract."];
        }

        return $messages;
    }

    /**
     * @param  array<int, array>  $fields
     * @param  array<int, mixed>  $refinements
     * @return array{0: array<int, array>, 1: int}
     */
    protected function mergeRefinement(array $fields, array $refinements): array
    {
        $byKey = [];
        foreach ($refinements as $refinement) {
            if (! is_array($refinement)) {
                continue;
            }
            $key = (string) ($refinement['field_key'] ?? '');
            if ($key !== '') {
                $byKey[$key] = $refinement;
            }
        }

        $changed = 0;

        foreach ($fields as &$field) {
            $ref = $byKey[$field['field_key']] ?? null;
            if (! is_array($ref)) {
                continue;
            }

            $newType = $this->canonicalize((string) ($ref['type'] ?? 'text'));

            // Never convert a field to a choice type without options.
            if (in_array($newType, self::CHOICE_TYPES, true) && empty($field['options'] ?? [])) {
                $newType = 'text';
            }

            // Keep layout types as-is.
            if (in_array($field['type'], ['section', 'heading', 'paragraph', 'divider'], true)) {
                $newType = $field['type'];
            }

            $before = [$field['type'], $field['is_required'], $field['validation'] ?? []];
            $field['type'] = $newType;

            if (isset($ref['is_required'])) {
                $field['is_required'] = $this->boolValue($ref['is_required']);
            }

            $validation = $this->repairValidation($ref['validation'] ?? [], $newType, $field['options'] ?? []);
            if (in_array($newType, self::CHOICE_TYPES, true) && ! empty($field['options'] ?? [])) {
                $validation['in'] = implode(',', array_column($field['options'], 'value'));
            }
            $field['validation'] = $validation;

            $after = [$field['type'], $field['is_required'], $field['validation'] ?? []];

            if ($before !== $after) {
                $field['confidence'] = self::CONFIDENCE_HIGH;
                $field['refined_by_ai'] = true;
                $changed++;
            }
        }
        unset($field);

        return [$fields, $changed];
    }

    /**
     * @param  array<int, string>  $options
     */
    protected function repairValidation(mixed $raw, string $type, array $options): array
    {
        $allowed = FormSchemaService::ALLOWED_VALIDATION_KEYS;
        $validation = [];

        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                $key = (string) $key;

                if (! in_array($key, $allowed, true)) {
                    continue;
                }

                if (in_array($key, ['min', 'max', 'minlength', 'maxlength'], true)) {
                    $value = filter_var($value, FILTER_VALIDATE_INT);
                    if ($value !== false && $value >= 0) {
                        $validation[$key] = (int) $value;
                    }
                } elseif (in_array($key, ['email', 'numeric', 'url', 'date', 'array', 'file'], true)) {
                    if ($this->boolValue($value)) {
                        $validation[$key] = true;
                    }
                } else {
                    $value = is_array($value) ? implode(',', array_map('strval', $value)) : (string) $value;
                    if (trim($value) !== '') {
                        $validation[$key] = $value;
                    }
                }
            }
        }

        // Always align choice "in" rules with the actual options.
        if (in_array($type, self::CHOICE_TYPES, true)) {
            $values = array_column($options, 'value');
            if (! empty($values)) {
                $validation['in'] = implode(',', $values);
            }
        }

        return $validation;
    }

    protected function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }

        return in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
