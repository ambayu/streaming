<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\StreamSetting;
use App\Models\Playlist;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;

class AutoRestartStreamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test command when there are no active streams.
     */
    public function test_command_does_nothing_if_no_active_streams()
    {
        $this->artisan('stream:auto-restart')
            ->expectsOutput('=== Memulai Proses Auto-Restart Streaming ===')
            ->expectsOutput('Tidak ada stream aktif yang perlu di-restart.')
            ->assertExitCode(0);
    }

    /**
     * Test command when there is an active stream with manual video selection.
     */
    public function test_command_restarts_active_manual_stream()
    {
        $user = User::factory()->create();
        
        $video1 = Video::create([
            'user_id' => $user->id,
            'title' => 'Video 1',
            'path' => 'video1.mp4',
            'order' => 1,
        ]);
        
        // Buat file palsu di storage fake
        Storage::disk('public')->put('video1.mp4', 'dummy content');

        $setting = StreamSetting::create([
            'user_id' => $user->id,
            'youtube_key' => 'test_youtube_key',
            'is_active' => true,
            'last_video_ids' => [$video1->id],
        ]);

        // Mock process execution if needed, but since we are running php -l/tests, 
        // we can run the command and catch the logs.
        // On local Windows dev, PM2 won't be installed or run, so we expect the log/command to log PM2 failure 
        // after attempting 5 times, but still exit cleanly or continue.
        
        $this->artisan('stream:auto-restart')
            ->expectsOutput('=== Memulai Proses Auto-Restart Streaming ===')
            ->expectsOutput("Memproses restart untuk User ID: {$user->id}")
            ->expectsOutput("Daftar video didapatkan dari pemilihan manual. Jumlah video: 1")
            ->assertExitCode(0);

        // Pastikan log file auto restart tercipta
        $this->assertTrue(file_exists(storage_path('logs/stream_auto_restart.log')));
        
        // Bersihkan log setelah test selesai
        @unlink(storage_path('logs/stream_auto_restart.log'));
        @unlink(base_path("scripts/stream_{$user->id}.sh"));
    }

    /**
     * Test command when there is an active stream with a playlist.
     */
    public function test_command_restarts_active_playlist_stream()
    {
        $user = User::factory()->create();
        
        $video1 = Video::create([
            'user_id' => $user->id,
            'title' => 'Video 1',
            'path' => 'video1.mp4',
            'order' => 1,
        ]);
        
        Storage::disk('public')->put('video1.mp4', 'dummy content');

        $playlist = Playlist::create([
            'user_id' => $user->id,
            'name' => 'My Playlist',
        ]);
        
        $playlist->videos()->attach($video1->id, ['order' => 1]);

        $setting = StreamSetting::create([
            'user_id' => $user->id,
            'youtube_key' => 'test_youtube_key',
            'is_active' => true,
            'last_playlist_id' => $playlist->id,
        ]);

        $this->artisan('stream:auto-restart')
            ->expectsOutput('=== Memulai Proses Auto-Restart Streaming ===')
            ->expectsOutput("Memproses restart untuk User ID: {$user->id}")
            ->expectsOutput("Daftar video didapatkan dari playlist: My Playlist. Jumlah video: 1")
            ->assertExitCode(0);

        @unlink(storage_path('logs/stream_auto_restart.log'));
        @unlink(base_path("scripts/stream_{$user->id}.sh"));
    }
}
