<?php

namespace App\Console\Commands;

use App\Services\Scheduling\WorkItemGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateWorkItems extends Command
{
    protected $signature = 'work-items:generate {--date= : Generate for a specific date (Y-m-d) instead of tomorrow}';

    protected $description = 'H-1 generation of concrete Work Items from active Task Checklists (idempotent).';

    public function handle(WorkItemGenerator $generator): int
    {
        if ($this->option('date')) {
            $date = Carbon::parse($this->option('date'));
            $count = $generator->generateForDate($date);
            $this->info("Generated {$count} work item(s) for {$date->toDateString()}.");

            return self::SUCCESS;
        }

        $today = now();
        $tomorrow = now()->addDay();
        $count = $generator->generateForDate($today) + $generator->generateForDate($tomorrow);
        $this->info("Generated {$count} work item(s) for {$today->toDateString()} and {$tomorrow->toDateString()}.");

        return self::SUCCESS;
    }
}
