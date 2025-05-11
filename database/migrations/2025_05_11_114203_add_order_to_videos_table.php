<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderToVideosTable extends Migration
{
    public function up()
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('path');
        });

        // Set initial order based on id
        \App\Models\Video::orderBy('id')->get()->each(function ($video, $index) {
            $video->update(['order' => $index + 1]);
        });
    }

    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
}
