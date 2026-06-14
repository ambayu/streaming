@extends('layouts.app')

@section('page-styles')
<style>
    /* ── Design tokens (scoped to stream page) ── */
    :root {
        --card-radius: 14px;
    }

    /* ── Page Header ── */
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

    /* ── Status Badge ── */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.3px;
    }

    .status-badge.live {
        background: rgba(34,197,94,0.15);
        border: 1px solid rgba(34,197,94,0.3);
        color: #4ade80;
    }

    .status-badge.offline {
        background: rgba(100,116,139,0.15);
        border: 1px solid rgba(100,116,139,0.25);
        color: var(--text-muted);
    }

    .status-badge .dot {
        width: 7px; height: 7px;
        border-radius: 50%;
    }

    .status-badge.live .dot {
        background: #4ade80;
        box-shadow: 0 0 6px #22c55e;
        animation: pulse 1.5s infinite;
    }

    .status-badge.offline .dot { background: var(--text-muted); }

    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.5; transform: scale(1.3); }
    }

    /* ── Card ── */
    .stream-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--card-radius);
        overflow: hidden;
        margin-bottom: 20px;
        transition: border-color 0.2s;
        display: flex;
        flex-direction: column;
    }

    .stream-card:hover { border-color: rgba(99,102,241,0.2); }

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

    .stream-card .card-head h3 i {
        color: var(--accent);
        font-size: 0.85rem;
    }

    .stream-card .card-body-inner {
        padding: 20px;
        flex: 1;
    }

    .dashboard-column {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .dashboard-column .stream-card {
        margin-bottom: 0;
    }

    /* ── Collapsible toggle ── */
    .collapse-btn {
        background: none;
        border: none;
        padding: 4px 8px;
        border-radius: 6px;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.8rem;
    }

    .collapse-btn:hover { background: rgba(255,255,255,0.07); color: var(--text-secondary); }

    .collapse-btn .fa-chevron-down {
        transition: transform 0.3s;
    }

    .collapse-btn.open .fa-chevron-down { transform: rotate(180deg); }

    /* ── Control Buttons ── */
    .btn-stream {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 11px 20px;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        width: 100%;
        text-decoration: none;
    }

    .btn-stream + .btn-stream { margin-top: 10px; }

    .btn-stream.primary {
        background: linear-gradient(135deg, var(--accent), #818cf8);
        color: #fff;
        box-shadow: 0 4px 15px var(--accent-glow);
    }

    .btn-stream.primary:hover:not(:disabled) {
        box-shadow: 0 6px 20px rgba(99,102,241,0.4);
        transform: translateY(-1px);
    }

    .btn-stream.success {
        background: linear-gradient(135deg, #16a34a, #22c55e);
        color: #fff;
        box-shadow: 0 4px 12px rgba(34,197,94,0.25);
    }

    .btn-stream.success:hover:not(:disabled) {
        box-shadow: 0 6px 18px rgba(34,197,94,0.35);
        transform: translateY(-1px);
    }

    .btn-stream.danger {
        background: rgba(239,68,68,0.12);
        color: #f87171;
        border: 1px solid rgba(239,68,68,0.25);
    }

    .btn-stream.danger:hover:not(:disabled) {
        background: rgba(239,68,68,0.2);
        border-color: rgba(239,68,68,0.4);
    }

    .btn-stream.outline {
        background: rgba(255,255,255,0.05);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
    }

    .btn-stream.outline:hover {
        background: rgba(99,102,241,0.1);
        border-color: rgba(99,102,241,0.3);
        color: var(--accent-hover);
    }

    .btn-stream:disabled {
        opacity: 0.35;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }

    /* ── YouTube Key Input ── */
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

    .input-dark::placeholder { color: var(--text-muted); }

    .input-group-dark {
        display: flex;
        align-items: stretch;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .input-group-dark:focus-within {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow);
    }

    .input-group-dark .input-prefix {
        display: flex;
        align-items: center;
        padding: 0 12px;
        background: rgba(255,255,255,0.04);
        border-right: 1px solid var(--border-color);
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .input-group-dark input {
        flex: 1;
        background: var(--bg-secondary);
        border: none;
        color: var(--text-primary);
        padding: 10px 14px;
        font-size: 0.875rem;
        outline: none;
    }

    .input-group-dark .input-suffix {
        display: flex;
        align-items: center;
        padding: 0 12px;
        background: rgba(255,255,255,0.04);
        border-left: 1px solid var(--border-color);
        color: var(--text-muted);
        cursor: pointer;
        transition: color 0.2s;
        font-size: 0.85rem;
    }

    .input-group-dark .input-suffix:hover { color: var(--text-primary); }

    /* ── Info Rows (Now Playing, VPS, etc) ── */
    .info-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.85rem;
    }

    .info-row:last-child { border-bottom: none; padding-bottom: 0; }
    .info-row:first-child { padding-top: 0; }

    .info-row .info-label {
        color: var(--text-muted);
        min-width: 90px;
        font-size: 0.8rem;
        padding-top: 1px;
    }

    .info-row .info-value {
        color: var(--text-secondary);
        word-break: break-all;
        flex: 1;
    }

    /* ── Terminal ── */
    .terminal-box {
        background: #0d0f14;
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: 10px;
        overflow: hidden;
    }

    .terminal-box .terminal-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: rgba(255,255,255,0.03);
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .terminal-box .terminal-top .dots span {
        display: inline-block;
        width: 10px; height: 10px;
        border-radius: 50%;
        margin-right: 5px;
    }

    .terminal-box .terminal-top .dots .red   { background: #ff5f57; }
    .terminal-box .terminal-top .dots .amber { background: #febc2e; }
    .terminal-box .terminal-top .dots .green { background: #28c840; }

    .terminal-box .terminal-top .badge-live {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 999px;
        background: rgba(34,197,94,0.15);
        color: #4ade80;
        border: 1px solid rgba(34,197,94,0.25);
        letter-spacing: 0.5px;
    }

    .terminal-box pre {
        margin: 0;
        padding: 14px;
        font-family: 'Courier New', monospace;
        font-size: 0.78rem;
        color: #4ade80;
        white-space: pre-wrap;
        word-break: break-all;
        max-height: 280px;
        overflow-y: auto;
    }

    .pm2-table-wrap {
        overflow-x: auto;
    }

    .pm2-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }

    .pm2-table th,
    .pm2-table td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid rgba(255,255,255,0.06);
        white-space: nowrap;
    }

    .pm2-table th {
        color: var(--text-muted);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        font-weight: 600;
    }

    .pm2-table td {
        color: var(--text-secondary);
    }

    .pm2-table tr:last-child td {
        border-bottom: none;
    }

    .pm2-status-chip {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .pm2-status-chip.online {
        background: rgba(34,197,94,0.14);
        color: #4ade80;
        border: 1px solid rgba(34,197,94,0.28);
    }

    .pm2-status-chip.stopped,
    .pm2-status-chip.errored {
        background: rgba(239,68,68,0.12);
        color: #f87171;
        border: 1px solid rgba(239,68,68,0.24);
    }

    .pm2-status-chip.default {
        background: rgba(148,163,184,0.12);
        color: var(--text-secondary);
        border: 1px solid rgba(148,163,184,0.22);
    }

    .yt-status-inline {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }

    .yt-status-inline .badge-item {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 700;
        border: 1px solid transparent;
        letter-spacing: 0.3px;
    }

    .yt-status-inline .badge-item.success {
        background: rgba(34,197,94,0.14);
        border-color: rgba(34,197,94,0.28);
        color: #4ade80;
    }

    .yt-status-inline .badge-item.warning {
        background: rgba(245,158,11,0.14);
        border-color: rgba(245,158,11,0.28);
        color: #fbbf24;
    }

    .yt-status-inline .badge-item.danger {
        background: rgba(239,68,68,0.14);
        border-color: rgba(239,68,68,0.28);
        color: #f87171;
    }

    .yt-status-inline .badge-item.neutral {
        background: rgba(148,163,184,0.12);
        border-color: rgba(148,163,184,0.22);
        color: var(--text-secondary);
    }

    /* ── Now Playing ── */
    .now-playing-bar {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        background: rgba(99,102,241,0.08);
        border: 1px solid rgba(99,102,241,0.2);
        border-radius: 10px;
    }

    .now-playing-bar .play-icon {
        width: 38px; height: 38px;
        background: linear-gradient(135deg, var(--accent), #818cf8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        color: #fff;
        flex-shrink: 0;
    }

    .now-playing-bar .meta .label {
        font-size: 0.72rem;
        color: var(--accent);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .now-playing-bar .meta .title {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
        margin-top: 2px;
    }

    /* ── Video Grid ── */
    .video-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 16px;
    }

    .video-card-item {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        overflow: hidden;
        cursor: move;
        transition: all 0.2s;
        position: relative;
    }

    .video-card-item:hover {
        border-color: rgba(99,102,241,0.35);
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    }

    .video-card-item.selected {
        border-color: var(--accent);
        box-shadow: 0 0 0 2px var(--accent-glow);
    }

    .video-card-item .thumb {
        position: relative;
        height: 110px;
        background: #0d0f14;
        overflow: hidden;
    }

    .video-placeholder-stream {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background-size: cover;
        background-position: center;
        cursor: pointer;
    }

    .video-placeholder-stream .play-circle {
        width: 36px; height: 36px;
        background: rgba(0,0,0,0.6);
        border: 2px solid rgba(255,255,255,0.7);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 0.8rem;
        padding-left: 2px;
        backdrop-filter: blur(4px);
        transition: all 0.2s;
    }

    .video-placeholder-stream:hover .play-circle {
        background: rgba(99,102,241,0.75);
        border-color: #fff;
        transform: scale(1.1);
    }

    .video-card-item .check-overlay {
        position: absolute;
        top: 8px; right: 8px;
        z-index: 10;
    }

    .video-card-item .check-overlay input[type="checkbox"] {
        width: 18px; height: 18px;
        accent-color: var(--accent);
        cursor: pointer;
    }

    .video-card-item .video-info {
        padding: 10px 12px;
    }

    .video-card-item .video-info .vid-title {
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--text-secondary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .video-card-item video {
        width: 100%; height: 100%;
        object-fit: contain;
        position: absolute;
        top: 0; left: 0;
    }

    /* Sortable states */
    .video-card-item.sortable-chosen  { opacity: 0.7; transform: scale(1.03); }
    .video-card-item.sortable-ghost   { opacity: 0.3; border: 2px dashed var(--accent); }

    /* ── Select All switch ── */
    .toggle-switch {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.85rem;
        color: var(--text-secondary);
        cursor: pointer;
        margin-bottom: 16px;
        user-select: none;
    }

    .toggle-switch input { accent-color: var(--accent); width: 17px; height: 17px; cursor: pointer; }

    /* ── Error log ── */
    .error-log-pre {
        font-size: 0.75rem;
        color: #f87171;
        background: rgba(239,68,68,0.07);
        border: 1px solid rgba(239,68,68,0.15);
        border-radius: 8px;
        padding: 12px;
        max-height: 200px;
        overflow-y: auto;
        margin: 0;
        white-space: pre-wrap;
        word-break: break-all;
    }

    /* ── Empty State ── */
    .empty-state {
        text-align: center;
        padding: 36px 20px;
    }

    .empty-state .empty-icon {
        font-size: 2.5rem;
        color: var(--text-muted);
        margin-bottom: 12px;
    }

    .empty-state p {
        color: var(--text-muted);
        font-size: 0.875rem;
        margin-bottom: 16px;
    }

    /* ── Streaming video list ── */
    .playlist-list { list-style: none; padding: 0; margin: 0; }
    .playlist-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
        color: var(--text-secondary);
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border-color);
        margin-bottom: 6px;
    }

    .playlist-list li .num {
        font-size: 0.75rem;
        color: var(--text-muted);
        min-width: 20px;
        font-weight: 600;
    }

    .playlist-list li i { color: var(--accent); font-size: 0.8rem; }

    /* ── Section label ── */
    .section-label {
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--text-muted);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border-color);
    }

    /* ── Form label ── */
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

    /* ── Save button ── */
    .btn-save {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        background: var(--accent);
        color: #fff;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 14px;
    }

    .btn-save:hover { background: var(--accent-hover); transform: translateY(-1px); box-shadow: 0 4px 12px var(--accent-glow); }

    /* ── Trash button ── */
    .btn-icon-danger {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 7px;
        font-size: 0.78rem;
        font-weight: 500;
        background: rgba(239,68,68,0.1);
        color: #f87171;
        border: 1px solid rgba(239,68,68,0.2);
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-icon-danger:hover { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); }

    @media (max-width: 767px) {
        .video-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
    }
</style>
@endsection

@section('content')
@php
    $ytStatus = $setting->youtube_last_prepare_status ?? null;
    $ytStatusMap = [
        'ready' => ['label' => 'Cookie Valid', 'class' => 'success'],
        'already_live' => ['label' => 'Already Live', 'class' => 'success'],
        'not_live' => ['label' => 'Not Live', 'class' => 'warning'],
        'login_required' => ['label' => 'Login Required', 'class' => 'danger'],
        'missing_cookies' => ['label' => 'Cookie Missing', 'class' => 'danger'],
    ];
    $ytStatusUi = $ytStatusMap[$ytStatus] ?? ['label' => 'Belum Dicek', 'class' => 'neutral'];
@endphp

{{-- Page Header --}}
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-satellite-dish me-2" style="color:var(--accent)"></i>Manajemen Streaming</h1>
        <p>Kelola dan pantau siaran langsung YouTube Anda</p>
    </div>
    <div>
        @if ($isStreaming)
            <span class="status-badge live"><span class="dot"></span> LIVE Sekarang</span>
        @else
            <span class="status-badge offline"><span class="dot"></span> Tidak Aktif</span>
        @endif
    </div>
</div>

{{-- ===== ROW 1: Left (Status + Playlist) | Right (Controls + YT Key + Error Log) ===== --}}
<div class="row g-4">

    {{-- LEFT COLUMN --}}
    <div class="col-lg-6 dashboard-column">

        {{-- Now Playing --}}
        @if ($isStreaming && !empty($playingLine))
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-headphones"></i> Sedang Diputar</h3>
            </div>
            <div class="card-body-inner">
                <div class="now-playing-bar">
                    <div class="play-icon"><i class="fas fa-play"></i></div>
                    <div class="meta">
                        <div class="label">Now Playing</div>
                        <div class="title">{{ $playingLine }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Playlist aktif --}}
        @if ($isStreaming && !empty($streamingVideos))
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-list"></i> Playlist Aktif</h3>
                <span class="status-badge live" style="font-size:0.7rem;padding:4px 10px;">
                    {{ count($streamingVideos) }} video
                </span>
            </div>
            <div class="card-body-inner">
                <ul class="playlist-list">
                    @foreach ($streamingVideos as $i => $video)
                        <li>
                            <span class="num">{{ $i + 1 }}</span>
                            <i class="fas fa-play-circle"></i>
                            {{ $video['title'] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- Jika tidak ada info streaming --}}
        @if (!$isStreaming && empty($streamingVideos))
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-info-circle"></i> Status Siaran</h3>
            </div>
            <div class="card-body-inner">
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-broadcast-tower"></i></div>
                    <p>Belum ada streaming yang sedang berlangsung.<br>Pilih video dan mulai streaming dari panel kanan.</p>
                </div>
            </div>
        </div>
        @endif

        {{-- PM2 Status --}}
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-server"></i> Status PM2</h3>
                <button class="collapse-btn" id="pm2Btn" onclick="toggleCollapse('pm2Body', this)">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="pm2Body" style="display:none;">
                <div class="card-body-inner">
                    @if (!empty($pm2Processes))
                        <div class="terminal-box">
                            <div class="terminal-top">
                                <div class="dots">
                                    <span class="red"></span>
                                    <span class="amber"></span>
                                    <span class="green"></span>
                                </div>
                                <span class="badge-live">LIVE</span>
                            </div>
                            <div class="pm2-table-wrap">
                                <table class="pm2-table">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Status</th>
                                            <th>PID</th>
                                            <th>Uptime</th>
                                            <th>CPU</th>
                                            <th>Memory</th>
                                            <th>Restart</th>
                                            <th>Mode</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($pm2Processes as $process)
                                            @php
                                                $statusClass = in_array($process['status'], ['online'], true)
                                                    ? 'online'
                                                    : (in_array($process['status'], ['stopped', 'errored'], true) ? 'stopped' : 'default');
                                            @endphp
                                            <tr>
                                                <td>{{ $process['name'] }}</td>
                                                <td>
                                                    <span class="pm2-status-chip {{ $statusClass }}">
                                                        {{ $process['status'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $process['pid'] }}</td>
                                                <td>{{ $process['uptime'] }}</td>
                                                <td>{{ $process['cpu'] }}</td>
                                                <td>{{ $process['memory'] }}</td>
                                                <td>{{ $process['restarts'] }}</td>
                                                <td>{{ $process['mode'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="empty-state" style="padding:20px;">
                            <div class="empty-icon" style="font-size:1.8rem;"><i class="fas fa-server"></i></div>
                            <p style="margin:0;">Tidak ada proses PM2 berjalan</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- VPS Info --}}
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-microchip"></i> Info VPS</h3>
                <button class="collapse-btn" id="vpsBtn" onclick="toggleCollapse('vpsBody', this)">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="vpsBody" style="display:none;">
                <div class="card-body-inner">
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-tachometer-alt me-1"></i> Load</span>
                        <span class="info-value">{{ $loadavg }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-memory me-1"></i> Memory</span>
                        <span class="info-value">{{ $meminfo }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-hdd me-1"></i> Disk</span>
                        <span class="info-value">{{ $diskinfo }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- end left col --}}

    {{-- RIGHT COLUMN --}}
    <div class="col-lg-6 dashboard-column">

        {{-- Kontrol Streaming --}}
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-sliders-h"></i> Kontrol Streaming</h3>
            </div>
            <div class="card-body-inner">

                {{-- Stream via Playlist --}}
                @if ($playlists->isNotEmpty())
                <div style="margin-bottom:14px;">
                    <p style="font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.7px;margin-bottom:8px;">⚡ Stream dari Playlist</p>
                    <form action="{{ route('stream.startPlaylist') }}" method="POST">
                        @csrf
                        <div style="display:flex;gap:8px;">
                            <select name="playlist_id" id="playlist_id" style="flex:1;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:9px;color:var(--text-primary);padding:9px 12px;font-size:0.85rem;outline:none;font-family:inherit;cursor:pointer;" @if($isStreaming) disabled @endif>
                                <option value="">-- Pilih playlist --</option>
                                @foreach ($playlists as $pl)
                                    <option value="{{ $pl->id }}">{{ $pl->name }} ({{ $pl->videos_count }} video)</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-stream success" style="width:auto;padding:9px 16px;" @if($isStreaming) disabled @endif>
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                    </form>
                    <div style="height:1px;background:var(--border-color);margin:14px 0;"></div>
                    <p style="font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.7px;margin-bottom:8px;">🎬 Stream pilih video manual</p>
                </div>
                @endif

                <button class="btn-stream success" type="submit" form="streamForm"
                    @if ($isStreaming) disabled @endif>
                    <i class="fas fa-play-circle"></i> Mulai Streaming Manual
                </button>

                <button class="btn-stream outline" type="button"
                    onclick="toggleCollapse('startForm', document.getElementById('videoToggleBtn'))"
                    id="videoToggleBtn">
                    <i class="fas fa-film"></i>
                    {{ $isStreaming ? 'Lihat / Ganti Video' : 'Pilih Video' }}
                </button>

                <form action="{{ route('stream.stop') }}" method="POST" style="margin-top:10px;">
                    @csrf
                    <button type="submit" class="btn-stream danger"
                        @if (!$isStreaming) disabled @endif>
                        <i class="fas fa-stop-circle"></i> Hentikan Streaming
                    </button>
                </form>

                <p style="font-size:0.78rem;color:var(--text-muted);text-align:center;margin-top:12px;margin-bottom:0;">
                    {{ $isStreaming ? '⚡ Siaran sedang aktif' : '⏸ Streaming belum dimulai' }}
                </p>

                <a href="{{ route('playlists.index') }}" style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:10px;font-size:0.8rem;color:var(--accent);text-decoration:none;"><i class="fas fa-list-ul"></i> Kelola Playlist</a>
            </div>
        </div>

        {{-- YouTube Connection Status --}}
        <div class="stream-card" id="youtube-config">
            <div class="card-head">
                <h3><i class="fab fa-youtube" style="color:#ff4444;"></i> Status Koneksi YouTube</h3>
            </div>
            <div class="card-body-inner">
                <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:14px;">
                    Konfigurasi YouTube sekarang dipindahkan ke menu terpisah agar halaman streaming tetap fokus ke operasional siaran.
                </p>

                <div class="yt-status-inline">
                    <span class="badge-item {{ $ytStatusUi['class'] }}">
                        <i class="fas fa-circle"></i> {{ $ytStatusUi['label'] }}
                    </span>
                    @if (($setting->youtube_last_prepare_status ?? null) === 'already_live')
                        <span class="badge-item success">
                            <i class="fas fa-broadcast-tower"></i> YouTube Masih Live
                        </span>
                    @elseif (($setting->youtube_last_prepare_status ?? null) === 'not_live')
                        <span class="badge-item warning">
                            <i class="fas fa-circle-pause"></i> Aman Untuk Start
                        </span>
                    @endif
                </div>

                <div style="margin-top:14px;padding:12px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid var(--border-color);">
                    <div class="info-row" style="padding-top:0;">
                        <span class="info-label">Akun</span>
                        <span class="info-value">{{ auth()->user()->email }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Google</span>
                        <span class="info-value">{{ $setting->google_email ?? 'Belum diatur' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Channel</span>
                        <span class="info-value">{{ $setting->youtube_channel_id ?? 'Otomatis / belum diatur' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            {{ $setting->youtube_last_prepare_status ?? 'Belum diuji' }}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Cookie</span>
                        <span class="info-value">
                            {{ !empty($setting->youtube_cookie_path ?? null) ? 'Tersimpan' : 'Belum ada' }}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Terhubung</span>
                        <span class="info-value">
                            {{ optional($setting->youtube_connected_at ?? null)->format('d M Y H:i') ?? 'Belum ada data' }}
                        </span>
                    </div>
                    <div class="info-row" style="border-bottom:none;padding-bottom:0;">
                        <span class="info-label">Pesan</span>
                        <span class="info-value">
                            {{ $setting->youtube_last_prepare_message ?? 'Belum ada hasil automasi.' }}
                        </span>
                    </div>
                </div>

                <a href="{{ route('stream.youtube') }}" class="btn-stream outline" style="margin-top:14px;">
                    <i class="fas fa-cog"></i> Buka Pengaturan YouTube
                </a>
            </div>
        </div>

        {{-- YouTube Stream Key --}}
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fab fa-youtube" style="color:#ff4444;"></i> YouTube Stream Key</h3>
            </div>
            <div class="card-body-inner">
                <form action="{{ route('stream.storeKey') }}" method="POST">
                    @csrf
                    <label class="form-label-dark" for="youtube_key">Kunci Streaming YouTube</label>
                    <div class="input-group-dark">
                        <span class="input-prefix"><i class="fas fa-key"></i></span>
                        <input type="password"
                               name="youtube_key"
                               id="youtube_key"
                               value="{{ $setting->youtube_key ?? '' }}"
                               placeholder="Masukkan stream key..."
                               autocomplete="off">
                        <span class="input-suffix toggle-password-btn" onclick="toggleYTKey(this)">
                            <i class="fas fa-eye" id="ytKeyIcon"></i>
                        </span>
                    </div>
                    @error('youtube_key')
                        <p style="font-size:0.78rem;color:#f87171;margin:6px 0 0 0;">{{ $message }}</p>
                    @enderror
                    <p class="form-hint">Kunci ini digunakan untuk mengirim siaran ke YouTube Live.</p>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Simpan Kunci
                    </button>
                </form>
            </div>
        </div>

        {{-- Error Log --}}
        <div class="stream-card">
            <div class="card-head">
                <h3 style="color:#f87171;"><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i> Log Error</h3>
                <div class="d-flex align-items-center gap-2">
                    <form action="{{ route('stream.clearErrors') }}" method="POST" style="margin:0;">
                        @csrf
                        <button type="submit" class="btn-icon-danger">
                            <i class="fas fa-trash-alt"></i> Hapus
                        </button>
                    </form>
                    <button class="collapse-btn" id="errBtn" onclick="toggleCollapse('errBody', this)">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div id="errBody" style="display:none;">
                <div class="card-body-inner">
                    @if(!empty(trim($errorLog)))
                        <pre class="error-log-pre">{{ $errorLog }}</pre>
                    @else
                        <div class="empty-state" style="padding:16px;">
                            <div class="empty-icon" style="font-size:1.8rem;"><i class="fas fa-check-circle" style="color:var(--success)"></i></div>
                            <p style="margin:0;color:var(--success);">Tidak ada error tercatat.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>{{-- end right col --}}
</div>

{{-- ===== ROW 2: Streaming Log (Full Width) ===== --}}
<div class="stream-card" style="margin-top:4px;">
    <div class="card-head">
        <h3><i class="fas fa-terminal"></i> Log Streaming</h3>
        <button class="collapse-btn" id="logBtn" onclick="toggleCollapse('logBody', this)">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
    <div id="logBody" style="display:none;">
        <div class="card-body-inner">
            @if ($isStreaming && !empty($streamLog))
                <div class="terminal-box">
                    <div class="terminal-top">
                        <div class="dots">
                            <span class="red"></span>
                            <span class="amber"></span>
                            <span class="green"></span>
                        </div>
                        <span class="badge-live">ACTIVE</span>
                    </div>
                    <pre>{{ $streamLog }}</pre>
                </div>
            @else
                <div class="empty-state" style="padding:20px;">
                    <div class="empty-icon"><i class="fas fa-clipboard"></i></div>
                    <p>Tidak ada log streaming yang tersedia saat ini.</p>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ===== ROW 3: Pilih Video (Collapsible, Full Width) ===== --}}
<div id="startForm" style="{{ $isStreaming ? 'display:none;' : '' }} margin-top: 4px;">
    <div class="stream-card">
        <div class="card-head">
            <h3><i class="fas fa-video"></i> Pilih Video untuk Streaming</h3>
        </div>
        <div class="card-body-inner">
            <form action="{{ route('stream.start') }}" method="POST" id="streamForm">
                @csrf
                @if ($videos->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-video-slash"></i></div>
                        <p>Tidak ada video tersedia. Upload video terlebih dahulu.</p>
                        <a href="{{ route('videos.create') }}" class="btn-stream primary" style="width:auto;display:inline-flex;">
                            <i class="fas fa-upload"></i> Upload Video
                        </a>
                    </div>
                @else
                    <label class="toggle-switch">
                        <input type="checkbox" id="selectAllVideos">
                        Pilih Semua Video
                    </label>
                    <p class="section-label">Seret untuk mengubah urutan</p>
                    <div class="video-grid sortable" id="videoList">
                        @foreach ($videos as $video)
                            <div class="video-card-item" data-id="{{ $video->id }}">
                                <div class="thumb">
                                    <div class="video-placeholder-stream"
                                         style="background-image:url('{{ route('videos.thumbnail', $video) }}');"
                                         data-src="{{ route('videos.stream', $video) }}">
                                        <div class="play-circle"><i class="fas fa-play"></i></div>
                                    </div>
                                    <video class="d-none" controls preload="none">
                                        Browser tidak mendukung video.
                                    </video>
                                    <div class="check-overlay">
                                        <input type="checkbox"
                                               name="videos[]"
                                               id="video_{{ $video->id }}"
                                               value="{{ $video->id }}">
                                    </div>
                                </div>
                                <div class="video-info">
                                    <div class="vid-title">{{ $video->title }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('videos')
                        <p style="color:#f87171;font-size:0.8rem;margin-top:10px;">{{ $message }}</p>
                    @enderror
                @endif
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    /* ── Collapse helper ── */
    function toggleCollapse(bodyId, btn) {
        const el = document.getElementById(bodyId);
        if (!el) return;
        const isOpen = el.style.display !== 'none';
        el.style.display = isOpen ? 'none' : 'block';
        if (btn) btn.classList.toggle('open', !isOpen);
    }

    /* ── YouTube key toggle ── */
    function toggleYTKey(btn) {
        const input = document.getElementById('youtube_key');
        const icon  = document.getElementById('ytKeyIcon');
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
        const videoList  = document.getElementById('videoList');
        const selectAll  = document.getElementById('selectAllVideos');
        const form       = document.getElementById('streamForm');

        if (!videoList) return;

        /* ── Track checked states ── */
        let checkedVideos = new Map();
        document.querySelectorAll('input[name="videos[]"]').forEach(cb => {
            checkedVideos.set(cb.value, cb.checked);
        });

        /* ── SortableJS ── */
        new Sortable(videoList, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            handle: '.video-card-item',
            onEnd: function () {
                const items = videoList.querySelectorAll('.video-card-item');
                const order = Array.from(items).map(i => i.dataset.id).filter(Boolean);

                fetch('{{ route('stream.updateOrder') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ order })
                })
                .then(r => r.json())
                .then(d => { if (!d.success) alert('Gagal menyimpan urutan: ' + d.message); })
                .catch(e => alert('Gagal: ' + e.message));

                /* rebuild checkboxes */
                document.querySelectorAll('input[name="videos[]"]').forEach(c => c.remove());
                items.forEach(item => {
                    const id  = item.dataset.id;
                    const inp = document.createElement('input');
                    inp.type  = 'checkbox';
                    inp.name  = 'videos[]';
                    inp.value = id;
                    inp.id    = 'video_' + id;
                    inp.className = 'form-check-input';
                    inp.checked   = checkedVideos.get(id) || false;
                    item.querySelector('.check-overlay').appendChild(inp);
                });
            }
        });

        /* ── Select All ── */
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('input[name="videos[]"]').forEach(cb => {
                    cb.checked = selectAll.checked;
                    checkedVideos.set(cb.value, cb.checked);
                });
                videoList.querySelectorAll('.video-card-item').forEach(c => {
                    c.classList.toggle('selected', selectAll.checked);
                });
            });
        }

        /* ── Track per-checkbox change ── */
        videoList.addEventListener('change', function (e) {
            if (e.target.matches('input[name="videos[]"]')) {
                checkedVideos.set(e.target.value, e.target.checked);
                e.target.closest('.video-card-item').classList.toggle('selected', e.target.checked);
            }
        });

        /* ── Click card → toggle checkbox ── */
        document.querySelectorAll('.video-card-item').forEach(card => {
            card.addEventListener('click', function (e) {
                if (e.target.tagName === 'INPUT') return;
                if (e.target.closest('.video-placeholder-stream')) return;
                const cb = this.querySelector('input[type="checkbox"]');
                if (!cb) return;
                cb.checked = !cb.checked;
                checkedVideos.set(cb.value, cb.checked);
                this.classList.toggle('selected', cb.checked);
            });
        });

        /* ── Lazy-load video on placeholder click ── */
        document.querySelectorAll('.video-placeholder-stream').forEach(function (ph) {
            ph.addEventListener('click', function (e) {
                e.stopPropagation();
                const src     = this.dataset.src;
                const wrapper = this.parentElement;
                const vid     = wrapper.querySelector('video');
                if (src && !vid.querySelector('source')) {
                    const s  = document.createElement('source');
                    s.src    = src;
                    s.type   = 'video/mp4';
                    vid.appendChild(s);
                    vid.load();
                }
                this.classList.add('d-none');
                vid.classList.remove('d-none');
                vid.play();
            });
        });

        /* ── IntersectionObserver: preload on scroll ── */
        const obs = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const ph  = entry.target;
                    const src = ph.dataset.src;
                    const vid = ph.parentElement.querySelector('video');
                    if (src && vid && !vid.querySelector('source')) {
                        const s = document.createElement('source');
                        s.src   = src; s.type = 'video/mp4';
                        vid.appendChild(s);
                    }
                    obs.unobserve(ph);
                }
            });
        }, { rootMargin: '150px' });

        document.querySelectorAll('.video-placeholder-stream').forEach(p => obs.observe(p));

        /* ── Form submit log ── */
        if (form) {
            form.addEventListener('submit', function () {
                const selected = new FormData(form).getAll('videos[]');
                console.log('Streaming videos:', selected);
            });
        }
    });
</script>
@endsection
