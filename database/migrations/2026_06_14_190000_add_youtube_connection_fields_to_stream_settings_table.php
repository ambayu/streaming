<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddYoutubeConnectionFieldsToStreamSettingsTable extends Migration
{
    public function up()
    {
        Schema::table('stream_settings', function (Blueprint $table) {
            $table->string('google_email')->nullable()->after('youtube_key');
            $table->string('youtube_channel_id')->nullable()->after('google_email');
            $table->string('youtube_cookie_path')->nullable()->after('youtube_channel_id');
            $table->timestamp('youtube_connected_at')->nullable()->after('youtube_cookie_path');
            $table->string('youtube_last_prepare_status')->nullable()->after('youtube_connected_at');
            $table->text('youtube_last_prepare_message')->nullable()->after('youtube_last_prepare_status');
        });
    }

    public function down()
    {
        Schema::table('stream_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_email',
                'youtube_channel_id',
                'youtube_cookie_path',
                'youtube_connected_at',
                'youtube_last_prepare_status',
                'youtube_last_prepare_message',
            ]);
        });
    }
}
