<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_resource_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('e_resource_id')
                  ->constrained('e_resources')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['e_resource_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_resource_downloads');
    }
};
