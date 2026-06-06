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
                <div class="text-end">
                    <span class="text-white d-block" style="font-size: 0.85rem; line-height: 1.2;">{{ Auth::user()->name }}</span>
                    <span class="badge bg-{{ Auth::user()->role === 'verifikator' ? 'info' : (Auth::user()->role === 'admin' ? 'danger' : 'secondary') }}" style="font-size: 0.65rem;">
                        {{ Auth::user()->role_label }}
                    </span>
                </div>
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