<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquisitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('authors')->nullable();
            $table->string('requested_by')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->unsignedTinyInteger('copies')->default(1);
            $table->enum('status', ['awaiting', 'ordered', 'received', 'declined'])->default('awaiting');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisitions');
    }
};
