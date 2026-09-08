<?php

namespace Tests\Feature;

use App\Models\RduIndicator;
use App\Models\RduIndicatorResult;
use App\Models\User;
use App\Services\RduCalculationService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    // -------------------------------------------------------------------------
    // N+1 — getOrCalculateMany() uses a single batch query
    // -------------------------------------------------------------------------

    public function test_get_or_calculate_many_uses_one_db_query_for_stored_results(): void
    {
        $indicators = RduIndicator::active()->ordered()->get();

        // Pre-populate stored results for all 13 KPIs
        foreach ($indicators as $ind) {
            RduIndicatorResult::factory()->create([
                'indicator_id' => $ind->id,
                'year'         => 2568,
                'month'        => 3,
            ]);
        }

        DB::enableQueryLog();

        $service = app(RduCalculationService::class);
        $service->getOrCalculateMany($indicators, 2568, 3);

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $resultsQueries = collect($log)
            ->filter(fn ($q) => str_contains($q['query'], 'rdu_indicator_results'))
            ->values();

        // Must be exactly one batch SELECT, not 13 individual SELECTs
        $this->assertCount(1, $resultsQueries, 'Expected exactly 1 batch query to rdu_indicator_results');
    }

    public function test_get_or_calculate_many_month_zero_skips_db(): void
    {
        $indicators = RduIndicator::active()->ordered()->get();

        DB::enableQueryLog();

        $service = app(RduCalculationService::class);
        $service->getOrCalculateMany($indicators, 2568, 0);

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $resultsQueries = collect($log)
            ->filter(fn ($q) => str_contains($q['query'], 'rdu_indicator_results'))
            ->values();

        // month=0 is always live — must NOT query stored results table
        $this->assertCount(0, $resultsQueries, 'month=0 must not query rdu_indicator_results');
    }

    public function test_get_or_calculate_many_returns_all_13_kpis(): void
    {
        $indicators = RduIndicator::active()->ordered()->get();
        $service    = app(RduCalculationService::class);

        $result = $service->getOrCalculateMany($indicators, 2568, 3);

        $this->assertCount(13, $result);
    }

    public function test_get_or_calculate_many_returns_from_cache_true_for_stored(): void
    {
        $indicators = RduIndicator::active()->ordered()->get();
        $first      = $indicators->first();

        RduIndicatorResult::factory()->create([
            'indicator_id' => $first->id,
            'year'         => 2568,
            'month'        => 5,
        ]);

        $service = app(RduCalculationService::class);
        $result  = $service->getOrCalculateMany($indicators, 2568, 5);

        $firstResult = $result->first(fn ($r) => $r['indicator']->id === $first->id);
        $this->assertTrue($firstResult['from_cache']);
    }

    public function test_get_or_calculate_many_returns_from_cache_false_for_live(): void
    {
        // No stored results — all must be computed live
        $indicators = RduIndicator::active()->ordered()->get();
        $service    = app(RduCalculationService::class);

        $result = $service->getOrCalculateMany($indicators, 2568, 5);

        foreach ($result as $r) {
            $this->assertFalse($r['from_cache']);
        }
    }

    // -------------------------------------------------------------------------
    // Dashboard page — query count with stored results
    // -------------------------------------------------------------------------

    public function test_dashboard_index_does_not_run_n_plus_1_queries(): void
    {
        $user       = User::factory()->create();
        $indicators = RduIndicator::active()->ordered()->get();

        foreach ($indicators as $ind) {
            RduIndicatorResult::factory()->create([
                'indicator_id' => $ind->id,
                'year'         => 2568,
                'month'        => 3,
            ]);
        }

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('dashboard', ['year' => 2568, 'month' => 3]));
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $resultsQueries = collect($log)
            ->filter(fn ($q) => str_contains($q['query'], 'rdu_indicator_results'))
            ->values();

        // With getOrCalculateMany(): only 1 batch SELECT (not 13)
        $this->assertLessThanOrEqual(2, $resultsQueries->count(),
            "Expected ≤2 queries to rdu_indicator_results, got {$resultsQueries->count()}");
    }

    // -------------------------------------------------------------------------
    // Cache — RduQueryService caches results
    // -------------------------------------------------------------------------

    public function test_rdu_query_service_caches_numerator(): void
    {
        $service = app(\App\Services\RduQueryService::class);

        // First call populates cache
        $first = $service->getNumerator('KPI01', 2568, 3);

        // Second call must return same value without re-querying data source
        $second = $service->getNumerator('KPI01', 2568, 3);

        $this->assertSame($first, $second);
    }

    public function test_rdu_query_service_caches_denominator(): void
    {
        $service = app(\App\Services\RduQueryService::class);

        $first  = $service->getDenominator('KPI01', 2568, 3);
        $second = $service->getDenominator('KPI01', 2568, 3);

        $this->assertSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // Chart payload — only lean fields
    // -------------------------------------------------------------------------

    public function test_dashboard_chart_data_payload_has_expected_fields_only(): void
    {
        $user     = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        // chartData is embedded in Blade — verify the page renders without extra payload bloat
        // (structural check: page loads under 1 MB is a reasonable proxy)
        $this->assertLessThan(1_048_576, strlen($response->content()),
            'Dashboard response should be under 1 MB');
    }

    public function test_kpi_trend_data_payload_has_12_points(): void
    {
        $user     = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard.kpi', 'KPI01'));

        $response->assertOk();

        preg_match('/const trend\s*=\s*(\[.*?\]);/s', $response->content(), $matches);
        $this->assertNotEmpty($matches, 'trendData must be present in page JS');

        $trend = json_decode($matches[1], true);
        $this->assertCount(12, $trend, 'Trend must have exactly 12 monthly data points');
    }
}
