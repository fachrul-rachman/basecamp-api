<?php

namespace App\Console\Commands;

use App\Services\ComplianceScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class LockMonthlyScores extends Command
{
    protected $signature = 'scores:lock-month {--month= : Lock a specific month (Y-m) instead of last month}';

    protected $description = "Permanently locks last month's PIC/Manager compliance scores (idempotent).";

    public function handle(ComplianceScoreService $scores): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('!Y-m', $this->option('month'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $result = $scores->lockMonth($month);

        $this->info("Locked {$result['pic_count']} PIC score(s) and {$result['manager_count']} manager score(s) for {$month->toDateString()}.");

        return self::SUCCESS;
    }
}
