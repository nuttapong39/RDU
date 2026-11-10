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

    /**
     * Every drug in HOSxP's `drugitems`, for the drug code settings page (read-only list to pick icodes from).
     * Returns list of ['icode','name','strength','units','dosageform','generic_name','trade_name','drugcategory',
     * 'therapeuticgroup','antibiotic','active'] — catalogue columns only, nothing about patients.
     */
    public function getDrugCatalog(): array;

    /**
     * Lab items matching a search term (code or name), at most `$limit` — for choosing the eGFR lab item.
     * Returns list of ['code','name','unit'].
     */
    public function searchLabItems(string $term, int $limit = 30): array;
}
