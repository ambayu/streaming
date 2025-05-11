<?php

namespace App\Http\Controllers;

use App\Models\StreamSetting;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
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
        $videos = auth()->user()->videos;

        // Cek status PM2 sebagai www-data
        $pm2Name = 'stream_' . auth()->id();
        $pm2Path = '/usr/bin/pm2';
        $env = ['PM2_HOME' => '/var/www/.pm2'];

        $process = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 60);
        $process->run();

        $isStreaming = $process->isSuccessful() && !empty(trim($process->getOutput()));

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
        if (!$setting || empty($setting->youtube_key)) {
            return redirect()->route('stream.index')->with('error', 'YouTube streaming key belum diatur!');
        }

        // Periksa dependensi sistem
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
            // Get selected videos
            $videos = Video::whereIn('id', $request->videos)
                ->where('user_id', auth()->id())
                ->get();

            if ($videos->isEmpty()) {
                return redirect()->route('stream.index')->with('error', 'Tidak ada video yang valid dipilih!');
            }

            // Prepare paths
            $scriptPath = base_path('scripts/stream_' . auth()->id() . '.sh');
            $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
            $youtubeKey = $setting->youtube_key;

            // Buat direktori scripts jika belum ada
            if (!file_exists(dirname($scriptPath))) {
                if (!mkdir(dirname($scriptPath), 0755, true)) {
                    throw new \Exception("Gagal membuat direktori scripts!");
                }
            }

            // Verifikasi dan persiapkan path video
            $videoPaths = [];
            foreach ($videos as $video) {
                $path = Storage::disk('public')->path($video->path);
                if (!file_exists($path)) {
                    throw new \Exception("File video tidak ditemukan: " . $video->path);
                }
                $videoPaths[] = $path;
            }

            // Buat string daftar video untuk script
            $videoList = implode(' ', array_map('escapeshellarg', $videoPaths));

            // Buat script shell
            $scriptContent = <<<EOD
#!/bin/bash

LOGFILE="$logFile"
YOUTUBE_KEY="$youtubeKey"
VIDEOS=($videoList)

while true; do
  for f in "\${VIDEOS[@]}"; do
    echo "\$(date): Streaming \$f" >> "\$LOGFILE"

    ffmpeg -re -i "\$f" -c:v copy -c:a copy -f flv "rtmp://a.rtmp.youtube.com/live2/\$YOUTUBE_KEY"

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

            // Tulis script file dan atur izin
            if (file_put_contents($scriptPath, $scriptContent) === false) {
                throw new \Exception("Gagal menulis file script!");
            }
            if (!chmod($scriptPath, 0755)) {
                throw new \Exception("Gagal mengatur izin file script!");
            }
            // Pastikan kepemilikan file benar
            chown($scriptPath, 'www-data');
            chgrp($scriptPath, 'www-data');

            // Hentikan proses sebelumnya jika ada
            $this->stop();

            // Mulai proses dengan PM2 sebagai www-data
            $pm2Name = 'stream_' . auth()->id();
            $pm2Path = '/usr/bin/pm2';
            $env = [
                'PM2_HOME' => '/var/www/.pm2',
                'PATH' => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin' // Pastikan bash ada di PATH
            ];

            $process = new Process(
                [$pm2Path, 'start', $scriptPath, '--name', $pm2Name, '--log', $logFile, '--no-autorestart'],
                null,
                $env,
                null,
                60
            );
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Verifikasi proses berjalan
            sleep(2); // Beri waktu untuk proses start
            $checkProcess = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 60);
            $checkProcess->run();

            // Log untuk debugging
            $debugLog = [
                'time' => date('Y-m-d H:i:s'),
                'command' => implode(' ', [$pm2Path, 'start', $scriptPath, '--name', $pm2Name, '--log', $logFile, '--no-autorestart']),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
                'status_check' => $checkProcess->getOutput(),
                'script_path' => $scriptPath,
                'log_file' => $logFile
            ];
            file_put_contents(storage_path('logs/stream_debug.log'), json_encode($debugLog, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

            if (!$checkProcess->isSuccessful() || empty(trim($checkProcess->getOutput()))) {
                throw new \Exception("Gagal memulai PM2 process. Output: " . ($process->getOutput() ?: 'Tidak ada output'));
            }

            return redirect()->route('stream.index')
                ->with('success', 'Streaming berhasil dimulai!')
                ->with('debug', 'Proses ID: ' . trim($checkProcess->getOutput()));
        } catch (\Exception $e) {
            // Log error detail
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
            $env = ['PM2_HOME' => '/var/www/.pm2'];

            // Cek apakah proses ada
            $checkProcess = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 60);
            $checkProcess->run();

            if ($checkProcess->isSuccessful() && !empty(trim($checkProcess->getOutput()))) {
                // Hentikan dan hapus proses
                $deleteProcess = new Process([$pm2Path, 'delete', $pm2Name], null, $env, null, 60);
                $deleteProcess->run();

                if (!$deleteProcess->isSuccessful()) {
                    throw new ProcessFailedException($deleteProcess);
                }
            }

            return redirect()->route('stream.index')->with('success', 'Streaming berhasil dihentikan!');
        } catch (\Exception $e) {
            return redirect()->route('stream.index')->with('error', 'Gagal menghentikan streaming: ' . $e->getMessage());
        }
    }
}
