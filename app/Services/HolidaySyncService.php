<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Facades\Http;

/**
 * Syncs Indonesian national public holidays from api.co.id into the
 * `holidays` table (scope: national). Matches existing rows by the
 * provider's own `id` (stored in `metadata.external_id`) rather than by
 * date/name, because the API can publish a date as tentative and correct
 * it in a later sync — matching by date/name would leave the old,
 * now-wrong date behind as an orphaned row instead of updating it.
 *
 * "Joint holidays" (cuti bersama) and non-day-off entries are filtered
 * out: this company observes national public holidays only.
 */
class HolidaySyncService
{
    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function sync(): array
    {
        $existingByExternalId = Holiday::where('scope', Holiday::SCOPE_NATIONAL)
            ->get()
            ->filter(fn (Holiday $holiday) => isset($holiday->metadata['external_id']))
            ->keyBy(fn (Holiday $holiday) => $holiday->metadata['external_id']);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($this->fetchAllEntries() as $entry) {
            if (! $entry['is_holiday'] || $entry['is_joint_holiday']) {
                $skipped++;

                continue;
            }

            $existing = $existingByExternalId->get($entry['id']);

            if (! $existing) {
                Holiday::create([
                    'date' => $entry['date'],
                    'name' => $entry['name'],
                    'scope' => Holiday::SCOPE_NATIONAL,
                    'metadata' => ['source' => 'api.co.id', 'external_id' => $entry['id']],
                ]);
                $created++;

                continue;
            }

            if ($existing->date->toDateString() !== $entry['date'] || $existing->name !== $entry['name']) {
                $existing->update(['date' => $entry['date'], 'name' => $entry['name']]);
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @return array<int, array>
     */
    private function fetchAllEntries(): array
    {
        $entries = [];
        $page = 1;
        $totalPages = 1;

        do {
            $response = Http::withHeaders(['x-api-co-id' => config('services.holiday_api.token')])
                ->get(rtrim(config('services.holiday_api.base_url'), '/').'/holidays/indonesia/', [
                    'type' => 'Public Holiday',
                    'page' => $page,
                    'size' => 100,
                ])
                ->throw();

            $body = $response->json();
            $entries = array_merge($entries, $body['data']);
            $totalPages = $body['paging']['total_page'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $entries;
    }
}
