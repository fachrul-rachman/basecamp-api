<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// H-1 generation: runs every 15 minutes and checks today + tomorrow, so
// a Checklist created at any time of day is picked up within minutes
// instead of waiting for a single daily cutoff
// (docs/superpowers/specs/2026-09-22-checklist-assignment-and-generator-frequency-design.md).
Schedule::command('work-items:generate')->everyFifteenMinutes()->withoutOverlapping();

// Passive late/failed evaluation for Work Items the PIC never finished
// (docs/02-BUSINESS-RULES.md §3, Phase 6 acceptance).
Schedule::command('work-items:evaluate')->everyFifteenMinutes()->withoutOverlapping();

// Manager SLA breach -> ISO escalation (docs/02-BUSINESS-RULES.md §8, Phase 7).
Schedule::command('findings:evaluate-sla')->everyFifteenMinutes()->withoutOverlapping();

// National holiday sync from api.co.id (docs/05-DATABASE-SCHEMA.md §9). Runs
// monthly year-round, not just near year-end, since the provider's own
// publish date for next year's list is unpredictable — this is how a
// monthly cadence "catches" it whenever it lands, and also picks up
// corrections to previously-tentative dates.
Schedule::command('holidays:sync')->monthlyOn(1, '03:00')->withoutOverlapping();

// Locks last month's compliance scores permanently, once the new month
// starts (docs/superpowers/specs/2026-09-11-compliance-scoring-design.md).
Schedule::command('scores:lock-month')->monthlyOn(1, '00:10')->withoutOverlapping();
