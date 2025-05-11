@extends('layouts.app')

@section('content')
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h1 class="h4 mb-0">Manajemen Streaming</h1>
        </div>
        <div class="card-body">
            <!-- Streaming Status Indicator -->
            @if ($isStreaming)
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" fill="currentColor">
                        <circle cx="12" cy="12" r="10" fill="green" />
                    </svg>
                    <div>
                        <strong>Streaming Aktif!</strong> Proses PM2 sedang berjalan.
                    </div>
                </div>
            @else
                <div class="alert alert-secondary d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" fill="currentColor">
                        <circle cx="12" cy="12" r="10" fill="gray" />
                    </svg>
                    <div>
                        Tidak ada streaming aktif.
                    </div>
                </div>
            @endif

            <!-- Tampilkan Daftar Proses PM2 -->
            @if (!empty($pm2Status))
                <h3>Proses PM2 Aktif</h3>
                <pre class="bg-light p-3 rounded">{{ $pm2Status }}</pre>
            @else
                <p class="text-muted">Tidak ada proses PM2 aktif.</p>
            @endif

            <!-- Tampilkan Log Streaming -->
            @if ($isStreaming && !empty($streamLog))
                <h3>Log Streaming Terbaru</h3>
                <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;">{{ $streamLog }}</pre>
            @else
                <p class="text-muted">{{ $streamLog }}</p>
            @endif

            <!-- Tampilkan Pesan Error -->
            @if (session('error'))
                <div class="alert alert-danger">
                    <strong>Error:</strong> {{ session('error') }}
                </div>
            @endif

            <h3>YouTube Stream Key</h3>
            <form action="{{ route('stream.storeKey') }}" method="POST" class="mb-4">
                @csrf
                <div class="mb-3">
                    <label for="youtube_key" class="form-label">YouTube Key</label>
                    <input type="text" name="youtube_key" id="youtube_key"
                        class="form-control @error('youtube_key') is-invalid @enderror"
                        value="{{ $setting->youtube_key ?? '' }}" required>
                    @error('youtube_key')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">Simpan Key</button>
            </form>

            <h3>Pilih Video untuk Streaming</h3>
            <form action="{{ route('stream.start') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Pilih Video</label>
                    @if ($videos->isEmpty())
                        <p class="text-muted">Tidak ada video yang tersedia.</p>
                    @else
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            @foreach ($videos as $video)
                                <div class="col">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input type="checkbox" name="videos[]" id="video_{{ $video->id }}"
                                                    value="{{ $video->id }}" class="form-check-input">
                                                <label for="video_{{ $video->id }}"
                                                    class="form-check-label">{{ $video->title }}
                                                    ({{ basename($video->path) }})</label>
                                            </div>
                                            <video width="100%" height="120" controls class="rounded mt-2">
                                                <source src="{{ Storage::url($video->path) }}" type="video/mp4">
                                                Browser Anda tidak mendukung tag video.
                                            </video>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @error('videos')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                @if (!$videos->isEmpty())
                    <button type="submit" class="btn btn-success">Mulai Streaming</button>
                @endif
            </form>

            <form action="{{ route('stream.stop') }}" method="POST" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-danger" @if (!$isStreaming) disabled @endif>Hentikan
                    Streaming</button>
            </form>
        </div>
    </div>
@endsection
