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
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now()->addDay();

        $count = $generator->generateForDate($date);

        $this->info("Generated {$count} work item(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
