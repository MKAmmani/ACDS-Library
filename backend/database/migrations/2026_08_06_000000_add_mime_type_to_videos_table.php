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
            // Detected MIME type of an uploaded file (e.g. video/mp4, audio/mpeg).
            // Captured at upload time so playback/streaming doesn't have to guess
            // from the file extension — this is what lets audio-only uploads
            // (podcasts, oral history recordings, …) stream with the right
            // Content-Type instead of being treated as video.
            $table->string('mime_type')->nullable()->after('file_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('mime_type');
        });
    }
};
