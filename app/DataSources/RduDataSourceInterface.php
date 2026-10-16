<?php

namespace App\DataSources;

use Illuminate\Support\Collection;

interface RduDataSourceInterface
{
    public function getNumerator(string $kpiCode, int $year, int $month): int;

    public function getDenominator(string $kpiCode, int $year, int $month): int;

    public function getDetails(string $kpiCode, int $year, int $month): Collection;

    public function getNumeratorBetween(string $kpiCode, string $startDate, string $endDate): int;

    public function getDenominatorBetween(string $kpiCode, string $startDate, string $endDate): int;

    public function getDetailsBetween(string $kpiCode, string $startDate, string $endDate): Collection;

    /**
     * ICD-10 code descriptions — not tied to any specific KPI. Returns
     * [code => ['name' => english, 'tname' => thai]] for whichever of the requested codes exist.
     */
    public function getIcd10Descriptions(array $codes): array;

    /**
     * Drug names — not tied to any specific KPI. Returns
     * [icode => ['name' => …, 'strength' => …, 'units' => …]] for whichever of the requested icodes exist.
     */
    public function getDrugDescriptions(array $icodes): array;
}
