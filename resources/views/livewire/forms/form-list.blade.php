<div>
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> My Forms
                </h5>
                <a href="{{ route('forms.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Create New Form
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" wire:model="search" class="form-control" placeholder="Search forms...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model="filter" class="form-select">
                        <option value="all">All Forms</option>
                        <option value="published">Published</option>
                        <option value="draft">Drafts</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model="perPage" class="form-select">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <span class="text-muted">Total: {{ $forms->total() }} forms</span>
                </div>
            </div>

            <!-- Forms Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th wire:click="sortBy('title')" style="cursor: pointer;">
                                Title <i class="fas fa-sort"></i>
                            </th>
                            <th wire:click="sortBy('submission_count')" style="cursor: pointer;">
                                Submissions <i class="fas fa-sort"></i>
                            </th>
                            <th wire:click="sortBy('is_published')" style="cursor: pointer;">
                                Status <i class="fas fa-sort"></i>
                            </th>
                            <th wire:click="sortBy('created_at')" style="cursor: pointer;">
                                Created <i class="fas fa-sort"></i>
                            </th>
                            <th wire:click="sortBy('updated_at')" style="cursor: pointer;">
                                Updated <i class="fas fa-sort"></i>
                            </th>
                            <th width="200">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($forms as $form)
                            <tr>
                                <td>
                                    <strong>{{ $form->title }}</strong>
                                    <br>
                                    <small class="text-muted">{{ Str::limit($form->description, 50) }}</small>
                                </td>
                                <td>{{ $form->submission_count }}</td>
                                <td>
                                    @if($form->is_published)
                                        <span class="badge bg-success">Published</span>
                                    @else
                                        <span class="badge bg-warning">Draft</span>
                                    @endif
                                </td>
                                <td>{{ $form->created_at->format('Y-m-d') }}</td>
                                <td>{{ $form->updated_at->format('Y-m-d') }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('forms.edit', $form->slug) }}" class="btn btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ route('forms.show', $form->slug) }}" class="btn btn-outline-success" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('forms.submissions', $form->slug) }}" class="btn btn-outline-info">
                                            <i class="fas fa-list"></i>
                                        </a>
                                        <button wire:click="deleteForm({{ $form->id }})" class="btn btn-outline-danger" onclick="return confirm('Delete this form?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-file-alt fa-3x text-muted"></i>
                                    <p class="mt-2 text-muted">No forms found. Create your first form!</p>
                                    <a href="{{ route('forms.create') }}" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Create Form
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    Showing {{ $forms->firstItem() ?? 0 }} to {{ $forms->lastItem() ?? 0 }} of {{ $forms->total() }} forms
                </div>
                <div>
                    {{ $forms->links() }}
                </div>
            </div>
        </div>
    </div>
</div>