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
 * KPI-09: ร้อยละของผู้ป่วยนอกโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้รับ NSAIDs
 * Adapted from the pharmacist's reference query (docs/SQL_KPI/KPI9.sql), agreed 2026-10-16
 * (see CONTEXT.md "ผู้ป่วยโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้ NSAIDs"):
 *  - counted in PATIENTS (distinct HN), not visits — like KPI08;
 *  - denominator = patients with an OPD visit (not admitted) diagnosed N183/N184/N185 in any position in the period;
 *  - numerator = those dispensed (qty > 0) an NSAID at ANY OPD visit in the same period — the pharmacist's
 *    query only looked at the CKD visit itself, which misses NSAIDs given at another clinic (e.g. the ER);
 *  - the NSAID list is KPI07's (the `nsaid` drug code set); test patients are excluded.
 * Target ≤ 10% (lower is better).
 * Each patient's detail row describes a representative visit: their latest NSAID visit if they are in the
 * numerator (so the doctor and room shown are the ones who gave the NSAID), else their latest CKD visit.
 */
class CkdNsaidQuery implements KpiQueryInterface, DescribesCountingMethod
{
    use UsesDrugCodeSets;

    /** Chronic kidney disease stage 3, 4 and 5 — not N182 (stage 2) or N189 (stage unspecified). */
    private const DIAGNOSIS_CODES = ['N183', 'N184', 'N185'];

    /** Kidney codes deliberately left out of DIAGNOSIS_CODES, and why (shown in the counting method). */
    private const EXCLUDED_DIAGNOSES = [
        'N182' => 'ระดับ 2 ยังไม่ถึงเกณฑ์ของตัวชี้วัด',
        'N189' => 'ไม่ระบุระดับ จึงไม่รู้ว่าถึงระดับ 3 หรือไม่',
    ];

    /** How this KPI is counted, built from the same constants as its SQL (CONTEXT.md: วิธีนับ). */
    public function countingMethod(): CountingMethod
    {
        return new CountingMethod(
            unit: 'ผู้ป่วย (คน) — คนเดียวมาหลายครั้งนับ 1 คน',
            denominator: ['ผู้ป่วยที่มีวิสิตผู้ป่วยนอกวินิจฉัยโรคไตเรื้อรังระดับ 3–5 (รหัสในข้อ 7) ในช่วงเวลาที่ดู'],
            numerator: ['ผู้ป่วยในตัวหารที่ได้รับ NSAIDs ตามรายการรหัสยา ในวิสิตผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน รวมวิสิตที่ไม่ได้ลงรหัสโรคไต เช่น ห้องฉุกเฉิน'],
            conditions: [CountingMethod::OPD_ONLY, CountingMethod::ANY_DIAGNOSIS_POSITION, CountingMethod::DISPENSED_ONLY, CountingMethod::EXCLUDES_TEST_PATIENTS, 'รายการ NSAIDs ชุดเดียวกับ KPI07 (แก้ที่เดียว เปลี่ยนทั้งสองตัวชี้วัด)'],
            drugLabel: 'NSAIDs',
            drugs: $this->drugItems('nsaid'),
            diagnoses: [
                ['label' => 'โรคไตเรื้อรังระดับ 3 ขึ้นไป', 'codes' => self::DIAGNOSIS_CODES, 'match' => 'exact'],
            ],
            excludedDiagnoses: self::EXCLUDED_DIAGNOSES,
            doctor: 'แพทย์ผู้ตรวจของวิสิตตัวแทน = วิสิตล่าสุดที่ได้ NSAIDs ถ้าได้ มิฉะนั้นวิสิตโรคไตครั้งล่าสุด',
            source: 'docs/SQL_KPI/KPI9.sql — query จากเภสัชกร',
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

    // ---- Arbitrary date-range core — used above, and directly for quarters, year-to-date and export ----

    public function getDenominatorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH '.$this->ckdVisitsCte().'
            SELECT COUNT(DISTINCT hn) AS total FROM ckd_visits',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    public function getNumeratorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH '.$this->ckdVisitsCte().', '.$this->nsaidDispensedCte().'
            SELECT COUNT(DISTINCT c.hn) AS total
            FROM ckd_visits c
            WHERE c.hn IN (SELECT hn FROM nsaid_dispensed)',
            [$startDate, $endDate, $startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /**
     * One row per denominator PATIENT, described by their representative visit (VN, date, attending doctor,
     * coverage, department, room, its dispensed-drug count). `dx` is the patient's CKD stage code(s) seen in
     * the period — the representative visit may be an NSAID visit with no CKD code. `drugitems_name` /
     * `atb_cost` are every NSAID dispensed to that patient at any OPD visit in the period — empty = not in
     * the numerator (the row contract every KPI page reads).
     * No LIMIT — callers that need a bounded page of rows must paginate the returned Collection themselves.
     */
    public function getDetailsBetween(string $startDate, string $endDate): Collection
    {
        $rows = DB::connection('rdu')->select(
            'WITH '.$this->ckdVisitsCte().', '.$this->nsaidDispensedCte().',
            representative_visit AS (
                SELECT DISTINCT ON (hn) hn, vn
                FROM (
                    SELECT n.hn, n.vn, n.vstdate, 1 AS priority
                    FROM nsaid_dispensed n
                    WHERE n.hn IN (SELECT hn FROM ckd_visits)
                    UNION ALL
                    SELECT c.hn, c.vn, c.vstdate, 2 AS priority
                    FROM ckd_visits c
                ) candidates
                ORDER BY hn, priority, vstdate DESC, vn DESC
            ),
            ckd_stage AS (
                SELECT c.hn, array_to_string(array_agg(DISTINCT dg.icd10 ORDER BY dg.icd10), \',\') AS dx
                FROM ckd_visits c
                INNER JOIN ovstdiag dg ON dg.vn = c.vn AND '.$this->diagnosisCondition('dg.icd10').'
                GROUP BY c.hn
            ),
            drug_info AS (
                SELECT n.hn,
                       array_to_string(array_agg(DISTINCT concat(d.name, \' \', d.strength, \' \', d.units)), \';\') AS drugitems_name,
                       SUM(n.sum_price) AS atb_cost
                FROM nsaid_dispensed n
                INNER JOIN drugitems d ON d.icode = n.icode
                WHERE n.hn IN (SELECT hn FROM representative_visit)
                GROUP BY n.hn
            ),
            all_drug_info AS (
                SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
                FROM opitemrece op
                WHERE op.vn IN (SELECT vn FROM representative_visit)
                  AND op.qty > 0
                GROUP BY op.vn
            )
            SELECT
                o.hn,
                o.vn,
                o.vstdate,
                cs.dx,
                p.pname, p.fname, p.lname,
                tt.drugitems_name,
                tt.atb_cost,
                COALESCE(adi.total_drug_items, 0) AS total_drug_items,
                CASE WHEN attdoc.code IS NOT NULL THEN concat(attdoc.name, \' (\', attdoc.licenseno, \')\') ELSE NULL END AS doctor,
                attdoc.code AS doctor_code,
                pt.name AS coverage,
                sp.name AS department,
                kd.department AS room
            FROM representative_visit rv
            INNER JOIN ovst o ON o.vn = rv.vn
            LEFT OUTER JOIN patient p ON p.hn = o.hn
            LEFT OUTER JOIN ckd_stage cs ON cs.hn = o.hn
            LEFT OUTER JOIN drug_info tt ON tt.hn = o.hn
            LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
            LEFT OUTER JOIN doctor attdoc ON attdoc.code = o.doctor AND attdoc.council_code = \'01\' AND attdoc.active = \'Y\'
            LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
            LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
            LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
            ORDER BY o.vstdate, o.hn, o.vn',
            [$startDate, $endDate, $startDate, $endDate]
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

    /** `ckd_visits(vn, hn, vstdate)`: every OPD visit in [?, ?] — not admitted — with an N183/N184/N185 diagnosis in any position. */
    private function ckdVisitsCte(): string
    {
        return 'ckd_visits AS (
                SELECT o.vn, o.hn, o.vstdate
                FROM ovst o
                WHERE o.vstdate BETWEEN ? AND ?
                  AND o.an IS NULL
                  AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn AND '.$this->diagnosisCondition('dg.icd10').')
                  AND o.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            )';
    }

    /** `nsaid_dispensed(vn, hn, vstdate, icode, sum_price)`: every NSAID line dispensed (qty > 0) at any OPD visit in [?, ?], whatever its diagnosis. */
    private function nsaidDispensedCte(): string
    {
        return 'nsaid_dispensed AS (
                SELECT o.vn, o.hn, o.vstdate, op.icode, op.sum_price
                FROM ovst o
                INNER JOIN opitemrece op ON op.vn = o.vn
                WHERE o.vstdate BETWEEN ? AND ?
                  AND o.an IS NULL
                  AND op.qty > 0
                  AND op.icode IN ('.$this->quotedList($this->drugCodes('nsaid')).')
            )';
    }

    /** CKD stage 3–5 (DIAGNOSIS_CODES). */
    private function diagnosisCondition(string $column): string
    {
        return "$column IN (".$this->quotedList(self::DIAGNOSIS_CODES).')';
    }


    /** PDPA masking: title + given name shown in full (for staff readability); surname replaced entirely with "xxxx". */
    private function maskThaiName(?string $pname, ?string $fname, ?string $lname): string
    {
        $fname = trim((string) $fname);
        $lname = trim((string) $lname);

        return trim(sprintf('%s %s %s', trim((string) $pname), $fname, $lname === '' ? '' : 'xxxx'));
    }
}
