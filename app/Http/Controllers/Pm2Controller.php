<?php

namespace App\Http\Controllers;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Http\Request;

class Pm2Controller extends Controller
{
    public function startPm2Process()
    {
        $pm2Path = '/root/.nvm/versions/node/v16.20.0/bin/pm2'; // full path ke pm2
        $scriptPath = '/var/www/html/web/streaming/scripts/stream_1.js';
        $processName = 'my-node-app';

        $process = new Process([$pm2Path, 'start', $scriptPath, '--name', $processName]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return response()->json([
            'status' => 'success',
            'output' => $process->getOutput(),
        ]);
    }
}
