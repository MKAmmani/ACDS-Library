<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $map = [
        'student' => 'pg_student',
        'staff'   => 'buk_staff',
        'faculty' => 'independent_researcher',
        'public'  => 'international_researcher',
    ];

    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY membership_type VARCHAR(40) DEFAULT 'pg_student'");
        foreach ($this->map as $old => $new) {
            DB::table('users')->where('membership_type', $old)->update(['membership_type' => $new]);
        }
        DB::statement("ALTER TABLE users MODIFY membership_type ENUM('buk_staff','pg_student','independent_researcher','international_researcher') DEFAULT 'pg_student'");

        DB::statement('ALTER TABLE loan_policies MODIFY membership_type VARCHAR(40)');
        foreach ($this->map as $old => $new) {
            DB::table('loan_policies')->where('membership_type', $old)->update(['membership_type' => $new]);
        }
        DB::statement("ALTER TABLE loan_policies MODIFY membership_type ENUM('buk_staff','pg_student','independent_researcher','international_researcher')");
    }

    public function down(): void
    {
        $reverse = array_flip($this->map);

        DB::statement("ALTER TABLE users MODIFY membership_type VARCHAR(40) DEFAULT 'student'");
        foreach ($reverse as $new => $old) {
            DB::table('users')->where('membership_type', $new)->update(['membership_type' => $old]);
        }
        DB::statement("ALTER TABLE users MODIFY membership_type ENUM('student','staff','faculty','public') DEFAULT 'student'");

        DB::statement('ALTER TABLE loan_policies MODIFY membership_type VARCHAR(40)');
        foreach ($reverse as $new => $old) {
            DB::table('loan_policies')->where('membership_type', $new)->update(['membership_type' => $old]);
        }
        DB::statement("ALTER TABLE loan_policies MODIFY membership_type ENUM('student','staff','faculty','public')");
    }
};
