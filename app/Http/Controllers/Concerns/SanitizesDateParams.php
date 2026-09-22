<?php

namespace App\Http\Controllers\Concerns;

trait SanitizesDateParams
{
    private function sanitizeYear(int $year): int
    {
        return max(2560, min(2600, $year));
    }

    private function sanitizeMonth(int $month): int
    {
        return max(0, min(12, $month));
    }

    /** Returns 1-4, or null if absent/invalid (distinct from "no quarter selected"). */
    private function sanitizeQuarter(mixed $quarter): ?int
    {
        if ($quarter === null || $quarter === '') {
            return null;
        }

        $q = (int) $quarter;

        return ($q >= 1 && $q <= 4) ? $q : null;
    }
}
