<?php

namespace Tests\Feature;

use App\DataSources\HosxpRduDataSource;
use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\RduIndicator;
use App\Models\User;
use App\Services\CountingMethodService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The "วิธีนับ" (counting method) explanation shown on every KPI page and on one page for all KPIs
 * (CONTEXT.md: วิธีนับ). Its drug and diagnosis codes must be the ones the query really counts.
 */
class CountingMethodTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    /** Built without the factory: its faker userName() needs the PHP intl extension, missing on some hosts. */
    private function user(): User
    {
        return User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);
    }

    private function kpi(string $code): RduIndicator
    {
        return RduIndicator::where('code', $code)->firstOrFail();
    }

    private function describe(string $code): array
    {
        return app(CountingMethodService::class)->describe($this->kpi($code));
    }

    public function test_every_kpi_on_hosxp_explains_how_it_counts(): void
    {
        foreach (config('rdu.hosxp_kpis') as $code) {
            $this->assertNotNull(app(HosxpRduDataSource::class)->countingMethodFor($code), "{$code} has no counting method");
            $this->assertNotNull($this->describe($code)['method']);
        }
    }

    public function test_a_kpi_without_a_query_has_no_counting_method_yet(): void
    {
        $this->assertNull($this->describe('KPI02')['method']);
    }

    public function test_kpi09_lists_the_nsaids_kpi07_counts_and_the_codes_it_leaves_out(): void
    {
        $kpi07 = $this->describe('KPI07');
        $kpi09 = $this->describe('KPI09');

        $this->assertSame(array_column($kpi07['drugs'], 'icode'), array_column($kpi09['drugs'], 'icode'));
        $this->assertContains('1610052', array_column($kpi09['drugs'], 'icode'));   // Diclofenac injection
        $this->assertSame(['N183', 'N184', 'N185'], array_column($kpi09['diagnoses'][0]['codes'], 'code'));
        $this->assertSame(['N182', 'N189'], array_column($kpi09['excluded'], 'code'));
    }

    public function test_drug_and_diagnosis_names_come_from_hosxp(): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class extends MockRduDataSource {
            public function getDrugDescriptions(array $icodes): array
            {
                return ['1483891' => ['name' => 'IBUPROFEN', 'strength' => '400 mg.', 'units' => 'เม็ด']];
            }

            public function getIcd10Descriptions(array $codes): array
            {
                return ['N184' => ['name' => 'Chronic kidney disease, stage 4', 'tname' => 'โรคไตเรื้อรังระยะที่ 4']];
            }
        });

        $kpi09 = $this->describe('KPI09');
        $ibuprofen = collect($kpi09['drugs'])->firstWhere('icode', '1483891');

        $this->assertSame('IBUPROFEN', $ibuprofen['name']);
        $this->assertSame('400 mg.', $ibuprofen['strength']);
        $this->assertSame('โรคไตเรื้อรังระยะที่ 4', collect($kpi09['diagnoses'][0]['codes'])->firstWhere('code', 'N184')['name']);
        $this->assertTrue($kpi09['namesAvailable']);
    }

    public function test_codes_are_still_shown_when_hosxp_cannot_be_reached_and_it_is_tried_once(): void
    {
        $source = new class extends MockRduDataSource {
            public int $calls = 0;
            public function getDrugDescriptions(array $icodes): array { $this->calls++; throw new RuntimeException('connection refused'); }
        };
        $this->app->instance(RduDataSourceInterface::class, $source);

        $service = app(CountingMethodService::class);
        $kpi09 = $service->describe($this->kpi('KPI09'));
        $service->describe($this->kpi('KPI07'));   // e.g. the all-KPI page: don't wait on a dead HOSxP again

        $this->assertFalse($kpi09['namesAvailable']);
        $this->assertCount(6, $kpi09['drugs']);
        $this->assertNull($kpi09['drugs'][0]['name']);
        $this->assertSame(1, $source->calls);
    }

    public function test_a_name_not_found_is_looked_up_again_later(): void
    {
        // Before HOSxP is configured the lookup finds nothing — that must not stick in the cache.
        $this->describe('KPI09');

        $this->app->instance(RduDataSourceInterface::class, new class extends MockRduDataSource {
            public function getDrugDescriptions(array $icodes): array
            {
                return ['1483891' => ['name' => 'IBUPROFEN', 'strength' => '400 mg.', 'units' => 'เม็ด']];
            }
        });
        $this->app->forgetInstance(\App\Services\RduQueryService::class);

        $this->assertSame('IBUPROFEN', collect($this->describe('KPI09')['drugs'])->firstWhere('icode', '1483891')['name']);
    }

    // ---- The pages ----

    public function test_kpi_page_has_a_counting_method_button_and_dialog(): void
    {
        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI09', 'year' => 2569]))
            ->assertOk()
            ->assertSee('aria-controls="countingMethodModal"', false)
            ->assertSee('วิธีนับ', false)
            ->assertSee('ผู้ป่วย (คน)', false)                         // unit
            ->assertSee('ห้องฉุกเฉิน', false)                           // numerator wording
            ->assertSee('1610052', false)                              // icode
            ->assertSee('N182', false)                                 // deliberately not counted
            ->assertSee('docs/SQL_KPI/KPI9.sql', false)                // source
            ->assertSee('ยอดไตรมาสและทั้งปีนับคนไม่ซ้ำ', false)        // patient KPI: quarters are distinct people
            ->assertSee(route('dashboard.counting-methods'), false);
    }

    public function test_a_visit_kpi_says_quarters_are_summed_visits(): void
    {
        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI04', 'year' => 2569]))
            ->assertOk()
            ->assertSee('ยอดไตรมาสและทั้งปีเท่ากับผลรวมรายเดือน', false)
            ->assertSee('J069', false);
    }

    public function test_a_kpi_without_a_query_says_it_awaits_the_pharmacist(): void
    {
        $kpi02 = $this->kpi('KPI02');

        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI02', 'year' => 2569]))
            ->assertOk()
            ->assertSee('ยังรอนิยามจากเภสัชกร', false)
            ->assertSee(e($kpi02->numerator_desc), false);
    }

    public function test_one_page_explains_every_kpi(): void
    {
        $response = $this->actingAs($this->user())->get(route('dashboard.counting-methods'))->assertOk();

        foreach (RduIndicator::active()->pluck('code') as $code) {
            $response->assertSee('id="method-'.$code.'"', false);
        }
        $response->assertSee('ยังรอนิยามจากเภสัชกร', false);
    }

    public function test_the_counting_methods_page_requires_login(): void
    {
        $this->get(route('dashboard.counting-methods'))->assertRedirect();
    }
}
