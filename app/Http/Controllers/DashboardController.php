<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesDateParams;
use App\Models\AuditLog;
use App\Models\RduIndicator;
use App\Services\RduCalculationService;
use App\Services\RduQueryService;
use Illuminate\Http\Request;
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

    public function kpi(Request $request, string $code): View
    {
        $year  = $this->sanitizeYear((int) $request->input('year',  $this->calcService->currentFiscalYear()));
        $month = $this->sanitizeMonth((int) $request->input('month', 0));

        $indicator = RduIndicator::where('code', strtoupper($code))->firstOrFail();
        $item      = $this->calcService->getOrCalculate($indicator, $year, $month);
        $trendData = $this->calcService->getTrend($indicator, $year);
        $details   = $this->queryService->getDetails($indicator->code, $year, $month);

        // Sidebar — batch load to avoid N+1
        $allIndicators = RduIndicator::active()->ordered()->get();
        $kpiData       = $this->calcService->getOrCalculateMany($allIndicators, $year, $month);
        $kpiByCategory = $kpiData->groupBy(fn ($i) => $i['indicator']->category);

        return view('dashboard.kpi', [
            'item'          => $item,
            'trendData'     => $trendData,
            'details'       => $details,
            'year'          => $year,
            'month'         => $month,
            'kpiByCategory' => $kpiByCategory,
            'categories'    => $this->categories(),
        ]);
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
