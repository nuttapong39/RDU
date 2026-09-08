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
}
