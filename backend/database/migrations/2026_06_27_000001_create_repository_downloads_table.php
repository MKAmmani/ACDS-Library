<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')
                  ->constrained('institutional_repositories')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['repository_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_downloads');
    }
};
