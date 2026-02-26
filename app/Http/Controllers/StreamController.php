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
            'setting', 'videos', 'isStreaming',
            'pm2Status', 'streamLog', 'errorLog', 'playingLine',
            'streamingVideos', 'loadavg', 'meminfo', 'diskinfo'
        ));
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

            $videoList = implode(' ', array_map('escapeshellarg', $videoPaths));

            $scriptContent = <<<EOD
#!/bin/bash

LOGFILE="$logFile"
YOUTUBE_KEY="$youtubeKey"
VIDEOS=($videoList)

mkdir -p "\$(dirname "\$LOGFILE")"
chown www-data:www-data "\$(dirname "\$LOGFILE")"
chmod 755 "\$(dirname "\$LOGFILE")"

while true; do
  for f in "\${VIDEOS[@]}"; do
    echo "\$(date): Streaming \$f" >> "\$LOGFILE"

    # run with nice/ionice, single thread, minimal logging
    nice -n 19 ionice -c2 -n7 \
      ffmpeg -re -i "\$f" -threads 1 \
             -c:v copy -c:a copy -loglevel error \
             -flvflags no_duration_filesize -f flv "rtmps://a.rtmps.youtube.com/live2/\$YOUTUBE_KEY" \
      >>"\$LOGFILE" 2>&1

    if [ \$? -ne 0 ]; then
      echo "\$(date): ERROR streaming \$f" >> "\$LOGFILE"
    else
      echo "\$(date): Finished \$f" >> "\$LOGFILE"
    fi
  done
  echo "\$(date): Menunggu 10 detik sebelum loop berikutnya..." >> "\$LOGFILE"
  sleep 10
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
