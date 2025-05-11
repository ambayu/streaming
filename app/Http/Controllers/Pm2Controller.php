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

        // Gunakan direktori yang dapat diakses oleh user web server
        $env = [
            'PM2_HOME' => '/var/www/.pm2' // Direktori yang writable oleh www-data
        ];

        // Menyiapkan perintah untuk menjalankan PM2
        $process = new Process([$pm2Path, 'start', $scriptPath, '--name', $processName], null, $env);
        $process->run();

        // Menangani jika proses gagal
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        // Mengembalikan respons JSON dengan status dan output
        return response()->json([
            'status' => 'success',
            'output' => $process->getOutput(),
        ]);
    }
}
