@extends('layouts.app')

@section('page-styles')
<style>
    .page-header {
        margin-bottom: 28px;
    }

    .page-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -0.5px;
        margin: 0;
    }

    .page-header p {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 4px 0 0 0;
    }

    .stream-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .stream-card .card-head {
        padding: 14px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(255,255,255,0.02);
    }

    .stream-card .card-head h3 {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .stream-card .card-body-inner {
        padding: 20px;
    }

    .input-dark {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-primary);
        padding: 10px 14px;
        font-size: 0.875rem;
        width: 100%;
        transition: border-color 0.2s, box-shadow 0.2s;
        outline: none;
    }

    .input-dark:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow);
    }

    .form-label-dark {
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 8px;
        display: block;
    }

    .form-hint {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 6px;
    }

    .btn-save, .btn-stream {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 10px 18px;
        border-radius: 9px;
        font-size: 0.85rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
    }

    .btn-save {
        background: var(--accent);
        color: #fff;
        margin-top: 14px;
    }

    .btn-save:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
    }

    .btn-stream.outline {
        width: 100%;
        background: rgba(255,255,255,0.05);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
    }

    .btn-stream.outline:hover {
        background: rgba(99,102,241,0.1);
        border-color: rgba(99,102,241,0.3);
        color: var(--accent-hover);
    }

    .account-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .account-pill {
        padding: 10px 12px;
        border-radius: 10px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border-color);
    }

    .account-pill .label {
        display: block;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--text-muted);
        margin-bottom: 4px;
    }

    .account-pill .value {
        font-size: 0.82rem;
        color: var(--text-primary);
        word-break: break-word;
    }

    .info-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.85rem;
    }

    .info-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .info-row:first-child {
        padding-top: 0;
    }

    .info-label {
        color: var(--text-muted);
        min-width: 90px;
        font-size: 0.8rem;
        padding-top: 1px;
    }

    .info-value {
        color: var(--text-secondary);
        word-break: break-word;
        flex: 1;
    }

    .status-badge-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.3px;
        border: 1px solid transparent;
    }

    .status-pill.success {
        background: rgba(34,197,94,0.14);
        border-color: rgba(34,197,94,0.28);
        color: #4ade80;
    }

    .status-pill.warning {
        background: rgba(245,158,11,0.14);
        border-color: rgba(245,158,11,0.28);
        color: #fbbf24;
    }

    .status-pill.danger {
        background: rgba(239,68,68,0.14);
        border-color: rgba(239,68,68,0.28);
        color: #f87171;
    }

    .status-pill.neutral {
        background: rgba(148,163,184,0.12);
        border-color: rgba(148,163,184,0.22);
        color: var(--text-secondary);
    }
</style>
@endsection

@section('content')
@php
    $status = $setting->youtube_last_prepare_status ?? null;
    $statusUi = match ($status) {
        'ready' => ['label' => 'Cookie Valid', 'class' => 'success'],
        'already_live' => ['label' => 'Already Live', 'class' => 'success'],
        'not_live' => ['label' => 'Not Live', 'class' => 'warning'],
        'login_required' => ['label' => 'Login Required', 'class' => 'danger'],
        'missing_cookies' => ['label' => 'Cookie Missing', 'class' => 'danger'],
        default => ['label' => 'Belum Dicek', 'class' => 'neutral'],
    };
@endphp
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
        <h1><i class="fab fa-youtube me-2" style="color:#ff4444"></i>Konfigurasi YouTube</h1>
        <p>Atur koneksi YouTube, cookie login, dan uji pembukaan halaman Go Live untuk akun ini.</p>
    </div>
    <a href="{{ route('stream.index') }}" class="btn-stream outline" style="width:auto;">
        <i class="fas fa-satellite-dish"></i> Kembali ke Stream
    </a>
</div>

<div class="stream-card">
    <div class="card-head">
        <h3><i class="fas fa-user-circle"></i> Akun Aktif</h3>
    </div>
    <div class="card-body-inner">
        <div class="account-summary">
            <div class="account-pill">
                <span class="label">Akun Website</span>
                <span class="value">{{ auth()->user()->name }}</span>
            </div>
            <div class="account-pill">
                <span class="label">Email Login</span>
                <span class="value">{{ auth()->user()->email }}</span>
            </div>
            <div class="account-pill">
                <span class="label">Email Google</span>
                <span class="value">{{ $setting->google_email ?? 'Belum diatur' }}</span>
            </div>
            <div class="account-pill">
                <span class="label">Channel ID</span>
                <span class="value">{{ $setting->youtube_channel_id ?? 'Otomatis / belum diatur' }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fab fa-google" style="color:#60a5fa;"></i> Koneksi Akun Google</h3>
            </div>
            <div class="card-body-inner">
                <form action="{{ route('stream.storeYoutubeConnection') }}" method="POST">
                    @csrf
                    <label class="form-label-dark" for="google_email">Email Google</label>
                    <input type="email"
                           name="google_email"
                           id="google_email"
                           class="input-dark"
                           value="{{ old('google_email', $setting->google_email ?? '') }}"
                           placeholder="nama@gmail.com">
                    @error('google_email')
                        <p style="font-size:0.78rem;color:#f87171;margin:6px 0 0 0;">{{ $message }}</p>
                    @enderror

                    <label class="form-label-dark" for="youtube_channel_id" style="margin-top:14px;">Channel ID <span style="color:var(--text-muted);font-weight:400">(opsional tapi disarankan)</span></label>
                    <input type="text"
                           name="youtube_channel_id"
                           id="youtube_channel_id"
                           class="input-dark"
                           value="{{ old('youtube_channel_id', $setting->youtube_channel_id ?? '') }}"
                           placeholder="UCxxxxxxxxxxxxxxxxxxxxxx">
                    @error('youtube_channel_id')
                        <p style="font-size:0.78rem;color:#f87171;margin:6px 0 0 0;">{{ $message }}</p>
                    @enderror

                    <p class="form-hint">Email dan channel ID ini dipakai khusus untuk akun website yang sedang login.</p>

                    <button type="submit" class="btn-save">
                        <i class="fas fa-link"></i> Simpan Koneksi
                    </button>
                </form>
            </div>
        </div>

        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-cookie-bite"></i> Cookie Login YouTube</h3>
            </div>
            <div class="card-body-inner">
                <form action="{{ route('stream.storeYoutubeCookies') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label-dark" for="youtube_cookies">Upload Cookie YouTube (.json)</label>
                    <input type="file"
                           name="youtube_cookies"
                           id="youtube_cookies"
                           class="input-dark"
                           accept=".json,application/json,text/plain">
                    @error('youtube_cookies')
                        <p style="font-size:0.78rem;color:#f87171;margin:6px 0 0 0;">{{ $message }}</p>
                    @enderror

                    <p class="form-hint">Gunakan cookie login Google/YouTube yang masih valid agar bot dapat membuka halaman live control room.</p>

                    <button type="submit" class="btn-save">
                        <i class="fas fa-upload"></i> Upload Cookie
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-vial"></i> Uji Automasi</h3>
            </div>
            <div class="card-body-inner">
                <div class="status-badge-grid">
                    <span class="status-pill {{ $statusUi['class'] }}">
                        <i class="fas fa-circle"></i> {{ $statusUi['label'] }}
                    </span>
                    @if (($setting->youtube_last_prepare_status ?? null) === 'already_live')
                        <span class="status-pill success">
                            <i class="fas fa-broadcast-tower"></i> YouTube Masih Live
                        </span>
                    @elseif (($setting->youtube_last_prepare_status ?? null) === 'not_live')
                        <span class="status-pill warning">
                            <i class="fas fa-circle-pause"></i> Aman Untuk Start
                        </span>
                    @endif
                </div>

                <form action="{{ route('stream.refreshYoutubeStatus') }}" method="POST" style="margin-bottom:12px;">
                    @csrf
                    <button type="submit" class="btn-stream outline">
                        <i class="fas fa-rotate-right"></i> Refresh Status Cookie & Live
                    </button>
                </form>

                <form action="{{ route('stream.prepareYoutube') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-stream outline">
                        <i class="fab fa-youtube"></i> Coba Buka Go Live Sekarang
                    </button>
                </form>

                <div style="margin-top:16px;padding:12px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid var(--border-color);">
                    <div class="info-row" style="padding-top:0;">
                        <span class="info-label">Status</span>
                        <span class="info-value">{{ $setting->youtube_last_prepare_status ?? 'Belum diuji' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Cookie</span>
                        <span class="info-value">{{ !empty($setting->youtube_cookie_path ?? null) ? 'Tersimpan' : 'Belum ada' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Terhubung</span>
                        <span class="info-value">{{ optional($setting->youtube_connected_at ?? null)->format('d M Y H:i') ?? 'Belum ada data' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Dicek</span>
                        <span class="info-value">{{ optional($setting->youtube_last_checked_at ?? null)->format('d M Y H:i') ?? 'Belum pernah dicek' }}</span>
                    </div>
                    <div class="info-row" style="border-bottom:none;padding-bottom:0;">
                        <span class="info-label">Pesan</span>
                        <span class="info-value">{{ $setting->youtube_last_prepare_message ?? 'Belum ada hasil automasi.' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-info-circle"></i> Catatan</h3>
            </div>
            <div class="card-body-inner">
                <p style="font-size:0.83rem;color:var(--text-secondary);margin:0 0 10px 0;">
                    Konfigurasi di halaman ini berlaku per akun aplikasi, sehingga tiap user bisa memakai akun YouTube yang berbeda.
                </p>
                <p style="font-size:0.83rem;color:var(--text-secondary);margin:0;">
                    Di halaman <strong style="color:var(--text-primary);">Stream</strong>, kita hanya tampilkan status koneksi agar operasional streaming tetap ringkas.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
