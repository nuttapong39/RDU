<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\RduIndicator;
use App\Models\User;
use App\Queries\Kpi\CountingMethod;
use App\Queries\Kpi\PatientSafety\ElderlyLongActingBenzodiazepineQuery;
use App\Services\HdcDataService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * KPI13 — ร้อยละผู้ป่วยนอกสูงอายุที่ใช้ยากลุ่ม Long-acting Benzodiazepine
 * (CONTEXT.md: ผู้สูงอายุที่ได้ยากลุ่ม Long-acting Benzodiazepine). Defined to match HDC s_rdu16: aged 65+,
 * every visit as the denominator, Diazepam only.
 */
class Kpi13ElderlyLongActingBenzodiazepineTest extends TestCase
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
        return RduIndicator::where('code', 'KPI13')->firstOrFail();
    }

    /** One detail row per visit; `$given` = got a long-acting benzodiazepine in that visit. */
    private function row(string $vn, string $doctor, bool $given): array
    {
        return [
            'HN' => "hn{$vn}", 'VN' => $vn, 'vstdate' => '2026-01-10', 'dx' => 'I10,E119', 'fullname' => 'นาย ทดลอง xxxx',
            'drugitems_name' => $given ? '(5 mg)Diazepam (สีเหลือง) 5 mg. เม็ด' : null,
            'atb_cost' => $given ? 3.0 : 0.0, 'total_drug_items' => 5,
            'doctor' => "Doctor {$doctor}", 'doctor_code' => "D{$doctor}",
            'coverage' => 'UC', 'department' => 'อายุรกรรม', 'room' => 'OPD',
        ];
    }

    private function bindRows(Collection $rows): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class($rows) extends MockRduDataSource {
            public function __construct(private Collection $rows) {}
            public function getDetails(string $kpiCode, int $year, int $month): Collection { return $kpiCode === 'KPI13' ? $this->rows : collect(); }
            public function getDetailsBetween(string $kpiCode, string $s, string $e): Collection { return $kpiCode === 'KPI13' ? $this->rows : collect(); }
        });
    }

    // ---- Seed / registration ----

    public function test_kpi13_is_seeded_as_visits_of_patients_aged_65_with_a_five_percent_ceiling(): void
    {
        $kpi13 = $this->kpi();

        $this->assertStringContainsString('Diazepam', $kpi13->numerator_desc);
        $this->assertStringContainsString('65 ปี', $kpi13->denominator_desc);
        $this->assertSame(5.0, (float) $kpi13->target_value);
        $this->assertSame('lower', $kpi13->target_direction);
    }

    public function test_kpi13_reads_hosxp_counts_visits_and_getting_the_drug_is_the_concern(): void
    {
        $kpi13 = $this->kpi();

        $this->assertTrue($kpi13->hasHosxpQuery());
        $this->assertTrue($kpi13->countUnit()['additive']);
        $this->assertTrue($kpi13->numeratorIsDrugReceived());
        $this->assertFalse($kpi13->receivingDrugIsGood());
    }

    public function test_kpi13_stays_linked_to_hdc_for_comparison(): void
    {
        $this->assertSame('s_rdu16', app(HdcDataService::class)->table('KPI13'));
    }

    public function test_the_counting_method_lists_the_three_diazepam_codes_and_no_clonazepam(): void
    {
        $method = app(ElderlyLongActingBenzodiazepineQuery::class)->countingMethod();

        $this->assertSame(['1000107', '1460009', '1000108'], array_map('strval', array_keys($method->drugs)));
        $this->assertArrayNotHasKey('1483866', $method->drugs);       // Clonazepam — removed by the pharmacist
        $this->assertSame([], $method->diagnoses);                    // every visit, whatever the diagnosis
        $this->assertStringContainsString('65 ปี', implode(' ', $method->denominator));
        $this->assertContains(CountingMethod::ALL_VISITS, $method->conditions);
        $this->assertNotContains(CountingMethod::OPD_ONLY, $method->conditions);
    }

    // ---- The page ----

    public function test_kpi13_page_names_long_acting_benzodiazepines_instead_of_antibiotics(): void
    {
        $this->bindRows(collect([$this->row('1', 'A', true), $this->row('2', 'A', false)]));

        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI13', 'year' => 2569]))
            ->assertOk()
            ->assertSee('VISIT ทั้งหมด', false)
            ->assertSee('VISIT ที่ได้ Long-acting BZD', false)
            ->assertSee(json_encode('visit ที่ได้รับยากลุ่ม Long-acting Benzodiazepine'), false)   // trend legend ($numeratorLabel, via @json)
            ->assertDontSee('แสดงผลจาก HDC (ข้อมูลส่วนกลาง) แทนชั่วคราว', false)
            ->assertDontSee('VISIT ที่ได้ ATB', false);
    }

    public function test_doctor_rate_is_red_above_the_ceiling_and_worst_first(): void
    {
        // DR "A": 50 visits, 1 given → 2% → green. DR "B": 10 visits, 3 given → 30% → red.
        $rows = collect();
        foreach (['A' => [50, 1], 'B' => [10, 3]] as $doctor => [$visits, $given]) {
            for ($i = 0; $i < $visits; $i++) {
                $rows->push($this->row("{$doctor}{$i}", $doctor, $i < $given));
            }
        }
        $this->bindRows($rows);

        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI13', 'year' => 2569]))
            ->assertOk()
            ->assertSee('อัตราการสั่งยากลุ่ม Long-acting Benzodiazepine ในผู้สูงอายุรายแพทย์ผู้ตรวจ', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/text-emerald-600">2%</', $html);
        $this->assertMatchesRegularExpression('/text-red-600">30%</', $html);
        $this->assertLessThan(strpos($html, '>2%<'), strpos($html, '>30%<'));
    }
}
