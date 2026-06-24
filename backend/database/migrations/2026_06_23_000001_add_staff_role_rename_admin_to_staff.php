<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Hold data during column swap
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_temp')->nullable();
        });

        // Step 2: Map admin → staff, preserve everything else
        DB::table('users')->update([
            'role_temp' => DB::raw("CASE WHEN role = 'admin' THEN 'staff' ELSE role END"),
        ]);

        // Step 3: Drop old constrained column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        // Step 4: Add new enum with all three tiers
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff', 'user'])->default('user')->after('email');
        });

        // Step 5: Restore from temp
        DB::table('users')->update([
            'role' => DB::raw('role_temp'),
        ]);

        // Step 6: Clean up temp column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role_temp');
        });
    }

    public function down(): void
    {
        // Revert: admin stays admin, staff → admin, user stays user
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_temp')->nullable();
        });

        DB::table('users')->update([
            'role_temp' => DB::raw("CASE WHEN role = 'staff' THEN 'admin' ELSE role END"),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'user'])->default('user')->after('email');
        });

        DB::table('users')->update([
            'role' => DB::raw('role_temp'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role_temp');
        });
    }
};
