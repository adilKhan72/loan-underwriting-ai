<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use Database\Factories\LoanApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['amount_requested_cents', 'term_months', 'purpose', 'stated_monthly_income_cents'])]
class LoanApplication extends Model
{
    /** @use HasFactory<LoanApplicationFactory> */
    use HasFactory, HasUlids;

    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'amount_requested_cents' => 'integer',
            'stated_monthly_income_cents' => 'integer',
            'term_months' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function hasDocumentOfType(DocumentType $type): bool
    {
        return $this->documents()->where('type', $type)->exists();
    }

    /**
     * @return BelongsTo<Applicant, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<Decision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }

    /**
     * @return HasOne<Decision, $this>
     */
    public function latestDecision(): HasOne
    {
        return $this->hasOne(Decision::class)->latestOfMany();
    }
}
