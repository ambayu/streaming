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

    // Verify YouTube key exists
    $setting = auth()->user()->streamSettings;
    if (!$setting || empty($setting->youtube_key)) {
        return redirect()->route('stream.index')->with('error', 'YouTube streaming key belum diatur!');
    }

    // Check system dependencies
    $ffmpegCheck = shell_exec('which ffmpeg 2>&1');
    $tmuxCheck = shell_exec('which tmux 2>&1');

    if (empty($ffmpegCheck)) {
        return redirect()->route('stream.index')->with('error', 'FFmpeg tidak terinstall!');
    }
    if (empty($tmuxCheck)) {
        return redirect()->route('stream.index')->with('error', 'Tmux tidak terinstall!');
    }

    try {
        // Get selected videos
        $videos = Video::whereIn('id', $request->videos)
                    ->where('user_id', auth()->id())
                    ->get();

        if ($videos->isEmpty()) {
            return redirect()->route('stream.index')->with('error', 'Tidak ada video yang valid dipilih!');
        }

        // Prepare paths
        $scriptPath = base_path('scripts/stream.sh');
        $logFile = storage_path('logs/stream_'.auth()->id().'.log');
        $tmuxTmpDir = storage_path('tmux');
        $sessionName = 'stream_'.auth()->id();

        // Create necessary directories
        if (!file_exists(dirname($scriptPath))) {
            mkdir(dirname($scriptPath), 0755, true);
        }
        if (!file_exists($tmuxTmpDir)) {
            mkdir($tmuxTmpDir, 0700, true);
        }

        // Build video file list
        $videoFiles = [];
        foreach ($videos as $video) {
            $path = Storage::disk('public')->path($video->path);
            if (!file_exists($path)) {
                throw new \Exception("File video tidak ditemukan: ".$video->path);
            }
            $videoFiles[] = $path;
        }

        // Create streaming script
        $scriptContent = <<<EOD
#!/bin/bash
# Streaming script generated at $(date)

LOGFILE="$logFile"
YT_KEY="{$setting->youtube_key}"
VIDEOS=(
    "$(implode('"'."\n    \"", $videoFiles))"
)

echo "\$(date) - Memulai streaming" >> "\$LOGFILE"

for video in "\${VIDEOS[@]}"; do
    if [ ! -f "\$video" ]; then
        echo "\$(date) - ERROR: File \$video tidak ditemukan" >> "\$LOGFILE"
        continue
    fi

    echo "\$(date) - Processing \$video" >> "\$LOGFILE"

    ffmpeg -re -i "\$video" \
        -c:v copy -c:a copy \
        -f flv "rtmp://a.rtmp.youtube.com/live2/\$YT_KEY" \
        2>> "\$LOGFILE"

    if [ \$? -ne 0 ]; then
        echo "\$(date) - ERROR: Gagal streaming \$video" >> "\$LOGFILE"
    else
        echo "\$(date) - Selesai streaming \$video" >> "\$LOGFILE"
    fi
done

echo "\$(date) - Semua video selesai diproses" >> "\$LOGFILE"
EOD;

        file_put_contents($scriptPath, $scriptContent);
        chmod($scriptPath, 0755);

        // Prepare environment
        $envVars = [
            'TMUX_TMPDIR' => $tmuxTmpDir,
            'HOME' => storage_path(),
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'TERM' => 'xterm'
        ];

        $envString = '';
        foreach ($envVars as $key => $value) {
            $envString .= "export $key=\"$value\"; ";
        }

        // Kill existing session
        $killCmd = "{$envString} tmux kill-session -t {$sessionName} 2>&1";
        shell_exec($killCmd);

        // Start new session
        $startCmd = "{$envString} tmux new-session -d -s {$sessionName} '{$scriptPath}' 2>&1";
        $output = shell_exec($startCmd);

        // Verify session
        sleep(2);
        $checkCmd = "{$envString} tmux has-session -t {$sessionName} 2>&1";
        $sessionStatus = shell_exec($checkCmd);

        // Log everything
        $debugLog = storage_path('logs/stream_debug.log');
        file_put_contents($debugLog, "\n=== NEW STREAM ATTEMPT ===\n", FILE_APPEND);
        file_put_contents($debugLog, "Time: ".date('Y-m-d H:i:s')."\n", FILE_APPEND);
        file_put_contents($debugLog, "Kill Command: {$killCmd}\n", FILE_APPEND);
        file_put_contents($debugLog, "Start Command: {$startCmd}\n", FILE_APPEND);
        file_put_contents($debugLog, "Command Output: ".($output ?: 'NULL')."\n", FILE_APPEND);
        file_put_contents($debugLog, "Check Command: {$checkCmd}\n", FILE_APPEND);
        file_put_contents($debugLog, "Session Status: ".($sessionStatus ?: 'NULL')."\n", FILE_APPEND);

        if (empty($sessionStatus)) {
            throw new \Exception("Tmux session tidak berhasil dimulai. Tidak ada output dari status check.");
        }

        if (strpos($sessionStatus, 'error') !== false || strpos($sessionStatus, 'fail') !== false) {
            throw new \Exception("Tmux melaporkan error: ".$sessionStatus);
        }

        return redirect()->route('stream.index')
               ->with('success', 'Streaming berhasil dimulai!')
               ->with('debug', nl2br(file_get_contents($debugLog)));

    } catch (\Exception $e) {
        $errorLog = storage_path('logs/stream_error.log');
        file_put_contents($errorLog, date('Y-m-d H:i:s')." - Error: ".$e->getMessage()."\n", FILE_APPEND);

        return redirect()->route('stream.index')
               ->with('error', 'Gagal memulai streaming: '.$e->getMessage())
               ->with('debug', nl2br(file_get_contents($debugLog ?? $errorLog)));
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
