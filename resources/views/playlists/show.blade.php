@extends('layouts.app')

@section('page-styles')
<style>
    .page-header { margin-bottom: 24px; }
    .page-header h1 { font-size: 1.4rem; font-weight: 700; color: var(--text-primary); letter-spacing: -0.5px; margin: 0; }
    .page-header p  { font-size: 0.875rem; color: var(--text-secondary); margin: 4px 0 0; }

    .back-link {
        display: inline-flex; align-items: center; gap: 7px;
        font-size: 0.82rem; color: var(--text-muted); text-decoration: none;
        margin-bottom: 20px; transition: color 0.2s;
    }
    .back-link:hover { color: var(--accent); }

    /* ── Cards ── */
    .stream-card {
        background: var(--bg-card); border: 1px solid var(--border-color);
        border-radius: 14px; overflow: hidden; margin-bottom: 20px;
    }
    .stream-card .card-head {
        padding: 14px 20px; border-bottom: 1px solid var(--border-color);
        display: flex; align-items: center; justify-content: space-between;
        background: rgba(255,255,255,0.02);
    }
    .stream-card .card-head h3 {
        font-size: 0.9rem; font-weight: 600; color: var(--text-primary);
        margin: 0; display: flex; align-items: center; gap: 9px;
    }
    .stream-card .card-head h3 i { color: var(--accent); font-size: 0.85rem; }
    .stream-card .card-body-inner { padding: 20px; }

    /* ── Edit Form ── */
    .field { margin-bottom: 14px; }
    .field label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 7px; }

    .input-dark {
        width: 100%; background: var(--bg-secondary); border: 1px solid var(--border-color);
        border-radius: 9px; color: var(--text-primary); padding: 10px 14px;
        font-size: 0.875rem; outline: none; font-family: 'Inter', sans-serif;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .input-dark:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    .input-dark::placeholder { color: var(--text-muted); }

    .btn-save {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 18px; border-radius: 9px; font-size: 0.85rem; font-weight: 600;
        background: var(--accent); color: #fff; border: none; cursor: pointer; transition: all 0.2s;
    }
    .btn-save:hover { background: var(--accent-hover); transform: translateY(-1px); }

    /* ── Add Video Form ── */
    .add-video-row {
        display: flex; gap: 10px; align-items: flex-end;
    }
    .add-video-row select {
        flex: 1; background: var(--bg-secondary); border: 1px solid var(--border-color);
        border-radius: 9px; color: var(--text-primary); padding: 10px 14px;
        font-size: 0.875rem; outline: none; font-family: 'Inter', sans-serif;
        transition: border-color 0.2s, box-shadow 0.2s; cursor: pointer;
    }
    .add-video-row select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    .add-video-row select option { background: var(--bg-secondary); }

    .btn-add {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 10px 18px; border-radius: 9px; font-size: 0.875rem; font-weight: 600;
        background: linear-gradient(135deg, var(--accent), #818cf8); color: #fff;
        border: none; cursor: pointer; white-space: nowrap; transition: all 0.2s;
        box-shadow: 0 4px 12px var(--accent-glow);
    }
    .btn-add:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }

    /* ── Video list in playlist ── */
    .pl-video-list { list-style: none; padding: 0; margin: 0; }

    .pl-video-item {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 12px; border-radius: 10px;
        border: 1px solid var(--border-color);
        background: rgba(255,255,255,0.02);
        margin-bottom: 8px; transition: all 0.2s;
        cursor: move;
    }
    .pl-video-item:hover { border-color: rgba(99,102,241,0.25); background: rgba(99,102,241,0.05); }
    .pl-video-item.sortable-ghost { opacity: 0.35; border: 2px dashed var(--accent); }
    .pl-video-item.sortable-chosen { opacity: 0.75; transform: scale(1.02); }

    .pl-video-item .drag-handle { color: var(--text-muted); font-size: 0.8rem; flex-shrink: 0; }
    .pl-video-item .num { font-size: 0.75rem; color: var(--text-muted); min-width: 22px; font-weight: 600; flex-shrink: 0; }

    .pl-video-item .thumb {
        width: 52px; height: 36px; border-radius: 6px;
        background: #0d0f14; overflow: hidden; flex-shrink: 0;
        background-size: cover; background-position: center;
    }

    .pl-video-item .title { flex: 1; font-size: 0.875rem; font-weight: 500; color: var(--text-secondary); }

    .btn-remove {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 10px; border-radius: 7px; font-size: 0.75rem; font-weight: 600;
        background: rgba(239,68,68,0.1); color: #f87171;
        border: 1px solid rgba(239,68,68,0.2); cursor: pointer; transition: all 0.2s;
        flex-shrink: 0;
    }
    .btn-remove:hover { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); }

    /* ── Stream this playlist btn ── */
    .btn-stream-pl {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%; padding: 13px; border-radius: 10px; font-size: 0.9rem; font-weight: 700;
        background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none;
        cursor: pointer; box-shadow: 0 4px 14px rgba(34,197,94,0.25); transition: all 0.2s;
        text-decoration: none;
    }
    .btn-stream-pl:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(34,197,94,0.35); color: #fff; }

    /* ── Empty ── */
    .empty-state { text-align: center; padding: 36px 20px; }
    .empty-state .empty-icon { font-size: 2.5rem; color: var(--text-muted); margin-bottom: 12px; }
    .empty-state p { color: var(--text-muted); font-size: 0.875rem; margin: 0; }

    .section-label {
        font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 12px;
        display: flex; align-items: center; gap: 8px;
    }
    .section-label::after { content: ''; flex: 1; height: 1px; background: var(--border-color); }
</style>
@endsection

@section('content')

<a href="{{ route('playlists.index') }}" class="back-link">
    <i class="fas fa-arrow-left"></i> Kembali ke Playlist
</a>

<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-compact-disc me-2" style="color:var(--accent)"></i>{{ $playlist->name }}</h1>
        @if ($playlist->description)
            <p>{{ $playlist->description }}</p>
        @endif
    </div>
    <span style="font-size:0.8rem;color:var(--text-muted);">
        {{ $playlist->videos->count() }} video dalam playlist
    </span>
</div>

<div class="row g-4">

    {{-- LEFT: Video dalam playlist --}}
    <div class="col-lg-8">

        {{-- Stream this playlist --}}
        @if ($playlist->videos->count() > 0)
        <div class="stream-card">
            <div class="card-body-inner">
                <form action="{{ route('stream.startPlaylist') }}" method="POST">
                    @csrf
                    <input type="hidden" name="playlist_id" value="{{ $playlist->id }}">
                    <button type="submit" class="btn-stream-pl">
                        <i class="fas fa-play-circle"></i>
                        Streaming Playlist "{{ $playlist->name }}"
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- Daftar video --}}
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-film"></i> Video dalam Playlist</h3>
                @if ($playlist->videos->count() > 0)
                <span style="font-size:0.78rem;color:var(--text-muted);">Seret untuk mengubah urutan</span>
                @endif
            </div>
            <div class="card-body-inner">
                @if ($playlist->videos->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-film"></i></div>
                        <p>Belum ada video. Tambahkan video dari panel kanan.</p>
                    </div>
                @else
                    <ul class="pl-video-list" id="playlistVideoList">
                        @foreach ($playlist->videos as $i => $video)
                            <li class="pl-video-item" data-id="{{ $video->id }}">
                                <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                                <span class="num">{{ $i + 1 }}</span>
                                <div class="thumb"
                                     style="background-image:url('{{ route('videos.thumbnail', $video) }}');"></div>
                                <span class="title">{{ $video->title }}</span>
                                <form action="{{ route('playlists.removeVideo', [$playlist, $video]) }}"
                                      method="POST"
                                      onsubmit="return confirm('Hapus video ini dari playlist?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-remove">
                                        <i class="fas fa-times"></i> Hapus
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

    </div>

    {{-- RIGHT: Tambah video + Edit --}}
    <div class="col-lg-4">

        {{-- Tambah Video --}}
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-plus"></i> Tambah Video</h3>
            </div>
            <div class="card-body-inner">
                @if ($allVideos->isEmpty())
                    <div class="empty-state" style="padding:20px;">
                        <div class="empty-icon" style="font-size:1.8rem;"><i class="fas fa-video-slash"></i></div>
                        <p style="margin:0;">Belum ada video. <a href="{{ route('videos.create') }}" style="color:var(--accent);">Upload video</a> terlebih dahulu.</p>
                    </div>
                @else
                    <form action="{{ route('playlists.addVideo', $playlist) }}" method="POST">
                        @csrf
                        <div class="field">
                            <label for="video_id">Pilih Video</label>
                            <div class="add-video-row">
                                <select name="video_id" id="video_id" required>
                                    <option value="">-- Pilih video --</option>
                                    @foreach ($allVideos as $video)
                                        <option value="{{ $video->id }}">{{ $video->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-add w-100" style="margin-top:4px;">
                            <i class="fas fa-plus"></i> Tambahkan
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Edit Playlist --}}
        <div class="stream-card">
            <div class="card-head">
                <h3><i class="fas fa-pen"></i> Edit Playlist</h3>
            </div>
            <div class="card-body-inner">
                <form action="{{ route('playlists.update', $playlist) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="name">Nama</label>
                        <input type="text" name="name" id="name" class="input-dark"
                               value="{{ old('name', $playlist->name) }}" required>
                        @error('name') <p style="font-size:0.78rem;color:#f87171;margin-top:5px;">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label for="description">Deskripsi</label>
                        <input type="text" name="description" id="description" class="input-dark"
                               value="{{ old('description', $playlist->description) }}"
                               placeholder="Deskripsi (opsional)">
                    </div>
                    <button type="submit" class="btn-save w-100">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('playlistVideoList');
    if (!list) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    new Sortable(list, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        handle: '.drag-handle',
        onEnd: function () {
            const items = list.querySelectorAll('.pl-video-item');
            const order = Array.from(items).map(i => i.dataset.id);

            // Update nomor urut visual
            items.forEach((item, idx) => {
                const num = item.querySelector('.num');
                if (num) num.textContent = idx + 1;
            });

            fetch('{{ route('playlists.reorder', $playlist) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ order })
            })
            .then(r => r.json())
            .catch(e => console.error('Gagal menyimpan urutan:', e));
        }
    });
});
</script>
@endsection
