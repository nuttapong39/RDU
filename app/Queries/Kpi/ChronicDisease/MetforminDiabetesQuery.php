<?php

namespace App\Queries\Kpi\ChronicDisease;

use App\Queries\Kpi\CountingMethod;
use App\Queries\Kpi\DescribesCountingMethod;
use App\Queries\Kpi\KpiQueryInterface;
use App\Queries\Kpi\UsesDrugCodeSets;
use App\Support\FiscalDateRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KPI-10: ร้อยละของผู้ป่วยเบาหวานที่ใช้ยา Metformin เป็นยาชนิดเดียวหรือร่วมกับยาอื่นเพื่อควบคุมระดับน้ำตาลโดยไม่มีข้อห้ามใช้
 * Adapted from the pharmacist's reference query (docs/SQL_KPI/KPI10.sql) to HDC's definition (s_rdu12), agreed
 * 2026-10-16 (see CONTEXT.md "ผู้ป่วยเบาหวานชนิดที่ 2 ที่ใช้ Metformin โดยไม่มีข้อห้ามใช้"):
 *  - counted in PATIENTS (distinct HN), not visits — like KPI08/KPI09;
 *  - denominator = patients with an OPD visit (not admitted) diagnosed E11* in any position in the period, who were
 *    dispensed at least one glucose-lowering drug at any OPD visit in the period, and whose latest eGFR in the
 *    12 months ending on the period's last day is ≥ 30 (no contraindication) — no eGFR result = not in it;
 *  - numerator = those dispensed (qty > 0) metformin at ANY OPD visit in the same period;
 *  - test patients are excluded. Target ≥ 80% (higher is better).
 * Each patient's detail row describes a representative visit: their latest metformin visit if they are in the
 * numerator, else their latest type 2 DM visit — plus their latest eGFR and its date.
 */
class MetforminDiabetesQuery implements KpiQueryInterface, DescribesCountingMethod
{
    use UsesDrugCodeSets;

    /** Type 2 diabetes — every code starting with it (E11, E110 … E119). */
    private const DIAGNOSIS_PREFIX = 'E11';

    /** Diabetes codes deliberately left out of the denominator, and why (shown in the counting method). */
    private const EXCLUDED_DIAGNOSES = [
        'E10' => 'เบาหวานชนิดที่ 1 ไม่ใช่กลุ่มที่ควรได้ Metformin',
        'E13' => 'เบาหวานชนิดอื่นที่ระบุ ไม่ใช่ชนิดที่ 2',
        'E14' => 'ไม่ระบุชนิด จึงไม่รู้ว่าเป็นชนิดที่ 2 หรือไม่',
    ];

    /** Metformin is contraindicated below this eGFR. */
    private const EGFR_MINIMUM = 30;

    /** How this KPI is counted, built from the same constants as its SQL (CONTEXT.md: วิธีนับ). */
    public function countingMethod(): CountingMethod
    {
        return new CountingMethod(
            unit: 'ผู้ป่วย (คน) — คนเดียวมาหลายครั้งนับ 1 คน',
            denominator: [
                'ผู้ป่วยที่มีวิสิตผู้ป่วยนอกวินิจฉัยเบาหวานชนิดที่ 2 (รหัสในข้อ 7) ในช่วงเวลาที่ดู',
                'และได้รับยาลดระดับน้ำตาลตามรายการรหัสยาอย่างน้อย 1 ชนิด ในวิสิตผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน (ผู้ที่คุมด้วยอาหารอย่างเดียวไม่นับ)',
                'และไม่มีข้อห้ามใช้ Metformin: ผล eGFR ล่าสุดในช่วง 12 เดือนที่สิ้นสุดวันสุดท้ายของช่วงเวลาที่ดู ≥ '.self::EGFR_MINIMUM.' — ผู้ที่ไม่มีผล eGFR ในช่วงนั้นไม่อยู่ในตัวหาร',
            ],
            numerator: ['ผู้ป่วยในตัวหารที่ได้รับ Metformin ในวิสิตผู้ป่วยนอกครั้งใดก็ได้ในช่วงเวลาเดียวกัน'],
            conditions: [
                CountingMethod::OPD_ONLY, CountingMethod::ANY_DIAGNOSIS_POSITION, CountingMethod::DISPENSED_ONLY, CountingMethod::EXCLUDES_TEST_PATIENTS,
                'ผล eGFR นับทั้งที่ตรวจตอนผู้ป่วยนอกและตอนนอนโรงพยาบาล อ่านตัวเลขหน้าผล (เช่น "19.49 R" = 19.49, "<30" = ต่ำกว่า 30) ผลที่ไม่มีตัวเลขข้ามไปใช้ผลก่อนหน้า',
            ],
            drugLabel: 'ยาลดระดับน้ำตาล (ตัวหาร) — Metformin คือยาที่วัด (ตัวตั้ง)',
            drugs: array_fill_keys(array_keys($this->drugItems('metformin')), 'ยาที่วัด (ตัวตั้ง)') + $this->drugItems('glucose_lowering'),
            diagnoses: [
                ['label' => 'เบาหวานชนิดที่ 2', 'codes' => [self::DIAGNOSIS_PREFIX], 'match' => 'prefix'],
            ],
            excludedDiagnoses: self::EXCLUDED_DIAGNOSES,
            doctor: 'แพทย์ผู้ตรวจของวิสิตตัวแทน = วิสิตล่าสุดที่ได้ Metformin ถ้าได้ มิฉะนั้นวิสิตเบาหวานครั้งล่าสุด',
            source: 'docs/SQL_KPI/KPI10.sql — query จากเภสัชกร ปรับตามนิยาม HDC (s_rdu12)',
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
            'WITH '.$this->cohortCtes().'
            SELECT COUNT(DISTINCT hn) AS total FROM cohort',
            $this->cohortBindings($startDate, $endDate)
        );

        return (int) ($row[0]->total ?? 0);
    }

    public function getNumeratorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH '.$this->cohortCtes().', '.$this->metforminDispensedCte().'
            SELECT COUNT(DISTINCT c.hn) AS total
            FROM cohort c
            WHERE c.hn IN (SELECT hn FROM metformin_dispensed)',
            [...$this->cohortBindings($startDate, $endDate), $startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /**
     * One row per denominator PATIENT, described by their representative visit (VN, date, attending doctor,
     * coverage, department, room, its dispensed-drug count). `dx` is the patient's E11 code(s) seen in the period
     * — the representative visit may be a metformin visit with no DM code. `drugitems_name` / `atb_cost` are the
     * metformin dispensed to that patient at any OPD visit in the period — empty = not in the numerator (the row
     * contract every KPI page reads). `egfr` / `egfr_date` are the latest eGFR that put them in the denominator.
     * No LIMIT — callers that need a bounded page of rows must paginate the returned Collection themselves.
     */
    public function getDetailsBetween(string $startDate, string $endDate): Collection
    {
        $rows = DB::connection('rdu')->select(
            'WITH '.$this->cohortCtes().', '.$this->metforminDispensedCte().',
            representative_visit AS (
                SELECT DISTINCT ON (hn) hn, vn
                FROM (
                    SELECT m.hn, m.vn, m.vstdate, 1 AS priority
                    FROM metformin_dispensed m
                    WHERE m.hn IN (SELECT hn FROM cohort)
                    UNION ALL
                    SELECT d.hn, d.vn, d.vstdate, 2 AS priority
                    FROM dm_visits d
                    WHERE d.hn IN (SELECT hn FROM cohort)
                ) candidates
                ORDER BY hn, priority, vstdate DESC, vn DESC
            ),
            dm_codes AS (
                SELECT d.hn, array_to_string(array_agg(DISTINCT dg.icd10 ORDER BY dg.icd10), \',\') AS dx
                FROM dm_visits d
                INNER JOIN ovstdiag dg ON dg.vn = d.vn AND '.$this->diagnosisCondition('dg.icd10').'
                WHERE d.hn IN (SELECT hn FROM cohort)
                GROUP BY d.hn
            ),
            drug_info AS (
                SELECT m.hn,
                       array_to_string(array_agg(DISTINCT concat(dr.name, \' \', dr.strength, \' \', dr.units)), \';\') AS drugitems_name,
                       SUM(m.sum_price) AS atb_cost
                FROM metformin_dispensed m
                INNER JOIN drugitems dr ON dr.icode = m.icode
                WHERE m.hn IN (SELECT hn FROM cohort)
                GROUP BY m.hn
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
                dc.dx,
                p.pname, p.fname, p.lname,
                tt.drugitems_name,
                tt.atb_cost,
                COALESCE(adi.total_drug_items, 0) AS total_drug_items,
                CASE WHEN attdoc.code IS NOT NULL THEN concat(attdoc.name, \' (\', attdoc.licenseno, \')\') ELSE NULL END AS doctor,
                attdoc.code AS doctor_code,
                pt.name AS coverage,
                sp.name AS department,
                kd.department AS room,
                c.egfr,
                c.egfr_date
            FROM representative_visit rv
            INNER JOIN ovst o ON o.vn = rv.vn
            INNER JOIN cohort c ON c.hn = rv.hn
            LEFT OUTER JOIN patient p ON p.hn = o.hn
            LEFT OUTER JOIN dm_codes dc ON dc.hn = o.hn
            LEFT OUTER JOIN drug_info tt ON tt.hn = o.hn
            LEFT OUTER JOIN all_drug_info adi ON adi.vn = o.vn
            LEFT OUTER JOIN doctor attdoc ON attdoc.code = o.doctor AND attdoc.council_code = \'01\' AND attdoc.active = \'Y\'
            LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
            LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
            LEFT OUTER JOIN kskdepartment kd ON kd.depcode = o.main_dep
            ORDER BY o.vstdate, o.hn, o.vn',
            [...$this->cohortBindings($startDate, $endDate), $startDate, $endDate]
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
            'egfr'             => (float) $row->egfr,
            'egfr_date'        => $row->egfr_date,
        ]);
    }

    /**
     * The denominator, as CTEs over [start, end] (bind with cohortBindings()):
     *  - `dm_visits(vn, hn, vstdate)`: every OPD visit in the period — not admitted — with an E11* diagnosis in any position;
     *  - `glucose_lowering(hn)`: patients dispensed (qty > 0) any glucose-lowering drug at an OPD visit in the period;
     *  - `latest_egfr(hn, egfr, egfr_date)`: each DM patient's latest readable eGFR in the 12 months ending on `end`
     *    (OPD or inpatient lab — kidney function is clinical whatever the setting). The leading number is read
     *    ("19.49  R" = 19.49, ">90" = 90, "<30" = just under 30 so it stays a contraindication); results with
     *    no number are skipped, so an earlier one is used;
     *  - `cohort(hn, egfr, egfr_date)`: DM patients on a glucose-lowering drug whose latest eGFR is ≥ EGFR_MINIMUM.
     * The regexes avoid "?" so the PDO placeholder parser never sees one outside a binding.
     */
    private function cohortCtes(): string
    {
        return 'dm_visits AS (
                SELECT o.vn, o.hn, o.vstdate
                FROM ovst o
                WHERE o.vstdate BETWEEN ? AND ?
                  AND o.an IS NULL
                  AND EXISTS (SELECT 1 FROM ovstdiag dg WHERE dg.vn = o.vn AND '.$this->diagnosisCondition('dg.icd10').')
                  AND o.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            ),
            glucose_lowering AS (
                SELECT DISTINCT o.hn
                FROM ovst o
                INNER JOIN opitemrece op ON op.vn = o.vn
                WHERE o.vstdate BETWEEN ? AND ?
                  AND o.an IS NULL
                  AND op.qty > 0
                  AND op.icode IN ('.$this->quotedList($this->drugCodes('glucose_lowering')).')
            ),
            latest_egfr AS (
                SELECT DISTINCT ON (lh.hn)
                       lh.hn,
                       substring(lo.lab_order_result FROM \'^\s*[<>]{0,1}\s*([0-9]+(\.[0-9]+){0,1})\')::numeric
                           - CASE WHEN lo.lab_order_result ~ \'^\s*<\' THEN 0.01 ELSE 0 END AS egfr,
                       lh.order_date AS egfr_date
                FROM lab_head lh
                INNER JOIN lab_order lo ON lo.lab_order_number = lh.lab_order_number
                WHERE lo.lab_items_code = '.(int) $this->drugCodes('egfr_lab')[0].'
                  AND lh.order_date BETWEEN (CAST(? AS date) - INTERVAL \'12 months\' + INTERVAL \'1 day\')::date AND CAST(? AS date)
                  AND lh.hn IN (SELECT hn FROM dm_visits)
                  AND lo.lab_order_result ~ \'^\s*[<>]{0,1}\s*[0-9]\'
                ORDER BY lh.hn, lh.order_date DESC, lh.lab_order_number DESC
            ),
            cohort AS (
                SELECT e.hn, e.egfr, e.egfr_date
                FROM latest_egfr e
                WHERE e.egfr >= '.self::EGFR_MINIMUM.'
                  AND e.hn IN (SELECT hn FROM glucose_lowering)
            )';
    }

    /** Bindings for cohortCtes(), in placeholder order. */
    private function cohortBindings(string $startDate, string $endDate): array
    {
        return [$startDate, $endDate, $startDate, $endDate, $endDate, $endDate];
    }

    /** `metformin_dispensed(vn, hn, vstdate, icode, sum_price)`: every metformin line dispensed (qty > 0) at any OPD visit in [?, ?], whatever its diagnosis. */
    private function metforminDispensedCte(): string
    {
        return 'metformin_dispensed AS (
                SELECT o.vn, o.hn, o.vstdate, op.icode, op.sum_price
                FROM ovst o
                INNER JOIN opitemrece op ON op.vn = o.vn
                WHERE o.vstdate BETWEEN ? AND ?
                  AND o.an IS NULL
                  AND op.qty > 0
                  AND op.icode IN ('.$this->quotedList($this->drugCodes('metformin')).')
            )';
    }

    /** Type 2 diabetes (DIAGNOSIS_PREFIX*). */
    private function diagnosisCondition(string $column): string
    {
        return "$column LIKE '".self::DIAGNOSIS_PREFIX."%'";
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
