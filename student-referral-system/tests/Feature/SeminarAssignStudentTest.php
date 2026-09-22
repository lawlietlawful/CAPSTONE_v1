<?php

namespace Tests\Feature;

use App\Models\Seminar;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Assigning a student who is already enrolled in a seminar used to throw an
 * uncaught UniqueConstraintViolationException (student_seminars has a
 * unique(student_id, seminar_id)) — a 500 instead of a redirect. This
 * happened in practice from the Referral Details page's "AI Recommended
 * Seminars" widget, which re-offers the same seminar for every referral
 * that shares its trigger_reason, so a student with more than one such
 * referral (or one already manually assigned) would hit "Assign Student"
 * on a seminar they were already in.
 */
class SeminarAssignStudentTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seminar(array $overrides = []): Seminar
    {
        return Seminar::create(array_merge([
            'title' => 'Time Management & Attendance Recovery',
            'date' => now()->addDays(5),
            'time' => '09:00:00',
            'venue' => 'Main Hall',
            'is_required' => true,
            'status' => 'upcoming',
            'trigger_reason' => 'attendance_intervention',
        ], $overrides));
    }

    public function test_assigning_an_already_enrolled_student_does_not_500(): void
    {
        $counselor = $this->counselor();
        $seminar = $this->seminar();
        $student = Student::factory()->create();

        $seminar->students()->attach($student->id, ['status' => 'enrolled', 'assigned_by' => 'manual']);

        $response = $this->actingAs($counselor)
            ->post(route('counselor.seminars.assign', $seminar->id), [
                'student_ids' => [$student->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1, $seminar->students()->count());
    }

    public function test_assigning_a_mix_of_new_and_already_enrolled_students_only_attaches_the_new_ones(): void
    {
        $counselor = $this->counselor();
        $seminar = $this->seminar();
        $alreadyIn = Student::factory()->create();
        $newStudent = Student::factory()->create();

        $seminar->students()->attach($alreadyIn->id, ['status' => 'enrolled', 'assigned_by' => 'manual']);

        $response = $this->actingAs($counselor)
            ->post(route('counselor.seminars.assign', $seminar->id), [
                'student_ids' => [$alreadyIn->id, $newStudent->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(2, $seminar->students()->count());
        $this->assertTrue($seminar->students()->where('students.id', $newStudent->id)->exists());
    }

    public function test_assigning_a_genuinely_new_student_still_works(): void
    {
        $counselor = $this->counselor();
        $seminar = $this->seminar();
        $student = Student::factory()->create();

        $response = $this->actingAs($counselor)
            ->post(route('counselor.seminars.assign', $seminar->id), [
                'student_ids' => [$student->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(1, $seminar->students()->count());
    }
}
