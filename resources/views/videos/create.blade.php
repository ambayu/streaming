@extends('layouts.app')

@section('content')
    <div class="card shadow-sm">
        <div class="card-header">
            <h1 class="h4 mb-0">Upload Video</h1>
        </div>
        <div class="card-body">
            <form id="uploadForm" action="{{ route('videos.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="title" class="form-label">Judul</label>
                    <input type="text" name="title" id="title"
                        class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="video" class="form-label">Video </label>
                    <input type="file" name="video" id="video"
                        class="form-control @error('video') is-invalid @enderror" accept=".mp4" required>
                    @error('video')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <!-- Progress Bar -->
                <div class="mb-3" id="progressContainer" style="display: none;">
                    <label class="form-label">Progres Upload</label>
                    <div class="progress">
                        <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0"
                            aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" id="uploadButton">Upload</button>
            </form>

            <!-- Pesan Sukses atau Error -->
            @if (session('success'))
                <div class="alert alert-success mt-3">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger mt-3">{{ session('error') }}</div>
            @endif
        </div>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = document.getElementById('progressBar');
            const uploadButton = document.getElementById('uploadButton');

            // Tampilkan progress bar dan nonaktifkan tombol
            progressContainer.style.display = 'block';
            uploadButton.disabled = true;

            const xhr = new XMLHttpRequest();

            // Pantau progres upload
            xhr.upload.addEventListener('progress', function(event) {
                if (event.lengthComputable) {
                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressBar.textContent = percentComplete + '%';
                    progressBar.setAttribute('aria-valuenow', percentComplete);
                }
            });

            // Tangani respons
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        window.location.href = response.redirect;
                    } else {
                        alert('Error: ' + response.message);
                        progressContainer.style.display = 'none';
                        uploadButton.disabled = false;
                    }
                } else {
                    alert('Gagal mengunggah video: ' + xhr.statusText);
                    progressContainer.style.display = 'none';
                    uploadButton.disabled = false;
                }
            });

            // Tangani error
            xhr.addEventListener('error', function() {
                alert('Terjadi kesalahan saat mengunggah video. Periksa koneksi Anda.');
                progressContainer.style.display = 'none';
                uploadButton.disabled = false;
            });

            // Kirim request
            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            xhr.send(formData);
        });
    </script>
@endsection
