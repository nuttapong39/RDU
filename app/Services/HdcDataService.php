<?php

namespace App\Services;

use App\Exceptions\HdcUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Reads RDU indicator figures from MOPH Open Data (HDC). All knowledge of the (undocumented) HDC API —
 * URL, table names, columns, direction conversion — lives here.
 */
class HdcDataService
{
    /**
     * KPI code => HDC table and the columns holding the denominator / numerator — one column, or a list
     * of columns that are summed (tables published per month, e.g. s_rdu10's t10…t09 / r10…r09).
     * `inverted`: HDC counts cases that RECEIVED an antibiotic while our numerator counts cases that did not.
     * Not linked yet (no matching HDC table found): KPI06, KPI07. KPI11 matches s_rdu18 but HDC counts this
     * hospital's numerator as 0 all year (likely an unmapped Cetirizine drug code), so it is not shown.
     */
    private const MAPPING = [
        'KPI01' => ['table' => 's_rdu8',  'den' => 'target',  'num' => 'result'],
        'KPI02' => ['table' => 's_rdu8',  'den' => 'target1', 'num' => 'result1'],
        'KPI03' => ['table' => 's_rdu9',  'den' => 'target',  'num' => 'result', 'inverted' => true],
        'KPI04' => ['table' => 's_rdu6',  'den' => 'target',  'num' => 'result'],
        'KPI05' => ['table' => 's_rdu7',  'den' => 'target',  'num' => 'result'],
        'KPI08' => ['table' => 's_rdu15', 'den' => 'target',  'num' => 'result'], // asthma OPD given inhaled corticosteroid
        'KPI09' => ['table' => 's_rdu14', 'den' => 'target',  'num' => 'result'], // CKD stage ≥3 given NSAIDs
        'KPI10' => ['table' => 's_rdu12', 'den' => 'target',  'num' => 'result'], // type 2 DM (eGFR ≥30) on metformin
        'KPI12' => ['table' => 's_rdu10', 'den' => self::FISCAL_MONTHS_T, 'num' => self::FISCAL_MONTHS_R], // essential HT visits: ≥ 1 / ≥ 2 RAS drugs
        'KPI13' => ['table' => 's_rdu16', 'den' => 'target',  'num' => 'result'], // visits of patients aged 65+ given a long-acting benzodiazepine
    ];

    /** s_rdu10's per-month columns, October → September. */
    private const FISCAL_MONTHS_T = ['t10', 't11', 't12', 't01', 't02', 't03', 't04', 't05', 't06', 't07', 't08', 't09'];
    private const FISCAL_MONTHS_R = ['r10', 'r11', 'r12', 'r01', 'r02', 'r03', 'r04', 'r05', 'r06', 'r07', 'r08', 'r09'];

    private const THAI_MONTHS = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    /** How long a failed HDC call suppresses further dashboard calls (the modal's retry ignores this). */
    private const DOWN_TTL = 300;

    public function isLinked(string $kpiCode): bool
    {
        return isset(self::MAPPING[$kpiCode]);
    }

    public function table(string $kpiCode): ?string
    {
        return self::MAPPING[$kpiCode]['table'] ?? null;
    }

    /**
     * Every hospital in the configured province for this KPI and fiscal year, as
     * ['hospcode', 'areacode', 'num', 'den', 'date_com'] with the numerator already in our KPI's direction.
     *
     * @param  bool  $honourOutage  false = try HDC even if it failed moments ago (user-initiated retry)
     * @throws HdcUnavailableException
     */
    public function rows(string $kpiCode, int $fyYear, bool $honourOutage = true): array
    {
        $map = self::MAPPING[$kpiCode] ?? null;
        if ($map === null) {
            return [];
        }

        return collect($this->fetch($map['table'], $fyYear, $honourOutage))
            ->filter(fn ($r) => is_array($r) && isset($r['hospcode']))
            ->map(function (array $r) use ($map) {
                $den = $this->sum($r, $map['den']);
                $num = $this->sum($r, $map['num']);
                if (! empty($map['inverted'])) {
                    $num = max(0, $den - $num);
                }

                return [
                    'hospcode' => (string) $r['hospcode'],
                    'areacode' => (string) ($r['areacode'] ?? ''),
                    'num'      => $num,
                    'den'      => $den,
                    'date_com' => $r['date_com'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Our hospital's row for this KPI and year, or null when the KPI is not linked or the hospital is absent.
     *
     * @throws HdcUnavailableException
     */
    public function ours(string $kpiCode, int $fyYear): ?array
    {
        $hospcode = config('rdu.hdc.hospcode');

        return collect($this->rows($kpiCode, $fyYear))->firstWhere('hospcode', $hospcode);
    }

    /** @param  string|list<string>  $columns  one column, or several to add up */
    private function sum(array $row, string|array $columns): int
    {
        return array_sum(array_map(fn (string $c) => (int) ($row[$c] ?? 0), (array) $columns));
    }

    /** "25 ก.ย. 2569 09:26" from HDC's YYYYMMDDhhmm processing timestamp. */
    public static function formatProcessedAt(?string $raw): ?string
    {
        if (! $raw || ! preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})/', $raw, $m)) {
            return null;
        }

        return sprintf('%d %s %d %s:%s', (int) $m[3], self::THAI_MONTHS[(int) $m[2] - 1] ?? '', (int) $m[1] + 543, $m[4], $m[5]);
    }

    /** @throws HdcUnavailableException */
    private function fetch(string $table, int $fyYear, bool $honourOutage): array
    {
        $province = config('rdu.hdc.province');
        $key      = "rdu.hdc.{$table}.{$fyYear}.{$province}";
        $downKey  = "rdu.hdc.down.{$table}.{$fyYear}.{$province}";
        $offline  = 'rdu.hdc.down';   // HDC unreachable at all (timeout/network) — covers every table

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        // Dashboard pages read several HDC tables per request: after a failure, don't make every page
        // load wait out the timeout again for the next few minutes.
        if ($honourOutage && (Cache::has($offline) || Cache::has($downKey))) {
            throw new HdcUnavailableException('HDC recently unavailable');
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
            $data = $response->successful() ? $response->json('data') : null;
            if (! is_array($data)) {
                throw new HdcUnavailableException('HDC returned HTTP '.$response->status());
            }
        } catch (HdcUnavailableException $e) {
            Cache::put($downKey, true, self::DOWN_TTL);
            throw $e;
        } catch (\Throwable $e) {
            // A timeout would hit every table on the page in turn, so one failure marks all of HDC down.
            Cache::put($offline, true, self::DOWN_TTL);
            throw new HdcUnavailableException('HDC request failed: '.$e->getMessage(), previous: $e);
        }

        Cache::put($key, $data, config('rdu.hdc.cache_ttl'));
        Cache::forget($downKey);
        Cache::forget($offline);

        return $data;
    }
}
