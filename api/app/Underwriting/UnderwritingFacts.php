<?php

namespace App\Underwriting;

use Carbon\CarbonImmutable;

/**
 * Everything the rules engine may look at, computed once from the application and its validated extractions.
 * Snapshotted onto the decision, so a decision can be replayed against the exact inputs it saw.
 */
final readonly class UnderwritingFacts
{
    /**
     * @param  list<int>  $payrollDepositsCents  deposits flagged as payroll on bank statements
     */
    public function __construct(
        public int $requestedCents,
        public int $termMonths,
        public int $statedMonthlyIncomeCents,
        public CarbonImmutable $submittedAt,
        public int $documentsNeedingReview,
        public bool $hasVerifiedPaystub,
        public ?int $verifiedMonthlyGrossCents,
        public ?int $latestNetPayCents,
        public ?CarbonImmutable $latestPayDate,
        public bool $hasVerifiedBankStatement,
        public array $payrollDepositsCents,
        public ?int $overdraftCount,
        public ?float $minimumConfidence,
        public int $estimatedMonthlyPaymentCents,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requested_cents' => $this->requestedCents,
            'term_months' => $this->termMonths,
            'stated_monthly_income_cents' => $this->statedMonthlyIncomeCents,
            'submitted_at' => $this->submittedAt->toIso8601String(),
            'documents_needing_review' => $this->documentsNeedingReview,
            'has_verified_paystub' => $this->hasVerifiedPaystub,
            'verified_monthly_gross_cents' => $this->verifiedMonthlyGrossCents,
            'latest_net_pay_cents' => $this->latestNetPayCents,
            'latest_pay_date' => $this->latestPayDate?->toDateString(),
            'has_verified_bank_statement' => $this->hasVerifiedBankStatement,
            'payroll_deposits_cents' => $this->payrollDepositsCents,
            'overdraft_count' => $this->overdraftCount,
            'minimum_confidence' => $this->minimumConfidence,
            'estimated_monthly_payment_cents' => $this->estimatedMonthlyPaymentCents,
        ];
    }
}
