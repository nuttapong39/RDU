<?php

namespace App\DataSources;

use Illuminate\Support\Collection;

/**
 * Used when no HOSxP connection is configured: every KPI reads as "no data" instead of showing
 * numbers. (MockRduDataSource's fake figures are for the test suite only — in the running app they
 * were once shown as if they were real results while the config file was missing.)
 */
class UnconfiguredRduDataSource implements RduDataSourceInterface
{
    public function getNumerator(string $kpiCode, int $year, int $month): int { return 0; }

    public function getDenominator(string $kpiCode, int $year, int $month): int { return 0; }

    public function getDetails(string $kpiCode, int $year, int $month): Collection { return collect(); }

    public function getNumeratorBetween(string $kpiCode, string $startDate, string $endDate): int { return 0; }

    public function getDenominatorBetween(string $kpiCode, string $startDate, string $endDate): int { return 0; }

    public function getDetailsBetween(string $kpiCode, string $startDate, string $endDate): Collection { return collect(); }

    public function getIcd10Descriptions(array $codes): array { return []; }
}
