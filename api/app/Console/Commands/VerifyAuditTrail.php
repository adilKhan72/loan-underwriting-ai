<?php

namespace App\Console\Commands;

use App\Audit\AuditLog;
use App\Models\AuditEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audit:verify {application? : Verify one application\'s trail; omit for all}')]
#[Description('Re-computes audit hash chains and reports any event that was altered or removed.')]
class VerifyAuditTrail extends Command
{
    public function handle(AuditLog $audit): int
    {
        $ids = $this->argument('application')
            ? [$this->argument('application')]
            : AuditEvent::query()->distinct()->pluck('loan_application_id')->all();

        $broken = 0;
        foreach ($ids as $id) {
            if ($eventId = $audit->firstBrokenEvent($id)) {
                $broken++;
                $this->error("Application {$id}: chain broken at event {$eventId}");
            }
        }

        if ($broken > 0) {
            return self::FAILURE;
        }

        $this->info('Verified '.count($ids).' audit trail(s): intact.');

        return self::SUCCESS;
    }
}
