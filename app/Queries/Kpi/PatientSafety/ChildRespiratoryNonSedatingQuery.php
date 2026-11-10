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
 * KPI-11: ร้อยละผู้รับบริการผู้ป่วยเด็กที่ได้รับการวินิจฉัยเป็นโรคติดเชื้อของทางเดินหายใจและได้รับยาน้ำชนิด non-sedating
 * Adapted from the pharmacist's reference query (original in git commit 2fcdb72, docs/SQL_KPI/KPI11.sql), changed
 * as agreed 2026-10-16 (see CONTEXT.md "เด็กติดเชื้อทางเดินหายใจที่ได้ยาแก้แพ้ชนิด non-sedating"):
 *  - age comes from patient.birthday at the visit date — the original's vn_stat is not readable by rdu_readonly;
 *  - adds a denominator (every child respiratory-infection visit, whatever was dispensed), dispensed lines only
 *    (qty > 0) and excludes test patients.
 * Denominator = OPD visits of children under 15 with a J0x / J20 / J21 / J22 diagnosis (any position);
 * numerator = those given a non-sedating antihistamine. Target ≤ 20% (lower is better), as HDC grades it.
 */
class ChildRespiratoryNonSedatingQuery implements KpiQueryInterface, DescribesCountingMethod
{
    use UsesDrugCodeSets;


    /** ICD-10 prefixes for respiratory infection — every code starting with one counts (J0 = J00…J09). */
    private const DIAGNOSIS_PREFIXES = ['J0', 'J20', 'J21', 'J22'];

    /** Children = under this many full years old on the visit date. */
    private const AGE_LIMIT = 15;

    /** How this KPI is counted, built from the same constants as its SQL (CONTEXT.md: วิธีนับ). */
    public function countingMethod(): CountingMethod
    {
        return new CountingMethod(
            unit: 'วิสิต (VN) — 1 วิสิตนับ 1 ครั้ง',
            denominator: ['วิสิตของเด็กอายุต่ำกว่า '.self::AGE_LIMIT.' ปีเต็ม (คิดจากวันเกิด ณ วันที่มารับบริการ) ที่มีรหัสวินิจฉัยโรคติดเชื้อทางเดินหายใจตามตาราง อย่างน้อย 1 รหัส ไม่ว่าจะได้ยาอะไร'],
            numerator: ['วิสิตในตัวหารที่ได้ยาแก้แพ้ชนิด non-sedating ตามรายการรหัสยา อย่างน้อย 1 รายการ — นับทั้งยาน้ำและยาเม็ด'],
            conditions: [CountingMethod::OPD_ONLY, CountingMethod::ANY_DIAGNOSIS_POSITION, CountingMethod::DISPENSED_ONLY, CountingMethod::EXCLUDES_TEST_PATIENTS],
            drugLabel: 'ยาแก้แพ้ชนิด non-sedating',
            drugs: $this->drugItems('non_sedating_antihistamine'),
            diagnoses: [
                ['label' => 'โรคติดเชื้อทางเดินหายใจ', 'codes' => self::DIAGNOSIS_PREFIXES, 'match' => 'prefix',
                 'note' => 'ชุดรหัสของ KPI11 — ต่างจาก KPI04: ไม่รวมหูชั้นกลางอักเสบ (H65–H72) และไข้หวัดใหญ่ J10/J11 แต่รวม J22 และ J07–J09 ที่ขึ้นต้นด้วย J0'],
            ],
            excludedDiagnoses: [],
            doctor: 'แพทย์ผู้ตรวจของวิสิตนั้น',
            source: 'docs/SQL_KPI/KPI11.sql — query อ้างอิงจากเภสัชกร',
            agreedOn: '2026-10-16',
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
              AND '.$this->nonSedatingDispensed('o'),
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /**
     * One row per denominator visit. `drugitems_name` / `atb_cost` are filled only for numerator visits — the
     * row contract every KPI page reads: empty = not in the numerator. `dx` is the visit's respiratory codes.
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
                  AND '.$this->nonSedatingDispensed('o').'
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
     * `vn_list(vn)`: every denominator visit in [?, ?] — OPD visits of children under AGE_LIMIT on the visit
     * date with a respiratory-infection diagnosis. A patient without a birthday has no age and is not counted.
     */
    private function vnListCte(): string
    {
        return 'vn_list AS (
                SELECT ovst.vn
                FROM ovst
                INNER JOIN patient pa ON pa.hn = ovst.hn
                WHERE ovst.vstdate BETWEEN ? AND ?
                  AND ovst.an IS NULL
                  AND date_part(\'year\', age(ovst.vstdate, pa.birthday)) < '.self::AGE_LIMIT.'
                  AND '.$this->diagnosisExists('ovst.vn').'
                  AND ovst.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            )';
    }

    /** `opitemrece` row-level check: a non-sedating antihistamine actually dispensed (qty > 0). */
    private function nonSedatingDispensed(string $alias): string
    {
        return "$alias.qty > 0 AND $alias.icode IN (".$this->quotedList($this->drugCodes('non_sedating_antihistamine')).')';
    }

    /** Row-level check: a respiratory-infection code (DIAGNOSIS_PREFIXES). */
    private function diagnosisCondition(string $column): string
    {
        return '('.implode(' OR ', array_map(fn (string $p) => "$column LIKE '$p%'", self::DIAGNOSIS_PREFIXES)).')';
    }

    /** Visit-level gate: any of the visit's diagnosis rows (any position) is a respiratory infection. */
    private function diagnosisExists(string $vnColumn): string
    {
        return 'EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = '.$vnColumn.' AND '.$this->diagnosisCondition('dg.icd10').')';
    }


    /** PDPA masking: title + given name shown in full (for staff readability); surname replaced entirely with "xxxx". */
    private function maskThaiName(?string $pname, ?string $fname, ?string $lname): string
    {
        $fname = trim((string) $fname);
        $lname = trim((string) $lname);

        return trim(sprintf('%s %s %s', trim((string) $pname), $fname, $lname === '' ? '' : 'xxxx'));
    }
}
