<?php

namespace App\Console\Commands;

use App\Services\HolidaySyncService;
use Illuminate\Console\Command;

class SyncHolidays extends Command
{
    protected $signature = 'holidays:sync';

    protected $description = 'Sync Indonesian national public holidays from api.co.id into the holidays table.';

    public function handle(HolidaySyncService $sync): int
    {
        $result = $sync->sync();

        $this->info(sprintf(
            'created: %d, updated: %d, skipped: %d',
            $result['created'],
            $result['updated'],
            $result['skipped']
        ));

        return self::SUCCESS;
    }
}
