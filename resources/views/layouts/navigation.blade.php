<nav class="navbar topbar">
    <div class="container-fluid">
        <button class="btn btn-outline-secondary sidebar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile">
            <i class="fas fa-bars"></i>
        </button>

        <div class="d-none d-lg-block">
            @isset($header)
                {{ $header }}
            @else
                <h1 class="h5 mb-0 text-dark">{{ config('app.name', 'AI Form Builder') }}</h1>
            @endisset
        </div>

        <ul class="navbar-nav ms-auto flex-row align-items-center gap-2">
            @guest
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('login') }}">Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('register') }}">Register</a>
                </li>
            @else
                <li class="nav-item">
                    <a href="{{ route('forms.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i>
                        <span class="d-none d-sm-inline">New Form</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="navbarDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="topbar-avatar">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <span class="d-none d-md-inline">{{ Auth::user()->name }}</span>
                        @if(Auth::user()->isSuperAdmin())
                            <span class="badge bg-danger">Super Admin</span>
                        @elseif(Auth::user()->isAdmin())
                            <span class="badge bg-primary">Admin</span>
                        @elseif(Auth::user()->hasRole('editor'))
                            <span class="badge bg-info">Editor</span>
                        @elseif(Auth::user()->hasRole('viewer'))
                            <span class="badge bg-secondary">Viewer</span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="{{ route('dashboard') }}">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                        <a class="dropdown-item" href="{{ route('forms.index') }}">
                            <i class="fas fa-list me-1"></i> My Forms
                        </a>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="fas fa-user me-1"></i> Profile
                        </a>
                        <hr class="dropdown-divider">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fas fa-sign-out-alt me-1"></i> Logout
                            </button>
                        </form>
                    </div>
                </li>
            @endguest
        </ul>
    </div>
</nav>
