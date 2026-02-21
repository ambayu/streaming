<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use App\Models\StreamSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;

class StreamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $setting = auth()->user()->streamSettings;
        $videos = auth()->user()->videos()->orderBy('order')->get();

        $pm2Name = 'stream_' . auth()->id();
        $pm2Path = '/usr/bin/pm2';
        $env = [
            'PM2_HOME' => '/var/www/.pm2',
            'PATH' => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin'
        ];

        $process = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 60);
        $process->run();

        $isStreaming = $process->isSuccessful() && !empty(trim($process->getOutput()));

        $pm2StatusProcess = new Process([$pm2Path, 'list'], null, $env, null, 60);
        $pm2StatusProcess->run();
        $pm2Status = $pm2StatusProcess->isSuccessful() ? trim($pm2StatusProcess->getOutput()) : 'Tidak ada proses PM2 aktif';

        $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
        $streamLog = '';
        if (file_exists($logFile)) {
            $streamLog = shell_exec("tail -n 50 " . escapeshellarg($logFile));
        } else {
            $streamLog = "Log file tidak ditemukan. Periksa izin atau proses streaming.";
        }

        $errorLogFile = storage_path('logs/stream_error.log');
        $errorLog = '';
        if (file_exists($errorLogFile)) {
            $errorLog = shell_exec("tail -n 50 " . escapeshellarg($errorLogFile));
        }

        $ffmpegRunning = false;
        if ($isStreaming) {
            $playlistFile = "/tmp/stream_playlist_" . auth()->id() . ".txt";
            $checkFfmpeg = shell_exec("ps aux | grep ffmpeg | grep " . escapeshellarg($playlistFile) . " | grep -v grep");
            $ffmpegRunning = !empty(trim($checkFfmpeg));
        }

        $streamingVideos = Session::get('streaming_videos_' . auth()->id(), []);

        return view('stream.index', compact('setting', 'videos', 'isStreaming', 'pm2Status', 'streamLog', 'errorLog', 'ffmpegRunning', 'streamingVideos'));
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
        if (!$setting || empty($setting->youtube_key)) {
            return redirect()->route('stream.index')->with('error', 'YouTube streaming key belum diatur!');
        }

        $ffmpegCheck = shell_exec('which ffmpeg 2>&1');
        $pm2Check = shell_exec('which pm2 2>&1');

        if (empty($ffmpegCheck)) {
            return redirect()->route('stream.index')
                ->with('error', 'FFmpeg tidak terinstall! Install dengan: sudo apt install ffmpeg');
        }
        if (empty($pm2Check)) {
            return redirect()->route('stream.index')
                ->with('error', 'PM2 tidak terinstall! Install dengan: sudo npm install pm2 -g');
        }

        try {
            $videoIds = $request->videos;
            $videos = Video::whereIn('id', $videoIds)
                ->where('user_id', auth()->id())
                ->get()
                ->sortBy(function ($video) use ($videoIds) {
                    return array_search($video->id, $videoIds);
                });

            if ($videos->isEmpty()) {
                return redirect()->route('stream.index')->with('error', 'Tidak ada video yang valid dipilih!');
            }

            $streamingVideos = $videos->map(function ($video) {
                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'path' => $video->path,
                ];
            })->toArray();
            Session::put('streaming_videos_' . auth()->id(), $streamingVideos);

            $scriptPath = base_path('scripts/stream_' . auth()->id() . '.sh');
            $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
            $youtubeKey = $setting->youtube_key;

            if (!file_exists(dirname($scriptPath))) {
                if (!mkdir(dirname($scriptPath), 0755, true)) {
                    throw new \Exception("Gagal membuat direktori scripts!");
                }
            }

            $videoPaths = [];
            foreach ($videos as $video) {
                $path = Storage::disk('public')->path($video->path);
                if (!file_exists($path)) {
                    throw new \Exception("File video tidak ditemukan: " . $video->path);
                }
                $videoPaths[] = $path;
            }

            $userId = auth()->id();
            $playlistFile = "/tmp/stream_playlist_{$userId}.txt";

            // Buat isi playlist concat untuk ffmpeg
            $playlistLines = '';
            foreach ($videoPaths as $vpath) {
                $escaped = str_replace("'", "'\\''", $vpath);
                $playlistLines .= "file '{$escaped}'\n";
            }

            $scriptContent = <<<EOD
#!/bin/bash

LOGFILE="$logFile"
YOUTUBE_KEY="$youtubeKey"
PLAYLIST="$playlistFile"
RTMP_URL="rtmps://a.rtmps.youtube.com/live2/\$YOUTUBE_KEY"

mkdir -p "\$(dirname "\$LOGFILE")"

# Buat file playlist concat ffmpeg
cat > "\$PLAYLIST" << 'PLAYLIST_EOF'
$playlistLines
PLAYLIST_EOF

echo "\$(date): [INFO] Playlist dibuat: \$PLAYLIST" >> "\$LOGFILE"
echo "\$(date): [INFO] Memulai streaming ke YouTube..." >> "\$LOGFILE"

while true; do
  echo "\$(date): [START] Menjalankan FFmpeg dengan concat loop..." >> "\$LOGFILE"

  ffmpeg -y \
    -re \
    -f concat \
    -safe 0 \
    -stream_loop -1 \
    -i "\$PLAYLIST" \
    -c:v copy \
    -c:a aac \
    -ar 44100 \
    -b:a 128k \
    -flvflags no_duration_filesize \
    -f flv \
    "\$RTMP_URL" >> "\$LOGFILE" 2>&1

  EXIT_CODE=\$?
  echo "\$(date): [WARN] FFmpeg berhenti (exit code: \$EXIT_CODE). Restart dalam 3 detik..." >> "\$LOGFILE"
  sleep 3
done
EOD;

            if (file_put_contents($scriptPath, $scriptContent) === false) {
                throw new \Exception("Gagal menulis file script!");
            }
            if (!chmod($scriptPath, 0755)) {
                throw new \Exception("Gagal mengatur izin file script!");
            }
            chown($scriptPath, 'www-data');
            chgrp($scriptPath, 'www-data');

            $this->stop();

            $pm2Name = 'stream_' . auth()->id();
            $pm2Path = '/usr/bin/pm2';
            $env = [
                'PM2_HOME' => '/var/www/.pm2',
                'PATH' => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin'
            ];

            $process = new Process(
                [$pm2Path, 'start', $scriptPath, '--name', $pm2Name, '--log', $logFile, '--output', $logFile, '--error', $logFile],
                null,
                $env,
                null,
                60
            );
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $checkProcess = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 60);
            $checkProcess->run();

            $debugLog = [
                'time' => date('Y-m-d H:i:s'),
                'command' => implode(' ', [$pm2Path, 'start', $scriptPath, '--name', $pm2Name, '--log', $logFile, '--output', $logFile, '--error', $logFile, '--no-autorestart']),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
                'status_check' => $checkProcess->getOutput(),
                'script_path' => $scriptPath,
                'log_file' => $logFile,
                'videos' => $streamingVideos
            ];
            file_put_contents(storage_path('logs/stream_debug.log'), json_encode($debugLog, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

            if (!$checkProcess->isSuccessful() || empty(trim($checkProcess->getOutput()))) {
                throw new \Exception("Gagal memulai PM2 process. Output: " . ($process->getOutput() ?: 'Tidak ada output'));
            }

            return redirect()->route('stream.index')
                ->with('success', 'Streaming berhasil dimulai! PM2 process ID: ' . trim($checkProcess->getOutput()))
                ->with('debug', 'Playlist: ' . count($videos) . ' video(s)');
        } catch (\Exception $e) {
            file_put_contents(
                storage_path('logs/stream_error.log'),
                date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n",
                FILE_APPEND
            );

            return redirect()->route('stream.index')
                ->with('error', 'Gagal memulai streaming: ' . $e->getMessage());
        }
    }

    public function stop()
    {
        try {
            $pm2Name = 'stream_' . auth()->id();
            $pm2Path = '/usr/bin/pm2';
            $env = [
                'PM2_HOME' => '/var/www/.pm2',
                'PATH' => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin'
            ];

            $checkProcess = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 60);
            $checkProcess->run();

            if ($checkProcess->isSuccessful() && !empty(trim($checkProcess->getOutput()))) {
                $deleteProcess = new Process([$pm2Path, 'delete', $pm2Name], null, $env, null, 60);
                $deleteProcess->run();

                if (!$deleteProcess->isSuccessful()) {
                    throw new ProcessFailedException($deleteProcess);
                }
            }

            Session::forget('streaming_videos_' . auth()->id());

            return redirect()->route('stream.index')->with('success', 'Streaming berhasil dihentikan!');
        } catch (\Exception $e) {
            return redirect()->route('stream.index')->with('error', 'Gagal menghentikan streaming: ' . $e->getMessage());
        }
    }

    public function clearErrorLog()
    {
        try {
            $errorLogFile = storage_path('logs/stream_error.log');
            if (file_exists($errorLogFile)) {
                unlink($errorLogFile);
            }
            return redirect()->route('stream.index')->with('success', 'Log error berhasil dihapus!');
        } catch (\Exception $e) {
            return redirect()->route('stream.index')->with('error', 'Gagal menghapus log error: ' . $e->getMessage());
        }
    }

    public function clearStreamLog()
    {
        try {
            $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
            if (file_exists($logFile)) {
                file_put_contents($logFile, ''); // Kosongkan isi file, jangan dihapus karena PM2 mungkin sedang menulis
            }
            return redirect()->route('stream.index')->with('success', 'Log streaming berhasil dibersihkan!');
        } catch (\Exception $e) {
            return redirect()->route('stream.index')->with('error', 'Gagal membersihkan log streaming: ' . $e->getMessage());
        }
    }


    public function updateOrder(Request $request)
    {
        Log::info('Update order request:', $request->all());

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'exists:videos,id',
        ]);

        try {
            DB::beginTransaction();
            foreach ($request->order as $index => $videoId) {
                Log::info('Updating video ID:', ['id' => $videoId, 'order' => $index + 1]);
                $updated = Video::where('id', $videoId)
                    ->where('user_id', auth()->id())
                    ->update(['order' => $index + 1]);

                if (!$updated) {
                    Log::warning('No rows updated for video ID:', ['id' => $videoId]);
                }
            }
            DB::commit();
            Log::info('Video order updated successfully');
            return response()->json(['success' => true, 'message' => 'Urutan video berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update order:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan urutan: ' . $e->getMessage()], 500);
        }
    }
}
