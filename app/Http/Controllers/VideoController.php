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
            'video' => 'required|mimes:mp4|max:200480', // Max 20MB
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
}
