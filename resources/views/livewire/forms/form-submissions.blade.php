<div>
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Submissions: {{ $form->title }}
                </h5>
                <div>
                    <a href="{{ route('forms.edit', $form->slug) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Form
                    </a>
                    <button wire:click="exportCSV" class="btn btn-success btn-sm">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                    @if(count($selectedSubmissions) > 0)
                        <button wire:click="deleteSelected" class="btn btn-danger btn-sm" 
                                onclick="return confirm('Delete selected submissions?')">
                            <i class="fas fa-trash"></i> Delete Selected ({{ count($selectedSubmissions) }})
                        </button>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Search & Filters -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" wire:model="search" class="form-control" placeholder="Search submissions...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select wire:model="spamFilter" class="form-select">
                        <option value="all">All submissions</option>
                        <option value="legit">Legitimate only</option>
                        <option value="spam">Spam only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model="perPage" class="form-select">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <span class="text-muted">Total: {{ $form->submission_count }} submissions</span>
                </div>
            </div>

            <!-- Submissions Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="40">
                                <input type="checkbox" wire:model="selectAll">
                            </th>
                            <th wire:click="sortBy('submission_uuid')" style="cursor: pointer;">
                                ID <i class="fas fa-sort"></i>
                            </th>
                            <th wire:click="sortBy('submitted_at')" style="cursor: pointer;">
                                Submitted <i class="fas fa-sort"></i>
                            </th>
                            <th wire:click="sortBy('ip_address')" style="cursor: pointer;">
                                IP Address <i class="fas fa-sort"></i>
                            </th>
                            @foreach($form->fields as $field)
                                @if(!in_array($field->type, ['section', 'divider', 'heading', 'paragraph']))
                                    <th>{{ Str::limit($field->label, 20) }}</th>
                                @endif
                            @endforeach
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($submissions as $submission)
                            <tr class="{{ $submission->is_spam ? 'table-danger' : '' }}">
                                <td>
                                    <input type="checkbox" wire:model="selectedSubmissions" value="{{ $submission->id }}">
                                </td>
                                <td>
                                    <code>{{ Str::limit($submission->submission_uuid, 8) }}</code>
                                    @if($submission->is_spam)
                                        <span class="badge bg-danger ms-1" title="Automatically flagged as spam">
                                            <i class="fas fa-bug"></i> SPAM
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $submission->submitted_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $submission->ip_address }}</td>
                                @foreach($form->fields as $field)
                                    @if(!in_array($field->type, ['section', 'divider', 'heading', 'paragraph']))
                                        <td>
                                            @php
                                                $value = $submission->data[$field->field_key] ?? '';
                                                if (is_array($value)) {
                                                    $value = implode(', ', $value);
                                                }
                                                $value = Str::limit($value, 50);
                                            @endphp
                                            {{ $value }}
                                        </td>
                                    @endif
                                @endforeach
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button wire:click="toggleSpamFlag({{ $submission->id }})" 
                                                class="btn btn-sm btn-outline-warning"
                                                title="{{ $submission->is_spam ? 'Mark as legitimate' : 'Flag as spam' }}">
                                            <i class="fas {{ $submission->is_spam ? 'fa-check' : 'fa-bug' }}"></i>
                                        </button>
                                        <button wire:click="deleteSubmission({{ $submission->id }})" 
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Delete this submission?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="100" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted"></i>
                                    <p class="mt-2 text-muted">No submissions found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    Showing {{ $submissions->firstItem() ?? 0 }} to {{ $submissions->lastItem() ?? 0 }} 
                    of {{ $submissions->total() }} submissions
                </div>
                <div>
                    {{ $submissions->links() }}
                </div>
            </div>
        </div>
    </div>
</div>