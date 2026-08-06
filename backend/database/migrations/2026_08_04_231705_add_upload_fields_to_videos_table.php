<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            // Uploaded thumbnail image, stored on the public disk. When this is
            // filled it takes precedence over thumbnail_url (a pasted external image link).
            $table->string('thumbnail')->nullable()->after('thumbnail_url');

            // Uploaded video file, stored on the s3/R2 disk. When this is filled
            // it takes precedence over video_url (a pasted YouTube/external link).
            $table->string('video_path')->nullable()->after('video_url');
            $table->unsignedBigInteger('file_size')->nullable()->after('video_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['thumbnail', 'video_path', 'file_size']);
        });
    }
};
