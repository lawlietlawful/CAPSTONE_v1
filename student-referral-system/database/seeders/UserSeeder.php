<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate (keyed by email) so re-seeding an already-populated
        // database (e.g. `db:seed` run twice) doesn't fail on the unique
        // email constraint.

        // Super Admin (Developers)
        User::firstOrCreate(
            ['email' => 'admin@school.com'],
            ['name' => 'System Admin', 'password' => Hash::make('password'), 'role' => 'super_admin']
        );

        // School Admin / Guidance Counselor
        User::firstOrCreate(
            ['email' => 'counselor@school.com'],
            ['name' => 'Ma\'am Edago', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        // Teacher
        User::firstOrCreate(
            ['email' => 'teacher@school.com'],
            ['name' => 'Sir Santos', 'password' => Hash::make('password'), 'role' => 'teacher']
        );

        // Student
        User::firstOrCreate(
            ['email' => 'student@school.com'],
            ['name' => 'Juan Dela Cruz', 'password' => Hash::make('password'), 'role' => 'student']
        );
    }
}
