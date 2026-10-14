<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An account that was never activated is refused even when the password
     * it was created with matches — with the same answer as a wrong password,
     * which points first-timers to activation without confirming the ID.
     */
    public function test_an_unactivated_student_is_pointed_to_activation_without_confirming_the_id(): void
    {
        $user = User::factory()->student()->create([
            'username' => '2026-0099',
            'account_activated_at' => null,
        ]);
        Student::factory()->create(['user_id' => $user->id, 'student_id_number' => '2026-0099']);

        $unactivated = $this->postJson('/api/login', ['email' => '2026-0099', 'password' => 'password']);
        $unknown = $this->postJson('/api/login', ['email' => '2026-9999', 'password' => 'password']);

        $unactivated->assertStatus(401)->assertJson([
            'message' => 'Invalid Student ID or password. Signing in for the first time? Activate your account first.',
        ]);
        $this->assertSame($unknown->json(), $unactivated->json());
        $this->assertSame(0, $user->tokens()->count());
    }
}
