<?php

namespace App\Queries\Kpi\Antibiotic;

use App\Queries\Kpi\CountingMethod;
use App\Queries\Kpi\DescribesCountingMethod;
use App\Queries\Kpi\KpiQueryInterface;
use App\Queries\Kpi\UsesDrugCodeSets;
use App\Support\FiscalDateRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KPI-02: ร้อยละการใช้ยาปฏิชีวนะในบาดแผลสดจากอุบัติเหตุ ไม่นับรวมแผลสัตว์กัด แผลลึกถึงกล้ามเนื้อ และแผลไฟไหม้น้ำร้อนลวก
 * Adapted from the pharmacist's reference query (original in git commit 0f565ab, docs/SQL_KPI/KPI2.sql), changed
 * as agreed 2026-10-17 to match HDC s_rdu8 target1/result1 (see CONTEXT.md "บาดแผลสดจากอุบัติเหตุ"):
 *  - a visit needs BOTH an injury code and an external-cause code, as KPI01 (docs/adr/0001, 0003) — the original's
 *    "injury OR external cause" also counted visits with a cause code alone, nearly doubling the denominator;
 *  - dispensed lines only (qty > 0) and excludes test patients; no vn_stat / CID (not readable / PDPA).
 * Denominator = OPD visits with a fresh accident wound and no excluded code; numerator = those given an antibiotic.
 * Target ≤ 40% (lower is better).
 */
class FreshAccidentWoundAntibioticQuery implements KpiQueryInterface, DescribesCountingMethod
{
    use UsesDrugCodeSets;

    /**
     * ICD-10 3-character categories for the injury-nature group — the pharmacist's list, without the codes that
     * EXCLUDED_PREFIXES drops anyway (S16, T20–T32), so the counting method never lists a code as both counted
     * and excluded. Not KPI01's set: it adds e.g. S05 (eye) and S07/S08 (crushing/amputation), whole T13/T14.
     */
    private const INJURY_PREFIXES = [
        'S00', 'S01', 'S05', 'S07', 'S08', 'S09',
        'S10', 'S11', 'S15', 'S17', 'S18', 'S19',
        'S20', 'S21', 'S25', 'S27', 'S28', 'S29',
        'S30', 'S31', 'S35', 'S37', 'S38', 'S39',
        'S40', 'S41', 'S45', 'S47', 'S48', 'S49',
        'S50', 'S51', 'S55', 'S57', 'S58', 'S59',
        'S60', 'S61', 'S65', 'S67', 'S68', 'S69',
        'S70', 'S71', 'S75', 'S77', 'S78', 'S79',
        'S80', 'S81', 'S85', 'S87', 'S88', 'S89',
        'S90', 'S91', 'S95', 'S97', 'S98', 'S99',
        'T00', 'T01', 'T09', 'T11', 'T13', 'T14',
    ];

    /** Why a visit is left out entirely => the code prefixes (3 or 4 characters) that mark it. */
    private const EXCLUDED_PREFIXES = [
        'แผลสัตว์กัด'               => ['W54', 'W55', 'W56'],
        'แผลไฟไหม้น้ำร้อนลวก'       => ['T20', 'T21', 'T22', 'T23', 'T24', 'T25', 'T26', 'T27', 'T28', 'T29', 'T30', 'T31', 'T32'],
        'แผลลึกถึงกล้ามเนื้อ/เอ็น' => ['S16', 'S46', 'S56', 'S66', 'S76', 'S86', 'S96', 'S390', 'T095', 'T115', 'T135', 'T146'],
    ];


    /** How this KPI is counted, built from the same constants as its SQL (CONTEXT.md: วิธีนับ). */
    public function countingMethod(): CountingMethod
    {
        return new CountingMethod(
            unit: 'วิสิต (VN) — 1 วิสิตนับ 1 ครั้ง',
            denominator: [
                'วิสิตที่มีรหัส "ลักษณะการบาดเจ็บ" และรหัส "สาเหตุภายนอก" (ดูข้อ 7) ในวิสิตเดียวกัน ต้องมีครบทั้งสองกลุ่ม',
                'ตัดออกทั้งวิสิต ถ้ามีรหัสใดขึ้นต้นด้วยรหัสในตาราง "รหัสที่ตั้งใจไม่นับ" — แผลสัตว์กัด แผลไฟไหม้น้ำร้อนลวก และแผลลึกถึงกล้ามเนื้อ/เอ็น',
            ],
            numerator: ['วิสิตในตัวหารที่ได้รับยาปฏิชีวนะชนิดกินหรือฉีดตามรายการรหัสยาอย่างน้อย 1 รายการ'],
            conditions: [CountingMethod::OPD_ONLY, CountingMethod::ANY_DIAGNOSIS_POSITION, CountingMethod::DISPENSED_ONLY, CountingMethod::EXCLUDES_TEST_PATIENTS],
            drugLabel: 'ยาปฏิชีวนะ',
            drugs: $this->drugItems('antibiotic_fresh_wound'),
            diagnoses: [
                ['label' => 'ลักษณะการบาดเจ็บ (รหัสขึ้นต้นด้วย)', 'codes' => self::INJURY_PREFIXES, 'match' => 'prefix',
                 'note' => 'ชุดรหัสของ KPI02 — ต่างจาก KPI01 เช่น รวมแผลที่ตา (S05)'],
                ['label' => 'สาเหตุภายนอก', 'codes' => [], 'match' => 'prefix',
                 'note' => 'ทุกรหัสที่ขึ้นต้นด้วย '.implode(' หรือ ', AccidentWoundAntibioticQuery::EXTERNAL_CAUSE_LETTERS).' และรหัส '.implode('–', AccidentWoundAntibioticQuery::EXTERNAL_CAUSE_RANGE)],
            ],
            excludedDiagnoses: $this->excludedPrefixes(),
            doctor: 'แพทย์ผู้ตรวจของวิสิตนั้น',
            source: 'docs/SQL_KPI/KPI2.sql — query อ้างอิงจากเภสัชกร ปรับให้ตรงกับนิยามของ HDC',
            agreedOn: '2026-10-17',
        );
    }
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
            'WITH '.$this->vnListCte().'
            SELECT COUNT(*) AS total FROM vn_list',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    public function getNumeratorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH '.$this->vnListCte().'
            SELECT COUNT(DISTINCT o.vn) AS total
            FROM opitemrece o
            WHERE o.vn IN (SELECT vn FROM vn_list)
              AND '.$this->antibioticDispensed('o'),
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /**
     * One row per denominator visit. `drugitems_name` / `atb_cost` are filled only for numerator visits — the
     * row contract every KPI page reads: empty = not in the numerator. `dx` is the visit's injury and
     * external-cause codes.
     * No LIMIT — callers that need a bounded page of rows must paginate the returned Collection themselves.
     */
    public function getDetailsBetween(string $startDate, string $endDate): Collection
    {
        $rows = DB::connection('rdu')->select(
            'WITH '.$this->vnListCte().',
            drug_info AS (
                SELECT o.vn,
                       array_to_string(array_agg(DISTINCT concat(d.name, \' \', d.strength, \' \', d.units)), \';\') AS drugitems_name,
                       SUM(o.sum_price) AS atb_cost
                FROM opitemrece o
                INNER JOIN drugitems d ON d.icode = o.icode
                WHERE o.vn IN (SELECT vn FROM vn_list)
                  AND '.$this->antibioticDispensed('o').'
                GROUP BY o.vn
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
            WHERE o.vn IN (SELECT vn FROM vn_list)
              AND (('.$this->injuryCondition('od.icd10').') OR ('.$this->externalCauseCondition('od.icd10').'))
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
     * `vn_list(vn)`: every denominator visit in [?, ?] — OPD visits with an injury code and an external-cause
     * code (possibly on different diagnosis rows) and no excluded code on any row.
     */
    private function vnListCte(): string
    {
        return 'vn_list AS (
                SELECT ovst.vn
                FROM ovst
                WHERE ovst.vstdate BETWEEN ? AND ?
                  AND ovst.an IS NULL
                  AND '.$this->diagnosisExists('ovst.vn', $this->injuryCondition('dg.icd10')).'
                  AND '.$this->diagnosisExists('ovst.vn', $this->externalCauseCondition('dg.icd10')).'
                  AND NOT '.$this->diagnosisExists('ovst.vn', $this->excludedCondition('dg.icd10')).'
                  AND ovst.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            )';
    }

    /** `opitemrece` row-level check: an antibiotic actually dispensed (qty > 0). */
    private function antibioticDispensed(string $alias): string
    {
        return "$alias.qty > 0 AND $alias.icode IN (".$this->quotedList($this->drugCodes('antibiotic_fresh_wound')).')';
    }

    /** Row-level check: an injury-nature code (INJURY_PREFIXES). */
    private function injuryCondition(string $column): string
    {
        return "UPPER(LEFT($column, 3)) IN (".$this->quotedList(self::INJURY_PREFIXES).')';
    }

    /** Row-level check: an external-cause code (KPI01's group: V…, W…, X00–X59). */
    private function externalCauseCondition(string $column): string
    {
        [$from, $to] = AccidentWoundAntibioticQuery::EXTERNAL_CAUSE_RANGE;

        return "(UPPER(LEFT($column, 1)) IN (".$this->quotedList(AccidentWoundAntibioticQuery::EXTERNAL_CAUSE_LETTERS).") OR UPPER(LEFT($column, 3)) BETWEEN '$from' AND '$to')";
    }

    /** Row-level check: a code that drops the visit (EXCLUDED_PREFIXES — 3- and 4-character prefixes). */
    private function excludedCondition(string $column): string
    {
        return implode(' OR ', array_map(fn (string $p) => "UPPER($column) LIKE '$p%'", array_keys($this->excludedPrefixes())));
    }

    /** EXCLUDED_PREFIXES flattened to prefix => why, the shape the counting method lists. */
    private function excludedPrefixes(): array
    {
        $byPrefix = [];
        foreach (self::EXCLUDED_PREFIXES as $reason => $prefixes) {
            $byPrefix += array_fill_keys($prefixes, $reason);
        }

        return $byPrefix;
    }

    /** Visit-level gate: any of the visit's diagnosis rows (alias `dg`, any position) meets `$condition`. */
    private function diagnosisExists(string $vnColumn, string $condition): string
    {
        return 'EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = '.$vnColumn.' AND ('.$condition.'))';
    }

    /**
     * Builds a quoted SQL literal list from a fixed internal whitelist (ICD-10 prefixes / icodes — class
     * constants, never user input), so no bound parameters are needed for these.
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
