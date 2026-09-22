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
        'KPI07' => ['numerator' => 623, 'denominator' => 1000], // Generic drug value
        'KPI08' => ['numerator' => 287, 'denominator' => 1000], // Antimicrobial value
        'KPI09' => ['numerator' => 41,  'denominator' => 500],  // Elderly NSAIDs
        'KPI10' => ['numerator' => 9,   'denominator' => 290],  // Vit B1 injection
        'KPI11' => ['numerator' => 14,  'denominator' => 300],  // Corticosteroid injection
        'KPI12' => ['numerator' => 63,  'denominator' => 500],  // Elderly polypharmacy
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
}
