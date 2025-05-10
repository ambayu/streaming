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

    /**
     * Display a listing of the user's videos.
     */
    public function index()
    {
        $videos = auth()->user()->videos;
        return view('videos.index', compact('videos'));
    }

    /**
     * Show the form for creating a new video.
     */
    public function create()
    {
        return view('videos.create');
    }

    /**
     * Store a newly uploaded video.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'video' => 'required|mimes:mp4|max:20480', // Max 20MB
        ]);

        try {
            $path = $request->file('video')->store('videos', 'public');

            auth()->user()->videos()->create([
                'title' => $request->title,
                'path' => $path,
            ]);

            return redirect()->route('videos.index')->with('success', 'Video berhasil diunggah!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal mengunggah video: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the video title.
     */
    public function edit(Video $video)
    {
        // $this->authorize('update', $video);
        return view('videos.edit', compact('video'));
    }

    /**
     * Update the video title.
     */
    public function update(Request $request, Video $video)
    {
        // $this->authorize('update', $video);

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

    /**
     * Remove the video from storage and database.
     */
    public function destroy(Video $video)
    {
        // $this->authorize('delete', $video);

        try {
            Storage::disk('public')->delete($video->path);
            $video->delete();

            return redirect()->route('videos.index')->with('success', 'Video berhasil dihapus!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus video: ' . $e->getMessage());
        }
    }
}
