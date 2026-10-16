<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\RduIndicator;
use App\Models\User;
use App\Queries\Kpi\PatientSafety\ChildRespiratoryNonSedatingQuery;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * KPI11 — ร้อยละผู้รับบริการผู้ป่วยเด็กที่ได้รับการวินิจฉัยเป็นโรคติดเชื้อของทางเดินหายใจและได้รับยาน้ำชนิด non-sedating
 * (CONTEXT.md: เด็กติดเชื้อทางเดินหายใจที่ได้ยาแก้แพ้ชนิด non-sedating). Counted in visits like KPI04; target
 * ≤ 20% as HDC grades it.
 */
class Kpi11ChildRespiratoryNonSedatingTest extends TestCase
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
        return RduIndicator::where('code', 'KPI11')->firstOrFail();
    }

    /** One detail row per visit; `$given` = got a non-sedating antihistamine in that visit. */
    private function row(string $vn, string $doctor, bool $given): array
    {
        return [
            'HN' => "hn{$vn}", 'VN' => $vn, 'vstdate' => '2026-01-10', 'dx' => 'J00', 'fullname' => 'ด.ช. ทดลอง xxxx',
            'drugitems_name' => $given ? 'CETIRIZINE  SYR. ยาน้ำ 5 mg/5ml ขวด' : null,
            'atb_cost' => $given ? 25.0 : 0.0, 'total_drug_items' => 3,
            'doctor' => "Doctor {$doctor}", 'doctor_code' => "D{$doctor}",
            'coverage' => 'UC', 'department' => 'กุมารเวชกรรม', 'room' => 'OPD',
        ];
    }

    private function bindRows(Collection $rows): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class($rows) extends MockRduDataSource {
            public function __construct(private Collection $rows) {}
            public function getDetails(string $kpiCode, int $year, int $month): Collection { return $kpiCode === 'KPI11' ? $this->rows : collect(); }
            public function getDetailsBetween(string $kpiCode, string $s, string $e): Collection { return $kpiCode === 'KPI11' ? $this->rows : collect(); }
        });
    }

    // ---- Seed / registration ----

    public function test_kpi11_is_seeded_as_child_respiratory_visits_with_hdcs_twenty_percent_ceiling(): void
    {
        $kpi11 = $this->kpi();

        $this->assertStringContainsString('non-sedating', $kpi11->numerator_desc);
        $this->assertStringContainsString('15 ปี', $kpi11->denominator_desc);
        $this->assertStringContainsString('J00', $kpi11->denominator_desc);
        $this->assertStringNotContainsString('Corticosteroid', $kpi11->numerator_desc);
        $this->assertSame(20.0, (float) $kpi11->target_value);
        $this->assertSame('lower', $kpi11->target_direction);
    }

    public function test_kpi11_reads_hosxp_counts_visits_and_getting_the_drug_is_the_concern(): void
    {
        $kpi11 = $this->kpi();

        $this->assertTrue($kpi11->hasHosxpQuery());
        $this->assertTrue($kpi11->countUnit()['additive']);
        $this->assertTrue($kpi11->numeratorIsDrugReceived());
        $this->assertFalse($kpi11->receivingDrugIsGood());
    }

    public function test_kpi11_is_not_compared_with_hdc_whose_numerator_for_this_hospital_is_zero(): void
    {
        $this->assertFalse(app(\App\Services\HdcDataService::class)->isLinked('KPI11'));
    }

    public function test_the_counting_method_lists_both_cetirizine_codes_and_the_respiratory_prefixes(): void
    {
        $method = app(ChildRespiratoryNonSedatingQuery::class)->countingMethod();

        $this->assertSame(['1510009', '1560013'], array_map('strval', array_keys($method->drugs)));
        $this->assertSame('ยาเม็ด', $method->drugs['1560013']);                       // tablets count despite "ยาน้ำ" in the name
        $this->assertStringContainsString('ยาเม็ด', implode(' ', $method->numerator));
        $this->assertSame(['J0', 'J20', 'J21', 'J22'], $method->diagnoses[0]['codes']);
        $this->assertSame('prefix', $method->diagnoses[0]['match']);
        $this->assertStringContainsString('15 ปี', implode(' ', $method->denominator));
        $this->assertContains(\App\Queries\Kpi\CountingMethod::OPD_ONLY, $method->conditions);
    }

    // ---- The page ----

    public function test_kpi11_page_names_non_sedating_antihistamine_instead_of_antibiotics(): void
    {
        $this->bindRows(collect([$this->row('1', 'A', true), $this->row('2', 'A', false)]));

        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI11', 'year' => 2569]))
            ->assertOk()
            ->assertSee('VISIT ทั้งหมด', false)
            ->assertSee('VISIT ที่ได้ non-sedating', false)
            ->assertSee(json_encode('visit ที่ได้รับยาแก้แพ้ชนิด non-sedating'), false)   // trend legend ($numeratorLabel, via @json)
            ->assertDontSee('VISIT ที่ได้ ATB', false)
            ->assertDontSee('มูลค่า ATB', false);
    }

    public function test_doctor_rate_is_red_above_the_ceiling_and_worst_first(): void
    {
        // DR "A": 10 visits, 1 given → 10% → green. DR "B": 10 visits, 5 given → 50% → red.
        $rows = collect();
        foreach (['A' => 1, 'B' => 5] as $doctor => $given) {
            for ($i = 0; $i < 10; $i++) {
                $rows->push($this->row("{$doctor}{$i}", $doctor, $i < $given));
            }
        }
        $this->bindRows($rows);

        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI11', 'year' => 2569]))
            ->assertOk()
            ->assertSee('อัตราการสั่งยาแก้แพ้ชนิด non-sedating ในเด็กติดเชื้อทางเดินหายใจรายแพทย์ผู้ตรวจ', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/text-emerald-600">10%</', $html);
        $this->assertMatchesRegularExpression('/text-red-600">50%</', $html);
        $this->assertLessThan(strpos($html, '>10%<'), strpos($html, '>50%<'));
    }
}
