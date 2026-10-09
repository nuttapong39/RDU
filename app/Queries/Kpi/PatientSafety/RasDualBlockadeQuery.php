<?php

namespace App\Queries\Kpi\PatientSafety;

use App\Queries\Kpi\KpiQueryInterface;
use App\Support\FiscalDateRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KPI-12: ร้อยละจำนวนครั้งบริการโรคความดันโลหิตสูงทั่วไปที่ได้รับ RAS Blockade 2 ชนิดร่วมกันในการรักษา
 * Adapted from a hospital-provided reference query (docs/SQL_KPI/KPI12.sql), changed as agreed
 * 2026-10-09 (see CONTEXT.md "RAS blockade 2 ชนิดร่วมกัน"):
 *  - "2 ชนิด" counts distinct active ingredients, not icodes — Enalapril 5 mg + 20 mg is one drug;
 *  - only dispensed lines (qty > 0) — a same-day switch (old drug returned, new one given) is not dual use;
 *  - Captopril and the retired Losartan icodes are included; test patients are excluded.
 * Denominator = OPD visits with an I10 diagnosis (any position) given ≥ 1 RAS drug;
 * numerator = those given ≥ 2 different RAS drugs. Target 0% (lower is better).
 */
class RasDualBlockadeQuery implements KpiQueryInterface
{
    /** `drugitems.icode` → active ingredient, for every ACEI / ARB in this hospital's formulary (incl. retired codes). */
    private const RAS_DRUGS = [
        '1510003' => 'enalapril',  // Enalapril 5 mg
        '1000122' => 'enalapril',  // Enalapril 20 mg
        '1530006' => 'captopril',  // Captopril 25 mg
        '1670015' => 'losartan',   // Losartan 50 mg
        '1540023' => 'losartan',   // Losartan 50 mg (retired code)
        '1570019' => 'losartan',   // Losartan(a) 50 mg (retired code)
    ];

    // ---- Fiscal month/quarter/year entry points — resolve to a date range, then delegate ----

    public function getDenominator(int $year, int $month): int
    {
        return $this->getDenominatorBetween(...FiscalDateRange::toGregorian($year, $month));
    }

    public function getNumerator(int $year, int $month): int
    {
        return $this->getNumeratorBetween(...FiscalDateRange::toGregorian($year, $month));
    }

    public function getDetails(int $year, int $month): Collection
    {
        return $this->getDetailsBetween(...FiscalDateRange::toGregorian($year, $month));
    }

    // ---- Arbitrary date-range core — used above, and directly for custom-range export ----

    public function getDenominatorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH '.$this->rasVisitsCte().'
            SELECT COUNT(*) AS total FROM ras_visits',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    public function getNumeratorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH '.$this->rasVisitsCte().'
            SELECT COUNT(*) AS total FROM ras_visits WHERE ras_drugs >= 2',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /**
     * One row per denominator visit. `drugitems_name` / `atb_cost` are filled only for numerator
     * visits (≥ 2 RAS drugs) — the row contract every KPI page reads: empty = not in the numerator.
     * No LIMIT — callers that need a bounded page of rows must paginate the returned Collection themselves.
     */
    public function getDetailsBetween(string $startDate, string $endDate): Collection
    {
        $rows = DB::connection('rdu')->select(
            'WITH '.$this->rasVisitsCte().',
            drug_info AS (
                SELECT o.vn,
                       array_to_string(array_agg(DISTINCT concat(d.name, \' \', d.strength, \' \', d.units)), \';\') AS drugitems_name,
                       SUM(o.sum_price) AS atb_cost
                FROM opitemrece o
                INNER JOIN drugitems d ON d.icode = o.icode
                WHERE o.vn IN (SELECT vn FROM ras_visits WHERE ras_drugs >= 2)
                  AND o.qty > 0
                  AND o.icode IN ('.$this->quotedList(array_keys(self::RAS_DRUGS)).')
                GROUP BY o.vn
            ),
            all_drug_info AS (
                SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
                FROM opitemrece op
                WHERE op.vn IN (SELECT vn FROM ras_visits)
                  AND op.qty > 0
                GROUP BY op.vn
            )
            SELECT
                o.hn,
                o.vn,
                o.vstdate,
                array_to_string(array_agg(DISTINCT od.icd10), \',\') AS dx,
                p.pname, p.fname, p.lname,
                tt.drugitems_name,
                tt.atb_cost,
                COALESCE(adi.total_drug_items, 0) AS total_drug_items,
                CASE WHEN attdoc.code IS NOT NULL THEN concat(attdoc.name, \' (\', attdoc.licenseno, \')\') ELSE NULL END AS doctor,
                attdoc.code AS doctor_code,
                pt.name AS coverage,
                sp.name AS department,
                kd.department AS room
            FROM ovst o
            LEFT OUTER JOIN patient p ON p.hn = o.hn
            LEFT OUTER JOIN ovstdiag od ON od.vn = o.vn
            LEFT OUTER JOIN drug_info tt ON tt.vn = o.vn
            LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
            LEFT OUTER JOIN doctor attdoc ON attdoc.code = o.doctor AND attdoc.council_code = \'01\' AND attdoc.active = \'Y\'
            LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
            LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
            LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
            WHERE o.vn IN (SELECT vn FROM ras_visits)
              AND '.$this->diagnosisCondition('od.icd10').'
            GROUP BY
                o.hn, o.vn, o.vstdate,
                tt.vn, tt.drugitems_name, tt.atb_cost,
                adi.total_drug_items,
                p.pname, p.fname, p.lname,
                attdoc.code, attdoc.name, attdoc.licenseno,
                pt.name, sp.name, kd.department
            ORDER BY o.vstdate, o.hn, o.vn',
            [$startDate, $endDate]
        );

        return collect($rows)->map(fn ($row) => [
            'HN'               => $row->hn,
            'VN'               => $row->vn,
            'vstdate'          => $row->vstdate,
            'dx'               => $row->dx,
            'fullname'         => $this->maskThaiName($row->pname, $row->fname, $row->lname),
            'drugitems_name'   => $row->drugitems_name,
            'atb_cost'         => (float) $row->atb_cost,
            'total_drug_items' => (int) $row->total_drug_items,
            'doctor'           => $row->doctor,
            'doctor_code'      => $row->doctor_code,
            'coverage'         => $row->coverage,
            'department'       => $row->department,
            'room'             => $row->room,
        ]);
    }

    /**
     * `ras_visits(vn, ras_drugs)`: every denominator visit in [?, ?] with how many different RAS active
     * ingredients were dispensed in it. Numerator = ras_drugs >= 2.
     */
    private function rasVisitsCte(): string
    {
        return 'ras_visits AS (
                SELECT o.vn, COUNT(DISTINCT '.$this->activeIngredient('op.icode').') AS ras_drugs
                FROM ovst o
                INNER JOIN opitemrece op ON op.vn = o.vn
                WHERE o.vstdate BETWEEN ? AND ?
                  AND '.$this->diagnosisExists('o.vn').'
                  AND op.qty > 0
                  AND op.icode IN ('.$this->quotedList(array_keys(self::RAS_DRUGS)).')
                  AND o.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
                GROUP BY o.vn
            )';
    }

    /** SQL expression mapping an icode column to its active ingredient (RAS_DRUGS), so dose variants count once. */
    private function activeIngredient(string $icodeColumn): string
    {
        $cases = '';
        foreach (self::RAS_DRUGS as $icode => $ingredient) {
            $cases .= ' WHEN '.$this->quotedList([$icode]).' THEN '.$this->quotedList([$ingredient]);
        }

        return "CASE $icodeColumn$cases END";
    }

    /** Row-level check: essential (primary) hypertension — ICD-10 I10. */
    private function diagnosisCondition(string $column): string
    {
        return "$column LIKE 'I10%'";
    }

    /** Visit-level gate: any of the visit's diagnosis rows (any position) is I10. */
    private function diagnosisExists(string $vnColumn): string
    {
        return 'EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = '.$vnColumn.' AND '.$this->diagnosisCondition('dg.icd10').')';
    }

    /**
     * Builds a quoted SQL literal list from a fixed internal whitelist (icodes / ingredient names —
     * class constants, never user input), so no bound parameters are needed for these.
     */
    private function quotedList(array $values): string
    {
        return implode(',', array_map(fn (string $v) => "'".str_replace("'", "''", $v)."'", $values));
    }

    /** PDPA masking: title + given name shown in full (for staff readability); surname replaced entirely with "xxxx". */
    private function maskThaiName(?string $pname, ?string $fname, ?string $lname): string
    {
        $fname = trim((string) $fname);
        $lname = trim((string) $lname);

        return trim(sprintf('%s %s %s', trim((string) $pname), $fname, $lname === '' ? '' : 'xxxx'));
    }
}
