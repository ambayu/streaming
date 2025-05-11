<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Pm2Controller extends Controller
{
    public function startProcess()
    {
        $pm2Path = '/usr/bin/pm2';
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
