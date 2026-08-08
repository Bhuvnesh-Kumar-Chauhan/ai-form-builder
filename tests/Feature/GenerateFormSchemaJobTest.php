<?php

namespace Tests\Feature;

use App\Jobs\GenerateFormSchemaJob;
use App\Models\AiFormGenerationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateFormSchemaJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_succeeds_and_records_model_tokens_and_latency()
    {
        Http::fake([
            '*' => Http::response($this->openAiResponse($this->validSchemaJson())),
        ]);

        $generation = AiFormGenerationJob::create([
            'user_id' => $this->user->id,
            'mode' => 'create',
            'prompt' => 'internship application',
            'status' => AiFormGenerationJob::STATUS_QUEUED,
        ]);

        GenerateFormSchemaJob::dispatchSync($generation->id);

        $generation->refresh();

        $this->assertSame(AiFormGenerationJob::STATUS_SUCCEEDED, $generation->status);
        $this->assertNotEmpty($generation->output_schema);
        $this->assertSame('mock-model', $generation->model);
        $this->assertSame(55, $generation->prompt_tokens);
        $this->assertSame(40, $generation->completion_tokens);
        $this->assertSame(95, $generation->total_tokens);
        $this->assertSame(1, $generation->attempts);
        $this->assertNotNull($generation->latency_ms);
        $this->assertNotNull($generation->finished_at);
        $this->assertSame('mock-model', $generation->model);
        $this->assertSame('Internship Application', $generation->output_schema['title']);
    }

    #[Test]
    public function it_retries_with_invalid_json_then_succeeds()
    {
        $calls = 0;

        Http::fake(function () use (&$calls) {
            $calls++;

            return $calls === 1
                ? Http::response($this->openAiResponse('this is definitely not json'))
                : Http::response($this->openAiResponse($this->validSchemaJson()));
        });

        $generation = AiFormGenerationJob::create([
            'user_id' => $this->user->id,
            'mode' => 'create',
            'prompt' => 'internship application',
            'status' => AiFormGenerationJob::STATUS_QUEUED,
        ]);

        GenerateFormSchemaJob::dispatchSync($generation->id);

        $generation->refresh();

        $this->assertSame(AiFormGenerationJob::STATUS_SUCCEEDED, $generation->status);
        $this->assertSame(2, $generation->attempts);
        $this->assertNotEmpty($generation->output_schema);
    }

    #[Test]
    public function it_fails_without_persisting_a_broken_schema()
    {
        Http::fake([
            '*' => Http::response($this->openAiResponse('garbage, still not json')),
        ]);

        $generation = AiFormGenerationJob::create([
            'user_id' => $this->user->id,
            'mode' => 'create',
            'prompt' => 'internship application',
            'status' => AiFormGenerationJob::STATUS_QUEUED,
        ]);

        GenerateFormSchemaJob::dispatchSync($generation->id);

        $generation->refresh();

        $this->assertSame(AiFormGenerationJob::STATUS_FAILED, $generation->status);
        $this->assertNull($generation->output_schema);
        $this->assertNotNull($generation->error);
    }

    #[Test]
    public function edit_mode_uses_the_captured_input_schema()
    {
        $capturedSchema = null;

        Http::fake(function ($request) use (&$capturedSchema) {
            $body = json_decode($request->body(), true);
            $capturedSchema = $body['messages'][2]['content'] ?? null;

            return Http::response($this->openAiResponse($this->validSchemaJson()));
        });

        $generation = AiFormGenerationJob::create([
            'user_id' => $this->user->id,
            'mode' => 'edit',
            'prompt' => 'make phone required',
            'status' => AiFormGenerationJob::STATUS_QUEUED,
            'input_schema' => ['title' => 'Current Form', 'fields' => []],
        ]);

        GenerateFormSchemaJob::dispatchSync($generation->id);

        $generation->refresh();

        $this->assertSame(AiFormGenerationJob::STATUS_SUCCEEDED, $generation->status);
        $this->assertStringContainsString('Current Form', $capturedSchema);
    }

    protected function validSchemaJson(): string
    {
        return json_encode([
            'title' => 'Internship Application',
            'description' => 'Apply here.',
            'settings' => ['theme' => 'default', 'layout' => 'vertical', 'show_progress' => true, 'recaptcha_enabled' => false, 'submit_button_text' => 'Submit', 'success_message' => 'Done!', 'redirect_url' => null],
            'fields' => [
                [
                    'field_key' => 'full_name',
                    'label' => 'Full Name',
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
                    'field_key' => 'resume',
                    'label' => 'Resume Upload',
                    'type' => 'file',
                    'placeholder' => '',
                    'help_text' => '',
                    'is_required' => true,
                    'is_visible' => true,
                    'default_value' => '',
                    'validation' => ['file' => true, 'mimes' => 'pdf,doc,docx', 'max' => 2048],
                    'options' => [],
                ],
            ],
        ]);
    }

    protected function openAiResponse(string $content): array
    {
        return [
            'id' => 'chatcmpl-mock',
            'object' => 'chat.completion',
            'model' => 'mock-model',
            'choices' => [
                ['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 55, 'completion_tokens' => 40, 'total_tokens' => 95],
        ];
    }
}
