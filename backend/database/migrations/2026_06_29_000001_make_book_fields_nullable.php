<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow partial catalog records (e.g. imports without an author or subject)
     * to be stored without violating NOT NULL constraints.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('authors')->nullable()->change();
            $table->string('subject_area')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('authors')->nullable(false)->change();
            $table->string('subject_area')->nullable(false)->change();
        });
    }
};
