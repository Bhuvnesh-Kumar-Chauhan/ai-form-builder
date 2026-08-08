<?php

namespace App\Jobs;

use App\Exceptions\InvalidSchemaException;
use App\Exceptions\LlmException;
use App\Models\AiFormGenerationJob;
use App\Models\Form;
use App\Services\FormSchemaService;
use App\Services\LlmClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateFormSchemaJob implements ShouldQueue
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

        $currentSchema = $this->currentSchemaFor($generation);

        $startedAt = hrtime(true);

        try {
            $result = $schemaService->generateWithRetries(
                instruction: $generation->prompt,
                mode: $generation->mode,
                currentSchema: $currentSchema,
                client: $client,
                maxAttempts: config('ai.max_attempts'),
            );

            $generation->update([
                'status' => AiFormGenerationJob::STATUS_SUCCEEDED,
                'output_schema' => $result['schema'],
                'raw_response' => $result['raw'],
                'model' => $result['model'],
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
                'latency_ms' => $result['latency_ms'],
                'attempts' => $result['attempts'],
                'error' => null,
                'finished_at' => now(),
            ]);

            Log::channel('ai')->info('ai.form_generation.succeeded', [
                'generation_id' => $generation->id,
                'user_id' => $generation->user_id,
                'form_id' => $generation->form_id,
                'mode' => $generation->mode,
                'model' => $result['model'],
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
                'total_tokens' => $result['usage']['total_tokens'] ?? null,
                'latency_ms' => $result['latency_ms'],
                'attempts' => $result['attempts'],
                'total_elapsed_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'fields' => count($result['schema']['fields'] ?? []),
            ]);
        } catch (LlmException|InvalidSchemaException|\Throwable $e) {
            $generation->update([
                'status' => AiFormGenerationJob::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Log::channel('ai')->error('ai.form_generation.failed', [
                'generation_id' => $generation->id,
                'user_id' => $generation->user_id,
                'form_id' => $generation->form_id,
                'mode' => $generation->mode,
                'error' => $e->getMessage(),
                'total_elapsed_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        }
    }

    protected function currentSchemaFor(AiFormGenerationJob $generation): ?array
    {
        if ($generation->mode !== 'edit') {
            return null;
        }

        // Prefer the schema snapshot captured at request time (may include
        // unsaved builder changes), falling back to the persisted schema.
        if (! empty($generation->input_schema)) {
            return $generation->input_schema;
        }

        if ($generation->form_id) {
            return Form::find($generation->form_id)?->schema;
        }

        return null;
    }
}
