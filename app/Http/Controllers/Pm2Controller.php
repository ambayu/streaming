<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Pm2Controller extends Controller
{
    public function startProcess()
    {
        // Tentukan path untuk PM2 dan script Node.js
        $pm2Path = '/usr/bin/pm2';
        $scriptPath = '/var/www/html/web/streaming/scripts/stream_1.js';
        $processName = 'my-node-app';

        // Jika perlu menambahkan variabel environment untuk PM2
        $env = [
            'PM2_HOME' => '/root/.pm2'  // Misalnya untuk menambahkan variabel lingkungan PM2_HOME
        ];

        // Menyiapkan perintah untuk menjalankan PM2 dengan script yang diinginkan
        $process = new Process([$pm2Path, 'start', $scriptPath, '--name', $processName], null, $env);
        $process->run();

        // Menangani jika proses gagal
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Mengembalikan respons JSON dengan status dan output dari proses
        return response()->json([
            'status' => 'success',
            'output' => $process->getOutput(),
        ]);
    }
}
