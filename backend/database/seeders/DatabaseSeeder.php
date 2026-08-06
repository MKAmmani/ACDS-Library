<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(LoanPolicySeeder::class);
        $this->call(MediaSeeder::class);
        $this->call(NewsPostSeeder::class);
        $this->call(EventSeeder::class);
        $this->call(OpeningHourSeeder::class);

        // Super admin — full system oversight, manages staff accounts
        User::firstOrCreate(
            ['email' => 'superadmin@acds-library.com'],
            [
                'name'     => 'Super Administrator',
                'password' => Hash::make('Admin@12345'),
                'role'     => 'admin',
            ]
        );

        // Staff account — same access as admin except cannot manage staff/admin users
        User::firstOrCreate(
            ['email' => 'staff@acds-library.com'],
            [
                'name'     => 'System Staff',
                'password' => Hash::make('Staff@12345'),
                'role'     => 'staff',
            ]
        );

        // Sample system user
        User::firstOrCreate(
            ['email' => 'user@acds-library.com'],
            [
                'name'     => 'Sample User',
                'password' => Hash::make('User@12345'),
                'role'     => 'user',
            ]
        );
    }
}
