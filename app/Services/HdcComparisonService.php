<?php

namespace App\Services;

use App\Exceptions\HdcUnavailableException;
use App\Models\RduIndicator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Compares this system's HOSxP-based fiscal-year result with the MOPH Open Data (HDC) figures.
 * All knowledge of the (undocumented) HDC API — URL, table names, columns, direction conversion —
 * lives here. HDC is an external public service, not HOSxP, so it does not go through RduQueryService;
 * the HOSxP side still comes from RduCalculationService (which uses RduQueryService).
 */
class HdcComparisonService
{
    /**
     * KPI code => HDC table and the columns holding the denominator / numerator.
     * `inverted`: HDC counts cases that RECEIVED an antibiotic while our numerator counts cases that did not.
     * KPI02 is deliberately not linked yet: HDC `s_rdu8` target1/result1 matches its name (wounds excluding
     * bites/deep/burns, lower is better) but its seeded direction/target (higher, 80%) came from a diarrhea
     * indicator — link it when KPI02 is implemented and its seed is corrected.
     */
    private const MAPPING = [
        'KPI01' => ['table' => 's_rdu8', 'den' => 'target',  'num' => 'result'],
        'KPI03' => ['table' => 's_rdu9', 'den' => 'target',  'num' => 'result', 'inverted' => true],
        'KPI04' => ['table' => 's_rdu6', 'den' => 'target',  'num' => 'result'],
        'KPI05' => ['table' => 's_rdu7', 'den' => 'target',  'num' => 'result'],
    ];

    /** Nan district codes (first 4 digits of HDC `areacode`). */
    private const NAN_DISTRICTS = [
        '5501' => 'เมืองน่าน', '5502' => 'แม่จริม', '5503' => 'บ้านหลวง', '5504' => 'นาน้อย', '5505' => 'ปัว',
        '5506' => 'ท่าวังผา', '5507' => 'เวียงสา', '5508' => 'ทุ่งช้าง', '5509' => 'เชียงกลาง', '5510' => 'นาหมื่น',
        '5511' => 'สันติสุข', '5512' => 'บ่อเกลือ', '5513' => 'สองแคว', '5514' => 'ภูเพียง', '5515' => 'เฉลิมพระเกียรติ',
    ];

    private const THAI_MONTHS = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    public function __construct(private RduCalculationService $calcService) {}

    public function isLinked(string $kpiCode): bool
    {
        return isset(self::MAPPING[$kpiCode]);
    }

    /** @throws HdcUnavailableException */
    public function compare(RduIndicator $indicator, int $fyYear): array
    {
        $hospcode = config('rdu.hdc.hospcode');
        $map      = self::MAPPING[$indicator->code] ?? null;

        if ($map === null) {
            return ['linked' => false, 'kpi' => $indicator->code, 'year' => $fyYear, 'hospcode' => $hospcode];
        }

        $rows = collect($this->fetch($map['table'], $fyYear))
            ->filter(fn ($r) => is_array($r) && isset($r['hospcode']))
            ->values()
            ->map(function (array $r) use ($map, $indicator, $hospcode) {
            $den = (int) ($r[$map['den']] ?? 0);
            $num = (int) ($r[$map['num']] ?? 0);
            if (! empty($map['inverted'])) {
                $num = max(0, $den - $num);
            }

            return $this->figure($num, $den, $indicator) + [
                'hospcode'     => (string) $r['hospcode'],
                'label'        => $this->label((string) $r['hospcode'], (string) ($r['areacode'] ?? '')),
                'is_ours'      => (string) $r['hospcode'] === $hospcode,
                'processed_at' => $this->formatProcessedAt($r['date_com'] ?? null),
            ];
        });

        $ours  = $rows->firstWhere('is_ours', true);
        $hosxp = $this->hosxpFullYear($indicator, $fyYear);

        $totalNum = $rows->sum('num');
        $totalDen = $rows->sum('den');

        return [
            'linked'    => true,
            'kpi'       => $indicator->code,
            'year'      => $fyYear,
            'hospcode'  => $hospcode,
            'hdc_table' => $map['table'],
            'ours'      => [
                'hosxp'   => $hosxp,
                'hdc'     => $ours,
                'diff_pp' => $ours && $hosxp['pct'] !== null && $ours['pct'] !== null
                    ? round($hosxp['pct'] - $ours['pct'], 1) : null,
            ],
            'province' => [
                'rows'      => $this->sortRows($rows->all(), $indicator->target_direction),
                'total_num' => $totalNum,
                'total_den' => $totalDen,
                'avg_pct'   => $totalDen > 0 ? round($totalNum / $totalDen * 100, 1) : null,
            ],
        ];
    }

    /**
     * Full fiscal year (all 12 months) from HOSxP, regardless of any quarter/month filter on the page —
     * the same whole-year figure the KPI page headline shows (month 0), so the two never disagree.
     */
    private function hosxpFullYear(RduIndicator $indicator, int $fyYear): array
    {
        $year = $this->calcService->getOrCalculate($indicator, $fyYear, 0);

        return $this->figure((int) $year['numerator'], (int) $year['denominator'], $indicator);
    }

    private function figure(int $num, int $den, RduIndicator $indicator): array
    {
        $pct = $den > 0 ? round($num / $den * 100, 1) : null;

        return [
            'num'    => $num,
            'den'    => $den,
            'pct'    => $pct,
            'status' => $this->calcService->getStatus($pct, (float) $indicator->target_value, $indicator->target_direction),
        ];
    }

    /** Best result first (lowest % for 'lower', highest for 'higher'); rows with no cases last. */
    private function sortRows(array $rows, string $direction): array
    {
        usort($rows, function ($a, $b) use ($direction) {
            if ($a['pct'] === null || $b['pct'] === null) {
                return ($a['pct'] === null) <=> ($b['pct'] === null);
            }

            return $direction === 'higher' ? $b['pct'] <=> $a['pct'] : $a['pct'] <=> $b['pct'];
        });

        return array_values($rows);
    }

    private function label(string $hospcode, string $areacode): string
    {
        $district = self::NAN_DISTRICTS[substr($areacode, 0, 4)] ?? null;

        return $district ? "อ.{$district} ({$hospcode})" : $hospcode;
    }

    private function formatProcessedAt(?string $raw): ?string
    {
        if (! $raw || ! preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})/', $raw, $m)) {
            return null;
        }

        return sprintf('%d %s %d %s:%s', (int) $m[3], self::THAI_MONTHS[(int) $m[2] - 1] ?? '', (int) $m[1] + 543, $m[4], $m[5]);
    }

    /**
     * One HDC call per (table, year, province); successful responses are cached, failures are not.
     *
     * @throws HdcUnavailableException
     */
    private function fetch(string $table, int $fyYear): array
    {
        $province = config('rdu.hdc.province');
        $key      = "rdu.hdc.{$table}.{$fyYear}.{$province}";

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(config('rdu.hdc.timeout'))
                ->acceptJson()
                ->post(rtrim(config('rdu.hdc.base_url'), '/').'/report_data', [
                    'tableName' => $table,
                    'year'      => (string) $fyYear,
                    'province'  => $province,
                    'type'      => 'json',
                ]);
        } catch (\Throwable $e) {
            throw new HdcUnavailableException('HDC request failed: '.$e->getMessage(), previous: $e);
        }

        $data = $response->successful() ? $response->json('data') : null;
        if (! is_array($data)) {
            throw new HdcUnavailableException('HDC returned HTTP '.$response->status());
        }

        Cache::put($key, $data, config('rdu.hdc.cache_ttl'));

        return $data;
    }
}
