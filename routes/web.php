<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Pm2Controller;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\StreamController;

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::resource('videos', VideoController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::get('videos/{video}/stream', [VideoController::class, 'stream'])->name('videos.stream');
    Route::get('stream', [StreamController::class, 'index'])->name('stream.index');
    Route::post('stream/key', [StreamController::class, 'storeKey'])->name('stream.storeKey');
    Route::post('stream/start', [StreamController::class, 'start'])->name('stream.start');
    Route::post('stream/stop', [StreamController::class, 'stop'])->name('stream.stop');
    Route::post('/stream/update-order', [StreamController::class, 'updateOrder'])->name('stream.updateOrder');
});

Route::get('/', fn() => redirect()->route('videos.index'));
Route::get('/home', fn() => redirect()->route('videos.index'));
Route::get('/pm2/start', [Pm2Controller::class, 'startProcess']);
