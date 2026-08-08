<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(App\Services\FormImportService::class);
$fixtures = 'C:/Users/bhuvn/Downloads/ai-form-builder/tests/fixtures';

echo "==== DOCX ====\n";
$r = $service->parseFile($fixtures . '/registration-form.docx', 'docx');
echo 'TITLE: ' . $r['title'] . PHP_EOL;
foreach ($r['fields'] as $f) {
    echo str_pad($f['type'], 9) . ' | ' . str_pad($f['label'], 42) . ' | '
        . ($f['options'] ? count($f['options']) . ' opts' : '       ')
        . ' | ' . $f['confidence'] . PHP_EOL;
}
echo 'WARNINGS: ' . count($r['warnings']) . PHP_EOL;
foreach ($r['warnings'] as $w) {
    echo '  - ' . $w . PHP_EOL;
}

echo "\n==== XLSX TEMPLATE ====\n";
$r = $service->parseFile($fixtures . '/feedback-template.xlsx', 'xlsx');
echo 'TITLE: ' . $r['title'] . PHP_EOL;
foreach ($r['fields'] as $f) {
    echo str_pad($f['type'], 9) . ' | ' . str_pad($f['label'], 42) . ' | '
        . ($f['options'] ? count($f['options']) . ' opts' : '       ')
        . ' | required=' . ($f['is_required'] ? 'yes' : 'no')
        . ' | ' . $f['confidence'] . PHP_EOL;
}
echo 'WARNINGS: ' . count($r['warnings']) . PHP_EOL;

echo "\n==== XLSX DATA ====\n";
$r = $service->parseFile($fixtures . '/survey-data.xlsx', 'xlsx');
echo 'TITLE: ' . $r['title'] . PHP_EOL;
foreach ($r['fields'] as $f) {
    echo str_pad($f['type'], 9) . ' | ' . str_pad($f['label'], 42) . ' | '
        . ($f['options'] ? count($f['options']) . ' opts: ' . implode(',', array_column($f['options'], 'label')) : '       ')
        . ' | required=' . ($f['is_required'] ? 'yes' : 'no')
        . ' | ' . $f['confidence'] . PHP_EOL;
}
echo 'WARNINGS: ' . count($r['warnings']) . PHP_EOL;
