<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\RduIndicator;
use App\Models\User;
use App\Queries\Kpi\ChronicDisease\MetforminDiabetesQuery;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * KPI10 — ร้อยละของผู้ป่วยเบาหวานที่ใช้ยา Metformin เป็นยาชนิดเดียวหรือร่วมกับยาอื่นโดยไม่มีข้อห้ามใช้
 * (CONTEXT.md: ผู้ป่วยเบาหวานชนิดที่ 2 ที่ใช้ Metformin โดยไม่มีข้อห้ามใช้). Counted in patients like KPI08,
 * and "higher is better": getting metformin is the good outcome.
 */
class Kpi10MetforminDiabetesTest extends TestCase
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

    /** One detail row per patient; `$metformin` = got metformin at any OPD visit in the period. */
    private function row(string $hn, string $doctor, bool $metformin): array
    {
        return [
            'HN' => $hn, 'VN' => "vn{$hn}", 'vstdate' => '2026-03-01', 'dx' => 'E119', 'fullname' => 'นาย ทดลอง xxxx',
            'drugitems_name' => $metformin ? 'metformin 500 mg. เม็ด' : null,
            'atb_cost' => $metformin ? 30.0 : 0.0, 'total_drug_items' => 4,
            'doctor' => "Doctor {$doctor}", 'doctor_code' => "D{$doctor}",
            'coverage' => 'UC', 'department' => 'อายุรกรรม', 'room' => 'คลินิกเบาหวาน',
            'egfr' => 72.5, 'egfr_date' => '2026-02-20',
        ];
    }

    private function bindRows(Collection $rows): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class($rows) extends MockRduDataSource {
            public function __construct(private Collection $rows) {}
            public function getDetails(string $kpiCode, int $year, int $month): Collection { return $kpiCode === 'KPI10' ? $this->rows : collect(); }
            public function getDetailsBetween(string $kpiCode, string $s, string $e): Collection { return $kpiCode === 'KPI10' ? $this->rows : collect(); }
        });
    }

    // ---- Seed / registration ----

    public function test_kpi10_is_seeded_as_type_2_dm_patients_without_contraindication_with_an_eighty_percent_floor(): void
    {
        $kpi10 = $this->kpi('KPI10');

        $this->assertStringContainsString('จำนวนผู้ป่วย', $kpi10->numerator_desc);
        $this->assertStringContainsString('Metformin', $kpi10->numerator_desc);
        $this->assertStringContainsString('E11', $kpi10->denominator_desc);
        $this->assertStringContainsString('ยาลดระดับน้ำตาล', $kpi10->denominator_desc);   // diet-controlled patients are not asked about
        $this->assertStringContainsString('eGFR', $kpi10->denominator_desc);
        $this->assertSame(80.0, (float) $kpi10->target_value);
        $this->assertSame('higher', $kpi10->target_direction);
    }

    public function test_kpi10_reads_hosxp_counts_patients_and_getting_metformin_is_good(): void
    {
        $kpi10 = $this->kpi('KPI10');

        $this->assertTrue($kpi10->hasHosxpQuery());
        $this->assertFalse($kpi10->countUnit()['additive']);
        $this->assertTrue($kpi10->numeratorIsDrugReceived());
        $this->assertTrue($kpi10->receivingDrugIsGood());
    }

    public function test_the_counting_method_lists_the_agreed_glucose_lowering_drugs_and_type_2_dm(): void
    {
        $method = app(MetforminDiabetesQuery::class)->countingMethod();

        $this->assertSame(
            ['1000184', '1019001', '1680038', '1510002', '1481213', '1590023', '1483896'],
            array_map('strval', array_keys($method->drugs))
        );
        $this->assertSame('ยาที่วัด (ตัวตั้ง)', $method->drugs['1000184']);
        $this->assertSame(['E11'], $method->diagnoses[0]['codes']);
        $this->assertSame('prefix', $method->diagnoses[0]['match']);
        $this->assertArrayHasKey('E10', $method->excludedDiagnoses);
        $this->assertStringContainsString('eGFR', implode(' ', $method->denominator));
    }

    // ---- The page ----

    public function test_kpi10_page_counts_patients_and_names_metformin(): void
    {
        $this->bindRows(collect([$this->row('1', 'A', true), $this->row('2', 'A', false)]));

        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI10', 'year' => 2569]))
            ->assertOk()
            ->assertSee('ผู้ป่วย ทั้งหมด', false)
            ->assertSee('ผู้ป่วย ที่ได้ Metformin', false)
            ->assertSee('มูลค่ายา Metformin (บาท)', false)
            ->assertSee(json_encode('ผู้ป่วยที่ได้รับ Metformin'), false)    // trend legend ($numeratorLabel, via @json)
            ->assertDontSee('VISIT ทั้งหมด', false);
    }

    public function test_doctor_rate_is_red_below_the_floor_and_worst_first(): void
    {
        // DR "A": 10 patients, 9 on metformin → 90% → green. DR "B": 10 patients, 5 on metformin → 50% → red.
        $rows = collect();
        foreach (['A' => 9, 'B' => 5] as $doctor => $onMetformin) {
            for ($i = 0; $i < 10; $i++) {
                $rows->push($this->row("{$doctor}{$i}", $doctor, $i < $onMetformin));
            }
        }
        $this->bindRows($rows);

        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI10', 'year' => 2569]))
            ->assertOk()
            ->assertSee('อัตราการสั่ง Metformin ในผู้ป่วยเบาหวานชนิดที่ 2 รายแพทย์ผู้ตรวจ', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/text-emerald-600">90%</', $html);
        $this->assertMatchesRegularExpression('/text-red-600">50%</', $html);
        $this->assertLessThan(strpos($html, '>90%<'), strpos($html, '>50%<'));
    }
}
