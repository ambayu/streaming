@extends('layouts.app')

@section('content')
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h1 class="h4 mb-0"><i class="fas fa-broadcast-tower me-2"></i>Manajemen Streaming Langsung</h1>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Kolom Kiri: Status Streaming, PM2 Status, Daftar Video yang Dijalankan -->
                <div class="col-md-6">
                    <!-- Streaming Status Card -->
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

                    <!-- Daftar Video yang Dijalankan -->
                    @if ($isStreaming && !empty($streamingVideos))
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-light">
                                <h3 class="h5 mb-0"><i class="fas fa-play-circle me-2"></i>Video yang Sedang Di-stream</h3>
                            </div>
                            <div class="card-body">
                                <ol class="list-group list-group-numbered">
                                    @foreach ($streamingVideos as $video)
                                        <li class="list-group-item">{{ $video['title'] }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                    @endif

                    <!-- PM2 Process Status with Collapse (Default Closed) -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h3 class="h5 mb-0"><i class="fas fa-server me-2"></i>Status Proses PM2</h3>
                            <button class="btn btn-link text-decoration-none p-0 collapse-toggle-btn" type="button"
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
                </div>

                <!-- Kolom Kanan: YouTube Stream Key, Error/Success Messages, Stop Streaming -->
                <div class="col-md-6">
                    <!-- Error and Success Messages -->
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

                    <!-- YouTube Stream Key Section -->
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
                                    <small class="text-muted">Kunci ini digunakan untuk mengirim video ke YouTube
                                        Live</small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Simpan Kunci
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Stop Streaming Section -->
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
                                @if (!$isStreaming)
                                    <p class="text-muted text-center mt-2 mb-0">Tidak ada streaming yang aktif</p>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log Streaming (Full Width: col-md-12) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h3 class="h5 mb-0"><i class="fas fa-clipboard-list me-2"></i>Log Streaming</h3>
                            <button class="btn btn-link text-decoration-none p-0 collapse-toggle-btn" type="button"
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
                </div>
            </div>

            <!-- Video Selection Section (Full Width: col-md-12) -->
            <div class="row">
                <div class="col-md-12">
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
                                        <div class="mb-3">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="selectAllVideos">
                                                <label class="form-check-label" for="selectAllVideos">Pilih Semua
                                                    Video</label>
                                            </div>
                                        </div>
                                        <div class="row row-cols-1 row-cols-md-4 g-4">
                                            @foreach ($videos as $video)
                                                <div class="col">
                                                    <div class="card h-100 shadow-sm video-card">
                                                        <div class="card-img-top video-thumbnail">
                                                            <video class="w-100" controls>
                                                                <source src="{{ Storage::url($video->path) }}"
                                                                    type="video/mp4">
                                                                Browser Anda tidak mendukung video.
                                                            </video>
                                                            <div class="video-overlay">
                                                                <div class="form-check form-switch">
                                                                    <input type="checkbox" name="videos[]"
                                                                        id="video_{{ $video->id }}"
                                                                        value="{{ $video->id }}"
                                                                        class="form-check-input">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <h5 class="card-title text-truncate">{{ $video->title }}</h5>
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
                </div>
            </div>
        </div>
    </div>
@endsection

@section('styles')
    <style>
        .terminal-container {
            border: 1px solid #444;
            border-radius: 5px;
            position: relative;
            transition: all 0.3s ease;
        }

        .terminal-header {
            padding: 5px 10px;
            background-color: #333;
            border-radius: 3px 3px 0 0;
        }

        .terminal-content {
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            color: #0f0;
            background-color: #000;
            padding: 10px;
            border-radius: 0 0 3px 3px;
        }

        .video-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }

        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .video-thumbnail {
            position: relative;
            overflow: hidden;
            height: 160px;
            background-color: #000;
        }

        .video-thumbnail video {
            height: 100%;
            object-fit: cover;
            opacity: 0.9;
            transition: opacity 0.3s;
        }

        .video-card:hover .video-thumbnail video {
            opacity: 1;
        }

        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            padding: 10px;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .video-card:hover .video-overlay {
            opacity: 1;
        }

        .form-check-input {
            transform: scale(1.3);
        }

        .toggle-password {
            cursor: pointer;
        }

        /* Styling untuk collapse */
        .collapse-toggle-btn {
            transition: transform 0.3s;
        }

        .collapse-toggle-btn .fas {
            transition: transform 0.3s;
        }

        .collapse-toggle-btn:not(.collapsed) .fas {
            transform: rotate(0deg);
        }

        .collapse-toggle-btn.collapsed .fas {
            transform: rotate(-90deg);
        }

        .card-header {
            cursor: pointer;
        }

        /* Responsif untuk layar kecil */
        @media (max-width: 767px) {
            .row>.col-md-6 {
                margin-bottom: 1.5rem;
            }
        }
    </style>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllVideos');
            const videoCheckboxes = document.querySelectorAll('input[name="videos[]"]');

            selectAllCheckbox.addEventListener('change', function() {
                videoCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });

            // Debugging collapse buttons
            document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(button => {
                console.log('Collapse button initialized:', button);
                const target = document.querySelector(button.getAttribute('data-bs-target'));

                // Tambahkan event listener untuk status collapse
                target.addEventListener('shown.bs.collapse', () => {
                    button.classList.remove('collapsed');
                    console.log('Collapse shown:', button.getAttribute('data-bs-target'));
                });
                target.addEventListener('hidden.bs.collapse', () => {
                    button.classList.add('collapsed');
                    console.log('Collapse hidden:', button.getAttribute('data-bs-target'));
                });
            });

            // Toggle password visibility
            document.querySelectorAll('.toggle-password').forEach(button => {
                console.log('Toggle password button initialized:', button);
                button.addEventListener('click', function() {
                    const passwordInput = document.getElementById('youtube_key');
                    const icon = this.querySelector('i');
                    console.log('Toggling password visibility');
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });

            // Video card selection
            document.querySelectorAll('.video-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'LABEL') {
                        const checkbox = this.querySelector('input[type="checkbox"]');
                        checkbox.checked = !checkbox.checked;
                        console.log('Video card toggled:', checkbox.value);
                    }
                });
            });
        });
    </script>
@endsection
