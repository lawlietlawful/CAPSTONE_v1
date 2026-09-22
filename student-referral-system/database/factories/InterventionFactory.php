<?php

namespace Database\Factories;

use App\Models\Intervention;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    protected $model = Intervention::class;

    public function definition(): array
    {
        return [
            'referral_id'       => Referral::factory(),
            'counselor_id'      => User::factory()->counselor(),
            'intervention_type' => fake()->randomElement(Intervention::TYPES),
            'intervention_date' => now()->format('Y-m-d'),
            'description'       => fake()->sentence(),
            'outcome'           => null,
            'follow_up_date'    => null,
            'follow_up_notes'   => null,
        ];
    }
}
