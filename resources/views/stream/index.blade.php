@extends('layouts.app')

@section('content')
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h1 class="h4 mb-0"><i class="fas fa-broadcast-tower me-2"></i>Manajemen Streaming Langsung</h1>
        </div>
        <div class="card-body">
            <!-- ... existing streaming status and other sections ... -->

            <!-- Video Selection Section -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-light">
                            <h3 class="h5 mb-0"><i class="fas fa-video me-2"></i>Pilih Video untuk Streaming</h3>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('stream.start') }}" method="POST" id="streamForm">
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
                                        <label class="form-label mb-3">Pilih dan atur urutan video untuk streaming:</label>
                                        <div class="mb-3">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="selectAllVideos">
                                                <label class="form-check-label" for="selectAllVideos">Pilih Semua
                                                    Video</label>
                                            </div>
                                        </div>

                                        <div class="row row-cols-1 row-cols-md-4 g-4" id="sortableVideos">
                                            @foreach ($videos as $video)
                                                <div class="col video-item" data-id="{{ $video->id }}">
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
                                                                        class="form-check-input video-checkbox">
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
        .video-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: move;
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

        /* Drag and drop styling */
        .sortable-ghost {
            opacity: 0.5;
            background: #c8ebfb;
        }

        .sortable-chosen {
            cursor: grabbing;
        }
    </style>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sortable = new Sortable(document.getElementById('sortableVideos'), {
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function() {
                    console.log('Drag ended, updating order...');
                    updateVideoOrder();
                }
            });

            function updateVideoOrder() {
                const videoItems = document.querySelectorAll('.video-item');
                const order = Array.from(videoItems).map(item => item.dataset.id);
                console.log('New order:', order);

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (!csrfToken) {
                    console.error('CSRF token not found');
                    alert('Token CSRF tidak ditemukan. Silakan muat ulang halaman.');
                    return;
                }

                fetch("{{ route('stream.updateOrder') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            order
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            alert('Urutan video berhasil diperbarui!');
                        } else {
                            console.error('Failed to update order:', data.message);
                            alert('Gagal memperbarui urutan: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('AJAX error:', error);
                        alert('Terjadi kesalahan saat memperbarui urutan: ' + error.message);
                    });
            }

            const selectAllCheckbox = document.getElementById('selectAllVideos');
            const videoCheckboxes = document.querySelectorAll('.video-checkbox');

            selectAllCheckbox.addEventListener('change', function() {
                videoCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });

            document.querySelectorAll('.video-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'LABEL') {
                        const checkbox = this.querySelector('input[type="checkbox"]');
                        checkbox.checked = !checkbox.checked;
                    }
                });
            });
        });
    </script>
@endsection
