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
        Schema::create('journals', function (Blueprint $table) {
            $table->id();

            // Bibliographic Information
            $table->string('title');
            $table->string('publisher_authors')->nullable();
            $table->string('issn', 20)->nullable();
            $table->unsignedSmallInteger('year')->nullable();

            // Classification & Shelving
            $table->string('call_number', 50)->nullable();
            $table->string('subject')->nullable();
            $table->string('shelf_location', 100)->nullable();

            // Files
            $table->string('cover_image')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_type', 20)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
