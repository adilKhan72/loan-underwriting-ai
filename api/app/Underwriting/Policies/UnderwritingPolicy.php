<?php

namespace App\Underwriting\Policies;

use App\Underwriting\Rule;

interface UnderwritingPolicy
{
    public function version(): string;

    /**
     * Annual rate used for the affordability estimate.
     */
    public function annualRate(): float;

    /**
     * @return list<Rule>
     */
    public function rules(): array;
}
