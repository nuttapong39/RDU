<?php

namespace App\Services;

use App\Models\RduDoctorAlias;
use Illuminate\Database\QueryException;

/**
 * Maps a real HOSxP doctor.code to a stable display alias (e.g. "DR-01"), scoped to the web UI
 * only — see docs/adr/0002-doctor-identity-masked-in-web-ui-only.md. The mapping is global across
 * every KPI and time period: the same doctor always gets the same alias, assigned once on first
 * sight and never reused or reshuffled.
 */
class DoctorAliasService
{
    public function aliasFor(string $doctorCode): string
    {
        try {
            $alias = RduDoctorAlias::firstOrCreate(['doctor_code' => $doctorCode]);
        } catch (QueryException) {
            // Lost a race with a concurrent request inserting the same doctor_code — the row
            // exists now, just not the one we hold; re-fetch it.
            $alias = RduDoctorAlias::where('doctor_code', $doctorCode)->firstOrFail();
        }

        return $alias->code();
    }
}
