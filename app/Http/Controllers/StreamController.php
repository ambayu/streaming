<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class StreamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * DASHBOARD
     */
    public function index()
    {
        $setting = auth()->user()->streamSettings;
        $videos  = auth()->user()->videos()->orderBy('order')->get();

        $userId  = auth()->id();
        $pm2Name = 'stream_' . $userId;
        $pm2Path = '/usr/bin/pm2';

        $env = [
            'PM2_HOME' => '/var/www/.pm2',
            'HOME'     => '/var/www',
            'PATH'     => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin',
        ];

        // ── Cek streaming aktif ──────────────────────────────────────────
        // Primary: cek file now_playing (tidak memerlukan PM2 I/O)
        $nowPlayingFile = storage_path("app/now_playing_{$userId}.json");
        $isStreamingByFile = file_exists($nowPlayingFile)
            && (time() - filemtime($nowPlayingFile)) < 45;

        // Secondary: tanya PM2 (timeout pendek, dibungkus try/catch)
        $isStreamingByPm2 = false;
        try {
            $process = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 5);
            $process->run();
            $isStreamingByPm2 = $process->isSuccessful()
                && trim($process->getOutput()) !== '';
        } catch (\Throwable $e) {
            // timeout / not found — abaikan, gunakan file-based
        }

        $isStreaming = $isStreamingByFile || $isStreamingByPm2;

        // pm2Status tidak lagi ditampilkan di UI, skip eksekusi
        $pm2Status = '';

        // log streaming
        $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
        $streamLog = file_exists($logFile)
            ? shell_exec("tail -n 50 " . escapeshellarg($logFile))
            : '';

        $streamingVideos = Session::get('streaming_videos_' . auth()->id(), []);
        $invalidVideos   = Session::get('invalid_videos_' . auth()->id(), []);
        $validVideos     = Session::get('valid_videos_' . auth()->id(), []);

        return view('stream.index', compact(
            'setting',
            'videos',
            'isStreaming',
            'pm2Status',
            'streamLog',
            'streamingVideos',
            'invalidVideos',
            'validVideos'
        ));
    }

    /**
     * START STREAM 24 JAM NONSTOP (PLAYLIST LOOP + NEXT VIDEO)
     */
    public function start(Request $request)
    {
        $request->validate([
            'videos' => 'required|array|min:1',
            'videos.*' => 'exists:videos,id',
        ]);

        $setting = auth()->user()->streamSettings;
        if (!$setting || empty($setting->youtube_key)) {
            return back()->with('error', 'YouTube streaming key belum diatur!');
        }

        try {
            $videoIds = $request->videos;

            $videos = Video::whereIn('id', $videoIds)
                ->where('user_id', auth()->id())
                ->get()
                ->sortBy(fn($v) => array_search($v->id, $videoIds));

            $validVideos = [];
            $invalidVideos = [];

            foreach ($videos as $video) {
                $absolutePath = Storage::disk('public')->path($video->path);

                if (!file_exists($absolutePath)) {
                    $invalidVideos[] = $video->title . ' (file tidak ditemukan)';
                    continue;
                }

                if (!is_readable($absolutePath)) {
                    $invalidVideos[] = $video->title . ' (tidak bisa dibaca)';
                    continue;
                }

                $validVideos[] = [
                    'path'  => realpath($absolutePath),
                    'title' => $video->title
                ];
            }

            if (empty($validVideos)) {
                return back()->with('error', 'Semua video invalid / rusak!');
            }

            Session::put('invalid_videos_' . auth()->id(), $invalidVideos);
            Session::put('valid_videos_' . auth()->id(), $validVideos);
            Session::put('streaming_videos_' . auth()->id(), $validVideos);

            /**
             * PLAYLIST TXT (PATH + TITLE)
             */
            $playlistFile = storage_path("app/stream_playlist_" . auth()->id() . ".txt");
            $playlistLines = '';

            foreach ($validVideos as $v) {
                $escapedPath = str_replace("'", "'\\''", $v['path']);
                $title = str_replace("'", "", $v['title']);
                $playlistLines .= "file '{$escapedPath}'|{$title}\n";
            }

            file_put_contents($playlistFile, $playlistLines);

            /**
             * STREAM ENGINE SCRIPT
             */
            $scriptPath = base_path('scripts/stream_' . auth()->id() . '.sh');
            $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
            $youtubeKey = $setting->youtube_key;
            $nowPlayingFile = storage_path('app/now_playing_' . auth()->id() . '.json');
            $concatFile = storage_path("app/stream_concat_" . auth()->id() . ".txt");

            $scriptContent = <<<BASH
#!/bin/bash
LOGFILE="$logFile"
PLAYLIST="$playlistFile"
CONCAT_LIST="$concatFile"
RTMP_URL="rtmps://a.rtmps.youtube.com/live2/$youtubeKey"
NOW_FILE="$nowPlayingFile"

mkdir -p "\$(dirname "\$LOGFILE")"

echo "===============================" >> "\$LOGFILE"
echo "🚀 STREAM ENGINE START (24 JAM)" >> "\$LOGFILE"
echo "===============================" >> "\$LOGFILE"

# Build ffmpeg concat playlist (hanya butuh sekali)
> "\$CONCAT_LIST"
while IFS= read -r line; do
  RAW=\$(echo "\$line" | tr -d '\\r')
  FILE=\$(echo "\$RAW" | cut -d'|' -f1 | sed -E "s/^file '(.+)'\$/\\1/")
  [ -z "\$FILE" ] && continue
  if [ ! -f "\$FILE" ]; then
    echo "\$(date): ❌ FILE HILANG -> \$FILE" >> "\$LOGFILE"
    continue
  fi
  echo "file '\$FILE'" >> "\$CONCAT_LIST"
done < "\$PLAYLIST"

if [ ! -s "\$CONCAT_LIST" ]; then
  echo "\$(date): ❌ Concat playlist kosong, henti." >> "\$LOGFILE"
  exit 1
fi

# Loop luar: restart ffmpeg bila putus
while true; do
  echo "\$(date '+%Y-%m-%d %H:%M:%S'): 🔁 FFMPEG START (concat loop)" >> "\$LOGFILE"

  # Tulis now_playing berdasarkan video pertama di playlist
  FIRST_RAW=\$(head -n1 "\$PLAYLIST" | tr -d '\\r')
  FIRST_TITLE=\$(echo "\$FIRST_RAW" | cut -d'|' -f2)
  FIRST_FILE=\$(echo "\$FIRST_RAW" | cut -d'|' -f1 | sed -E "s/^file '(.+)'\$/\\1/")
  FIRST_DUR=\$(ffprobe -v error -show_entries format=duration -of csv=p=0 "\$FIRST_FILE" 2>/dev/null)
  [ -z "\$FIRST_DUR" ] && FIRST_DUR=0
  echo "{\\"title\\":\\"\$FIRST_TITLE\\",\\"start\\":\$(date +%s),\\"duration\\":\$FIRST_DUR}" > "\$NOW_FILE"

  ffmpeg -re -f concat -safe 0 -stream_loop -1 \\
    -avoid_negative_ts make_zero \\
    -fflags +genpts \\
    -i "\$CONCAT_LIST" \\
    -vf "scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2,fps=30" \\
    -c:v libx264 -preset veryfast -tune zerolatency \\
    -pix_fmt yuv420p \\
    -r 30 -g 60 -keyint_min 60 \\
    -b:v 1500k -maxrate 1800k -bufsize 3600k \\
    -x264-params "nal-hrd=cbr:force-cfr=1" \\
    -c:a aac -ar 44100 -b:a 128k -ac 2 \\
    -max_muxing_queue_size 9999 \\
    -threads 2 \\
    -f flv "\$RTMP_URL" >> "\$LOGFILE" 2>&1 &
  FFMPEG_PID=\$!

  # Keepalive: touch now_playing setiap 15 detik agar dashboard tahu streaming masih aktif
  ( while kill -0 \$FFMPEG_PID 2>/dev/null; do sleep 15; touch "\$NOW_FILE"; done ) &
  KEEPALIVE_PID=\$!

  wait \$FFMPEG_PID
  EXIT_CODE=\$?
  kill \$KEEPALIVE_PID 2>/dev/null

  echo "\$(date): ⚠ ffmpeg keluar (exit \$EXIT_CODE). Restart dalam 3 detik..." >> "\$LOGFILE"
  sleep 3
done
BASH;

            file_put_contents($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            // stop lama
            $this->stop(false);

            // start pm2
            $process = new Process(
                ['/usr/bin/pm2', 'start', $scriptPath, '--name', 'stream_' . auth()->id()],
                null,
                ['PM2_HOME' => '/var/www/.pm2']
            );
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return redirect()->route('stream.index')
                ->with('success', '🔥 Streaming 24 JAM NONSTOP dimulai!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * STOP STREAM
     */
    public function stop($redirect = true)
    {
        $userId = auth()->id();
        $pm2Name = 'stream_' . $userId;

        (new Process(['/usr/bin/pm2', 'delete', $pm2Name], null, [
            'PM2_HOME' => '/var/www/.pm2'
        ]))->run();

        // hapus now playing agar UI berhenti
        $nowPlayingFile = storage_path("app/now_playing_{$userId}.json");
        if (file_exists($nowPlayingFile)) {
            unlink($nowPlayingFile);
        }

        // hapus concat playlist
        $concatFile = storage_path("app/stream_concat_{$userId}.txt");
        if (file_exists($concatFile)) {
            unlink($concatFile);
        }

        Session::forget([
            'streaming_videos_' . $userId,
            'invalid_videos_' . $userId,
            'valid_videos_' . $userId
        ]);

        if ($redirect) {
            return redirect()->route('stream.index')
                ->with('success', 'Streaming dihentikan!');
        }
    }

    /**
     * LOG API — last N lines, JSON
     */
    public function streamLog()
    {
        $userId = auth()->id();
        $logFile = storage_path('logs/stream_' . $userId . '.log');

        if (!file_exists($logFile)) {
            return response()->json(['lines' => []]);
        }

        // Ambil 150 baris terakhir
        $lines = [];
        $fp = new \SplFileObject($logFile, 'r');
        $fp->seek(PHP_INT_MAX);
        $total = $fp->key();
        $start = max(0, $total - 150);
        $fp->seek($start);
        while (!$fp->eof()) {
            $line = rtrim($fp->current());
            if ($line !== '') {
                $lines[] = $line;
            }
            $fp->next();
        }

        return response()->json(['lines' => $lines]);
    }

    /**
     * NOW PLAYING API
     */
    public function nowPlaying()
    {
        $userId = auth()->id();

        if (!$userId) {
            return response()->json(['status' => 'idle']);
        }

        $file = storage_path("app/now_playing_{$userId}.json");

        if (!file_exists($file)) {
            return response()->json(['status' => 'idle']);
        }

        return response()->json(json_decode(file_get_contents($file), true));
    }
}
