<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());
    }

    private function pdf(string $content = '%PDF-1.7 paystub'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('paystub.pdf', $content)->mimeType('application/pdf');
    }

    public function test_it_stores_an_uploaded_document_privately_with_its_hash(): void
    {
        $application = LoanApplication::factory()->create();
        $file = $this->pdf();

        $response = $this->postJson("/api/applications/{$application->id}/documents", [
            'type' => 'paystub',
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'paystub')
            ->assertJsonPath('data.status', 'uploaded')
            ->assertJsonPath('data.original_name', 'paystub.pdf')
            ->assertJsonPath('data.sha256', hash('sha256', '%PDF-1.7 paystub'))
            ->assertJsonMissingPath('data.path');

        $document = Document::first();
        $this->assertStringStartsWith("applications/{$application->id}/", $document->path);
        $this->assertStringNotContainsString('paystub', basename($document->path));
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_the_same_file_cannot_be_attached_twice(): void
    {
        $application = LoanApplication::factory()->create();

        $this->postJson("/api/applications/{$application->id}/documents", ['type' => 'paystub', 'file' => $this->pdf()])
            ->assertCreated();

        $this->postJson("/api/applications/{$application->id}/documents", ['type' => 'paystub', 'file' => $this->pdf()])
            ->assertConflict();

        $this->assertDatabaseCount('documents', 1);
    }

    public function test_it_rejects_unsupported_file_types(): void
    {
        $application = LoanApplication::factory()->create();
        $file = UploadedFile::fake()->createWithContent('notes.txt', 'hello')->mimeType('text/plain');

        $this->postJson("/api/applications/{$application->id}/documents", ['type' => 'paystub', 'file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_it_rejects_unknown_document_types(): void
    {
        $application = LoanApplication::factory()->create();

        $this->postJson("/api/applications/{$application->id}/documents", ['type' => 'selfie', 'file' => $this->pdf()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_documents_cannot_be_added_after_submission(): void
    {
        $application = LoanApplication::factory()->submitted()->create();

        $this->postJson("/api/applications/{$application->id}/documents", ['type' => 'paystub', 'file' => $this->pdf()])
            ->assertConflict();
    }

    public function test_submitting_requires_at_least_one_paystub(): void
    {
        $application = LoanApplication::factory()->create();
        Document::factory()->bankStatement()->for($application)->create();

        $this->postJson("/api/applications/{$application->id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents');

        $this->assertSame('draft', $application->fresh()->status->value);
    }

    public function test_submitting_locks_the_application(): void
    {
        $application = LoanApplication::factory()->create();
        Document::factory()->for($application)->create(['type' => DocumentType::Paystub]);

        $this->postJson("/api/applications/{$application->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonCount(1, 'data.documents');

        $this->assertNotNull($application->fresh()->submitted_at);
    }

    public function test_an_application_cannot_be_submitted_twice(): void
    {
        $application = LoanApplication::factory()->submitted()->create();

        $this->postJson("/api/applications/{$application->id}/submit")->assertConflict();
    }
}
