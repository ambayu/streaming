@extends('layouts.app')

@section('page-styles')
<style>
    .page-header { margin-bottom: 28px; }
    .page-header h1 {
        font-size: 1.5rem; font-weight: 700;
        color: var(--text-primary); letter-spacing: -0.5px; margin: 0;
    }
    .page-header p { font-size: 0.875rem; color: var(--text-secondary); margin: 4px 0 0; }

    .btn-upload {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 20px; border-radius: 10px;
        font-size: 0.875rem; font-weight: 600;
        background: linear-gradient(135deg, var(--accent), #818cf8);
        color: #fff; border: none; cursor: pointer;
        text-decoration: none;
        box-shadow: 0 4px 14px var(--accent-glow);
        transition: all 0.2s;
    }
    .btn-upload:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); color:#fff; }

    /* Grid */
    .video-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 20px;
    }

    .video-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        overflow: hidden;
        transition: all 0.2s;
    }
    .video-card:hover {
        border-color: rgba(99,102,241,0.3);
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.35);
    }

    /* Thumbnail */
    .video-thumb {
        position: relative;
        height: 140px;
        background: #0d0f14;
        overflow: hidden;
        cursor: pointer;
    }

    .video-placeholder {
        position: absolute; inset: 0;
        display: flex; align-items: center; justify-content: center;
        background-size: cover; background-position: center;
        transition: all 0.2s;
    }

    .play-circle {
        width: 46px; height: 46px;
        background: rgba(0,0,0,0.55);
        border: 2px solid rgba(255,255,255,0.75);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 0.9rem;
        padding-left: 3px;
        backdrop-filter: blur(4px);
        transition: all 0.2s;
    }

    .video-thumb:hover .play-circle {
        background: rgba(99,102,241,0.75);
        border-color: #fff;
        transform: scale(1.1);
    }

    .video-thumb video {
        width: 100%; height: 100%;
        object-fit: contain;
        position: absolute; top: 0; left: 0;
    }

    /* Card body */
    .video-meta {
        padding: 14px 16px;
    }

    .video-meta .vid-title {
        font-size: 0.9rem; font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .video-meta .vid-file {
        font-size: 0.75rem; color: var(--text-muted);
        margin: 0 0 14px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .video-actions {
        display: flex; gap: 8px;
    }

    .btn-act {
        flex: 1; display: flex; align-items: center; justify-content: center;
        gap: 6px; padding: 7px 10px; border-radius: 8px;
        font-size: 0.78rem; font-weight: 600;
        cursor: pointer; border: none; text-decoration: none;
        transition: all 0.2s;
    }

    .btn-act.edit {
        background: rgba(245,158,11,0.12);
        color: #fbbf24;
        border: 1px solid rgba(245,158,11,0.25);
    }
    .btn-act.edit:hover { background: rgba(245,158,11,0.22); color: #fbbf24; }

    .btn-act.del {
        background: rgba(239,68,68,0.1);
        color: #f87171;
        border: 1px solid rgba(239,68,68,0.2);
    }
    .btn-act.del:hover { background: rgba(239,68,68,0.2); color: #f87171; }

    /* Empty */
    .empty-state {
        text-align: center; padding: 60px 20px;
    }
    .empty-state .empty-icon { font-size: 3rem; color: var(--text-muted); margin-bottom: 14px; }
    .empty-state p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px; }

    @media (max-width: 600px) {
        .video-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
    }
</style>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-film me-2" style="color:var(--accent)"></i>Daftar Video</h1>
        <p>Kelola video yang akan digunakan untuk streaming</p>
    </div>
    <a href="{{ route('videos.create') }}" class="btn-upload">
        <i class="fas fa-upload"></i> Upload Video
    </a>
</div>

@if ($videos->isEmpty())
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;">
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-video-slash"></i></div>
            <p>Belum ada video yang diunggah.<br>Upload video pertama Anda sekarang.</p>
            <a href="{{ route('videos.create') }}" class="btn-upload">
                <i class="fas fa-upload"></i> Upload Video
            </a>
        </div>
    </div>
@else
    <div class="video-grid">
        @foreach ($videos as $video)
            <div class="video-card">
                <div class="video-thumb video-wrapper" data-src="{{ route('videos.stream', $video) }}">
                    <div class="video-placeholder"
                         style="background-image:url('{{ route('videos.thumbnail', $video) }}');">
                        <div class="play-circle"><i class="fas fa-play"></i></div>
                    </div>
                    <video controls preload="none" class="d-none">
                        Browser Anda tidak mendukung tag video.
                    </video>
                </div>
                <div class="video-meta">
                    <p class="vid-title">{{ $video->title }}</p>
                    <p class="vid-file"><i class="fas fa-file-video me-1"></i>{{ basename($video->path) }}</p>
                    <div class="video-actions">
                        <a href="{{ route('videos.edit', $video) }}" class="btn-act edit">
                            <i class="fas fa-pen"></i> Edit
                        </a>
                        <form action="{{ route('videos.destroy', $video) }}" method="POST"
                              onsubmit="return confirm('Hapus video \'{{ addslashes($video->title) }}\'?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-act del">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                const wrapper = entry.target;
                const video   = wrapper.querySelector('video');
                const src     = wrapper.dataset.src;
                if (src && !video.querySelector('source')) {
                    const s = document.createElement('source');
                    s.src = src; s.type = 'video/mp4';
                    video.appendChild(s); video.load();
                }
                observer.unobserve(wrapper);
            }
        });
    }, { rootMargin: '100px' });

    document.querySelectorAll('.video-wrapper').forEach(function (wrapper) {
        observer.observe(wrapper);
        wrapper.addEventListener('click', function () {
            const ph  = wrapper.querySelector('.video-placeholder');
            const vid = wrapper.querySelector('video');
            const src = wrapper.dataset.src;
            if (src && !vid.querySelector('source')) {
                const s = document.createElement('source');
                s.src = src; s.type = 'video/mp4';
                vid.appendChild(s); vid.load();
            }
            if (ph) ph.classList.add('d-none');
            vid.classList.remove('d-none');
            vid.play();
        });
    });
});
</script>
@endsection
