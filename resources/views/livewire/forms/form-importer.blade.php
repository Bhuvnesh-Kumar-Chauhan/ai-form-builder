<div>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-file-import"></i> Import Form from Document
            </h5>
        </div>
        <div class="card-body">

            @if($error && ! $importJobId)
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-times-circle me-1"></i> {{ $error }}
                </div>
            @endif

            @if(! $importJobId)
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="border border-dashed rounded-3 p-4 text-center">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                            <h6 class="fw-bold">Drop a Word or Excel form here</h6>
                            <p class="text-muted small mb-3">
                                We read the document and convert it into an editable form you can review before saving.
                            </p>

                            <input type="file"
                                wire:model="file"
                                class="form-control w-50 mx-auto"
                                accept=".docx,.xlsx,.xls">

                            @error('file')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror

                            <div wire:loading wire:target="file" class="mt-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                Uploading...
                            </div>

                            <div class="mt-4">
                                <small class="text-muted d-block mb-2"><strong>What happens next</strong></small>
                                <div class="row text-start small">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <i class="fas fa-file-word text-success"></i>
                                            <strong>.docx</strong> &mdash; headings become sections, paragraphs become
                                            fields, and bulleted lists / checkbox tables become choice options.
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <i class="fas fa-file-excel text-success"></i>
                                            <strong>.xlsx</strong> &mdash; either a documented template
                                            (<code>type | label | required | options | placeholder | help_text | section</code>)
                                            or a plain sheet whose header row is the questions and rows are answers
                                            (types are inferred from the values).
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($importJob && in_array($importJob->status, ['queued', 'processing'], true))
                <div wire:poll.2s="refreshStatus" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 mb-1"><strong>Parsing your document...</strong></p>
                    <p class="text-muted small mb-0">
                        Larger files are parsed in the background so you can keep working.
                    </p>
                </div>
            @endif

            @if($importJob && $importJob->status === 'failed' && $error)
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-times-circle me-1"></i> Import failed: {{ $error }}
                </div>
                <button type="button" wire:click="discardImport" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Try another file
                </button>
            @endif

            @if($fields && $importJob && $importJob->status === 'succeeded')
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Form title</label>
                        <input type="text" wire:model="title" class="form-control" placeholder="Form title">
                        @error('title') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Layout detected</label>
                        <div>
                            @if($layout === 'docx')
                                <span class="badge bg-success">Word document</span>
                            @elseif($layout === 'xlsx-template')
                                <span class="badge bg-success">Excel template layout</span>
                            @elseif($layout === 'xlsx-data')
                                <span class="badge bg-success">Excel data layout</span>
                            @endif
                            <span class="badge bg-secondary">{{ count($fields) }} fields</span>
                        </div>
                    </div>
                    <div class="col-12 mt-2">
                        <label class="form-label fw-bold">Description</label>
                        <input type="text" wire:model="description" class="form-control" placeholder="Optional description">
                    </div>
                </div>

                @if(count($warnings))
                    <div class="alert alert-warning py-2" role="alert">
                        <strong><i class="fas fa-exclamation-triangle me-1"></i> Things to check</strong>
                        <ul class="mb-0 mt-1">
                            @foreach($warnings as $warning)
                                <li class="small">{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">
                        <i class="fas fa-list-check"></i> Review fields (all editable before saving)
                    </h6>
                    <div>
                        <button type="button" wire:click="refineWithAi" wire:loading.attr="disabled"
                            class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-wand-magic-sparkles"></i>
                            <span wire:loading.remove wire:target="refineWithAi">Refine with AI</span>
                            <span wire:loading wire:target="refineWithAi">Refining...</span>
                        </button>
                    </div>
                </div>

                @if($refining)
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        Asking the AI to improve types and validation...
                    </div>
                @elseif($refinedCount > 0)
                    <div class="alert alert-success py-2" role="alert">
                        <i class="fas fa-check-circle me-1"></i>
                        AI refinement updated {{ $refinedCount }} field(s)
                        {{ $refinedModel ? ' (' . $refinedModel . ')' : '' }}.
                        The structure always comes from your file &mdash; labels and options are never rewritten.
                    </div>
                @endif

                @if($error)
                    <div class="alert alert-danger py-2" role="alert">{{ $error }}</div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="35">#</th>
                                <th>Label</th>
                                <th width="160">Type</th>
                                <th width="90" class="text-center">Required</th>
                                <th>Options</th>
                                <th width="90">Source</th>
                                <th width="60"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fields as $index => $field)
                                <tr>
                                    <td class="text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        <input type="text" wire:model="fields.{{ $index }}.label" class="form-control form-control-sm">
                                        @if(($field['type'] ?? '') === 'section')
                                            <small class="text-primary"><i class="fas fa-heading me-1"></i>Section heading</small>
                                        @endif
                                    </td>
                                    <td>
                                        <select wire:model="fields.{{ $index }}.type" class="form-select form-select-sm">
                                            @foreach($fieldTypes as $type)
                                                <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox"
                                            wire:model="fields.{{ $index }}.is_required"
                                            class="form-check-input"
                                            {{ in_array($field['type'] ?? '', ['section', 'divider', 'heading', 'paragraph'], true) ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        @if(! empty($field['options']))
                                            <span class="small text-muted">
                                                {{ collect($field['options'])->pluck('label')->take(4)->implode(', ') }}
                                                @if(count($field['options']) > 4) +{{ count($field['options']) - 4 }} more @endif
                                            </span>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ ($field['confidence'] ?? 'low') === 'high' ? 'success' : (($field['confidence'] ?? 'low') === 'medium' ? 'warning' : 'secondary') }}">
                                            {{ $field['confidence'] ?? 'low' }}
                                        </span>
                                        <br>
                                        <small class="text-muted">{{ Str::after($field['origin'] ?? '', ':') }}</small>
                                    </td>
                                    <td>
                                        <button type="button" wire:click="removeField({{ $index }})"
                                            class="btn btn-outline-danger btn-sm"
                                            onclick="return confirm('Remove this field?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @error('fields')
                    <div class="alert alert-danger py-2" role="alert">{{ $message }}</div>
                @enderror

                <div class="d-flex justify-content-between">
                    <button type="button" wire:click="discardImport" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Choose a different file
                    </button>
                    <button type="button" wire:click="saveForm" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i> Create Form
                    </button>
                </div>
            @endif

        </div>
    </div>
</div>
