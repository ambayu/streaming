<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $videos = auth()->user()->videos;
        return view('videos.index', compact('videos'));
    }

    public function create()
    {
        return view('videos.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'video' => 'required|mimes:mp4', // Max 20MB
        ]);

        try {
            $path = $request->file('video')->store('videos', 'public');

            auth()->user()->videos()->create([
                'title' => $request->title,
                'path' => $path,
            ]);

            $response = [
                'success' => true,
                'message' => 'Video berhasil diunggah!',
                'redirect' => route('videos.index')
            ];

            if ($request->expectsJson()) {
                return response()->json($response);
            }

            return redirect()->route('videos.index')->with('success', 'Video berhasil diunggah!');
        } catch (\Exception $e) {
            $response = [
                'success' => false,
                'message' => 'Gagal mengunggah video: ' . $e->getMessage()
            ];

            if ($request->expectsJson()) {
                return response()->json($response, 500);
            }

            return redirect()->back()->with('error', 'Gagal mengunggah video: ' . $e->getMessage());
        }
    }

    public function edit(Video $video)
    {
        return view('videos.edit', compact('video'));
    }

    public function update(Request $request, Video $video)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        try {
            $video->update([
                'title' => $request->title,
            ]);

            return redirect()->route('videos.index')->with('success', 'Judul video berhasil diperbarui!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui judul: ' . $e->getMessage());
        }
    }

    public function destroy(Video $video)
    {
        try {
            Storage::disk('public')->delete($video->path);
            $video->delete();

            return redirect()->route('videos.index')->with('success', 'Video berhasil dihapus!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus video: ' . $e->getMessage());
        }
    }

    public function stream(Video $video)
    {
        // Bypass auth check untuk video stream (browser video player tidak kirim cookie)
        // abort_if($video->user_id !== auth()->id(), 403);

        $path = Storage::disk('public')->path($video->path);

        if (!file_exists($path)) {
            \Log::error('Video file not found: ' . $path);
            abort(404, 'File video tidak ditemukan.');
        }

        $size = filesize($path);
        $mimeType = 'video/mp4';

        \Log::info('Streaming video: ' . $path . ' Size: ' . $size);

        $headers = [
            'Content-Type'              => $mimeType,
            'Content-Length'            => $size,
            'Content-Disposition'       => 'inline; filename="' . basename($path) . '"',
            'Accept-Ranges'             => 'bytes',
            'Cache-Control'             => 'public, max-age=86400',
            'X-Content-Type-Options'    => 'nosniff',
        ];

        // Dukung HTTP Range Request (agar video bisa di-seek)
        $request = request();
        if ($request->hasHeader('Range')) {
            $range = $request->header('Range');
            preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);

            $start = (int) $matches[1];
            $end   = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $size - 1;
            $end   = min($end, $size - 1);
            $length = $end - $start + 1;

            $headers['Content-Range']  = "bytes {$start}-{$end}/{$size}";
            $headers['Content-Length'] = $length;

            $stream = fopen($path, 'rb');
            fseek($stream, $start);

            return response()->stream(function () use ($stream, $length) {
                $remaining = $length;
                while (!feof($stream) && $remaining > 0) {
                    $chunk = min(8192, $remaining);
                    echo fread($stream, $chunk);
                    $remaining -= $chunk;
                    flush();
                }
                fclose($stream);
            }, 206, $headers);
        }

        return response()->file($path, $headers);
    }
}
