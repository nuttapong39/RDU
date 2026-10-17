<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\User;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The overview page (/dashboard): a hero card with the status counts and the period filter, a "ต้องดูก่อน" bar
 * of every KPI not meeting its target, then the KPI cards grouped by category. Small samples (CONTEXT.md: เคสน้อย)
 * are flagged, not hidden.
 */
class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    /** Built without the factory: its faker userName() needs the PHP intl extension, missing on some hosts. */
    private function user(): User
    {
        return User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);
    }

    /** @param array<string, array{0: int, 1: int}> $counts KPI code => [numerator, denominator]; others keep the mock's figures. */
    private function withCounts(array $counts): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class($counts) extends MockRduDataSource {
            public function __construct(private array $counts) {}
            public function getNumerator(string $kpiCode, int $year, int $month): int
            {
                return $this->counts[$kpiCode][0] ?? parent::getNumerator($kpiCode, $year, $month);
            }
            public function getDenominator(string $kpiCode, int $year, int $month): int
            {
                return $this->counts[$kpiCode][1] ?? parent::getDenominator($kpiCode, $year, $month);
            }
        });
    }

    /** Every active KPI meeting its target. */
    private function allPassing(): array
    {
        return [
            'KPI01' => [10, 100], 'KPI02' => [10, 100], 'KPI03' => [90, 100], 'KPI04' => [10, 100],
            'KPI05' => [10, 100], 'KPI07' => [1, 100],  'KPI08' => [90, 100], 'KPI09' => [1, 100],
            'KPI10' => [90, 100], 'KPI11' => [10, 100], 'KPI12' => [0, 100],  'KPI13' => [1, 100],
        ];
    }

    private function overview(): string
    {
        return $this->actingAs($this->user())->get(route('dashboard', ['year' => 2569]))->assertOk()->getContent();
    }

    /** The HTML of the "ต้องดูก่อน" bar, or null when the page has none. */
    private function attentionBar(string $html): ?string
    {
        return preg_match('~<section[^>]*data-attention[^>]*>(.*?)</section>~s', $html, $m) ? $m[1] : null;
    }

    /** The HTML of one KPI card on the grid. */
    private function card(string $html, string $code): string
    {
        $this->assertSame(1, preg_match('~<a[^>]*data-kpi-card="'.$code.'"[^>]*>(.*?)</a>~s', $html, $m), "card $code");

        return $m[1];
    }

    // ---- Hero ----

    public function test_hero_names_the_project_and_where_the_data_comes_from(): void
    {
        $this->actingAs($this->user())->get(route('dashboard', ['year' => 2569]))
            ->assertOk()
            ->assertSee('RDU Dashboard')
            ->assertSee('การใช้ยาอย่างสมเหตุสมผล')
            ->assertSee('ข้อมูลจาก HOSxP · อัปเดตทุก 1 ชั่วโมง')
            ->assertSee('<svg', false)
            ->assertDontSee('สวัสดีตอน')        // the greeting used the server's UTC hour
            ->assertDontSee('อัปเดตล่าสุด');     // was the time the page rendered, not when the data was calculated
    }

    public function test_hero_counts_kpis_in_each_status(): void
    {
        // KPI01 57.5% and KPI02 52.2% fail (≤ 40%); KPI04 42% is within 5 points of 40% → warning; the rest pass.
        $this->withCounts(['KPI01' => [575, 1000], 'KPI02' => [522, 1000], 'KPI04' => [42, 100]] + $this->allPassing());

        $html = $this->overview();

        $this->assertMatchesRegularExpression('~data-status-count="pass">\s*9\s*<~', $html);
        $this->assertMatchesRegularExpression('~data-status-count="warning">\s*1\s*<~', $html);
        $this->assertMatchesRegularExpression('~data-status-count="fail">\s*2\s*<~', $html);
        $this->assertMatchesRegularExpression('~data-status-count="na">\s*0\s*<~', $html);
    }

    // ---- Removed sections ----

    public function test_sections_that_repeated_the_cards_or_misled_are_gone(): void
    {
        $this->actingAs($this->user())->get(route('dashboard', ['year' => 2569]))
            ->assertOk()
            ->assertDontSee('overviewChart', false)       // mixed lower-/higher-is-better bars on one axis
            ->assertDontSee('chart.umd', false)
            ->assertDontSee('% เฉลี่ย')                    // averaged percentages of opposite directions
            ->assertDontSee('สรุปตามหมวดตัวชี้วัด')
            ->assertDontSee('เมนูลัด')
            ->assertDontSee('ประวัติกิจกรรมล่าสุด')
            ->assertDontSee('ตัวชี้วัดที่ต้องเฝ้าระวัง')
            ->assertDontSee('ดูรายละเอียด');
    }

    // ---- "ต้องดูก่อน" bar ----

    public function test_attention_bar_lists_every_failing_and_near_target_kpi_failures_first(): void
    {
        // Six fail (more than the old watchlist's cap of 5), one warning.
        $this->withCounts([
            'KPI04' => [42, 100],                                               // warning, listed after the failures
            'KPI01' => [575, 1000], 'KPI02' => [522, 1000], 'KPI05' => [50, 100],
            'KPI07' => [30, 100],   'KPI11' => [60, 100],   'KPI12' => [1, 1000], // KPI12: 0.1% of a 0% target, still fail
        ] + $this->allPassing());

        $bar = $this->attentionBar($this->overview());

        $this->assertNotNull($bar);
        $this->assertSame(7, substr_count($bar, 'data-attention-item'));
        foreach (['KPI01', 'KPI02', 'KPI05', 'KPI07', 'KPI11', 'KPI12', 'KPI04'] as $code) {
            $this->assertStringContainsString(route('dashboard.kpi', $code), $bar);
        }
        $this->assertStringContainsString('57.5%', $bar);
        $this->assertStringContainsString('≤ 40', $bar);
        $this->assertGreaterThan(strpos($bar, 'KPI12'), strpos($bar, 'KPI04'), 'the warning comes after every failure');
        $this->assertStringNotContainsString('KPI03', $bar);
    }

    public function test_attention_bar_is_hidden_when_every_kpi_meets_its_target(): void
    {
        $this->withCounts($this->allPassing());

        $this->assertNull($this->attentionBar($this->overview()));
    }

    // ---- KPI cards ----

    public function test_card_shows_the_counts_with_their_unit_and_a_target_tick(): void
    {
        $this->withCounts(['KPI01' => [1032, 1794], 'KPI08' => [71, 87]] + $this->allPassing());

        $html = $this->overview();

        $kpi01 = $this->card($html, 'KPI01');
        $this->assertStringContainsString('1,032 / 1,794 visit', $kpi01);
        $this->assertStringContainsString('57.5%', $kpi01);
        $this->assertStringContainsString('ไม่ผ่านเกณฑ์', $kpi01);
        $this->assertMatchesRegularExpression('~data-target-tick[^>]*left:\s*40%~', $kpi01);

        $kpi08 = $this->card($html, 'KPI08');
        $this->assertStringContainsString('71 / 87 ผู้ป่วย', $kpi08);   // counted in patients
        $this->assertMatchesRegularExpression('~data-target-tick[^>]*left:\s*80%~', $kpi08);
    }

    public function test_card_drops_the_decoration(): void
    {
        $kpi01 = $this->card($this->overview(), 'KPI01');

        $this->assertStringNotContainsString('cat-badge-antibiotic', $kpi01);   // colour is kept for status only
        $this->assertStringNotContainsString('font-size: 108px', $kpi01);       // watermark icon
    }

    public function test_the_empty_drug_quality_category_has_no_section(): void
    {
        $this->assertStringNotContainsString('หมวดคุณภาพยา', $this->overview());
    }

    // ---- เคสน้อย ----

    public function test_a_denominator_below_five_is_flagged_but_still_judged(): void
    {
        // KPI03 1/1 = 100% passes on a single case; KPI12 1/4 = 25% fails on four.
        $this->withCounts(['KPI03' => [1, 1], 'KPI12' => [1, 4], 'KPI05' => [1, 5]] + $this->allPassing());

        $html = $this->overview();

        $kpi03 = $this->card($html, 'KPI03');
        $this->assertStringContainsString('เคสน้อย · n=1', $kpi03);
        $this->assertStringContainsString('ผ่านเกณฑ์', $kpi03);

        $this->assertStringContainsString('เคสน้อย · n=4', $this->card($html, 'KPI12'));
        $this->assertStringNotContainsString('เคสน้อย', $this->card($html, 'KPI05'));   // five is enough
        $this->assertStringNotContainsString('เคสน้อย', $this->card($html, 'KPI01'));

        $bar = $this->attentionBar($html);
        $this->assertStringContainsString('KPI12', $bar);                // a small sample still needs looking at
        $this->assertStringContainsString('เคสน้อย · n=4', $bar);
    }

    public function test_no_data_is_not_a_small_sample(): void
    {
        $this->withCounts(['KPI03' => [0, 0]] + $this->allPassing());

        $kpi03 = $this->card($this->overview(), 'KPI03');

        $this->assertStringNotContainsString('เคสน้อย', $kpi03);
        $this->assertStringContainsString('ไม่มีข้อมูล', $kpi03);
    }
}
