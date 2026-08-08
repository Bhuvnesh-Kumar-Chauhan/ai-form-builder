<div>
    @if($submitted)
        <div class="card shadow">
            <div class="card-body text-center p-5">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                </div>
                <h3 class="mb-3">Thank You!</h3>
                <p class="text-muted">{{ $form->settings['success_message'] ?? 'Your submission has been received successfully.' }}</p>
                @if($form->settings['redirect_url'] ?? false)
                    <p class="text-muted">You will be redirected shortly...</p>
                    <script>
                        setTimeout(() => {
                            window.location.href = "{{ $form->settings['redirect_url'] }}";
                        }, 3000);
                    </script>
                @endif
            </div>
        </div>
    @else
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">{{ $form->title }}</h4>
                @if($form->description)
                    <p class="mb-0 text-white-50">{{ $form->description }}</p>
                @endif
            </div>
            <div class="card-body">
                <!-- Progress Bar -->
                @if($form->is_multi_step && $totalSteps > 1)
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <small>Step {{ $currentStep + 1 }} of {{ $totalSteps }}</small>
                            <small>{{ round(($currentStep + 1) / $totalSteps * 100) }}%</small>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" style="width: {{ ($currentStep + 1) / $totalSteps * 100 }}%"></div>
                        </div>
                    </div>
                @endif

                <form wire:submit.prevent="submit">
                    @csrf
                    
                    <!-- Display Errors -->
                    @if($validationErrors)
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($validationErrors as $fieldErrors)
                                    @foreach($fieldErrors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Form Sections -->
                    @foreach($stepSections as $section)
                        <div class="form-section mt-4 mb-3">
                            <h5 class="border-bottom pb-2">{{ $section->label }}</h5>
                            @if($section->help_text)
                                <p class="text-muted small">{{ $section->help_text }}</p>
                            @endif
                        </div>
                    @endforeach

                    <!-- Form Fields -->
                    @foreach($stepFields as $field)
                        @if($field->type === 'divider')
                            <hr>
                        @elseif($field->type === 'heading')
                            <h{{ $field->settings['level'] ?? 3 }} class="mt-3 mb-2">{{ $field->label }}</h{{ $field->settings['level'] ?? 3 }}>
                        @elseif($field->type === 'paragraph')
                            <p class="text-muted">{{ $field->label }}</p>
                        @else
                            <div class="form-group mb-3">
                                <label for="field_{{ $field->field_key }}" class="form-label">
                                    {{ $field->label }}
                                    @if($field->is_required)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                
                                @switch($field->type)
                                    @case('textarea')
                                        <textarea 
                                            wire:model="submissionData.{{ $field->field_key }}"
                                            id="field_{{ $field->field_key }}"
                                            class="form-control @error('submissionData.' . $field->field_key) is-invalid @enderror"
                                            rows="4"
                                            placeholder="{{ $field->placeholder }}"
                                            {{ $field->is_required ? 'required' : '' }}
                                        ></textarea>
                                        @break
                                        
                                    @case('select')
                                        <select 
                                            wire:model="submissionData.{{ $field->field_key }}"
                                            id="field_{{ $field->field_key }}"
                                            class="form-select @error('submissionData.' . $field->field_key) is-invalid @enderror"
                                            {{ $field->is_required ? 'required' : '' }}
                                        >
                                            <option value="">{{ $field->placeholder ?? 'Select an option...' }}</option>
                                            @foreach($field->options as $option)
                                                <option value="{{ $option->value }}">{{ $option->label }}</option>
                                            @endforeach
                                        </select>
                                        @break
                                        
                                    @case('radio')
                                        <div>
                                            @foreach($field->options as $option)
                                                <div class="form-check">
                                                    <input 
                                                        type="radio"
                                                        wire:model="submissionData.{{ $field->field_key }}"
                                                        id="field_{{ $field->field_key }}_{{ $option->value }}"
                                                        class="form-check-input @error('submissionData.' . $field->field_key) is-invalid @enderror"
                                                        value="{{ $option->value }}"
                                                        {{ $field->is_required ? 'required' : '' }}
                                                    >
                                                    <label class="form-check-label" for="field_{{ $field->field_key }}_{{ $option->value }}">
                                                        {{ $option->label }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                        @break
                                        
                                    @case('checkbox')
                                        <div>
                                            @foreach($field->options as $option)
                                                <div class="form-check">
                                                    <input 
                                                        type="checkbox"
                                                        wire:model="submissionData.{{ $field->field_key }}"
                                                        id="field_{{ $field->field_key }}_{{ $option->value }}"
                                                        class="form-check-input @error('submissionData.' . $field->field_key) is-invalid @enderror"
                                                        value="{{ $option->value }}"
                                                    >
                                                    <label class="form-check-label" for="field_{{ $field->field_key }}_{{ $option->value }}">
                                                        {{ $option->label }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                        @break
                                        
                                    @case('file')
                                        <input 
                                            type="file"
                                            wire:model="submissionData.{{ $field->field_key }}"
                                            id="field_{{ $field->field_key }}"
                                            class="form-control @error('submissionData.' . $field->field_key) is-invalid @enderror"
                                            {{ $field->is_required ? 'required' : '' }}
                                        >
                                        @break
                                        
                                    @case('rating')
                                        <div class="rating">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="fas fa-star rating-star" 
                                                   wire:click="$set('submissionData.{{ $field->field_key }}', {{ $i }})"
                                                   style="cursor: pointer; color: {{ ($submissionData[$field->field_key] ?? 0) >= $i ? '#ffc107' : '#e9ecef' }}; font-size: 24px;">
                                                </i>
                                            @endfor
                                        </div>
                                        @break
                                        
                                    @case('date')
                                        <input 
                                            type="date"
                                            wire:model="submissionData.{{ $field->field_key }}"
                                            id="field_{{ $field->field_key }}"
                                            class="form-control @error('submissionData.' . $field->field_key) is-invalid @enderror"
                                            {{ $field->is_required ? 'required' : '' }}
                                        >
                                        @break
                                        
                                    @case('color')
                                        <input 
                                            type="color"
                                            wire:model="submissionData.{{ $field->field_key }}"
                                            id="field_{{ $field->field_key }}"
                                            class="form-control form-control-color @error('submissionData.' . $field->field_key) is-invalid @enderror"
                                            {{ $field->is_required ? 'required' : '' }}
                                        >
                                        @break
                                        
                                    @case('range')
                                        <input 
                                            type="range"
                                            wire:model="submissionData.{{ $field->field_key }}"
                                            id="field_{{ $field->field_key }}"
                                            class="form-range @error('submissionData.' . $field->field_key) is-invalid @enderror"
                                            min="{{ $field->settings['min'] ?? 0 }}"
                                            max="{{ $field->settings['max'] ?? 100 }}"
                                            step="{{ $field->settings['step'] ?? 1 }}"
                                        >
                                        <div class="d-flex justify-content-between">
                                            <small>{{ $field->settings['min'] ?? 0 }}</small>
                                            <small>{{ $submissionData[$field->field_key] ?? $field->settings['min'] ?? 0 }}</small>
                                            <small>{{ $field->settings['max'] ?? 100 }}</small>
                                        </div>
                                        @break
                                        
                                    @default
                                        <input 
                                            type="{{ $field->type }}"
                                            wire:model="submissionData.{{ $field->field_key }}"
                                            id="field_{{ $field->field_key }}"
                                            class="form-control @error('submissionData.' . $field->field_key) is-invalid @enderror"
                                            placeholder="{{ $field->placeholder }}"
                                            {{ $field->is_required ? 'required' : '' }}
                                            @if($field->type === 'email') autocomplete="email" @endif
                                        >
                                @endswitch
                                
                                @error('submissionData.' . $field->field_key)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                
                                @if($field->help_text)
                                    <small class="form-text text-muted">{{ $field->help_text }}</small>
                                @endif
                            </div>
                        @endif
                    @endforeach

                    <!-- Form Actions -->
                    <div class="form-actions mt-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                @if($form->is_multi_step && $totalSteps > 1 && $currentStep > 0)
                                    <button type="button" class="btn btn-secondary" wire:click="previousStep">
                                        <i class="fas fa-arrow-left"></i> Previous
                                    </button>
                                @endif
                            </div>
                            <div>
                                @if($form->is_multi_step && $totalSteps > 1 && $currentStep < $totalSteps - 1)
                                    <button type="button" class="btn btn-primary" wire:click="nextStep">
                                        Next <i class="fas fa-arrow-right"></i>
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-paper-plane"></i> {{ $form->settings['submit_button_text'] ?? 'Submit' }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @push('styles')
    <style>
    .form-section {
        padding: 10px 0;
    }
    .rating-star {
        transition: color 0.2s ease;
    }
    .rating-star:hover {
        color: #ffc107 !important;
    }
    </style>
    @endpush
</div>