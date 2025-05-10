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
    /**
     * Start streaming selected videos.
     */
    /**
     * Start streaming selected videos.
     */
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

        // Periksa dependensi sistem
        $ffmpegPath = shell_exec('which ffmpeg');
        $tmuxPath = shell_exec('which tmux');
        if (!$ffmpegPath || !$tmuxPath) {
            return redirect()->route('stream.index')->with('error', 'FFmpeg atau tmux tidak terinstal di server! FFmpeg: ' . ($ffmpegPath ?: 'not found') . ', Tmux: ' . ($tmuxPath ?: 'not found'));
        }

        try {
            $videos = Video::whereIn('id', $request->videos)
                ->where('user_id', auth()->id())
                ->get();

            if ($videos->isEmpty()) {
                return redirect()->route('stream.index')->with('error', 'Tidak ada video valid yang dipilih!');
            }

            // Gunakan disk 'public' untuk video paths
            $videoPaths = $videos->pluck('path')->map(fn($path) => Storage::disk('public')->path($path))->toArray();

            // Verifikasi file video ada
            foreach ($videoPaths as $videoPath) {
                if (!file_exists($videoPath)) {
                    return redirect()->route('stream.index')->with('error', 'File video tidak ditemukan: ' . $videoPath);
                }
            }

            $scriptPath = base_path('scripts/stream.sh');
            $logFile = storage_path('logs/stream.log');
            $youtubeKey = $setting->youtube_key;
            $tmuxTmpDir = base_path('storage/tmux');
            $videoDir = storage_path('app/public/videos');

            // Buat direktori tmux jika belum ada
            if (!file_exists($tmuxTmpDir)) {
                if (!mkdir($tmuxTmpDir, 0700, true)) {
                    return redirect()->route('stream.index')->with('error', 'Gagal membuat direktori tmux!');
                }
                file_put_contents(storage_path('logs/tmux.log'), date('Y-m-d H:i:s') . ": Created tmux directory\n", FILE_APPEND);
            }

            // Buat direktori scripts jika belum ada
            if (!file_exists(dirname($scriptPath))) {
                if (!mkdir(dirname($scriptPath), 0755, true)) {
                    return redirect()->route('stream.index')->with('error', 'Gagal membuat direktori scripts!');
                }
                file_put_contents(storage_path('logs/tmux.log'), date('Y-m-d H:i:s') . ": Created scripts directory\n", FILE_APPEND);
            }

            // Buat script bash
            $scriptContent = <<<EOD
#!/bin/bash

LOGFILE="$logFile"
VIDEO_DIR="$videoDir"

if [ ! -d "\$VIDEO_DIR" ]; then
    echo "\$(date): ERROR: Direktori video \$VIDEO_DIR tidak ditemukan" >> "\$LOGFILE"
    exit 1
fi

while true; do
    shopt -s nullglob
    VIDEO_FILES=("\$VIDEO_DIR"/*.mp4)
    if [ \${#VIDEO_FILES[@]} -eq 0 ]; then
        echo "\$(date): WARNING: Tidak ada file .mp4 di \$VIDEO_DIR, menunggu 10 detik" >> "\$LOGFILE"
        sleep 10
        continue
    fi
    for f in "\${VIDEO_FILES[@]}"; do
        echo "\$(date): Memulai streaming \$f" >> "\$LOGFILE"
        ffmpeg -re -i "\$f" -c:v copy -c:a copy -f flv "rtmp://a.rtmp.youtube.com/live2/$youtubeKey" >> "\$LOGFILE" 2>&1
        if [ \$? -ne 0 ]; then
            echo "\$(date): ERROR saat streaming \$f" >> "\$LOGFILE"
        else
            echo "\$(date): Selesai streaming \$f" >> "\$LOGFILE"
        fi
    done
done
EOD;

            if (!file_put_contents($scriptPath, $scriptContent)) {
                return redirect()->route('stream.index')->with('error', 'Gagal menulis stream.sh!');
            }
            if (!chmod($scriptPath, 0755)) {
                return redirect()->route('stream.index')->with('error', 'Gagal mengatur izin stream.sh!');
            }
            file_put_contents(storage_path('logs/tmux.log'), date('Y-m-d H:i:s') . ": Created and set permissions for stream.sh\n", FILE_APPEND);

            // Set environment variables dan path
            $envCommand = "export TMUX_TMPDIR=$tmuxTmpDir; export PATH=\$PATH:/usr/local/bin:/usr/bin:/bin;";

            // Mulai tmux session dengan environment yang benar
            $sessionName = 'stream_' . auth()->id();
            shell_exec("$envCommand tmux kill-session -t $sessionName 2>/dev/null");

            $tmuxCommand = "$envCommand tmux new-session -d -s $sessionName '$scriptPath' 2>&1";
            $tmuxOutput = shell_exec($tmuxCommand);

            file_put_contents(storage_path('logs/tmux.log'), date('Y-m-d H:i:s') . ": Tmux command: $tmuxCommand\n", FILE_APPEND);
            if ($tmuxOutput) {
                file_put_contents(storage_path('logs/tmux.log'), date('Y-m-d H:i:s') . ": Tmux output: $tmuxOutput\n", FILE_APPEND);
            }

            // Verifikasi apakah tmux session berjalan
            sleep(3);
            $checkCommand = "$envCommand tmux has-session -t $sessionName 2>&1";
            $tmuxStatus = shell_exec($checkCommand);

            file_put_contents(storage_path('logs/tmux.log'), date('Y-m-d H:i:s') . ": Tmux check command: $checkCommand\n", FILE_APPEND);
            file_put_contents(storage_path('logs/tmux.log'), date('Y-m-d H:i:s') . ": Tmux status check output: $tmuxStatus\n", FILE_APPEND);

            if ($tmuxStatus === null || strpos($tmuxStatus, 'no server running') !== false || strpos($tmuxStatus, 'error') !== false) {
                return redirect()->route('stream.index')->with('error', 'Gagal memulai tmux session! Log: ' . ($tmuxOutput ?: 'No tmux output'));
            }

            return redirect()->route('stream.index')->with('success', 'Streaming berhasil dimulai dan tmux session berjalan!');
        } catch (\Exception $e) {
            file_put_contents(storage_path('logs/tmux.log'), date('Y-m-d H:i:s') . ": Exception: " . $e->getMessage() . "\n", FILE_APPEND);
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
