<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\DrugCodeSet;
use App\Models\RduIndicator;
use App\Models\RduIndicatorResult;
use App\Models\User;
use App\Services\DrugCodeSetService;
use App\Services\RduCalculationService;
use App\Services\RduQueryService;
use App\Services\StatusSnapshotService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 2 of docs/plans/drug-code-sets-and-facility-profile.md: a KPI whose drug code sets are empty is
 * "ยังไม่ตั้งค่า" (CONTEXT.md) — no numbers, no status, never persisted — and editing a set invalidates old results.
 */
class KpiNotConfiguredTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    // Start with the empty sets a new hospital has; tests configure what they need.
    protected bool $seedDrugCodeSets = false;

    private function user(): User
    {
        return User::firstOrCreate(['username' => 'staff'], ['name' => 'Staff', 'email' => 'staff@rdu.local', 'password' => 'x']);
    }

    private function kpi(string $code): RduIndicator
    {
        return RduIndicator::where('code', $code)->firstOrFail();
    }

    private function configure(string ...$keys): void
    {
        foreach ($keys as $key) {
            $set = DrugCodeSet::firstOrCreate(['key' => $key], ['version' => 1]);
            $set->items()->create(['code' => '1000001', 'attribute' => in_array($key, ['ras_blockade', 'non_sedating_antihistamine', 'long_acting_bzd'], true) ? 'x' : null]);
        }
        app(DrugCodeSetService::class)->flush();
    }

    /** A data source that fails the test if a KPI is counted — an unconfigured KPI must never reach HOSxP. */
    private function forbidCounting(): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class extends MockRduDataSource {
            public function getNumerator(string $kpiCode, int $year, int $month): int { throw new \LogicException("counted $kpiCode"); }
            public function getDenominator(string $kpiCode, int $year, int $month): int { throw new \LogicException("counted $kpiCode"); }
            public function getNumeratorBetween(string $kpiCode, string $s, string $e): int { throw new \LogicException("counted $kpiCode"); }
            public function getDenominatorBetween(string $kpiCode, string $s, string $e): int { throw new \LogicException("counted $kpiCode"); }
        });
    }

    // ---- calculation layer ----

    public function test_an_unconfigured_kpi_has_no_numbers_and_no_status_and_never_reaches_the_data_source(): void
    {
        $this->forbidCounting();
        $svc = app(RduCalculationService::class);
        $kpi = $this->kpi('KPI04');

        foreach ([$svc->getOrCalculate($kpi, 2569, 0), $svc->getOrCalculate($kpi, 2569, 3), $svc->getOrCalculateQuarter($kpi, 2569, 2),
                  $svc->getOrCalculateMany(collect([$kpi]), 2569, 3)->first(), $svc->calculate($kpi, 2569, 3)] as $result) {
            $this->assertSame('not_configured', $result['status']);
            $this->assertNull($result['percentage']);
            $this->assertSame(0, $result['numerator']);
            $this->assertSame(['antibiotic'], $result['missing_sets']);
        }
        $this->assertDatabaseCount('rdu_indicator_results', 0);   // calculate() persisted nothing
    }

    public function test_the_trend_of_an_unconfigured_kpi_is_twelve_empty_points(): void
    {
        $this->forbidCounting();

        $trend = app(RduCalculationService::class)->getTrend($this->kpi('KPI04'), 2569);

        $this->assertCount(12, $trend);
        $this->assertSame(['not_configured'], array_unique(array_column($trend, 'status')));
        $this->assertSame([null], array_unique(array_column($trend, 'pct')));
    }

    public function test_a_stored_result_is_not_shown_for_a_kpi_that_is_not_configured(): void
    {
        $kpi = $this->kpi('KPI04');
        RduIndicatorResult::create(['indicator_id' => $kpi->id, 'year' => 2569, 'month' => 3, 'numerator' => 5, 'denominator' => 10,
            'result' => 50, 'status' => 'fail', 'calculated_at' => now()]);

        $this->assertSame('not_configured', app(RduCalculationService::class)->getOrCalculate($kpi, 2569, 3)['status']);
        $this->assertSame('not_configured', app(RduCalculationService::class)->getOrCalculateMany(collect([$kpi]), 2569, 3)->first()['status']);
    }

    public function test_only_the_kpis_whose_sets_are_filled_are_configured(): void
    {
        $this->configure('antibiotic');
        $svc = app(RduCalculationService::class);

        foreach (['KPI01', 'KPI03', 'KPI04', 'KPI05'] as $code) {
            $this->assertNotSame('not_configured', $svc->getOrCalculate($this->kpi($code), 2569, 0)['status'], $code);
        }
        foreach (['KPI02', 'KPI07', 'KPI10'] as $code) {
            $this->assertSame('not_configured', $svc->getOrCalculate($this->kpi($code), 2569, 0)['status'], $code);
        }
    }

    public function test_calculate_all_skips_unconfigured_kpis_and_stores_only_configured_ones(): void
    {
        $this->configure('antibiotic');

        $results = app(RduCalculationService::class)->calculateAll(2569, 3);

        $this->assertSame('not_configured', $results['KPI02']['status']);
        $this->assertNotSame('not_configured', $results['KPI01']['status']);
        $this->assertSame(
            ['KPI01', 'KPI03', 'KPI04', 'KPI05'],
            RduIndicatorResult::with('indicator')->get()->pluck('indicator.code')->sort()->values()->all()
        );
    }

    public function test_the_calculate_command_reports_skipped_kpis(): void
    {
        $this->configure('antibiotic');

        $this->artisan('rdu:calculate', ['year' => 2569, 'month' => 3])
            ->expectsOutputToContain('ยังไม่ตั้งค่า (ข้าม)')->assertSuccessful();
    }

    // ---- a saved set invalidates what was counted before ----

    public function test_a_result_saved_before_a_set_was_edited_is_ignored_and_one_saved_after_is_used(): void
    {
        $this->configure('antibiotic');
        $kpi = $this->kpi('KPI04');
        RduIndicatorResult::create(['indicator_id' => $kpi->id, 'year' => 2569, 'month' => 3, 'numerator' => 36, 'denominator' => 124,
            'result' => 29, 'status' => 'fail', 'calculated_at' => now()->subHours(2)]);
        $svc = app(RduCalculationService::class);

        // Nothing was ever edited on the settings page (only imported): the saved row stands.
        $this->assertTrue($svc->getOrCalculate($kpi, 2569, 3)['from_cache']);

        // A pharmacist saved the set an hour ago: the row from 2 hours ago was counted with other codes.
        DrugCodeSet::find('antibiotic')->update(['changed_at' => now()->subHour()]);
        app(DrugCodeSetService::class)->flush();
        $this->assertFalse($svc->getOrCalculate($kpi, 2569, 3)['from_cache']);
        $this->assertFalse($svc->getOrCalculateMany(collect([$kpi]), 2569, 3)->first()['from_cache']);
        $stale = collect($svc->getTrend($kpi, 2569))->firstWhere('month', 3);
        $this->assertNotSame(124, $stale['den']);

        // A row calculated after the edit is valid again.
        RduIndicatorResult::where('indicator_id', $kpi->id)->update(['calculated_at' => now()]);
        $this->assertTrue($svc->getOrCalculate($kpi, 2569, 3)['from_cache']);
        $this->assertDatabaseCount('rdu_indicator_results', 1);   // the stale row was never deleted
    }

    public function test_cached_counts_are_not_reused_after_a_set_is_saved(): void
    {
        $this->configure('antibiotic');
        Cache::flush();
        $source = new class extends MockRduDataSource {
            public int $calls = 0;
            public function getNumerator(string $kpiCode, int $year, int $month): int { $this->calls++; return 1; }
        };
        $this->app->instance(RduDataSourceInterface::class, $source);
        $query = app(RduQueryService::class);

        $query->getNumerator('KPI04', 2569, 3);
        $query->getNumerator('KPI04', 2569, 3);
        $this->assertSame(1, $source->calls);   // second read came from the cache

        DrugCodeSet::find('antibiotic')->increment('version');
        app(DrugCodeSetService::class)->flush();
        $query->getNumerator('KPI04', 2569, 3);
        $this->assertSame(2, $source->calls);   // a saved set re-counts

        $query->getNumerator('KPI02', 2569, 3);   // a KPI on another set is not affected by the antibiotic set
        $this->configure('antibiotic_fresh_wound');
        $query->getNumerator('KPI02', 2569, 3);
        $this->assertSame(4, $source->calls);
    }

    // ---- pages ----

    public function test_the_overview_shows_a_not_configured_card_for_every_kpi_and_counts_none_of_them(): void
    {
        $this->forbidCounting();

        $response = $this->actingAs($this->user())->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-not-configured-count', false)
            ->assertSee('ยังไม่ได้ตั้งค่ารหัสยาของตัวชี้วัดนี้')
            ->assertDontSee('data-attention', false)
            ->assertSee('ภาพรวม 0 ตัวชี้วัด');
        foreach (self::ACTIVE_KPI_CODES as $code) {
            $response->assertSee('data-kpi-card="'.$code.'" data-not-configured', false);
        }
        $response->assertSee('data-status-count="pass">0', false);
    }

    public function test_the_overview_mixes_configured_and_unconfigured_kpis(): void
    {
        $this->configure('antibiotic');

        $response = $this->actingAs($this->user())->get(route('dashboard'));

        $response->assertOk()->assertSee('ภาพรวม 4 ตัวชี้วัด');
        $response->assertSee('data-kpi-card="KPI02" data-not-configured', false);
        $response->assertDontSee('data-kpi-card="KPI01" data-not-configured', false);
        $this->assertSame(8, preg_match_all('/data-kpi-card="KPI\d+" data-not-configured/', $response->getContent()));
    }

    public function test_the_kpi_page_is_an_empty_card_with_a_button_to_the_settings(): void
    {
        $this->forbidCounting();

        $this->actingAs($this->user())->get(route('dashboard.kpi', 'KPI10'))
            ->assertOk()
            ->assertSee('data-not-configured-card', false)
            ->assertSee('ยังไม่ได้ตั้งค่าตัวชี้วัดนี้')
            ->assertSee('ยังขาด: Metformin (ยาที่วัด)')
            ->assertSee('ยังขาด: ยาลดระดับน้ำตาล (ตัวหาร)')
            ->assertSee('ยังขาด: รายการ lab eGFR')
            ->assertSee(route('drug-code-sets.index'), false);
    }

    public function test_a_configured_kpi_page_is_the_normal_page(): void
    {
        $this->configure('antibiotic');

        $this->actingAs($this->user())->get(route('dashboard.kpi', 'KPI04'))
            ->assertOk()->assertDontSee('data-not-configured-card', false);
    }

    public function test_the_sidebar_shows_how_many_kpis_are_not_configured(): void
    {
        $this->configure('antibiotic');

        $this->actingAs($this->user())->get(route('dashboard'))
            ->assertSee('ตั้งค่ารหัสยา')
            ->assertSee('title="ตัวชี้วัดที่ยังไม่ตั้งค่า 8 ตัว"', false);
    }

    public function test_the_counting_method_says_so_when_there_are_no_drug_codes(): void
    {
        $this->actingAs($this->user())->get(route('dashboard.counting-methods'))
            ->assertOk()->assertSee('data-drugs-not-configured', false);
    }

    public function test_the_drug_code_sets_page_lists_each_kpi_with_its_sets(): void
    {
        $this->configure('antibiotic');

        $this->actingAs($this->user())->get(route('drug-code-sets.index'))
            ->assertOk()
            ->assertSee('data-drug-code-kpi="KPI04"', false)
            ->assertSee('ใช้ร่วมกับ KPI03, KPI04, KPI05')
            ->assertSee('ตั้งค่าแล้ว')
            ->assertSee('ยังไม่ตั้งค่า')
            ->assertSee('การแก้ไขต้องได้รับสิทธิ์');
    }

    public function test_the_drug_code_sets_page_needs_a_login(): void
    {
        $this->get(route('drug-code-sets.index'))->assertRedirect(route('login'));
    }

    public function test_the_login_page_counts_kpis_that_are_not_configured(): void
    {
        $this->forbidCounting();
        $user = $this->user();
        $this->actingAs($user)->get(route('dashboard'));   // the overview records the snapshot
        auth()->logout();

        $snapshot = app(StatusSnapshotService::class)->forLogin();

        $this->assertSame(12, $snapshot['counts']['not_configured']);
        $this->assertSame(0, $snapshot['counts']['pass']);
        $this->get(route('login'))->assertOk()->assertSee('ยังไม่ตั้งค่า');
    }
}
