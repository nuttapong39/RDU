<?php

namespace App\DataSources;

use App\Queries\Kpi\KpiQueryInterface;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * HOSxP data source — dispatches to the correct KpiQueryInterface per KPI code.
 * Injected via AppServiceProvider which builds the $queries registry.
 */
class HosxpRduDataSource implements RduDataSourceInterface
{
    /** @param array<string, KpiQueryInterface> $queries */
    public function __construct(private array $queries) {}

    public function getNumerator(string $kpiCode, int $year, int $month): int
    {
        return $this->resolve($kpiCode)->getNumerator($year, $month);
    }

    public function getDenominator(string $kpiCode, int $year, int $month): int
    {
        return $this->resolve($kpiCode)->getDenominator($year, $month);
    }

    public function getDetails(string $kpiCode, int $year, int $month): Collection
    {
        return $this->resolve($kpiCode)->getDetails($year, $month);
    }

    private function resolve(string $kpiCode): KpiQueryInterface
    {
        if (!isset($this->queries[$kpiCode])) {
            throw new RuntimeException("No HOSxP query registered for KPI code: {$kpiCode}");
        }
        return $this->queries[$kpiCode];
    }
}
