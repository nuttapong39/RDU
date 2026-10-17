<?php

namespace Tests;

use App\DataSources\HosxpRduDataSource;
use App\Queries\Kpi\KpiQueryInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Collection;

abstract class TestCase extends BaseTestCase
{
    /** The KPIs the seeder switches on — every code KPI01–KPI13 except KPI06, removed 2026-10-17. */
    protected const ACTIVE_KPI_CODES = [
        'KPI01', 'KPI02', 'KPI03', 'KPI04', 'KPI05', 'KPI07',
        'KPI08', 'KPI09', 'KPI10', 'KPI11', 'KPI12', 'KPI13',
    ];

    /**
     * Every KPI now has a HOSxP query, so to test how a KPI still awaiting its definition behaves (HDC stand-in
     * figure, "ยังรอนิยามจากเภสัชกร"), this puts `$code` back to that state: off rdu.hosxp_kpis, and a query
     * that counts nothing and describes no counting method.
     */
    protected function withKpiAwaitingQuery(string $code): void
    {
        config(['rdu.hosxp_kpis' => array_values(array_diff(config('rdu.hosxp_kpis', []), [$code]))]);

        $queries = (fn () => $this->queries)->call($this->app->make(HosxpRduDataSource::class));
        $queries[$code] = new class implements KpiQueryInterface {
            public function getNumerator(int $year, int $month): int { return 0; }
            public function getDenominator(int $year, int $month): int { return 0; }
            public function getDetails(int $year, int $month): Collection { return collect(); }
            public function getNumeratorBetween(string $startDate, string $endDate): int { return 0; }
            public function getDenominatorBetween(string $startDate, string $endDate): int { return 0; }
            public function getDetailsBetween(string $startDate, string $endDate): Collection { return collect(); }
        };
        $this->app->instance(HosxpRduDataSource::class, new HosxpRduDataSource($queries));
    }

    /**
     * Every test runs against a throw-away storage directory. Tests such as DatabaseConfigTest create
     * and delete storage/app/private/config/rdu_database.json — pointed at the real storage path they
     * deleted the live HOSxP connection config (and with it, HOSxP login) on every test run.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $storage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rdu-test-storage';
        foreach (['app/private/config', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
            if (! is_dir($storage.'/'.$dir)) {
                mkdir($storage.'/'.$dir, 0777, true);
            }
        }
        $app->useStoragePath($storage);

        return $app;
    }
}
