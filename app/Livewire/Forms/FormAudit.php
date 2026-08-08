<?php

namespace App\Livewire\Forms;

use App\Jobs\AuditFormSchemaJob;
use App\Models\AiFormGenerationJob;
use Livewire\Attributes\Computed;
use Livewire\Component;

class FormAudit extends Component
{
    public ?int $formId = null;

    public ?string $schemaJson = null;

    public ?AiFormGenerationJob $generationJob = null;

    public function mount(?int $formId = null, ?string $schema = null): void
    {
        $this->formId = $formId;
        $this->schemaJson = $schema;
    }

    public function runAudit(): void
    {
        if (! auth()->user()->can('edit forms') && ! auth()->user()->can('create forms')) {
            abort(403, 'You do not have permission to use AI audit.');
        }

        $this->generationJob = null;

        $inputSchema = null;
        if ($this->schemaJson) {
            $decoded = json_decode($this->schemaJson, true);
            if (is_array($decoded)) {
                $inputSchema = $decoded;
            }
        }

        $job = AiFormGenerationJob::create([
            'user_id' => auth()->id(),
            'form_id' => $this->formId,
            'mode' => 'edit',
            'prompt' => 'Audit this form',
            'status' => AiFormGenerationJob::STATUS_QUEUED,
            'input_schema' => $inputSchema,
        ]);

        $this->generationJob = $job;

        AuditFormSchemaJob::dispatch($job->id);
    }

    public function refreshStatus(): void
    {
        $this->generationJob?->refresh();
    }

    public function applyFixes(): void
    {
        if (! $this->generationJob || $this->generationJob->status !== AiFormGenerationJob::STATUS_SUCCEEDED) {
            return;
        }

        $output = $this->generationJob->output_schema ?? [];
        $schema = $output['schema'] ?? null;

        if (is_array($schema)) {
            $this->dispatch('aiSchemaReady', schema: $schema);
        }
    }

    public function resetAudit(): void
    {
        $this->generationJob = null;
    }

    #[Computed]
    public function report(): ?array
    {
        if (! $this->generationJob || $this->generationJob->status !== AiFormGenerationJob::STATUS_SUCCEEDED) {
            return null;
        }

        $output = $this->generationJob->output_schema ?? [];

        return is_array($output['audit'] ?? null) ? $output['audit'] : null;
    }

    public function render()
    {
        return view('livewire.forms.form-audit', [
            'report' => $this->report,
        ]);
    }
}
