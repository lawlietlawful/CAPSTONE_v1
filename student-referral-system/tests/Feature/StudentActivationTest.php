<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The student Student-ID + one-time-code activation flow, mirroring the
 * teacher flow (TeacherActivationTest). This replaced a Student-ID +
 * birthdate check: birthdates aren't a real secret in a school context, so
 * that flow let an attacker who knew a student's ID and birthdate hijack the
 * account before the real student ever activated it.
 */
class StudentActivationTest extends TestCase
{
    use RefreshDatabase;

    /** A student created but not yet activated, with a known code. */
    private function pendingStudent(string $code, string $studentIdNumber = '2024-0001'): Student
    {
        $user = User::factory()->student()->create([
            'username' => $studentIdNumber,
            'password' => Hash::make(Str::random(40)), // locked
            'account_activated_at' => null,
            'activation_code' => Hash::make(User::canonicalActivationCode($code)),
        ]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'student_id_number' => $studentIdNumber,
        ]);
    }

    private function activatePayload(array $overrides = []): array
    {
        return array_merge([
            'student_id_number' => '2024-0001',
            'activation_code' => 'ABC-123',
            'new_password' => 'chosenpass1',
            'new_password_confirmation' => 'chosenpass1',
        ], $overrides);
    }

    // ── Login gate ────────────────────────────────────────────────────────

    public function test_unactivated_student_cannot_log_in(): void
    {
        $this->pendingStudent('ABC-123');

        $this->postJson('/api/login', [
            'email' => '2024-0001', 'password' => 'anything',
        ])->assertStatus(403); // "activate first"
    }

    public function test_activated_student_can_log_in_with_chosen_password(): void
    {
        $this->pendingStudent('ABC-123');
        $this->postJson('/api/activate', $this->activatePayload())->assertOk();

        $this->postJson('/api/login', [
            'email' => '2024-0001', 'password' => 'chosenpass1',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    // ── Activation ────────────────────────────────────────────────────────

    public function test_student_activates_with_id_and_code(): void
    {
        $student = $this->pendingStudent('ABC-123');

        $this->postJson('/api/activate', $this->activatePayload())
            ->assertOk()
            ->assertJsonStructure(['token', 'student' => ['id', 'first_name', 'last_name', 'school_id']]);

        $user = $student->user->fresh();
        $this->assertNotNull($user->account_activated_at, 'account must be marked activated');
        $this->assertNull($user->activation_code, 'code must be consumed (one-time)');
        $this->assertTrue(Hash::check('chosenpass1', $user->password), 'chosen password must be set');
    }

    public function test_activation_code_match_is_forgiving_of_case_and_hyphen(): void
    {
        $this->pendingStudent('ABC-123');

        // Typed lowercase, with a space instead of the display hyphen.
        $this->postJson('/api/activate', $this->activatePayload([
            'activation_code' => 'abc 123',
        ]))->assertOk();
    }

    public function test_wrong_code_is_rejected_and_account_stays_pending(): void
    {
        $student = $this->pendingStudent('ABC-123');

        $this->postJson('/api/activate', $this->activatePayload([
            'activation_code' => 'WRONG-99',
        ]))->assertStatus(422);

        $this->assertNull($student->user->fresh()->account_activated_at);
    }

    public function test_correct_code_for_the_wrong_student_id_is_rejected(): void
    {
        $this->pendingStudent('ABC-123', '2024-0001');
        $this->pendingStudent('XYZ-789', '2024-0002');

        // Right code, but paired with someone else's Student ID.
        $this->postJson('/api/activate', $this->activatePayload([
            'student_id_number' => '2024-0002',
            'activation_code' => 'ABC-123',
        ]))->assertStatus(422);
    }

    public function test_unknown_student_id_is_rejected(): void
    {
        $this->pendingStudent('ABC-123');

        $this->postJson('/api/activate', $this->activatePayload([
            'student_id_number' => '2024-9999',
        ]))->assertStatus(422);
    }

    public function test_already_activated_account_cannot_reactivate(): void
    {
        $this->pendingStudent('ABC-123');
        $this->postJson('/api/activate', $this->activatePayload())->assertOk();

        // A second attempt (even with the right original code) must fail — the
        // code was consumed and the account is active.
        $this->postJson('/api/activate', $this->activatePayload())
            ->assertStatus(422);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->pendingStudent('ABC-123');

        $this->postJson('/api/activate', $this->activatePayload([
            'new_password_confirmation' => 'doesnotmatch',
        ]))->assertStatus(422);
    }

    public function test_password_has_a_minimum_length(): void
    {
        $this->pendingStudent('ABC-123');

        $this->postJson('/api/activate', $this->activatePayload([
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ]))->assertStatus(422);
    }
}
