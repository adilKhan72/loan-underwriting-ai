<?php

namespace App\Jobs;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceRejected;
use App\Services\Documents\TextExtractor;
use App\Services\Documents\UnsupportedDocumentException;
use App\Support\Pii\PiiRedactor;
use App\Support\Pii\RedactionResult;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * read text → redact PII → ai-service /v1/extract → store the result.
 *
 * Permanent problems (unreadable file, rejected input) end in a final status straight away.
 * Only AiServiceUnavailable escapes handle(), so only real outages use the retry budget.
 */
class ProcessDocument implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(public Document $document) {}

    public function handle(TextExtractor $extractor, PiiRedactor $redactor, AiServiceClient $ai): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $document = $this->document;
        $application = $document->loanApplication;

        if ($application->status === ApplicationStatus::Submitted) {
            $application->forceFill(['status' => ApplicationStatus::Processing])->save();
        }

        $this->setStatus(DocumentStatus::Processing);

        try {
            $text = $extractor->extract($document);
        } catch (UnsupportedDocumentException $e) {
            $this->finish(DocumentStatus::NeedsReview, ExtractionStatus::NeedsReview, errors: [$e->getMessage()]);

            return;
        }

        $redaction = $redactor->redact($text);

        try {
            $result = $ai->extract($document->type, $redaction->text);
        } catch (AiServiceRejected $e) {
            $this->finish(DocumentStatus::Failed, ExtractionStatus::NeedsReview, errors: [$e->getMessage()], redaction: $redaction);

            return;
        }

        $ok = $result['status'] === ExtractionStatus::Ok->value;

        $this->finish(
            $ok ? DocumentStatus::Extracted : DocumentStatus::NeedsReview,
            $ok ? ExtractionStatus::Ok : ExtractionStatus::NeedsReview,
            data: $result['data'],
            errors: $result['errors'],
            redaction: $redaction,
            provider: $result['provider'],
            attempts: $result['attempts'],
        );
    }

    /**
     * Retries exhausted on a transient error: record it so the application can still be completed.
     */
    public function failed(?Throwable $exception): void
    {
        $this->finish(
            DocumentStatus::Failed,
            ExtractionStatus::NeedsReview,
            errors: ['Processing failed after retries: '.($exception?->getMessage() ?? 'unknown error')],
        );
    }

    private function setStatus(DocumentStatus $status): void
    {
        $this->document->forceFill(['status' => $status])->save();
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @param  list<string>  $errors
     */
    private function finish(
        DocumentStatus $documentStatus,
        ExtractionStatus $extractionStatus,
        ?array $data = null,
        array $errors = [],
        ?RedactionResult $redaction = null,
        ?string $provider = null,
        int $attempts = 0,
    ): void {
        $this->document->extractions()->create([
            'status' => $extractionStatus,
            'data' => $data,
            'errors' => $errors,
            'redactions' => $redaction?->counts,
            'provider' => $provider,
            'attempts' => $attempts,
        ]);

        $this->setStatus($documentStatus);
    }
}
