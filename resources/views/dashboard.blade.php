<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Forms</div>
                        <div class="fs-3 fw-bold">{{ $totalForms }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Published</div>
                        <div class="fs-3 fw-bold">{{ $publishedForms }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning-subtle text-warning">
                        <i class="fas fa-pen"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Drafts</div>
                        <div class="fs-3 fw-bold">{{ $draftForms }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info-subtle text-info">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Submissions</div>
                        <div class="fs-3 fw-bold">{{ $totalSubmissions }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fas fa-shield-alt me-1"></i> My Role &amp; Permissions</h5>
            <div>
                @foreach($userRoles as $role)
                    <span class="badge bg-primary me-1"><i class="fas fa-user-tag"></i> {{ ucwords($role) }}</span>
                @endforeach
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($modules as $moduleName => $permissions)
                    <div class="col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <h6 class="mb-3 text-uppercase small fw-bold text-muted">{{ $moduleName }}</h6>
                            <ul class="list-unstyled mb-0">
                                @foreach($permissions as $perm => $label)
                                    <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-1">
                                        <span class="small">{{ $label }}</span>
                                        @if($userPermissions->contains($perm))
                                            <i class="fas fa-check-circle text-success" title="Access granted"></i>
                                        @else
                                            <i class="fas fa-lock text-danger" title="Requires '{{ $perm }}'"></i>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-1"></i> My Forms</h5>
                    <a href="{{ route('forms.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Submissions</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($forms as $form)
                                    <tr>
                                        <td>
                                            <strong>{{ $form->title }}</strong>
                                            <br>
                                            <small class="text-muted">{{ Str::limit($form->description, 45) }}</small>
                                        </td>
                                        <td>
                                            @if($form->is_published)
                                                <span class="badge bg-success">Published</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Draft</span>
                                            @endif
                                        </td>
                                        <td>{{ $form->submissions_count }}</td>
                                        <td>{{ $form->created_at->format('Y-m-d') }}</td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('forms.edit', $form->slug) }}" class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="{{ route('forms.show', $form->slug) }}" class="btn btn-outline-success" title="Fill Form" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('forms.submissions', $form->slug) }}" class="btn btn-outline-info" title="Submissions">
                                                    <i class="fas fa-list"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-file-alt fa-3x text-muted"></i>
                                            <p class="mt-2 mb-2 text-muted">No forms found yet.</p>
                                            <a href="{{ route('forms.create') }}" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Create Your First Form
                                            </a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-clock me-1"></i> Recent Submissions</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($recentSubmissions as $submission)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="min-w-0">
                                        <strong class="d-block text-truncate">{{ $submission->form->title ?? 'Form' }}</strong>
                                        <small class="text-muted">{{ $submission->submitted_at->diffForHumans() }}</small>
                                    </div>
                                    @if($submission->is_spam)
                                        <span class="badge bg-danger">Spam</span>
                                    @else
                                        <span class="badge bg-success">New</span>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted py-4">
                                No submissions yet.
                            </li>
                        @endforelse
                    </ul>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-grid">
                        <a href="{{ route('forms.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-arrow-right me-1"></i> Go to Forms
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
