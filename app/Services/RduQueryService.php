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
    private const REFERENCE_CACHE_TTL = 86400; // 24 hours — static reference data, safe to cache much longer

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

    // ---- Arbitrary date ranges — a quarter or a year-to-date span is counted over its whole range,
    // never summed from months: a KPI counted in patients (KPI08) sees the same person in several months.

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

    public function getNumeratorForQuarter(string $kpiCode, int $year, int $quarter): int
    {
        return $this->getNumeratorBetween($kpiCode, ...FiscalDateRange::quarterToGregorian($year, $quarter));
    }

    public function getDenominatorForQuarter(string $kpiCode, int $year, int $quarter): int
    {
        return $this->getDenominatorBetween($kpiCode, ...FiscalDateRange::quarterToGregorian($year, $quarter));
    }

    /** Case-level detail rows of a fiscal quarter, queried over the quarter's whole date range — not capped. */
    public function getDetailsForQuarter(string $kpiCode, int $year, int $quarter): Collection
    {
        return $this->getDetailsBetween($kpiCode, ...FiscalDateRange::quarterToGregorian($year, $quarter));
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
        return $this->cachedReference('icd10', $codes, fn (array $missing) => $this->dataSource->getIcd10Descriptions($missing));
    }

    /**
     * Drug names for the given `drugitems.icode`s — [icode => ['name' => …, 'strength' => …, 'units' => …]].
     * Cached per-code like getIcd10Descriptions (CONTEXT.md: วิธีนับ shows these beside each icode).
     */
    public function getDrugDescriptions(array $icodes): array
    {
        return $this->cachedReference('drug', $icodes, fn (array $missing) => $this->dataSource->getDrugDescriptions($missing));
    }

    /**
     * The hospital's drug list for the settings page (CONTEXT.md: ชุดรหัสยา) — `drugitems` catalogue columns only.
     * Cached an hour: it is reference data and the page loads it once.
     */
    public function drugCatalog(): array
    {
        return Cache::remember('rdu.drug-catalog', 3600, fn () => $this->dataSource->getDrugCatalog());
    }

    /** Lab items matching a search (for the eGFR lab item), at most 30. */
    public function searchLabItems(string $term): array
    {
        $term = trim($term);

        return mb_strlen($term) < 2 ? [] : $this->dataSource->searchLabItems($term, 30);
    }

    /**
     * Per-code cache for static HOSxP reference data. Only names found are cached: a code with no
     * name (or a data source with none yet, e.g. before HOSxP is configured) is looked up again next time.
     *
     * @param  callable(list<string>): array<string, mixed>  $fetch
     */
    private function cachedReference(string $kind, array $codes, callable $fetch): array
    {
        $codes = array_values(array_unique(array_filter($codes, fn ($c) => $c !== null && $c !== '')));
        if (empty($codes)) {
            return [];
        }

        $result  = [];
        $missing = [];

        foreach ($codes as $code) {
            $cached = Cache::get($this->referenceCacheKey($kind, $code), '__miss__');
            if ($cached === '__miss__') {
                $missing[] = $code;
            } else {
                $result[$code] = $cached;
            }
        }

        if (!empty($missing)) {
            $fetched = $fetch($missing);
            foreach ($missing as $code) {
                $value = $fetched[$code] ?? null;
                if ($value !== null) {
                    Cache::put($this->referenceCacheKey($kind, $code), $value, self::REFERENCE_CACHE_TTL);
                }
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

    // The sets' versions are in the key so that saving a drug code set stops the old cached counts being used (ADR 0004).
    private function cacheKey(string $kpiCode, int $year, int $month, string $type): string
    {
        return "rdu.kpi.{$kpiCode}.v{$this->setVersion($kpiCode)}.{$year}.{$month}.{$type}";
    }

    private function cacheKeyBetween(string $kpiCode, string $startDate, string $endDate, string $type): string
    {
        return "rdu.kpi.{$kpiCode}.v{$this->setVersion($kpiCode)}.between.{$startDate}.{$endDate}.{$type}";
    }

    private function setVersion(string $kpiCode): string
    {
        return app(DrugCodeSetService::class)->cacheVersion($kpiCode);
    }

    private function referenceCacheKey(string $kind, string $code): string
    {
        return "rdu.{$kind}.{$code}";
    }
}
