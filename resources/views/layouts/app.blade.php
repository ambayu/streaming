<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Streaming App') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-secondary: #1a1d27;
            --bg-card: #1e2130;
            --bg-card-hover: #242840;
            --border-color: rgba(255,255,255,0.07);
            --accent: #6366f1;
            --accent-hover: #818cf8;
            --accent-glow: rgba(99,102,241,0.25);
            --danger: #ef4444;
            --success: #22c55e;
            --warning: #f59e0b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --navbar-height: 60px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
        }

        /* ── Navbar ── */
        .app-navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--navbar-height);
            background: rgba(15,17,23,0.85);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            padding: 0 24px;
            z-index: 1000;
            gap: 16px;
        }

        .app-navbar .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-primary);
            letter-spacing: -0.3px;
        }

        .app-navbar .brand-icon {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--accent), #818cf8);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            color: #fff;
            box-shadow: 0 0 14px var(--accent-glow);
        }

        .app-navbar .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: auto;
        }

        .app-navbar .nav-link-item {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all 0.2s;
            border: none;
            background: none;
            cursor: pointer;
        }

        .app-navbar .nav-link-item:hover,
        .app-navbar .nav-link-item.active {
            background: rgba(99,102,241,0.12);
            color: var(--accent-hover);
        }

        .app-navbar .nav-link-item i { font-size: 0.8rem; }

        .app-navbar .nav-divider {
            width: 1px;
            height: 20px;
            background: var(--border-color);
            margin: 0 6px;
        }

        .app-navbar .btn-logout {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #f87171;
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            cursor: pointer;
            transition: all 0.2s;
        }

        .app-navbar .btn-logout:hover {
            background: rgba(239,68,68,0.2);
            border-color: rgba(239,68,68,0.4);
        }

        /* ── Page Body ── */
        .page-wrapper {
            padding-top: calc(var(--navbar-height) + 28px);
            padding-bottom: 40px;
            min-height: 100vh;
        }

        /* ── Alert Global ── */
        .global-alerts {
            position: fixed;
            top: calc(var(--navbar-height) + 12px);
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-width: 380px;
        }

        .toast-alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
            border: 1px solid;
            animation: slideInRight 0.3s ease;
        }

        .toast-alert.success {
            background: rgba(34,197,94,0.15);
            border-color: rgba(34,197,94,0.3);
            color: #4ade80;
        }

        .toast-alert.error {
            background: rgba(239,68,68,0.15);
            border-color: rgba(239,68,68,0.3);
            color: #f87171;
        }

        .toast-alert .btn-close {
            filter: invert(1);
            opacity: 0.6;
            margin-left: auto;
        }

        @keyframes slideInRight {
            from { transform: translateX(30px); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }

        @yield('styles')
    </style>
    @yield('page-styles')
</head>
<body>

    <nav class="app-navbar">
        <a href="{{ route('videos.index') }}" class="brand">
            <div class="brand-icon"><i class="fas fa-broadcast-tower"></i></div>
            {{ config('app.name', 'StreamApp') }}
        </a>

        <div class="nav-links">
            @auth
                <a href="{{ route('videos.index') }}"
                   class="nav-link-item {{ request()->routeIs('videos.*') ? 'active' : '' }}">
                    <i class="fas fa-film"></i> Videos
                </a>
                <a href="{{ route('playlists.index') }}"
                   class="nav-link-item {{ request()->routeIs('playlists.*') ? 'active' : '' }}">
                    <i class="fas fa-list-ul"></i> Playlist
                </a>
                <a href="{{ route('stream.index') }}"
                   class="nav-link-item {{ request()->routeIs('stream.*') ? 'active' : '' }}">
                    <i class="fas fa-satellite-dish"></i> Stream
                </a>
                <div class="nav-divider"></div>
                <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="nav-link-item">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            @endauth
        </div>
    </nav>

    <!-- Toast Alerts -->
    <div class="global-alerts">
        @if (session('success'))
            <div class="toast-alert success">
                <i class="fas fa-check-circle"></i>
                {{ session('success') }}
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="toast-alert error">
                <i class="fas fa-exclamation-circle"></i>
                {{ session('error') }}
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        @endif
    </div>

    <div class="page-wrapper">
        <div class="container-xl px-4">
            @yield('content')
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @yield('scripts')
</body>
</html>
