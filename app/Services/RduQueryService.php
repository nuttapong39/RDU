<?php

namespace App\Services;

use App\DataSources\RduDataSourceInterface;
use App\Support\FiscalDateRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Single gateway for all HOSxP KPI queries.
 * Controllers must NEVER call RduDataSourceInterface or DB::connection('rdu') directly.
 */
class RduQueryService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const ICD10_CACHE_TTL = 86400; // 24 hours — static reference data, safe to cache much longer

    public function __construct(
        private RduDataSourceInterface $dataSource
    ) {}

    public function getNumerator(string $kpiCode, int $year, int $month): int
    {
        return Cache::remember(
            $this->cacheKey($kpiCode, $year, $month, 'num'),
            self::CACHE_TTL,
            fn () => $this->dataSource->getNumerator($kpiCode, $year, $month)
        );
    }

    public function getDenominator(string $kpiCode, int $year, int $month): int
    {
        return Cache::remember(
            $this->cacheKey($kpiCode, $year, $month, 'den'),
            self::CACHE_TTL,
            fn () => $this->dataSource->getDenominator($kpiCode, $year, $month)
        );
    }

    public function getDetails(string $kpiCode, int $year, int $month): Collection
    {
        return Cache::remember(
            $this->cacheKey($kpiCode, $year, $month, 'detail'),
            self::CACHE_TTL,
            fn () => $this->dataSource->getDetails($kpiCode, $year, $month)
        );
    }

    /**
     * Case-level detail rows across all 3 months of a fiscal quarter, combined — not capped
     * (each month is already fetched/cached individually via getDetails(); callers that need a
     * bounded page for on-screen display should paginate the returned Collection themselves,
     * e.g. via App\Support\SimplePaginator — exports need the full set, so nothing is dropped here).
     */
    public function getDetailsForQuarter(string $kpiCode, int $year, int $quarter): Collection
    {
        return collect(FiscalDateRange::quarterMonths($quarter))
            ->flatMap(fn (int $m) => $this->getDetails($kpiCode, $year, $m))
            ->values();
    }

    public function getNumeratorBetween(string $kpiCode, string $startDate, string $endDate): int
    {
        return Cache::remember(
            $this->cacheKeyBetween($kpiCode, $startDate, $endDate, 'num'),
            self::CACHE_TTL,
            fn () => $this->dataSource->getNumeratorBetween($kpiCode, $startDate, $endDate)
        );
    }

    public function getDenominatorBetween(string $kpiCode, string $startDate, string $endDate): int
    {
        return Cache::remember(
            $this->cacheKeyBetween($kpiCode, $startDate, $endDate, 'den'),
            self::CACHE_TTL,
            fn () => $this->dataSource->getDenominatorBetween($kpiCode, $startDate, $endDate)
        );
    }

    public function getDetailsBetween(string $kpiCode, string $startDate, string $endDate): Collection
    {
        return Cache::remember(
            $this->cacheKeyBetween($kpiCode, $startDate, $endDate, 'detail'),
            self::CACHE_TTL,
            fn () => $this->dataSource->getDetailsBetween($kpiCode, $startDate, $endDate)
        );
    }

    /** Returns percentage (0–100), or null if denominator is 0. */
    public function getPercentage(string $kpiCode, int $year, int $month): ?float
    {
        $den = $this->getDenominator($kpiCode, $year, $month);
        if ($den === 0) {
            return null;
        }

        return round($this->getNumerator($kpiCode, $year, $month) / $den * 100, 2);
    }

    /**
     * ICD-10 descriptions for the given codes — [code => ['name' => english, 'tname' => thai]].
     * Cached per-code (this is static reference data, not period-scoped), so repeated calls across
     * different filtered periods only fetch whichever codes aren't already cached.
     */
    public function getIcd10Descriptions(array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes, fn ($c) => $c !== null && $c !== '')));
        if (empty($codes)) {
            return [];
        }

        $result  = [];
        $missing = [];

        foreach ($codes as $code) {
            $cached = Cache::get($this->icd10CacheKey($code), '__miss__');
            if ($cached === '__miss__') {
                $missing[] = $code;
            } else {
                $result[$code] = $cached;
            }
        }

        if (!empty($missing)) {
            $fetched = $this->dataSource->getIcd10Descriptions($missing);
            foreach ($missing as $code) {
                $value = $fetched[$code] ?? null;
                Cache::put($this->icd10CacheKey($code), $value, self::ICD10_CACHE_TTL);
                $result[$code] = $value;
            }
        }

        return $result;
    }

    /** Flush all cached results for a given period. */
    public function flushCache(int $year, int $month): void
    {
        // Cache::tags not available on all drivers — use specific keys from known KPI codes
        $kpiCodes = \DB::table('rdu_indicators')->pluck('code');
        foreach ($kpiCodes as $code) {
            foreach (['num', 'den', 'detail'] as $type) {
                Cache::forget($this->cacheKey($code, $year, $month, $type));
            }
        }
    }

    private function cacheKey(string $kpiCode, int $year, int $month, string $type): string
    {
        return "rdu.kpi.{$kpiCode}.{$year}.{$month}.{$type}";
    }

    private function cacheKeyBetween(string $kpiCode, string $startDate, string $endDate, string $type): string
    {
        return "rdu.kpi.{$kpiCode}.between.{$startDate}.{$endDate}.{$type}";
    }

    private function icd10CacheKey(string $code): string
    {
        return "rdu.icd10.{$code}";
    }
}
