<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\RduIndicator;
use App\Models\User;
use App\Queries\Kpi\Antibiotic\FreshAccidentWoundAntibioticQuery;
use App\Queries\Kpi\CountingMethod;
use App\Services\HdcDataService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * KPI02 — ร้อยละการใช้ยาปฏิชีวนะในบาดแผลสดจากอุบัติเหตุ ไม่นับแผลสัตว์กัด แผลลึกถึงกล้ามเนื้อ และแผลไฟไหม้น้ำร้อนลวก
 * (CONTEXT.md: บาดแผลสดจากอุบัติเหตุ). Defined to match HDC s_rdu8 (target1 / result1): OPD visits with both an
 * injury code and an external-cause code, visits with an excluded code dropped whole.
 */
class Kpi02FreshAccidentWoundAntibioticTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    /** Built without the factory: its faker userName() needs the PHP intl extension, missing on some hosts. */
    private function user(): User
    {
        return User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);
    }

    private function kpi(): RduIndicator
    {
        return RduIndicator::where('code', 'KPI02')->firstOrFail();
    }

    /** One detail row per visit; `$given` = got an antibiotic in that visit. */
    private function row(string $vn, string $doctor, bool $given): array
    {
        return [
            'HN' => "hn{$vn}", 'VN' => $vn, 'vstdate' => '2026-01-10', 'dx' => 'S610,W26', 'fullname' => 'นาย ทดลอง xxxx',
            'drugitems_name' => $given ? 'Di - cloxacillin 250 mg. CAPSULES' : null,
            'atb_cost' => $given ? 12.0 : 0.0, 'total_drug_items' => 3,
            'doctor' => "Doctor {$doctor}", 'doctor_code' => "D{$doctor}",
            'coverage' => 'UC', 'department' => 'อุบัติเหตุและฉุกเฉิน', 'room' => 'ER',
        ];
    }

    private function bindRows(Collection $rows): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class($rows) extends MockRduDataSource {
            public function __construct(private Collection $rows) {}
            public function getDetails(string $kpiCode, int $year, int $month): Collection { return $kpiCode === 'KPI02' ? $this->rows : collect(); }
            public function getDetailsBetween(string $kpiCode, string $s, string $e): Collection { return $kpiCode === 'KPI02' ? $this->rows : collect(); }
        });
    }

    private function method(): CountingMethod
    {
        return app(FreshAccidentWoundAntibioticQuery::class)->countingMethod();
    }

    // ---- Seed / registration ----

    public function test_kpi02_is_seeded_as_fresh_accident_wound_visits_with_a_forty_percent_ceiling(): void
    {
        $kpi02 = $this->kpi();

        $this->assertStringContainsString('ยาปฏิชีวนะ', $kpi02->numerator_desc);
        $this->assertStringContainsString('ผู้ป่วยนอก', $kpi02->denominator_desc);
        $this->assertStringContainsString('สัตว์กัด', $kpi02->denominator_desc);
        $this->assertSame(40.0, (float) $kpi02->target_value);
        $this->assertSame('lower', $kpi02->target_direction);
    }

    public function test_kpi02_reads_hosxp_counts_visits_and_getting_an_antibiotic_is_the_concern(): void
    {
        $kpi02 = $this->kpi();

        $this->assertTrue($kpi02->hasHosxpQuery());
        $this->assertTrue($kpi02->countUnit()['additive']);
        $this->assertTrue($kpi02->numeratorIsDrugReceived());
        $this->assertFalse($kpi02->receivingDrugIsGood());
    }

    public function test_kpi02_stays_linked_to_hdc_for_comparison(): void
    {
        $this->assertSame('s_rdu8', app(HdcDataService::class)->table('KPI02'));
    }

    // ---- Counting method (built from the query's own constants) ----

    public function test_the_counting_method_lists_the_pharmacists_28_systemic_antibiotics(): void
    {
        $drugs = array_map('strval', array_keys($this->method()->drugs));

        $this->assertCount(28, $drugs);
        $this->assertContains('1670021', $drugs);     // Clindamycin cap — not on KPI01's list
        $this->assertContains('1670027', $drugs);     // Clindamycin inj
        $this->assertContains('1483877', $drugs);     // Dicloxacillin, the most used
        $this->assertNotContains('1000011', $drugs);  // Albendazole — on KPI01's list, not an antibiotic
        $this->assertNotContains('1520008', $drugs);  // Chlortetracycline eye ointment — topical
    }

    public function test_a_visit_needs_both_an_injury_code_and_an_external_cause(): void
    {
        $method = $this->method();
        [$injury, $cause] = $method->diagnoses;

        $this->assertContains('S05', $injury['codes']);      // eye injury — in KPI02's set, not KPI01's
        $this->assertContains('T14', $injury['codes']);
        $this->assertNotContains('T20', $injury['codes']);   // burns are excluded, so not listed as counted
        $this->assertNotContains('S16', $injury['codes']);   // muscle/tendon — excluded
        $this->assertSame('prefix', $injury['match']);
        $this->assertStringContainsString('X00', $cause['note']);
        $this->assertStringContainsString('X59', $cause['note']);
        $this->assertStringContainsString('ต้องมีครบทั้งสองกลุ่ม', implode(' ', $method->denominator));
        $this->assertContains(CountingMethod::OPD_ONLY, $method->conditions);
        $this->assertNotContains(CountingMethod::ALL_VISITS, $method->conditions);
    }

    public function test_animal_bites_burns_and_muscle_or_tendon_injuries_drop_the_whole_visit(): void
    {
        $excluded = $this->method()->excludedDiagnoses;

        foreach (['W54', 'W55', 'W56', 'T20', 'T29', 'T30', 'T32', 'S16', 'S46', 'S96', 'S390', 'T095', 'T115', 'T135', 'T146'] as $code) {
            $this->assertArrayHasKey($code, $excluded, "$code should drop the visit");
        }
        $this->assertArrayNotHasKey('W53', $excluded);   // rat bite — HDC's list stops at W54–W56
        $this->assertArrayNotHasKey('X20', $excluded);   // snake bite — likewise kept
        $this->assertArrayNotHasKey('T33', $excluded);
        $this->assertCount(28, $excluded);
    }

    // ---- The page ----

    public function test_kpi02_page_reads_hosxp_not_the_hdc_stand_in(): void
    {
        $this->bindRows(collect([$this->row('1', 'A', true), $this->row('2', 'A', false)]));

        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI02', 'year' => 2569]))
            ->assertOk()
            ->assertSee('VISIT ที่ได้ ATB', false)
            ->assertSee(json_encode('visit ที่ได้รับยาปฏิชีวนะ'), false)   // trend legend ($numeratorLabel, via @json)
            ->assertDontSee('แสดงผลจาก HDC (ข้อมูลส่วนกลาง) แทนชั่วคราว', false)
            ->assertDontSee('ยังรอนิยามจากเภสัชกร', false);
    }

    public function test_doctor_rate_is_red_above_the_ceiling_and_worst_first(): void
    {
        // DR "A": 10 visits, 2 given → 20% → green. DR "B": 10 visits, 7 given → 70% → red.
        $rows = collect();
        foreach (['A' => 2, 'B' => 7] as $doctor => $given) {
            for ($i = 0; $i < 10; $i++) {
                $rows->push($this->row("{$doctor}{$i}", $doctor, $i < $given));
            }
        }
        $this->bindRows($rows);

        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI02', 'year' => 2569]))
            ->assertOk()
            ->assertSee('อัตราการสั่งยาปฏิชีวนะรายแพทย์ผู้ตรวจ', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/text-emerald-600">20%</', $html);
        $this->assertMatchesRegularExpression('/text-red-600">70%</', $html);
        $this->assertLessThan(strpos($html, '>20%<'), strpos($html, '>70%<'));
    }
}
