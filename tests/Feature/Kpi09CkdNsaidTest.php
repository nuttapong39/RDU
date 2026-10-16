<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\RduIndicator;
use App\Models\User;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * KPI09 — ร้อยละของผู้ป่วยนอกโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้รับ NSAIDs
 * (CONTEXT.md: ผู้ป่วยโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้ NSAIDs). Counted in patients like KPI08, but
 * "lower is better": getting the drug is the bad outcome.
 */
class Kpi09CkdNsaidTest extends TestCase
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

    /** One detail row per patient; `$nsaid` = got an NSAID at any OPD visit in the period. */
    private function row(string $hn, string $doctor, bool $nsaid): array
    {
        return [
            'HN' => $hn, 'VN' => "vn{$hn}", 'vstdate' => '2026-03-01', 'dx' => 'N183', 'fullname' => 'นาย ทดลอง xxxx',
            'drugitems_name' => $nsaid ? 'IBUPROFEN 400 mg. เม็ด' : null,
            'atb_cost' => $nsaid ? 12.0 : 0.0, 'total_drug_items' => 5,
            'doctor' => "Doctor {$doctor}", 'doctor_code' => "D{$doctor}",
            'coverage' => 'UC', 'department' => 'อายุรกรรม', 'room' => 'OPD',
        ];
    }

    private function bindRows(Collection $rows): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class($rows) extends MockRduDataSource {
            public function __construct(private Collection $rows) {}
            public function getDetails(string $kpiCode, int $year, int $month): Collection { return $kpiCode === 'KPI09' ? $this->rows : collect(); }
            public function getDetailsBetween(string $kpiCode, string $s, string $e): Collection { return $kpiCode === 'KPI09' ? $this->rows : collect(); }
        });
    }

    // ---- Seed / registration ----

    public function test_kpi09_is_seeded_as_ckd_stage_3_plus_patients_with_a_ten_percent_ceiling(): void
    {
        $kpi09 = $this->kpi('KPI09');

        $this->assertStringContainsString('จำนวนผู้ป่วย', $kpi09->numerator_desc);
        $this->assertStringContainsString('NSAIDs', $kpi09->numerator_desc);
        $this->assertStringContainsString('N183', $kpi09->denominator_desc);
        $this->assertStringNotContainsString('อย่างน้อย 1 ชนิด', $kpi09->denominator_desc);   // all CKD patients, not only those given a drug
        $this->assertSame(10.0, (float) $kpi09->target_value);
        $this->assertSame('lower', $kpi09->target_direction);
    }

    public function test_kpi09_reads_hosxp_counts_patients_and_getting_the_drug_is_bad(): void
    {
        $kpi09 = $this->kpi('KPI09');

        $this->assertTrue($kpi09->hasHosxpQuery());
        $this->assertFalse($kpi09->countUnit()['additive']);
        $this->assertTrue($kpi09->numeratorIsDrugReceived());
        $this->assertFalse($kpi09->receivingDrugIsGood());
    }

    // ---- The page ----

    public function test_kpi09_page_counts_patients_and_names_nsaids_in_ckd(): void
    {
        $this->bindRows(collect([$this->row('1', 'A', true), $this->row('2', 'A', false)]));

        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI09', 'year' => 2569]))
            ->assertOk()
            ->assertSee('ผู้ป่วย ทั้งหมด', false)
            ->assertSee('ผู้ป่วย ที่ได้ NSAIDs', false)
            ->assertSee('มูลค่ายา NSAIDs (บาท)', false)
            ->assertSee(json_encode('ผู้ป่วยที่ได้รับ NSAIDs'), false)    // trend legend ($numeratorLabel, via @json)
            ->assertDontSee('VISIT ทั้งหมด', false)
            ->assertDontSee('NSAIDs ≥ 2 รายการ', false);                 // KPI07's wording must not leak in
    }

    public function test_doctor_rate_is_red_above_the_ceiling_and_worst_first(): void
    {
        // DR "A": 10 patients, none on NSAIDs → 0% → green. DR "B": 10 patients, 3 on NSAIDs → 30% → red.
        $rows = collect();
        foreach (['A' => 0, 'B' => 3] as $doctor => $onNsaid) {
            for ($i = 0; $i < 10; $i++) {
                $rows->push($this->row("{$doctor}{$i}", $doctor, $i < $onNsaid));
            }
        }
        $this->bindRows($rows);

        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI09', 'year' => 2569]))
            ->assertOk()
            ->assertSee('อัตราการสั่ง NSAIDs ในผู้ป่วยโรคไตเรื้อรังรายแพทย์ผู้ตรวจ', false)
            ->assertSee('เกินเกณฑ์ 10%', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/text-emerald-600">0%</', $html);
        $this->assertMatchesRegularExpression('/text-red-600">30%</', $html);
        $this->assertLessThan(strpos($html, '>0%<'), strpos($html, '>30%<'));
    }
}
