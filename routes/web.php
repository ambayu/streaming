<?php

use App\Http\Controllers\StreamController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Auth::routes();

Route::middleware('auth')->group(function () {
    Route::resource('videos', VideoController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::get('stream', [StreamController::class, 'index'])->name('stream.index');
    Route::post('stream/key', [StreamController::class, 'storeKey'])->name('stream.storeKey');
    Route::post('stream/start', [StreamController::class, 'start'])->name('stream.start');
    Route::post('stream/stop', [StreamController::class, 'stop'])->name('stream.stop');
});

Route::get('/', fn() => redirect()->route('videos.index'));
Route::get('/home', fn() => redirect()->route('videos.index'));
