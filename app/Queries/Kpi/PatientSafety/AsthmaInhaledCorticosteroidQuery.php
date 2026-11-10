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
 * KPI-08: ร้อยละของผู้ป่วยนอกโรคหืดที่ได้รับยา Inhaled Corticosteroid
 * Adapted from the pharmacist's reference query (docs/SQL_KPI/KPI8.sql), agreed 2026-10-15
 * (see CONTEXT.md "ผู้ป่วยโรคหืดที่ได้ ICS"):
 *  - counted in PATIENTS (distinct HN), not visits — the pharmacist's query lists visits, but a visit
 *    for an exacerbation while the patient still has an inhaler at home is not "no ICS";
 *  - denominator = patients with an OPD visit (not admitted) diagnosed J45/J46 in any position in the period;
 *  - numerator = those dispensed (qty > 0) an ICS at ANY OPD visit in the same period;
 *  - test patients are excluded. Target ≥ 80% (higher is better).
 * Each patient's detail row describes their latest asthma visit in the period (the representative visit).
 */
class AsthmaInhaledCorticosteroidQuery implements KpiQueryInterface, DescribesCountingMethod
{
    use UsesDrugCodeSets;


    /** ICD-10 prefixes for asthma — every code starting with one counts (J45, J450, J459, J46…). */
    private const DIAGNOSIS_PREFIXES = ['J45', 'J46'];

    /** How this KPI is counted, built from the same constants as its SQL (CONTEXT.md: วิธีนับ). */
    public function countingMethod(): CountingMethod
    {
        return new CountingMethod(
            unit: 'ผู้ป่วย (คน) — คนเดียวมาหลายครั้งนับ 1 คน',
            denominator: ['ผู้ป่วยที่มีวิสิตผู้ป่วยนอกวินิจฉัยโรคหืด (รหัสในข้อ 7) ในช่วงเวลาที่ดู'],
            numerator: ['ผู้ป่วยในตัวหารที่ได้รับยา ICS ตามรายการรหัสยา ในวิสิตผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน (ไม่จำเป็นต้องเป็นวิสิตที่วินิจฉัยโรคหืด)'],
            conditions: [CountingMethod::OPD_ONLY, CountingMethod::ANY_DIAGNOSIS_POSITION, CountingMethod::DISPENSED_ONLY, CountingMethod::EXCLUDES_TEST_PATIENTS],
            drugLabel: 'ยา Inhaled Corticosteroid (ICS)',
            drugs: $this->drugItems('ics'),
            diagnoses: [
                ['label' => 'โรคหืด', 'codes' => self::DIAGNOSIS_PREFIXES, 'match' => 'prefix'],
            ],
            excludedDiagnoses: [],
            doctor: 'แพทย์ผู้ตรวจของวิสิตตัวแทน = วิสิตโรคหืดครั้งล่าสุดของผู้ป่วยในช่วงเวลานั้น',
            source: 'docs/SQL_KPI/KPI8.sql — query จากเภสัชกร',
            agreedOn: '2026-10-15',
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
            'WITH '.$this->asthmaVisitsCte().'
            SELECT COUNT(DISTINCT hn) AS total FROM asthma_visits',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    public function getNumeratorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH '.$this->asthmaVisitsCte().', '.$this->icsDispensedCte().'
            SELECT COUNT(DISTINCT a.hn) AS total
            FROM asthma_visits a
            WHERE a.hn IN (SELECT hn FROM ics_dispensed)',
            [$startDate, $endDate, $startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /**
     * One row per denominator PATIENT, described by their latest asthma visit in the period (VN, date,
     * attending doctor, coverage, department, room, the visit's J45/J46 codes, its dispensed-drug count).
     * `drugitems_name` / `atb_cost` are every ICS dispensed to that patient at any OPD visit in the period
     * — empty = not in the numerator (the row contract every KPI page reads).
     * No LIMIT — callers that need a bounded page of rows must paginate the returned Collection themselves.
     */
    public function getDetailsBetween(string $startDate, string $endDate): Collection
    {
        $rows = DB::connection('rdu')->select(
            'WITH '.$this->asthmaVisitsCte().', '.$this->icsDispensedCte().',
            latest_visit AS (
                SELECT DISTINCT ON (hn) hn, vn
                FROM asthma_visits
                ORDER BY hn, vstdate DESC, vn DESC
            ),
            drug_info AS (
                SELECT i.hn,
                       array_to_string(array_agg(DISTINCT concat(d.name, \' \', d.strength, \' \', d.units)), \';\') AS drugitems_name,
                       SUM(i.sum_price) AS atb_cost
                FROM ics_dispensed i
                INNER JOIN drugitems d ON d.icode = i.icode
                WHERE i.hn IN (SELECT hn FROM latest_visit)
                GROUP BY i.hn
            ),
            all_drug_info AS (
                SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
                FROM opitemrece op
                WHERE op.vn IN (SELECT vn FROM latest_visit)
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
            FROM latest_visit lv
            INNER JOIN ovst o ON o.vn = lv.vn
            LEFT OUTER JOIN patient p ON p.hn = o.hn
            LEFT OUTER JOIN ovstdiag od ON od.vn = o.vn AND '.$this->diagnosisCondition('od.icd10').'
            LEFT OUTER JOIN drug_info tt ON tt.hn = o.hn
            LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
            LEFT OUTER JOIN doctor attdoc ON attdoc.code = o.doctor AND attdoc.council_code = \'01\' AND attdoc.active = \'Y\'
            LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
            LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
            LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
            GROUP BY
                o.hn, o.vn, o.vstdate,
                tt.drugitems_name, tt.atb_cost,
                adi.total_drug_items,
                p.pname, p.fname, p.lname,
                attdoc.code, attdoc.name, attdoc.licenseno,
                pt.name, sp.name, kd.department
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

    /** `asthma_visits(vn, hn, vstdate)`: every OPD visit in [?, ?] — not admitted — with a J45/J46 diagnosis in any position. */
    private function asthmaVisitsCte(): string
    {
        return 'asthma_visits AS (
                SELECT o.vn, o.hn, o.vstdate
                FROM ovst o
                WHERE o.vstdate BETWEEN ? AND ?
                  AND o.an IS NULL
                  AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn AND '.$this->diagnosisCondition('dg.icd10').')
                  AND o.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            )';
    }

    /** `ics_dispensed(hn, icode, sum_price)`: every ICS line dispensed (qty > 0) at any OPD visit in [?, ?], whatever its diagnosis. */
    private function icsDispensedCte(): string
    {
        return 'ics_dispensed AS (
                SELECT o.hn, op.icode, op.sum_price
                FROM ovst o
                INNER JOIN opitemrece op ON op.vn = o.vn
                WHERE o.vstdate BETWEEN ? AND ?
                  AND o.an IS NULL
                  AND op.qty > 0
                  AND op.icode IN ('.$this->quotedList($this->drugCodes('ics')).')
            )';
    }

    /** Asthma (DIAGNOSIS_PREFIXES: J45, or J46 status asthmaticus / acute severe asthma), any sub-code. */
    private function diagnosisCondition(string $column): string
    {
        return '('.implode(' OR ', array_map(fn (string $p) => "$column LIKE '$p%'", self::DIAGNOSIS_PREFIXES)).')';
    }

    /**
     * Builds a quoted SQL literal list from a fixed internal whitelist (icodes — a class constant,
     * never user input), so no bound parameters are needed for these.
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
