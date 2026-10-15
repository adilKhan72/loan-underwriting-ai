<?php

namespace App\Http\Controllers\Api;

use App\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditEventResource;
use App\Models\AuditEvent;
use App\Models\LoanApplication;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApplicationAuditController extends Controller
{
    public function __invoke(LoanApplication $application, AuditLog $audit): AnonymousResourceCollection
    {
        $events = AuditEvent::query()->where('loan_application_id', $application->id)->orderBy('position')->get();

        return AuditEventResource::collection($events)->additional([
            'chain_intact' => $audit->firstBrokenEvent($application->id) === null,
        ]);
    }
}
