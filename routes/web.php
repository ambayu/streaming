<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Pm2Controller;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\StreamController;

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::resource('videos', VideoController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::get('stream', [StreamController::class, 'index'])->name('stream.index');
    Route::post('stream/key', [StreamController::class, 'storeKey'])->name('stream.storeKey');
    Route::post('stream/start', [StreamController::class, 'start'])->name('stream.start');
    Route::post('stream/stop', [StreamController::class, 'stop'])->name('stream.stop');
    Route::post('/stream/update-order', [StreamController::class, 'updateOrder'])->name('stream.updateOrder');
});

// Route video stream & thumbnail tanpa auth (untuk browser video player & img tag)
Route::get('videos/{video}/stream', [VideoController::class, 'stream'])->name('videos.stream');
Route::get('videos/{video}/thumbnail', [VideoController::class, 'thumbnail'])->name('videos.thumbnail');
Route::get('/stream/now-playing', [StreamController::class, 'nowPlaying'])
    ->name('stream.nowPlaying');

Route::get('/', fn() => redirect()->route('videos.index'));
Route::get('/home', fn() => redirect()->route('videos.index'));
Route::get('/pm2/start', [Pm2Controller::class, 'startProcess']);
