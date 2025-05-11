<?php

namespace App\Http\Controllers;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Http\Request;

class Pm2Controller extends Controller
{
    public function startProcess()
    {
        $scriptPath = '/var/www/html/web/streaming/scripts/stream_1.js'; // path file node
        $processName = 'my-node-app';                // nama proses di PM2

        $process = new Process(["pm2", "start", $scriptPath, "--name", $processName]);
        $process->run();

        // Cek apakah berhasil
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return response()->json([
            'status' => 'success',
            'output' => $process->getOutput(),
        ]);
    }
}
