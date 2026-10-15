<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\LoanApplication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_application_id' => LoanApplication::factory(),
            'type' => DocumentType::Paystub,
            'disk' => 'local',
            'path' => 'applications/test/'.Str::ulid().'.pdf',
            'original_name' => 'paystub.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 52_000,
            'sha256' => hash('sha256', Str::random(32)),
        ];
    }

    public function bankStatement(): static
    {
        return $this->state(fn () => ['type' => DocumentType::BankStatement, 'original_name' => 'statement.pdf']);
    }
}
