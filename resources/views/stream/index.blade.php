@extends('layouts.app')

@section('content')
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h1 class="h4 mb-0"><i class="fas fa-broadcast-tower me-2"></i>Manajemen Streaming Langsung</h1>
        </div>
        <div class="card-body">
            <!-- Status Streaming -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="h5 mb-0"><i class="fas fa-info-circle me-2"></i>Status Streaming</h3>
                </div>
                <div class="card-body">
                    @if ($isStreaming)
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <div class="me-3">
                                <i class="fas fa-circle-play fa-2x"></i>
                            </div>
                            <div>
                                <h4 class="alert-heading mb-1">Streaming Aktif!</h4>
                                <p class="mb-0">Proses streaming sedang berjalan dengan lancar.</p>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-secondary d-flex align-items-center" role="alert">
                            <div class="me-3">
                                <i class="fas fa-circle-stop fa-2x"></i>
                            </div>
                            <div>
                                <h4 class="alert-heading mb-1">Streaming Tidak Aktif</h4>
                                <p class="mb-0">Tidak ada streaming yang sedang berlangsung.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Status PM2 (Collapsible) -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h3 class="h5 mb-0"><i class="fas fa-server me-2"></i>Status Proses PM2</h3>
                    <button class="btn btn-link text-decoration-none p-0 toggle-collapse" type="button"
                        data-bs-toggle="collapse" data-bs-target="#pm2StatusCollapse" aria-expanded="false"
                        aria-controls="pm2StatusCollapse">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="card-body collapse" id="pm2StatusCollapse">
                    @if (!empty($pm2Status))
                        <div class="terminal-container bg-dark text-light p-3 rounded">
                            <div class="terminal-header mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted">PM2 Process List</span>
                                <span class="badge bg-primary">Live</span>
                            </div>
                            <pre class="mb-0 terminal-content">{{ $pm2Status }}</pre>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-server fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Tidak ada proses PM2 yang sedang berjalan</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Log Streaming (Collapsible) -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h3 class="h5 mb-0"><i class="fas fa-clipboard-list me-2"></i>Log Streaming</h3>
                    <button class="btn btn-link text-decoration-none p-0 toggle-collapse" type="button"
                        data-bs-toggle="collapse" data-bs-target="#streamLogCollapse" aria-expanded="false"
                        aria-controls="streamLogCollapse">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="card-body collapse" id="streamLogCollapse">
                    @if ($isStreaming && !empty($streamLog))
                        <div class="terminal-container bg-dark text-light p-3 rounded">
                            <div class="terminal-header mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted">Live Streaming Logs</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <pre class="mb-0 terminal-content" style="max-height: 300px; overflow-y: auto;">{{ $streamLog }}</pre>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Tidak ada log streaming yang tersedia</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Flash Message -->
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Terjadi Kesalahan:</strong> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Berhasil:</strong> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Stream Key -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="h5 mb-0"><i class="fab fa-youtube me-2 text-danger"></i>YouTube Stream Key</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('stream.storeKey') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="youtube_key" class="form-label">Kunci Streaming YouTube</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="youtube_key" id="youtube_key"
                                    class="form-control @error('youtube_key') is-invalid @enderror"
                                    value="{{ $setting->youtube_key ?? '' }}" required
                                    placeholder="Masukkan kunci streaming YouTube">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            @error('youtube_key')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Kunci ini digunakan untuk mengirim video ke YouTube Live</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Kunci
                        </button>
                    </form>
                </div>
            </div>

            <!-- Pilih Video -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="h5 mb-0"><i class="fas fa-video me-2"></i>Pilih Video untuk Streaming</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('stream.start') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            @if ($videos->isEmpty())
                                <div class="text-center py-4">
                                    <i class="fas fa-video-slash fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada video yang tersedia untuk streaming</p>
                                    <a href="{{ route('videos.create') }}" class="btn btn-primary">
                                        <i class="fas fa-upload me-1"></i> Upload Video Baru
                                    </a>
                                </div>
                            @else
                                <label class="form-label mb-3">Pilih satu atau lebih video untuk streaming:</label>
                                <div class="row row-cols-1 g-4">
                                    @foreach ($videos as $video)
                                        <div class="col">
                                            <div class="card h-100 shadow-sm video-card">
                                                <div class="card-img-top video-thumbnail">
                                                    <video class="w-100" controls>
                                                        <source src="{{ Storage::url($video->path) }}" type="video/mp4">
                                                        Browser Anda tidak mendukung video.
                                                    </video>
                                                    <div class="video-overlay">
                                                        <div class="form-check form-switch">
                                                            <input type="checkbox" name="videos[]"
                                                                value="{{ $video->id }}" class="form-check-input">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <h5 class="card-title text-truncate">{{ $video->title }}</h5>
                                                    <p class="card-text text-muted small">
                                                        <i class="far fa-file me-1"></i> {{ basename($video->path) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @error('videos')
                                    <div class="text-danger mt-2">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        @if (!$videos->isEmpty())
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-play-circle me-1"></i> Mulai Streaming
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            <!-- Stop Streaming -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="h5 mb-0"><i class="fas fa-stop-circle me-2"></i>Hentikan Streaming</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('stream.stop') }}" method="POST">
                        @csrf
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger btn-lg"
                                @if (!$isStreaming) disabled @endif>
                                <i class="fas fa-stop-circle me-1"></i> Hentikan Streaming
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Toggle stream key password
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', () => {
                const input = button.previousElementSibling;
                if (input.type === 'password') {
                    input.type = 'text';
                    button.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    input.type = 'password';
                    button.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });
        });

        // Toggle collapse icon
        document.querySelectorAll('.toggle-collapse').forEach(button => {
            const icon = button.querySelector('i');
            const collapse = document.querySelector(button.dataset.bsTarget);

            collapse.addEventListener('show.bs.collapse', () => icon.className = 'fas fa-chevron-up');
            collapse.addEventListener('hide.bs.collapse', () => icon.className = 'fas fa-chevron-down');
        });
    </script>
@endpush
