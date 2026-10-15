<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\Documents\TextExtractor;
use App\Services\Documents\UnsupportedDocumentException;
use App\Support\Pii\PiiRedactor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('pii:preview {path : Path to a PDF on disk} {--counts : Also print what was redacted, by type}')]
#[Description('Prints exactly the text the AI service would receive for a document, after extraction and redaction.')]
class PiiPreview extends Command
{
    public function handle(TextExtractor $extractor, PiiRedactor $redactor): int
    {
        $source = $this->argument('path');
        if (! is_file($source)) {
            $this->error("No such file: {$source}");

            return self::FAILURE;
        }

        // Same code path as ProcessDocument: copy onto the documents disk and extract from there.
        $path = 'pii-preview/'.Str::ulid().'.pdf';
        Storage::disk('local')->put($path, file_get_contents($source));

        try {
            $result = $redactor->redact($extractor->extract(new Document([
                'disk' => 'local', 'path' => $path, 'mime_type' => 'application/pdf',
            ])));
        } catch (UnsupportedDocumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            Storage::disk('local')->delete($path);
        }

        $this->output->write($result->text);

        if ($this->option('counts')) {
            $this->newLine();
            $this->comment(json_encode($result->counts));
        }

        return self::SUCCESS;
    }
}
