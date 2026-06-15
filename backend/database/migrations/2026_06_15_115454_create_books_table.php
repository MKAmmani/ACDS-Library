<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('subject');
            $table->string('isbn', 20)->unique()->nullable();
            $table->enum('format', ['book', 'journal', 'thesis', 'ebook'])->default('book');
            $table->enum('status', ['available', 'on_loan', 'digital_access'])->default('available');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('language', 50)->default('English');
            $table->string('cover_image')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
