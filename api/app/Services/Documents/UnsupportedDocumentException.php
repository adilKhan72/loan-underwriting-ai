<?php

namespace App\Services\Documents;

use RuntimeException;

/**
 * The document can't be machine-read. Permanent: retrying won't help, a person has to look at it.
 */
class UnsupportedDocumentException extends RuntimeException {}
