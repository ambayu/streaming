<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — {{ config('app.name', 'StreamApp') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f1117;
            --bg-card: #1e2130;
            --bg-secondary: #1a1d27;
            --border-color: rgba(255,255,255,0.07);
            --accent: #6366f1;
            --accent-hover: #818cf8;
            --accent-glow: rgba(99,102,241,0.25);
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Background glow */
        body::before {
            content: '';
            position: fixed;
            top: -200px; left: 50%;
            transform: translateX(-50%);
            width: 700px; height: 500px;
            background: radial-gradient(ellipse, rgba(99,102,241,0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }

        /* Branding */
        .brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-icon {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, var(--accent), #818cf8);
            border-radius: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: #fff;
            box-shadow: 0 0 30px var(--accent-glow);
            margin-bottom: 14px;
        }

        .brand h1 {
            font-size: 1.5rem; font-weight: 700;
            color: var(--text-primary); margin: 0 0 4px;
            letter-spacing: -0.5px;
        }

        .brand p {
            font-size: 0.875rem; color: var(--text-muted); margin: 0;
        }

        /* Card */
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.4);
        }

        /* Field */
        .field { margin-bottom: 18px; }

        .field label {
            display: block;
            font-size: 0.8rem; font-weight: 600;
            color: var(--text-secondary); margin-bottom: 8px;
        }

        .input-wrap {
            display: flex; align-items: center;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px; overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .input-wrap:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .input-wrap.is-invalid { border-color: #ef4444; }

        .input-wrap .icon {
            padding: 0 13px;
            color: var(--text-muted); font-size: 0.85rem;
            flex-shrink: 0;
        }

        .input-wrap input {
            flex: 1;
            background: none; border: none; outline: none;
            color: var(--text-primary);
            padding: 12px 14px 12px 0;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
        }

        .input-wrap input::placeholder { color: var(--text-muted); }

        .input-wrap .toggle-pw {
            padding: 0 13px;
            color: var(--text-muted); font-size: 0.85rem;
            cursor: pointer; flex-shrink: 0;
            transition: color 0.2s;
        }

        .input-wrap .toggle-pw:hover { color: var(--text-secondary); }

        .error-msg { font-size: 0.78rem; color: #f87171; margin-top: 6px; }

        /* Remember + forgot */
        .row-extras {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px;
        }

        .remember-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.82rem; color: var(--text-secondary); cursor: pointer;
        }

        .remember-label input { accent-color: var(--accent); width: 15px; height: 15px; }

        .forgot-link {
            font-size: 0.82rem; color: var(--accent);
            text-decoration: none; transition: color 0.2s;
        }
        .forgot-link:hover { color: var(--accent-hover); }

        /* Submit */
        .btn-login {
            width: 100%; padding: 13px;
            border-radius: 11px; border: none; cursor: pointer;
            font-size: 0.9rem; font-weight: 700;
            background: linear-gradient(135deg, var(--accent), #818cf8);
            color: #fff; letter-spacing: 0.3px;
            box-shadow: 0 4px 18px var(--accent-glow);
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 9px;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(99,102,241,0.45);
        }

        /* Footer */
        .login-footer {
            text-align: center; margin-top: 20px;
            font-size: 0.8rem; color: var(--text-muted);
        }

        .login-footer a { color: var(--accent); text-decoration: none; }
        .login-footer a:hover { color: var(--accent-hover); }
    </style>
</head>
<body>

<div class="login-box">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-broadcast-tower"></i></div>
        <h1>{{ config('app.name', 'StreamApp') }}</h1>
        <p>Masuk untuk mengelola streaming Anda</p>
    </div>

    <div class="login-card">
        <form method="POST" action="{{ route('login') }}">
            @csrf

            {{-- Email --}}
            <div class="field">
                <label for="email">Email</label>
                <div class="input-wrap @error('email') is-invalid @enderror">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="email" name="email"
                           value="{{ old('email') }}"
                           placeholder="nama@email.com"
                           required autofocus autocomplete="email">
                </div>
                @error('email')
                    <p class="error-msg">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap @error('password') is-invalid @enderror">
                    <span class="icon"><i class="fas fa-lock"></i></span>
                    <input type="password" id="password" name="password"
                           placeholder="••••••••"
                           required autocomplete="current-password">
                    <span class="toggle-pw" onclick="togglePw()">
                        <i class="fas fa-eye" id="pwIcon"></i>
                    </span>
                </div>
                @error('password')
                    <p class="error-msg">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember + Forgot --}}
            <div class="row-extras">
                <label class="remember-label">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                    Ingat saya
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="forgot-link">Lupa password?</a>
                @endif
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Masuk
            </button>
        </form>
    </div>

    @if (Route::has('register'))
    <div class="login-footer">
        Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a>
    </div>
    @endif
</div>

<script>
    function togglePw() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('pwIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>

</body>
</html>
