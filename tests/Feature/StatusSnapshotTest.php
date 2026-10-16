<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\User;
use App\Services\RduCalculationService;
use App\Services\StatusSnapshotService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

/**
 * The status snapshot the login page shows (CONTEXT.md: ภาพสถานะล่าสุด): recorded when the overview
 * computes the current fiscal year, read by the login page without ever querying HOSxP, and limited
 * to statuses — no percentages or counts reach someone who has not logged in.
 */
class StatusSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();              // HDC-backed KPIs: no network in tests
        Storage::fake('local');    // a fresh, throw-away disk per test (inside the temp test storage)
    }

    /** Built without the factory: its faker userName() needs the PHP intl extension, missing on some hosts. */
    private function user(): User
    {
        return User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);
    }

    private function currentYear(): int
    {
        return app(RduCalculationService::class)->currentFiscalYear();
    }

    private function snapshot(): array
    {
        return app(StatusSnapshotService::class)->forLogin();
    }

    private function statusOf(string $code): string
    {
        return collect($this->snapshot()['kpis'])->firstWhere('code', $code)['status'];
    }

    /** A data source that fails the test if anything asks HOSxP for figures. */
    private function forbidHosxp(): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class extends MockRduDataSource {
            public function getNumerator(string $kpiCode, int $year, int $month): int { throw new LogicException('login page queried HOSxP'); }
            public function getDenominator(string $kpiCode, int $year, int $month): int { throw new LogicException('login page queried HOSxP'); }
            public function getDetails(string $kpiCode, int $year, int $month): Collection { throw new LogicException('login page queried HOSxP'); }
        });
    }

    // ---- Recording ----

    public function test_there_is_no_status_before_the_overview_has_been_computed(): void
    {
        $snapshot = $this->snapshot();

        $this->assertNull($snapshot['takenAt']);
        $this->assertCount(13, $snapshot['kpis']);
        $this->assertSame(13, $snapshot['counts']['na']);
    }

    public function test_the_overview_of_the_current_fiscal_year_records_the_snapshot(): void
    {
        $this->actingAs($this->user())->get(route('dashboard', ['year' => $this->currentYear()]))->assertOk();

        $this->assertNotNull($this->snapshot()['takenAt']);
        $this->assertSame('pass', $this->statusOf('KPI09'));   // mock 23 / 892 = 2.6% ≤ 10%
        $this->assertSame('fail', $this->statusOf('KPI12'));   // mock 2 / 800 > 0%
    }

    public function test_a_filtered_overview_does_not_overwrite_the_snapshot(): void
    {
        $this->actingAs($this->user());
        $this->get(route('dashboard', ['year' => $this->currentYear(), 'month' => 3]))->assertOk();
        $this->get(route('dashboard', ['year' => $this->currentYear() - 1]))->assertOk();

        $this->assertNull($this->snapshot()['takenAt']);
    }

    public function test_a_snapshot_from_an_earlier_fiscal_year_is_not_shown(): void
    {
        $kpiData = app(RduCalculationService::class)->getOrCalculateMany(
            \App\Models\RduIndicator::active()->ordered()->get(), $this->currentYear() - 1, 0
        );
        app(StatusSnapshotService::class)->record($this->currentYear() - 1, $kpiData);

        $this->assertSame(13, $this->snapshot()['counts']['na']);
    }

    // ---- The login page ----

    public function test_the_login_page_shows_the_snapshot_without_querying_hosxp(): void
    {
        $this->actingAs($this->user())->get(route('dashboard', ['year' => $this->currentYear()]));
        auth()->logout();
        $this->forbidHosxp();

        $counts = $this->snapshot()['counts'];

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="rduScene"', false)
            ->assertSeeText('ผ่านเกณฑ์ '.$counts['pass'])
            ->assertSeeText('ไม่ผ่านเกณฑ์ '.$counts['fail'])
            ->assertSee('สถานะ ณ', false)
            ->assertSee('อัปเดตเมื่อมีผู้เปิดหน้าภาพรวม', false)
            ->assertDontSee('Real-time', false)
            ->assertDontSee('เรียลไทม์', false);
    }

    public function test_the_login_page_gives_statuses_only_never_figures(): void
    {
        $this->actingAs($this->user())->get(route('dashboard', ['year' => $this->currentYear()]));
        auth()->logout();

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<script type="application/json" id="rduSceneData">(.*?)</script>#s', $html, $m));
        $data = json_decode($m[1], true);
        $this->assertCount(13, $data['kpis']);
        foreach ($data['kpis'] as $kpi) {
            $this->assertSame(['code', 'name', 'category', 'status'], array_keys($kpi));
        }
        $this->assertStringNotContainsString('2.6%', $html);    // KPI09's mock result
        $this->assertStringNotContainsString('892', $html);     // …and its denominator
    }

    public function test_the_login_page_still_works_when_the_app_database_cannot_be_read(): void
    {
        \Illuminate\Support\Facades\Schema::rename('rdu_indicators', 'rdu_indicators_away');   // the in-memory test database only

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('ยังไม่มีข้อมูลสถานะ', false);
    }

    public function test_a_malformed_snapshot_file_counts_as_no_data(): void
    {
        Storage::disk('local')->put('status_snapshot.json', json_encode(['year' => $this->currentYear(), 'statuses' => ['KPI09' => 'pass']]));

        $this->assertSame(13, $this->snapshot()['counts']['na']);
        $this->get(route('login'))->assertOk()->assertSee('ยังไม่มีข้อมูลสถานะ', false);
    }

    public function test_the_time_is_shown_in_thai_local_time(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-16 05:11:00', 'UTC'));
        $this->actingAs($this->user())->get(route('dashboard', ['year' => $this->currentYear()]));

        $this->assertSame('16 ต.ค. 2569 12:11 น.', $this->snapshot()['takenAtText']);   // UTC+7
    }

    public function test_the_login_page_works_before_any_snapshot_exists(): void
    {
        $this->forbidHosxp();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('ยังไม่มีข้อมูลสถานะ', false);
    }
}
