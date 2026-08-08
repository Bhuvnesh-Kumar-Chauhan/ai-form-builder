<?php

namespace Tests\Unit;

use App\Exceptions\InvalidSchemaException;
use App\Services\FormSchemaService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormSchemaServiceTest extends TestCase
{
    protected FormSchemaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FormSchemaService::class);
    }

    #[Test]
    public function it_repairs_a_valid_schema_unchanged()
    {
        $input = [
            'title' => 'Contact Form',
            'description' => 'Get in touch',
            'settings' => ['theme' => 'dark', 'layout' => 'inline', 'show_progress' => true, 'recaptcha_enabled' => false, 'submit_button_text' => 'Go', 'success_message' => 'Thanks!', 'redirect_url' => null],
            'fields' => [
                [
                    'field_key' => 'name',
                    'label' => 'Your Name',
                    'type' => 'text',
                    'placeholder' => 'Jane Doe',
                    'help_text' => '',
                    'is_required' => true,
                    'is_visible' => true,
                    'default_value' => '',
                    'validation' => ['min' => 1, 'max' => 255],
                    'options' => [],
                ],
                [
                    'field_key' => 'department',
                    'label' => 'Department',
                    'type' => 'select',
                    'placeholder' => '',
                    'help_text' => '',
                    'is_required' => false,
                    'is_visible' => true,
                    'default_value' => '',
                    'validation' => ['in' => 'sales,support'],
                    'options' => [
                        ['label' => 'Sales', 'value' => 'sales'],
                        ['label' => 'Support', 'value' => 'support'],
                    ],
                ],
            ],
        ];

        $schema = $this->service->validateAndRepair($input);

        $this->assertSame('Contact Form', $schema['title']);
        $this->assertCount(2, $schema['fields']);
        $this->assertSame('text', $schema['fields'][0]['type']);
        $this->assertSame('sales,support', $schema['fields'][1]['validation']['in']);
    }

    #[Test]
    public function it_coerces_hallucinated_field_types()
    {
        $input = [
            'title' => 'T',
            'description' => '',
            'settings' => [],
            'fields' => [
                ['label' => 'Full Name', 'type' => 'fullname'],
                ['label' => 'Country', 'type' => 'dropdown', 'options' => [['label' => 'India', 'value' => 'india']]],
                ['label' => 'Notes', 'type' => 'multiline_text'],
                ['label' => 'Something Unknown', 'type' => 'starship'],
            ],
        ];

        $schema = $this->service->validateAndRepair($input);

        $this->assertSame('text', $schema['fields'][0]['type']);
        $this->assertSame('select', $schema['fields'][1]['type']);
        $this->assertSame('textarea', $schema['fields'][2]['type']);
        $this->assertSame('text', $schema['fields'][3]['type']);
    }

    #[Test]
    public function it_generates_and_deduplicates_field_keys()
    {
        $input = [
            'title' => 'T',
            'description' => '',
            'settings' => [],
            'fields' => [
                ['label' => 'Email Address', 'type' => 'email', 'field_key' => ''],
                ['label' => 'Full Name', 'type' => 'text'],
                ['label' => 'Email Address', 'type' => 'text'],
                ['label' => '', 'type' => 'text'],
            ],
        ];

        $schema = $this->service->validateAndRepair($input);

        $keys = array_column($schema['fields'], 'field_key');

        $this->assertSame('email_address', $keys[0]);
        $this->assertSame('full_name', $keys[1]);
        $this->assertSame('email_address_1', $keys[2]);
        $this->assertSame('field_4', $keys[3]);
        $this->assertCount(4, array_unique($keys));
    }

    #[Test]
    public function it_defaults_options_for_choice_fields_without_any()
    {
        $input = [
            'title' => 'T',
            'description' => '',
            'settings' => [],
            'fields' => [
                ['label' => 'Will you attend?', 'type' => 'radio'],
            ],
        ];

        $schema = $this->service->validateAndRepair($input);

        $this->assertSame('radio', $schema['fields'][0]['type']);
        $this->assertNotEmpty($schema['fields'][0]['options']);
        $this->assertSame(['yes', 'no'], array_column($schema['fields'][0]['options'], 'value'));
        $this->assertSame('yes,no', $schema['fields'][0]['validation']['in']);
    }

    #[Test]
    public function it_drops_unknown_validation_keys_and_keeps_types()
    {
        $input = [
            'title' => 'T',
            'description' => '',
            'settings' => [],
            'fields' => [
                [
                    'label' => 'Email',
                    'type' => 'email',
                    'validation' => ['email' => true, 'banana' => 'x', 'max' => '100', 'regex' => '/^a$/'],
                ],
            ],
        ];

        $schema = $this->service->validateAndRepair($input);

        $this->assertSame(true, $schema['fields'][0]['validation']['email']);
        $this->assertSame(100, $schema['fields'][0]['validation']['max']);
        $this->assertSame('/^a$/', $schema['fields'][0]['validation']['regex']);
        $this->assertArrayNotHasKey('banana', $schema['fields'][0]['validation']);
    }

    #[Test]
    public function it_clamps_settings_enum_values()
    {
        $input = [
            'title' => 'T',
            'description' => '',
            'settings' => ['theme' => 'neon-rainbow', 'layout' => 'vertical', 'show_progress' => 'no', 'recaptcha_enabled' => true],
            'fields' => [],
        ];

        $schema = $this->service->validateAndRepair($input, 'edit');

        $this->assertSame('default', $schema['settings']['theme']);
        $this->assertSame(false, $schema['settings']['show_progress']);
        $this->assertSame(true, $schema['settings']['recaptcha_enabled']);
    }

    #[Test]
    public function it_rejects_non_array_input()
    {
        $this->expectException(InvalidSchemaException::class);

        $this->service->validateAndRepair('not an object at all');
    }

    #[Test]
    public function it_rejects_create_schema_with_no_fields()
    {
        $this->expectException(InvalidSchemaException::class);

        $this->service->validateAndRepair(['title' => 'Empty', 'description' => '', 'settings' => [], 'fields' => []]);
    }

    #[Test]
    public function it_extracts_json_from_code_fences_and_prose()
    {
        $withFences = "Here is the result:\n```json\n{\"title\":\"A\",\"description\":\"\",\"settings\":[],\"fields\":[{\"label\":\"N\",\"type\":\"text\"}]}\n```\nEnjoy!";
        $withProse = 'Sure! The form should be {"title":"B","description":"","settings":[],"fields":[]} as requested.';

        $this->assertNotNull($this->service->extractJson($withFences));
        $this->assertSame('A', $this->service->extractJson($withFences)['title']);
        $this->assertSame('B', $this->service->extractJson($withProse)['title']);
        $this->assertNull($this->service->extractJson('no json here'));
    }
}
