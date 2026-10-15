<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'applicant' => [
                'first_name' => 'Ayesha',
                'last_name' => 'Malik',
                'email' => 'ayesha@example.com',
                'phone' => '555-010-2000',
                'date_of_birth' => '1990-04-12',
                'ssn' => '900-12-3456',
            ],
            'amount_requested_cents' => 150_000,
            'term_months' => 12,
            'purpose' => 'car repair',
            'stated_monthly_income_cents' => 420_000,
        ], $overrides);
    }

    public function test_api_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withHeaders(['Authorization' => ''])
            ->getJson('/api/applications')
            ->assertUnauthorized();
    }

    public function test_it_creates_a_draft_application_with_its_applicant(): void
    {
        $response = $this->postJson('/api/applications', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.amount_requested_cents', 150_000)
            ->assertJsonPath('data.applicant.first_name', 'Ayesha')
            ->assertJsonPath('data.applicant.ssn_masked', '***-**-3456')
            ->assertJsonMissingPath('data.applicant.ssn')
            ->assertJsonMissingPath('data.applicant.date_of_birth');

        $this->assertDatabaseCount('applicants', 1);
        $this->assertDatabaseCount('loan_applications', 1);
    }

    public function test_ssn_and_date_of_birth_are_encrypted_at_rest(): void
    {
        $this->postJson('/api/applications', $this->validPayload())->assertCreated();

        $row = DB::table('applicants')->first();

        $this->assertNotSame('900123456', $row->ssn);
        $this->assertStringNotContainsString('3456', $row->ssn);
        $this->assertNotSame('1990-04-12', $row->date_of_birth);
        $this->assertSame('3456', $row->ssn_last4);

        $applicant = Applicant::first();
        $this->assertSame('900123456', $applicant->ssn);
        $this->assertSame('1990-04-12', $applicant->date_of_birth);
    }

    public function test_it_rejects_an_invalid_ssn(): void
    {
        $this->postJson('/api/applications', $this->validPayload(['applicant' => ['ssn' => '12-345']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('applicant.ssn');
    }

    public function test_it_rejects_applicants_under_eighteen(): void
    {
        $dob = now()->subYears(17)->format('Y-m-d');

        $this->postJson('/api/applications', $this->validPayload(['applicant' => ['date_of_birth' => $dob]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('applicant.date_of_birth');
    }

    public function test_it_rejects_out_of_range_amounts_and_terms(): void
    {
        $this->postJson('/api/applications', $this->validPayload([
            'amount_requested_cents' => 500,
            'term_months' => 120,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_requested_cents', 'term_months']);
    }

    public function test_it_lists_applications_newest_first_and_filters_by_status(): void
    {
        LoanApplication::factory()->count(2)->create();
        LoanApplication::factory()->submitted()->create();

        $this->getJson('/api/applications')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/applications?status=submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', ApplicationStatus::Submitted->value);
    }

    public function test_it_rejects_an_unknown_status_filter(): void
    {
        $this->getJson('/api/applications?status=approved-ish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_it_shows_one_application_with_masked_applicant(): void
    {
        $application = LoanApplication::factory()->create();

        $this->getJson("/api/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.applicant.ssn_masked', $application->applicant->maskedSsn());
    }

    public function test_it_returns_404_for_an_unknown_application(): void
    {
        $this->getJson('/api/applications/01J0000000000000000000000Z')->assertNotFound();
    }
}
