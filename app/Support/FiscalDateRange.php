<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Converts Thai fiscal year + month to Gregorian date strings for HOSxP queries.
 *
 * Thai FY year = Buddhist Era year of September (end of FY).
 * FY runs: Oct 1 (FY-1 BE) → Sep 30 (FY BE).
 * HOSxP stores dates in Gregorian (AD) calendar.
 */
class FiscalDateRange
{
    /**
     * @param int $fyYear Thai fiscal year (BE), e.g. 2568
     * @param int $month  1–12, or 0 for full fiscal year (Oct–Sep)
     * @return array{0: string, 1: string}  ['Y-m-d', 'Y-m-d'] Gregorian
     */
    public static function toGregorian(int $fyYear, int $month): array
    {
        if ($month === 0) {
            $adPrev = ($fyYear - 1) - 543;
            $adCurr = $fyYear - 543;
            return ["{$adPrev}-10-01", "{$adCurr}-09-30"];
        }

        // Months 10-12 belong to the calendar year before the FY year
        $adYear = $month >= 10 ? ($fyYear - 1) - 543 : $fyYear - 543;
        $start  = sprintf('%04d-%02d-01', $adYear, $month);
        $end    = Carbon::parse($start)->endOfMonth()->format('Y-m-d');

        return [$start, $end];
    }
}
