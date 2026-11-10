<?php

namespace App\Console\Commands;

use App\Models\DrugCodeSet;
use App\Services\DrugCodeSetService;
use App\Services\FacilityProfileService;
use App\Support\DrugCodeSetRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time import of Chiang Klang's drug code sets (the lists that used to be hard-coded in the KPI queries,
 * ADR 0004). Run by hand on that hospital's server after deploy: other hospitals start with empty sets and never
 * run it. A set that already has items is left alone, so running it twice, or after a pharmacist has edited a
 * set, changes nothing. It does not set `changed_at`: the import is the baseline, so results calculated
 * before it stay valid.
 *
 * With the default file it also sets Chiang Klang as the facility (name, province, hospcode, the old logo file) —
 * but only when no facility is set yet, so it never overwrites what an admin saved.
 */
class ImportDrugCodeSetsCommand extends Command
{
    protected $signature = 'rdu:import-drug-code-sets {--file= : PHP file returning [set key => [code => attribute]] (default: database/data/chiangklang-drug-code-sets.php)}';

    protected $description = "Import Chiang Klang Hospital's drug code sets once (skips any set that already has items)";

    public function handle(DrugCodeSetService $service, FacilityProfileService $facility): int
    {
        $file = $this->option('file') ?: database_path('data/chiangklang-drug-code-sets.php');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $data = require $file;

        foreach (array_keys($data) as $key) {
            if (! DrugCodeSetRegistry::exists($key)) {
                $this->error("Unknown drug code set '{$key}' in {$file} — nothing imported.");

                return self::FAILURE;
            }
        }

        foreach (DrugCodeSetRegistry::all() as $key => $definition) {
            $items = $data[$key] ?? [];

            if ($items === []) {
                $this->line("  {$key}: not in the file, skipped");
            } elseif ($service->isConfigured($key)) {
                $this->line("  {$key}: already has items, skipped");
            } else {
                $this->importSet($key, $items);
                $this->info("  {$key}: imported ".count($items).' item(s)');
            }
        }

        $service->flush();

        if (! $this->option('file')) {
            $this->importFacility($facility);
        }

        return self::SUCCESS;
    }

    private function importFacility(FacilityProfileService $facility): void
    {
        if ($facility->isConfigured()) {
            $this->line('  facility: already set, skipped');

            return;
        }

        $facility->save([
            'name' => 'โรงพยาบาลเชียงกลาง', 'short_name' => 'รพ.เชียงกลาง', 'province_code' => '55', 'hospcode' => '11179',
        ]);

        $logo = public_path('images/logo_ck.png');
        if (is_file($logo)) {
            $facility->importLogoFile($logo);
        }

        $this->info('  facility: set to Chiang Klang Hospital');
    }

    /** @param array<int|string, ?string> $items code => attribute */
    private function importSet(string $key, array $items): void
    {
        DB::transaction(function () use ($key, $items) {
            DrugCodeSet::firstOrCreate(['key' => $key], ['version' => 1]);

            foreach ($items as $code => $attribute) {
                DrugCodeSet::find($key)->items()->create(['code' => (string) $code, 'attribute' => $attribute]);
            }
        });
    }
}
