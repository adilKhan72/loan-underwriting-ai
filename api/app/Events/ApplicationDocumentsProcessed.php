<?php

namespace App\Events;

use App\Models\LoanApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Every document of a submitted application has reached a final state (extracted, needs review or failed).
 * The rules engine (milestone 5) listens for this to make the decision.
 */
class ApplicationDocumentsProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(public LoanApplication $application) {}
}
