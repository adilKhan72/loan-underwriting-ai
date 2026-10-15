<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Permanent: ai-service refused the input (4xx). Retrying the same request can't succeed.
 */
class AiServiceRejected extends RuntimeException {}
