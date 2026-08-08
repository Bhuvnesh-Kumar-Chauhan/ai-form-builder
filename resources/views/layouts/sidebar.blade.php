<aside class="sidebar">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-white text-decoration-none gap-2">
            <i class="fas fa-pencil-ruler text-primary fs-4"></i>
            <span class="fw-bold">{{ config('app.name', 'AI Form Builder') }}</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="sidebar-section">Main</li>
            <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            @auth
                <li class="sidebar-section">Form Module</li>

                @can('view forms')
                    <li class="nav-item">
                        <a href="{{ route('forms.index') }}" class="nav-link {{ request()->routeIs('forms.index') ? 'active' : '' }}">
                            <i class="fas fa-list"></i>
                            <span>All Forms</span>
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <span class="nav-link sidebar-locked" title="Requires 'view forms' permission">
                            <i class="fas fa-lock"></i>
                            <span>All Forms</span>
                        </span>
                    </li>
                @endcan

                @can('create forms')
                    <li class="nav-item">
                        <a href="{{ route('forms.create') }}" class="nav-link {{ request()->routeIs('forms.create') ? 'active' : '' }}">
                            <i class="fas fa-plus-circle"></i>
                            <span>Create Form</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('forms.import') }}" class="nav-link {{ request()->routeIs('forms.import') ? 'active' : '' }}">
                            <i class="fas fa-file-import"></i>
                            <span>Import Form</span>
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <span class="nav-link sidebar-locked" title="Requires 'create forms' permission">
                            <i class="fas fa-lock"></i>
                            <span>Create Form</span>
                        </span>
                    </li>
                @endcan

                @if(request()->routeIs('forms.edit') || request()->routeIs('forms.submissions'))
                    <li class="nav-item">
                        <a href="{{ route('forms.index') }}" class="nav-link">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Forms</span>
                        </a>
                    </li>
                @endif

                <li class="sidebar-section">Submission Module</li>

                @can('view submissions')
                    <li class="nav-item">
                        <a href="{{ route('forms.index') }}" class="nav-link {{ request()->routeIs('forms.submissions') ? 'active' : '' }}">
                            <i class="fas fa-envelope-open-text"></i>
                            <span>Submissions</span>
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <span class="nav-link sidebar-locked" title="Requires 'view submissions' permission">
                            <i class="fas fa-lock"></i>
                            <span>Submissions</span>
                        </span>
                    </li>
                @endcan

                @can('export submissions')
                    <li class="nav-item">
                        <a href="{{ route('forms.index') }}" class="nav-link">
                            <i class="fas fa-download"></i>
                            <span>Export Submissions</span>
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <span class="nav-link sidebar-locked" title="Requires 'export submissions' permission">
                            <i class="fas fa-lock"></i>
                            <span>Export Submissions</span>
                        </span>
                    </li>
                @endcan

                <li class="sidebar-section">Administration</li>

                @can('manage permissions')
                    <li class="nav-item">
                        <a href="{{ route('admin.permissions') }}" class="nav-link {{ request()->routeIs('admin.permissions') ? 'active' : '' }}">
                            <i class="fas fa-shield-alt"></i>
                            <span>Roles & Permissions</span>
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <span class="nav-link sidebar-locked" title="Requires 'manage permissions' permission">
                            <i class="fas fa-lock"></i>
                            <span>Roles & Permissions</span>
                        </span>
                    </li>
                @endcan

                @can('manage settings')
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                @else
                    <li class="nav-item">
                        <span class="nav-link sidebar-locked" title="Requires 'manage settings' permission">
                            <i class="fas fa-lock"></i>
                            <span>Settings</span>
                        </span>
                    </li>
                @endcan
            @endauth
        </ul>
    </nav>

    @auth
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="d-flex align-items-center gap-2">
                    <div class="sidebar-avatar">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-white text-truncate small fw-semibold">{{ Auth::user()->name }}</div>
                        <div class="text-muted small text-truncate">{{ Auth::user()->email }}</div>
                    </div>
                </div>
                <div class="mt-2">
                    @foreach(Auth::user()->getRoleNames() as $role)
                        <span class="badge badge-role">
                            <i class="fas fa-user-tag"></i> {{ ucwords($role) }}
                        </span>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger w-100 mt-3">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    @endauth
</aside>
