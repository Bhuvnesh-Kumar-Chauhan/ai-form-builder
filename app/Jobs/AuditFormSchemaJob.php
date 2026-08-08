<?php

namespace App\Jobs;

use App\Exceptions\InvalidSchemaException;
use App\Exceptions\LlmException;
use App\Models\AiFormGenerationJob;
use App\Services\FormSchemaService;
use App\Services\LlmClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AuditFormSchemaJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(public int $generationId)
    {
    }

    public function handle(LlmClient $client, FormSchemaService $schemaService): void
    {
        $generation = AiFormGenerationJob::findOrFail($this->generationId);

        $generation->update([
            'status' => AiFormGenerationJob::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $startedAt = hrtime(true);

        try {
            $messages = $this->buildMessages($generation->input_schema);

            $response = $client->complete($messages);

            $decoded = $schemaService->extractJson($response['content']);

            if ($decoded === null) {
                throw new InvalidSchemaException('The audit response was not valid JSON.');
            }

            $audit = is_array($decoded['audit'] ?? null) ? $decoded['audit'] : [];
            $schema = $schemaService->validateAndRepair($decoded['schema'] ?? [], $generation->mode);

            $generation->update([
                'status' => AiFormGenerationJob::STATUS_SUCCEEDED,
                'output_schema' => ['audit' => $audit, 'schema' => $schema],
                'raw_response' => $response['content'],
                'model' => $response['model'],
                'prompt_tokens' => $response['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? null,
                'total_tokens' => $response['usage']['total_tokens'] ?? null,
                'latency_ms' => $response['latency_ms'],
                'attempts' => 1,
                'error' => null,
                'finished_at' => now(),
            ]);

            Log::channel('ai')->info('ai.form_audit.succeeded', [
                'generation_id' => $generation->id,
                'user_id' => $generation->user_id,
                'form_id' => $generation->form_id,
                'model' => $response['model'],
                'score' => $audit['score'] ?? null,
                'issues' => count($audit['issues'] ?? []),
                'total_tokens' => $response['usage']['total_tokens'] ?? null,
                'latency_ms' => $response['latency_ms'],
                'total_elapsed_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        } catch (LlmException|InvalidSchemaException|\Throwable $e) {
            $generation->update([
                'status' => AiFormGenerationJob::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Log::channel('ai')->error('ai.form_audit.failed', [
                'generation_id' => $generation->id,
                'user_id' => $generation->user_id,
                'form_id' => $generation->form_id,
                'error' => $e->getMessage(),
                'total_elapsed_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        }
    }

    /**
     * @param  array|null  $schema  the schema snapshot to audit
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessages(?array $schema): array
    {
        $system = <<<'PROMPT'
        You are an expert form auditor. Review the given form schema for correctness,
        validation quality, UX issues and accessibility.

        RETURN ONLY A SINGLE VALID JSON OBJECT with this exact contract:
        {
          "audit": {
            "score": 0,
            "summary": "one or two sentence overall assessment",
            "issues": [
              { "severity": "high|medium|low", "title": "short title", "detail": "what to change and why" }
            ]
          },
          "schema": {
            "title": "...",
            "description": "...",
            "settings": { "theme": "default", "layout": "vertical", "show_progress": true, "recaptcha_enabled": false, "submit_button_text": "Submit", "success_message": "...", "redirect_url": null },
            "fields": [
              {
                "field_key": "...",
                "label": "...",
                "type": "text",
                "placeholder": "...",
                "help_text": "...",
                "is_required": true,
                "is_visible": true,
                "default_value": "",
                "validation": {},
                "options": []
              }
            ]
          }
        }

        Rules:
        - "schema" is the COMPLETE corrected schema (never a diff). Preserve every field_key,
          label and option unless the fix requires changing them.
        - Only suggest fixes that genuinely improve the form (missing required on contact
          fields, mismatched validation, empty placeholders, weak labels, missing sections).
        - Cap the issues list at 8. Score from 0-100.
        PROMPT;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => 'Audit this form schema:' . "\n\n" . json_encode($schema ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)],
        ];
    }
}
