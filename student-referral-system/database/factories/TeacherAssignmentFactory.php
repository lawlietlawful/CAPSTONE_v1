<?php

namespace Database\Factories;

use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends Factory
{
    protected $model = TeacherAssignment::class;

    public function definition(): array
    {
        return [
            'teacher_id'  => User::factory()->teacher(),
            'course'      => 'BSIT',
            'grade_level' => '1st Year',
            'section'     => 'A',
        ];
    }
}
