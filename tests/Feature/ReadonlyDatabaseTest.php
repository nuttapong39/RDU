<?php

namespace Tests\Feature;

use App\DataSources\MockRduDataSource;
use App\DataSources\RduDataSourceInterface;
use App\Exceptions\ReadonlyDatabaseException;
use App\Models\Hosxp\HosxpPatient;
use App\Services\DatabaseConfigService;
use App\Services\RduQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadonlyDatabaseTest extends TestCase
{
    // RduQueryService keys its cache by the drug code sets' versions, which live in the app database.
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Layer 1: ReadonlyHosxpModel — Eloquent write blocking
    // -------------------------------------------------------------------------

    public function test_readonly_model_blocks_save(): void
    {
        $patient = new HosxpPatient();
        $this->expectException(ReadonlyDatabaseException::class);
        $patient->save();
    }

    public function test_readonly_model_blocks_create(): void
    {
        $this->expectException(ReadonlyDatabaseException::class);
        HosxpPatient::create(['hn' => '000001']);
    }

    public function test_readonly_model_blocks_delete(): void
    {
        $patient = new HosxpPatient();
        $this->expectException(ReadonlyDatabaseException::class);
        $patient->delete();
    }

    public function test_readonly_model_blocks_update(): void
    {
        $patient = new HosxpPatient();
        $this->expectException(ReadonlyDatabaseException::class);
        $patient->update(['name' => 'test']);
    }

    public function test_readonly_model_blocks_force_create(): void
    {
        $this->expectException(ReadonlyDatabaseException::class);
        HosxpPatient::forceCreate(['hn' => '000001']);
    }

    // -------------------------------------------------------------------------
    // Layer 2: ReadonlyConnection — Raw SQL write blocking
    // -------------------------------------------------------------------------

    public function test_readonly_connection_blocks_insert(): void
    {
        $this->expectException(ReadonlyDatabaseException::class);
        DB::connection('rdu')->statement("INSERT INTO patient (hn) VALUES ('test')");
    }

    public function test_readonly_connection_blocks_update(): void
    {
        $this->expectException(ReadonlyDatabaseException::class);
        DB::connection('rdu')->statement("UPDATE patient SET name = 'x' WHERE hn = '1'");
    }

    public function test_readonly_connection_blocks_delete(): void
    {
        $this->expectException(ReadonlyDatabaseException::class);
        DB::connection('rdu')->statement("DELETE FROM patient WHERE hn = '1'");
    }

    public function test_readonly_connection_blocks_create_table(): void
    {
        $this->expectException(ReadonlyDatabaseException::class);
        DB::connection('rdu')->statement('CREATE TABLE _rdu_hack (id INT)');
    }

    public function test_readonly_connection_blocks_drop(): void
    {
        $this->expectException(ReadonlyDatabaseException::class);
        DB::connection('rdu')->statement('DROP TABLE patient');
    }

    public function test_readonly_connection_blocks_truncate(): void
    {
        $this->expectException(ReadonlyDatabaseException::class);
        DB::connection('rdu')->statement('TRUNCATE TABLE patient');
    }

    // -------------------------------------------------------------------------
    // MockRduDataSource — Dev/test data source
    // -------------------------------------------------------------------------

    public function test_mock_data_source_returns_numerator(): void
    {
        $mock = new MockRduDataSource();
        $result = $mock->getNumerator('KPI01', 2568, 1);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function test_mock_data_source_returns_denominator_gte_numerator(): void
    {
        $mock = new MockRduDataSource();
        $num = $mock->getNumerator('KPI01', 2568, 1);
        $den = $mock->getDenominator('KPI01', 2568, 1);
        $this->assertGreaterThanOrEqual($num, $den);
    }

    public function test_mock_data_source_returns_collection_for_details(): void
    {
        $mock = new MockRduDataSource();
        $details = $mock->getDetails('KPI01', 2568, 1);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $details);
    }

    // -------------------------------------------------------------------------
    // RduQueryService — Caching + percentage calculation
    // -------------------------------------------------------------------------

    public function test_rdu_query_service_calculates_percentage(): void
    {
        $service = new RduQueryService(new MockRduDataSource());
        $pct = $service->getPercentage('KPI01', 2568, 1);
        $this->assertNotNull($pct);
        $this->assertGreaterThan(0, $pct);
        $this->assertLessThanOrEqual(100, $pct);
    }

    public function test_rdu_query_service_returns_null_when_denominator_zero(): void
    {
        $mock = new class extends MockRduDataSource {
            public function getDenominator(string $kpiCode, int $year, int $month): int
            {
                return 0;
            }
        };

        $service = new RduQueryService($mock);
        $this->assertNull($service->getPercentage('KPI01', 2568, 1));
    }

    // -------------------------------------------------------------------------
    // DI binding — mock when no JSON config present
    // -------------------------------------------------------------------------

    public function test_app_binds_mock_data_source_when_no_json_config(): void
    {
        $configService = new DatabaseConfigService();
        if ($configService->hasConfig()) {
            $this->markTestSkipped('JSON config exists — mock binding skipped.');
        }

        $resolved = $this->app->make(RduDataSourceInterface::class);
        $this->assertInstanceOf(MockRduDataSource::class, $resolved);
    }
}
