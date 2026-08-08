<?php

/*
 * Regenerates the sample import files used by the PHPUnit parser tests and
 * available for manual testing of the import feature:
 *
 *   tests/fixtures/registration-form.docx   - Word form (headings, lists, table)
 *   tests/fixtures/feedback-template.xlsx   - documented template layout
 *   tests/fixtures/survey-data.xlsx         - plain header-row / data layout
 *
 * Usage: php tests/fixtures/make-fixtures.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

$dir = __DIR__;

/* ---------------- DOCX ---------------- */

$phpWord = new PhpWord();
$phpWord->addNumberingStyle('bulletList', [
    'type' => 'singleLevel',
    'levels' => [['format' => 'bullet', 'text' => '•', 'alignment' => 'left', 'left' => 360, 'hanging' => 360, 'tabPos' => 360, 'font' => 'Symbol']],
]);
$phpWord->addNumberingStyle('numberList', [
    'type' => 'singleLevel',
    'levels' => [['format' => 'decimal', 'text' => '%1.', 'alignment' => 'left', 'left' => 360, 'hanging' => 360, 'tabPos' => 360, 'font' => 'Arial']],
]);

$section = $phpWord->addSection();

$section->addText('Registration Form', null, 'Heading1');
$section->addText('Please fill in your details below.');
$section->addText('Full Name');
$section->addText('Email Address');
$section->addText('Phone Number');

$section->addText('Personal Details', null, 'Heading2');
$section->addText('Date of Birth');
$section->addText('Highest Degree');
$section->addListItem('High School', 0, null, 'numberList');
$section->addListItem('Bachelor', 0, null, 'numberList');
$section->addListItem('Master', 0, null, 'numberList');

$section->addText('Which sessions are you interested in?');
$section->addListItem('☐ Keynote', 0, null, 'bulletList');
$section->addListItem('☐ Workshops', 0, null, 'bulletList');
$section->addListItem('☐ Networking', 0, null, 'bulletList');

$section->addText('Additional Info', null, 'Heading2');
$section->addText('Comments');

$table = $section->addTable();
$row = $table->addRow();
$row->addCell()->addText('Dietary Requirements');
$row->addCell()->addText('☐ Vegetarian');
$row->addCell()->addText('☐ Vegan');
$row->addCell()->addText('☐ None');

$wordWriter = WordIOFactory::createWriter($phpWord, 'Word2007');
$wordWriter->save($dir . '/registration-form.docx');
echo "wrote registration-form.docx\n";

/* ---------------- XLSX template layout ---------------- */

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray([
    ['type', 'label', 'required', 'options', 'placeholder', 'help_text', 'section'],
    ['text', 'Full Name', 'yes', '', 'John Doe', '', ''],
    ['email', 'Email Address', 'yes', '', 'you@example.com', '', ''],
    ['phone', 'Phone Number', 'no', '', '+1 555 0000', '', ''],
    ['date', 'Date of Birth', 'yes', '', '', '', 'Personal Details'],
    ['radio', 'How did you hear about us?', 'no', 'Friend,Social Media,Search Engine', '', '', 'Personal Details'],
    ['checkbox', 'Which topics interest you?', 'no', 'Design,Engineering,Marketing', '', '', 'Personal Details'],
    ['file', 'Resume Upload', 'no', '', '', 'PDF or DOCX, max 2MB', ''],
], null, 'A1');

(new XlsxWriter($spreadsheet))->save($dir . '/feedback-template.xlsx');
echo "wrote feedback-template.xlsx\n";

/* ---------------- XLSX data layout ---------------- */

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray([
    ['Full Name', 'Email Address', 'Phone Number', 'How did you hear about us?', 'Date of Birth', 'Comments', 'Overall Rating'],
    ['John Doe', 'john@example.com', '555-1234', 'Friend', '1990-05-12', 'Great event!', '5'],
    ['Jane Smith', 'jane@example.com', '555-5678', 'Social Media', '1992-08-21', 'Loved it', '4'],
    ['Bob Brown', 'bob@example.com', '555-9999', 'Friend', '1988-01-30', '', '4'],
    ['Alice Lee', 'alice@example.com', '555-1111', 'Search Engine', '1995-03-15', 'Well organized', '5'],
    ['Tom Hill', 'tom@example.com', '555-2222', 'Friend', '1985-07-07', '', '3'],
    ['Mia Khan', 'mia@example.com', '555-3333', 'Social Media', '1998-11-11', 'Could be longer', '4'],
    ['Leo Ray', 'leo@example.com', '555-4444', 'Friend', '1991-09-09', 'Nice', '5'],
], null, 'A1');

(new XlsxWriter($spreadsheet))->save($dir . '/survey-data.xlsx');
echo "wrote survey-data.xlsx\n";
