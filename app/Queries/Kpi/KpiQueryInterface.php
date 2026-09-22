<?php

namespace App\Queries\Kpi;

use Illuminate\Support\Collection;

interface KpiQueryInterface
{
    public function getNumerator(int $year, int $month): int;

    public function getDenominator(int $year, int $month): int;

    /** รายการข้อมูลรายกรณีทั้งหมด ไม่จำกัดจำนวน (ใช้สำหรับ Detail Page + Export) */
    public function getDetails(int $year, int $month): Collection;

    /** ตัวแปรช่วงวันที่กำหนดเอง (Y-m-d) — ใช้สำหรับ export ช่วงวันที่กำหนดเอง (custom date range) */
    public function getNumeratorBetween(string $startDate, string $endDate): int;

    public function getDenominatorBetween(string $startDate, string $endDate): int;

    public function getDetailsBetween(string $startDate, string $endDate): Collection;
}
