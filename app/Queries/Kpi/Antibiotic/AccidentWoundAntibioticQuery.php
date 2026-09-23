<?php

namespace App\Queries\Kpi\Antibiotic;

use App\Queries\Kpi\KpiQueryInterface;
use App\Support\FiscalDateRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KPI-01: ร้อยละการใช้ยาปฏิชีวนะในบาดแผลจากอุบัติเหตุ ครอบคลุมแผลทุกชนิด
 * Adapted from docs/SQL_KPI/KPI1.sql (hospital-provided, verified against live HOSxP schema).
 */
class AccidentWoundAntibioticQuery implements KpiQueryInterface
{
    /**
     * ICD-10 (dotless) 3-char prefixes for the injury-nature ("what happened to the body") group.
     * A visit qualifies as an accident-wound case only when it has a code from THIS list AND a
     * separate code from EXTERNAL_CAUSE (see injuryTypeExists()/externalCauseExists()) — matching
     * standard ICD-10 practice of pairing a nature-of-injury code with an external-cause code.
     */
    private const ICD10_PREFIX3 = [
        'S00', 'S01', 'S16', 'S19', 'S20', 'S21', 'S29', 'S30', 'S31', 'S40', 'S41', 'S46', 'S49',
        'S50', 'S51', 'S56', 'S59', 'S60', 'S61', 'S66', 'S69', 'S70', 'S71', 'S76', 'S79',
        'S80', 'S81', 'S86', 'S89', 'S90', 'S91', 'S96', 'S99', 'T00', 'T01', 'T07', 'T20',
        'T21', 'T22', 'T23', 'T24', 'T25', 'T29', 'T30', 'T31', 'T32',
    ];

    /** ICD-10 (dotless) 4-char prefixes for the injury-nature group. */
    private const ICD10_PREFIX4 = [
        'S091', 'S098', 'S099', 'S107', 'S108', 'S109', 'S117', 'S118', 'S119', 'S390', 'S398', 'S399',
        'T090', 'T091', 'T095', 'T110', 'T111', 'T115', 'T130', 'T131', 'T135', 'T140', 'T141', 'T146', 'T149',
    ];

    /**
     * `drugitems.icode` whitelist for antibiotics — hospital-provided list (docs/SQL_KPI/KPI1.sql),
     * used directly instead of the antibiotic='Y'+dosageform-flag approach this class used before:
     * confirmed against live data that this list is a strict superset of the old flag-based method
     * (loses zero actually-dispensed antibiotics, gains 11 — several of which the old method missed
     * due to a dosageform singular/plural mismatch, e.g. 'TABLET' vs the old whitelist's 'TABLETS').
     */
    private const ANTIBIOTIC_ICODES = [
        '1000125', '1483847', '1000028', '1000030', '1500906', '1000213', '1530023', '1460340',
        '1483916', '1483852', '1000034', '1610058', '1610031', '1000084', '1440403', '1460312',
        '1530013', '1000082', '1483877', '1000085', '1000060', '1483876', '1483867', '1520008',
        '1000140', '1540024', '1483857', '1000267', '1460047', '1000163', '1430203', '1510018',
        '1483898', '1460057', '1460106', '1520010', '1570021', '1000011', '1580013', '1483849',
        '1483788', '1510042', '1015001', '1600008', '1590006', '1640030', '1440208', '1510032',
        '1460109', '1580003', '1000221', '1510041', '1510044', '1000202', '1000188', '1000231',
        '1460110', '1520011', '1000126', '1460177', '1460300', '1483815', '1510004', '1510007',
        '1530015', '1550002',
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
            'SELECT COUNT(DISTINCT ovst.vn) AS total
             FROM ovst
             WHERE ovst.vstdate BETWEEN ? AND ?
               AND '.$this->injuryTypeExists('ovst.vn').'
               AND '.$this->externalCauseExists('ovst.vn').'
               AND ovst.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    public function getNumeratorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH vn_list AS (
                SELECT ovst.vn
                FROM ovst
                WHERE ovst.vstdate BETWEEN ? AND ?
                  AND '.$this->injuryTypeExists('ovst.vn').'
                  AND '.$this->externalCauseExists('ovst.vn').'
                  AND ovst.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            )
            SELECT COUNT(DISTINCT o.vn) AS total
            FROM opitemrece o
            INNER JOIN drugitems d ON d.icode = o.icode
            WHERE o.vn IN (SELECT vn FROM vn_list)
              AND o.qty > 0
              AND d.icode IN ('.$this->quotedList(self::ANTIBIOTIC_ICODES).')',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /** No LIMIT — callers that need a bounded page of rows must paginate the returned Collection themselves. */
    public function getDetailsBetween(string $startDate, string $endDate): Collection
    {
        $rows = DB::connection('rdu')->select(
            'WITH vn_list AS (
                SELECT ovst.vn
                FROM ovst
                WHERE ovst.vstdate BETWEEN ? AND ?
                  AND '.$this->injuryTypeExists('ovst.vn').'
                  AND '.$this->externalCauseExists('ovst.vn').'
                  AND ovst.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            ),
            drug_info AS (
                SELECT o.hn, o.vn,
                       array_to_string(array_agg(DISTINCT concat(d.name, \' \', d.strength, \' \', d.units)), \';\') AS drugitems_name,
                       array_to_string(array_agg(DISTINCT concat(dd.name, \' (\', dd.licenseno, \')\')), \';\') AS doctor_name,
                       SUM(o.sum_price) AS atb_cost
                FROM opitemrece o
                INNER JOIN drugitems d ON d.icode = o.icode
                LEFT OUTER JOIN doctor dd ON dd.code = o.doctor
                WHERE o.vn IN (SELECT vn FROM vn_list)
                  AND o.qty > 0
                  AND d.icode IN ('.$this->quotedList(self::ANTIBIOTIC_ICODES).')
                GROUP BY o.hn, o.vn
            ),
            all_drug_info AS (
                SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
                FROM opitemrece op
                WHERE op.vn IN (SELECT vn FROM vn_list)
                  AND op.qty > 0
                GROUP BY op.vn
            )
            SELECT
                o.hn,
                o.vn,
                o.vstdate,
                array_to_string(array_agg(DISTINCT od.icd10), \',\') AS dx,
                p.pname, p.fname, p.lname,
                c.cc1 AS cc,
                h.hpi1 AS hpi,
                CASE WHEN length(tt.vn) > 0 THEN 1 ELSE 0 END AS aa,
                tt.drugitems_name,
                tt.doctor_name,
                tt.atb_cost,
                COALESCE(adi.total_drug_items, 0) AS total_drug_items,
                CASE WHEN attdoc.code IS NOT NULL THEN concat(attdoc.name, \' (\', attdoc.licenseno, \')\') ELSE NULL END AS attending_doctor,
                attdoc.code AS attending_doctor_code,
                pt.name AS coverage,
                sp.name AS department,
                kd.department AS room
            FROM ovst o
            LEFT OUTER JOIN patient p ON p.hn = o.hn
            LEFT OUTER JOIN ovstdiag od ON od.vn = o.vn
            LEFT OUTER JOIN drug_info tt ON tt.vn = o.vn
            LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
            LEFT OUTER JOIN doctor attdoc ON attdoc.code = o.doctor AND attdoc.council_code = \'01\' AND attdoc.active = \'Y\'
            LEFT JOIN (SELECT vn, string_agg(cc, \', \') AS cc1 FROM opdscreen_cc_list GROUP BY vn) c ON o.vn = c.vn
            LEFT JOIN (SELECT vn, string_agg(hpi_text, \', \') AS hpi1 FROM patient_history_hpi GROUP BY vn) h ON o.vn = h.vn
            LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
            LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
            LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
            WHERE o.vn IN (SELECT vn FROM vn_list)
              AND (('.$this->injuryTypeCondition('od.icd10').') OR ('.$this->externalCauseCondition('od.icd10').'))
            GROUP BY
                o.hn, o.vn, o.vstdate,
                tt.vn, tt.drugitems_name, tt.doctor_name, tt.atb_cost,
                adi.total_drug_items,
                p.pname, p.fname, p.lname,
                c.cc1, h.hpi1,
                attdoc.code, attdoc.name, attdoc.licenseno,
                pt.name, sp.name, kd.department
            ORDER BY aa DESC, o.vstdate, o.hn, o.vn',
            [$startDate, $endDate]
        );

        return collect($rows)->map(fn ($row) => [
            'HN'               => $row->hn,
            'VN'               => $row->vn,
            'vstdate'          => $row->vstdate,
            'dx'               => $row->dx,
            'fullname'         => $this->maskThaiName($row->pname, $row->fname, $row->lname),
            'CC'               => $row->cc,
            'HPI'              => $row->hpi,
            'drugitems_name'   => $row->drugitems_name,
            'atb_cost'         => (float) $row->atb_cost,
            'total_drug_items' => (int) $row->total_drug_items,
            'doctor'           => $row->doctor_name,
            'attending_doctor' => $row->attending_doctor,
            'attending_doctor_code' => $row->attending_doctor_code,
            'coverage'         => $row->coverage,
            'department'       => $row->department,
            'room'             => $row->room,
        ]);
    }

    /** Row-level check: does this ICD-10 code describe the NATURE of an injury (S/T-type code)? */
    private function injuryTypeCondition(string $column): string
    {
        $p3 = $this->quotedList(self::ICD10_PREFIX3);
        $p4 = $this->quotedList(self::ICD10_PREFIX4);

        return "UPPER(LEFT($column,3)) IN ($p3) OR UPPER(LEFT($column,4)) IN ($p4)";
    }

    /** Row-level check: does this ICD-10 code describe the EXTERNAL CAUSE of an injury (V/W/X-type code)? */
    private function externalCauseCondition(string $column): string
    {
        return "UPPER(LEFT($column,1)) IN ('V','W') OR UPPER(LEFT($column,3)) BETWEEN 'X00' AND 'X59'";
    }

    /**
     * Visit-level gate: at least one of the visit's diagnosis rows is an injury-nature code.
     * Combined with externalCauseExists() via AND at the call site — a visit only qualifies as an
     * accident-wound case when BOTH an injury-nature code and an external-cause code are present
     * (on possibly different ovstdiag rows), matching standard ICD-10 nature+cause code pairing.
     * See docs/adr/0001-kpi01-injury-and-external-cause-required.md for why this replaced the
     * previous single-OR condition.
     */
    private function injuryTypeExists(string $vnColumn): string
    {
        return 'EXISTS (SELECT 1 FROM ovstdiag inj WHERE inj.vn = '.$vnColumn.' AND ('.$this->injuryTypeCondition('inj.icd10').'))';
    }

    /** Visit-level gate: at least one of the visit's diagnosis rows is an external-cause code. */
    private function externalCauseExists(string $vnColumn): string
    {
        return 'EXISTS (SELECT 1 FROM ovstdiag ext WHERE ext.vn = '.$vnColumn.' AND ('.$this->externalCauseCondition('ext.icd10').'))';
    }

    /**
     * Builds a quoted SQL literal list from a fixed internal whitelist (ICD-10 prefixes / dosage
     * forms — class constants, never user input), so no bound parameters are needed for these.
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
