<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('member_number', 20)->unique()->nullable()->after('name');
            $table->string('phone', 20)->nullable()->after('member_number');
            $table->text('address')->nullable()->after('phone');
            $table->enum('membership_type', ['student', 'staff', 'faculty', 'public'])->default('student')->after('address');
            $table->date('membership_expires_at')->nullable()->after('membership_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['member_number', 'phone', 'address', 'membership_type', 'membership_expires_at']);
        });
    }
};
