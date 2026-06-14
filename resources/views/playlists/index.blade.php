@extends('layouts.app')

@section('page-styles')
<style>
    .page-header { margin-bottom: 28px; }
    .page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); letter-spacing: -0.5px; margin: 0; }
    .page-header p  { font-size: 0.875rem; color: var(--text-secondary); margin: 4px 0 0; }

    /* ── Create Form Card ── */
    .create-card {
        background: var(--bg-card); border: 1px solid var(--border-color);
        border-radius: 14px; padding: 24px; margin-bottom: 28px;
    }
    .create-card h2 { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); margin: 0 0 16px; }

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

    .btn-primary-dark {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 10px 20px; border-radius: 10px; font-size: 0.875rem; font-weight: 600;
        background: linear-gradient(135deg, var(--accent), #818cf8); color: #fff;
        border: none; cursor: pointer; box-shadow: 0 4px 14px var(--accent-glow);
        transition: all 0.2s;
    }
    .btn-primary-dark:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }

    /* ── Playlist Grid ── */
    .playlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 18px;
    }

    .playlist-card {
        background: var(--bg-card); border: 1px solid var(--border-color);
        border-radius: 14px; overflow: hidden; transition: all 0.2s;
        display: flex; flex-direction: column;
    }
    .playlist-card:hover { border-color: rgba(99,102,241,0.3); transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.3); }

    .playlist-card .pl-cover {
        height: 80px;
        background: linear-gradient(135deg, rgba(99,102,241,0.25) 0%, rgba(129,140,248,0.1) 100%);
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; color: rgba(99,102,241,0.6);
        border-bottom: 1px solid var(--border-color);
    }

    .playlist-card .pl-body { padding: 16px; flex: 1; }
    .playlist-card .pl-name { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin: 0 0 4px; }
    .playlist-card .pl-desc { font-size: 0.8rem; color: var(--text-muted); margin: 0 0 12px; min-height: 20px; }

    .pl-meta {
        display: flex; align-items: center; gap: 6px;
        font-size: 0.78rem; color: var(--text-muted); margin-bottom: 16px;
    }
    .pl-meta i { color: var(--accent); }

    .pl-actions { display: flex; gap: 8px; }

    .btn-pl {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
        padding: 8px 10px; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
        cursor: pointer; border: none; text-decoration: none; transition: all 0.2s;
    }

    .btn-pl.open {
        background: rgba(99,102,241,0.12); color: var(--accent-hover);
        border: 1px solid rgba(99,102,241,0.25);
    }
    .btn-pl.open:hover { background: rgba(99,102,241,0.22); color: #fff; }

    .btn-pl.del {
        background: rgba(239,68,68,0.1); color: #f87171;
        border: 1px solid rgba(239,68,68,0.2);
    }
    .btn-pl.del:hover { background: rgba(239,68,68,0.2); }

    /* ── Empty State ── */
    .empty-state { text-align: center; padding: 50px 20px; }
    .empty-state .empty-icon { font-size: 3rem; color: var(--text-muted); margin-bottom: 12px; }
    .empty-state p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }

    /* ── Section label ── */
    .section-label {
        font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 14px;
        display: flex; align-items: center; gap: 8px;
    }
    .section-label::after { content: ''; flex: 1; height: 1px; background: var(--border-color); }
</style>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-list-ul me-2" style="color:var(--accent)"></i>Playlist</h1>
        <p>Kelompokkan video ke dalam playlist untuk streaming yang mudah</p>
    </div>
</div>

{{-- Create Form --}}
<div class="create-card">
    <h2><i class="fas fa-plus-circle me-2" style="color:var(--accent)"></i>Buat Playlist Baru</h2>
    <form action="{{ route('playlists.store') }}" method="POST">
        @csrf
        <div class="row g-3">
            <div class="col-md-5">
                <div class="field" style="margin:0;">
                    <label for="name">Nama Playlist</label>
                    <input type="text" name="name" id="name"
                           class="input-dark"
                           placeholder="Cth: Playlist Pagi Hari"
                           value="{{ old('name') }}" required>
                    @error('name') <p style="font-size:0.78rem;color:#f87171;margin-top:5px;">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="col-md-5">
                <div class="field" style="margin:0;">
                    <label for="description">Deskripsi <span style="color:var(--text-muted);font-weight:400">(opsional)</span></label>
                    <input type="text" name="description" id="description"
                           class="input-dark"
                           placeholder="Deskripsi singkat playlist..."
                           value="{{ old('description') }}">
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn-primary-dark w-100" style="padding:10px;">
                    <i class="fas fa-plus"></i> Buat
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Playlist Grid --}}
@if ($playlists->isEmpty())
    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;">
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-list-ul"></i></div>
            <p>Belum ada playlist. Buat playlist pertama Anda di atas!</p>
        </div>
    </div>
@else
    <p class="section-label">{{ $playlists->count() }} Playlist</p>
    <div class="playlist-grid">
        @foreach ($playlists as $playlist)
            <div class="playlist-card">
                <div class="pl-cover">
                    <i class="fas fa-compact-disc"></i>
                </div>
                <div class="pl-body">
                    <p class="pl-name">{{ $playlist->name }}</p>
                    <p class="pl-desc">{{ $playlist->description ?: 'Tidak ada deskripsi' }}</p>
                    <div class="pl-meta">
                        <i class="fas fa-film"></i>
                        {{ $playlist->videos_count }} video
                        <span style="margin-left:auto;font-size:0.73rem;">
                            {{ $playlist->created_at->diffForHumans() }}
                        </span>
                    </div>
                    <div class="pl-actions">
                        <a href="{{ route('playlists.show', $playlist) }}" class="btn-pl open">
                            <i class="fas fa-folder-open"></i> Buka
                        </a>
                        <form action="{{ route('playlists.destroy', $playlist) }}" method="POST"
                              onsubmit="return confirm('Hapus playlist \'{{ addslashes($playlist->name) }}\'?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-pl del">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection
