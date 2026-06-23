<?php

namespace Database\Seeders;

use App\Models\LoanPolicy;
use Illuminate\Database\Seeder;

class LoanPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            ['membership_type' => 'student', 'max_books' => 5,  'loan_days' => 14, 'fine_per_day' => 0.50, 'max_fine' => 10.00],
            ['membership_type' => 'staff',   'max_books' => 10, 'loan_days' => 30, 'fine_per_day' => 0.25, 'max_fine' => 10.00],
            ['membership_type' => 'faculty', 'max_books' => 15, 'loan_days' => 60, 'fine_per_day' => 0.00, 'max_fine' => null],
            ['membership_type' => 'public',  'max_books' => 3,  'loan_days' => 7,  'fine_per_day' => 1.00, 'max_fine' => 15.00],
        ];

        foreach ($policies as $policy) {
            LoanPolicy::updateOrCreate(
                ['membership_type' => $policy['membership_type']],
                $policy
            );
        }
    }
}
