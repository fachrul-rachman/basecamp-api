<?php

use App\Models\Holiday;
use App\Services\HolidaySyncService;
use Illuminate\Support\Facades\Http;

test('sync creates a new holiday from the api response', function () {
    Http::fake([
        'use.api.co.id/*' => Http::response(fakeHolidayApiPage([fakeHolidayEntry()])),
    ]);

    $result = app(HolidaySyncService::class)->sync();

    expect($result)->toBe(['created' => 1, 'updated' => 0, 'skipped' => 0]);
    $holiday = Holiday::where('scope', Holiday::SCOPE_NATIONAL)->firstOrFail();
    expect($holiday->date->toDateString())->toBe('2026-01-01');
    expect($holiday->name)->toBe("New Year's Day");
    expect($holiday->metadata['external_id'])->toBe(1166);
});

test('sync skips joint holidays and non-holiday observances', function () {
    Http::fake([
        'use.api.co.id/*' => Http::response(fakeHolidayApiPage([
            fakeHolidayEntry(['id' => 1, 'is_joint_holiday' => true]),
            fakeHolidayEntry(['id' => 2, 'is_holiday' => false]),
            fakeHolidayEntry(['id' => 3]),
        ])),
    ]);

    $result = app(HolidaySyncService::class)->sync();

    expect($result)->toBe(['created' => 1, 'updated' => 0, 'skipped' => 2]);
    expect(Holiday::count())->toBe(1);
});

test('sync updates an existing holiday when its tentative date is corrected', function () {
    Holiday::create([
        'date' => '2026-01-16',
        'name' => 'Ascension of the Prophet Muhammad (Tentative Date)',
        'scope' => Holiday::SCOPE_NATIONAL,
        'metadata' => ['source' => 'api.co.id', 'external_id' => 1167],
    ]);

    Http::fake([
        'use.api.co.id/*' => Http::response(fakeHolidayApiPage([fakeHolidayEntry([
            'id' => 1167,
            'date' => '2026-01-17',
            'name' => 'Ascension of the Prophet Muhammad',
        ])])),
    ]);

    $result = app(HolidaySyncService::class)->sync();

    expect($result)->toBe(['created' => 0, 'updated' => 1, 'skipped' => 0]);
    expect(Holiday::count())->toBe(1);
    $holiday = Holiday::firstOrFail();
    expect($holiday->date->toDateString())->toBe('2026-01-17');
    expect($holiday->name)->toBe('Ascension of the Prophet Muhammad');
});

test('sync does not duplicate or change anything on a second identical run', function () {
    Http::fake([
        'use.api.co.id/*' => Http::response(fakeHolidayApiPage([fakeHolidayEntry()])),
    ]);

    $service = app(HolidaySyncService::class);
    $service->sync();
    $result = $service->sync();

    expect($result)->toBe(['created' => 0, 'updated' => 0, 'skipped' => 0]);
    expect(Holiday::count())->toBe(1);
});

test('sync follows pagination across multiple pages', function () {
    Http::fake([
        'use.api.co.id/*' => function ($request) {
            $page = (int) ($request->data()['page'] ?? 1);

            return $page === 1
                ? Http::response(fakeHolidayApiPage([fakeHolidayEntry(['id' => 1])], 1, 2))
                : Http::response(fakeHolidayApiPage([fakeHolidayEntry(['id' => 2, 'date' => '2026-02-01'])], 2, 2));
        },
    ]);

    $result = app(HolidaySyncService::class)->sync();

    expect($result)->toBe(['created' => 2, 'updated' => 0, 'skipped' => 0]);
    expect(Holiday::count())->toBe(2);
});

test('the holidays:sync command runs the service and reports a summary', function () {
    Http::fake([
        'use.api.co.id/*' => Http::response(fakeHolidayApiPage([fakeHolidayEntry()])),
    ]);

    $this->artisan('holidays:sync')
        ->expectsOutputToContain('created: 1, updated: 0, skipped: 0')
        ->assertExitCode(0);

    expect(Holiday::count())->toBe(1);
});
