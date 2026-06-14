<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Video;
use Illuminate\Http\Request;
use App\Models\StreamSetting;
use App\Services\YouTubeAutomationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Exception\ProcessFailedException;

class StreamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['nowPlaying']);
    }

    public function index()
    {
        $setting = auth()->user()->streamSettings;
        $videos = auth()->user()->videos()->orderBy('order')->get();
        $playlists = auth()->user()->playlists()->withCount('videos')->latest()->get();

        $pm2Name = 'stream_' . auth()->id();
        $pm2Path = '/usr/bin/pm2';
        $env = [
            'PM2_HOME' => '/var/www/.pm2',
            'PATH' => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin'
        ];

        $process = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 60);
        $process->run();

        $isStreaming = $process->isSuccessful() && !empty(trim($process->getOutput()));

        $pm2StatusProcess = new Process([$pm2Path, 'jlist'], null, $env, null, 60);
        $pm2StatusProcess->run();
        $pm2Processes = [];
        if ($pm2StatusProcess->isSuccessful()) {
            $pm2Processes = $this->parsePm2Processes($pm2StatusProcess->getOutput());
        }

        $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
        $streamLog = 'Log file tidak ditemukan. Periksa izin atau proses streaming.';
        $errorLog = '';
        $playingLine = '';
        if (file_exists($logFile)) {
            $streamLog = shell_exec("tail -n 50 " . escapeshellarg($logFile));

            // ambil semua baris yang mengandung kata "error" agar log bersifat persisten sampai kita menghapus manual
            // sebelumnya hanya menampilkan 20 baris terakhir, sekarang seluruh riwayat kesalahan ditampilkan
            $errorLog = shell_exec("grep -a -i -n 'error' " . escapeshellarg($logFile));

            // ambil baris streaming terakhir untuk tampilkan video sedang dimainkan
            $lastStreamLine = trim(shell_exec("grep -a -i 'Streaming ' " . escapeshellarg($logFile) . " | tail -n 1"));
            if ($lastStreamLine) {
                if (preg_match('/Streaming\s+(.*)$/i', $lastStreamLine, $m)) {
                    $path = trim($m[1]);
                    $basename = basename($path);
                    $video = Video::where('path', 'like', "%{$basename}%")->first();
                    $playingLine = $video ? $video->title : $basename;
                } else {
                    $playingLine = $lastStreamLine;
                }
            }
        }

        // informasi VPS: load, memori, disk
        $loadavg = trim(shell_exec("cut -d ' ' -f1-3 /proc/loadavg"));
        $meminfo = trim(shell_exec("free -h | awk 'NR==2{print \$3\"/\"\$2\" used (\"\$4\" free)\"}'"));
        $diskinfo = trim(shell_exec("df -h / | tail -1 | awk '{print \\$3\"/\"\\$2\" used (\\$5\" used)\"}'"));

        $streamingVideos = Session::get('streaming_videos_' . auth()->id(), []);

        return view('stream.index', compact(
            'setting', 'videos', 'playlists', 'isStreaming',
            'pm2Processes', 'streamLog', 'errorLog', 'playingLine',
            'streamingVideos', 'loadavg', 'meminfo', 'diskinfo'
        ));
    }

    protected function parsePm2Processes(string $output): array
    {
        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(function ($process) {
                $pm2Env = Arr::get($process, 'pm2_env', []);

                return [
                    'name' => $process['name'] ?? '-',
                    'status' => $pm2Env['status'] ?? '-',
                    'pid' => $process['pid'] ?? '-',
                    'uptime' => $this->formatPm2Uptime($pm2Env['pm_uptime'] ?? null),
                    'cpu' => isset($process['monit']['cpu']) ? $process['monit']['cpu'] . '%' : '-',
                    'memory' => isset($process['monit']['memory']) ? $this->formatBytes((int) $process['monit']['memory']) : '-',
                    'restarts' => $pm2Env['restart_time'] ?? 0,
                    'mode' => $pm2Env['exec_mode'] ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    protected function formatPm2Uptime($pmUptime): string
    {
        if (empty($pmUptime)) {
            return '-';
        }

        $seconds = max(0, (int) floor((round(microtime(true) * 1000) - (int) $pmUptime) / 1000));

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60) . 'm';
        }

        if ($seconds < 86400) {
            return floor($seconds / 3600) . 'h';
        }

        return floor($seconds / 86400) . 'd';
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
    }

    public function youtube()
    {
        $setting = auth()->user()->streamSettings;

        return view('stream.youtube', compact('setting'));
    }

    public function clearErrors()
    {
        $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
        return redirect()->route('stream.index')->with('success', 'Error log telah dibersihkan');
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

    public function storeYoutubeConnection(Request $request)
    {
        $request->validate([
            'google_email' => 'required|email|max:255',
            'youtube_channel_id' => 'nullable|string|max:255',
        ]);

        try {
            $setting = auth()->user()->streamSettings;
            if (!$setting) {
                return redirect()->route('stream.index')
                    ->with('error', 'Simpan YouTube stream key terlebih dahulu sebelum mengatur koneksi akun Google.');
            }

            $setting->update([
                'google_email' => $request->google_email,
                'youtube_channel_id' => $request->youtube_channel_id,
            ]);

            return redirect()->route('stream.index')->with('success', 'Koneksi akun YouTube berhasil disimpan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan koneksi YouTube: ' . $e->getMessage());
        }
    }

    public function storeYoutubeCookies(Request $request)
    {
        $request->validate([
            'youtube_cookies' => 'required|file|mimetypes:application/json,text/plain|max:2048',
        ]);

        try {
            $setting = auth()->user()->streamSettings;
            if (!$setting) {
                return redirect()->route('stream.index')
                    ->with('error', 'Simpan YouTube stream key terlebih dahulu sebelum mengunggah cookie YouTube.');
            }

            $path = $request->file('youtube_cookies')->storeAs(
                'youtube-cookies',
                'user_' . auth()->id() . '.json',
                'local'
            );

            $setting->update([
                'youtube_cookie_path' => $path,
                'youtube_connected_at' => now(),
                'youtube_last_prepare_status' => 'cookies_uploaded',
                'youtube_last_prepare_message' => 'Cookie berhasil diunggah dan siap dipakai untuk automasi YouTube.',
            ]);

            return redirect()->route('stream.index')->with('success', 'Cookie YouTube berhasil diunggah.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal mengunggah cookie YouTube: ' . $e->getMessage());
        }
    }

    public function prepareYoutube(YouTubeAutomationService $youTubeAutomationService)
    {
        $setting = auth()->user()->streamSettings;

        if (!$setting || empty($setting->google_email)) {
            return redirect()->route('stream.index')
                ->with('error', 'Email Google belum disimpan. Lengkapi koneksi YouTube terlebih dahulu.');
        }

        $result = $youTubeAutomationService->prepare($setting);

        if (!($result['success'] ?? false)) {
            return redirect()->route('stream.index')
                ->with('error', $result['message'] ?? 'Gagal menyiapkan YouTube Go Live.');
        }

        return redirect()->route('stream.index')
            ->with('success', $result['message'] ?? 'YouTube Live Control Room berhasil disiapkan.');
    }

    public function startFromPlaylist(Request $request, YouTubeAutomationService $youTubeAutomationService)
    {
        $request->validate(['playlist_id' => 'required|exists:playlists,id']);

        $playlist = Playlist::where('id', $request->playlist_id)
                            ->where('user_id', auth()->id())
                            ->with('videos')
                            ->firstOrFail();

        if ($playlist->videos->isEmpty()) {
            return redirect()->route('stream.index')->with('error', 'Playlist kosong! Tambahkan video ke playlist terlebih dahulu.');
        }

        // Inject video IDs ordered by pivot order, then delegate to start()
        $request->merge(['videos' => $playlist->videos->pluck('id')->toArray()]);
        return $this->start($request, $youTubeAutomationService);
    }

    public function start(Request $request, YouTubeAutomationService $youTubeAutomationService)
    {
        $request->validate([
            'videos' => 'required|array|min:1',
            'videos.*' => 'exists:videos,id',
        ]);

        $setting = auth()->user()->streamSettings;
        if (!$setting || empty($setting->youtube_key)) {
            return redirect()->route('stream.index')->with('error', 'YouTube streaming key belum diatur!');
        }

        if (!empty($setting->google_email)) {
            $youtubeResult = $youTubeAutomationService->prepare($setting);
            if (!($youtubeResult['success'] ?? false)) {
                return redirect()->route('stream.index')
                    ->with('error', 'Gagal membuka YouTube Go Live sebelum streaming: ' . ($youtubeResult['message'] ?? 'Unknown error'));
            }
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

            // Update database streaming status
            $setting->update([
                'is_active' => true,
                'last_playlist_id' => $request->input('playlist_id'),
                'last_video_ids' => $videoIds,
            ]);

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

            $videoList = implode(' ', array_map('escapeshellarg', $videoPaths));

            $scriptContent = <<<EOD
#!/bin/bash

LOGFILE="$logFile"
YOUTUBE_KEY="$youtubeKey"
VIDEOS=($videoList)

mkdir -p "\$(dirname "\$LOGFILE")"
chown www-data:www-data "\$(dirname "\$LOGFILE")"
chmod 755 "\$(dirname "\$LOGFILE")"

# Trap signals to clean up the running FFmpeg process
trap 'kill \$FFMPEG_PID 2>/dev/null; exit 0' SIGINT SIGTERM EXIT

while true; do
  for f in "\${VIDEOS[@]}"; do
    echo "\$(date): Streaming \$f" >> "\$LOGFILE"

    # Stream Copy (menggunakan codec asli video/audio untuk menghemat CPU VPS)
    ffmpeg -re -i "\$f" -c:v copy -c:a copy -loglevel warning -flvflags no_duration_filesize -f flv "rtmps://a.rtmps.youtube.com/live2/\$YOUTUBE_KEY" >>"\$LOGFILE" 2>&1 &
    FFMPEG_PID=\$!
    wait \$FFMPEG_PID

    EXIT_CODE=\$?
    if [ \$EXIT_CODE -ne 0 ]; then
      echo "\$(date): ERROR streaming \$f (exit code: \$EXIT_CODE)" >> "\$LOGFILE"
      echo "\$(date): Menunggu 5 detik sebelum video berikutnya..." >> "\$LOGFILE"
      sleep 5
    else
      echo "\$(date): Finished \$f" >> "\$LOGFILE"
    fi
  done
  echo "\$(date): Semua video selesai. Mengulang playlist dalam 5 detik..." >> "\$LOGFILE"
  sleep 5
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
                [$pm2Path, 'start', $scriptPath, '--name', $pm2Name, '--log', $logFile, '--output', $logFile, '--error', $logFile, '--no-autorestart'],
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
                ->with('success', 'Streaming berhasil dimulai!')
                ->with('debug', 'Proses ID: ' . trim($checkProcess->getOutput()));
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

    public function stop($userId = null)
    {
        try {
            $targetUserId = $userId ?: auth()->id();
            if (!$targetUserId) {
                throw new \Exception("User ID tidak valid.");
            }

            $pm2Name = 'stream_' . $targetUserId;
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

            // Update database streaming status
            $user = \App\Models\User::find($targetUserId);
            if ($user && $user->streamSettings) {
                $user->streamSettings->update(['is_active' => false]);
            }

            Session::forget('streaming_videos_' . $targetUserId);

            if (request()->expectsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('stream.index')->with('success', 'Streaming berhasil dihentikan!');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
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

    public function nowPlaying(Request $request)
    {
        $userId = (int) $request->query('user_id', auth()->id() ?? 0);

        if ($userId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'user_id tidak valid.',
            ], 422);
        }

        $pm2Name = 'stream_' . $userId;
        $pm2Path = '/usr/bin/pm2';
        $env = [
            'PM2_HOME' => '/var/www/.pm2',
            'PATH' => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin'
        ];

        $pidProcess = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 30);
        $pidProcess->run();
        $isStreaming = $pidProcess->isSuccessful() && !empty(trim($pidProcess->getOutput()));

        $logFile = storage_path('logs/stream_' . $userId . '.log');
        $playingTitle = null;
        $playingPath = null;
        $videoId = null;
        $lastStreamLine = null;

        if (file_exists($logFile)) {
            $lastStreamLine = trim(shell_exec("grep -a -i 'Streaming ' " . escapeshellarg($logFile) . " | tail -n 1"));

            if (!empty($lastStreamLine) && preg_match('/Streaming\s+(.*)$/i', $lastStreamLine, $m)) {
                $playingPath = trim($m[1]);
                $basename = basename($playingPath);

                $video = Video::where('user_id', $userId)
                    ->where('path', 'like', "%{$basename}%")
                    ->first();

                if ($video) {
                    $videoId = $video->id;
                    $playingTitle = $video->title;
                } else {
                    $playingTitle = $basename;
                }
            }
        }

        return response()->json([
            'success' => true,
            'user_id' => $userId,
            'is_streaming' => $isStreaming,
            'now_playing' => $playingTitle,
            'video_id' => $videoId,
            'video_path' => $playingPath,
            'log_line' => $lastStreamLine,
            'log_file_exists' => file_exists($logFile),
        ]);
    }
}
