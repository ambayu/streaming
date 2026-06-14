@extends('layouts.app')

@section('page-styles')
<style>
    .form-page-wrapper { max-width: 560px; margin: 0 auto; }

    .page-header { margin-bottom: 28px; }
    .page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); letter-spacing: -0.5px; margin: 0; }
    .page-header p { font-size: 0.875rem; color: var(--text-secondary); margin: 4px 0 0; }

    .form-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 14px;
    }
    .form-card .form-card-body { padding: 28px; }

    .field { margin-bottom: 20px; }
    .field label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; }

    .input-dark {
        width: 100%; background: var(--bg-secondary);
        border: 1px solid var(--border-color); border-radius: 9px;
        color: var(--text-primary); padding: 11px 14px; font-size: 0.875rem;
        outline: none; transition: border-color 0.2s, box-shadow 0.2s;
        font-family: 'Inter', sans-serif;
    }
    .input-dark:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    .input-dark.is-invalid { border-color: #ef4444; }
    .error-msg { font-size: 0.78rem; color: #f87171; margin-top: 6px; }

    .form-actions { display: flex; gap: 10px; margin-top: 28px; }

    .btn-primary-dark {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;
        padding: 11px 20px; border-radius: 10px; font-size: 0.875rem; font-weight: 600;
        background: linear-gradient(135deg, var(--accent), #818cf8);
        color: #fff; border: none; cursor: pointer;
        box-shadow: 0 4px 14px var(--accent-glow); transition: all 0.2s;
    }
    .btn-primary-dark:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }

    .btn-secondary-dark {
        display: flex; align-items: center; gap: 8px;
        padding: 11px 18px; border-radius: 10px; font-size: 0.875rem; font-weight: 600;
        background: rgba(255,255,255,0.05); color: var(--text-secondary);
        border: 1px solid var(--border-color); cursor: pointer; text-decoration: none; transition: all 0.2s;
    }
    .btn-secondary-dark:hover { background: rgba(255,255,255,0.09); color: var(--text-primary); }
</style>
@endsection

@section('content')
<div class="form-page-wrapper">
    <div class="page-header">
        <h1><i class="fas fa-pen me-2" style="color:var(--accent)"></i>Edit Judul Video</h1>
        <p>Perbarui judul untuk video <strong style="color:var(--text-primary)">{{ $video->title }}</strong></p>
    </div>

    <div class="form-card">
        <div class="form-card-body">
            <form action="{{ route('videos.update', $video) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="title">Judul Video</label>
                    <input type="text" name="title" id="title"
                           class="input-dark @error('title') is-invalid @enderror"
                           value="{{ old('title', $video->title) }}"
                           placeholder="Masukkan judul baru..."
                           required>
                    @error('title')
                        <p class="error-msg">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary-dark">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                    <a href="{{ route('videos.index') }}" class="btn-secondary-dark">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
