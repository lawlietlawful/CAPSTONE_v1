<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        return [
            'student_id'    => Student::factory(),
            'referred_by'   => User::factory()->teacher(),
            'referral_type' => 'Misconduct',
            'concern_type'  => 'behavioral',
            'reason'        => fake()->sentence(),
            'priority'      => 'moderate',
            'status'        => 'pending',
        ];
    }
}
