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
        // Default admin account
        User::firstOrCreate(
            ['email' => 'admin@acds-library.com'],
            [
                'name'     => 'System Administrator',
                'password' => Hash::make('Admin@12345'),
                'role'     => 'admin',
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
