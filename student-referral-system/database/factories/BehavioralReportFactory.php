<?php

namespace Database\Factories;

use App\Models\BehavioralReport;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BehavioralReport>
 */
class BehavioralReportFactory extends Factory
{
    protected $model = BehavioralReport::class;

    public function definition(): array
    {
        return [
            'student_id'    => Student::factory(),
            'reported_by'   => User::factory()->teacher(),
            'incident_type' => 'Disciplinary Incident',
            'severity'      => 'Low',
            'description'   => fake()->sentence(),
            'incident_date' => now()->toDateString(),
            'location'      => 'Room 101',
            'status'        => 'pending',
        ];
    }
}
