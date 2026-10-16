<?php

namespace App\DataSources;

use Illuminate\Support\Collection;

class MockRduDataSource implements RduDataSourceInterface
{
    private array $mockData = [
        'KPI01' => ['numerator' => 340, 'denominator' => 399],  // Accident wound antibiotic
        'KPI02' => ['numerator' => 156, 'denominator' => 199],  // Diarrhea no antibiotic
        'KPI03' => ['numerator' => 182, 'denominator' => 200],  // Wound no antibiotic
        'KPI04' => ['numerator' => 97,  'denominator' => 400],  // IPD antibiotic use
        'KPI05' => ['numerator' => 61,  'denominator' => 80],   // CAP appropriate antibiotic
        'KPI06' => ['numerator' => 881, 'denominator' => 1000], // National drug list value
        'KPI07' => ['numerator' => 18,  'denominator' => 1000], // Duplicate NSAIDs
        'KPI08' => ['numerator' => 71,  'denominator' => 87],   // Asthma patients on ICS
        'KPI09' => ['numerator' => 23,  'denominator' => 892],  // CKD stage 3+ patients on NSAIDs
        'KPI10' => ['numerator' => 1400, 'denominator' => 1478], // Type 2 DM patients on metformin
        'KPI11' => ['numerator' => 58,  'denominator' => 476],  // Child respiratory infection given non-sedating antihistamine
        'KPI12' => ['numerator' => 2,   'denominator' => 800],  // Dual RAS blockade (target 0%)
        'KPI13' => ['numerator' => 171, 'denominator' => 400],  // DM HbA1c controlled
    ];

    public function getNumerator(string $kpiCode, int $year, int $month): int
    {
        return $this->mockData[$kpiCode]['numerator'] ?? 0;
    }

    public function getDenominator(string $kpiCode, int $year, int $month): int
    {
        return $this->mockData[$kpiCode]['denominator'] ?? 0;
    }

    public function getDetails(string $kpiCode, int $year, int $month): Collection
    {
        return collect([]);
    }

    public function getNumeratorBetween(string $kpiCode, string $startDate, string $endDate): int
    {
        return $this->mockData[$kpiCode]['numerator'] ?? 0;
    }

    public function getDenominatorBetween(string $kpiCode, string $startDate, string $endDate): int
    {
        return $this->mockData[$kpiCode]['denominator'] ?? 0;
    }

    public function getDetailsBetween(string $kpiCode, string $startDate, string $endDate): Collection
    {
        return collect([]);
    }

    public function getIcd10Descriptions(array $codes): array
    {
        return [];
    }

    public function getDrugDescriptions(array $icodes): array
    {
        return [];
    }
}
