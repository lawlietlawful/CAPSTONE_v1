<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unactivated_student_is_told_to_use_their_activation_code_not_a_birthdate(): void
    {
        $user = User::factory()->student()->create([
            'username' => '2026-0099',
            'account_activated_at' => null,
        ]);
        Student::factory()->create(['user_id' => $user->id, 'student_id_number' => '2026-0099']);

        $response = $this->postJson('/api/login', [
            'email' => '2026-0099',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Please activate your account first using your Student ID and activation code.',
        ]);
        $response->assertJsonMissing(['message' => 'Please activate your account first using your Student ID and birthdate.']);
    }
}
