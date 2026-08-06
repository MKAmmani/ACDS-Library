<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE institutional_repositories MODIFY authors VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE institutional_repositories SET authors = '' WHERE authors IS NULL");
        DB::statement('ALTER TABLE institutional_repositories MODIFY authors VARCHAR(255) NOT NULL');
    }
};
