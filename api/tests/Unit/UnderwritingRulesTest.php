<?php

namespace Tests\Unit;

use App\Enums\DecisionOutcome;
use App\Enums\RuleOutcome;
use App\Underwriting\Policies\PolicyV1;
use App\Underwriting\Rules\DocumentsReadable;
use App\Underwriting\Rules\ExtractionConfidence;
use App\Underwriting\Rules\MinimumVerifiedIncome;
use App\Underwriting\Rules\OverdraftHistory;
use App\Underwriting\Rules\PayDateRecent;
use App\Underwriting\Rules\PaymentToIncome;
use App\Underwriting\Rules\PayrollDepositMatches;
use App\Underwriting\Rules\PaystubVerified;
use App\Underwriting\Rules\StatedIncomeConsistent;
use App\Underwriting\RulesEngine;
use App\Underwriting\UnderwritingFacts;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class UnderwritingRulesTest extends TestCase
{
    /**
     * A clean, approvable applicant; each test overrides only what it is about.
     */
    private function facts(array $overrides = []): UnderwritingFacts
    {
        return new UnderwritingFacts(...array_merge([
            'requestedCents' => 150_000,
            'termMonths' => 12,
            'statedMonthlyIncomeCents' => 450_000,
            'submittedAt' => CarbonImmutable::parse('2026-10-01'),
            'documentsNeedingReview' => 0,
            'hasVerifiedPaystub' => true,
            'verifiedMonthlyGrossCents' => 456_517,
            'latestNetPayCents' => 157_795,
            'latestPayDate' => CarbonImmutable::parse('2026-09-19'),
            'hasVerifiedBankStatement' => true,
            'payrollDepositsCents' => [157_140, 157_795],
            'overdraftCount' => 1,
            'minimumConfidence' => 0.93,
            'estimatedMonthlyPaymentCents' => 15_070,
        ], $overrides));
    }

    public function test_a_clean_application_is_approved_with_every_rule_recorded(): void
    {
        $policy = new PolicyV1;

        $result = (new RulesEngine)->evaluate($policy, $this->facts());

        $this->assertSame(DecisionOutcome::Approve, $result['outcome']);
        $this->assertCount(count($policy->rules()), $result['results']);
        $this->assertSame([], array_filter($result['results'], fn ($r) => $r->outcome !== RuleOutcome::Pass));
    }

    public function test_any_fail_declines_even_when_other_rules_refer(): void
    {
        $result = (new RulesEngine)->evaluate(new PolicyV1, $this->facts([
            'verifiedMonthlyGrossCents' => 120_000,
            'documentsNeedingReview' => 1,
        ]));

        $this->assertSame(DecisionOutcome::Decline, $result['outcome']);
    }

    public function test_refers_without_fails_refer(): void
    {
        $result = (new RulesEngine)->evaluate(new PolicyV1, $this->facts(['overdraftCount' => 4]));

        $this->assertSame(DecisionOutcome::Refer, $result['outcome']);
    }

    public function test_skips_are_neutral(): void
    {
        $result = (new RulesEngine)->evaluate(new PolicyV1, $this->facts([
            'hasVerifiedBankStatement' => false,
            'payrollDepositsCents' => [],
            'overdraftCount' => null,
        ]));

        $this->assertSame(DecisionOutcome::Approve, $result['outcome']);
    }

    public function test_documents_needing_review_refer(): void
    {
        $this->assertSame(RuleOutcome::Refer, (new DocumentsReadable)->evaluate($this->facts(['documentsNeedingReview' => 2]))->outcome);
    }

    public function test_missing_paystub_or_frequency_refers(): void
    {
        $rule = new PaystubVerified;

        $this->assertSame(RuleOutcome::Refer, $rule->evaluate($this->facts(['hasVerifiedPaystub' => false, 'verifiedMonthlyGrossCents' => null]))->outcome);
        $this->assertSame(RuleOutcome::Refer, $rule->evaluate($this->facts(['verifiedMonthlyGrossCents' => null]))->outcome);
    }

    public function test_minimum_income_boundary(): void
    {
        $rule = new MinimumVerifiedIncome(150_000);

        $this->assertSame(RuleOutcome::Pass, $rule->evaluate($this->facts(['verifiedMonthlyGrossCents' => 150_000]))->outcome);
        $fail = $rule->evaluate($this->facts(['verifiedMonthlyGrossCents' => 149_999]));
        $this->assertSame(RuleOutcome::Fail, $fail->outcome);
        $this->assertStringContainsString('$1,499.99', $fail->message);
        $this->assertSame(RuleOutcome::Skip, $rule->evaluate($this->facts(['verifiedMonthlyGrossCents' => null]))->outcome);
    }

    public function test_only_overstated_income_is_referred(): void
    {
        $rule = new StatedIncomeConsistent(0.20);

        $this->assertSame(RuleOutcome::Pass, $rule->evaluate($this->facts(['statedMonthlyIncomeCents' => 300_000]))->outcome);
        $this->assertSame(RuleOutcome::Pass, $rule->evaluate($this->facts(['statedMonthlyIncomeCents' => 570_000]))->outcome);
        $this->assertSame(RuleOutcome::Refer, $rule->evaluate($this->facts(['statedMonthlyIncomeCents' => 600_000]))->outcome);
    }

    public function test_payment_to_income_bands(): void
    {
        $rule = new PaymentToIncome(referAbove: 0.15, failAbove: 0.25);
        $income = ['verifiedMonthlyGrossCents' => 100_000];

        $this->assertSame(RuleOutcome::Pass, $rule->evaluate($this->facts($income + ['estimatedMonthlyPaymentCents' => 15_000]))->outcome);
        $this->assertSame(RuleOutcome::Refer, $rule->evaluate($this->facts($income + ['estimatedMonthlyPaymentCents' => 20_000]))->outcome);
        $this->assertSame(RuleOutcome::Fail, $rule->evaluate($this->facts($income + ['estimatedMonthlyPaymentCents' => 26_000]))->outcome);
    }

    public function test_stale_or_future_pay_dates_refer(): void
    {
        $rule = new PayDateRecent(45);

        $this->assertSame(RuleOutcome::Pass, $rule->evaluate($this->facts())->outcome);
        $this->assertSame(RuleOutcome::Refer, $rule->evaluate($this->facts(['latestPayDate' => CarbonImmutable::parse('2026-07-01')]))->outcome);
        $this->assertSame(RuleOutcome::Refer, $rule->evaluate($this->facts(['latestPayDate' => CarbonImmutable::parse('2026-10-20')]))->outcome);
    }

    public function test_payroll_deposit_must_match_net_pay_within_tolerance(): void
    {
        $rule = new PayrollDepositMatches(0.05);

        $this->assertSame(RuleOutcome::Pass, $rule->evaluate($this->facts(['payrollDepositsCents' => [150_000]]))->outcome);
        $this->assertSame(RuleOutcome::Refer, $rule->evaluate($this->facts(['payrollDepositsCents' => [120_000]]))->outcome);
        $this->assertSame(RuleOutcome::Skip, $rule->evaluate($this->facts(['hasVerifiedBankStatement' => false]))->outcome);
    }

    public function test_overdrafts_and_low_confidence_refer_but_never_decline(): void
    {
        $this->assertSame(RuleOutcome::Refer, (new OverdraftHistory(3))->evaluate($this->facts(['overdraftCount' => 3]))->outcome);
        $this->assertSame(RuleOutcome::Refer, (new ExtractionConfidence(0.7))->evaluate($this->facts(['minimumConfidence' => 0.69]))->outcome);
    }
}
