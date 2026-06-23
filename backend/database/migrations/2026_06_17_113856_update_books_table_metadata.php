<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop removed columns in a separate call (SQLite rebuilds the table internally)
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['author', 'subject', 'cover_image', 'file_path', 'status', 'publication_year', 'format']);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->string('authors')->after('title');
            $table->string('publisher')->nullable()->after('authors');
            $table->unsignedSmallInteger('year')->nullable()->after('publisher');
            $table->string('edition', 50)->nullable()->after('year');
            $table->string('subject_area')->after('description');
            $table->string('call_number', 50)->nullable()->after('subject_area');
            $table->string('shelf_location', 100)->nullable()->after('call_number');
            $table->string('format', 50)->nullable()->after('language');
            $table->string('material_type', 50)->nullable()->after('format');
            $table->unsignedSmallInteger('number_of_copies')->default(1)->after('material_type');
            $table->string('cover_treatment', 100)->nullable()->after('number_of_copies');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn([
                'authors', 'publisher', 'year', 'edition', 'subject_area',
                'call_number', 'shelf_location', 'format', 'material_type',
                'number_of_copies', 'cover_treatment',
            ]);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->string('author')->after('title');
            $table->string('subject')->after('author');
            $table->enum('format', ['book', 'journal', 'thesis', 'ebook'])->default('book');
            $table->enum('status', ['available', 'on_loan', 'digital_access'])->default('available');
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('file_path')->nullable();
        });
    }
};
