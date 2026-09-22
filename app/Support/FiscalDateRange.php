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
    /** Thai fiscal quarter (1-4) → its 3 calendar months. Q1 opens the FY (Oct-Dec). */
    public const QUARTER_MONTHS = [
        1 => [10, 11, 12],
        2 => [1, 2, 3],
        3 => [4, 5, 6],
        4 => [7, 8, 9],
    ];

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

    /** @return int[] the 3 calendar months (1-12) making up fiscal quarter $quarter (1-4). */
    public static function quarterMonths(int $quarter): array
    {
        return self::QUARTER_MONTHS[$quarter] ?? [];
    }

    /** @return array{0: string, 1: string} ['Y-m-d', 'Y-m-d'] spanning the whole quarter (first day of its first month to last day of its last month). */
    public static function quarterToGregorian(int $fyYear, int $quarter): array
    {
        $months = self::quarterMonths($quarter);
        [$start] = self::toGregorian($fyYear, $months[0]);
        [, $end] = self::toGregorian($fyYear, $months[count($months) - 1]);

        return [$start, $end];
    }

    /** Thai display label for a fiscal quarter, e.g. "ไตรมาส 1 (ต.ค.–ธ.ค.)". */
    public static function quarterLabel(int $quarter): string
    {
        return match ($quarter) {
            1 => 'ไตรมาส 1 (ต.ค.–ธ.ค.)',
            2 => 'ไตรมาส 2 (ม.ค.–มี.ค.)',
            3 => 'ไตรมาส 3 (เม.ย.–มิ.ย.)',
            4 => 'ไตรมาส 4 (ก.ค.–ก.ย.)',
            default => '',
        };
    }
}
