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

        // Cek status PM2
        $pm2Status = shell_exec("pm2 pid stream_" . auth()->id());
        $isStreaming = !empty($pm2Status);

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

        // Periksa dependensi sistem
        $ffmpegPath = shell_exec('which ffmpeg');
        $pm2Path = shell_exec('which pm2');
        if (!$ffmpegPath || !$pm2Path) {
            return redirect()->route('stream.index')->with('error', 'FFmpeg atau PM2 tidak terinstal di server! FFmpeg: ' . ($ffmpegPath ?: 'not found') . ', PM2: ' . ($pm2Path ?: 'not found'));
        }

        try {
            $videos = Video::whereIn('id', $request->videos)
                ->where('user_id', auth()->id())
                ->get();

            if ($videos->isEmpty()) {
                return redirect()->route('stream.index')->with('error', 'Tidak ada video valid yang dipilih!');
            }

            // Verifikasi file video ada
            foreach ($videos as $video) {
                $videoPath = Storage::disk('public')->path($video->path);
                if (!file_exists($videoPath)) {
                    return redirect()->route('stream.index')->with('error', 'File video tidak ditemukan: ' . $videoPath);
                }
            }

            $scriptPath = base_path('scripts/stream.js');
            $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
            $youtubeKey = $setting->youtube_key;

            // Buat direktori scripts jika belum ada
            if (!file_exists(dirname($scriptPath))) {
                if (!mkdir(dirname($scriptPath), 0755, true)) {
                    return redirect()->route('stream.index')->with('error', 'Gagal membuat direktori scripts!');
                }
            }

            // Buat script Node.js untuk PM2
            $videoPaths = $videos->pluck('path')->map(fn($path) => Storage::disk('public')->path($path))->toArray();

            $scriptContent = <<<EOD
const { exec } = require('child_process');
const fs = require('fs');

const YT_KEY = "$youtubeKey";
const LOG_FILE = "$logFile";
const VIDEOS = JSON.parse(`$videoPaths`);

function log(message) {
    const timestamp = new Date().toISOString();
    fs.appendFileSync(LOG_FILE, `[\${timestamp}] \${message}\n`);
}

async function streamVideo(videoPath) {
    return new Promise((resolve, reject) => {
        log(`Memulai streaming \${videoPath}`);

        const ffmpegCmd = \`ffmpeg -re -i "\${videoPath}" -c:v copy -c:a copy -f flv "rtmp://a.rtmp.youtube.com/live2/\${YT_KEY}"\`;

        exec(ffmpegCmd, (error, stdout, stderr) => {
            if (error) {
                log(\`ERROR streaming \${videoPath}: \${error.message}\`);
                return reject(error);
            }
            log(\`Selesai streaming \${videoPath}\`);
            resolve();
        });
    });
}

async function main() {
    try {
        log('Streaming process started');

        while (true) {
            for (const video of VIDEOS) {
                if (!fs.existsSync(video)) {
                    log(\`WARNING: File \${video} tidak ditemukan\`);
                    continue;
                }

                try {
                    await streamVideo(video);
                } catch (err) {
                    log(\`ERROR: \${err.message}\`);
                }
            }

            // Tunggu 10 detik sebelum loop berikutnya
            await new Promise(resolve => setTimeout(resolve, 10000));
        }
    } catch (err) {
        log(\`FATAL ERROR: \${err.message}\`);
        process.exit(1);
    }
}

main();
EOD;

            // Tulis script
            if (!file_put_contents($scriptPath, $scriptContent)) {
                return redirect()->route('stream.index')->with('error', 'Gagal menulis stream.js!');
            }

            // Hentikan proses sebelumnya jika ada
            $this->stop();

            // Mulai proses dengan PM2
            $pm2Name = 'stream_' . auth()->id();
            $pm2Command = "pm2 start $scriptPath --name $pm2Name --log $logFile --no-autorestart";
            $pm2Output = shell_exec($pm2Command);

            // Verifikasi proses berjalan
            sleep(2);
            $pm2Status = shell_exec("pm2 pid $pm2Name");
            if (empty($pm2Status)) {
                return redirect()->route('stream.index')->with('error', 'Gagal memulai streaming dengan PM2! Output: ' . ($pm2Output ?: 'Tidak ada output'));
            }

            return redirect()->route('stream.index')->with('success', 'Streaming berhasil dimulai dengan PM2!');
        } catch (\Exception $e) {
            return redirect()->route('stream.index')->with('error', 'Gagal memulai streaming: ' . $e->getMessage());
        }
    }

    public function stop()
    {
        try {
            $pm2Name = 'stream_' . auth()->id();
            $pm2Status = shell_exec("pm2 pid $pm2Name");

            if (!empty($pm2Status)) {
                shell_exec("pm2 delete $pm2Name");
            }

            return redirect()->route('stream.index')->with('success', 'Streaming berhasil dihentikan!');
        } catch (\Exception $e) {
            return redirect()->route('stream.index')->with('error', 'Gagal menghentikan streaming: ' . $e->getMessage());
        }
    }
}
