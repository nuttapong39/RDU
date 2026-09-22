<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesDateParams;
use App\Models\AuditLog;
use App\Models\RduIndicator;
use App\Services\DoctorAliasService;
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
        private DoctorAliasService $aliasService,
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

    /** Doctors with fewer visits than this are excluded from red/green judgement in the doctor-rate table (too few cases for the rate to be meaningful) — still listed, just ungraded. */
    private const DOCTOR_RATE_MIN_VISITS = 5;

    /** KPI codes with real detail data rich enough to drive the deep-dive charts + quarterly-summary zone. */
    private const DEEP_DIVE_KPI_CODES = ['KPI01', 'KPI03', 'KPI04', 'KPI05'];

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
            'doctorRateMinVisits'   => self::DOCTOR_RATE_MIN_VISITS,
            'doctorRateThreshold'   => $this->doctorRateThreshold($indicator),
            'hasDeepDive'       => $hasDeepDive,
        ]);
    }

    /**
     * Per-attending-doctor antibiotic rate: (visits where that doctor's patient got an antibiotic)
     * / (total visits attended by that doctor) — a true rate, not just a prescription count, since
     * the attending-doctor field is populated on every visit (ATB or not), unlike the prescribing
     * `doctor` field which is only set when an antibiotic was actually dispensed. Every doctor is
     * shown; visits with no attending-doctor code are bucketed separately as "ไม่ระบุแพทย์" (returned
     * as `unknown`, never assigned an alias or graded pass/fail — not a real, accountable person).
     *
     * Row shape isn't uniform across KPIs: KPI01 exposes both `attending_doctor`/`attending_doctor_code`
     * (attending) and `doctor` (prescribing) as distinct keys; KPI03/KPI04/KPI05 only carry one doctor
     * column, keyed `doctor`/`doctor_code`, which already means attending for those three. Checking
     * the `attending_doctor_code` key first and falling back to `doctor_code` resolves correctly for
     * all four without per-KPI branching.
     *
     * Doctor identity is masked to a stable per-doctor alias (e.g. "DR-01") in this table — see
     * docs/adr/0002-doctor-identity-masked-in-web-ui-only.md. The alias is resolved from the RAW
     * `*_doctor_code` (HOSxP `doctor.code`), not the display string, so it survives name corrections.
     *
     * Grading (`red`) is direction-aware and intentionally strict (no "near miss" tier — see Q4 of
     * the KPI05-doctor-rate-table grilling session): KPI03's numerator is inverted (target_direction=
     * 'higher' — it counts visits that did NOT get an antibiotic, the opposite of KPI01/04/05), so a
     * doctor prescribing to 100% of cases (worst outcome) must never grade as green. `rate` shown to
     * the user always stays the plain ATB-received rate regardless of direction; only the red/green
     * call is direction-corrected. Doctors below DOCTOR_RATE_MIN_VISITS are returned with `graded =
     * false` — too few cases for the rate to mean anything, shown but never colored.
     *
     * @return array<int, array{code: string, real_name: ?string, total: int, atb: int, rate: float, red: bool, graded: bool, unknown: bool}>
     */
    private function aggregateDoctorRate(Collection $allDetails, RduIndicator $indicator): array
    {
        $groups = [];

        foreach ($allDetails as $row) {
            $doctorRaw = array_key_exists('attending_doctor', $row) ? $row['attending_doctor'] : ($row['doctor'] ?? null);
            $codeRaw   = array_key_exists('attending_doctor_code', $row) ? $row['attending_doctor_code'] : ($row['doctor_code'] ?? null);
            $doctor    = trim((string) $doctorRaw);
            $key       = $doctor !== '' ? trim((string) $codeRaw) : '__unknown__';

            $groups[$key] ??= ['real_name' => $doctor, 'total' => 0, 'atb' => 0];
            $groups[$key]['total']++;
            if (trim((string) ($row['drugitems_name'] ?? '')) !== '') {
                $groups[$key]['atb']++;
            }
        }

        $items = [];
        foreach ($groups as $key => $g) {
            $isUnknown = $key === '__unknown__';
            $rate      = $g['total'] > 0 ? round($g['atb'] / $g['total'] * 100, 1) : 0.0;
            $graded    = ! $isUnknown && $g['total'] >= self::DOCTOR_RATE_MIN_VISITS;

            $items[] = [
                'code'      => $isUnknown ? null : $this->aliasService->aliasFor($key),
                'real_name' => $isUnknown ? null : $g['real_name'],
                'total'     => $g['total'],
                'atb'       => $g['atb'],
                'rate'      => $rate,
                'red'       => $graded && $this->isDoctorRateRed($rate, $indicator),
                'graded'    => $graded,
                'unknown'   => $isUnknown,
            ];
        }

        // Worst offenders first — sorted by the plain ATB-received rate regardless of KPI direction,
        // since a high raw rate always means "this doctor prescribes antibiotics more" across all
        // four KPIs (KPI03's inverted target only affects the red/green call, not what "worse" means
        // here). The unknown-doctor bucket, if any, always sorts last regardless of its rate.
        usort($items, fn ($a, $b) => $a['unknown'] <=> $b['unknown'] ?: $b['rate'] <=> $a['rate']);

        return $items;
    }

    /**
     * Direction-aware, strict pass/fail for the doctor-rate table's 2-color scheme (no "near miss"
     * tier, unlike RduCalculationService::getStatus()) — exactly at the target counts as red, matching
     * the hospital-provided reference dashboard this table was modeled on.
     */
    private function isDoctorRateRed(float $rate, RduIndicator $indicator): bool
    {
        return $indicator->target_direction === 'higher'
            ? (100 - $rate) <= $indicator->target_value
            : $rate >= $indicator->target_value;
    }

    /**
     * Where the red/green boundary sits on the plain ATB-received rate scale shown in the doctor-rate
     * table (bar width, dashed tick, legend text) — must match isDoctorRateRed()'s actual math, not
     * just echo $indicator->target_value directly. For 'lower' KPIs the two coincide (target_value IS
     * the rate threshold), but for 'higher' KPIs (KPI03) target_value is an avoidance-rate target
     * (e.g. 80%), while the flip point on the RECEIVED-rate scale this table plots is 100-target_value
     * (e.g. 20%) — using target_value directly there would draw the tick 60 points from where the
     * color actually changes.
     */
    private function doctorRateThreshold(RduIndicator $indicator): float
    {
        return $indicator->target_direction === 'higher'
            ? round(100 - $indicator->target_value, 2)
            : $indicator->target_value;
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
