<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            // Each student row is backed by a student login account.
            'user_id'           => User::factory()->student(),
            'student_id_number' => fake()->unique()->numerify('2023-####'),
            // course/grade_level/section drive User::advisedStudentsQuery(); the
            // defaults here match the advisedTeacher() helper so a factory-made
            // student is advised by a factory-made teacher out of the box.
            'course'            => 'BSIT',
            'first_name'        => fake()->firstName(),
            'last_name'         => fake()->lastName(),
            'gender'            => fake()->randomElement(['Male', 'Female']),
            'grade_level'       => '1st Year',
            'section'           => 'A',
            'school_year'       => '2025-2026',
            'parent_name'       => fake()->name(),
            'parent_contact'    => '0917' . fake()->numerify('#######'),
            'status'            => 'active',
        ];
    }

    /** A student outside any given teacher's course, so they are NOT advised. */
    public function inCourse(string $course, string $gradeLevel = '1st Year', string $section = 'A'): static
    {
        return $this->state(fn () => [
            'course'      => $course,
            'grade_level' => $gradeLevel,
            'section'     => $section,
        ]);
    }

    /** A Basic Education (K-12) student instead of the default College enrollee. */
    public function basicEducation(string $gradeLevel = 'Grade 7', string $section = 'A', ?string $strand = null): static
    {
        return $this->state(fn () => [
            'education_level' => 'Basic Education',
            'course'          => 'Basic Education',
            'grade_level'     => $gradeLevel,
            'strand'          => $strand,
            'section'         => $section,
        ]);
    }

    /** parent_contact blank, so the escalation SMS branch is skipped. */
    public function withoutParentContact(): static
    {
        return $this->state(fn () => ['parent_contact' => '']);
    }
}
