<?php

namespace App\Queries\Kpi\Antibiotic;

use App\Queries\Kpi\KpiQueryInterface;
use App\Support\FiscalDateRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KPI-03: ร้อยละการใช้ยาปฏิชีวนะในหญิงคลอดปกติครบกำหนด
 * Adapted from a hospital IT/reporting-provided reference query (2026-09), verified against live
 * HOSxP schema. Note the numerator here is the INVERSE of KPI01's: it counts admissions that did
 * NOT receive an antibiotic (target_direction='higher' — more non-antibiotic deliveries is better).
 */
class NormalDeliveryAntibioticQuery implements KpiQueryInterface
{
    /** Principal diagnosis for a normal, spontaneous, full-term delivery ("การคลอดปกติ"). */
    private const PRINCIPAL_DX = 'O800';

    /** Same `drugitems.icode` antibiotic whitelist used by KPI01 — hospital-provided list. */
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
            'SELECT COUNT(DISTINCT o.an) AS total
             FROM ipt o
             INNER JOIN an_stat vn ON vn.an = o.an
             WHERE o.dchdate BETWEEN ? AND ?
               AND o.an IS NOT NULL
               AND vn.pdx = \''.self::PRINCIPAL_DX.'\'
               AND o.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /** Numerator = admissions that received NO antibiotic (target_direction='higher' — more is better). */
    public function getNumeratorBetween(string $startDate, string $endDate): int
    {
        $row = DB::connection('rdu')->select(
            'WITH an_list AS (
                SELECT o.an
                FROM ipt o
                INNER JOIN an_stat vn ON vn.an = o.an
                WHERE o.dchdate BETWEEN ? AND ?
                  AND o.an IS NOT NULL
                  AND vn.pdx = \''.self::PRINCIPAL_DX.'\'
                  AND o.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            )
            SELECT COUNT(*) AS total
            FROM an_list
            WHERE an_list.an NOT IN (
                SELECT op.an
                FROM opitemrece op
                INNER JOIN drugitems d ON d.icode = op.icode
                WHERE op.an IN (SELECT an FROM an_list)
                  AND op.qty > 0
                  AND d.icode IN ('.$this->quotedList(self::ANTIBIOTIC_ICODES).')
            )',
            [$startDate, $endDate]
        );

        return (int) ($row[0]->total ?? 0);
    }

    /** No LIMIT — callers that need a bounded page of rows must paginate the returned Collection themselves. */
    public function getDetailsBetween(string $startDate, string $endDate): Collection
    {
        $rows = DB::connection('rdu')->select(
            'WITH an_list AS (
                SELECT o.an
                FROM ipt o
                INNER JOIN an_stat vn ON vn.an = o.an
                WHERE o.dchdate BETWEEN ? AND ?
                  AND o.an IS NOT NULL
                  AND vn.pdx = \''.self::PRINCIPAL_DX.'\'
                  AND o.hn NOT IN (SELECT hn FROM patient WHERE fname LIKE \'%ทดสอบ%\')
            ),
            drug_info AS (
                SELECT op.an,
                       array_to_string(array_agg(DISTINCT concat(d.name, \' \', d.strength, \' \', d.units)), \';\') AS drugitems_name,
                       SUM(op.sum_price) AS atb_cost
                FROM opitemrece op
                INNER JOIN drugitems d ON d.icode = op.icode
                WHERE op.an IN (SELECT an FROM an_list)
                  AND op.qty > 0
                  AND d.icode IN ('.$this->quotedList(self::ANTIBIOTIC_ICODES).')
                GROUP BY op.an
            ),
            all_drug_info AS (
                SELECT op.an, COUNT(DISTINCT op.icode) AS total_drug_items
                FROM opitemrece op
                WHERE op.an IN (SELECT an FROM an_list)
                  AND op.qty > 0
                GROUP BY op.an
            )
            SELECT
                o.hn,
                o.an,
                o.dchdate,
                vn.pdx AS dx,
                p.pname, p.fname, p.lname,
                di.drugitems_name,
                di.atb_cost,
                COALESCE(adi.total_drug_items, 0) AS total_drug_items,
                CASE WHEN dd.code IS NOT NULL THEN concat(dd.name, \' (\', dd.licenseno, \')\') ELSE NULL END AS doctor,
                dd.code AS doctor_code,
                pt.name AS coverage,
                sp.name AS department,
                w.name AS room
            FROM ipt o
            INNER JOIN an_stat vn ON vn.an = o.an
            LEFT OUTER JOIN patient p ON p.hn = o.hn
            LEFT OUTER JOIN drug_info di ON di.an = o.an
            LEFT OUTER JOIN all_drug_info adi ON adi.an = o.an
            LEFT OUTER JOIN doctor dd ON dd.code = o.admdoctor AND dd.council_code = \'01\' AND dd.active = \'Y\'
            LEFT OUTER JOIN pttype pt ON pt.pttype = o.pttype
            LEFT OUTER JOIN spclty sp ON sp.spclty = o.spclty
            LEFT OUTER JOIN ward w ON w.ward = o.ward
            WHERE o.an IN (SELECT an FROM an_list)
            ORDER BY o.dchdate, o.hn, o.an',
            [$startDate, $endDate]
        );

        return collect($rows)->map(fn ($row) => [
            'HN'               => $row->hn,
            'AN'               => $row->an,
            'dchdate'          => $row->dchdate,
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
     * Builds a quoted SQL literal list from a fixed internal whitelist (antibiotic icodes — a
     * class constant, never user input), so no bound parameters are needed for this.
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
