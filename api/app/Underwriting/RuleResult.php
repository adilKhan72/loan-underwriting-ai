<?php

namespace App\Underwriting;

use App\Enums\RuleOutcome;

final readonly class RuleResult
{
    /**
     * @param  array<string, mixed>  $values  the inputs the rule used, stored for audit and replay
     */
    public function __construct(
        public string $code,
        public RuleOutcome $outcome,
        public string $message,
        public array $values = [],
    ) {}

    /**
     * @return array{code: string, outcome: string, message: string, values: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'outcome' => $this->outcome->value,
            'message' => $this->message,
            'values' => $this->values,
        ];
    }
}
