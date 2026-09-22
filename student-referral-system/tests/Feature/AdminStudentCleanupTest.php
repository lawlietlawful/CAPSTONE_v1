<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two cleanups to the Students page:
 *
 * 1. admin.students.create/edit are gone. create had no link pointing to it
 *    anywhere in the UI (Add Student is a modal); edit had no controller
 *    method at all — Route::resource() registered it anyway and it would
 *    have 500'd if anyone ever hit it directly.
 *
 * 2. The single Add/Edit forms now enforce the same course/grade/section
 *    catalog match the CSV importer already enforced on every row — the UI
 *    dropdowns already make this practically unreachable, but a raw request
 *    could previously create a student pointing at a course/section that
 *    doesn't exist in the catalog at all.
 */
class AdminStudentCleanupTest extends TestCase
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

    private function validStudentPayload(array $overrides = []): array
    {
        return array_merge([
            'student_id_number' => '2026-5001',
            'course' => 'BSIT',
            'education_level' => 'College',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'gender' => 'Male',
            'birthdate' => '2005-01-01',
            'grade_level' => '1st Year',
            'section' => 'A',
            'school_year' => '2025-2026',
            'parent_name' => 'Test Parent',
            'parent_contact' => '09170000000',
            'address' => 'Test Address',
            'status' => 'active',
        ], $overrides);
    }

    // ── Orphaned routes removed ──────────────────────────────────────────

    public function test_the_standalone_create_route_no_longer_exists(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.students.create'));
    }

    public function test_the_broken_edit_route_no_longer_exists(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.students.edit'));
    }

    public function test_adding_a_student_via_the_modal_flow_still_works(): void
    {
        $this->seedCatalog();

        $response = $this->actingAs($this->counselor())
            ->post(route('admin.students.store'), $this->validStudentPayload());

        $response->assertRedirect(route('admin.students.index'));
        $this->assertDatabaseHas('students', ['student_id_number' => '2026-5001']);
    }

    // ── Catalog validation on the single Add/Edit forms ─────────────────

    public function test_store_rejects_a_course_grade_section_combination_not_in_the_catalog(): void
    {
        $this->seedCatalog();

        $response = $this->actingAs($this->counselor())->post(route('admin.students.store'), $this->validStudentPayload([
            'grade_level' => '5th Year', // not seeded — only '1st Year' exists for BSIT
        ]));

        $response->assertSessionHasErrors('course');
        $this->assertDatabaseMissing('students', ['student_id_number' => '2026-5001']);
    }

    public function test_store_accepts_a_course_grade_section_combination_that_is_in_the_catalog(): void
    {
        $this->seedCatalog();

        $response = $this->actingAs($this->counselor())
            ->post(route('admin.students.store'), $this->validStudentPayload());

        $response->assertSessionDoesntHaveErrors('course');
        $this->assertDatabaseHas('students', ['student_id_number' => '2026-5001']);
    }

    public function test_update_rejects_a_course_grade_section_combination_not_in_the_catalog(): void
    {
        $this->seedCatalog();
        $student = Student::factory()->create(['course' => 'BSIT', 'grade_level' => '1st Year', 'section' => 'A']);

        $response = $this->actingAs($this->counselor())->put(
            route('admin.students.update', $student->id),
            $this->validStudentPayload(['student_id_number' => $student->student_id_number, 'section' => 'Z'])
        );

        $response->assertSessionHasErrors('course');
    }

    public function test_update_accepts_a_course_grade_section_combination_that_is_in_the_catalog(): void
    {
        $this->seedCatalog();
        $student = Student::factory()->create(['course' => 'BSIT', 'grade_level' => '1st Year', 'section' => 'A']);

        $response = $this->actingAs($this->counselor())->put(
            route('admin.students.update', $student->id),
            $this->validStudentPayload(['student_id_number' => $student->student_id_number])
        );

        $response->assertSessionDoesntHaveErrors('course');
    }
}
