@extends('layouts.app')

@section('content')
    {{-- ─── HEADER BANNER ─────────────────────────────────────────── --}}
    <div class="stream-hero mb-4 rounded-3 shadow">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h1 class="hero-title mb-1"><i class="fas fa-broadcast-tower me-2"></i>Live Stream Dashboard</h1>
                <span class="hero-sub">Kelola streaming YouTube 24 jam nonstop</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                @if ($isStreaming)
                    <span class="live-badge"><span class="live-dot"></span> LIVE</span>
                @else
                    <span class="offline-badge"><i class="fas fa-circle me-1"></i> OFFLINE</span>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── FLASH MESSAGES ────────────────────────────────────────── --}}
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><strong>Error:</strong> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i><strong>Berhasil:</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body p-0 p-md-3">
            {{-- ─── ROW 1: NOW PLAYING + CONTROLS ─────────────────── --}}
            <div class="row g-4 mb-4">
                {{-- LEFT: NOW PLAYING ─────────────────────────────── --}}
                <div class="col-lg-7 col-md-12">
                    <div class="card np-card shadow border-0 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span id="npLiveDot" class="np-live-dot {{ $isStreaming ? '' : 'np-idle' }}"></span>
                                <span class="text-uppercase fw-semibold small text-secondary tracking-wide">Now Playing</span>
                            </div>

                            <h4 id="nowPlayingTitle" class="np-title mb-3">
                                {{ $isStreaming ? 'Memuat data...' : 'Streaming tidak aktif' }}
                            </h4>

                            <div class="np-progress-wrap mb-2">
                                <div class="progress np-progress">
                                    <div id="progressBar"
                                        class="progress-bar progress-bar-striped {{ $isStreaming ? 'progress-bar-animated bg-success' : 'bg-secondary' }}"
                                        role="progressbar" style="width:0%">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <small id="timeInfo" class="text-muted">Durasi: - / -</small>
                                <small id="npPercent" class="fw-semibold text-success">0%</small>
                            </div>

                            {{-- Playlist sedang berjalan --}}
                            @if ($isStreaming && !empty($streamingVideos))
                                <hr class="my-3 opacity-25">
                                <p class="small text-muted mb-2 fw-semibold"><i class="fas fa-list me-1"></i>Antrian Playlist</p>
                                <div class="playlist-scroll">
                                    @foreach ($streamingVideos as $idx => $video)
                                        <div class="playlist-item" id="pli-{{ $idx }}">
                                            <span class="playlist-num">{{ $idx + 1 }}</span>
                                            <span class="playlist-name text-truncate">{{ $video['title'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- RIGHT: KEY + STOP ─────────────────────────────── --}}
                <div class="col-lg-5 col-md-12 d-flex flex-column gap-3">
                    {{-- Status Chip --}}
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-3 d-flex align-items-center gap-3">
                            @if ($isStreaming)
                                <div class="status-icon-wrap bg-success-subtle">
                                    <i class="fas fa-circle-play text-success fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Streaming Aktif</div>
                                    <small class="text-muted">Proses berjalan via PM2</small>
                                </div>
                            @else
                                <div class="status-icon-wrap bg-secondary-subtle">
                                    <i class="fas fa-circle-stop text-secondary fs-4"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-secondary">Streaming Offline</div>
                                    <small class="text-muted">Pilih video lalu mulai</small>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- YouTube Key --}}
                    <div class="card border-0 shadow-sm flex-grow-1">
                        <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0">
                            <span class="fw-semibold small text-uppercase text-muted tracking-wide">
                                <i class="fab fa-youtube text-danger me-1"></i>YouTube Stream Key
                            </span>
                        </div>
                        <div class="card-body pt-2">
                            <form action="{{ route('stream.storeKey') }}" method="POST">
                                @csrf
                                <div class="input-group mb-2">
                                    <input type="password" name="youtube_key" id="youtube_key"
                                        class="form-control form-control-sm @error('youtube_key') is-invalid @enderror"
                                        value="{{ $setting->youtube_key ?? '' }}" required
                                        placeholder="xxxx-xxxx-xxxx-xxxx">
                                    <button class="btn btn-outline-secondary btn-sm toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                                @error('youtube_key')
                                    <div class="invalid-feedback d-block small">{{ $message }}</div>
                                @enderror
                                @if ($setting && $setting->youtube_key)
                                    <small class="text-success"><i class="fas fa-check-circle me-1"></i>Key tersimpan</small>
                                @else
                                    <small class="text-warning"><i class="fas fa-exclamation-circle me-1"></i>Belum diatur</small>
                                @endif
                            </form>
                        </div>
                    </div>

                    {{-- Stop Button --}}
                    <form action="{{ route('stream.stop') }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="btn btn-danger w-100 {{ !$isStreaming ? 'disabled' : '' }}"
                            @if (!$isStreaming) disabled @endif>
                            <i class="fas fa-stop-circle me-2"></i>Hentikan Streaming
                        </button>
                    </form>
                </div>
            </div>

            {{-- ─── ROW 2 merged into ROW 3 header ──────────────────── --}}

            {{-- ─── ROW 3: ERROR NOTIF + LOG BUTTON ─────────────────── --}}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-1">
                        {{-- Error summary chip (ringan, tidak polling) --}}
                        <div id="errorChip" class="d-flex align-items-center gap-2 {{ empty($invalidVideos) ? 'd-none' : '' }}">
                            <span class="badge bg-danger rounded-pill px-3 py-2">
                                <i class="fas fa-circle-exclamation me-1"></i>
                                <span id="errorChipCount">{{ count($invalidVideos) }}</span> video bermasalah
                            </span>
                        </div>
                        <div class="ms-auto">
                            <button class="btn btn-sm btn-outline-secondary" id="btnOpenLog">
                                <i class="fas fa-terminal me-1"></i>Lihat Log Streaming
                                <span id="logLiveDot" class="ms-1 live-dot-sm {{ $isStreaming ? '' : 'd-none' }}"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ─── LOG MODAL ───────────────────────────────────────────── --}}
            <div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content bg-dark text-light border-0">
                        <div class="modal-header border-secondary py-2">
                            <div class="d-flex align-items-center gap-3 flex-wrap flex-grow-1">
                                <span class="fw-semibold small text-uppercase tracking-wide" id="logModalLabel">
                                    <i class="fas fa-terminal me-1 text-success"></i>Log Streaming
                                </span>
                                <div class="log-tab-bar d-flex gap-1">
                                    <button class="log-tab-btn active" data-filter="all">Semua</button>
                                    <button class="log-tab-btn" data-filter="error">
                                        <i class="fas fa-circle-xmark text-danger me-1"></i>Error
                                        <span id="tabErrorCount" class="badge bg-danger ms-1 rounded-pill">0</span>
                                    </button>
                                    <button class="log-tab-btn" data-filter="play">
                                        <i class="fas fa-play text-success me-1"></i>Playing
                                    </button>
                                    <button class="log-tab-btn" data-filter="warn">
                                        <i class="fas fa-triangle-exclamation text-warning me-1"></i>Warn
                                    </button>
                                </div>
                                <span id="logBadge" class="badge bg-secondary ms-auto">idle</span>
                            </div>
                            <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div id="smartLog" class="smart-log-container" style="height:65vh;">
                                <div class="text-center py-5 text-muted" id="logEmpty">
                                    <i class="fas fa-circle-notch fa-spin fa-2x mb-2 d-block opacity-50"></i>
                                    Memuat log...
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-secondary py-2 d-flex justify-content-between">
                            <small id="logMeta" class="text-muted">-</small>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary" id="btnScrollTop">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-light" id="btnScrollBottom">
                                    <i class="fas fa-arrow-down"></i> Terbaru
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ─── ROW 4: VIDEO SELECTION ──────────────────────────── --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4 shadow-sm border-0">
                        <div class="card-header bg-transparent border-bottom">
                            <span class="fw-semibold small text-uppercase text-muted tracking-wide">
                                <i class="fas fa-video me-2"></i>Pilih Video untuk Streaming
                            </span>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('stream.start') }}" method="POST" id="streamForm">
                                @csrf
                                <div class="mb-3">
                                    @if ($videos->isEmpty())
                                        <div class="text-center py-5">
                                            <i class="fas fa-video-slash fa-3x text-muted mb-3 d-block opacity-50"></i>
                                            <p class="text-muted">Tidak ada video tersedia</p>
                                            <a href="{{ route('videos.create') }}" class="btn btn-primary btn-sm">
                                                <i class="fas fa-upload me-1"></i> Upload Video
                                            </a>
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <small class="text-muted">Seret kartu untuk mengubah urutan putar</small>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="selectAllVideos">
                                                <label class="form-check-label small" for="selectAllVideos">Pilih Semua</label>
                                            </div>
                                        </div>
                                        <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3 sortable" id="videoList">
                                            @foreach ($videos as $video)
                                                <div class="col" data-id="{{ $video->id }}">
                                                    <div class="card h-100 shadow-sm video-card border-0">
                                                        <div class="card-img-top video-thumbnail position-relative bg-dark" style="height:120px;">
                                                            <div class="video-placeholder-stream d-flex flex-column align-items-center justify-content-center h-100 text-white"
                                                                style="cursor:pointer;background-image:url('{{ route('videos.thumbnail', $video) }}');background-size:cover;background-position:center;"
                                                                data-src="{{ route('videos.stream', $video) }}">
                                                                <div class="play-overlay-btn">
                                                                    <i class="fas fa-play text-white small"></i>
                                                                </div>
                                                            </div>
                                                            <video class="w-100 h-100 d-none" controls preload="none"
                                                                style="object-fit:contain;position:absolute;top:0;left:0;">
                                                                Browser Anda tidak mendukung video.
                                                            </video>
                                                            <div class="video-check-overlay">
                                                                <div class="form-check form-switch mb-0">
                                                                    <input type="checkbox" name="videos[]"
                                                                        id="video_{{ $video->id }}"
                                                                        value="{{ $video->id }}"
                                                                        class="form-check-input">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-body p-2">
                                                            <p class="card-title small text-truncate mb-0 fw-semibold" title="{{ $video->title }}">{{ $video->title }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        @error('videos')
                                            <div class="text-danger mt-2 small"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>
                                @if (!$videos->isEmpty())
                                    <div class="d-flex justify-content-end mt-3">
                                        <button type="submit" class="btn btn-success px-4">
                                            <i class="fas fa-play-circle me-2"></i>Mulai Streaming
                                        </button>
                                    </div>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


@section('styles')
    <style>
        /* ── HERO ─────────────────────────────────── */
        .stream-hero {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #fff;
            padding: 1.5rem 2rem;
        }
        .hero-title  { font-size: 1.6rem; font-weight: 700; letter-spacing: -.5px; }
        .hero-sub    { font-size: .85rem; opacity: .7; }

        /* ── LIVE / OFFLINE BADGE ─────────────────── */
        .live-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,0,0,.15); border: 1px solid rgba(255,60,60,.5);
            color: #ff6b6b; padding: 4px 12px; border-radius: 20px;
            font-size: .78rem; font-weight: 700; letter-spacing: .08em;
        }
        .live-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #ff3c3c;
            animation: livePulse 1.2s ease-in-out infinite;
        }
        @keyframes livePulse {
            0%,100% { opacity:1; transform:scale(1); box-shadow:0 0 0 0 rgba(255,60,60,.6); }
            50%      { opacity:.8; transform:scale(1.2); box-shadow:0 0 0 5px rgba(255,60,60,0); }
        }
        .offline-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(150,150,150,.1); border: 1px solid rgba(150,150,150,.3);
            color: #aaa; padding: 4px 12px; border-radius: 20px;
            font-size: .78rem; font-weight: 600;
        }

        /* ── NOW PLAYING CARD ─────────────────────── */
        .np-card { background: linear-gradient(135deg, #0d1117 0%, #161b22 100%); color: #e6edf3; border: 1px solid #30363d; }
        .np-title { font-size: 1.15rem; font-weight: 700; color: #58a6ff; min-height: 2rem; }
        .np-live-dot {
            width: 10px; height: 10px; border-radius: 50%; background: #3fb950;
            animation: livePulse 1.2s ease-in-out infinite;
        }
        .np-live-dot.np-idle { background: #6e7681; animation: none; }
        .np-progress-wrap .np-progress { height: 8px; border-radius: 4px; background: #21262d; }
        .np-progress .progress-bar { border-radius: 4px; }
        .playlist-scroll { max-height: 140px; overflow-y: auto; }
        .playlist-item {
            display: flex; align-items: center; gap: 10px;
            padding: 5px 0; border-bottom: 1px solid #21262d;
            font-size: .82rem; color: #8b949e;
        }
        .playlist-item:last-child { border-bottom: none; }
        .playlist-num {
            width: 20px; height: 20px; border-radius: 50%;
            background: #21262d; color: #58a6ff;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; flex-shrink: 0;
        }
        .playlist-name { color: #c9d1d9; }

        /* ── STATUS ICON ──────────────────────────── */
        .status-icon-wrap {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }

        /* ── SMART LOG MODAL ──────────────────────── */
        .smart-log-container {
            overflow-y: auto; background: #0d1117;
            contain: strict;
        }
        .log-entry {
            display: flex; align-items: flex-start; gap: 8px;
            padding: 4px 14px; border-bottom: 1px solid #161b22;
            font-family: 'Consolas','Courier New',monospace; font-size: .76rem;
            line-height: 1.45;
        }
        .log-entry.log-error { color: #ff7b72; background: rgba(248,81,73,.07); }
        .log-entry.log-warn  { color: #d29922; }
        .log-entry.log-play  { color: #3fb950; }
        .log-entry.log-loop  { color: #58a6ff; }
        .log-entry.log-info  { color: #6e7681; }
        .log-entry .log-icon { flex-shrink:0; width:14px; text-align:center; }
        .log-entry .log-text { flex:1; white-space:pre-wrap; word-break:break-all; }
        .log-tab-bar         { gap:0; }
        .log-tab-btn {
            background:none; border:none; padding:4px 10px;
            font-size:.75rem; color:#6e7681;
            border-bottom: 2px solid transparent; cursor:pointer;
        }
        .log-tab-btn.active  { color:#58a6ff; border-bottom-color:#58a6ff; }
        .log-tab-btn:hover:not(.active) { color:#c9d1d9; }
        /* Live dot kecil */
        .live-dot-sm {
            display:inline-block; width:6px; height:6px; border-radius:50%;
            background:#3fb950; animation:livePulse 1.2s ease-in-out infinite;
            vertical-align:middle;
        }

        /* ── TRACKING WIDE ────────────────────────── */
        .tracking-wide { letter-spacing: .06em; }

        /* ── BTN XS ───────────────────────────────── */
        .btn-xs { font-size: .7rem; padding: 2px 8px; }

        /* ── VIDEO CARDS ──────────────────────────── */
        .video-card {
            transition: transform .18s, box-shadow .18s; cursor: move;
            border-radius: 10px !important;
        }
        .video-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.18) !important; }
        .video-thumbnail  { border-radius: 10px 10px 0 0; overflow: hidden; }
        .video-check-overlay {
            position: absolute; top: 6px; right: 6px; z-index: 10;
            background: rgba(0,0,0,.45); border-radius: 6px; padding: 2px 6px;
        }
        .play-overlay-btn {
            background: rgba(0,0,0,.5); border-radius: 50%;
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
        }

        /* ── SORTABLE ─────────────────────────────── */
        .sortable .col.sortable-chosen { opacity: .7; transform: scale(1.04); }
        .sortable .col.sortable-ghost  { opacity: .3; outline: 2px dashed #0d6efd; }

        /* ── SCROLLBAR DARK ───────────────────────── */
        .smart-log-container::-webkit-scrollbar { width: 4px; }
        .smart-log-container::-webkit-scrollbar-track { background: #0d1117; }
        .smart-log-container::-webkit-scrollbar-thumb { background: #30363d; border-radius: 3px; }
        .playlist-scroll::-webkit-scrollbar { width: 4px; }
        .playlist-scroll::-webkit-scrollbar-track { background: #0d1117; }
        .playlist-scroll::-webkit-scrollbar-thumb { background: #30363d; border-radius: 3px; }

        @media (max-width:767px) {
            .stream-hero { padding: 1rem; }
            .hero-title  { font-size: 1.2rem; }
        }
    </style>
@endsection

@section('scripts')
    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
    // ─── NOW PLAYING (2s interval) ───────────────────────────────────────────
    const NP_URL  = "{{ route('stream.nowPlaying') }}";
    const LOG_URL = "{{ route('stream.log') }}";

    function fmtTime(s) {
        s = Math.floor(s);
        const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), ss = s%60;
        return h ? `${h}j ${m}m ${ss}s` : `${m}m ${ss}s`;
    }

    setInterval(async () => {
        try {
            const data = await fetch(NP_URL).then(r => r.json());
            const titleEl   = document.getElementById('nowPlayingTitle');
            const bar        = document.getElementById('progressBar');
            const timeInfo   = document.getElementById('timeInfo');
            const npPercent  = document.getElementById('npPercent');
            const npLiveDot  = document.getElementById('npLiveDot');
            if (!titleEl) return;

            if (!data || data.status === 'idle') {
                titleEl.innerText   = 'Tidak ada video diputar';
                bar.style.width     = '0%';
                timeInfo.innerText  = 'Durasi: - / -';
                if (npPercent) npPercent.innerText = '0%';
                if (npLiveDot) { npLiveDot.classList.add('np-idle'); }
                return;
            }

            if (npLiveDot) npLiveDot.classList.remove('np-idle');
            titleEl.innerText = data.title ?? 'Unknown';

            const elapsed  = Math.floor(Date.now()/1000) - data.start;
            const duration = parseFloat(data.duration || 0);
            if (duration > 0) {
                const pct = Math.min(100, (elapsed / duration) * 100).toFixed(1);
                bar.style.width   = pct + '%';
                if (npPercent) npPercent.innerText = pct + '%';
                timeInfo.innerText = `Sudah diputar: ${fmtTime(elapsed)}  /  Total: ${fmtTime(duration)}`;
            } else {
                bar.style.width = '100%';
                if (npPercent) npPercent.innerText = '-';
                timeInfo.innerText = 'Durasi tidak diketahui';
            }
        } catch(e) {}
    }, 2000);

    // ─── LOG ON-DEMAND (hanya polling saat modal terbuka) ───────────────────
    let logFilter  = 'all';
    let allLogLines = [];
    let logPollTimer = null;
    let autoScroll = true;

    function classifyLine(line) {
        if (/❌|FILE HILANG|No such file|\bError\b|\berror\b|FATAL|mux_open|rtmp.*fail|Connection refused/i.test(line)) return 'error';
        if (/⚠|keluar|Restart|\bexit\b|\bwarn\b|buffering|bitrate drop/i.test(line)) return 'warn';
        if (/▶|NOW PLAYING/i.test(line)) return 'play';
        if (/🔁|LOOP|FFMPEG START/i.test(line)) return 'loop';
        return 'info';
    }

    function renderLog() {
        const container = document.getElementById('smartLog');
        if (!container) return;
        const filtered = logFilter === 'all'
            ? allLogLines
            : allLogLines.filter(l => classifyLine(l) === logFilter);

        if (!filtered.length) {
            container.innerHTML = `<div class="text-center py-5 text-muted" id="logEmpty">
                <i class="fas fa-clipboard fa-2x mb-2 d-block opacity-50"></i>Log belum tersedia</div>`;
            return;
        }

        // Render hanya 300 baris terakhir agar DOM ringan
        const visible = filtered.slice(-300);
        const icons   = {error:'⊗',warn:'⚠',play:'▶',loop:'↺',info:'·'};
        const html    = visible.map(line => {
            const cls  = classifyLine(line);
            const safe = line.replace(/</g,'&lt;').replace(/>/g,'&gt;');
            return `<div class="log-entry log-${cls}"><span class="log-icon">${icons[cls]}</span><span class="log-text">${safe}</span></div>`;
        }).join('');
        container.innerHTML = html;

        // Meta info
        const meta = document.getElementById('logMeta');
        if (meta) meta.innerText = `${filtered.length} baris (${visible.length} ditampilkan)`;

        if (autoScroll) container.scrollTop = container.scrollHeight;
    }

    async function fetchLog() {
        try {
            const data = await fetch(LOG_URL).then(r => r.json());
            allLogLines = data.lines || [];

            const badge   = document.getElementById('logBadge');
            const errCount = document.getElementById('tabErrorCount');
            const errors  = allLogLines.filter(l => classifyLine(l) === 'error');

            if (badge) {
                badge.className = allLogLines.length ? 'badge bg-success' : 'badge bg-secondary';
                badge.innerText = allLogLines.length ? 'live' : 'idle';
            }
            if (errCount) errCount.innerText = errors.length || '0';

            // Update error chip di dashboard (ringan)
            const chip  = document.getElementById('errorChip');
            const count = document.getElementById('errorChipCount');
            if (errors.length && chip) {
                if (count) count.innerText = errors.length;
                chip.classList.remove('d-none');
            }

            renderLog();
        } catch(e) {}
    }

    // ─── LOG TABS ────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const logModal   = document.getElementById('logModal');
        const btnOpenLog = document.getElementById('btnOpenLog');
        const bsModal    = logModal ? new bootstrap.Modal(logModal) : null;

        // Buka log modal
        if (btnOpenLog && bsModal) {
            btnOpenLog.addEventListener('click', () => bsModal.show());
        }

        // Mulai polling saat modal dibuka, hentikan saat ditutup
        if (logModal) {
            logModal.addEventListener('shown.bs.modal', () => {
                fetchLog();
                logPollTimer = setInterval(fetchLog, 4000);
            });
            logModal.addEventListener('hidden.bs.modal', () => {
                clearInterval(logPollTimer);
                logPollTimer = null;
            });
        }

        // Scroll kontrol
        const logContainer = document.getElementById('smartLog');
        if (logContainer) {
            logContainer.addEventListener('scroll', () => {
                const nearBottom = logContainer.scrollHeight - logContainer.scrollTop - logContainer.clientHeight < 60;
                autoScroll = nearBottom;
            });
        }
        document.getElementById('btnScrollBottom')?.addEventListener('click', () => {
            autoScroll = true;
            if (logContainer) logContainer.scrollTop = logContainer.scrollHeight;
        });
        document.getElementById('btnScrollTop')?.addEventListener('click', () => {
            autoScroll = false;
            if (logContainer) logContainer.scrollTop = 0;
        });

        document.querySelectorAll('.log-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.log-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                logFilter = btn.dataset.filter;
                renderLog();
            });
        });

        // ─── SORTABLE ────────────────────────────────────────────────────────
        const videoList   = document.getElementById('videoList');
        const form        = document.getElementById('streamForm');
        const selectAll   = document.getElementById('selectAllVideos');
        const csrfToken   = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                            || '{{ csrf_token() }}';
        let checkedVideos = new Map();

        if (!videoList) return;

        document.querySelectorAll('input[name="videos[]"]').forEach(cb => {
            checkedVideos.set(cb.value, cb.checked);
        });

        new Sortable(videoList, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            handle: '.video-card',
            onEnd() {
                const items = videoList.querySelectorAll('.col');
                const order = Array.from(items).map(i => i.getAttribute('data-id')).filter(Boolean);
                if (!order.length) return;
                fetch('{{ route('stream.updateOrder') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ order })
                }).then(r => r.json()).then(d => {
                    if (!d.success) alert('Gagal menyimpan urutan: ' + d.message);
                }).catch(() => alert('Gagal menyimpan urutan.'));

                const existingCbs = document.querySelectorAll('input[name="videos[]"]');
                existingCbs.forEach(cb => cb.remove());
                items.forEach((item, idx) => {
                    const vid = order[idx];
                    if (!vid) return;
                    const cb = Object.assign(document.createElement('input'), {
                        type:'checkbox', name:'videos[]', value:vid,
                        id:'video_'+vid, className:'form-check-input'
                    });
                    cb.checked = checkedVideos.get(vid) || false;
                    item.querySelector('.video-check-overlay .form-check')?.appendChild(cb);
                });
            }
        });

        // Select all
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                document.querySelectorAll('input[name="videos[]"]').forEach(cb => {
                    cb.checked = selectAll.checked;
                    checkedVideos.set(cb.value, cb.checked);
                });
            });
        }

        videoList.addEventListener('change', e => {
            if (e.target.matches('input[name="videos[]"]'))
                checkedVideos.set(e.target.value, e.target.checked);
        });

        // Card click toggle
        document.querySelectorAll('.video-card').forEach(card => {
            card.addEventListener('click', e => {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') return;
                const cb = card.querySelector('input[type="checkbox"]');
                if (cb) { cb.checked = !cb.checked; checkedVideos.set(cb.value, cb.checked); }
            });
        });

        // Toggle password
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const inp  = document.getElementById('youtube_key');
                const icon = btn.querySelector('i');
                if (inp.type === 'password') {
                    inp.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash');
                } else {
                    inp.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye');
                }
            });
        });

        // Lazy load video on placeholder click
        document.querySelectorAll('.video-placeholder-stream').forEach(ph => {
            ph.addEventListener('click', e => {
                e.stopPropagation();
                const src   = ph.dataset.src;
                const video = ph.parentElement.querySelector('video');
                if (src && video && !video.querySelector('source')) {
                    const s = document.createElement('source');
                    s.src = src; s.type = 'video/mp4';
                    video.appendChild(s); video.load();
                }
                ph.classList.add('d-none');
                if (video) { video.classList.remove('d-none'); video.play(); }
            });
        });

        // Intersection Observer preload
        const obs = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const ph    = entry.target;
                const src   = ph.dataset.src;
                const video = ph.parentElement?.querySelector('video');
                if (src && video && !video.querySelector('source')) {
                    const s = document.createElement('source');
                    s.src = src; s.type = 'video/mp4'; video.appendChild(s);
                }
                obs.unobserve(ph);
            });
        }, { rootMargin: '150px' });
        document.querySelectorAll('.video-placeholder-stream').forEach(p => obs.observe(p));
    });
    </script>
@endsection
