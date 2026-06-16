<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAutoRestartEnabledToStreamSettingsTable extends Migration
{
    public function up()
    {
        Schema::table('stream_settings', function (Blueprint $table) {
            $table->boolean('auto_restart_enabled')->default(true)->after('is_active');
        });
    }

    public function down()
    {
        Schema::table('stream_settings', function (Blueprint $table) {
            $table->dropColumn('auto_restart_enabled');
        });
    }
}
