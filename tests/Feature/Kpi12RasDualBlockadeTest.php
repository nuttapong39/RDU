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
 * KPI12 — ร้อยละจำนวนครั้งบริการโรคความดันโลหิตสูงทั่วไปที่ได้รับ RAS Blockade 2 ชนิดร่วมกัน.
 * First KPI that is not about antibiotics, and the first with a 0% target (see CONTEXT.md:
 * สถานะ, กลุ่มยาของตัวชี้วัด, RAS blockade 2 ชนิดร่วมกัน).
 */
class Kpi12RasDualBlockadeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    /** Built without the factory: its faker userName() needs the PHP intl extension, missing on some hosts. */
    private function user(): User
    {
        return User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);
    }

    // ---- 0% target: no "near target" tier ----

    public function test_a_zero_target_has_no_near_target_tier(): void
    {
        $svc = app(RduCalculationService::class);

        $this->assertSame('pass', $svc->getStatus(0.0, 0, 'lower'));
        $this->assertSame('fail', $svc->getStatus(0.02, 0, 'lower')); // a single case is already a failure
        $this->assertSame('fail', $svc->getStatus(4.9, 0, 'lower'));
    }

    public function test_other_targets_keep_their_near_target_tier(): void
    {
        $svc = app(RduCalculationService::class);

        $this->assertSame('warning', $svc->getStatus(42.0, 40, 'lower'));
        $this->assertSame('warning', $svc->getStatus(78.0, 80, 'higher'));
        $this->assertSame('fail', $svc->getStatus(46.0, 40, 'lower'));
    }

    public function test_a_single_case_in_a_large_denominator_never_rounds_to_zero_and_passes(): void
    {
        // 1 / 9,200 = 0.011% — rounding to one decimal first would read 0.0% and grade it 'pass'.
        $this->app->instance(RduDataSourceInterface::class, new class extends MockRduDataSource {
            public function getNumerator(string $kpiCode, int $year, int $month): int { return 1; }
            public function getDenominator(string $kpiCode, int $year, int $month): int { return 9200; }
            public function getNumeratorBetween(string $kpiCode, string $s, string $e): int { return 1; }
            public function getDenominatorBetween(string $kpiCode, string $s, string $e): int { return 9200; }
        });
        $kpi12 = RduIndicator::where('code', 'KPI12')->firstOrFail();
        $svc   = app(RduCalculationService::class);

        foreach ([$svc->getOrCalculate($kpi12, 2569, 0), $svc->getOrCalculateQuarter($kpi12, 2569, 2), $svc->getOrCalculate($kpi12, 2569, 3)] as $item) {
            $this->assertGreaterThan(0, $item['percentage']);
            $this->assertSame('fail', $item['status']);
        }
    }

    public function test_percentages_keep_one_decimal_and_only_tiny_non_zero_values_get_more(): void
    {
        $this->assertNull(RduCalculationService::percentage(0, 0));
        $this->assertSame(0.0, RduCalculationService::percentage(0, 9200));
        $this->assertSame(57.5, RduCalculationService::percentage(1032, 1794));  // unchanged for normal values
        $this->assertSame(0.01, RduCalculationService::percentage(1, 9200));
        $this->assertSame(0.01, RduCalculationService::percentage(1, 900000));  // never 0 while a case exists
    }

    // ---- Seed ----

    public function test_kpi12_is_seeded_as_dual_ras_blockade_with_a_zero_target(): void
    {
        $kpi12 = RduIndicator::where('code', 'KPI12')->firstOrFail();

        $this->assertStringContainsString('RAS Blockade 2 ชนิดร่วมกัน', $kpi12->name);
        $this->assertStringContainsString('RAS blockade', $kpi12->numerator_desc);
        $this->assertSame(0.0, (float) $kpi12->target_value);
        $this->assertSame('lower', $kpi12->target_direction);
    }

    // ---- The page names the KPI's drug group ----

    public function test_kpi12_page_names_ras_blockade_instead_of_antibiotics(): void
    {
        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI12', 'year' => 2569]))
            ->assertOk()
            ->assertSee('VISIT ที่ได้ RAS ≥2 ชนิด', false)
            ->assertSee('มูลค่ายา RAS (บาท)', false)
            ->assertDontSee('VISIT ที่ได้ ATB', false)
            ->assertDontSee('มูลค่า ATB', false);
    }

    public function test_antibiotic_kpis_keep_their_antibiotic_wording(): void
    {
        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI04', 'year' => 2569]))
            ->assertOk()
            ->assertSee('VISIT ที่ได้ ATB', false)
            ->assertSee('มูลค่า ATB (บาท)', false)
            ->assertDontSee('RAS ≥2', false);
    }

    // ---- Doctor-rate table at a 0% target ----

    public function test_doctor_rate_table_grades_zero_cases_green_and_any_case_red_at_a_zero_target(): void
    {
        // DR "A": 5 visits, none with dual blockade → 0% → must be green (0% IS the target).
        // DR "B": 5 visits, one with dual blockade → 20% → red.
        $rows = collect();
        foreach (['A' => 0, 'B' => 1] as $doctor => $dual) {
            for ($i = 0; $i < 5; $i++) {
                $rows->push([
                    'HN' => "hn{$doctor}{$i}", 'VN' => "vn{$doctor}{$i}", 'vstdate' => '2026-03-01', 'dx' => 'I10',
                    'fullname' => 'นาย ทดลอง xxxx',
                    'drugitems_name' => $i < $dual ? 'Enalapril 5 mg.;LOSARTAN 50 mg.' : null,
                    'atb_cost' => $i < $dual ? 12.5 : 0.0, 'total_drug_items' => 3,
                    'doctor' => "Doctor {$doctor}", 'doctor_code' => "D{$doctor}",
                    'coverage' => 'UC', 'department' => 'อายุรกรรม', 'room' => 'OPD',
                ]);
            }
        }
        $this->app->instance(RduDataSourceInterface::class, new class($rows) extends MockRduDataSource {
            public function __construct(private Collection $rows) {}

            public function getDetails(string $kpiCode, int $year, int $month): Collection
            {
                return $kpiCode === 'KPI12' ? $this->rows : collect();
            }

            public function getDetailsBetween(string $kpiCode, string $startDate, string $endDate): Collection
            {
                return $kpiCode === 'KPI12' ? $this->rows : collect();
            }
        });

        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI12', 'year' => 2569]))
            ->assertOk()
            ->assertSee('อัตราการสั่ง RAS blockade ≥ 2 ชนิดรายแพทย์ผู้ตรวจ', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/text-emerald-600">0%</', $html, '0% doctor must be green at a 0% target');
        $this->assertMatchesRegularExpression('/text-red-600">20%</', $html, 'any dual-blockade case must be red');
        $this->assertStringNotContainsString('ต่ำกว่าเกณฑ์', $html, 'at a 0% target green means "no case", not "below"');
    }

    public function test_a_doctor_with_one_case_among_thousands_of_visits_is_red(): void
    {
        $rows = collect();
        for ($i = 0; $i < 2500; $i++) {   // 1 / 2,500 = 0.04% — one decimal would round it to a green 0.0%
            $rows->push([
                'HN' => "hn$i", 'VN' => "vn$i", 'vstdate' => '2026-03-01', 'dx' => 'I10', 'fullname' => 'นาย ทดลอง xxxx',
                'drugitems_name' => $i === 0 ? 'Enalapril 5 mg.;CAPTOPRIL 25 mg.' : null,
                'atb_cost' => 0.0, 'total_drug_items' => 2, 'doctor' => 'Doctor C', 'doctor_code' => 'DC',
                'coverage' => 'UC', 'department' => 'อายุรกรรม', 'room' => 'OPD',
            ]);
        }
        $this->app->instance(RduDataSourceInterface::class, new class($rows) extends MockRduDataSource {
            public function __construct(private Collection $rows) {}
            public function getDetails(string $kpiCode, int $year, int $month): Collection { return $kpiCode === 'KPI12' ? $this->rows : collect(); }
            public function getDetailsBetween(string $kpiCode, string $s, string $e): Collection { return $kpiCode === 'KPI12' ? $this->rows : collect(); }
        });

        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI12', 'year' => 2569]))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/text-red-600">0\.04%</', $html);
    }
}
