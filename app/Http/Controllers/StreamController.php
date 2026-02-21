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

        $streamingVideos = Session::get('streaming_videos_' . auth()->id(), []);

        return view('stream.index', compact('setting', 'videos', 'isStreaming', 'pm2Status', 'streamLog', 'streamingVideos'));
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

pm2  controllernya
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Pm2Controller extends Controller
{
    public function startProcess()
    {
        // Tentukan path untuk PM2 dan script Node.js
        $pm2Path = '/usr/bin/pm2';
        $scriptPath = '/var/www/html/web/streaming/scripts/stream_1.js';
        $processName = 'ul ul ul';

        // Gunakan direktori yang dapat diakses oleh user web server
        $env = [
            'PM2_HOME' => '/var/www/.pm2' // Direktori yang writable oleh www-data
        ];

        // Menyiapkan perintah untuk menjalankan PM2
        $process = new Process([$pm2Path, 'start', $scriptPath, '--name', $processName], null, $env);
        $process->run();

        // Menangani jika proses gagal
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Mengembalikan respons JSON dengan status dan output
        return response()->json([
            'status' => 'success',
            'output' => $process->getOutput(),
        ]);
    }
}
vidio controllernya
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

    public function thumbnail(Video $video)
    {
        $videoPath = Storage::disk('public')->path($video->path);

        if (!file_exists($videoPath)) {
            abort(404);
        }

        // Direktori cache thumbnail
        $thumbDir  = Storage::disk('public')->path('thumbnails');
        $thumbPath = $thumbDir . '/' . $video->id . '.jpg';

        // Buat direktori jika belum ada
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        // Generate thumbnail jika belum ada (menggunakan FFmpeg)
        if (!file_exists($thumbPath)) {
            $ffmpeg = trim(shell_exec('which ffmpeg 2>/dev/null') ?: '');

            if (!empty($ffmpeg)) {
                // Ambil frame di detik ke-3 (atau detik ke-1 jika video pendek)
                $cmd = escapeshellcmd($ffmpeg)
                    . ' -y -i ' . escapeshellarg($videoPath)
                    . ' -ss 00:00:03'
                    . ' -vframes 1'
                    . ' -vf "scale=320:-1"'
                    . ' -q:v 5'
                    . ' ' . escapeshellarg($thumbPath)
                    . ' 2>/dev/null';
                exec($cmd);

                // Jika detik ke-3 gagal (video terlalu pendek), coba detik ke-1
                if (!file_exists($thumbPath)) {
                    $cmd = escapeshellcmd($ffmpeg)
                        . ' -y -i ' . escapeshellarg($videoPath)
                        . ' -ss 00:00:01'
                        . ' -vframes 1'
                        . ' -vf "scale=320:-1"'
                        . ' -q:v 5'
                        . ' ' . escapeshellarg($thumbPath)
                        . ' 2>/dev/null';
                    exec($cmd);
                }
            }

            // Jika FFmpeg tidak ada atau gagal, return placeholder SVG
            if (!file_exists($thumbPath)) {
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180" viewBox="0 0 320 180">'
                     . '<rect width="320" height="180" fill="#1a1a2e"/>'
                     . '<polygon points="120,60 120,120 200,90" fill="#6c757d"/>'
                     . '</svg>';
                return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
            }
        }

        return response()->file($thumbPath, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}