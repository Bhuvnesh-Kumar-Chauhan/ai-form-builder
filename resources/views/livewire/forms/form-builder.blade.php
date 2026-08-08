<div x-data="{ activeTab: 'builder' }">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-pencil-ruler"></i> Form Builder
                            @if(!empty($form['id']))
                                <small class="text-muted">({{ $form['title'] }})</small>
                            @endif
                        </h5>
                        <div>
                            @can('publish forms')
                            <div class="btn-group me-2">
                                <button type="button" class="btn btn-sm dropdown-toggle {{ $form['is_published'] ? 'btn-success' : 'btn-warning' }}" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas {{ $form['is_published'] ? 'fa-check-circle' : 'fa-pen' }} me-1"></i>
                                    {{ $form['is_published'] ? 'Published' : 'Draft' }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li><h6 class="dropdown-header">Change status</h6></li>
                                    <li>
                                        <button type="button" class="dropdown-item" wire:click="setStatus(true)">
                                            <i class="fas fa-check-circle text-success me-1"></i> Published
                                            @if($form['is_published']) <i class="fas fa-check ms-1"></i> @endif
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item" wire:click="setStatus(false)">
                                            <i class="fas fa-pen text-warning me-1"></i> Draft
                                            @if(!$form['is_published']) <i class="fas fa-check ms-1"></i> @endif
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            @endcan
                            <button type="button" class="btn btn-outline-primary btn-sm me-2" wire:click="openSchemaEditor">
                                <i class="fas fa-code"></i> Schema
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm me-2" wire:click="previewForm">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" wire:click="saveForm">
                                <i class="fas fa-save"></i> Save Form
                            </button>
                        </div>
                    </div>
                </div>
                @if($statusMessage)
                <div class="card-body pt-2 pb-0" x-data="{ show: true }">
                    <div class="alert alert-success d-flex align-items-center justify-content-between py-2" x-show="show" role="alert">
                        <span><i class="fas fa-check-circle me-1"></i> {{ $statusMessage }}</span>
                        <button type="button" class="btn-close btn-sm" aria-label="Close" @click="show = false"></button>
                    </div>
                </div>
                @endif
                <div class="card-body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: activeTab === 'builder' }" href="#" @click.prevent="activeTab = 'builder'">
                                <i class="fas fa-layer-group"></i> Builder
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: activeTab === 'settings' }" href="#" @click.prevent="activeTab = 'settings'">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: activeTab === 'fields' }" href="#" @click.prevent="activeTab = 'fields'">
                                <i class="fas fa-list"></i> Fields Library
                            </a>
                        </li>
                    </ul>

                    <!-- Builder Tab -->
                    <div x-show="activeTab === 'builder'">
                        <!-- Form Title & Description -->
                        <div class="form-builder-header mb-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <input type="text" wire:model="form.title" class="form-control form-control-lg" placeholder="Enter form title...">
                                        @error('form.title') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="mb-3">
                                        <textarea wire:model="form.description" class="form-control" rows="2" placeholder="Form description..."></textarea>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-builder-stats p-3 bg-light rounded">
                                        <div class="d-flex justify-content-between">
                                            <span>Fields: <strong>{{ count($fields) }}</strong></span>
                                            <span>Sections: <strong>{{ count($sections) }}</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fields Drop Zone -->
                        <div class="form-fields-container">
                            
                            @if(count($fields) > 0)
                                @foreach($fields as $index => $field)
                                    <div class="field-item card mb-2 {{ $field['type'] === 'section' ? 'bg-light border-primary' : '' }}" 
                                         wire:key="field-{{ $index }}" 
                                         data-sortable-item="{{ $field['id'] ?? $index }}"
                                         x-data="{ showOptions: false }">
                                        
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="d-flex align-items-start flex-grow-1">
                                                    <span class="drag-handle me-2 mt-1">
                                                        <i class="fas fa-grip-vertical text-muted"></i>
                                                    </span>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center">
                                                            <span class="field-type-icon me-2">
                                                                @php
                                                                    $icon = collect($fieldTypes)->firstWhere('value', $field['type']);
                                                                @endphp
                                                                <i class="fas {{ $icon['icon'] ?? 'fa-puzzle-piece' }}"></i>
                                                            </span>
                                                            <strong>{{ $field['label'] }}</strong>
                                                            <span class="badge bg-secondary ms-2">{{ $field['type'] }}</span>
                                                            @if($field['is_required'] ?? false)
                                                                <span class="badge bg-danger ms-1">Required</span>
                                                            @endif
                                                            @if($field['type'] === 'section')
                                                                <span class="badge bg-primary ms-1">Section</span>
                                                            @endif
                                                        </div>
                                                        <small class="text-muted">Key: {{ $field['field_key'] }}</small>
                                                        @if(!empty($field['placeholder']))
                                                            <small class="text-muted ms-2">Placeholder: {{ $field['placeholder'] }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="btn-group btn-group-sm">
                                                    <button wire:click="editField({{ $index }})" class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button wire:click="duplicateField({{ $index }})" class="btn btn-outline-secondary">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                    <button wire:click="removeField({{ $index }})" class="btn btn-outline-danger" onclick="return confirm('Delete this field?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            @if(isset($field['options']) && count($field['options']) > 0)
                                                <div class="mt-2 ms-4">
                                                    <small class="text-muted">Options:</small>
                                                    @foreach($field['options'] as $option)
                                                        <span class="badge bg-light text-dark border me-1">{{ $option['label'] }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            
                                            @if(!empty($field['validation']))
                                                <div class="mt-2 ms-4">
                                                    <small class="text-muted">Validation:</small>
                                                    @foreach($field['validation'] as $rule => $value)
                                                        <span class="badge bg-info">{{ $rule }}{{ $value ? ':'.$value : '' }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center p-5 border-2 border-dashed rounded">
                                    <i class="fas fa-plus-circle fa-3x text-muted"></i>
                                    <p class="mt-3 text-muted">No fields added yet. Click "Add Field" or drag fields from the library.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Add Field Buttons -->
                        <div class="mt-3">
                            <div class="d-flex flex-wrap gap-2">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="dropdown">
                                        <i class="fas fa-plus"></i> Add Field
                                    </button>
                                    <ul class="dropdown-menu" style="max-height: 400px; overflow-y: auto;">
                                        @foreach(collect($fieldTypes)->groupBy('category') as $category => $types)
                                            <li class="dropdown-header">{{ ucfirst($category) }}</li>
                                            @foreach($types as $type)
                                                <li>
                                                    <button wire:click="addField('{{ $type['value'] }}')" class="dropdown-item">
                                                        <i class="fas {{ $type['icon'] }}"></i> {{ $type['label'] }}
                                                    </button>
                                                </li>
                                            @endforeach
                                            @if(!$loop->last)
                                                <li><hr class="dropdown-divider"></li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                                
                                <button type="button" class="btn btn-outline-primary" wire:click="addSection">
                                    <i class="fas fa-layer-group"></i> Add Section
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div x-show="activeTab === 'settings'">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Form Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Theme</label>
                                            <select wire:model="form.settings.theme" class="form-select">
                                                <option value="default">Default</option>
                                                <option value="dark">Dark</option>
                                                <option value="minimal">Minimal</option>
                                                <option value="modern">Modern</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Layout</label>
                                            <select wire:model="form.settings.layout" class="form-select">
                                                <option value="vertical">Vertical</option>
                                                <option value="horizontal">Horizontal</option>
                                                <option value="inline">Inline</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Submit Button Text</label>
                                            <input type="text" wire:model="form.settings.submit_button_text" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Success Message</label>
                                            <textarea wire:model="form.settings.success_message" class="form-control" rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Redirect URL (after submission)</label>
                                            <input type="url" wire:model="form.settings.redirect_url" class="form-control" placeholder="https://example.com/thank-you">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Advanced Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check mb-2">
                                            <input type="checkbox" wire:model="form.is_published" class="form-check-input">
                                            <label class="form-check-label">Publish Form</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" wire:model="form.is_multi_step" class="form-check-input">
                                            <label class="form-check-label">Multi-step Form</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" wire:model="form.settings.show_progress" class="form-check-input">
                                            <label class="form-check-label">Show Progress Bar</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" wire:model="form.settings.recaptcha_enabled" class="form-check-input">
                                            <label class="form-check-label">Enable reCAPTCHA</label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Expiration Date</label>
                                            <input type="datetime-local" wire:model="form.expires_at" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">Form URL</h6>
                                    </div>
                                    <div class="card-body">
                                        @if(!empty($form['id']))
                                            <div class="input-group">
                                                <input type="text" class="form-control" value="{{ $form['fill_url'] }}" readonly>
                                                <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ $form['fill_url'] }}')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        @else
                                            <p class="text-muted">Save the form to get the public URL.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fields Library Tab -->
                    <div x-show="activeTab === 'fields'">
                        <div class="row">
                            @foreach(collect($fieldTypes)->groupBy('category') as $category => $types)
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">{{ ucfirst($category) }}</h6>
                                        </div>
                                        <div class="card-body">
                                            @foreach($types as $type)
                                                <div class="field-library-item p-2 border-bottom">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span>
                                                            <i class="fas {{ $type['icon'] }}"></i> {{ $type['label'] }}
                                                        </span>
                                                        <button wire:click="addField('{{ $type['value'] }}')" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Field Edit Modal -->
    @if($showFieldModal && isset($fields[$editingField]))
        @php $field = $fields[$editingField]; @endphp
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit"></i> Edit Field: {{ $field['label'] }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="$set('showFieldModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Basic Settings -->
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Basic Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Label <span class="text-danger">*</span></label>
                                            <input type="text" wire:model="fields.{{ $editingField }}.label" class="form-control">
                                            @error('fields.' . $editingField . '.label') 
                                                <small class="text-danger">{{ $message }}</small> 
                                            @enderror
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Field Key <span class="text-danger">*</span></label>
                                            <input type="text" wire:model="fields.{{ $editingField }}.field_key" class="form-control" placeholder="unique_key_name">
                                            @error('fields.' . $editingField . '.field_key') 
                                                <small class="text-danger">{{ $message }}</small> 
                                            @enderror
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Type</label>
                                            <select wire:model="fields.{{ $editingField }}.type" class="form-select" disabled>
                                                @foreach($fieldTypes as $type)
                                                    <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Placeholder</label>
                                            <input type="text" wire:model="fields.{{ $editingField }}.placeholder" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Help Text</label>
                                            <textarea wire:model="fields.{{ $editingField }}.help_text" class="form-control" rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Default Value</label>
                                            <input type="text" wire:model="fields.{{ $editingField }}.default_value" class="form-control">
                                        </div>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" wire:model="fields.{{ $editingField }}.is_required" class="form-check-input">
                                            <label class="form-check-label">Required Field</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" wire:model="fields.{{ $editingField }}.is_visible" class="form-check-input">
                                            <label class="form-check-label">Visible</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <!-- Validation Rules -->
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Validation Rules</h6>
                                    </div>
                                    <div class="card-body">
                                        @if($fieldTypeConfigs[$field['type']]['has_validation'] ?? false)
                                            <div class="mb-2">
                                                <label class="form-label">Min Length/Value</label>
                                                <input type="number" wire:model="fields.{{ $editingField }}.validation.min" class="form-control" placeholder="Min">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">Max Length/Value</label>
                                                <input type="number" wire:model="fields.{{ $editingField }}.validation.max" class="form-control" placeholder="Max">
                                            </div>
                                            @if(in_array($field['type'], ['text', 'textarea']))
                                                <div class="mb-2">
                                                    <label class="form-label">Regex Pattern</label>
                                                    <input type="text" wire:model="fields.{{ $editingField }}.validation.regex" class="form-control" placeholder="/^[a-zA-Z]+$/">
                                                </div>
                                            @endif
                                            @if($field['type'] === 'file')
                                                <div class="mb-2">
                                                    <label class="form-label">Allowed File Types</label>
                                                    <input type="text" wire:model="fields.{{ $editingField }}.validation.mimes" class="form-control" placeholder="jpg,png,pdf">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Max File Size (KB)</label>
                                                    <input type="number" wire:model="fields.{{ $editingField }}.validation.max" class="form-control" placeholder="2048">
                                                </div>
                                            @endif
                                            @if(in_array($field['type'], ['select', 'radio', 'checkbox']))
                                                <div class="mb-2">
                                                    <label class="form-label">Allowed Values (comma separated)</label>
                                                    <input type="text" wire:model="fields.{{ $editingField }}.validation.in" class="form-control" placeholder="option1,option2,option3">
                                                </div>
                                            @endif
                                        @else
                                            <p class="text-muted">This field type has no validation rules.</p>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Options Management (for select, radio, checkbox) -->
                                @if(in_array($field['type'], ['select', 'radio', 'checkbox']))
                                    <div class="card mt-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">Options</h6>
                                        </div>
                                        <div class="card-body">
                                            @foreach($field['options'] ?? [] as $optionIndex => $option)
                                                <div class="input-group mb-2">
                                                    <input type="text" wire:model="fields.{{ $editingField }}.options.{{ $optionIndex }}.label" 
                                                           class="form-control" placeholder="Option label">
                                                    <input type="text" wire:model="fields.{{ $editingField }}.options.{{ $optionIndex }}.value" 
                                                           class="form-control" placeholder="Option value">
                                                    <button wire:click="removeOption({{ $editingField }}, {{ $optionIndex }})" 
                                                            class="btn btn-outline-danger">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            @endforeach
                                            <button wire:click="addOption({{ $editingField }})" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-plus"></i> Add Option
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showFieldModal', false)">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="updateField">Update Field</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Section Modal -->
    @if($showSectionModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Section</h5>
                        <button type="button" class="btn-close" wire:click="$set('showSectionModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Section Name <span class="text-danger">*</span></label>
                            <input type="text" wire:model="sectionName" class="form-control" placeholder="Enter section name...">
                            @error('sectionName') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showSectionModal', false)">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="saveSection">Add Section</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Schema Editor Modal -->
    @if($showSchemaModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-code"></i> JSON Schema Editor
                        </h5>
                        <button type="button" class="btn-close" wire:click="$set('showSchemaModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Edit the JSON schema directly. Changes will be synced with the form builder.
                        </div>
                        <textarea wire:model="schemaEditor" class="form-control font-monospace" rows="20" style="font-size: 12px;"></textarea>
                        @error('schemaEditor') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showSchemaModal', false)">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="saveSchema">
                            <i class="fas fa-check"></i> Apply Schema
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@push('styles')
<style>
.drag-handle {
    cursor: grab;
}
.drag-handle:active {
    cursor: grabbing;
}
.field-item {
    transition: all 0.2s ease;
}
.field-item:hover {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}
.border-2 {
    border-width: 2px !important;
}
.border-dashed {
    border-style: dashed !important;
}
.field-library-item:hover {
    background-color: #f8f9fa;
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('livewire:init', function () {
    const container = document.querySelector('.form-fields-container');
    if (container) {
        Sortable.create(container, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: function () {
                const items = Array.from(container.querySelectorAll('[data-sortable-item]'));
                const orderedIds = items.map(el => el.getAttribute('data-sortable-item'));
                @this.reorderFields(orderedIds);
            }
        });
    }
});
</script>
@endpush