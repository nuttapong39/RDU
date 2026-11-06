<?php

namespace App\Services;

use App\Exceptions\HdcUnavailableException;
use App\Models\RduIndicator;
use App\Models\RduIndicatorResult;
use App\Support\FiscalDateRange;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PDOException;

class RduCalculationService
{
    private const NEAR_THRESHOLD = 5.0;

    public function __construct(
        private RduQueryService $queryService,
        private HdcDataService $hdc
    ) {}

    /**
     * KPIs whose HOSxP query is not implemented yet show their HDC whole-year figure instead (for any
     * month/quarter selection — HDC has no monthly data). Returns null for KPIs on HOSxP, or with no
     * linked HDC table, which then take the normal HOSxP path. An HDC outage yields a "no data" item
     * flagged `hdc_error` so the page can say why.
     */
    private function hdcFallback(RduIndicator $indicator, int $fyYear): ?array
    {
        if ($indicator->hasHosxpQuery() || ! $this->hdc->isLinked($indicator->code)) {
            return null;
        }

        $error = false;
        try {
            $row = $this->hdc->ours($indicator->code, $fyYear);
        } catch (HdcUnavailableException) {
            $row   = null;
            $error = true;
        }

        $num = $row['num'] ?? 0;
        $den = $row['den'] ?? 0;
        $pct = self::percentage($num, $den);

        return [
            'indicator'        => $indicator,
            'numerator'        => $num,
            'denominator'      => $den,
            'percentage'       => $pct,
            'status'           => $this->getStatus($pct, $indicator->target_value, $indicator->target_direction),
            'from_cache'       => false,
            'source'           => 'hdc',
            'hdc_processed_at' => HdcDataService::formatProcessedAt($row['date_com'] ?? null),
            'hdc_error'        => $error,
        ];
    }

    // -------------------------------------------------------------------------
    // Core calculation — computes one KPI and persists the result
    // -------------------------------------------------------------------------

    /**
     * Calculate a single KPI for a period and store it in rdu_indicator_results.
     * Returns the computed result array.
     */
    public function calculate(RduIndicator $indicator, int $year, int $month): array
    {
        $num    = $this->queryService->getNumerator($indicator->code, $year, $month);
        $den    = $this->queryService->getDenominator($indicator->code, $year, $month);
        $pct    = self::percentage($num, $den, 2);
        $status = $this->getStatus($pct, $indicator->target_value, $indicator->target_direction);

        RduIndicatorResult::updateOrCreate(
            ['indicator_id' => $indicator->id, 'year' => $year, 'month' => $month],
            [
                'numerator'     => $num,
                'denominator'   => $den,
                'result'        => $pct,
                'status'        => $status,
                'calculated_at' => now(),
                'calculated_by' => auth()->id(),
            ]
        );

        return [
            'indicator'   => $indicator,
            'numerator'   => $num,
            'denominator' => $den,
            'percentage'  => $pct,
            'status'      => $status,
        ];
    }

    /**
     * Calculate all active KPIs for a period and persist results.
     * Returns a Collection of result arrays keyed by KPI code.
     */
    public function calculateAll(int $year, int $month): Collection
    {
        $indicators = RduIndicator::active()->ordered()->get();
        return $indicators->mapWithKeys(function (RduIndicator $ind) use ($year, $month) {
            return [$ind->code => $this->calculate($ind, $year, $month)];
        });
    }

    /**
     * Try stored result first; fall back to live calculation (without persisting).
     * month=0 always calculates live (aggregate across months not storable).
     */
    public function getOrCalculate(RduIndicator $indicator, int $year, int $month): array
    {
        if ($fallback = $this->hdcFallback($indicator, $year)) {
            return $fallback;
        }

        if ($month !== 0) {
            $stored = RduIndicatorResult::where('indicator_id', $indicator->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($stored !== null) {
                return $this->storedResult($indicator, $stored);
            }
        }

        // Live — query the data source (cached by RduQueryService for 1 hour)
        return $this->liveResult($indicator, fn () => [
            $this->queryService->getNumerator($indicator->code, $year, $month),
            $this->queryService->getDenominator($indicator->code, $year, $month),
        ]);
    }

    /**
     * A result saved by calculate(), judged against the indicator's current target. The saved status is not
     * used (it only records the verdict at calculation time): a KPI has one target for every period, so a
     * row saved before the target changed (KPI04's ≤ 40% → ≤ 20%) must not keep its old verdict.
     */
    private function storedResult(RduIndicator $indicator, RduIndicatorResult $stored): array
    {
        $pct = self::percentage($stored->numerator, $stored->denominator);

        return [
            'indicator'   => $indicator,
            'numerator'   => $stored->numerator,
            'denominator' => $stored->denominator,
            'percentage'  => $pct,
            'status'      => $this->getStatus($pct, $indicator->target_value, $indicator->target_direction),
            'from_cache'  => true,
        ];
    }

    /**
     * A result computed live from `$counts` (returns [numerator, denominator]). A failing query — e.g. a
     * HOSxP table the read-only role cannot SELECT yet — makes this one KPI "no data" (0 / 0 → na), so the
     * overview and the quarter sidebar keep working instead of returning a 500.
     *
     * @param  callable(): array{int, int}  $counts
     */
    private function liveResult(RduIndicator $indicator, callable $counts): array
    {
        try {
            [$num, $den] = $counts();
        } catch (QueryException|PDOException $e) {
            // The class and SQLSTATE only: the message carries the HOSxP host and the full SQL.
            Log::error('KPI query failed', ['kpi' => $indicator->code, 'error' => get_class($e), 'sqlstate' => $e->getCode()]);
            [$num, $den] = [0, 0];
        }

        $pct    = self::percentage($num, $den);
        $status = $this->getStatus($pct, $indicator->target_value, $indicator->target_direction);

        return [
            'indicator'   => $indicator,
            'numerator'   => $num,
            'denominator' => $den,
            'percentage'  => $pct,
            'status'      => $status,
            'from_cache'  => false,
        ];
    }

    /**
     * Batch version of getOrCalculate() for multiple indicators.
     * Loads all stored results in ONE query instead of N queries.
     */
    public function getOrCalculateMany(Collection $indicators, int $year, int $month): Collection
    {
        if ($month !== 0) {
            $stored = RduIndicatorResult::whereIn('indicator_id', $indicators->pluck('id'))
                ->where('year', $year)
                ->where('month', $month)
                ->get()
                ->keyBy('indicator_id');

            return $indicators->map(function (RduIndicator $ind) use ($year, $month, $stored) {
                if ($fallback = $this->hdcFallback($ind, $year)) {
                    return $fallback;
                }
                $result = $stored->get($ind->id);
                if ($result !== null) {
                    return $this->storedResult($ind, $result);
                }
                // No stored result — fall through to live query (cached by RduQueryService)
                return $this->getOrCalculate($ind, $year, $month);
            });
        }

        // month=0: full-year aggregate is never stored, always live
        return $indicators->map(fn ($ind) => $this->getOrCalculate($ind, $year, $month));
    }

    // -------------------------------------------------------------------------
    // Fiscal quarter — always live, never persisted (same convention as month=0)
    // -------------------------------------------------------------------------

    /**
     * Numerator/denominator of a fiscal quarter, counted over the quarter's whole date range (live only —
     * a quarter total is never persisted to rdu_indicator_results, matching month=0; 1-hour cached by
     * RduQueryService). Not summed from months: a KPI counted in patients sees one person in several months.
     */
    public function getOrCalculateQuarter(RduIndicator $indicator, int $year, int $quarter): array
    {
        if ($fallback = $this->hdcFallback($indicator, $year)) {
            return $fallback;
        }

        // Counted over the quarter's whole date range, not summed from its months — the same patient
        // seen in two months is one patient in the quarter (CONTEXT.md: ผู้ป่วยโรคหืดที่ได้ ICS).
        return $this->liveResult($indicator, fn () => [
            $this->queryService->getNumeratorForQuarter($indicator->code, $year, $quarter),
            $this->queryService->getDenominatorForQuarter($indicator->code, $year, $quarter),
        ]);
    }

    /** Batch version of getOrCalculateQuarter() for the sidebar (no batch-query optimization yet — see plan). */
    public function getOrCalculateManyForQuarter(Collection $indicators, int $year, int $quarter): Collection
    {
        return $indicators->map(fn (RduIndicator $ind) => $this->getOrCalculateQuarter($ind, $year, $quarter));
    }

    // -------------------------------------------------------------------------
    // Trend — 12 months of a fiscal year in order (Oct → Sep)
    // -------------------------------------------------------------------------

    /**
     * Returns 12 data points for the fiscal year trend chart.
     * Uses stored results when available; falls back to a live query (1-hour cached by
     * RduQueryService, never persisted here — same "live only" convention as month=0/quarters)
     * for any month that hasn't been explicitly calculate()'d yet, so the chart never shows
     * gaps just because nobody has run the batch calculation for that month.
     */
    public function getTrend(RduIndicator $indicator, int $fyYear): array
    {
        $months  = $this->fiscalYearMonths($fyYear);
        $labels  = self::thaiMonthsShort();

        // Load all stored results for this indicator + FY in one query
        $prevYear = $fyYear - 1;
        $stored = RduIndicatorResult::where('indicator_id', $indicator->id)
            ->where(function ($q) use ($prevYear, $fyYear) {
                $q->where(fn ($s) => $s->where('year', $prevYear)->whereIn('month', [10, 11, 12]))
                  ->orWhere(fn ($s) => $s->where('year', $fyYear)->whereBetween('month', [1, 9]));
            })
            ->get()
            ->keyBy(fn ($r) => "{$r->year}-{$r->month}");

        $trend = [];
        foreach ($months as ['year' => $y, 'month' => $m]) {
            $key    = "{$y}-{$m}";
            $result = $stored->get($key);

            if ($result !== null) {
                $graded  = $this->storedResult($indicator, $result);
                $trend[] = [
                    'year'   => $y,
                    'month'  => $m,
                    'label'  => $labels[$m],
                    'pct'    => $graded['percentage'],
                    'num'    => $graded['numerator'],
                    'den'    => $graded['denominator'],
                    'status' => $graded['status'],
                ];
                continue;
            }

            // Not yet calculate()'d — compute live instead of leaving a gap.
            $num    = $this->queryService->getNumerator($indicator->code, $y, $m);
            $den    = $this->queryService->getDenominator($indicator->code, $y, $m);
            $pct    = self::percentage($num, $den);
            $trend[] = [
                'year'   => $y,
                'month'  => $m,
                'label'  => $labels[$m],
                'pct'    => $pct,
                'num'    => $num,
                'den'    => $den,
                'status' => $this->getStatus($pct, $indicator->target_value, $indicator->target_direction),
            ];
        }

        return $this->withYearToDate($trend, $indicator, $fyYear);
    }

    /**
     * Adds the year-to-date figure (start of the fiscal year through each month) to every trend point as
     * `cum_num` / `cum_den` / `cum_pct`. Visits never repeat across months, so a visit KPI's figure is the
     * running sum of its months; a KPI counted in patients is counted over the whole span instead.
     */
    private function withYearToDate(array $trend, RduIndicator $indicator, int $fyYear): array
    {
        $additive  = $indicator->countUnit()['additive'];
        [$fyStart] = FiscalDateRange::toGregorian($fyYear, 10);
        $cumNum    = 0;
        $cumDen    = 0;

        foreach ($trend as $i => $point) {
            if ($additive) {
                $cumNum += $point['num'];
                $cumDen += $point['den'];
            } else {
                [, $monthEnd] = FiscalDateRange::toGregorian($point['year'], $point['month']);
                $cumNum = $this->queryService->getNumeratorBetween($indicator->code, $fyStart, $monthEnd);
                $cumDen = $this->queryService->getDenominatorBetween($indicator->code, $fyStart, $monthEnd);
            }

            $trend[$i]['cum_num'] = $cumNum;
            $trend[$i]['cum_den'] = $cumDen;
            $trend[$i]['cum_pct'] = self::percentage($cumNum, $cumDen);
        }

        return $trend;
    }

    /**
     * Returns ordered (year, month) pairs for a Thai fiscal year.
     * FY year = BE year of September (end of FY).
     * Order: Oct(prev), Nov(prev), Dec(prev), Jan, Feb, ..., Sep
     */
    public function fiscalYearMonths(int $fyYear): array
    {
        // FiscalDateRange::toGregorian() already maps a fiscal year's Oct-Dec months to the previous
        // AD calendar year internally — every (year, month) pair here uses the SAME fiscal year label
        // $fyYear regardless of month. Subtracting 1 here too (as this used to do) double-applies that
        // shift, pulling Oct/Nov/Dec from a full fiscal year earlier than intended.
        $months = [];
        foreach ([10, 11, 12] as $m) {
            $months[] = ['year' => $fyYear, 'month' => $m];
        }
        foreach (range(1, 9) as $m) {
            $months[] = ['year' => $fyYear, 'month' => $m];
        }
        return $months;
    }

    // -------------------------------------------------------------------------
    // Status logic
    // -------------------------------------------------------------------------

    /**
     * Result % used everywhere a rate is judged or shown: one decimal, except that a non-zero count never
     * rounds down to 0 (1 / 9,200 → 0.01, not 0.0). At a 0% target that rounding would turn a real case
     * into a 'pass'. null when there is no denominator.
     */
    public static function percentage(int|float $num, int|float $den, int $precision = 1): ?float
    {
        if ($den <= 0) {
            return null;
        }

        $raw = $num / $den * 100;
        $pct = round($raw, $precision);

        return ($num > 0 && $pct <= 0.0) ? max(round($raw, 2), 0.01) : $pct;
    }

    /** Returns 'pass' | 'warning' | 'fail' | 'na' */
    public function getStatus(?float $pct, float $target, string $direction): string
    {
        if ($pct === null) {
            return 'na';
        }

        $passed = match ($direction) {
            'higher' => $pct >= $target,
            'lower'  => $pct <= $target,
            default  => false,
        };

        if ($passed) {
            return 'pass';
        }

        // A 0% "lower" target means the event should never happen (e.g. KPI12 dual RAS blockade):
        // every case is a failure, there is no "nearly there".
        if ($direction === 'lower' && $target <= 0.0) {
            return 'fail';
        }

        $near = match ($direction) {
            'higher' => $pct >= $target - self::NEAR_THRESHOLD,
            'lower'  => $pct <= $target + self::NEAR_THRESHOLD,
            default  => false,
        };

        return $near ? 'warning' : 'fail';
    }

    /** Count summary: [pass, warning, fail, na] from status strings. */
    public function summarize(array $statuses): array
    {
        $counts = ['pass' => 0, 'warning' => 0, 'fail' => 0, 'na' => 0];
        foreach ($statuses as $s) {
            $counts[$s] = ($counts[$s] ?? 0) + 1;
        }
        return $counts;
    }

    // -------------------------------------------------------------------------
    // Fiscal year helpers
    // -------------------------------------------------------------------------

    /** Thai FY: October 1 → September 30. FY year = Buddhist year of September. */
    public function currentFiscalYear(): int
    {
        $now    = now();
        $beYear = $now->year + 543;
        return $now->month >= 10 ? $beYear + 1 : $beYear;
    }

    public function fiscalYearOptions(int $count = 3): array
    {
        $current = $this->currentFiscalYear();
        return range($current, $current - $count + 1);
    }

    // -------------------------------------------------------------------------
    // Static month name helpers
    // -------------------------------------------------------------------------

    public static function thaiMonths(): array
    {
        return [
            1 => 'มกราคม',   2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
            4 => 'เมษายน',   5 => 'พฤษภาคม',   6 => 'มิถุนายน',
            7 => 'กรกฎาคม',  8 => 'สิงหาคม',   9 => 'กันยายน',
            10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
        ];
    }

    public static function thaiMonthsShort(): array
    {
        return [
            1 => 'ม.ค.',  2 => 'ก.พ.',  3 => 'มี.ค.',
            4 => 'เม.ย.', 5 => 'พ.ค.',  6 => 'มิ.ย.',
            7 => 'ก.ค.',  8 => 'ส.ค.',  9 => 'ก.ย.',
            10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
        ];
    }
}
