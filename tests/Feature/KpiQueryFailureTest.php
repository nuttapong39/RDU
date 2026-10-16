<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Models\RduIndicator;
use App\Models\User;
use App\Services\RduCalculationService;
use Database\Seeders\RduIndicatorSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PDOException;
use Tests\TestCase;

/**
 * One KPI whose HOSxP query fails (e.g. "permission denied for table lab_order" before a GRANT) shows as
 * "ไม่มีข้อมูล" — it must not take the whole overview down with a 500.
 */
class KpiQueryFailureTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = RduIndicatorSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();              // HDC-backed KPIs: no network in tests
        Storage::fake('local');    // the overview records the status snapshot

        $this->app->instance(RduDataSourceInterface::class, new class extends MockRduDataSource {
            public function getNumerator(string $kpiCode, int $year, int $month): int
            {
                $this->failFor($kpiCode);

                return parent::getNumerator($kpiCode, $year, $month);
            }

            public function getNumeratorBetween(string $kpiCode, string $startDate, string $endDate): int
            {
                $this->failFor($kpiCode);

                return parent::getNumeratorBetween($kpiCode, $startDate, $endDate);
            }

            private function failFor(string $kpiCode): void
            {
                if ($kpiCode === 'KPI10') {
                    throw new QueryException('rdu', 'select … from lab_order', [], new PDOException('SQLSTATE[42501]: permission denied for table lab_order'));
                }
            }
        });
    }

    public function test_a_failing_kpi_counts_as_no_data(): void
    {
        $item = app(RduCalculationService::class)->getOrCalculate(RduIndicator::where('code', 'KPI10')->firstOrFail(), 2569, 0);

        $this->assertSame('na', $item['status']);
        $this->assertNull($item['percentage']);
    }

    public function test_the_overview_still_shows_every_other_kpi(): void
    {
        $user = User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);

        $this->actingAs($user)->get(route('dashboard', ['year' => 2569]))
            ->assertOk()
            ->assertSee('KPI09', false)
            ->assertSee('KPI10', false);
    }

    public function test_another_kpi_page_with_a_quarter_still_loads(): void
    {
        $user = User::create(['name' => 'Staff', 'username' => 'staff', 'email' => 'staff@rdu.local', 'password' => bcrypt('x')]);

        // The sidebar computes every KPI's quarter figure, KPI10 included.
        $this->actingAs($user)->get(route('dashboard.kpi', ['code' => 'KPI09', 'year' => 2569, 'quarter' => 2]))->assertOk();
    }
}
