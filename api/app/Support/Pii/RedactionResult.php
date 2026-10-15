<?php

namespace App\Support\Pii;

final readonly class RedactionResult
{
    /**
     * @param  array<string, int>  $counts  placeholder type => number of replacements
     */
    public function __construct(
        public string $text,
        public array $counts,
    ) {}

    public function total(): int
    {
        return array_sum($this->counts);
    }
}
