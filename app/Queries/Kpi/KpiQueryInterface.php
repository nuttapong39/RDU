<?php

namespace App\Queries\Kpi;

use Illuminate\Support\Collection;

interface KpiQueryInterface
{
    public function getNumerator(int $year, int $month): int;

    public function getDenominator(int $year, int $month): int;

    /** รายการข้อมูลรายกรณี (ใช้สำหรับ Detail Page) */
    public function getDetails(int $year, int $month): Collection;
}
