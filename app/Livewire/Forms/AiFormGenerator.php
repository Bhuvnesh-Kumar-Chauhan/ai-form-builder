<?php

namespace App\Livewire\Forms;

use App\Models\AiFormGenerationJob;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class AiFormGenerator extends Component
{
    public string $prompt = '';

    public ?string $mode = 'create';

    public ?AiFormGenerationJob $generationJob = null;

    protected $rules = [
        'prompt' => 'required|min:3',
    ];

    public function mount(?string $mode = null): void
    {
        $this->mode = $mode ?: 'create';
    }

    public function generate(): void
    {
        $this->validate();

        $this->generationJob = null;

        // The parent FormBuilder owns the current form state, so it creates the
        // job with a fresh schema snapshot and tells us the job id back.
        $this->dispatch('aiGenerate', prompt: $this->prompt);
    }

    #[On('aiGenerationStarted')]
    public function onGenerationStarted(mixed $id): void
    {
        $this->generationJob = AiFormGenerationJob::find((int) $id);
    }

    public function refreshStatus(): void
    {
        $this->generationJob?->refresh();
    }

    public function apply(): void
    {
        if (! $this->generationJob || $this->generationJob->status !== AiFormGenerationJob::STATUS_SUCCEEDED) {
            return;
        }

        $this->dispatch('aiSchemaReady', schema: $this->generationJob->output_schema);
    }

    public function resetJob(): void
    {
        $this->generationJob = null;
    }

    #[Computed]
    public function isRunning(): bool
    {
        return $this->generationJob?->isRunning() ?? false;
    }

    public function render()
    {
        return view('livewire.forms.ai-form-generator');
    }
}
