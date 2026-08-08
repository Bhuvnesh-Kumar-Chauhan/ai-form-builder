<?php

namespace Tests\Unit;

use App\Services\FormImportService;
use App\Services\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormImportService $service;

    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FormImportService::class);
        $this->fixturesDir = realpath(__DIR__.'/../fixtures');
    }

    public function test_parses_docx_title_sections_and_fields(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/registration-form.docx', 'docx');

        $this->assertSame('Registration Form', $result['title']);
        $this->assertSame('docx', $result['layout']);

        $labels = array_column($result['fields'], 'label');

        $this->assertContains('Full Name', $labels);
        $this->assertContains('Email Address', $labels);
        $this->assertContains('Phone Number', $labels);
        $this->assertContains('Date of Birth', $labels);
        $this->assertContains('Personal Details', $labels);
        $this->assertContains('Additional Info', $labels);
    }

    public function test_docx_fields_have_inferred_types_and_sections(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/registration-form.docx', 'docx');

        $fields = collect($result['fields'])->keyBy('label');

        $this->assertSame('text', $fields['Full Name']['type']);
        $this->assertSame('email', $fields['Email Address']['type']);
        $this->assertSame('phone', $fields['Phone Number']['type']);
        $this->assertSame('date', $fields['Date of Birth']['type']);
        $this->assertSame('section', $fields['Personal Details']['type']);
        $this->assertSame('section', $fields['Additional Info']['type']);
    }

    public function test_docx_list_attaches_options_to_previous_field(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/registration-form.docx', 'docx');

        $fields = collect($result['fields'])->keyBy('label');

        $degree = $fields['Highest Degree'];
        $this->assertSame('radio', $degree['type']);
        $this->assertCount(3, $degree['options']);
        $this->assertSame(
            ['High School', 'Bachelor', 'Master'],
            array_column($degree['options'], 'label')
        );
    }

    public function test_docx_checkbox_list_and_table_become_checkbox_fields(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/registration-form.docx', 'docx');

        $fields = collect($result['fields'])->keyBy('label');

        $sessions = $fields['Which sessions are you interested in?'];
        $this->assertSame('checkbox', $sessions['type']);
        $this->assertCount(3, $sessions['options']);
        $this->assertSame(
            ['Keynote', 'Workshops', 'Networking'],
            array_column($sessions['options'], 'label')
        );

        $dietary = $fields['Dietary Requirements'];
        $this->assertSame('checkbox', $dietary['type']);
        $this->assertSame(
            ['Vegetarian', 'Vegan', 'None'],
            array_column($dietary['options'], 'label')
        );
    }

    public function test_xlsx_template_layout_parses_headers_and_options(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/feedback-template.xlsx', 'xlsx');

        $this->assertSame('xlsx-template', $result['layout']);

        $fields = collect($result['fields'])->keyBy('label');

        $this->assertSame('text', $fields['Full Name']['type']);
        $this->assertTrue($fields['Full Name']['is_required']);
        $this->assertSame('email', $fields['Email Address']['type']);
        $this->assertTrue($fields['Email Address']['is_required']);
        $this->assertSame('phone', $fields['Phone Number']['type']);
        $this->assertFalse($fields['Phone Number']['is_required']);

        $hear = $fields['How did you hear about us?'];
        $this->assertSame('radio', $hear['type']);
        $this->assertCount(3, $hear['options']);
    }

    public function test_xlsx_template_section_column_creates_sections(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/feedback-template.xlsx', 'xlsx');

        $fields = collect($result['fields'])->keyBy('label');

        $this->assertSame('section', $fields['Personal Details']['type']);
    }

    public function test_xlsx_data_layout_infers_types_from_values(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/survey-data.xlsx', 'xlsx');

        $this->assertSame('xlsx-data', $result['layout']);

        $fields = collect($result['fields'])->keyBy('label');

        $this->assertSame('text', $fields['Full Name']['type']);
        $this->assertSame('email', $fields['Email Address']['type']);
        $this->assertSame('phone', $fields['Phone Number']['type']);
        $this->assertSame('date', $fields['Date of Birth']['type']);
        $this->assertSame('textarea', $fields['Comments']['type']);
        $this->assertSame('rating', $fields['Overall Rating']['type']);
        $this->assertSame('select', $fields['How did you hear about us?']['type']);
        $this->assertCount(3, $fields['How did you hear about us?']['options']);
    }

    public function test_xlsx_data_layout_marks_columns_with_blanks_optional(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/survey-data.xlsx', 'xlsx');

        $fields = collect($result['fields'])->keyBy('label');

        $this->assertTrue($fields['Email Address']['is_required']);
        $this->assertFalse($fields['Comments']['is_required']);
    }

    public function test_unsupported_extension_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->parseFile($this->fixturesDir.'/registration-form.docx', 'pdf');
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->parseFile($this->fixturesDir.'/does-not-exist.docx', 'docx');
    }

    public function test_infer_field_type_heuristics(): void
    {
        $this->assertSame('email', $this->service->inferFieldType('Email Address')['type']);
        $this->assertSame('phone', $this->service->inferFieldType('Phone Number')['type']);
        $this->assertSame('date', $this->service->inferFieldType('Date of Birth')['type']);
        $this->assertSame('url', $this->service->inferFieldType('Website')['type']);
        $this->assertSame('file', $this->service->inferFieldType('Upload your resume')['type']);
        $this->assertSame('rating', $this->service->inferFieldType('Overall Rating')['type']);
        $this->assertSame('textarea', $this->service->inferFieldType('Comments')['type']);
        $this->assertSame('number', $this->service->inferFieldType('How many people?')['type']);

        $radio = $this->service->inferFieldType('Are you a student?');
        $this->assertSame('radio', $radio['type']);
        $this->assertSame(['Yes', 'No'], $radio['options']);

        $fallback = $this->service->inferFieldType('Some random question');
        $this->assertSame('text', $fallback['type']);
        $this->assertSame(FormImportService::CONFIDENCE_LOW, $fallback['confidence']);
    }

    public function test_refine_with_ai_merges_types_and_preserves_structure(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/survey-data.xlsx', 'xlsx');

        $initial = collect($result['fields'])->keyBy('field_key');
        $this->assertSame('phone', $initial['phone_number']['type']);
        $this->assertFalse($initial['comments']['is_required']);

        $client = new class extends LlmClient
        {
            public function complete(array $messages): array
            {
                return [
                    'content' => json_encode([
                        'fields' => [
                            ['field_key' => 'phone_number', 'type' => 'text', 'is_required' => true],
                            ['field_key' => 'comments', 'type' => 'textarea', 'is_required' => true],
                        ],
                    ]),
                    'model' => 'stub-model',
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
                    'latency_ms' => 1,
                ];
            }
        };

        $refined = $this->service->refineWithAi($result['fields'], $client, 1);

        $updated = collect($refined['fields'])->keyBy('field_key');
        $this->assertSame('text', $updated['phone_number']['type']);
        $this->assertTrue($updated['phone_number']['is_required']);
        $this->assertTrue($updated['phone_number']['refined_by_ai']);
        $this->assertTrue($updated['comments']['is_required']);
        $this->assertSame(2, $refined['changed']);
        $this->assertSame('stub-model', $refined['model']);
        $this->assertSame(1, $refined['attempts']);

        // Structure (labels, options, order) is untouched.
        $this->assertSame(
            array_column($result['fields'], 'field_key'),
            array_column($refined['fields'], 'field_key')
        );
        $this->assertSame(
            array_column($initial['how_did_you_hear_about_us']['options'], 'label'),
            array_column($updated['how_did_you_hear_about_us']['options'], 'label')
        );
    }

    public function test_refine_with_ai_never_converts_to_choice_type_without_options(): void
    {
        $result = $this->service->parseFile($this->fixturesDir.'/survey-data.xlsx', 'xlsx');

        $commentsKey = collect($result['fields'])->keyBy('label')['Comments']['field_key'];

        $client = new class($commentsKey) extends LlmClient
        {
            public function __construct(private string $commentsKey) {}

            public function complete(array $messages): array
            {
                return [
                    'content' => json_encode([
                        'fields' => [
                            ['field_key' => $this->commentsKey, 'type' => 'checkbox', 'is_required' => true],
                        ],
                    ]),
                    'model' => 'stub-model',
                    'usage' => [],
                    'latency_ms' => 1,
                ];
            }
        };

        $refined = $this->service->refineWithAi($result['fields'], $client, 1);

        $comments = collect($refined['fields'])->firstWhere('field_key', $commentsKey);
        $this->assertSame('text', $comments['type']);
    }
}
