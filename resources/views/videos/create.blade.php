@extends('layouts.app')

@section('page-styles')
<style>
    .form-page-wrapper {
        max-width: 640px;
        margin: 0 auto;
    }

    .page-header { margin-bottom: 28px; }
    .page-header h1 {
        font-size: 1.5rem; font-weight: 700;
        color: var(--text-primary); letter-spacing: -0.5px; margin: 0;
    }
    .page-header p { font-size: 0.875rem; color: var(--text-secondary); margin: 4px 0 0; }

    .form-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        overflow: hidden;
    }

    .form-card .form-card-body { padding: 28px; }

    .field { margin-bottom: 20px; }

    .field label {
        display: block;
        font-size: 0.82rem; font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 8px;
        letter-spacing: 0.2px;
    }

    .input-dark {
        width: 100%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 9px;
        color: var(--text-primary);
        padding: 11px 14px;
        font-size: 0.875rem;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        font-family: 'Inter', sans-serif;
    }
    .input-dark:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow);
    }
    .input-dark::placeholder { color: var(--text-muted); }
    .input-dark.is-invalid { border-color: #ef4444; }

    .error-msg { font-size: 0.78rem; color: #f87171; margin-top: 6px; }

    /* File drop zone */
    .file-zone {
        border: 2px dashed var(--border-color);
        border-radius: 10px;
        padding: 30px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: var(--bg-secondary);
        position: relative;
    }
    .file-zone:hover, .file-zone.drag-over {
        border-color: var(--accent);
        background: rgba(99,102,241,0.06);
    }
    .file-zone input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer;
    }
    .file-zone .zone-icon { font-size: 2rem; color: var(--text-muted); margin-bottom: 10px; }
    .file-zone .zone-text { font-size: 0.875rem; color: var(--text-secondary); margin: 0; }
    .file-zone .zone-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }
    .file-zone .zone-selected { font-size: 0.82rem; color: var(--accent); margin-top: 8px; font-weight: 500; }

    /* Progress */
    .progress-wrap { margin-top: 16px; display: none; }
    .progress-wrap label { font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 8px; display: block; }
    .progress-track {
        height: 8px; background: rgba(255,255,255,0.07);
        border-radius: 99px; overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--accent), #818cf8);
        border-radius: 99px;
        width: 0%;
        transition: width 0.3s;
    }
    .progress-pct { font-size: 0.75rem; color: var(--accent); text-align: right; margin-top: 4px; }

    /* Buttons */
    .form-actions { display: flex; gap: 10px; margin-top: 28px; }

    .btn-primary-dark {
        flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px;
        padding: 11px 20px; border-radius: 10px;
        font-size: 0.875rem; font-weight: 600;
        background: linear-gradient(135deg, var(--accent), #818cf8);
        color: #fff; border: none; cursor: pointer;
        box-shadow: 0 4px 14px var(--accent-glow);
        transition: all 0.2s;
    }
    .btn-primary-dark:hover:not(:disabled) {
        transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4);
    }
    .btn-primary-dark:disabled { opacity: 0.4; cursor: not-allowed; }

    .btn-secondary-dark {
        display: flex; align-items: center; gap: 8px;
        padding: 11px 18px; border-radius: 10px;
        font-size: 0.875rem; font-weight: 600;
        background: rgba(255,255,255,0.05);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
        cursor: pointer; text-decoration: none;
        transition: all 0.2s;
    }
    .btn-secondary-dark:hover { background: rgba(255,255,255,0.09); color: var(--text-primary); }
</style>
@endsection

@section('content')
<div class="form-page-wrapper">
    <div class="page-header">
        <h1><i class="fas fa-upload me-2" style="color:var(--accent)"></i>Upload Video</h1>
        <p>Tambahkan video baru untuk keperluan streaming</p>
    </div>

    <div class="form-card">
        <div class="form-card-body">
            <form id="uploadForm" action="{{ route('videos.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                {{-- Judul --}}
                <div class="field">
                    <label for="title">Judul Video</label>
                    <input type="text" name="title" id="title"
                           class="input-dark @error('title') is-invalid @enderror"
                           value="{{ old('title') }}"
                           placeholder="Masukkan judul video..."
                           required>
                    @error('title')
                        <p class="error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- File upload --}}
                <div class="field">
                    <label for="video">File Video <span style="color:var(--text-muted);font-weight:400">(MP4)</span></label>
                    <div class="file-zone" id="fileZone">
                        <input type="file" name="video" id="video" accept=".mp4" required
                               onchange="updateFileName(this)">
                        <div class="zone-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <p class="zone-text">Klik atau seret file ke sini</p>
                        <p class="zone-hint">Format: MP4</p>
                        <p class="zone-selected" id="fileNameDisplay" style="display:none;"></p>
                    </div>
                    @error('video')
                        <p class="error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Progress --}}
                <div class="progress-wrap" id="progressContainer">
                    <label>Progress Upload</label>
                    <div class="progress-track">
                        <div class="progress-fill" id="progressBar"></div>
                    </div>
                    <p class="progress-pct" id="progressPct">0%</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary-dark" id="uploadButton">
                        <i class="fas fa-upload"></i> Upload
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

@section('scripts')
<script>
    function updateFileName(input) {
        const display = document.getElementById('fileNameDisplay');
        if (input.files && input.files[0]) {
            display.textContent = '📁 ' + input.files[0].name;
            display.style.display = 'block';
        }
    }

    // Drag & drop visual
    const zone = document.getElementById('fileZone');
    if (zone) {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', () => zone.classList.remove('drag-over'));
    }

    document.getElementById('uploadForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const form       = this;
        const formData   = new FormData(form);
        const container  = document.getElementById('progressContainer');
        const fill       = document.getElementById('progressBar');
        const pct        = document.getElementById('progressPct');
        const btn        = document.getElementById('uploadButton');

        container.style.display = 'block';
        btn.disabled = true;

        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function (event) {
            if (event.lengthComputable) {
                const p = Math.round((event.loaded / event.total) * 100);
                fill.style.width = p + '%';
                pct.textContent  = p + '%';
            }
        });

        xhr.addEventListener('load', function () {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    window.location.href = response.redirect;
                } else {
                    alert('Error: ' + response.message);
                    container.style.display = 'none';
                    btn.disabled = false;
                }
            } else {
                alert('Gagal mengunggah: ' + xhr.statusText);
                container.style.display = 'none';
                btn.disabled = false;
            }
        });

        xhr.addEventListener('error', function () {
            alert('Terjadi kesalahan saat mengunggah. Periksa koneksi Anda.');
            container.style.display = 'none';
            btn.disabled = false;
        });

        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
        xhr.send(formData);
    });
</script>
@endsection
