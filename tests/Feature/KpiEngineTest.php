<?php

namespace Tests\Feature;

use App\Models\RduIndicator;
use App\Models\RduIndicatorResult;
use App\Models\User;
use App\Services\RduCalculationService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiEngineTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    // ---- calculate() ----

    public function test_calculate_stores_result_in_database(): void
    {
        $svc = app(RduCalculationService::class);
        $ind = RduIndicator::first();

        $result = $svc->calculate($ind, 2568, 9);

        $this->assertDatabaseHas('rdu_indicator_results', [
            'indicator_id' => $ind->id,
            'year'         => 2568,
            'month'        => 9,
        ]);

        $this->assertArrayHasKey('indicator',   $result);
        $this->assertArrayHasKey('numerator',   $result);
        $this->assertArrayHasKey('denominator', $result);
        $this->assertArrayHasKey('percentage',  $result);
        $this->assertArrayHasKey('status',      $result);
    }

    public function test_calculate_updates_existing_result(): void
    {
        $svc = app(RduCalculationService::class);
        $ind = RduIndicator::first();

        $svc->calculate($ind, 2568, 8);
        $svc->calculate($ind, 2568, 8);

        $this->assertSame(
            1,
            RduIndicatorResult::where('indicator_id', $ind->id)
                ->where('year', 2568)->where('month', 8)->count()
        );
    }

    public function test_calculate_status_is_valid_value(): void
    {
        $svc = app(RduCalculationService::class);
        $ind = RduIndicator::first();

        $result = $svc->calculate($ind, 2568, 7);

        $this->assertContains($result['status'], ['pass', 'warning', 'fail', 'na']);
    }

    // ---- calculateAll() ----

    public function test_calculate_all_stores_all_active_indicators(): void
    {
        $svc   = app(RduCalculationService::class);
        $count = RduIndicator::active()->count();

        $results = $svc->calculateAll(2568, 6);

        $this->assertCount($count, $results);
        $this->assertSame($count, RduIndicatorResult::where('year', 2568)->where('month', 6)->count());
    }

    public function test_calculate_all_keyed_by_code(): void
    {
        $svc     = app(RduCalculationService::class);
        $results = $svc->calculateAll(2568, 5);

        foreach ($results->keys() as $key) {
            $this->assertMatchesRegularExpression('/^KPI\d{2}$/', $key);
        }
    }

    // ---- getOrCalculate() ----

    public function test_get_or_calculate_returns_stored_result(): void
    {
        $svc = app(RduCalculationService::class);
        $ind = RduIndicator::first();

        // Store a result
        RduIndicatorResult::create([
            'indicator_id' => $ind->id,
            'year'         => 2568,
            'month'        => 4,
            'numerator'    => 50,
            'denominator'  => 100,
            'result'       => 50.0,
            'status'       => 'fail',
            'calculated_at' => now(),
        ]);

        $result = $svc->getOrCalculate($ind, 2568, 4);

        $this->assertTrue($result['from_cache']);
        $this->assertSame(50.0, $result['percentage']);
        $this->assertSame('fail', $result['status']);
    }

    public function test_get_or_calculate_live_when_no_stored_result(): void
    {
        $svc    = app(RduCalculationService::class);
        $ind    = RduIndicator::first();
        $result = $svc->getOrCalculate($ind, 2568, 3);

        $this->assertFalse($result['from_cache']);
        $this->assertContains($result['status'], ['pass', 'warning', 'fail', 'na']);
    }

    public function test_get_or_calculate_month_zero_always_live(): void
    {
        $svc = app(RduCalculationService::class);
        $ind = RduIndicator::first();

        // Even if a stored record exists for individual months, month=0 is always live
        $result = $svc->getOrCalculate($ind, 2568, 0);

        $this->assertFalse($result['from_cache']);
    }

    // ---- getTrend() ----

    public function test_get_trend_returns_12_months(): void
    {
        $svc    = app(RduCalculationService::class);
        $ind    = RduIndicator::first();
        $trend  = $svc->getTrend($ind, 2568);

        $this->assertCount(12, $trend);
    }

    public function test_get_trend_starts_with_october_of_previous_year(): void
    {
        $svc   = app(RduCalculationService::class);
        $ind   = RduIndicator::first();
        $trend = $svc->getTrend($ind, 2568);

        $first = $trend[0];
        $this->assertSame(10, $first['month']);
        $this->assertSame(2567, $first['year']);
    }

    public function test_get_trend_ends_with_september_of_fy_year(): void
    {
        $svc   = app(RduCalculationService::class);
        $ind   = RduIndicator::first();
        $trend = $svc->getTrend($ind, 2568);

        $last = $trend[11];
        $this->assertSame(9, $last['month']);
        $this->assertSame(2568, $last['year']);
    }

    public function test_get_trend_includes_stored_results(): void
    {
        $svc = app(RduCalculationService::class);
        $ind = RduIndicator::first();

        RduIndicatorResult::create([
            'indicator_id' => $ind->id,
            'year'         => 2568,
            'month'        => 1,
            'numerator'    => 80,
            'denominator'  => 100,
            'result'       => 80.0,
            'status'       => 'pass',
            'calculated_at' => now(),
        ]);

        $trend = $svc->getTrend($ind, 2568);

        // January is index 3 (Oct=0, Nov=1, Dec=2, Jan=3)
        $jan = collect($trend)->firstWhere('month', 1);
        $this->assertSame(80.0, $jan['pct']);
        $this->assertSame('pass', $jan['status']);
    }

    public function test_get_trend_null_pct_for_months_without_data(): void
    {
        $svc   = app(RduCalculationService::class);
        $ind   = RduIndicator::first();
        $trend = $svc->getTrend($ind, 2568);

        // With no stored results, all pct values should be null
        foreach ($trend as $point) {
            $this->assertNull($point['pct']);
        }
    }

    // ---- fiscalYearMonths() ----

    public function test_fiscal_year_months_returns_correct_order(): void
    {
        $svc    = app(RduCalculationService::class);
        $months = $svc->fiscalYearMonths(2568);

        $this->assertCount(12, $months);
        $this->assertSame(['year' => 2567, 'month' => 10], $months[0]);
        $this->assertSame(['year' => 2567, 'month' => 11], $months[1]);
        $this->assertSame(['year' => 2567, 'month' => 12], $months[2]);
        $this->assertSame(['year' => 2568, 'month' => 1],  $months[3]);
        $this->assertSame(['year' => 2568, 'month' => 9],  $months[11]);
    }

    // ---- Artisan command ----

    public function test_artisan_rdu_calculate_runs_for_single_month(): void
    {
        $this->artisan('rdu:calculate', ['year' => 2568, 'month' => 9])
            ->assertExitCode(0);

        $count = RduIndicator::active()->count();
        $this->assertSame(
            $count,
            RduIndicatorResult::where('year', 2568)->where('month', 9)->count()
        );
    }

    public function test_artisan_rdu_calculate_rejects_invalid_year(): void
    {
        $this->artisan('rdu:calculate', ['year' => 1999, 'month' => 1])
            ->assertExitCode(1);
    }

    public function test_artisan_rdu_calculate_rejects_invalid_month(): void
    {
        $this->artisan('rdu:calculate', ['year' => 2568, 'month' => 13])
            ->assertExitCode(1);
    }

    public function test_artisan_rdu_calculate_month_zero_calculates_all_months(): void
    {
        $this->artisan('rdu:calculate', ['year' => 2568, 'month' => 0])
            ->assertExitCode(0);

        $indCount = RduIndicator::active()->count();
        $this->assertSame(
            $indCount * 12,
            RduIndicatorResult::where('year', 2568)->count()
        );
    }
}
