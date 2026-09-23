<?php

namespace App\Services;

use App\Models\RduIndicator;
use App\Models\RduIndicatorResult;
use App\Support\FiscalDateRange;
use Illuminate\Support\Collection;

class RduCalculationService
{
    private const NEAR_THRESHOLD = 5.0;

    public function __construct(private RduQueryService $queryService) {}

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
        $pct    = $den > 0 ? round($num / $den * 100, 2) : null;
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
        if ($month !== 0) {
            $stored = RduIndicatorResult::where('indicator_id', $indicator->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($stored !== null) {
                return [
                    'indicator'   => $indicator,
                    'numerator'   => $stored->numerator,
                    'denominator' => $stored->denominator,
                    'percentage'  => $stored->result !== null ? round((float) $stored->result, 1) : null,
                    'status'      => $stored->status ?? 'na',
                    'from_cache'  => true,
                ];
            }
        }

        // Live — query the data source (cached by RduQueryService for 1 hour)
        $num    = $this->queryService->getNumerator($indicator->code, $year, $month);
        $den    = $this->queryService->getDenominator($indicator->code, $year, $month);
        $pct    = $den > 0 ? round($num / $den * 100, 1) : null;
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
                $result = $stored->get($ind->id);
                if ($result !== null) {
                    return [
                        'indicator'   => $ind,
                        'numerator'   => $result->numerator,
                        'denominator' => $result->denominator,
                        'percentage'  => $result->result !== null ? round((float) $result->result, 1) : null,
                        'status'      => $result->status ?? 'na',
                        'from_cache'  => true,
                    ];
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
     * Sums numerator/denominator across the 3 months of a fiscal quarter (live only —
     * a quarter total is never persisted to rdu_indicator_results, matching month=0).
     * Each month's getNumerator/getDenominator call is still 1-hour cached by RduQueryService,
     * and the 3 months never overlap in date range, so summing is safe (no double-counting).
     */
    public function getOrCalculateQuarter(RduIndicator $indicator, int $year, int $quarter): array
    {
        $num = 0;
        $den = 0;
        foreach (FiscalDateRange::quarterMonths($quarter) as $m) {
            $num += $this->queryService->getNumerator($indicator->code, $year, $m);
            $den += $this->queryService->getDenominator($indicator->code, $year, $m);
        }

        $pct    = $den > 0 ? round($num / $den * 100, 1) : null;
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
                $trend[] = [
                    'year'   => $y,
                    'month'  => $m,
                    'label'  => $labels[$m],
                    'pct'    => round((float) $result->result, 1),
                    'num'    => $result->numerator,
                    'den'    => $result->denominator,
                    'status' => $result->status ?? 'na',
                ];
                continue;
            }

            // Not yet calculate()'d — compute live instead of leaving a gap.
            $num    = $this->queryService->getNumerator($indicator->code, $y, $m);
            $den    = $this->queryService->getDenominator($indicator->code, $y, $m);
            $pct    = $den > 0 ? round($num / $den * 100, 1) : null;
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
