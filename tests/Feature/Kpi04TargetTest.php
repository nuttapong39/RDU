<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\RduIndicator;
use App\Models\RduIndicatorResult;
use App\Services\RduCalculationService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KPI04's target is ≤ 20% (the Ministry's RDU Service Plan criterion, changed from ≤ 40% in 2026-11). A KPI has
 * one target for every fiscal year, so a result stored by `rdu:calculate` is judged against the current target
 * when it is read, never by the status saved alongside it (CONTEXT.md: เป้าหมาย).
 */
class Kpi04TargetTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    private function kpi04(): RduIndicator
    {
        return RduIndicator::where('code', 'KPI04')->firstOrFail();
    }

    private function withKpi04Counts(int $num, int $den): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class($num, $den) extends MockRduDataSource {
            public function __construct(private int $num, private int $den) {}
            public function getNumerator(string $kpiCode, int $year, int $month): int
            {
                return $kpiCode === 'KPI04' ? $this->num : parent::getNumerator($kpiCode, $year, $month);
            }
            public function getDenominator(string $kpiCode, int $year, int $month): int
            {
                return $kpiCode === 'KPI04' ? $this->den : parent::getDenominator($kpiCode, $year, $month);
            }
        });
    }

    /** A row as `rdu:calculate` saves it, with the status it was given under the target of the day. */
    private function storeResult(int $year, int $month, int $num, int $den, string $savedStatus): void
    {
        RduIndicatorResult::create([
            'indicator_id' => $this->kpi04()->id, 'year' => $year, 'month' => $month,
            'numerator' => $num, 'denominator' => $den, 'result' => round($num / $den * 100, 2),
            'status' => $savedStatus, 'calculated_at' => now(),
        ]);
    }

    public function test_kpi04_target_is_at_most_20_percent(): void
    {
        $kpi = $this->kpi04();

        $this->assertSame(20.0, (float) $kpi->target_value);
        $this->assertSame('lower', $kpi->target_direction);
        $this->assertSame('≤ 20%', $kpi->targetLabel());
    }

    /** @return array<string, array{int, int, string}> */
    public static function kpi04LiveResultProvider(): array
    {
        return [
            'exactly 20% passes'          => [200, 1000, 'pass'],
            '20.1% is near the target'    => [201, 1000, 'warning'],
            '25.0% is still near'         => [250, 1000, 'warning'],
            '25.1% fails'                 => [251, 1000, 'fail'],
            '27.1% (FY2570 so far) fails' => [46, 170, 'fail'],
        ];
    }

    /** @dataProvider kpi04LiveResultProvider */
    public function test_live_result_is_judged_against_20_percent(int $num, int $den, string $status): void
    {
        $this->withKpi04Counts($num, $den);

        $result = app(RduCalculationService::class)->getOrCalculate($this->kpi04(), 2570, 0);

        $this->assertSame($status, $result['status']);
    }

    public function test_a_stored_month_is_judged_against_the_current_target_not_its_saved_status(): void
    {
        $this->storeResult(2569, 3, 36, 124, 'pass');   // 29.0% — 'pass' under the old ≤ 40%
        $service = app(RduCalculationService::class);
        $kpi     = $this->kpi04();

        $single = $service->getOrCalculate($kpi, 2569, 3);
        $this->assertTrue($single['from_cache']);
        $this->assertSame(29.0, $single['percentage']);
        $this->assertSame('fail', $single['status']);

        $many = $service->getOrCalculateMany(collect([$kpi]), 2569, 3)->first();
        $this->assertTrue($many['from_cache']);
        $this->assertSame('fail', $many['status']);

        $march = collect($service->getTrend($kpi, 2569))->firstWhere('month', 3);
        $this->assertSame(29.0, $march['pct']);
        $this->assertSame('fail', $march['status']);
    }

    public function test_a_stored_status_follows_any_later_target_change(): void
    {
        $this->storeResult(2569, 3, 36, 124, 'fail');   // 29.0% — 'fail' under ≤ 20%
        $kpi = $this->kpi04();
        $kpi->update(['target_value' => 30]);

        $result = app(RduCalculationService::class)->getOrCalculate($kpi->fresh(), 2569, 3);

        $this->assertSame('pass', $result['status']);
    }
}
