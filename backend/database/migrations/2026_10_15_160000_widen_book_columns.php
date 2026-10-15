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
        Schema::table('books', function (Blueprint $table) {
            $table->text('title')->change();
            $table->text('authors')->nullable()->change();
            $table->text('publisher')->nullable()->change();
            $table->text('subject_area')->nullable()->change();
            $table->string('call_number', 100)->nullable()->change();
            $table->string('shelf_location', 255)->nullable()->change();
            $table->string('edition', 100)->nullable()->change();
        });

        Schema::table('acquisitions', function (Blueprint $table) {
            $table->text('title')->change();
            $table->text('authors')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acquisitions', function (Blueprint $table) {
            $table->string('title', 255)->change();
            $table->string('authors', 255)->nullable()->change();
        });

        Schema::table('books', function (Blueprint $table) {
            $table->string('title', 255)->change();
            $table->string('authors', 255)->nullable()->change();
            $table->string('publisher', 255)->nullable()->change();
            $table->string('subject_area', 255)->nullable()->change();
            $table->string('call_number', 50)->nullable()->change();
            $table->string('shelf_location', 100)->nullable()->change();
            $table->string('edition', 50)->nullable()->change();
        });
    }
};
