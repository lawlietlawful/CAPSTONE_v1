<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin-facing side of student activation codes: a fresh code is issued
 * when a student is created via the single Add Student form, and a lost
 * code can be regenerated for any student who hasn't activated yet.
 */
class StudentActivationCodeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function seedCatalog(): void
    {
        $course = Course::create(['name' => 'BSIT', 'education_level' => 'College']);
        CourseSection::create(['course_id' => $course->id, 'grade_level' => '1st Year', 'section' => 'A']);
    }

    public function test_creating_a_student_issues_an_activation_code(): void
    {
        $this->seedCatalog();

        $response = $this->actingAs($this->counselor())->post(route('admin.students.store'), [
            'student_id_number' => '2024-0001',
            'course' => 'BSIT',
            'education_level' => 'College',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'gender' => 'Male',
            'birthdate' => '2005-06-15',
            'grade_level' => '1st Year',
            'section' => 'A',
            'school_year' => '2025-2026',
            'parent_name' => 'Maria Dela Cruz',
            'parent_contact' => '09171234567',
            'address' => '123 Sample St.',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHas('activation_code', function ($act) {
            return $act['school_id'] === '2024-0001' && !empty($act['code']);
        });

        $user = Student::where('student_id_number', '2024-0001')->first()->user;
        $this->assertNotNull($user->activation_code);
        $this->assertNull($user->account_activated_at);
    }

    public function test_regenerating_a_code_replaces_the_stored_hash(): void
    {
        $student = Student::factory()->create(['student_id_number' => '2024-0001']);
        $student->user->update([
            'account_activated_at' => null,
            'activation_code' => Hash::make(User::canonicalActivationCode('OLD-CODE')),
        ]);
        $originalHash = $student->user->activation_code;

        $response = $this->actingAs($this->counselor())
            ->post(route('admin.students.activation-code', $student));

        $response->assertRedirect();
        $response->assertSessionHas('activation_code', function ($act) use ($student) {
            return $act['school_id'] === $student->student_id_number && !empty($act['code']);
        });

        $this->assertNotSame($originalHash, $student->user->fresh()->activation_code);
    }

    public function test_cannot_regenerate_a_code_for_an_already_activated_student(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['account_activated_at' => now()]);

        $this->actingAs($this->counselor())
            ->post(route('admin.students.activation-code', $student))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull(session('activation_code'), 'must not issue a code for an active account');
    }

    public function test_non_admin_cannot_regenerate_a_code(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = Student::factory()->create();

        $this->actingAs($teacher)
            ->post(route('admin.students.activation-code', $student))
            ->assertForbidden();
    }
}
