<div>
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-history"></i> Version History: {{ $form->title }}
                </h5>
                <a href="{{ route('forms.edit', $form->slug) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Form
                </a>
            </div>
        </div>
        <div class="card-body">
            @if($versions->isEmpty())
                <div class="text-center py-5">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">No versions recorded yet.</h6>
                    <p class="text-muted small mb-0">
                        A snapshot is captured automatically every time you save the form with changes.
                    </p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Version</th>
                                <th>Date</th>
                                <th>Author</th>
                                <th>Note</th>
                                <th>Fields</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($versions as $version)
                                <tr class="{{ $previewVersionId === $version->id ? 'table-primary' : '' }}">
                                    <td>
                                        <span class="badge bg-secondary">v{{ $version->version }}</span>
                                    </td>
                                    <td>{{ $version->created_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ $version->author?->name ?? 'System' }}</td>
                                    <td>
                                        <span class="text-muted small">{{ $version->note ?? '—' }}</span>
                                    </td>
                                    <td>{{ $version->field_count }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button wire:click="previewVersion({{ $version->id }})" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button wire:click="askRollback({{ $version->id }})" class="btn btn-outline-warning">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Rollback confirmation -->
                @if($confirmRollbackId)
                    @php $rollbackVersion = $versions->firstWhere('id', $confirmRollbackId); @endphp
                    <div class="alert alert-warning mt-3">
                        <h6><i class="fas fa-exclamation-triangle"></i> Roll back to v{{ $rollbackVersion->version }}?</h6>
                        <p class="small mb-2">
                            The form's fields and settings will be replaced with this version.
                            A snapshot of the current state is saved first, so you can undo this rollback.
                        </p>
                        <div class="d-flex gap-2">
                            <button wire:click="rollback({{ $confirmRollbackId }})" class="btn btn-warning btn-sm">
                                <i class="fas fa-undo"></i> Yes, roll back
                            </button>
                            <button wire:click="cancelRollback" class="btn btn-outline-secondary btn-sm">Cancel</button>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <!-- Version preview modal -->
    @if($preview)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-eye"></i> v{{ $preview->version }} — {{ $preview->schema['title'] ?? $form->title }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closePreview" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if($previewDiff)
                            @if($previewDiff['added'])
                                <h6 class="text-success"><i class="fas fa-plus-circle"></i> Added fields</h6>
                                <ul class="small mb-3">
                                    @foreach($previewDiff['added'] as $label)
                                        <li>{{ $label }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($previewDiff['removed'])
                                <h6 class="text-danger"><i class="fas fa-minus-circle"></i> Removed fields</h6>
                                <ul class="small mb-3">
                                    @foreach($previewDiff['removed'] as $label)
                                        <li>{{ $label }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($previewDiff['changed'])
                                <h6 class="text-warning"><i class="fas fa-pen"></i> Changed</h6>
                                <ul class="small mb-3">
                                    @foreach($previewDiff['changed'] as $change)
                                        <li>
                                            <strong>{{ $change['label'] }}</strong>:
                                            <code>{{ $change['attribute'] }}</code>
                                            changed from <code>{{ $change['from'] ?? '—' }}</code>
                                            to <code>{{ $change['to'] ?? '—' }}</code>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if(empty($previewDiff['added']) && empty($previewDiff['removed']) && empty($previewDiff['changed']))
                                <p class="small text-muted">No structural differences from the previous version.</p>
                            @endif
                        @endif

                        <h6 class="mt-3"><i class="fas fa-tasks"></i> Fields ({{ $preview->field_count }})</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Label</th>
                                        <th>Type</th>
                                        <th>Required</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($preview->schema['fields'] ?? [] as $field)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                {{ $field['label'] }}
                                                @if($field['type'] === 'section')
                                                    <span class="badge bg-light text-dark ms-1">section</span>
                                                @endif
                                            </td>
                                            <td><code>{{ $field['type'] }}</code></td>
                                            <td>{{ !empty($field['is_required']) ? 'Yes' : 'No' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button wire:click="closePreview" class="btn btn-outline-secondary btn-sm">Close</button>
                        <button wire:click="askRollback({{ $preview->id }})" class="btn btn-warning btn-sm">
                            <i class="fas fa-undo"></i> Roll back to this version
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
