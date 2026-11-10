<?php

namespace Tests\Feature;

use App\Exceptions\DrugCodeSetNotConfiguredException;
use App\Models\DrugCodeSet;
use App\Queries\Kpi\Antibiotic\RespiratoryInfectionAntibioticQuery;
use App\Queries\Kpi\ChronicDisease\MetforminDiabetesQuery;
use App\Queries\Kpi\PatientSafety\RasDualBlockadeQuery;
use App\Services\DrugCodeSetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Phase 2 of docs/plans/drug-code-sets-and-facility-profile.md: the one-time import and queries reading the sets. */
class DrugCodeSetImportTest extends TestCase
{
    use RefreshDatabase;

    // Start empty, like a new hospital; each test runs the import itself.
    protected bool $seedDrugCodeSets = false;

    private function service(): DrugCodeSetService
    {
        return app(DrugCodeSetService::class);
    }

    public function test_the_import_loads_chiang_klangs_lists_exactly_as_they_were_hard_coded(): void
    {
        $this->artisan('rdu:import-drug-code-sets')->assertSuccessful();

        $expected = [
            'antibiotic' => 66, 'antibiotic_fresh_wound' => 28, 'nsaid' => 6, 'ics' => 4, 'metformin' => 1,
            'glucose_lowering' => 7, 'egfr_lab' => 1, 'non_sedating_antihistamine' => 2, 'ras_blockade' => 6, 'long_acting_bzd' => 3,
        ];
        foreach ($expected as $key => $count) {
            $this->assertCount($count, $this->service()->codes($key), $key);
        }
        $this->assertSame(['200'], $this->service()->codes('egfr_lab'));
        $this->assertSame(['1000184'], $this->service()->codes('metformin'));
        $this->assertSame('1000184', $this->service()->codes('glucose_lowering')[0]);   // metformin first, as before
        $this->assertContains('1483852', $this->service()->codes('antibiotic'));        // Budesonide kept as-is (ADR 0004)
    }

    public function test_the_import_keeps_the_attributes_the_kpis_need(): void
    {
        $this->artisan('rdu:import-drug-code-sets')->assertSuccessful();

        foreach (['ras_blockade', 'non_sedating_antihistamine', 'long_acting_bzd'] as $key) {
            foreach ($this->service()->items($key) as $code => $attribute) {
                $this->assertNotEmpty($attribute, "$key $code");
            }
        }
        $this->assertSame('ยาน้ำ', $this->service()->items('non_sedating_antihistamine')['1510009']);
    }

    public function test_every_kpi_is_configured_after_the_import(): void
    {
        $this->artisan('rdu:import-drug-code-sets')->assertSuccessful();

        foreach (['KPI01', 'KPI02', 'KPI03', 'KPI04', 'KPI05', 'KPI07', 'KPI08', 'KPI09', 'KPI10', 'KPI11', 'KPI12', 'KPI13'] as $kpi) {
            $this->assertTrue($this->service()->isKpiConfigured($kpi), $kpi);
        }
    }

    public function test_the_import_is_the_baseline_and_does_not_mark_the_sets_as_edited(): void
    {
        $this->artisan('rdu:import-drug-code-sets')->assertSuccessful();

        $this->assertNull($this->service()->changedAt('antibiotic'));
        $this->assertSame(1, $this->service()->version('antibiotic'));
    }

    public function test_running_the_import_twice_changes_nothing(): void
    {
        $this->artisan('rdu:import-drug-code-sets')->assertSuccessful();
        $this->artisan('rdu:import-drug-code-sets')->assertSuccessful();

        $this->assertCount(66, $this->service()->codes('antibiotic'));
        $this->assertDatabaseCount('drug_code_set_items', 66 + 28 + 6 + 4 + 1 + 7 + 1 + 2 + 6 + 3);
    }

    public function test_a_set_a_pharmacist_already_filled_is_left_alone(): void
    {
        DrugCodeSet::create(['key' => 'nsaid', 'version' => 3, 'changed_at' => now()]);
        DrugCodeSet::find('nsaid')->items()->create(['code' => '9999999']);

        $this->artisan('rdu:import-drug-code-sets')->assertSuccessful();

        $this->service()->flush();
        $this->assertSame(['9999999'], $this->service()->codes('nsaid'));
        $this->assertSame(3, $this->service()->version('nsaid'));
        $this->assertCount(66, $this->service()->codes('antibiotic'));   // the other sets still imported
    }

    public function test_an_unknown_set_in_the_file_imports_nothing(): void
    {
        $file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rdu-bad-sets-'.uniqid().'.php';
        file_put_contents($file, "<?php return ['no_such_set' => ['1' => null], 'nsaid' => ['2' => null]];");

        try {
            $this->artisan('rdu:import-drug-code-sets', ['--file' => $file])->assertFailed();
        } finally {
            @unlink($file);   // the temp file this test created
        }

        $this->assertDatabaseCount('drug_code_set_items', 0);
    }

    public function test_a_missing_file_fails_cleanly(): void
    {
        $this->artisan('rdu:import-drug-code-sets', ['--file' => '/no/such/file.php'])->assertFailed();
    }

    // ---- queries read the sets, never icodes written in the class ----

    public function test_a_query_refuses_to_run_when_its_set_is_empty(): void
    {
        $this->expectException(DrugCodeSetNotConfiguredException::class);

        app(RespiratoryInfectionAntibioticQuery::class)->getNumerator(2569, 3);
    }

    public function test_kpi10_needs_its_lab_code_as_well_as_its_drug_sets(): void
    {
        $this->artisan('rdu:import-drug-code-sets')->assertSuccessful();
        DrugCodeSet::find('egfr_lab')->items()->delete();
        $this->service()->flush();

        $this->expectException(DrugCodeSetNotConfiguredException::class);
        app(MetforminDiabetesQuery::class)->getDenominator(2569, 3);
    }

    public function test_a_query_describes_its_drugs_from_the_sets_and_an_empty_set_describes_nothing(): void
    {
        $empty = app(RasDualBlockadeQuery::class)->countingMethod();
        $this->assertSame([], $empty->drugs);

        $this->artisan('rdu:import-drug-code-sets')->assertSuccessful();
        $this->service()->flush();

        $this->assertCount(6, app(RasDualBlockadeQuery::class)->countingMethod()->drugs);
    }

    public function test_the_artisan_command_is_registered_for_the_deploy_docs(): void
    {
        $this->assertArrayHasKey('rdu:import-drug-code-sets', Artisan::all());
    }
}
