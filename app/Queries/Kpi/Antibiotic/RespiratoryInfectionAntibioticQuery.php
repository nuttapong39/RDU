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
 * KPI-04: ร้อยละการใช้ยาปฏิชีวนะในโรคติดเชื้อที่ระบบการหายใจช่วงบนและหลอดลมอักเสบเฉียบพลันในผู้ป่วยนอก
 * Adapted from a hospital IT/reporting-provided reference query (2026-09), verified against live
 * HOSxP schema. Unlike KPI01 this uses a single flat ICD-10 whitelist (no injury+external-cause
 * pairing needed) — a visit qualifies if ANY of its diagnoses matches DIAGNOSIS_CODES.
 */
class RespiratoryInfectionAntibioticQuery implements KpiQueryInterface, DescribesCountingMethod
{
    use UsesDrugCodeSets;

    /** ICD-10 codes for upper-respiratory-tract infection / acute bronchitis (+ related otitis). */
    private const DIAGNOSIS_CODES = [
        'H650', 'H651', 'H659', 'H660', 'H664', 'H669', 'H670', 'H671', 'H678',
        'H720', 'H721', 'H722', 'H728', 'H729',
        'J00', 'J010', 'J011', 'J012', 'J013', 'J014', 'J018', 'J019',
        'J020', 'J029', 'J030', 'J038', 'J039', 'J040', 'J041', 'J042',
        'J050', 'J051', 'J060', 'J068', 'J069', 'J101', 'J111',
        'J200', 'J201', 'J202', 'J203', 'J204', 'J205', 'J206', 'J207', 'J208', 'J209',
        'J210', 'J218', 'J219',
    ];


    /** How this KPI is counted, built from the same constants as its SQL (CONTEXT.md: วิธีนับ). */
    public function countingMethod(): CountingMethod
    {
        return new CountingMethod(
            unit: 'วิสิต (VN) — 1 วิสิตนับ 1 ครั้ง',
            denominator: ['วิสิตที่มีรหัสวินิจฉัยโรคติดเชื้อทางเดินหายใจช่วงบน / หลอดลมอักเสบเฉียบพลัน (รวมหูอักเสบที่เกี่ยวข้อง) ตามตาราง อย่างน้อย 1 รหัส'],
            numerator: ['วิสิตในตัวหารที่ได้รับยาปฏิชีวนะตามรายการรหัสยาอย่างน้อย 1 รายการ'],
            conditions: [CountingMethod::ANY_DIAGNOSIS_POSITION, CountingMethod::DISPENSED_ONLY, CountingMethod::EXCLUDES_TEST_PATIENTS, CountingMethod::ALL_VISITS],
            drugLabel: 'ยาปฏิชีวนะ',
            drugs: $this->drugItems('antibiotic'),
            diagnoses: [
                ['label' => 'โรคติดเชื้อทางเดินหายใจช่วงบน / หลอดลมอักเสบเฉียบพลัน', 'codes' => self::DIAGNOSIS_CODES, 'match' => 'exact'],
            ],
            excludedDiagnoses: [],
            doctor: 'แพทย์ผู้ตรวจของวิสิตนั้น',
            source: 'docs/SQL_KPI/KPI4.sql — query อ้างอิงจากทีม IT/รายงานของโรงพยาบาล',
            agreedOn: null,
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
            'SELECT COUNT(DISTINCT ovst.vn) AS total
             FROM ovst
             WHERE ovst.vstdate BETWEEN ? AND ?
               AND '.$this->diagnosisExists('ovst.vn').'
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
                  AND '.$this->diagnosisExists('ovst.vn').'
                  AND ovst.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            )
            SELECT COUNT(DISTINCT o.vn) AS total
            FROM opitemrece o
            INNER JOIN drugitems d ON d.icode = o.icode
            WHERE o.vn IN (SELECT vn FROM vn_list)
              AND o.qty > 0
              AND d.icode IN ('.$this->quotedList($this->drugCodes('antibiotic')).')',
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
                  AND '.$this->diagnosisExists('ovst.vn').'
                  AND ovst.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            ),
            drug_info AS (
                SELECT o.hn, o.vn,
                       array_to_string(array_agg(DISTINCT concat(d.name, \' \', d.strength, \' \', d.units)), \';\') AS drugitems_name,
                       SUM(o.sum_price) AS atb_cost
                FROM opitemrece o
                INNER JOIN drugitems d ON d.icode = o.icode
                WHERE o.vn IN (SELECT vn FROM vn_list)
                  AND o.qty > 0
                  AND d.icode IN ('.$this->quotedList($this->drugCodes('antibiotic')).')
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

    /** Row-level check: does this ICD-10 code belong to the URI/acute-bronchitis whitelist? */
    private function diagnosisCondition(string $column): string
    {
        return "$column IN (".$this->quotedList(self::DIAGNOSIS_CODES).')';
    }

    /** Visit-level gate: at least one of the visit's diagnosis rows matches the whitelist. */
    private function diagnosisExists(string $vnColumn): string
    {
        return 'EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = '.$vnColumn.' AND dg.icd10 IN ('.$this->quotedList(self::DIAGNOSIS_CODES).'))';
    }


    /** PDPA masking: title + given name shown in full (for staff readability); surname replaced entirely with "xxxx". */
    private function maskThaiName(?string $pname, ?string $fname, ?string $lname): string
    {
        $fname = trim((string) $fname);
        $lname = trim((string) $lname);

        return trim(sprintf('%s %s %s', trim((string) $pname), $fname, $lname === '' ? '' : 'xxxx'));
    }
}
