<?php

namespace App\Models;

use App\Enums\DecisionOutcome;
use App\Enums\ExplanationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['policy_version', 'outcome', 'rule_results', 'facts', 'explanation_status', 'underwriter_summary', 'applicant_message', 'principal_reasons', 'explanation_provider', 'decided_at'])]
class Decision extends Model
{
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => DecisionOutcome::class,
            'explanation_status' => ExplanationStatus::class,
            'rule_results' => 'array',
            'facts' => 'array',
            'principal_reasons' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LoanApplication, $this>
     */
    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
