<?php

namespace App\Services;

use App\DataSources\RduDataSourceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Single gateway for all HOSxP KPI queries.
 * Controllers must NEVER call RduDataSourceInterface or DB::connection('rdu') directly.
 */
class RduQueryService
{
    private const CACHE_TTL = 3600; // 1 hour

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

    /** Returns percentage (0–100), or null if denominator is 0. */
    public function getPercentage(string $kpiCode, int $year, int $month): ?float
    {
        $den = $this->getDenominator($kpiCode, $year, $month);
        if ($den === 0) {
            return null;
        }

        return round($this->getNumerator($kpiCode, $year, $month) / $den * 100, 2);
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
}
