<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Transient: outage, timeout or upstream model error. The queue retries the job with backoff.
 */
class AiServiceUnavailable extends RuntimeException {}
