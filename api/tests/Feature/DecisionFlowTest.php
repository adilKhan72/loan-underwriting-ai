<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\DecisionOutcome;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ExplanationStatus;
use App\Events\ApplicationDocumentsProcessed;
use App\Jobs\ExplainDecision;
use App\Listeners\MakeDecision;
use App\Models\Decision;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\User;
use App\Underwriting\FactsBuilder;
use App\Underwriting\Policies\PolicyV1;
use App\Underwriting\RulesEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DecisionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function application(int $grossPerPeriod = 210_700, ?array $bankStatement = null): LoanApplication
    {
        $application = LoanApplication::factory()->create([
            'status' => ApplicationStatus::Processing,
            'submitted_at' => '2026-10-01 10:00:00',
            'amount_requested_cents' => 150_000,
            'term_months' => 12,
            'stated_monthly_income_cents' => 450_000,
        ]);

        $paystub = Document::factory()->for($application)->create(['type' => DocumentType::Paystub]);
        $paystub->forceFill(['status' => DocumentStatus::Extracted])->save();
        $paystub->extractions()->create(['status' => 'ok', 'attempts' => 1, 'provider' => 'fixture', 'data' => [
            'pay_frequency' => 'biweekly',
            'gross_pay_cents' => $grossPerPeriod,
            'net_pay_cents' => 157_795,
            'pay_date' => '2026-09-19',
            'confidence' => 0.96,
        ]]);

        if ($bankStatement !== null) {
            $statement = Document::factory()->bankStatement()->for($application)->create();
            $statement->forceFill(['status' => DocumentStatus::Extracted])->save();
            $statement->extractions()->create(['status' => 'ok', 'attempts' => 1, 'provider' => 'fixture', 'data' => $bankStatement]);
        }

        return $application;
    }

    private function decide(LoanApplication $application): Decision
    {
        $this->app->make(MakeDecision::class)->handle(new ApplicationDocumentsProcessed($application));

        return $application->fresh()->latestDecision;
    }

    public function test_the_listener_is_registered_for_the_processing_event(): void
    {
        Event::fake();

        Event::assertListening(ApplicationDocumentsProcessed::class, MakeDecision::class);
    }

    public function test_facts_are_computed_from_validated_extractions(): void
    {
        $application = $this->application(bankStatement: [
            'deposits' => [
                ['amount_cents' => 157_140, 'likely_payroll' => true],
                ['amount_cents' => 6_000, 'likely_payroll' => false],
            ],
            'overdraft_or_nsf_count' => 1,
            'confidence' => 0.93,
        ]);

        $facts = (new FactsBuilder)->build($application, (new PolicyV1)->annualRate());

        $this->assertSame(456_517, $facts->verifiedMonthlyGrossCents); // 210,700 × 26 / 12
        $this->assertSame([157_140], $facts->payrollDepositsCents);
        $this->assertSame(1, $facts->overdraftCount);
        $this->assertSame(0.93, $facts->minimumConfidence);
        $this->assertSame(15_070, $facts->estimatedMonthlyPaymentCents); // $1,500 over 12 months at 36% APR
    }

    public function test_an_approvable_application_is_approved_and_explanation_queued(): void
    {
        Queue::fake();
        $application = $this->application();

        $decision = $this->decide($application);

        $this->assertSame(DecisionOutcome::Approve, $decision->outcome);
        $this->assertSame('v1', $decision->policy_version);
        $this->assertSame(ExplanationStatus::Pending, $decision->explanation_status);
        $this->assertSame(456_517, $decision->facts['verified_monthly_gross_cents']);
        $this->assertSame(ApplicationStatus::Decided, $application->fresh()->status);
        Queue::assertPushed(ExplainDecision::class);
    }

    public function test_low_income_is_declined_with_the_failing_rule_recorded(): void
    {
        Queue::fake();

        $decision = $this->decide($this->application(grossPerPeriod: 50_000));

        $this->assertSame(DecisionOutcome::Decline, $decision->outcome);
        $failing = collect($decision->rule_results)->firstWhere('outcome', 'fail');
        $this->assertSame('MIN_VERIFIED_INCOME', $failing['code']);
    }

    public function test_a_redelivered_event_does_not_create_a_second_decision(): void
    {
        Queue::fake();
        $application = $this->application();

        $this->decide($application);
        $this->decide($application);

        $this->assertSame(1, $application->decisions()->count());
    }

    public function test_a_decision_replays_identically_from_its_stored_facts(): void
    {
        Queue::fake();
        $decision = $this->decide($this->application());

        $replayed = (new RulesEngine)->evaluate(new PolicyV1, (new FactsBuilder)->build($decision->loanApplication, 0.36));

        $this->assertSame($decision->outcome, $replayed['outcome']);
        $this->assertSame($decision->rule_results, array_map(fn ($r) => $r->toArray(), $replayed['results']));
    }

    public function test_explanation_is_stored_when_the_ai_service_answers(): void
    {
        Queue::fake();
        $decision = $this->decide($this->application(grossPerPeriod: 50_000));
        Http::fake(['*/v1/explain' => Http::response([
            'underwriter_summary' => 'Declined: MIN_VERIFIED_INCOME failed.',
            'applicant_message' => 'We are unable to approve your loan because your verified income is too low.',
            'principal_reasons' => ['Verified income is too low.'],
            'provider' => 'fixture',
        ])]);

        $this->app->call([new ExplainDecision($decision), 'handle']);

        Http::assertSent(fn ($r) => $r['outcome'] === 'decline' && $r['policy_version'] === 'v1'
            && ! array_key_exists('values', $r['rule_results'][0]));
        $decision->refresh();
        $this->assertSame(ExplanationStatus::Ready, $decision->explanation_status);
        $this->assertSame(['Verified income is too low.'], $decision->principal_reasons);
    }

    public function test_explanation_failures_never_touch_the_decision(): void
    {
        Queue::fake();
        $decision = $this->decide($this->application());
        Http::fake(['*/v1/explain' => Http::response(['detail' => 'explanation unavailable'], 422)]);

        $this->app->call([new ExplainDecision($decision), 'handle']);

        $decision->refresh();
        $this->assertSame(ExplanationStatus::Unavailable, $decision->explanation_status);
        $this->assertSame(DecisionOutcome::Approve, $decision->outcome);
    }

    public function test_the_api_returns_the_decision_with_rule_results(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());
        $application = $this->application(grossPerPeriod: 50_000);
        $this->decide($application);

        $this->getJson("/api/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'decided')
            ->assertJsonPath('data.decision.outcome', 'decline')
            ->assertJsonPath('data.decision.policy_version', 'v1')
            ->assertJsonPath('data.decision.explanation.status', 'pending')
            ->assertJsonCount(count((new PolicyV1)->rules()), 'data.decision.rule_results');
    }
}
