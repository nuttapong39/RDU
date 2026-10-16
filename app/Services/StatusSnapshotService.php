<?php

namespace App\Services;

use App\Models\RduIndicator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The status snapshot shown on the login page (CONTEXT.md: ภาพสถานะล่าสุด): the status of every KPI for
 * the current fiscal year, as the overview last computed it. Recording only ever happens from a
 * logged-in overview; reading never queries HOSxP, so someone who has not logged in cannot trigger
 * a calculation. Only statuses are kept — no percentages, counts or case data.
 *
 * Kept as a file on the local disk rather than in the cache, so `cache:clear` on every deploy
 * does not blank the login page until someone opens the overview again.
 */
class StatusSnapshotService
{
    private const FILE = 'status_snapshot.json';

    private const STATUSES = ['pass', 'warning', 'fail', 'na'];

    private const DISPLAY_TIMEZONE = 'Asia/Bangkok';

    public function __construct(private RduCalculationService $calcService) {}

    /**
     * Keep the statuses of a whole-year overview. Callers pass only the current fiscal year's
     * whole-year figures (month 0); a failed write never breaks the overview.
     *
     * @param  Collection<int, array{indicator: RduIndicator, status: string}>  $kpiData
     */
    public function record(int $fiscalYear, Collection $kpiData): void
    {
        try {
            $statuses = $kpiData->mapWithKeys(fn (array $item) => [$item['indicator']->code => $item['status']])->all();

            Storage::disk('local')->put(self::FILE, json_encode([
                'year'     => $fiscalYear,
                'taken_at' => now()->toIso8601String(),
                'statuses' => $statuses,
            ]));
        } catch (Throwable $e) {
            Log::warning('Status snapshot not saved', ['error' => get_class($e)]);
        }
    }

    /**
     * What the login page may show: each active KPI's code, name, category and status, plus how many
     * KPIs are in each status. A snapshot from an earlier fiscal year counts as no data.
     *
     * @return array{
     *     takenAt: Carbon|null,           // when the overview recorded it, in the hospital's local time (Asia/Bangkok)
     *     takenAtText: string|null,       // the same as Thai text, e.g. "16 ต.ค. 2569 12:11 น."
     *     kpis: list<array{code: string, name: string, category: string, status: string}>,
     *     counts: array{pass: int, warning: int, fail: int, na: int},
     * }
     */
    public function forLogin(): array
    {
        $snapshot = $this->read();
        $current  = $snapshot !== null && ($snapshot['year'] ?? null) === $this->calcService->currentFiscalYear();
        $statuses = $current ? (array) ($snapshot['statuses'] ?? []) : [];

        $kpis = $this->indicators()->map(fn (RduIndicator $ind) => [
            'code'     => $ind->code,
            'name'     => $ind->name,
            'category' => $ind->category,
            'status'   => in_array($statuses[$ind->code] ?? null, self::STATUSES, true) ? $statuses[$ind->code] : 'na',
        ])->values()->all();

        $takenAt = $current ? $snapshot['taken_at'] : null;

        return [
            'takenAt'     => $takenAt,
            'takenAtText' => $takenAt ? $this->thaiDateTime($takenAt) : null,
            'kpis'        => $kpis,
            'counts'      => $this->calcService->summarize(array_column($kpis, 'status')),
        ];
    }

    /** The login form must never fail because of its decoration: no KPI list if the app database can't be read. */
    private function indicators(): Collection
    {
        try {
            return RduIndicator::active()->ordered()->get();
        } catch (Throwable $e) {
            Log::warning('Status snapshot: KPI list unavailable', ['error' => get_class($e)]);

            return collect();
        }
    }

    private function thaiDateTime(Carbon $at): string
    {
        $month = RduCalculationService::thaiMonthsShort()[$at->month];

        return "{$at->day} {$month} ".($at->year + 543).' '.$at->format('H:i').' น.';
    }

    /** The snapshot file, with `taken_at` parsed into local time — null if missing, unreadable or malformed. */
    private function read(): ?array
    {
        try {
            $disk = Storage::disk('local');
            if (! $disk->exists(self::FILE)) {
                return null;
            }
            $data = json_decode((string) $disk->get(self::FILE), true);
            if (! is_array($data) || ! is_string($data['taken_at'] ?? null)) {
                return null;
            }
            // The app runs in UTC; the page shows the hospital's local time.
            $data['taken_at'] = Carbon::parse($data['taken_at'])->setTimezone(self::DISPLAY_TIMEZONE);

            return $data;
        } catch (Throwable $e) {
            Log::warning('Status snapshot unreadable', ['error' => get_class($e)]);

            return null;
        }
    }
}
