<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesDateParams;
use App\Models\AuditLog;
use App\Models\RduIndicator;
use App\Services\RduCalculationService;
use App\Services\RduQueryService;
use App\Support\SimplePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use SanitizesDateParams;

    public function __construct(
        private RduCalculationService $calcService,
        private RduQueryService $queryService,
    ) {}

    public function index(Request $request): View
    {
        $year  = $this->sanitizeYear((int) $request->input('year',  $this->calcService->currentFiscalYear()));
        $month = $this->sanitizeMonth((int) $request->input('month', 0));

        $indicators = RduIndicator::active()->ordered()->get();
        $kpiData    = $this->calcService->getOrCalculateMany($indicators, $year, $month);

        $summary       = $this->calcService->summarize($kpiData->pluck('status')->all());
        $kpiByCategory = $kpiData->groupBy(fn ($i) => $i['indicator']->category);

        $chartData = $kpiData->map(fn ($i) => [
            'label'     => $i['indicator']->code,
            'name'      => mb_substr($i['indicator']->name, 0, 20),
            'pct'       => $i['percentage'],
            'target'    => $i['indicator']->target_value,
            'direction' => $i['indicator']->target_direction,
            'status'    => $i['status'],
        ])->values()->toArray();

        $categoryStats = $kpiByCategory->map(fn ($items) => [
            'count'   => count($items),
            'avg_pct' => collect($items)->pluck('percentage')->filter(fn ($v) => $v !== null)->avg(),
            'pass'    => collect($items)->where('status', 'pass')->count(),
            'warning' => collect($items)->where('status', 'warning')->count(),
            'fail'    => collect($items)->where('status', 'fail')->count(),
            'na'      => collect($items)->where('status', 'na')->count(),
        ]);

        $watchlist = $kpiData
            ->filter(fn ($i) => in_array($i['status'], ['fail', 'warning']))
            ->sortBy(function ($i) {
                if ($i['percentage'] === null) return 0;
                $diff = $i['indicator']->target_value - $i['percentage'];
                return $i['indicator']->target_direction === 'higher' ? -$diff : $diff;
            })
            ->take(5)
            ->values();

        $recentActivity = AuditLog::with('user')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('dashboard.index', [
            'kpiData'        => $kpiData,
            'kpiByCategory'  => $kpiByCategory,
            'categories'     => $this->categories(),
            'summary'        => $summary,
            'year'           => $year,
            'month'          => $month,
            'yearOptions'    => $this->calcService->fiscalYearOptions(4),
            'monthOptions'   => RduCalculationService::thaiMonths(),
            'chartData'      => $chartData,
            'categoryStats'  => $categoryStats,
            'watchlist'      => $watchlist,
            'recentActivity' => $recentActivity,
        ]);
    }

    private const DETAILS_PER_PAGE = 50;

    /** Cap on distinct bars shown in the drug-usage chart before folding the rest into "อื่นๆ". */
    private const DRUG_USAGE_TOP_N = 12;

    /** Ranked ICD-10 codes shown before folding the rest into an "อื่นๆ" donut slice. */
    private const ICD10_TOP_N = 10;

    /** KPI codes with real detail data rich enough to drive the deep-dive charts + quarterly-summary zone. */
    private const DEEP_DIVE_KPI_CODES = ['KPI01', 'KPI03', 'KPI04'];

    /** Detail-row keys a table column header may sort by (matches the associative keys every KpiQueryInterface::getDetails() row uses). */
    private const SORTABLE_COLUMNS = ['HN', 'VN', 'AN', 'vstdate', 'dchdate', 'dx', 'fullname', 'CC', 'HPI', 'drugitems_name', 'doctor', 'attending_doctor'];

    public function kpi(Request $request, string $code): View
    {
        $year    = $this->sanitizeYear((int) $request->input('year', $this->calcService->currentFiscalYear()));
        $quarter = $this->sanitizeQuarter($request->input('quarter'));
        $month   = $quarter ? 0 : $this->sanitizeMonth((int) $request->input('month', 0));
        $page    = max(1, (int) $request->input('page', 1));

        $search      = trim((string) $request->input('q', ''));
        $colDateFrom = $request->input('col_date_from') ?: null;
        $colDateTo   = $request->input('col_date_to') ?: null;
        $sortCol     = in_array($request->input('sort'), self::SORTABLE_COLUMNS, true) ? $request->input('sort') : null;
        $sortDir     = $request->input('dir') === 'desc' ? 'desc' : 'asc';

        $indicator = RduIndicator::where('code', strtoupper($code))->firstOrFail();
        $trendData = $this->calcService->getTrend($indicator, $year);

        $allIndicators = RduIndicator::active()->ordered()->get();

        if ($quarter) {
            $item        = $this->calcService->getOrCalculateQuarter($indicator, $year, $quarter);
            $allDetails  = $this->queryService->getDetailsForQuarter($indicator->code, $year, $quarter);
            $kpiData     = $this->calcService->getOrCalculateManyForQuarter($allIndicators, $year, $quarter);
        } else {
            $item        = $this->calcService->getOrCalculate($indicator, $year, $month);
            $allDetails  = $this->queryService->getDetails($indicator->code, $year, $month);
            $kpiData     = $this->calcService->getOrCalculateMany($allIndicators, $year, $month);
        }

        // Feature charts (drug usage / ICD-10 ranking / doctor rate) and the quarterly-summary zone
        // are only meaningful for KPIs with real detail data — the rest are still query stubs.
        $hasDeepDive  = in_array($indicator->code, self::DEEP_DIVE_KPI_CODES, true);
        $drugUsage    = $hasDeepDive ? $this->aggregateDrugUsage($allDetails) : [];
        $icd10Ranking = $hasDeepDive ? $this->aggregateIcd10Ranking($allDetails) : ['items' => [], 'total' => 0];
        $doctorRate   = $hasDeepDive ? $this->aggregateDoctorRate($allDetails, $indicator) : [];

        $detailsGrandTotal = $allDetails->count();
        $filteredDetails   = $this->filterAndSortDetails($allDetails, $search, $colDateFrom, $colDateTo, $sortCol, $sortDir);
        $pagination        = SimplePaginator::paginate($filteredDetails, $page, self::DETAILS_PER_PAGE);

        $kpiByCategory = $kpiData->groupBy(fn ($i) => $i['indicator']->category);

        // Per-quarter breakdown for this fiscal year — drives the 4 hero cards and the quarterly
        // pie chart (always all 4 quarters of $year, regardless of the page's own month/quarter filter).
        $quarterlyBreakdown = collect([1, 2, 3, 4])->map(function (int $q) use ($indicator, $year) {
            $result = $this->calcService->getOrCalculateQuarter($indicator, $year, $q);
            return [
                'quarter'     => $q,
                'numerator'   => $result['numerator'],
                'denominator' => $result['denominator'],
                'percentage'  => $result['percentage'],
                'status'      => $result['status'],
            ];
        });

        return view('dashboard.kpi', [
            'item'               => $item,
            'quarterlyBreakdown' => $quarterlyBreakdown,
            'trendData'         => $trendData,
            'details'           => $pagination['data'],
            'detailsTotal'      => $pagination['total'],
            'detailsGrandTotal' => $detailsGrandTotal,
            'detailsPage'       => $pagination['page'],
            'detailsLastPage'   => $pagination['lastPage'],
            'detailsPerPage'    => $pagination['perPage'],
            'search'            => $search,
            'colDateFrom'       => $colDateFrom,
            'colDateTo'         => $colDateTo,
            'sortCol'           => $sortCol,
            'sortDir'           => $sortDir,
            'year'              => $year,
            'month'             => $month,
            'quarter'           => $quarter,
            'kpiByCategory'     => $kpiByCategory,
            'categories'        => $this->categories(),
            'yearOptions'       => $this->calcService->fiscalYearOptions(4),
            'monthOptions'      => RduCalculationService::thaiMonths(),
            'drugUsage'         => $drugUsage,
            'icd10Ranking'      => $icd10Ranking,
            'doctorRate'        => $doctorRate,
            'hasDeepDive'       => $hasDeepDive,
        ]);
    }

    /**
     * Per-attending-doctor antibiotic rate: (visits where that doctor's patient got an antibiotic)
     * / (total visits attended by that doctor) — a true rate, not just a prescription count, since
     * the attending-doctor field is populated on every visit (ATB or not), unlike the prescribing
     * `doctor` field which is only set when an antibiotic was actually dispensed. Every doctor is
     * shown (no minimum-case threshold), each carrying its own case count so noisy small-n bars are
     * visible rather than hidden. Visits with no attending-doctor code are bucketed separately as
     * "ไม่ระบุแพทย์" and never colored pass/fail (not a real, accountable person).
     *
     * Row shape isn't uniform across KPIs: KPI01 has both `attending_doctor` (attending) and
     * `doctor` (prescribing) as distinct keys; KPI03/KPI04 only carry one doctor column, keyed
     * `doctor`, which already means attending for those two. Checking `attending_doctor` first and
     * falling back to `doctor` resolves correctly for all three without per-KPI branching.
     *
     * The bar-height metric (`atb`, ATB-received count) always means the same thing across KPIs.
     * The pass/fail COLOR does not follow it directly, because KPI03's numerator is inverted
     * (target_direction='higher' — it counts visits that did NOT get an antibiotic, the opposite of
     * KPI01/KPI04). Coloring straight off the ATB-received rate would flip KPI03's meaning: a doctor
     * prescribing to 100% of cases (the worst outcome) would read as "pass" (100% >= 80% target).
     * `$statusRate` corrects for this — for a 'higher' direction it's the no-antibiotic rate instead
     * — while `rate` (shown to the user in the tooltip) always stays the plain ATB-received rate.
     *
     * @return array<int, array{name: string, total: int, atb: int, rate: float, status: ?string, unknown: bool}>
     */
    private function aggregateDoctorRate(Collection $allDetails, RduIndicator $indicator): array
    {
        $groups = [];

        foreach ($allDetails as $row) {
            $doctorRaw = array_key_exists('attending_doctor', $row) ? $row['attending_doctor'] : ($row['doctor'] ?? null);
            $doctor    = trim((string) $doctorRaw);
            $key       = $doctor !== '' ? $doctor : '__unknown__';

            $groups[$key] ??= ['total' => 0, 'atb' => 0];
            $groups[$key]['total']++;
            if (trim((string) ($row['drugitems_name'] ?? '')) !== '') {
                $groups[$key]['atb']++;
            }
        }

        $items = [];
        foreach ($groups as $key => $g) {
            $isUnknown  = $key === '__unknown__';
            $rate       = $g['total'] > 0 ? round($g['atb'] / $g['total'] * 100, 1) : 0.0;
            $statusRate = $indicator->target_direction === 'higher' ? round(100 - $rate, 1) : $rate;

            $items[] = [
                'name'    => $isUnknown ? 'ไม่ระบุแพทย์' : $key,
                'total'   => $g['total'],
                'atb'     => $g['atb'],
                'rate'    => $rate,
                'status'  => $isUnknown ? null : $this->calcService->getStatus($statusRate, $indicator->target_value, $indicator->target_direction),
                'unknown' => $isUnknown,
            ];
        }

        // Sorted by case COUNT (the chart's primary bar-height metric), not rate — a doctor with
        // n=1 and a 100% rate would otherwise sort above a doctor with n=50 and a 60% rate, which
        // is exactly the small-n distortion this metric was changed to avoid.
        usort($items, fn ($a, $b) => $b['atb'] <=> $a['atb']);

        return $items;
    }

    /**
     * Tallies ICD-10 diagnosis codes across the period's detail rows (dx is a comma-joined list of
     * every wound-qualifying code recorded per visit — already scoped to the KPI's own diagnosis
     * whitelist, not incidental comorbidities). Returns the top N by share of total occurrences
     * plus the sum of the remainder, and resolves human-readable descriptions only for the codes
     * actually shown (RduQueryService::getIcd10Descriptions is cached per-code, so repeat calls
     * across periods are cheap).
     *
     * @return array{items: array<int, array{code: ?string, name: ?string, tname: ?string, count: int, percent: float}>, total: int}
     */
    private function aggregateIcd10Ranking(Collection $allDetails): array
    {
        $counts = [];

        foreach ($allDetails as $row) {
            $raw = trim((string) ($row['dx'] ?? ''));
            if ($raw === '') {
                continue;
            }
            foreach (explode(',', $raw) as $code) {
                $code = trim($code);
                if ($code === '') {
                    continue;
                }
                $counts[$code] = ($counts[$code] ?? 0) + 1;
            }
        }

        $total = array_sum($counts);
        if ($total === 0) {
            return ['items' => [], 'total' => 0];
        }

        arsort($counts);
        $top        = array_slice($counts, 0, self::ICD10_TOP_N, true);
        $otherCount = $total - array_sum($top);

        $descriptions = $this->queryService->getIcd10Descriptions(array_keys($top));

        $items = [];
        foreach ($top as $code => $count) {
            $desc = $descriptions[$code] ?? null;
            $items[] = [
                'code'    => $code,
                'name'    => $desc['name'] ?? null,
                'tname'   => $desc['tname'] ?? null,
                'count'   => $count,
                'percent' => round($count / $total * 100, 1),
            ];
        }
        if ($otherCount > 0) {
            $items[] = [
                'code'    => null,
                'name'    => null,
                'tname'   => 'อื่นๆ',
                'count'   => $otherCount,
                'percent' => round($otherCount / $total * 100, 1),
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Tallies antibiotic drug names across the period's detail rows (drugitems_name is a
     * semicolon-joined list of every distinct drug dispensed in that visit, empty for non-ATB
     * visits) — grouped on the raw full string (name+strength+units), not a parsed generic name,
     * since cardinality is low in practice (confirmed ~7 distinct values per quarter). Returns the
     * top N by share of total occurrences, folding the remainder into an "อื่นๆ" bucket.
     *
     * @return array<int, array{name: string, count: int, percent: float}>
     */
    private function aggregateDrugUsage(Collection $allDetails): array
    {
        $counts = [];

        foreach ($allDetails as $row) {
            $raw = trim((string) ($row['drugitems_name'] ?? ''));
            if ($raw === '') {
                continue;
            }
            foreach (explode(';', $raw) as $drug) {
                $drug = trim($drug);
                if ($drug === '') {
                    continue;
                }
                $counts[$drug] = ($counts[$drug] ?? 0) + 1;
            }
        }

        $total = array_sum($counts);
        if ($total === 0) {
            return [];
        }

        arsort($counts);
        $top        = array_slice($counts, 0, self::DRUG_USAGE_TOP_N, true);
        $otherCount = $total - array_sum($top);

        $result = [];
        foreach ($top as $name => $count) {
            $result[] = ['name' => $name, 'count' => $count, 'percent' => round($count / $total * 100, 1)];
        }
        if ($otherCount > 0) {
            $result[] = ['name' => 'อื่นๆ', 'count' => $otherCount, 'percent' => round($otherCount / $total * 100, 1)];
        }

        return $result;
    }

    /**
     * Search + column date-filter + sort — all applied in PHP against the already-fetched
     * (and already 1-hour cached by RduQueryService) full Collection, so this works against the
     * *entire* matching dataset (not just the current page) without any new HOSxP query/cache key.
     */
    private function filterAndSortDetails(
        Collection $details,
        string $search,
        ?string $colDateFrom,
        ?string $colDateTo,
        ?string $sortCol,
        string $sortDir,
    ): Collection {
        // Row shape varies by KPI (KPI01 uses VN/vstdate for an OPD visit, KPI03 uses AN/dchdate for
        // an inpatient admission) — fall back across both so search/date-filter work for either.
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $details = $details->filter(fn ($row) =>
                str_contains(mb_strtolower((string) ($row['HN'] ?? '')), $needle)
                || str_contains(mb_strtolower((string) ($row['VN'] ?? $row['AN'] ?? '')), $needle)
                || str_contains(mb_strtolower((string) ($row['fullname'] ?? '')), $needle)
            )->values();
        }

        if ($colDateFrom || $colDateTo) {
            $details = $details->filter(function ($row) use ($colDateFrom, $colDateTo) {
                $date = (string) ($row['vstdate'] ?? $row['dchdate'] ?? '');
                if ($colDateFrom && $date < $colDateFrom) return false;
                if ($colDateTo && $date > $colDateTo) return false;
                return true;
            })->values();
        }

        if ($sortCol !== null) {
            $details = $sortDir === 'desc'
                ? $details->sortByDesc(fn ($row) => $row[$sortCol])->values()
                : $details->sortBy(fn ($row) => $row[$sortCol])->values();
        }

        return $details;
    }

    private function categories(): array
    {
        return [
            'Antibiotic'     => 'ยาปฏิชีวนะ',
            'DrugQuality'    => 'คุณภาพยา',
            'PatientSafety'  => 'ความปลอดภัย',
            'ChronicDisease' => 'โรคเรื้อรัง',
        ];
    }
}
