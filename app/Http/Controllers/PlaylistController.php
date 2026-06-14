<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Video;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $playlists = auth()->user()->playlists()->withCount('videos')->latest()->get();
        $videos    = auth()->user()->videos()->orderBy('order')->get();
        return view('playlists.index', compact('playlists', 'videos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        auth()->user()->playlists()->create([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('playlists.index')->with('success', 'Playlist berhasil dibuat!');
    }

    public function show(Playlist $playlist)
    {
        $this->authorize('view', $playlist);
        $playlist->load('videos');
        $allVideos = auth()->user()->videos()->orderBy('order')->get();
        return view('playlists.show', compact('playlist', 'allVideos'));
    }

    public function update(Request $request, Playlist $playlist)
    {
        $this->authorize('update', $playlist);
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $playlist->update([
            'name'        => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('playlists.show', $playlist)->with('success', 'Playlist diperbarui!');
    }

    public function destroy(Playlist $playlist)
    {
        $this->authorize('delete', $playlist);
        $playlist->delete();
        return redirect()->route('playlists.index')->with('success', 'Playlist dihapus!');
    }

    public function addVideo(Request $request, Playlist $playlist)
    {
        $this->authorize('update', $playlist);
        $request->validate([
            'video_id' => 'required|exists:videos,id',
        ]);

        $video = Video::where('id', $request->video_id)
                      ->where('user_id', auth()->id())
                      ->firstOrFail();

        // Cegah duplikat
        if (!$playlist->videos()->where('video_id', $video->id)->exists()) {
            $maxOrder = $playlist->videos()->max('playlist_video.order') ?? 0;
            $playlist->videos()->attach($video->id, ['order' => $maxOrder + 1]);
        }

        return redirect()->route('playlists.show', $playlist)->with('success', 'Video ditambahkan ke playlist!');
    }

    public function removeVideo(Playlist $playlist, Video $video)
    {
        $this->authorize('update', $playlist);
        $playlist->videos()->detach($video->id);
        return redirect()->route('playlists.show', $playlist)->with('success', 'Video dihapus dari playlist!');
    }

    public function reorder(Request $request, Playlist $playlist)
    {
        $this->authorize('update', $playlist);
        $request->validate([
            'order'   => 'required|array',
            'order.*' => 'exists:videos,id',
        ]);

        foreach ($request->order as $index => $videoId) {
            $playlist->videos()->updateExistingPivot($videoId, ['order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }
}
