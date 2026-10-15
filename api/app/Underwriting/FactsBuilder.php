<?php

namespace App\Underwriting;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\LoanApplication;
use Carbon\CarbonImmutable;

class FactsBuilder
{
    private const PERIODS_PER_YEAR = [
        'weekly' => 52,
        'biweekly' => 26,
        'semimonthly' => 24,
        'monthly' => 12,
    ];

    public function build(LoanApplication $application, float $annualRate): UnderwritingFacts
    {
        $documents = $application->documents()->with('latestExtraction')->get();

        $verified = $documents->filter(
            fn (Document $d) => $d->status === DocumentStatus::Extracted && $d->latestExtraction?->status === ExtractionStatus::Ok
        );

        $paystubs = $verified->where('type', DocumentType::Paystub)->map(fn (Document $d) => $d->latestExtraction->data);
        $statements = $verified->where('type', DocumentType::BankStatement)->map(fn (Document $d) => $d->latestExtraction->data);

        // The most recent paystub is the best evidence of current income.
        $latest = $paystubs->sortByDesc(fn (array $p) => $p['pay_date'] ?? '')->first();

        $confidences = $verified->map(fn (Document $d) => $d->latestExtraction->data['confidence'] ?? null)->filter(fn ($c) => $c !== null);

        return new UnderwritingFacts(
            requestedCents: $application->amount_requested_cents,
            termMonths: $application->term_months,
            statedMonthlyIncomeCents: $application->stated_monthly_income_cents,
            submittedAt: CarbonImmutable::parse($application->submitted_at ?? now()),
            documentsNeedingReview: $documents->filter(fn (Document $d) => in_array($d->status, [DocumentStatus::NeedsReview, DocumentStatus::Failed], true))->count(),
            hasVerifiedPaystub: $latest !== null,
            verifiedMonthlyGrossCents: $latest ? $this->monthlyGross($latest) : null,
            latestNetPayCents: $latest['net_pay_cents'] ?? null,
            latestPayDate: isset($latest['pay_date']) ? CarbonImmutable::parse($latest['pay_date']) : null,
            hasVerifiedBankStatement: $statements->isNotEmpty(),
            payrollDepositsCents: $statements->flatMap(fn (array $s) => collect($s['deposits'] ?? [])->where('likely_payroll', true)->pluck('amount_cents'))->values()->all(),
            overdraftCount: $statements->isEmpty() ? null : (int) $statements->sum(fn (array $s) => $s['overdraft_or_nsf_count'] ?? 0),
            minimumConfidence: $confidences->isEmpty() ? null : (float) $confidences->min(),
            estimatedMonthlyPaymentCents: $this->monthlyPayment($application->amount_requested_cents, $application->term_months, $annualRate),
        );
    }

    /**
     * Gross pay per period annualised by pay frequency. Unknown frequency → null: we don't guess.
     *
     * @param  array<string, mixed>  $paystub
     */
    private function monthlyGross(array $paystub): ?int
    {
        $periods = self::PERIODS_PER_YEAR[$paystub['pay_frequency'] ?? ''] ?? null;

        if ($periods === null || ! isset($paystub['gross_pay_cents'])) {
            return null;
        }

        return (int) round($paystub['gross_pay_cents'] * $periods / 12);
    }

    /**
     * Standard amortising payment. An estimate for affordability checks, not a disclosure figure.
     */
    private function monthlyPayment(int $principalCents, int $months, float $annualRate): int
    {
        $rate = $annualRate / 12;

        if ($rate == 0.0) {
            return (int) ceil($principalCents / $months);
        }

        return (int) ceil($principalCents * $rate / (1 - (1 + $rate) ** -$months));
    }
}
