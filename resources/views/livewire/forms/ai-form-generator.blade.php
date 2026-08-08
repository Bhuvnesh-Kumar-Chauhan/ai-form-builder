<div>
    <div class="card border-primary">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">
                <i class="fas fa-magic me-1"></i>
                {{ $mode === 'edit' ? 'Edit this form with AI' : 'Generate a form with AI' }}
            </h6>
        </div>
        <div class="card-body">

            @error('prompt')
                <div class="alert alert-danger py-2" role="alert">{{ $message }}</div>
            @enderror

            @if($generationJob && $generationJob->status === 'failed')
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-times-circle me-1"></i>
                    Generation failed. {{ $generationJob->error }}
                </div>
            @endif

            @if(! $generationJob || $generationJob->status === 'failed' || $generationJob->status === 'succeeded')
                <div class="mb-3">
                    <label class="form-label">Describe the form or the change you want</label>
                    <textarea wire:model="prompt" rows="3" class="form-control"
                        placeholder="{{ $mode === 'edit'
                            ? 'e.g. Add an emergency contact section, make phone required, translate labels to Hindi...'
                            : 'e.g. Internship application with education history, skills and resume upload...' }}"></textarea>
                </div>

                @if(! $generationJob || $generationJob->status === 'failed')
                    <button type="button" wire:click="generate" class="btn btn-primary">
                        <i class="fas fa-wand-magic-sparkles"></i> Generate
                    </button>
                @else
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-1"></i>
                        Generation complete ({{ $generationJob->attempts }} attempt(s),
                        {{ $generationJob->model }},
                        {{ $generationJob->latency_ms }}ms,
                        {{ $generationJob->total_tokens }} tokens).
                    </div>
                    <button type="button" wire:click="apply" class="btn btn-success">
                        <i class="fas fa-arrow-down-into-square me-1"></i> Apply to Builder
                    </button>
                    <button type="button" wire:click="resetJob" class="btn btn-outline-secondary ms-1">
                        <i class="fas fa-rotate me-1"></i> New Request
                    </button>
                @endif

                <div class="mt-3">
                    <small class="text-muted d-block mb-1">Try an example:</small>
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" wire:click="$set('prompt', '{{ $mode === 'edit'
                            ? 'Add an emergency contact section'
                            : 'Internship application with education history, skills and resume upload' }}')" class="btn btn-sm btn-outline-primary">
                            {{ $mode === 'edit' ? 'Add emergency contact section' : 'Internship application' }}
                        </button>
                        <button type="button" wire:click="$set('prompt', '{{ $mode === 'edit'
                            ? 'Make phone required'
                            : 'Customer feedback survey with rating, comments and contact email' }}')" class="btn btn-sm btn-outline-primary">
                            {{ $mode === 'edit' ? 'Make phone required' : 'Customer feedback survey' }}
                        </button>
                        <button type="button" wire:click="$set('prompt', '{{ $mode === 'edit'
                            ? 'Translate all labels to Hindi'
                            : 'Event registration with ticket type and dietary preferences' }}')" class="btn btn-sm btn-outline-primary">
                            {{ $mode === 'edit' ? 'Translate labels to Hindi' : 'Event registration' }}
                        </button>
                    </div>
                </div>
            @endif

            @if($generationJob && in_array($generationJob->status, ['queued', 'processing'], true))
                <div wire:poll.2s="refreshStatus" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 mb-1"><strong>Generating your form...</strong></p>
                    <p class="text-muted small mb-0">
                        This may take a few moments. The request is processed in the background so you can keep working.
                    </p>
                </div>
            @endif

        </div>
    </div>
</div>
