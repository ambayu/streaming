<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddYoutubeOauthFieldsToStreamSettingsTable extends Migration
{
    public function up()
    {
        Schema::table('stream_settings', function (Blueprint $table) {
            $table->string('google_oauth_email')->nullable()->after('google_email');
            $table->text('google_oauth_refresh_token')->nullable()->after('google_oauth_email');
            $table->text('google_oauth_scopes')->nullable()->after('google_oauth_refresh_token');
            $table->timestamp('google_oauth_connected_at')->nullable()->after('google_oauth_scopes');
        });
    }

    public function down()
    {
        Schema::table('stream_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_oauth_email',
                'google_oauth_refresh_token',
                'google_oauth_scopes',
                'google_oauth_connected_at',
            ]);
        });
    }
}
