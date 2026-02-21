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

        $pm2Name = 'stream_' . auth()->id();
        $pm2Path = '/usr/bin/pm2';

        $env = [
            'PM2_HOME' => '/var/www/.pm2',
            'PATH' => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin'
        ];

        // cek streaming aktif
        $process = new Process([$pm2Path, 'pid', $pm2Name], null, $env, null, 10);
        $process->run();
        $isStreaming = $process->isSuccessful() && trim($process->getOutput()) !== '';

        // status pm2
        $pm2StatusProcess = new Process([$pm2Path, 'list'], null, $env, null, 10);
        $pm2StatusProcess->run();
        $pm2Status = $pm2StatusProcess->isSuccessful()
            ? trim($pm2StatusProcess->getOutput())
            : 'Tidak ada proses PM2 aktif';

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
     * START STREAM 24 JAM NONSTOP
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

            $validPaths = [];
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

                $validPaths[] = realpath($absolutePath);
            }

            if (empty($validPaths)) {
                return back()->with('error', 'Semua video invalid / rusak!');
            }

            Session::put('invalid_videos_' . auth()->id(), $invalidVideos);
            Session::put('valid_videos_' . auth()->id(), $validPaths);

            $streamingVideos = collect($videos)
                ->filter(
                    fn($v) =>
                    in_array(realpath(Storage::disk('public')->path($v->path)), $validPaths)
                )
                ->map(fn($v) => [
                    'id' => $v->id,
                    'title' => $v->title,
                    'path' => $v->path
                ])->values()->toArray();

            Session::put('streaming_videos_' . auth()->id(), $streamingVideos);

            /**
             * PLAYLIST TXT
             */
            $playlistFile = storage_path("app/stream_playlist_" . auth()->id() . ".txt");
            $playlistLines = '';
            foreach ($validPaths as $vpath) {
                $escaped = str_replace("'", "'\\''", $vpath);
                $playlistLines .= "file '{$escaped}'\n";
            }
            file_put_contents($playlistFile, $playlistLines);

            /**
             * ENGINE 24 JAM NONSTOP + NOW PLAYING REALTIME
             */
            $scriptPath = base_path('scripts/stream_' . auth()->id() . '.sh');
            $logFile = storage_path('logs/stream_' . auth()->id() . '.log');
            $youtubeKey = $setting->youtube_key;
            $nowPlayingFile = storage_path('app/now_playing_' . auth()->id() . '.json');

            $scriptContent = <<<BASH
#!/bin/bash
LOGFILE="$logFile"
PLAYLIST="$playlistFile"
RTMP_URL="rtmps://a.rtmps.youtube.com/live2/$youtubeKey"
NOW_FILE="$nowPlayingFile"

# Auto create log folder
mkdir -p "\$(dirname "\$LOGFILE")"

echo "\$(date): ENGINE START 24 JAM NONSTOP" >> "\$LOGFILE"

while true; do
  echo "\$(date): LOOP PLAYLIST..." >> "\$LOGFILE"

  while IFS= read -r line; do
    FILE=\$(echo "\$line" | sed -E "s/^file '(.*)'$/\\1/")

    if [ ! -f "\$FILE" ]; then
      echo "\$(date): [SKIP] File hilang -> \$FILE" >> "\$LOGFILE"
      continue
    fi

    BASENAME=\$(basename "\$FILE")
    DURATION=\$(ffprobe -v error -show_entries format=duration -of csv=p=0 "\$FILE")
    if [ -z "\$DURATION" ]; then
      DURATION=0
    fi
    START_TIME=\$(date +%s)

    echo "{\\"file\\":\\"\$BASENAME\\",\\"start\\":\$START_TIME,\\"duration\\":\$DURATION}" > "\$NOW_FILE"

    echo "\$(date): [PLAYING] \$FILE" >> "\$LOGFILE"

    ffmpeg -re -i "\$FILE" \\
      -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 \\
      -c:v libx264 -preset veryfast -tune zerolatency \\
      -r 30 -g 60 -keyint_min 60 \\
      -b:v 3500k -maxrate 3500k -bufsize 7000k \\
      -c:a aac -ar 44100 -b:a 128k \\
      -f flv "\$RTMP_URL" >> "\$LOGFILE" 2>&1

    echo "\$(date): [END VIDEO]" >> "\$LOGFILE"
    sleep 2
  done < "\$PLAYLIST"

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
        $pm2Name = 'stream_' . auth()->id();

        (new Process(['/usr/bin/pm2', 'delete', $pm2Name], null, [
            'PM2_HOME' => '/var/www/.pm2'
        ]))->run();

        Session::forget([
            'streaming_videos_' . auth()->id(),
            'invalid_videos_' . auth()->id(),
            'valid_videos_' . auth()->id()
        ]);

        if ($redirect) {
            return redirect()->route('stream.index')->with('success', 'Streaming dihentikan!');
        }
    }

    /**
     * NOW PLAYING API (Realtime Progress)
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
