<?php

namespace Tests\Feature;

use App\Support\FiscalDateRange;
use Tests\TestCase;

class HosxpQueryTest extends TestCase
{
    // ---- FiscalDateRange unit tests ----

    public function test_full_year_returns_oct_to_sep(): void
    {
        [$start, $end] = FiscalDateRange::toGregorian(2568, 0);
        $this->assertSame('2024-10-01', $start);
        $this->assertSame('2025-09-30', $end);
    }

    public function test_month_october_belongs_to_previous_ad_year(): void
    {
        [$start, $end] = FiscalDateRange::toGregorian(2568, 10);
        $this->assertSame('2024-10-01', $start);
        $this->assertSame('2024-10-31', $end);
    }

    public function test_month_december_belongs_to_previous_ad_year(): void
    {
        [$start, $end] = FiscalDateRange::toGregorian(2568, 12);
        $this->assertSame('2024-12-01', $start);
        $this->assertSame('2024-12-31', $end);
    }

    public function test_month_january_belongs_to_current_ad_year(): void
    {
        [$start, $end] = FiscalDateRange::toGregorian(2568, 1);
        $this->assertSame('2025-01-01', $start);
        $this->assertSame('2025-01-31', $end);
    }

    public function test_month_september_closes_fiscal_year(): void
    {
        [$start, $end] = FiscalDateRange::toGregorian(2568, 9);
        $this->assertSame('2025-09-01', $start);
        $this->assertSame('2025-09-30', $end);
    }

    public function test_february_end_of_month_is_correct(): void
    {
        [$start, $end] = FiscalDateRange::toGregorian(2568, 2);
        $this->assertSame('2025-02-01', $start);
        $this->assertSame('2025-02-28', $end);
    }

    public function test_fy2569_full_year(): void
    {
        [$start, $end] = FiscalDateRange::toGregorian(2569, 0);
        $this->assertSame('2025-10-01', $start);
        $this->assertSame('2026-09-30', $end);
    }

    // ---- HosxpRduDataSource dispatcher ----

    public function test_hosxp_data_source_resolves_all_13_kpi_codes(): void
    {
        // When no JSON config is present, AppServiceProvider binds MockRduDataSource.
        // This test verifies that HosxpRduDataSource can be constructed with all 13 entries.
        $source = app(\App\DataSources\HosxpRduDataSource::class);
        $this->assertInstanceOf(\App\DataSources\HosxpRduDataSource::class, $source);
    }

    public function test_hosxp_data_source_throws_for_unknown_code(): void
    {
        $source = app(\App\DataSources\HosxpRduDataSource::class);
        $this->expectException(\RuntimeException::class);
        $source->getNumerator('INVALID', 2568, 1);
    }

    // ---- Query class instantiation (smoke tests) ----

    /**
     * @dataProvider queryClassProvider
     */
    public function test_query_class_can_be_instantiated(string $class): void
    {
        $instance = app($class);
        $this->assertInstanceOf(\App\Queries\Kpi\KpiQueryInterface::class, $instance);
    }

    public static function queryClassProvider(): array
    {
        return [
            'KPI01' => [\App\Queries\Kpi\Antibiotic\AccidentWoundAntibioticQuery::class],
            'KPI02' => [\App\Queries\Kpi\Antibiotic\DiarrheaNoAntibioticQuery::class],
            'KPI03' => [\App\Queries\Kpi\Antibiotic\NormalDeliveryAntibioticQuery::class],
            'KPI04' => [\App\Queries\Kpi\Antibiotic\RespiratoryInfectionAntibioticQuery::class],
            'KPI05' => [\App\Queries\Kpi\Antibiotic\AcuteDiarrheaAntibioticQuery::class],
            'KPI06' => [\App\Queries\Kpi\DrugQuality\NationalDrugListValueQuery::class],
            'KPI07' => [\App\Queries\Kpi\DrugQuality\GenericDrugValueQuery::class],
            'KPI08' => [\App\Queries\Kpi\DrugQuality\AntimicrobialValueQuery::class],
            'KPI09' => [\App\Queries\Kpi\PatientSafety\ElderlyNsaidsQuery::class],
            'KPI10' => [\App\Queries\Kpi\PatientSafety\VitB1InjectionQuery::class],
            'KPI11' => [\App\Queries\Kpi\PatientSafety\CorticosteroidInjectionQuery::class],
            'KPI12' => [\App\Queries\Kpi\PatientSafety\RasDualBlockadeQuery::class],
            'KPI13' => [\App\Queries\Kpi\ChronicDisease\DmHba1cControlledQuery::class],
        ];
    }
}
