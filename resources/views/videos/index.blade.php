@extends('layouts.app')

@section('content')
    <div class="card shadow-sm">
        <div class="card-header">
            <h1 class="h4 mb-0">Daftar Video</h1>
        </div>
        <div class="card-body">
            <a href="{{ route('videos.create') }}" class="btn btn-primary mb-3">Upload Video</a>
            @if ($videos->isEmpty())
                <p>Tidak ada video yang diunggah.</p>
            @else
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    @foreach ($videos as $video)
                        <div class="col">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <h5 class="card-title">{{ $video->title }}</h5>
                                    <p class="card-text text-muted">File: {{ basename($video->path) }}</p>

                                    {{-- Wrapper video dengan placeholder --}}
                                    <div class="video-wrapper rounded mb-3 position-relative bg-dark"
                                         style="height:180px; cursor:pointer;"
                                         data-src="{{ route('videos.stream', $video) }}">

                                        {{-- Placeholder tampil sebelum video diload --}}
                                        <div class="video-placeholder d-flex flex-column align-items-center justify-content-center h-100 text-white">
                                            <i class="fas fa-play-circle fa-3x mb-2 text-secondary"></i>
                                            <small class="text-muted">Klik untuk putar</small>
                                        </div>

                                        {{-- Video element, src tidak diset dulu --}}
                                        <video width="100%" height="180"
                                               controls
                                               preload="none"
                                               class="rounded d-none w-100 h-100"
                                               style="object-fit:contain;">
                                            Browser Anda tidak mendukung tag video.
                                        </video>
                                    </div>

                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="{{ route('videos.edit', $video) }}" class="btn btn-sm btn-warning">Edit Judul</a>
                                        <form action="{{ route('videos.destroy', $video) }}" method="POST"
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus video {{ addslashes($video->title) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Intersection Observer: load video hanya saat masuk viewport
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                const wrapper = entry.target;
                const video   = wrapper.querySelector('video');
                const src     = wrapper.dataset.src;

                // Set src hanya jika belum pernah diset
                if (src && !video.src) {
                    const source = document.createElement('source');
                    source.src  = src;
                    source.type = 'video/mp4';
                    video.appendChild(source);
                    video.load();
                }

                observer.unobserve(wrapper); // Cukup sekali
            }
        });
    }, { rootMargin: '100px' }); // Preload 100px sebelum masuk layar

    document.querySelectorAll('.video-wrapper').forEach(function (wrapper) {
        observer.observe(wrapper);
    });

    // Klik placeholder → tampilkan video dan mainkan
    document.querySelectorAll('.video-wrapper').forEach(function (wrapper) {
        wrapper.addEventListener('click', function () {
            const placeholder = wrapper.querySelector('.video-placeholder');
            const video       = wrapper.querySelector('video');
            const src         = wrapper.dataset.src;

            // Set src jika belum ada
            if (src && !video.src && !video.querySelector('source')) {
                const source = document.createElement('source');
                source.src  = src;
                source.type = 'video/mp4';
                video.appendChild(source);
                video.load();
            }

            if (placeholder) placeholder.classList.add('d-none');
            video.classList.remove('d-none');
            video.play();
        });
    });
});
</script>
@endsection
