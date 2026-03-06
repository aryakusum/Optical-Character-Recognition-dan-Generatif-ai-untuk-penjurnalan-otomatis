<!-- Navbar Component -->
<nav class="navbar navbar-dark" style="background-color: #2c3e50;">
    <div class="container">
        <a class="navbar-brand" href="/">Sistem Verifikasi Dokumen</a>

        <div class="d-flex align-items-center gap-4">
            <!-- Menu Navigation -->
            <div class="d-flex gap-3">
                <a class="nav-link text-white {{ request()->routeIs('journal.index') ? 'fw-bold' : '' }}"
                    href="{{ route('journal.index') }}">Upload</a>
                <a class="nav-link text-white {{ request()->routeIs('journals.*') ? 'fw-bold' : '' }}"
                    href="{{ route('journals.list') }}">Jurnal Umum</a>
            </div>

            <!-- Separator -->
            <div class="border-start border-secondary" style="height: 24px;"></div>

            <!-- User Info -->
            @auth
            <div class="d-flex align-items-center gap-2">
                <span class="text-white-50">{{ Auth::user()->name }}</span>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-outline-light">Logout</button>
                </form>
            </div>
            @else
            <a class="btn btn-sm btn-outline-light" href="{{ route('login') }}">Login</a>
            @endauth
        </div>
    </div>
</nav>