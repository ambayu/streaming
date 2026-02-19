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
                                    <video width="100%" height="180" controls class="rounded mb-3" preload="metadata">
                                        <source src="{{ route('videos.stream', $video) }}" type="video/mp4">
                                        Browser Anda tidak mendukung tag video.
                                    </video>
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="{{ route('videos.edit', $video) }}" class="btn btn-sm btn-warning">Edit Judul</a>
                                        <form action="{{ route('videos.destroy', $video) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus video {{ $video->title }}?');">
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
