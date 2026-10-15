<?php

namespace Database\Factories;

use App\Models\Applicant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('555-###-####'),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-21 years')->format('Y-m-d'),
            // 9xx area numbers are never issued, so test data can't collide with a real SSN.
            'ssn' => fake()->numerify('9##-##-####'),
        ];
    }
}
