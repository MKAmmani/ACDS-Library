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
        Schema::create('loan_policies', function (Blueprint $table) {
            $table->id();
            $table->enum('membership_type', ['student', 'staff', 'faculty', 'public'])->unique();
            $table->unsignedTinyInteger('max_books')->default(5);
            $table->unsignedSmallInteger('loan_days')->default(14);
            $table->decimal('fine_per_day', 8, 2)->default(0.50);
            $table->decimal('max_fine', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_policies');
    }
};
