<?php

namespace Tests\Concerns;

use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Shared scaffolding for teacher/escalation tests: a teacher advising a student,
 * and a deterministic stand-in for the Python ML engine.
 */
trait BuildsTeacherScenario
{
    /**
     * A teacher assigned to a course, plus a student in that same course — so
     * the teacher advises the student per User::advisedStudentsQuery().
     *
     * @return array{0: User, 1: Student}
     */
    protected function teacherAdvising(array $studentOverrides = []): array
    {
        $teacher = User::factory()->teacher()->create();

        TeacherAssignment::factory()->create([
            'teacher_id'  => $teacher->id,
            'course'      => 'BSIT',
            'grade_level' => '1st Year',
            'section'     => 'A',
        ]);

        // StudentFactory defaults (BSIT / 1st Year / A / active) match the
        // assignment above, so the teacher advises this student by default.
        $student = Student::factory()->create($studentOverrides);

        return [$teacher, $student];
    }

    /**
     * Fake the ML engine so escalation/priority logic is exercised against a
     * known risk level instead of a live model. The catch-all keeps any other
     * outbound call (e.g. a stray SMS gateway hit) off the real network.
     */
    protected function fakeMlEngine(string $riskLevel, float $riskScore = 90, string $seminarTag = 'values_formation'): void
    {
        Http::fake([
            '*/predict' => Http::response([
                'risk_level'              => $riskLevel,
                'risk_score'              => $riskScore,
                'recommended_seminar_tag' => $seminarTag,
            ], 200),
            '*' => Http::response([], 200),
        ]);
    }

    /** Simulate the ML engine being down: /predict returns a 503. */
    protected function failMlEngine(): void
    {
        Http::fake([
            '*/predict' => Http::response(['detail' => 'Models not loaded.'], 503),
            '*' => Http::response([], 200),
        ]);
    }
}
