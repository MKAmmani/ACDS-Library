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
        Schema::create('institutional_repositories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('authors');
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->string('edition', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('call_number', 50)->nullable();
            $table->string('shelf_location', 100)->nullable();
            $table->string('subject_area');
            $table->string('language', 50)->default('English');
            $table->string('format', 50)->nullable();
            $table->string('cover_image')->nullable();
            $table->string('file_path');
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
        Schema::dropIfExists('institutional_repositories');
    }
};
