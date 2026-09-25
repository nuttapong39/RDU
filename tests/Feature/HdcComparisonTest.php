<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The HOSxP side comes from MockRduDataSource (testing environment): per month KPI01 = 340/399,
 * KPI03 = 182/200, so a full fiscal year is 12× those values.
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
            ->assertJsonPath('ours.hosxp.num', 340 * 12)
            ->assertJsonPath('ours.hosxp.den', 399 * 12)
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

    public function test_kpi02_is_not_linked_until_its_seed_is_corrected(): void
    {
        Http::fake();

        $this->actingAs($this->user())
            ->getJson(route('dashboard.kpi.hdc', ['code' => 'KPI02', 'year' => 2569]))
            ->assertOk()
            ->assertJsonPath('linked', false);

        Http::assertNothingSent();
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
}
