<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The videos table now carries audio uploads too (podcasts, oral history
     * recordings, …), so it — and the model/controller/routes built on top
     * of it — are renamed to the more general "media". Older migrations
     * that built this table are left untouched (they already ran); this one
     * captures the rename as its own step.
     */
    public function up(): void
    {
        Schema::rename('videos', 'media');

        Schema::table('media', function (Blueprint $table) {
            $table->renameColumn('video_url', 'media_url');
            $table->renameColumn('video_path', 'media_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->renameColumn('media_url', 'video_url');
            $table->renameColumn('media_path', 'video_path');
        });

        Schema::rename('media', 'videos');
    }
};
