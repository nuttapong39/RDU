<?php

namespace Tests\Feature;

use App\Models\RduIndicator;
use App\Models\User;
use App\Services\RduCalculationService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The HOSxP side comes from MockRduDataSource (testing environment), which returns KPI01 = 340/399 for
 * any period — including the whole-year (month 0) figure the comparison uses.
 */
class HdcComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    private function row(string $hospcode, string $areacode, array $counts): array
    {
        return array_merge([
            'id' => 'x', 'hospcode' => $hospcode, 'areacode' => $areacode, 'flag_sent' => '0',
            'date_com' => '202609240926', 'b_year' => '2569',
        ], $counts);
    }

    private function fakeHdc(array $rows): void
    {
        Http::fake(['opendata.moph.go.th/*' => Http::response(['data' => $rows, 'total' => (string) count($rows)], 201)]);
    }

    /** Built without the factory: its faker userName() needs the PHP intl extension, missing on some hosts. */
    private function user(): User
    {
        return User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);
    }

    public function test_guest_cannot_use_the_endpoint(): void
    {
        $this->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI01', 'year' => 2569]))->assertUnauthorized();
        $this->get(route('dashboard.kpi.hdc', ['code' => 'KPI01', 'year' => 2569]))->assertRedirect(route('login'));
    }

    public function test_hdc_timeout_returns_an_error_state(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

        $this->actingAs($this->user())
            ->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI01', 'year' => 2569]))
            ->assertStatus(502)
            ->assertJsonPath('error', 'hdc_unavailable')
            ->assertJsonMissingPath('exception');
    }

    public function test_kpi01_compares_our_hospital_and_ranks_the_province(): void
    {
        $this->fakeHdc([
            $this->row('11179', '55090111', ['target' => 1670, 'result' => 923, 'target1' => 1214, 'result1' => 613]),
            $this->row('10716', '55010100', ['target' => 4390, 'result' => 1136, 'target1' => 2415, 'result1' => 159]),
            $this->row('11173', '55020204', ['target' => 1039, 'result' => 406, 'target1' => 698, 'result1' => 203]),
        ]);

        $res = $this->actingAs($this->user())
            ->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI01', 'year' => 2569]))
            ->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('hospcode', '11179')
            ->assertJsonPath('ours.hosxp.num', 340)
            ->assertJsonPath('ours.hosxp.den', 399)
            ->assertJsonPath('ours.hdc.num', 923)
            ->assertJsonPath('ours.hdc.den', 1670)
            ->assertJsonPath('ours.hdc.pct', 55.3)
            ->assertJsonPath('ours.hdc.processed_at', '24 ก.ย. 2569 09:26');

        $rows = $res->json('province.rows');
        $this->assertSame(['10716', '11173', '11179'], array_column($rows, 'hospcode')); // lower-is-better: best first
        $this->assertSame('อ.เชียงกลาง (11179)', $rows[2]['label']);
        $this->assertTrue($rows[2]['is_ours']);
        $this->assertSame(round((923 + 1136 + 406) / (1670 + 4390 + 1039) * 100, 1), $res->json('province.avg_pct'));
    }

    public function test_kpi03_converts_hdc_to_not_received(): void
    {
        $this->fakeHdc([$this->row('11179', '55090111', ['target' => 10, 'result' => 3])]);

        $this->actingAs($this->user())
            ->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI03', 'year' => 2569]))
            ->assertOk()
            ->assertJsonPath('ours.hdc.num', 7)
            ->assertJsonPath('ours.hdc.den', 10);
    }

    public function test_kpi02_uses_the_second_column_pair_and_has_no_hosxp_side(): void
    {
        $this->fakeHdc([$this->row('11179', '55090111', ['target' => 1670, 'result' => 923, 'target1' => 1214, 'result1' => 613])]);

        $this->actingAs($this->user())
            ->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI02', 'year' => 2569]))
            ->assertOk()
            ->assertJsonPath('ours.hdc.num', 613)
            ->assertJsonPath('ours.hdc.den', 1214)
            ->assertJsonPath('ours.hdc.status', 'fail') // 50.5% against the corrected ≤ 40% target
            ->assertJsonPath('ours.hosxp.den', 0);     // KPI02 has no HOSxP query yet
    }

    public function test_kpi12_sums_the_twelve_monthly_columns_of_s_rdu10(): void
    {
        $months = ['10', '11', '12', '01', '02', '03', '04', '05', '06', '07', '08', '09'];
        $counts = [];
        foreach ($months as $i => $m) {
            $counts["t$m"] = 100 + $i;          // visits given ≥ 1 RAS drug that month
            $counts["r$m"] = $i === 4 ? 1 : 0;  // one dual-blockade visit in February
        }
        $this->fakeHdc([$this->row('11179', '55090111', $counts)]);

        $this->actingAs($this->user())
            ->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI12', 'year' => 2569]))
            ->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('ours.hdc.den', 1266)   // 100 + 101 + … + 111
            ->assertJsonPath('ours.hdc.num', 1)
            ->assertJsonPath('ours.hdc.status', 'fail'); // 0% target: one case already fails
    }

    // ---- KPIs without a HOSxP query show their HDC whole-year figure ----

    public function test_kpi_without_hosxp_query_shows_the_hdc_figure_on_its_page_and_overview(): void
    {
        $this->fakeHdc([$this->row('11179', '55090111', ['target1' => 2000, 'result1' => 46])]); // s_rdu8 second pair, acute diarrhoea
        $user = $this->user();

        $this->actingAs($user)->get(route('dashboard.kpi', ['code' => 'KPI02', 'year' => 2569]))
            ->assertOk()
            ->assertSee('แสดงผลจาก HDC (ข้อมูลส่วนกลาง) แทนชั่วคราว', false)
            ->assertSee('2.3')                   // 46 / 2000
            ->assertSee('ผ่านเกณฑ์');           // ≤ 40%, lower is better

        $this->actingAs($user)->get(route('dashboard', ['year' => 2569]))
            ->assertOk()->assertSee('HDC · ทั้งปี', false)
            ->assertDontSee('ไม่เปลี่ยนตามเดือนที่เลือก', false);

        $this->actingAs($user)->get(route('dashboard', ['year' => 2569, 'month' => 3]))
            ->assertOk()->assertSee('ไม่เปลี่ยนตามเดือนที่เลือก', false);
    }

    public function test_hdc_whole_year_figure_is_not_spread_over_quarter_cards_or_the_visit_donut(): void
    {
        $this->fakeHdc([$this->row('11179', '55090111', ['target1' => 2000, 'result1' => 46])]);

        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI02', 'year' => 2569, 'quarter' => 2]))
            ->assertOk()
            ->assertSee('ตัวเลขนี้จึงไม่เปลี่ยนตามช่วงเวลาที่เลือก', false)
            ->assertDontSee('id="visitProportionDonut"', false)
            ->assertDontSee('id="quarterPieChart"', false)
            ->assertDontSee('46 / 2,000');        // quarter cards would print "num / den ราย"
    }

    public function test_kpi_with_hosxp_query_ignores_hdc(): void
    {
        Http::fake();
        $kpi01 = RduIndicator::where('code', 'KPI01')->first();

        $item = app(RduCalculationService::class)->getOrCalculate($kpi01, 2569, 0);
        $this->assertSame(340, $item['numerator']); // MockRduDataSource KPI01 = 340/399
        $this->assertNotSame('hdc', $item['source'] ?? 'hosxp');
        Http::assertNothingSent();

        // (The page still fetches HDC for the sidebar's HDC-backed KPIs — just not for KPI01 itself.)
        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI01', 'year' => 2569]))
            ->assertOk()
            ->assertDontSee('แสดงผลจาก HDC (ข้อมูลส่วนกลาง) แทนชั่วคราว', false)
            ->assertSee('85.2');
    }

    public function test_hdc_outage_shows_no_data_with_a_note_and_is_not_retried_on_every_page_load(): void
    {
        Http::fake(['opendata.moph.go.th/*' => Http::response('down', 500)]);
        $user = $this->user();

        $this->actingAs($user)->get(route('dashboard.kpi', ['code' => 'KPI02', 'year' => 2569]))
            ->assertOk()
            ->assertSee('ดึงข้อมูลจาก HDC ไม่ได้ในขณะนี้', false);
        $sent = count(Http::recorded());

        $this->actingAs($user)->get(route('dashboard.kpi', ['code' => 'KPI02', 'year' => 2569]))->assertOk();
        $this->actingAs($user)->get(route('dashboard', ['year' => 2569]))
            ->assertOk()->assertSee('HDC ไม่ตอบสนอง', false);
        $this->assertSame($sent, count(Http::recorded()), 'HDC was called again although it just failed');
    }

    public function test_hdc_timeout_is_waited_out_once_per_page_not_once_per_hdc_table(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        // The overview needs an HDC table for every linked KPI without a HOSxP query; only the first may hit the network.
        $this->actingAs($this->user())->get(route('dashboard', ['year' => 2569]))->assertOk();
        $this->assertSame(1, $attempts);
    }

    public function test_unlinked_kpi_reports_not_linked_without_calling_hdc(): void
    {
        Http::fake();

        $this->actingAs($this->user())
            ->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI06', 'year' => 2569]))
            ->assertOk()
            ->assertJsonPath('linked', false);

        Http::assertNothingSent();
    }

    public function test_hdc_failure_returns_an_error_state_and_is_not_cached(): void
    {
        Http::fake(['opendata.moph.go.th/*' => Http::sequence()
            ->push('boom', 500)
            ->push(['data' => [$this->row('11179', '55090111', ['target' => 1670, 'result' => 923])]], 201)]);

        $this->actingAs($this->user())
            ->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI01', 'year' => 2569]))
            ->assertStatus(502)
            ->assertJsonPath('error', 'hdc_unavailable');

        // The failure was not cached, so the retry reaches HDC again and succeeds.
        $this->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI01', 'year' => 2569]))->assertOk();
    }

    public function test_second_request_is_served_from_cache(): void
    {
        $this->fakeHdc([$this->row('11179', '55090111', ['target' => 1670, 'result' => 923])]);
        $user = $this->user();

        $this->actingAs($user)->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI01', 'year' => 2569]))->assertOk();
        $this->actingAs($user)->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI01', 'year' => 2569]))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_every_kpi_page_has_the_compare_button(): void
    {
        $user = $this->user();
        foreach (range(1, 13) as $n) {
            $this->actingAs($user)->get(route('dashboard.kpi', sprintf('KPI%02d', $n)))
                ->assertOk()->assertSee('id="hdcCompareBtn"', false);
        }
    }

    public function test_compare_button_is_the_hdc_logo_with_an_accessible_name(): void
    {
        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', 'KPI01'))->assertOk()->getContent();

        preg_match('/<button[^>]*id="hdcCompareBtn".*?<\/button>/s', $html, $m);
        $this->assertNotEmpty($m, 'compare button not found');
        $button = $m[0];

        $this->assertStringContainsString('aria-label="เทียบข้อมูลกับ HDC"', $button);
        $this->assertStringContainsString('images/hdc-logo.png', $button);
        $this->assertStringNotContainsString('เทียบ HDC', strip_tags($button)); // logo only, no visible label
        $this->assertFileExists(public_path('images/hdc-logo.png'));
    }
}
