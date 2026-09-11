<?php

namespace App\Console\Commands;

use App\Models\Finding;
use App\Models\SlaInstance;
use App\Services\FindingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Escalates findings whose Manager SLA has expired to ISO, recorded
 * independently from the underlying PIC finding (docs/02-BUSINESS-RULES.md §8).
 */
class EvaluateManagerSla extends Command
{
    protected $signature = 'findings:evaluate-sla';

    protected $description = 'Escalate findings whose Manager SLA has expired to ISO.';

    public function handle(FindingService $findings): int
    {
        $now = now();

        $breached = SlaInstance::query()
            ->where('sla_type', SlaInstance::TYPE_MANAGER)
            ->where('status', SlaInstance::STATUS_RUNNING)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->get();

        foreach ($breached as $instance) {
            DB::transaction(function () use ($instance, $now, $findings) {
                $instance->update(['status' => SlaInstance::STATUS_BREACHED, 'breached_at' => $now]);

                $finding = $instance->finding;

                if ($finding && $finding->status !== Finding::STATUS_ESCALATED) {
                    $finding->update(['status' => Finding::STATUS_ESCALATED]);
                    $findings->startIsoSla($finding);
                }
            });
        }

        $this->info("Escalated {$breached->count()} finding(s) to ISO.");

        return self::SUCCESS;
    }
}
