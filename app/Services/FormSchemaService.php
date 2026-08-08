<?php

namespace App\Services;

use App\Exceptions\InvalidSchemaException;
use App\Exceptions\LlmException;
use Illuminate\Support\Str;

class FormSchemaService
{
    /** Field types the builder actually supports. Anything else is coerced. */
    public const ALLOWED_TYPES = [
        'text', 'email', 'number', 'phone', 'textarea', 'select', 'radio', 'checkbox',
        'file', 'date', 'time', 'datetime', 'color', 'range', 'url', 'password',
        'heading', 'paragraph', 'divider', 'section', 'rating', 'hidden',
    ];

    /** Types that must carry options. */
    public const CHOICE_TYPES = ['select', 'radio', 'checkbox'];

    /** Validation keys the persistence layer understands. */
    public const ALLOWED_VALIDATION_KEYS = [
        'min', 'max', 'minlength', 'maxlength', 'email', 'numeric', 'url', 'regex',
        'mimes', 'unique', 'in', 'date', 'array', 'file',
    ];

    public const SETTINGS_DEFAULTS = [
        'theme' => 'default',
        'layout' => 'vertical',
        'show_progress' => true,
        'recaptcha_enabled' => false,
        'submit_button_text' => 'Submit',
        'success_message' => 'Form submitted successfully!',
        'redirect_url' => null,
    ];

    public const SETTINGS_ENUMS = [
        'theme' => ['default', 'dark', 'minimal', 'modern'],
        'layout' => ['vertical', 'horizontal', 'inline'],
    ];

    /**
     * Map likely "hallucinated" type names to a real builder type.
     * Unknown types default to 'text' (never stored as-is).
     */
    protected const TYPE_SYNONYMS = [
        'string' => 'text', 'singleline' => 'text', 'shorttext' => 'text', 'fullname' => 'text',
        'name' => 'text', 'input' => 'text', 'free_text' => 'text', 'freetext' => 'text',
        'e-mail' => 'email', 'email_address' => 'email', 'mail' => 'email',
        'tel' => 'phone', 'telephone' => 'phone', 'mobile' => 'phone', 'phone_number' => 'phone',
        'integer' => 'number', 'int' => 'number', 'numeric' => 'number', 'amount' => 'number',
        'longtext' => 'textarea', 'long_text' => 'textarea', 'multiline' => 'textarea',
        'multiline_text' => 'textarea', 'description' => 'textarea', 'message' => 'textarea',
        'dropdown' => 'select', 'menu' => 'select', 'combobox' => 'select', 'single_select' => 'select',
        'multi_select' => 'checkbox', 'multiselect' => 'checkbox', 'checkbox_group' => 'checkbox',
        'choice' => 'radio', 'single_choice' => 'radio', 'options' => 'radio',
        'upload' => 'file', 'document' => 'file', 'attachment' => 'file', 'pdf' => 'file',
        'image' => 'file', 'photo' => 'file', 'file_upload' => 'file',
        'birthday' => 'date', 'dob' => 'date', 'date_of_birth' => 'date',
        'datetime_local' => 'datetime', 'datetime-local' => 'datetime',
        'star' => 'rating', 'stars' => 'rating', 'score' => 'rating',
        'link' => 'url', 'website' => 'url',
        'header' => 'heading', 'title' => 'heading', 'section_title' => 'section',
        'group' => 'section', 'separator' => 'divider', 'line' => 'divider',
        'pass' => 'password', 'secret' => 'password',
        'boolean' => 'checkbox', 'yes_no' => 'radio', 'agree' => 'checkbox',
    ];

    /**
     * Run the full generate-with-retries loop. Each retry re-asks the model
     * with the parse/validation error appended, so it can repair its output.
     *
     * @param  array|null  $currentSchema  existing form schema (edit mode only)
     * @return array{
     *   schema: array,
     *   raw: string,
     *   model: string,
     *   usage: array,
     *   latency_ms: int,
     *   attempts: int,
     * }
     */
    public function generateWithRetries(string $instruction, string $mode, ?array $currentSchema, LlmClient $client, int $maxAttempts): array
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $messages = $this->buildMessages($instruction, $mode, $currentSchema, $lastError);

            try {
                $response = $client->complete($messages);
            } catch (LlmException $e) {
                // Network/provider failure: only one attempt is useful - a retry
                // would almost certainly hit the same outage.
                throw $e;
            }

            $decoded = $this->extractJson($response['content']);

            if ($decoded === null) {
                $lastError = 'The response was not valid JSON. Return a single JSON object only.';
                continue;
            }

            try {
                $schema = $this->validateAndRepair($decoded, $mode);
            } catch (InvalidSchemaException $e) {
                $lastError = $e->getMessage();
                continue;
            }

            return [
                'schema' => $schema,
                'raw' => $response['content'],
                'model' => $response['model'],
                'usage' => $response['usage'],
                'latency_ms' => $response['latency_ms'],
                'attempts' => $attempt,
            ];
        }

        throw new InvalidSchemaException(
            'Could not produce a valid form schema after ' . $maxAttempts . ' attempt(s). ' . ($lastError ?? '')
        );
    }

    /**
     * Build the message list sent to the model.
     *
     * @param  string|null  $lastError  parse/validation feedback from a previous attempt
     * @return array<int, array{role: string, content: string}>
     */
    public function buildMessages(string $instruction, string $mode, ?array $currentSchema, ?string $lastError = null): array
    {
        $system = $mode === 'edit'
            ? $this->systemPromptForEdit()
            : $this->systemPromptForCreate();

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $instruction],
        ];

        if ($mode === 'edit' && ! empty($currentSchema)) {
            $messages[] = [
                'role' => 'user',
                'content' => "This is the CURRENT form schema. Apply the requested change and return the COMPLETE updated schema, preserving every existing field (field_keys and options) unless the instruction requires otherwise:\n\n"
                    . json_encode($currentSchema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ];
        }

        if ($lastError) {
            $messages[] = [
                'role' => 'assistant',
                'content' => '{}',
            ];
            $messages[] = [
                'role' => 'user',
                'content' => "Your previous response was rejected: {$lastError}\n\nReturn only a single valid JSON object matching the required contract.",
            ];
        }

        return $messages;
    }

    public function systemPromptForCreate(): string
    {
        return $this->sharedContract() . <<<'PROMPT'

        The user wants a brand-new form. Generate a complete, production-ready form from their requirements.

        Design rules:
        - Include every field the user asked for plus any obviously implied fields (e.g. an "internship application" implies name, email, phone, education history, skills, resume upload).
        - Group related fields under "section" type fields (each section = a titled group), then immediately list its fields after it.
        - Prefer the most appropriate type; do not invent field types outside the allowed list.
        - Never invent security-sensitive fields (passwords, SSN, financial account numbers) unless explicitly requested.
        PROMPT;
    }

    public function systemPromptForEdit(): string
    {
        return $this->sharedContract() . <<<'PROMPT'

        The user wants to MODIFY an existing form. You will be given the current form schema.

        Edit rules:
        - Return the COMPLETE updated schema, never a diff or partial list.
        - Preserve every existing field and its field_key unless the instruction explicitly removes or renames it.
        - Preserve existing options unless the change requires otherwise.
        - Apply the requested change faithfully: add sections/fields, toggle is_required, translate labels, etc.
        PROMPT;
    }

    protected function sharedContract(): string
    {
        $types = implode(', ', self::ALLOWED_TYPES);

        return <<<PROMPT
        You are an expert form designer. Convert natural-language requests into complete, editable form schemas.

        RETURN ONLY A SINGLE VALID JSON OBJECT. No markdown, no code fences, no explanation, no trailing text. The object must match this contract exactly:

        {
          "title": "short, descriptive form title",
          "description": "one or two sentence description or empty string",
          "settings": {
            "theme": "default",
            "layout": "vertical",
            "show_progress": true,
            "recaptcha_enabled": false,
            "submit_button_text": "Submit",
            "success_message": "Form submitted successfully!",
            "redirect_url": null
          },
          "fields": [
            {
              "field_key": "unique_snake_case_ascii",
              "label": "Human readable label",
              "type": "one allowed type below",
              "placeholder": "example placeholder",
              "help_text": "short helper or empty string",
              "is_required": true,
              "is_visible": true,
              "default_value": "",
              "validation": { "min": 1, "max": 255 },
              "options": [ { "label": "Option A", "value": "option_a" } ]
            }
          ]
        }

        Allowed field types (use ONLY these): {$types}.

        Rules:
        - "options" must be non-empty for select, radio and checkbox. Option values are snake_case/ascii.
        - "validation" keys are limited to: min, max, minlength, maxlength, email, numeric, url, regex, mimes, unique, in, date, array, file. Use rules appropriate to the type (email fields get "email": true, numeric get "numeric": true, file uploads get "mimes" and "max" in KB, text gets min/max length).
        - Always provide a meaningful placeholder.
        - field_key must be unique, snake_case, ASCII.
        - Labels should be clear and concise.
        PROMPT;
    }

    /**
     * Validate and canonicalize decoded JSON into the exact contract shape.
     * Throws InvalidSchemaException only when the output is unrecoverable;
     * otherwise repairs it (type coercion, key generation, defaulting).
     *
     * @return array{title: string, description: string, settings: array, fields: array}
     */
    public function validateAndRepair(mixed $input, string $mode = 'create'): array
    {
        if (! is_array($input)) {
            throw new InvalidSchemaException('The response was not a JSON object.');
        }

        $title = $this->stringValue($input['title'] ?? null, 'Untitled Form');
        $title = Str::limit($title, 255);

        $description = $this->stringValue($input['description'] ?? null, '');
        $description = Str::limit($description, 1000);

        $settings = $this->repairSettings($input['settings'] ?? []);

        $fields = [];
        if (isset($input['fields']) && is_array($input['fields'])) {
            $fields = $this->repairFields($input['fields']);
        }

        if ($mode === 'create' && empty($fields)) {
            throw new InvalidSchemaException('The response contained no fields.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'settings' => $settings,
            'fields' => $fields,
        ];
    }

    /**
     * Pull a valid JSON object out of an LLM response that may contain
     * markdown fences, leading prose, trailing text, etc.
     */
    public function extractJson(string $text): ?array
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        // Try the raw text first.
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Strip markdown code fences.
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $text, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Fall back to the outermost JSON object in the text.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Normalize a decoded form JSON into a builder-ready schema array.
     */
    public function toBuilderSchema(array $decoded): array
    {
        return $this->validateAndRepair($decoded, 'create');
    }

    protected function repairSettings(mixed $settings): array
    {
        $result = self::SETTINGS_DEFAULTS;

        if (! is_array($settings)) {
            return $result;
        }

        foreach (self::SETTINGS_DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $settings)) {
                continue;
            }

            $value = $settings[$key];

            if (in_array($key, ['show_progress', 'recaptcha_enabled'], true)) {
                $result[$key] = $this->boolValue($value, (bool) $default);
            } elseif (isset(self::SETTINGS_ENUMS[$key])) {
                $result[$key] = in_array($value, self::SETTINGS_ENUMS[$key], true)
                    ? $value
                    : $default;
            } elseif ($value === null || is_scalar($value) || (is_array($value) && isset($value[0]))) {
                $result[$key] = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
            }
        }

        return $result;
    }

    protected function repairFields(array $rawFields): array
    {
        $usedKeys = [];
        $fields = [];

        foreach ($rawFields as $i => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $label = $this->stringValue($raw['label'] ?? null, 'Field ' . ($i + 1));
            $label = Str::limit($label, 255);

            $type = $this->coerceType($raw['type'] ?? null);

            $fieldKey = $this->stringValue($raw['field_key'] ?? null, '');
            $fieldKey = $this->uniqueFieldKey($fieldKey !== '' ? $fieldKey : $label, $usedKeys);

            $options = $this->repairOptions($raw['options'] ?? [], $type, $label);
            $validation = $this->repairValidation($raw['validation'] ?? [], $type, $options);

            $fields[] = [
                'field_key' => $fieldKey,
                'label' => $label,
                'type' => $type,
                'placeholder' => $this->stringValue($raw['placeholder'] ?? null, ''),
                'help_text' => $this->stringValue($raw['help_text'] ?? null, ''),
                'default_value' => $this->stringValue($raw['default_value'] ?? null, ''),
                'is_required' => $this->boolValue($raw['is_required'] ?? false, false),
                'is_visible' => $this->boolValue($raw['is_visible'] ?? true, true),
                'validation' => $validation,
                'settings' => is_array($raw['settings'] ?? null) ? $raw['settings'] : [],
                'options' => $options,
            ];
        }

        return $fields;
    }

    protected function coerceType(mixed $type): string
    {
        if (! is_string($type)) {
            return 'text';
        }

        $type = Str::lower(trim($type));

        if (in_array($type, self::ALLOWED_TYPES, true)) {
            return $type;
        }

        return self::TYPE_SYNONYMS[$type] ?? 'text';
    }

    protected function repairOptions(mixed $raw, string $type, string $label): array
    {
        $options = [];
        $seen = [];

        if (is_array($raw)) {
            foreach ($raw as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $optionLabel = $this->stringValue($option['label'] ?? null, $this->stringValue($option['value'] ?? null, ''));
                if ($optionLabel === '') {
                    continue;
                }

                $optionValue = $this->stringValue($option['value'] ?? null, '');
                if ($optionValue === '' || $optionValue === null) {
                    $optionValue = Str::slug($optionLabel, '_');
                }
                if ($optionValue === '') {
                    $optionValue = 'option_' . Str::random(4);
                }

                $key = Str::lower($optionValue);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $options[] = [
                    'label' => Str::limit($optionLabel, 255),
                    'value' => Str::limit($optionValue, 255),
                    'order' => count($options),
                    'is_default' => $this->boolValue($option['is_default'] ?? false, false),
                ];
            }
        }

        // A choice field without options is unusable - generate sensible ones
        // from the label so the schema stays valid.
        if (in_array($type, self::CHOICE_TYPES, true) && empty($options)) {
            $options = [
                [
                    'label' => 'Yes',
                    'value' => 'yes',
                    'order' => 0,
                    'is_default' => false,
                ],
                [
                    'label' => 'No',
                    'value' => 'no',
                    'order' => 1,
                    'is_default' => false,
                ],
            ];
        }

        return $options;
    }

    protected function repairValidation(mixed $raw, string $type, array $options): array
    {
        $validation = [];

        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                $key = (string) $key;

                if (! in_array($key, self::ALLOWED_VALIDATION_KEYS, true)) {
                    continue;
                }

                if (in_array($key, ['min', 'max', 'minlength', 'maxlength'], true)) {
                    $value = filter_var($value, FILTER_VALIDATE_INT);
                    if ($value !== false && $value >= 0) {
                        $validation[$key] = (int) $value;
                    }
                } elseif (in_array($key, ['email', 'numeric', 'url', 'date', 'array', 'file'], true)) {
                    if ($this->boolValue($value, true)) {
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

        // Keep choice-field "in" rules in sync with the options we actually keep.
        if (in_array($type, self::CHOICE_TYPES, true)) {
            $values = array_column($options, 'value');
            if (! empty($values)) {
                $validation['in'] = implode(',', $values);
            }
        }

        return $validation;
    }

    protected function uniqueFieldKey(string $candidate, array &$used): string
    {
        $base = Str::slug($candidate, '_');
        $base = preg_replace('/[^a-z0-9_]/i', '', $base);

        if ($base === '') {
            $base = 'field';
        }

        $key = Str::lower($base);
        $suffix = 1;

        while (in_array($key, $used, true)) {
            $key = Str::lower($base) . '_' . $suffix++;
        }

        $used[] = $key;

        return Str::limit($key, 100, '');
    }

    protected function stringValue(mixed $value, string $default): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    protected function boolValue(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return in_array(Str::lower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }
}
