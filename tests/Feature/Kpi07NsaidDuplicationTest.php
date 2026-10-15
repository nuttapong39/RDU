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
 * KPI07 — ร้อยละของผู้ป่วยนอกที่มีการใช้ยากลุ่ม NSAIDs ซ้ำซ้อน (CONTEXT.md: NSAIDs ซ้ำซ้อน, กลุ่มยาของตัวชี้วัด).
 */
class Kpi07NsaidDuplicationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    /** Built without the factory: its faker userName() needs the PHP intl extension, missing on some hosts. */
    private function user(): User
    {
        return User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);
    }

    public function test_kpi07_is_seeded_as_duplicate_nsaids_with_a_five_percent_ceiling(): void
    {
        $kpi07 = RduIndicator::where('code', 'KPI07')->firstOrFail();

        $this->assertStringContainsString('NSAIDs ซ้ำซ้อน', $kpi07->name);
        $this->assertStringContainsString('NSAIDs ตั้งแต่ 2 รายการ', $kpi07->numerator_desc);
        $this->assertStringContainsString('NSAIDs อย่างน้อย 1 รายการ', $kpi07->denominator_desc);
        $this->assertSame(5.0, (float) $kpi07->target_value);
        $this->assertSame('lower', $kpi07->target_direction);
    }

    public function test_kpi07_reads_hosxp(): void
    {
        $this->assertTrue(RduIndicator::where('code', 'KPI07')->firstOrFail()->hasHosxpQuery());
    }

    public function test_kpi07_page_names_nsaids_instead_of_antibiotics(): void
    {
        $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI07', 'year' => 2569]))
            ->assertOk()
            ->assertSee('VISIT ที่ได้ NSAIDs ≥2 รายการ', false)
            ->assertSee('มูลค่ายา NSAIDs (บาท)', false)
            ->assertDontSee('VISIT ที่ได้ ATB', false)
            ->assertDontSee('มูลค่า ATB', false);
    }

    public function test_doctor_rate_table_grades_against_the_five_percent_ceiling(): void
    {
        // DR "A": 50 visits, 1 duplicated → 2% → green. DR "B": 10 visits, 1 duplicated → 10% → red.
        $rows = collect();
        foreach (['A' => 50, 'B' => 10] as $doctor => $visits) {
            for ($i = 0; $i < $visits; $i++) {
                $rows->push([
                    'HN' => "hn{$doctor}{$i}", 'VN' => "vn{$doctor}{$i}", 'vstdate' => '2026-03-01', 'dx' => 'M545',
                    'fullname' => 'นาย ทดลอง xxxx',
                    'drugitems_name' => $i === 0 ? 'Diclofenac  INJ 75 mg/3ml;IBUPROFEN 400 mg.' : null,
                    'atb_cost' => $i === 0 ? 20.0 : 0.0, 'total_drug_items' => 3,
                    'doctor' => "Doctor {$doctor}", 'doctor_code' => "D{$doctor}",
                    'coverage' => 'UC', 'department' => 'อายุรกรรม', 'room' => 'OPD',
                ]);
            }
        }
        $this->app->instance(RduDataSourceInterface::class, new class($rows) extends MockRduDataSource {
            public function __construct(private Collection $rows) {}
            public function getDetails(string $kpiCode, int $year, int $month): Collection { return $kpiCode === 'KPI07' ? $this->rows : collect(); }
            public function getDetailsBetween(string $kpiCode, string $s, string $e): Collection { return $kpiCode === 'KPI07' ? $this->rows : collect(); }
        });

        $html = $this->actingAs($this->user())->get(route('dashboard.kpi', ['code' => 'KPI07', 'year' => 2569]))
            ->assertOk()
            ->assertSee('อัตราการสั่ง NSAIDs ซ้ำซ้อนรายแพทย์ผู้ตรวจ', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/text-emerald-600">2%</', $html);
        $this->assertMatchesRegularExpression('/text-red-600">10%</', $html);
    }
}
