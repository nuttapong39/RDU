<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\RduIndicator;
use App\Models\User;
use App\Services\RduCalculationService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * KPI08 — ร้อยละของผู้ป่วยนอกโรคหืดที่ได้รับยา Inhaled Corticosteroid (CONTEXT.md: ผู้ป่วยโรคหืดที่ได้ ICS).
 * First KPI counted in patients rather than visits, and the first "higher is better" KPI whose
 * numerator is the cases that GOT the drug.
 */
class Kpi08AsthmaIcsTest extends TestCase
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

    /**
     * A patient seen in several months is one patient in the quarter: each month reports 10 / 20, but
     * the quarter's own date range reports 12 / 25 — summing months (30 / 60) would count people twice.
     */
    private function bindPatientCounts(): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class extends MockRduDataSource {
            public function getNumerator(string $kpiCode, int $year, int $month): int { return 10; }
            public function getDenominator(string $kpiCode, int $year, int $month): int { return 20; }
            public function getNumeratorBetween(string $kpiCode, string $s, string $e): int { return 12; }
            public function getDenominatorBetween(string $kpiCode, string $s, string $e): int { return 25; }
        });
    }

    /** One detail row per patient; `$ics` = got an inhaled corticosteroid in the period. */
    private function row(string $hn, string $doctor, bool $ics): array
    {
        return [
            'HN' => $hn, 'VN' => "vn{$hn}", 'vstdate' => '2026-03-01', 'dx' => 'J450', 'fullname' => 'นาย ทดลอง xxxx',
            'drugitems_name' => $ics ? 'BUDESONIDE MDI 200puff/กล่อง 200 mcg./dose ขวด' : null,
            'atb_cost' => $ics ? 250.0 : 0.0, 'total_drug_items' => 3,
            'doctor' => "Doctor {$doctor}", 'doctor_code' => "D{$doctor}",
            'coverage' => 'UC', 'department' => 'อายุรกรรม', 'room' => 'OPD',
        ];
    }

    private function bindRows(Collection $rows): void
    {
        $this->app->instance(RduDataSourceInterface::class, new class($rows) extends MockRduDataSource {
            public function __construct(private Collection $rows) {}
            public function getDetails(string $kpiCode, int $year, int $month): Collection { return $kpiCode === 'KPI08' ? $this->rows : collect(); }
            public function getDetailsBetween(string $kpiCode, string $s, string $e): Collection { return $kpiCode === 'KPI08' ? $this->rows : collect(); }
        });
    }

    // ---- Seed / registration ----

    public function test_kpi08_is_seeded_as_patients_with_an_eighty_percent_floor(): void
    {
        $kpi08 = $this->kpi('KPI08');

        $this->assertStringContainsString('จำนวนผู้ป่วย', $kpi08->numerator_desc);
        $this->assertStringContainsString('J45', $kpi08->denominator_desc);
        $this->assertSame(80.0, (float) $kpi08->target_value);
        $this->assertSame('higher', $kpi08->target_direction);
    }

    public function test_kpi08_reads_hosxp_counts_patients_and_its_numerator_is_getting_the_drug(): void
    {
        $kpi08 = $this->kpi('KPI08');

        $this->assertTrue($kpi08->hasHosxpQuery());
        $this->assertFalse($kpi08->countUnit()['additive']);
        $this->assertTrue($kpi08->numeratorIsDrugReceived());
        $this->assertTrue($kpi08->receivingDrugIsGood());

        $this->assertFalse($this->kpi('KPI03')->numeratorIsDrugReceived());   // deliveries WITHOUT an antibiotic
        $this->assertFalse($this->kpi('KPI03')->receivingDrugIsGood());
        $this->assertFalse($this->kpi('KPI04')->receivingDrugIsGood());
        $this->assertTrue($this->kpi('KPI04')->countUnit()['additive']);
    }

    // ---- A quarter is counted over its own date range ----

    public function test_a_quarter_is_counted_over_its_date_range_not_summed_from_months(): void
    {
        $this->bindPatientCounts();

        $item = app(RduCalculationService::class)->getOrCalculateQuarter($this->kpi('KPI08'), 2569, 2);

        $this->assertSame(12, $item['numerator']);
        $this->assertSame(25, $item['denominator']);
        $this->assertSame(48.0, $item['percentage']);
    }

    public function test_the_year_to_date_figure_of_a_patient_kpi_is_counted_over_its_date_range(): void
    {
        $this->bindPatientCounts();

        $trend = app(RduCalculationService::class)->getTrend($this->kpi('KPI08'), 2569);

        $this->assertSame(10, $trend[2]['num']);        // the month itself
        $this->assertSame(12, $trend[2]['cum_num']);    // Oct–Dec as one range, not 10 + 10 + 10
        $this->assertSame(25, $trend[2]['cum_den']);
        $this->assertSame(48.0, $trend[2]['cum_pct']);
    }

    public function test_the_year_to_date_figure_of_a_visit_kpi_is_the_running_sum_of_its_months(): void
    {
        $this->bindPatientCounts();

        $trend = app(RduCalculationService::class)->getTrend($this->kpi('KPI04'), 2569);

        $this->assertSame(30, $trend[2]['cum_num']);    // visits never repeat across months: 10 + 10 + 10
        $this->assertSame(60, $trend[2]['cum_den']);
    }

    // ---- The page ----

    public function test_kpi08_page_counts_patients_and_names_ics(): void
    {
        $this->bindRows(collect([$this->row('1', 'A', true), $this->row('2', 'A', false)]));

        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI08', 'year' => 2569]))
            ->assertOk()
            ->assertSee('ผู้ป่วย ทั้งหมด', false)
            ->assertSee('ผู้ป่วย ที่ได้ ICS', false)
            ->assertSee('มูลค่ายา ICS (บาท)', false)
            ->assertDontSee('VISIT ทั้งหมด', false)
            ->assertDontSee('มูลค่า ATB', false);
    }

    public function test_a_visit_kpi_page_still_counts_visits(): void
    {
        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI04', 'year' => 2569]))
            ->assertOk()
            ->assertSee('VISIT ทั้งหมด', false)
            ->assertDontSee('ผู้ป่วย ทั้งหมด', false);
    }

    public function test_doctor_rate_is_red_below_the_floor_when_getting_the_drug_is_good(): void
    {
        // DR "A": 10 patients, 9 on ICS → 90% → green. DR "B": 10 patients, 5 on ICS → 50% → red.
        // DR "C": exactly at the 80% floor → green, as the KPI itself passes at pct >= target.
        $rows = collect();
        foreach (['A' => 9, 'B' => 5, 'C' => 8] as $doctor => $onIcs) {
            for ($i = 0; $i < 10; $i++) {
                $rows->push($this->row("{$doctor}{$i}", $doctor, $i < $onIcs));
            }
        }
        $this->bindRows($rows);

        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI08', 'year' => 2569]))
            ->assertOk()
            ->assertSee('อัตราการสั่ง ICS รายแพทย์ผู้ตรวจ', false)
            ->assertSee('ต่ำกว่าเกณฑ์ 80%', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/text-emerald-600">90%</', $html);
        $this->assertMatchesRegularExpression('/text-red-600">50%</', $html);
        $this->assertMatchesRegularExpression('/text-emerald-600">80%</', $html);
        // Worst first: for a drug that should be given, the lowest rate is the worst.
        $this->assertLessThan(strpos($html, '>90%<'), strpos($html, '>50%<'));
    }
}
