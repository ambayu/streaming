<?php

namespace App\Http\Controllers;

use App\Models\StreamSetting;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StreamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $setting = auth()->user()->streamSettings;
        $videos = auth()->user()->videos;

        $sessionName = 'stream_' . auth()->id();
        $tmuxStatus = shell_exec("tmux has-session -t $sessionName 2>/dev/null");
        $isStreaming = $tmuxStatus !== null ? true : false;

        return view('stream.index', compact('setting', 'videos', 'isStreaming'));
    }

    public function storeKey(Request $request)
    {
        $request->validate([
            'youtube_key' => 'required|string|regex:/^[a-zA-Z0-9\-_]+$/',
        ]);

        try {
            auth()->user()->streamSettings()->updateOrCreate(
                ['user_id' => auth()->id()],
                ['youtube_key' => $request->youtube_key]
            );

            return redirect()->route('stream.index')->with('success', 'YouTube key berhasil disimpan!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan YouTube key: ' . $e->getMessage());
        }
    }

    public function start(Request $request)
    {
        $request->validate([
            'videos' => 'required|array|min:1',
            'videos.*' => 'exists:videos,id',
        ]);

        $setting = auth()->user()->streamSettings;
        if (!$setting) {
            return redirect()->route('stream.index')->with('error', 'Masukkan YouTube key terlebih dahulu!');
        }

        if (!shell_exec('which ffmpeg') || !shell_exec('which tmux')) {
            return redirect()->route('stream.index')->with('error', 'FFmpeg atau tmux tidak terinstal di server!');
        }

        try {
            $videos = Video::whereIn('id', $request->videos)
                ->where('user_id', auth()->id())
                ->get();

            if ($videos->isEmpty()) {
                return redirect()->route('stream.index')->with('error', 'Tidak ada video valid yang dipilih!');
            }

            $videoPaths = $videos->pluck('path')->map(fn($path) => Storage::path($path))->toArray();

            $scriptPath = base_path('scripts/stream.sh');
            $logFile = storage_path('logs/stream.log');
            $youtubeKey = $setting->youtube_key;

            if (!file_exists(dirname($scriptPath))) {
                mkdir(dirname($scriptPath), 0755, true);
            }

            $videoList = implode(' ', array_map('escapeshellarg', $videoPaths));
            $scriptContent = <<<EOD
#!/bin/bash

LOGFILE="$logFile"
VIDEO_FILES=($videoList)

if [ \${#VIDEO_FILES[@]} -eq 0 ]; then
    echo "\$(date): ERROR: Tidak ada video untuk distream" >> "\$LOGFILE"
    exit 1
fi

while true; do
    for f in "\${VIDEO_FILES[@]}"; do
        echo "\$(date): Memulai streaming \$f" >> "\$LOGFILE"
        if [ ! -f "\$f" ]; then
            echo "\$(date): ERROR: File tidak ditemukan \$f" >> "\$LOGFILE"
            continue
        fi
        ffmpeg -re -i "\$f" -c:v copy -c:a copy -f flv "rtmp://a.rtmp.youtube.com/live2/$youtubeKey" >> "\$LOGFILE" 2>&1
        if [ \$? -ne 0 ]; then
            echo "\$(date): ERROR saat streaming \$f" >> "\$LOGFILE"
        else
            echo "\$(date): Selesai streaming \$f" >> "\$LOGFILE"
        fi
    done
done
EOD;

            file_put_contents($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            $sessionName = 'stream_' . auth()->id();
            shell_exec("tmux kill-session -t $sessionName 2>/dev/null");
            shell_exec("tmux new-session -d -s $sessionName '$scriptPath'");

            sleep(1);
            $tmuxStatus = shell_exec("tmux has-session -t $sessionName 2>/dev/null");
            if ($tmuxStatus === null) {
                return redirect()->route('stream.index')->with('error', 'Gagal memulai tmux session!');
            }

            return redirect()->route('stream.index')->with('success', 'Streaming berhasil dimulai!');
        } catch (\Exception $e) {
            return redirect()->route('stream.index')->with('error', 'Gagal memulai streaming: ' . $e->getMessage());
        }
    }

    public function stop()
    {
        try {
            $sessionName = 'stream_' . auth()->id();
            shell_exec("tmux kill-session -t $sessionName 2>/dev/null");
            return redirect()->route('stream.index')->with('success', 'Streaming berhasil dihentikan!');
        } catch (\Exception $e) {
            return redirect()->route('stream.index')->with('error', 'Gagal menghentikan streaming: ' . $e->getMessage());
        }
    }
}
