<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateThumbnails extends Command
{
    protected $signature   = 'videos:thumbnails {--force : Regenerate semua thumbnail meskipun sudah ada}';
    protected $description = 'Generate thumbnail dari semua video menggunakan FFmpeg';

    public function handle()
    {
        $ffmpeg = trim(shell_exec('which ffmpeg 2>/dev/null') ?: '');

        if (empty($ffmpeg)) {
            $this->error('FFmpeg tidak ditemukan! Install dengan: sudo apt install ffmpeg');
            return 1;
        }

        $this->info("FFmpeg ditemukan: {$ffmpeg}");

        $thumbDir = Storage::disk('public')->path('thumbnails');
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
            $this->info("Direktori thumbnails dibuat: {$thumbDir}");
        }

        $videos = Video::all();
        $total  = $videos->count();

        if ($total === 0) {
            $this->warn('Tidak ada video di database.');
            return 0;
        }

        $this->info("Memproses {$total} video...");

        $bar     = $this->output->createProgressBar($total);
        $success = 0;
        $failed  = 0;
        $skipped = 0;

        foreach ($videos as $video) {
            $videoPath = Storage::disk('public')->path($video->path);
            $thumbPath = $thumbDir . '/' . $video->id . '.jpg';

            if (!file_exists($videoPath)) {
                $this->newLine();
                $this->warn("  [SKIP] File tidak ditemukan: {$video->path}");
                $failed++;
                $bar->advance();
                continue;
            }

            if (file_exists($thumbPath) && !$this->option('force')) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // Coba ambil frame di detik ke-3
            $this->generateFrame($ffmpeg, $videoPath, $thumbPath, '00:00:03');

            // Fallback ke detik ke-1
            if (!file_exists($thumbPath)) {
                $this->generateFrame($ffmpeg, $videoPath, $thumbPath, '00:00:01');
            }

            // Fallback ke frame pertama
            if (!file_exists($thumbPath)) {
                $this->generateFrame($ffmpeg, $videoPath, $thumbPath, '00:00:00');
            }

            if (file_exists($thumbPath)) {
                $success++;
            } else {
                $this->newLine();
                $this->error("  [FAIL] Gagal generate thumbnail: {$video->title}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Selesai! Berhasil: {$success} | Dilewati: {$skipped} | Gagal: {$failed}");

        return 0;
    }

    private function generateFrame(string $ffmpeg, string $videoPath, string $thumbPath, string $timestamp): void
    {
        $cmd = escapeshellcmd($ffmpeg)
            . ' -y'
            . ' -i ' . escapeshellarg($videoPath)
            . ' -ss ' . $timestamp
            . ' -vframes 1'
            . ' -vf "scale=480:-1"'
            . ' -q:v 4'
            . ' ' . escapeshellarg($thumbPath)
            . ' 2>/dev/null';

        exec($cmd);
    }
}
