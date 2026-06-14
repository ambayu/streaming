<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStreamStatusToStreamSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stream_settings', function (Blueprint $table) {
            $table->foreignId('last_playlist_id')->nullable()->after('youtube_key')->constrained('playlists')->onDelete('set null');
            $table->text('last_video_ids')->nullable()->after('last_playlist_id');
            $table->boolean('is_active')->default(false)->after('last_video_ids');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stream_settings', function (Blueprint $table) {
            $table->dropForeign(['last_playlist_id']);
            $table->dropColumn(['last_playlist_id', 'last_video_ids', 'is_active']);
        });
    }
}
