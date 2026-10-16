<?php

namespace App\DataSources;

use App\Queries\Kpi\CountingMethod;
use App\Queries\Kpi\DescribesCountingMethod;
use App\Queries\Kpi\KpiQueryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public function getNumeratorBetween(string $kpiCode, string $startDate, string $endDate): int
    {
        return $this->resolve($kpiCode)->getNumeratorBetween($startDate, $endDate);
    }

    public function getDenominatorBetween(string $kpiCode, string $startDate, string $endDate): int
    {
        return $this->resolve($kpiCode)->getDenominatorBetween($startDate, $endDate);
    }

    public function getDetailsBetween(string $kpiCode, string $startDate, string $endDate): Collection
    {
        return $this->resolve($kpiCode)->getDetailsBetween($startDate, $endDate);
    }

    /** Not per-KPI — a plain reference lookup against HOSxP's icd101 table (SELECT only). */
    public function getIcd10Descriptions(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $rows = DB::connection('rdu')->select(
            "SELECT code, name, tname FROM icd101 WHERE code IN ({$placeholders})",
            array_values($codes)
        );

        return collect($rows)->mapWithKeys(fn ($row) => [
            $row->code => ['name' => $row->name, 'tname' => $row->tname],
        ])->all();
    }

    /** Not per-KPI — a plain reference lookup against HOSxP's drugitems table (SELECT only). */
    public function getDrugDescriptions(array $icodes): array
    {
        if (empty($icodes)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($icodes), '?'));
        $rows = DB::connection('rdu')->select(
            "SELECT icode, name, strength, units FROM drugitems WHERE icode IN ({$placeholders})",
            array_values($icodes)
        );

        return collect($rows)->mapWithKeys(fn ($row) => [
            $row->icode => ['name' => trim((string) $row->name), 'strength' => trim((string) $row->strength), 'units' => trim((string) $row->units)],
        ])->all();
    }

    /**
     * How a KPI is counted (CONTEXT.md: วิธีนับ), straight from its query class — null while the query
     * is a stub. Runs no SQL: the rules are code, so this works whatever data source is active.
     */
    public function countingMethodFor(string $kpiCode): ?CountingMethod
    {
        $query = $this->queries[$kpiCode] ?? null;

        return $query instanceof DescribesCountingMethod ? $query->countingMethod() : null;
    }

    private function resolve(string $kpiCode): KpiQueryInterface
    {
        if (!isset($this->queries[$kpiCode])) {
            throw new RuntimeException("No HOSxP query registered for KPI code: {$kpiCode}");
        }
        return $this->queries[$kpiCode];
    }
}
