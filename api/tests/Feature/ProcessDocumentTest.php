<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ExtractionStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Services\Ai\AiServiceUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\MakesPdfs;
use Tests\TestCase;

class ProcessDocumentTest extends TestCase
{
    use MakesPdfs, RefreshDatabase;

    private const PAYSTUB_LINES = [
        'ACME LOGISTICS INC. EARNINGS STATEMENT',
        'Employee SSN: 900-12-3456   Email: jane.doe@example.com',
        'Pay Period: 09/01/2026 - 09/14/2026   Pay Date: 09/19/2026',
        'GROSS PAY 2,107.00   NET PAY 1,577.95',
        'Direct deposit to Account No. 0012345678',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function storedDocument(string $content, string $mime = 'application/pdf'): Document
    {
        $application = LoanApplication::factory()->submitted()->create();
        $path = "applications/{$application->id}/stub.pdf";
        Storage::disk('local')->put($path, $content);

        return Document::factory()->for($application)->create([
            'type' => DocumentType::Paystub,
            'path' => $path,
            'mime_type' => $mime,
        ]);
    }

    private function aiReturns(array $body, int $status = 200): void
    {
        Http::fake(['*/v1/extract' => Http::response($body, $status)]);
    }

    private function okResult(): array
    {
        return [
            'status' => 'ok',
            'document_type' => 'paystub',
            'data' => ['gross_pay_cents' => 210700, 'net_pay_cents' => 157795],
            'errors' => [],
            'attempts' => 1,
            'provider' => 'fixture',
        ];
    }

    private function process(Document $document): void
    {
        $this->app->call([new ProcessDocument($document), 'handle']);
    }

    public function test_it_extracts_a_pdf_and_sends_only_redacted_text(): void
    {
        $document = $this->storedDocument($this->makePdf(self::PAYSTUB_LINES));
        $this->aiReturns($this->okResult());

        $this->process($document);

        Http::assertSent(function (Request $request) {
            $text = $request['text'];

            return $request['document_type'] === 'paystub'
                && str_contains($text, 'GROSS PAY 2,107.00')
                && str_contains($text, '[SSN]') && ! str_contains($text, '900-12-3456')
                && str_contains($text, '[EMAIL]') && ! str_contains($text, 'jane.doe')
                && str_contains($text, '[ACCOUNT_NUMBER]') && ! str_contains($text, '0012345678');
        });

        $document->refresh();
        $this->assertSame(DocumentStatus::Extracted, $document->status);
        $this->assertSame(ApplicationStatus::Processing, $document->loanApplication->status);

        $extraction = $document->latestExtraction;
        $this->assertSame(ExtractionStatus::Ok, $extraction->status);
        $this->assertSame(210700, $extraction->data['gross_pay_cents']);
        $this->assertSame(['SSN' => 1, 'ACCOUNT_NUMBER' => 1, 'EMAIL' => 1], $extraction->redactions);
        $this->assertSame('fixture', $extraction->provider);
    }

    public function test_a_needs_review_answer_is_stored_as_needs_review(): void
    {
        $document = $this->storedDocument($this->makePdf(self::PAYSTUB_LINES));
        $this->aiReturns(['status' => 'needs_review', 'data' => null, 'errors' => ['confidence: too high'], 'attempts' => 2, 'provider' => 'fixture'] + ['document_type' => 'paystub']);

        $this->process($document);

        $this->assertSame(DocumentStatus::NeedsReview, $document->fresh()->status);
        $this->assertSame(['confidence: too high'], $document->latestExtraction->errors);
    }

    public function test_images_go_to_review_without_calling_the_ai_service(): void
    {
        $document = $this->storedDocument('fake-png-bytes', 'image/png');
        Http::fake();

        $this->process($document);

        Http::assertNothingSent();
        $this->assertSame(DocumentStatus::NeedsReview, $document->fresh()->status);
        $this->assertStringContainsString('OCR', $document->latestExtraction->errors[0]);
    }

    public function test_a_pdf_without_a_text_layer_goes_to_review(): void
    {
        $document = $this->storedDocument($this->makePdf(['']));
        Http::fake();

        $this->process($document);

        Http::assertNothingSent();
        $this->assertSame(DocumentStatus::NeedsReview, $document->fresh()->status);
    }

    public function test_an_ai_outage_is_rethrown_so_the_queue_retries(): void
    {
        $document = $this->storedDocument($this->makePdf(self::PAYSTUB_LINES));
        $this->aiReturns(['detail' => 'anthropic unreachable'], 502);

        try {
            $this->process($document);
            $this->fail('Expected AiServiceUnavailable');
        } catch (AiServiceUnavailable $e) {
            $this->assertStringContainsString('anthropic unreachable', $e->getMessage());
        }

        $this->assertSame(DocumentStatus::Processing, $document->fresh()->status);
        $this->assertSame(0, $document->extractions()->count());
    }

    public function test_a_rejected_request_fails_the_document_without_retrying(): void
    {
        $document = $this->storedDocument($this->makePdf(self::PAYSTUB_LINES));
        $this->aiReturns(['detail' => 'bad'], 422);

        $this->process($document);

        $this->assertSame(DocumentStatus::Failed, $document->fresh()->status);
    }

    public function test_exhausted_retries_are_recorded_for_the_underwriter(): void
    {
        $document = $this->storedDocument($this->makePdf(self::PAYSTUB_LINES));

        (new ProcessDocument($document))->failed(new RuntimeException('ai-service returned 502'));

        $this->assertSame(DocumentStatus::Failed, $document->fresh()->status);
        $this->assertStringContainsString('after retries', $document->latestExtraction->errors[0]);
    }

    public function test_the_job_retries_transient_failures_with_backoff(): void
    {
        $job = new ProcessDocument(Document::factory()->create());

        $this->assertSame(4, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff);
    }
}
