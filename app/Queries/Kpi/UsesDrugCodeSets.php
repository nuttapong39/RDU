<?php

namespace App\Queries\Kpi;

use App\Exceptions\DrugCodeSetNotConfiguredException;
use App\Services\DrugCodeSetService;

/**
 * Gives a KPI query the hospital's drug code sets (CONTEXT.md: ชุดรหัสยา, ADR 0004) in place of icodes written in
 * the class. The calculation layer checks a KPI is configured before it runs a query, so the empty-set exception
 * below is the last guard: it stops a query from ever being built with an empty `IN ()` or silently counting nothing.
 */
trait UsesDrugCodeSets
{
    /**
     * icodes (or lab item codes) of a set, for building SQL.
     *
     * @return non-empty-list<string>
     * @throws DrugCodeSetNotConfiguredException when the set is empty
     */
    private function drugCodes(string $set): array
    {
        $codes = app(DrugCodeSetService::class)->codes($set);

        if ($codes === []) {
            throw new DrugCodeSetNotConfiguredException($set);
        }

        return $codes;
    }

    /**
     * code => attribute (ingredient / dosage form / null), for describing the KPI. Empty when the set is not
     * configured, so a KPI that cannot be counted yet can still be described.
     *
     * @return array<string, ?string>
     */
    private function drugItems(string $set): array
    {
        return app(DrugCodeSetService::class)->items($set);
    }
}
