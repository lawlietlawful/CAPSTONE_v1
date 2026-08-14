<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The teacher Employee ID + one-time-code activation flow, mirroring the student
 * flow. Locks down the login gate, the activation checks, and the one-time
 * nature of the code.
 */
class TeacherActivationTest extends TestCase
{
    use RefreshDatabase;

    /** A teacher account created but not yet activated, with a known code. */
    private function pendingTeacher(string $code, string $schoolId = 'T-TEST-001'): User
    {
        return User::factory()->teacher()->create([
            'username'             => $schoolId,
            'password'             => Hash::make(Str::random(40)), // locked
            'account_activated_at' => null,
            'activation_code'      => Hash::make(User::canonicalActivationCode($code)),
        ]);
    }

    private function activatePayload(array $overrides = []): array
    {
        return array_merge([
            'school_id'                 => 'T-TEST-001',
            'activation_code'           => 'ABC-123',
            'new_password'              => 'chosenpass1',
            'new_password_confirmation' => 'chosenpass1',
        ], $overrides);
    }

    // ── Login gate ────────────────────────────────────────────────────────

    public function test_unactivated_teacher_cannot_log_in(): void
    {
        $this->pendingTeacher('ABC-123');

        $this->postJson('/api/teacher/login', [
            'email' => 'T-TEST-001', 'password' => 'anything',
        ])->assertStatus(403); // "activate first"
    }

    public function test_activated_teacher_can_log_in_with_chosen_password(): void
    {
        $this->pendingTeacher('ABC-123');
        $this->postJson('/api/teacher/activate', $this->activatePayload())->assertOk();

        $this->postJson('/api/teacher/login', [
            'email' => 'T-TEST-001', 'password' => 'chosenpass1',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    // ── Activation ────────────────────────────────────────────────────────

    public function test_teacher_activates_with_school_id_and_code(): void
    {
        $teacher = $this->pendingTeacher('ABC-123');

        $this->postJson('/api/teacher/activate', $this->activatePayload())
            ->assertOk()
            ->assertJsonStructure(['token', 'teacher' => ['id', 'name', 'username']]);

        $teacher->refresh();
        $this->assertNotNull($teacher->account_activated_at, 'account must be marked activated');
        $this->assertNull($teacher->activation_code, 'code must be consumed (one-time)');
        $this->assertTrue(Hash::check('chosenpass1', $teacher->password), 'chosen password must be set');
    }

    public function test_activation_code_match_is_forgiving_of_case_and_hyphen(): void
    {
        $this->pendingTeacher('ABC-123');

        // Typed lowercase, with a space instead of the display hyphen.
        $this->postJson('/api/teacher/activate', $this->activatePayload([
            'activation_code' => 'abc 123',
        ]))->assertOk();
    }

    public function test_wrong_code_is_rejected_and_account_stays_pending(): void
    {
        $teacher = $this->pendingTeacher('ABC-123');

        $this->postJson('/api/teacher/activate', $this->activatePayload([
            'activation_code' => 'WRONG-99',
        ]))->assertStatus(422);

        $this->assertNull($teacher->fresh()->account_activated_at);
    }

    public function test_unknown_school_id_is_rejected(): void
    {
        $this->pendingTeacher('ABC-123');

        $this->postJson('/api/teacher/activate', $this->activatePayload([
            'school_id' => 'T-NOPE-999',
        ]))->assertStatus(422);
    }

    public function test_already_activated_account_cannot_reactivate(): void
    {
        $this->pendingTeacher('ABC-123');
        $this->postJson('/api/teacher/activate', $this->activatePayload())->assertOk();

        // A second attempt (even with the right original code) must fail — the
        // code was consumed and the account is active.
        $this->postJson('/api/teacher/activate', $this->activatePayload())
            ->assertStatus(422);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->pendingTeacher('ABC-123');

        $this->postJson('/api/teacher/activate', $this->activatePayload([
            'new_password_confirmation' => 'doesnotmatch',
        ]))->assertStatus(422);
    }

    public function test_password_has_a_minimum_length(): void
    {
        $this->pendingTeacher('ABC-123');

        $this->postJson('/api/teacher/activate', $this->activatePayload([
            'new_password'              => 'short',
            'new_password_confirmation' => 'short',
        ]))->assertStatus(422);
    }

    // ── Code helpers ──────────────────────────────────────────────────────

    public function test_generated_code_canonicalizes_consistently(): void
    {
        $code = User::generateActivationCode();

        // However a teacher re-types it (lowercase, hyphen as space), it
        // canonicalizes to the same value that gets hashed.
        $this->assertSame(
            User::canonicalActivationCode($code),
            User::canonicalActivationCode(strtolower(str_replace('-', ' ', $code)))
        );
    }
}
