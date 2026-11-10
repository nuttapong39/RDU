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
 * KPI-07: ร้อยละของผู้ป่วยนอกที่มีการใช้ยากลุ่ม NSAIDs ซ้ำซ้อน
 * Adapted from the pharmacist's reference query (docs/SQL_KPI/KPI7.sql), agreed 2026-10-15
 * (see CONTEXT.md "NSAIDs ซ้ำซ้อน"):
 *  - "ซ้ำซ้อน" counts drug items (icodes), not active ingredients — Diclofenac injection + Diclofenac
 *    tablet in one visit IS a duplicate (unlike KPI12, which counts ingredients);
 *  - only dispensed lines (qty > 0); test patients are excluded; any diagnosis.
 * Denominator = OPD visits (not admitted) given ≥ 1 NSAID item; numerator = those given ≥ 2 different
 * NSAID items. Target ≤ 5% (lower is better).
 */
class NsaidDuplicationQuery implements KpiQueryInterface, DescribesCountingMethod
{
    use UsesDrugCodeSets;


    /** How this KPI is counted, built from the same constants as its SQL (CONTEXT.md: วิธีนับ). */
    public function countingMethod(): CountingMethod
    {
        return new CountingMethod(
            unit: 'วิสิตผู้ป่วยนอก (VN) — 1 วิสิตนับ 1 ครั้ง',
            denominator: ['วิสิตผู้ป่วยนอกที่ได้รับ NSAIDs ตามรายการรหัสยาอย่างน้อย 1 รายการ (ไม่จำกัดการวินิจฉัย)'],
            numerator: ['วิสิตในตัวหารที่ได้ NSAIDs ตั้งแต่ 2 รายการ (รหัสยา) ขึ้นไปในวิสิตเดียวกัน — Diclofenac ฉีด + Diclofenac กิน นับเป็นซ้ำซ้อน'],
            conditions: [CountingMethod::OPD_ONLY, CountingMethod::DISPENSED_ONLY, CountingMethod::EXCLUDES_TEST_PATIENTS, 'ไม่นับยาที่ผสมยาแก้ปวดกลุ่มอื่น (เช่น Orphenadrine + Paracetamol) และไม่นับ Aspirin'],
            drugLabel: 'NSAIDs',
            drugs: $this->drugItems('nsaid'),
            diagnoses: [],
            excludedDiagnoses: [],
            doctor: 'แพทย์ผู้ตรวจของวิสิตนั้น',
            source: 'docs/SQL_KPI/KPI7.sql — query จากเภสัชกร',
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

    // ---- Arbitrary date-range core — used above, and directly for custom-range export ----

    public function getDenominatorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH '.$this->nsaidVisitsCte().'
            SELECT COUNT(*) AS total FROM nsaid_visits',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    public function getNumeratorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH '.$this->nsaidVisitsCte().'
            SELECT COUNT(*) AS total FROM nsaid_visits WHERE nsaid_items >= 2',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /**
     * One row per denominator visit. `drugitems_name` / `atb_cost` are filled only for numerator
     * visits (≥ 2 NSAID items) — the row contract every KPI page reads: empty = not in the numerator.
     * `dx` lists every diagnosis of the visit, since this KPI has no diagnosis condition.
     * No LIMIT — callers that need a bounded page of rows must paginate the returned Collection themselves.
     */
    public function getDetailsBetween(string $startDate, string $endDate): Collection
    {
        $rows = DB::connection('rdu')->select(
            'WITH '.$this->nsaidVisitsCte().',
            drug_info AS (
                SELECT o.vn,
                       array_to_string(array_agg(DISTINCT concat(d.name, \' \', d.strength, \' \', d.units)), \';\') AS drugitems_name,
                       SUM(o.sum_price) AS atb_cost
                FROM opitemrece o
                INNER JOIN drugitems d ON d.icode = o.icode
                WHERE o.vn IN (SELECT vn FROM nsaid_visits WHERE nsaid_items >= 2)
                  AND o.qty > 0
                  AND o.icode IN ('.$this->quotedList($this->drugCodes('nsaid')).')
                GROUP BY o.vn
            ),
            all_drug_info AS (
                SELECT op.vn, COUNT(DISTINCT op.icode) AS total_drug_items
                FROM opitemrece op
                WHERE op.vn IN (SELECT vn FROM nsaid_visits)
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
            WHERE o.vn IN (SELECT vn FROM nsaid_visits)
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
     * `nsaid_visits(vn, nsaid_items)`: every denominator visit in [?, ?] — an OPD visit that was not
     * admitted — with how many different NSAID items were dispensed in it. Numerator = nsaid_items >= 2.
     */
    private function nsaidVisitsCte(): string
    {
        return 'nsaid_visits AS (
                SELECT o.vn, COUNT(DISTINCT op.icode) AS nsaid_items
                FROM ovst o
                INNER JOIN opitemrece op ON op.vn = o.vn
                WHERE o.vstdate BETWEEN ? AND ?
                  AND o.an IS NULL
                  AND op.qty > 0
                  AND op.icode IN ('.$this->quotedList($this->drugCodes('nsaid')).')
                  AND o.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
                GROUP BY o.vn
            )';
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
