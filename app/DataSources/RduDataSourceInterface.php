<?php

namespace App\DataSources;

use Illuminate\Support\Collection;

interface RduDataSourceInterface
{
    public function getNumerator(string $kpiCode, int $year, int $month): int;

    public function getDenominator(string $kpiCode, int $year, int $month): int;

    public function getDetails(string $kpiCode, int $year, int $month): Collection;
}
