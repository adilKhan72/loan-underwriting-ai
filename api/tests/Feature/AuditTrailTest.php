<?php

namespace Tests\Feature;

use App\Audit\AuditLog;
use App\Events\ApplicationDocumentsProcessed;
use App\Jobs\ExplainDecision;
use App\Jobs\ProcessDocument;
use App\Listeners\MakeDecision;
use App\Models\AuditEvent;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Support\MakesPdfs;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use MakesPdfs, RefreshDatabase;

    private function audit(): AuditLog
    {
        return $this->app->make(AuditLog::class);
    }

    public function test_events_form_a_hash_chain_per_application(): void
    {
        $application = LoanApplication::factory()->create();
        $other = LoanApplication::factory()->create();

        $first = $this->audit()->record($application, 'application.created', ['term_months' => 12]);
        $this->audit()->record($other, 'application.created');
        $second = $this->audit()->record($application, 'application.submitted', ['document_count' => 1]);

        $this->assertNull($first->prev_hash);
        $this->assertSame([1, 2], [$first->position, $second->position]);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertNull($this->audit()->firstBrokenEvent($application->id));
        $this->assertNull($this->audit()->firstBrokenEvent($other->id));
    }

    public function test_the_hash_does_not_depend_on_key_order(): void
    {
        $base = ['id' => '01j', 'loan_application_id' => 'a', 'position' => 1, 'event' => 'e', 'actor_type' => 'system', 'actor_id' => null, 'prev_hash' => null];

        $this->assertSame(
            AuditLog::hash($base + ['data' => ['b' => 1, 'a' => ['y' => 2, 'x' => 1]]]),
            AuditLog::hash($base + ['data' => ['a' => ['x' => 1, 'y' => 2], 'b' => 1]]),
        );
    }

    public function test_the_model_refuses_updates_and_deletes(): void
    {
        $event = $this->audit()->record(LoanApplication::factory()->create(), 'application.created');

        try {
            $event->update(['event' => 'rewritten']);
            $this->fail('update should be refused');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_tampering_is_detected_and_reported(): void
    {
        $application = LoanApplication::factory()->create();
        $this->audit()->record($application, 'application.created');
        $decision = $this->audit()->record($application, 'decision.made', ['outcome' => 'decline']);
        $this->audit()->record($application, 'decision.explained', ['status' => 'ready']);

        if (DB::getDriverName() === 'pgsql') {
            // The trigger blocks even raw SQL...
            try {
                DB::table('audit_events')->where('id', $decision->id)->update(['data' => '{"outcome":"approve"}']);
                $this->fail('PostgreSQL trigger should block updates');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
            // ...so simulate a superuser who disables it; the chain must still expose the edit.
            DB::statement('ALTER TABLE audit_events DISABLE TRIGGER audit_events_no_update_delete');
        }

        DB::table('audit_events')->where('id', $decision->id)->update(['data' => '{"outcome":"approve"}']);

        $this->assertSame($decision->id, $this->audit()->firstBrokenEvent($application->id));
        $this->artisan('audit:verify', ['application' => $application->id])
            ->expectsOutputToContain("chain broken at event {$decision->id}")
            ->assertFailed();
    }

    public function test_verify_command_passes_on_intact_trails(): void
    {
        $this->audit()->record(LoanApplication::factory()->create(), 'application.created');

        $this->artisan('audit:verify')->expectsOutputToContain('intact')->assertSuccessful();
    }

    public function test_the_full_flow_is_audited_without_any_pii(): void
    {
        Storage::fake('local');
        Bus::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/applications', [
            'applicant' => [
                'first_name' => 'Ayesha', 'last_name' => 'Malik', 'email' => 'ayesha@example.com',
                'date_of_birth' => '1990-04-12', 'ssn' => '900-12-3456',
            ],
            'amount_requested_cents' => 150_000, 'term_months' => 12, 'stated_monthly_income_cents' => 450_000,
        ])->json('data.id');

        $pdf = UploadedFile::fake()->createWithContent('stub.pdf', $this->makePdf([
            'ACME PAYSTUB  SSN: 900-12-3456  GROSS PAY 2,107.00 NET PAY 1,577.95',
        ]))->mimeType('application/pdf');
        $this->postJson("/api/applications/{$id}/documents", ['type' => 'paystub', 'file' => $pdf])->assertCreated();
        $this->postJson("/api/applications/{$id}/submit")->assertOk();

        $application = LoanApplication::findOrFail($id);
        Http::fake([
            '*/v1/extract' => Http::response(['status' => 'ok', 'data' => [
                'pay_frequency' => 'biweekly', 'gross_pay_cents' => 210_700, 'net_pay_cents' => 157_795,
                'pay_date' => now()->subDays(5)->toDateString(), 'confidence' => 0.95,
            ], 'errors' => [], 'attempts' => 1, 'provider' => 'fixture']),
            '*/v1/explain' => Http::response([
                'underwriter_summary' => 'Approved under policy v1; all rules passed.',
                'applicant_message' => 'Good news: your loan application has been approved.',
                'principal_reasons' => [], 'provider' => 'fixture',
            ]),
        ]);
        $this->app->call([new ProcessDocument($application->documents()->first()), 'handle']);
        $this->app->make(MakeDecision::class)->handle(new ApplicationDocumentsProcessed($application));
        $this->app->call([new ExplainDecision($application->fresh()->latestDecision), 'handle']);

        $response = $this->getJson("/api/applications/{$id}/audit")->assertOk()->assertJsonPath('chain_intact', true);

        $this->assertSame(
            ['application.created', 'document.uploaded', 'application.submitted', 'document.processed', 'decision.made', 'decision.explained'],
            array_column($response->json('data'), 'event'),
        );
        $this->assertSame('user', $response->json('data.0.actor.type'));
        $this->assertSame('system', $response->json('data.3.actor.type'));
        $this->assertSame(['SSN' => 1], $response->json('data.3.data.redactions'));

        $everything = json_encode(AuditEvent::all()->toArray());
        foreach (['900-12-3456', '900123456', 'Ayesha', 'Malik', 'ayesha@example.com', '1990-04-12'] as $pii) {
            $this->assertStringNotContainsString($pii, $everything);
        }
    }

    public function test_a_deleted_event_is_detected_as_a_gap(): void
    {
        $application = LoanApplication::factory()->create();
        $this->audit()->record($application, 'application.created');
        $middle = $this->audit()->record($application, 'application.submitted');
        $last = $this->audit()->record($application, 'decision.made');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_events DISABLE TRIGGER audit_events_no_update_delete');
        }
        DB::table('audit_events')->where('id', $middle->id)->delete();

        $this->assertSame($last->id, $this->audit()->firstBrokenEvent($application->id));
    }
}
