<header class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ auth('web')->check() ? route('admin.dashboard') : route('admin.login') }}">Code Arena</a>
        @auth('web')
            <div class="d-flex align-items-center gap-3 ms-auto">
                <a class="nav-link text-white" href="{{ route('admin.dashboard') }}">Dashboard</a>
                <span class="navbar-text">{{ auth('web')->user()->name }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="btn btn-outline-light btn-sm" type="submit">Log out</button>
                </form>
            </div>
        @endauth
    </div>
</header>
