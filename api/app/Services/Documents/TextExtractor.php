<?php

namespace App\Services\Documents;

use App\Models\Document;

interface TextExtractor
{
    /**
     * @throws UnsupportedDocumentException when the document's text can't be read (e.g. a scanned image)
     */
    public function extract(Document $document): string;
}
