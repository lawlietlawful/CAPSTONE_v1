<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'name'     => 'System Admin',
            'email'    => 'admin@school.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        // Guidance Counselor
        User::create([
            'name'     => 'Ma\'am Edago',
            'email'    => 'counselor@school.com',
            'password' => Hash::make('password'),
            'role'     => 'guidance_counselor',
        ]);

        // Teacher
        User::create([
            'name'     => 'Sir Santos',
            'email'    => 'teacher@school.com',
            'password' => Hash::make('password'),
            'role'     => 'teacher',
        ]);

        // Student
        User::create([
            'name'     => 'Juan Dela Cruz',
            'email'    => 'student@school.com',
            'password' => Hash::make('password'),
            'role'     => 'student',
        ]);
    }
}
