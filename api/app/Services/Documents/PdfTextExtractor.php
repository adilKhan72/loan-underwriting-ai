<?php

namespace App\Services\Documents;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfTextExtractor implements TextExtractor
{
    /**
     * Below this many characters a PDF is almost certainly a scan with no text layer.
     */
    private const MIN_TEXT_LENGTH = 20;

    public function __construct(private readonly Parser $parser = new Parser) {}

    public function extract(Document $document): string
    {
        if ($document->mime_type !== 'application/pdf') {
            // Images need OCR, which isn't built yet; they go to a person instead of failing silently.
            throw new UnsupportedDocumentException("No text extraction for {$document->mime_type} yet (OCR not implemented).");
        }

        try {
            $text = $this->parser->parseContent(Storage::disk($document->disk)->get($document->path))->getText();
        } catch (Throwable $e) {
            throw new UnsupportedDocumentException('PDF could not be parsed: '.$e->getMessage(), previous: $e);
        }

        $text = trim(preg_replace("/[ \t]+/", ' ', $text));

        if (mb_strlen($text) < self::MIN_TEXT_LENGTH) {
            throw new UnsupportedDocumentException('PDF has no usable text layer (likely a scan).');
        }

        return $text;
    }
}
