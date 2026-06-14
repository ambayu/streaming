<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StreamSetting;
use App\Models\Video;
use App\Models\Playlist;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class AutoRestartStream extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stream:auto-restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otomatis restart streaming jam 6 pagi dengan playlist/video terakhir yang diputar';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Log message to a dedicated file and console output.
     *
     * @param string $message
     * @param string $level
     * @return void
     */
    private function logMessage($message, $level = 'info')
    {
        $logFile = storage_path('logs/stream_auto_restart.log');
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [" . strtoupper($level) . "] $message\n";
        
        // Buat folder logs jika belum ada
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);

        if ($level === 'error') {
            $this->error($message);
        } elseif ($level === 'warning') {
            $this->warn($message);
        } else {
            $this->info($message);
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->logMessage("=== Memulai Proses Auto-Restart Streaming ===");

        // Cari semua setting streaming yang berstatus aktif
        $activeSettings = StreamSetting::where('is_active', true)->get();

        if ($activeSettings->isEmpty()) {
            $this->logMessage("Tidak ada stream aktif yang perlu di-restart.");
            return 0;
        }

        foreach ($activeSettings as $setting) {
            $userId = $setting->user_id;
            $this->logMessage("Memproses restart untuk User ID: $userId");

            // 1. Dapatkan daftar video terakhir
            $videos = collect();
            $source = 'manual';

            if ($setting->last_playlist_id) {
                $playlist = Playlist::with('videos')->find($setting->last_playlist_id);
                if ($playlist && $playlist->videos->isNotEmpty()) {
                    $videos = $playlist->videos->sortBy(function ($video) {
                        return $video->pivot->order ?? 0;
                    });
                    $source = "playlist: " . $playlist->name;
                }
            }

            if ($videos->isEmpty() && !empty($setting->last_video_ids)) {
                $videoIds = $setting->last_video_ids;
                if (is_array($videoIds)) {
                    $videos = Video::whereIn('id', $videoIds)
                        ->get()
                        ->sortBy(function ($video) use ($videoIds) {
                            return array_search($video->id, $videoIds);
                        });
                    $source = "pemilihan manual";
                }
            }

            if ($videos->isEmpty()) {
                $this->logMessage("Gagal restart User $userId: Tidak ada video yang valid ditemukan.", 'warning');
                continue;
            }

            $this->logMessage("Daftar video didapatkan dari $source. Jumlah video: " . $videos->count());

            // 2. Kumpulkan path video & validasi keberadaan file
            $videoPaths = [];
            $hasErrorFile = false;
            foreach ($videos as $video) {
                $path = Storage::disk('public')->path($video->path);
                if (!file_exists($path)) {
                    $this->logMessage("File video tidak ditemukan untuk User $userId di path: $path", 'error');
                    $hasErrorFile = true;
                    break;
                }
                $videoPaths[] = $path;
            }

            if ($hasErrorFile) {
                continue;
            }

            // 3. Tulis script bash streaming
            $scriptPath = base_path('scripts/stream_' . $userId . '.sh');
            $logFile = storage_path('logs/stream_' . $userId . '.log');
            $youtubeKey = $setting->youtube_key;

            if (!file_exists(dirname($scriptPath))) {
                if (!mkdir(dirname($scriptPath), 0755, true)) {
                    $this->logMessage("Gagal membuat direktori scripts untuk User $userId!", 'error');
                    continue;
                }
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

    # Encode ke H.264 + AAC (wajib untuk YouTube Live)
    ffmpeg -re -i "\$f" -c:v libx264 -preset veryfast -tune zerolatency -maxrate 2500k -bufsize 5000k -pix_fmt yuv420p -g 60 -c:a aac -b:a 128k -ar 44100 -loglevel warning -flvflags no_duration_filesize -f flv "rtmps://a.rtmps.youtube.com/live2/\$YOUTUBE_KEY" >>"\$LOGFILE" 2>&1

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
                $this->logMessage("Gagal menulis file script streaming untuk User $userId!", 'error');
                continue;
            }
            chmod($scriptPath, 0755);
            
            // Atur kepemilikan file script jika berjalan di VPS Linux
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                @chown($scriptPath, 'www-data');
                @chgrp($scriptPath, 'www-data');
            }

            // 4. Hentikan proses PM2 lama jika ada
            $pm2Name = 'stream_' . $userId;
            $pm2Path = '/usr/bin/pm2';
            $env = [
                'PM2_HOME' => '/var/www/.pm2',
                'PATH' => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin'
            ];

            // 5. Jalankan streaming PM2 dengan melakukan restart sebanyak 3 kali secara berurutan
            $started = false;
            $maxAttempts = 3;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $this->logMessage("Menjalankan restart PM2 untuk User $userId (Percobaan $attempt dari $maxAttempts)...");

                // Coba stop dan hapus proses PM2 yang sedang berjalan untuk percobaan ini
                $checkProcess = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 15);
                $checkProcess->run();

                if ($checkProcess->isSuccessful() && !empty(trim($checkProcess->getOutput()))) {
                    $this->logMessage("Menghentikan streaming sebelum memulai kembali...");
                    $deleteProcess = new Process([$pm2Path, 'delete', $pm2Name], null, $env, null, 30);
                    $deleteProcess->run();
                }

                $startProcess = new Process(
                    [$pm2Path, 'start', $scriptPath, '--name', $pm2Name, '--log', $logFile, '--output', $logFile, '--error', $logFile, '--no-autorestart'],
                    null,
                    $env,
                    null,
                    60
                );
                $startProcess->run();

                // Verifikasi keberhasilan
                $verifyProcess = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 15);
                $verifyProcess->run();

                if ($verifyProcess->isSuccessful() && !empty(trim($verifyProcess->getOutput()))) {
                    $started = true;
                    $this->logMessage("Stream berhasil dijalankan untuk User $userId pada percobaan ke-$attempt. PID: " . trim($verifyProcess->getOutput()));
                } else {
                    $started = false;
                    $this->logMessage("Gagal memulai PM2 untuk User $userId pada percobaan ke-$attempt. Error: " . $startProcess->getErrorOutput(), 'error');
                }

                if ($attempt < $maxAttempts) {
                    $this->logMessage("Menunggu 10 detik sebelum melakukan restart berikutnya...", 'warning');
                    sleep(10);
                }
            }

            if (!$started) {
                $this->logMessage("Gagal total menjalankan streaming untuk User $userId setelah $maxAttempts kali percobaan.", 'error');
            }
        }

        $this->logMessage("=== Proses Auto-Restart Selesai ===");
        return 0;
    }
}
