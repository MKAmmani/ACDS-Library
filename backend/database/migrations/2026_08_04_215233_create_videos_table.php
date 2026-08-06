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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('kind', 50)->nullable();       // Public lecture, Documentary, Training…
            $table->string('tag', 100)->nullable();        // subject tag shown on the card
            $table->string('duration_label', 20)->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
