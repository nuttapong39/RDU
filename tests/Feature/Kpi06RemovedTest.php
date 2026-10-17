<?php

namespace Tests\Feature;

use App\Models\RduIndicator;
use App\Models\RduIndicatorResult;
use App\Models\User;
use App\Services\StatusSnapshotService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * KPI06 was removed on 2026-10-17 (no data source). Its row stays in rdu_indicators switched off, so the
 * stored results survive and the other KPIs keep their codes; no page shows or serves it.
 */
class Kpi06RemovedTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    /** Built without the factory: its faker userName() needs the PHP intl extension, missing on some hosts. */
    private function user(): User
    {
        return User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);
    }

    public function test_the_seeder_switches_kpi06_off_and_every_other_kpi_on(): void
    {
        $this->assertFalse(RduIndicator::where('code', 'KPI06')->firstOrFail()->is_active);
        $this->assertSame(self::ACTIVE_KPI_CODES, RduIndicator::active()->ordered()->pluck('code')->all());
    }

    public function test_reseeding_switches_an_active_kpi06_off_and_keeps_its_stored_results(): void
    {
        $kpi06 = RduIndicator::where('code', 'KPI06')->firstOrFail();
        $kpi06->update(['is_active' => true]);   // as on a server seeded before the removal
        RduIndicatorResult::factory()->create(['indicator_id' => $kpi06->id, 'year' => 2568, 'month' => 3]);

        $this->seed(RduIndicatorSeeder::class);

        $this->assertFalse($kpi06->fresh()->is_active);
        $this->assertSame(1, RduIndicatorResult::where('indicator_id', $kpi06->id)->count());
    }

    public function test_the_overview_counts_twelve_kpis_and_has_no_kpi06(): void
    {
        $this->actingAs($this->user())->get(route('dashboard', ['year' => 2569]))
            ->assertOk()
            ->assertSee('<title>ภาพรวม 12 ตัวชี้วัด RDU', false)
            ->assertSee('<span>ภาพรวม 12 ตัวชี้วัด</span>', false)
            ->assertDontSee('13 ตัวชี้วัด', false)
            ->assertDontSee(route('dashboard.kpi', 'KPI06'), false);
    }

    public function test_the_kpi06_page_and_its_hdc_comparison_are_not_found(): void
    {
        Http::fake();
        $user = $this->user();

        $this->actingAs($user)->get(route('dashboard.kpi', 'KPI06'))->assertNotFound();
        $this->actingAs($user)->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI06', 'year' => 2569]))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_the_counting_methods_page_and_the_login_scene_leave_kpi06_out(): void
    {
        $this->actingAs($this->user())->get(route('dashboard.counting-methods'))
            ->assertOk()
            ->assertDontSee('KPI06', false);

        $this->assertSame(self::ACTIVE_KPI_CODES, array_column(app(StatusSnapshotService::class)->forLogin()['kpis'], 'code'));
    }
}
