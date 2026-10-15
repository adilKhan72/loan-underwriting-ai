<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\LoanApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanApplication>
 */
class LoanApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'amount_requested_cents' => fake()->numberBetween(50, 500) * 1000,
            'term_months' => fake()->randomElement([6, 12, 18, 24]),
            'purpose' => fake()->randomElement(['car repair', 'medical bill', 'debt consolidation']),
            'stated_monthly_income_cents' => fake()->numberBetween(200, 900) * 1000,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Submitted, 'submitted_at' => now()]);
    }
}
