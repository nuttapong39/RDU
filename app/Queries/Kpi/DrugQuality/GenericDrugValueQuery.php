<?php

namespace App\Queries\Kpi\DrugQuality;

use App\Queries\Kpi\KpiQueryInterface;
use Illuminate\Support\Collection;

class GenericDrugValueQuery implements KpiQueryInterface
{
    public function getNumerator(int $year, int $month): int { return 0; }
    public function getDenominator(int $year, int $month): int { return 0; }
    public function getDetails(int $year, int $month): Collection { return collect(); }
    public function getNumeratorBetween(string $startDate, string $endDate): int { return 0; }
    public function getDenominatorBetween(string $startDate, string $endDate): int { return 0; }
    public function getDetailsBetween(string $startDate, string $endDate): Collection { return collect(); }
}
