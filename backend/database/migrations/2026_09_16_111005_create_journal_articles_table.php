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
        Schema::create('journal_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')
                  ->constrained('journals')
                  ->cascadeOnDelete();
            $table->string('title');
            $table->string('authors')->nullable();
            $table->string('page_range', 50)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['journal_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_articles');
    }
};
