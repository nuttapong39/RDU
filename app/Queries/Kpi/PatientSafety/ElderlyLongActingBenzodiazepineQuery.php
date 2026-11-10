<?php

namespace App\Queries\Kpi\PatientSafety;

use App\Queries\Kpi\CountingMethod;
use App\Queries\Kpi\DescribesCountingMethod;
use App\Queries\Kpi\KpiQueryInterface;
use App\Queries\Kpi\UsesDrugCodeSets;
use App\Support\FiscalDateRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KPI-13: ร้อยละผู้ป่วยนอกสูงอายุที่ใช้ยากลุ่ม Long-acting Benzodiazepine
 * Adapted from the pharmacist's reference query (original in git commit 1dd5498, docs/SQL_KPI/KPI13.sql), changed
 * as agreed 2026-10-17 to match HDC s_rdu16 (see CONTEXT.md "ผู้สูงอายุที่ได้ยากลุ่ม Long-acting Benzodiazepine"):
 *  - aged 65+ (the original: 60+), age from patient.birthday at the visit date — vn_stat is not readable by rdu_readonly;
 *  - Diazepam only — the pharmacist removed Clonazepam (1483866);
 *  - every visit by its service date, including visits later admitted (the original: OPD only), as HDC counts;
 *  - adds a denominator (every elderly visit), dispensed lines only (qty > 0) and excludes test patients.
 * Denominator = visits of patients aged 65+; numerator = those given Diazepam. Target ≤ 5% (lower is better).
 */
class ElderlyLongActingBenzodiazepineQuery implements KpiQueryInterface, DescribesCountingMethod
{
    use UsesDrugCodeSets;


    /** Elderly = at least this many full years old on the visit date (HDC's cut-off, not the general 60). */
    private const AGE_MINIMUM = 65;

    /** How this KPI is counted, built from the same constants as its SQL (CONTEXT.md: วิธีนับ). */
    public function countingMethod(): CountingMethod
    {
        return new CountingMethod(
            unit: 'วิสิต (VN) — 1 วิสิตนับ 1 ครั้ง',
            denominator: ['ทุกวิสิตของผู้สูงอายุ '.self::AGE_MINIMUM.' ปีเต็มขึ้นไป (คิดจากวันเกิด ณ วันที่มารับบริการ) ไม่จำกัดโรคหรือยา — ใช้เกณฑ์อายุเดียวกับ HDC'],
            numerator: ['วิสิตในตัวหารที่ได้ยากลุ่ม Long-acting Benzodiazepine ตามรายการรหัสยา อย่างน้อย 1 รายการ — Diazepam ทั้งยาเม็ดและยาฉีด ไม่นับ Clonazepam'],
            conditions: [CountingMethod::ALL_VISITS, CountingMethod::DISPENSED_ONLY, CountingMethod::EXCLUDES_TEST_PATIENTS],
            drugLabel: 'ยากลุ่ม Long-acting Benzodiazepine',
            drugs: $this->drugItems('long_acting_bzd'),
            diagnoses: [],
            excludedDiagnoses: [],
            doctor: 'แพทย์ผู้ตรวจของวิสิตนั้น',
            source: 'docs/SQL_KPI/KPI13.sql — query อ้างอิงจากเภสัชกร ปรับให้ตรงกับนิยามของ HDC',
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
              AND '.$this->longActingBzdDispensed('o'),
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /**
     * One row per denominator visit. `drugitems_name` / `atb_cost` are filled only for numerator visits — the
     * row contract every KPI page reads: empty = not in the numerator. `dx` lists every diagnosis of the visit,
     * since this KPI has no diagnosis condition.
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
                  AND '.$this->longActingBzdDispensed('o').'
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
     * `vn_list(vn)`: every denominator visit in [?, ?] — visits (later admissions included) of patients aged
     * AGE_MINIMUM or more on the visit date. A patient without a birthday has no age and is not counted.
     */
    private function vnListCte(): string
    {
        return 'vn_list AS (
                SELECT ovst.vn
                FROM ovst
                INNER JOIN patient pa ON pa.hn = ovst.hn
                WHERE ovst.vstdate BETWEEN ? AND ?
                  AND date_part(\'year\', age(ovst.vstdate, pa.birthday)) >= '.self::AGE_MINIMUM.'
                  AND ovst.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            )';
    }

    /** `opitemrece` row-level check: a long-acting benzodiazepine actually dispensed (qty > 0). */
    private function longActingBzdDispensed(string $alias): string
    {
        return "$alias.qty > 0 AND $alias.icode IN (".$this->quotedList($this->drugCodes('long_acting_bzd')).')';
    }

    /**
     * Builds a quoted SQL literal list from a fixed internal whitelist (icodes — class constants, never user
     * input), so no bound parameters are needed for these.
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
