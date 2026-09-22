<?php

namespace App\Http\Controllers;

use App\Exports\KpiExport;
use App\Http\Controllers\Concerns\SanitizesDateParams;
use App\Models\RduIndicator;
use App\Services\RduCalculationService;
use App\Services\RduQueryService;
use App\Support\FiscalDateRange;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KpiExportController extends Controller
{
    use SanitizesDateParams;

    /** Custom date-range exports may not span more than one fiscal year. */
    private const MAX_CUSTOM_RANGE_DAYS = 366;

    /**
     * DomPDF's table-layout algorithm scales very poorly with row count — this is a render-TIME
     * limit, not just memory (measured on this table's 9 columns incl. long free-text CC/HPI
     * cells: 431 rows ≈ 13.5s, 831 rows ≈ 38s, 1,465 rows exceeded the 60s PHP execution limit
     * even after bumping memory). Kept low enough to stay a "click and it downloads" experience.
     * Excel/CSV have no such ceiling — they're the recommended path for anything larger.
     */
    private const PDF_ROW_LIMIT = 500;

    public function __construct(
        private RduCalculationService $calcService,
        private RduQueryService $queryService,
    ) {}

    public function csv(Request $request, string $code): StreamedResponse
    {
        $indicator = RduIndicator::where('code', strtoupper($code))->firstOrFail();
        $ctx = $this->resolveContext($request);
        [$item, $details] = $this->load($indicator, $ctx);

        $filename = $this->filename($indicator->code, $ctx, 'csv');

        return response()->streamDownload(function () use ($item, $details, $ctx) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");

            fputcsv($out, ['ตัวชี้วัด', $item['indicator']->code . ' — ' . $item['indicator']->name]);
            fputcsv($out, ['ช่วงเวลา', $ctx['periodLabel']]);
            fputcsv($out, ['ตัวเศษ', $item['numerator']]);
            fputcsv($out, ['ตัวส่วน', $item['denominator']]);
            fputcsv($out, ['ผลลัพธ์', $item['percentage'] !== null ? $item['percentage'] . '%' : 'N/A']);
            fputcsv($out, ['เป้าหมาย', $item['indicator']->targetLabel()]);
            fputcsv($out, []);

            if ($details->isNotEmpty()) {
                fputcsv($out, array_keys($details->first()));
                foreach ($details as $row) {
                    fputcsv($out, array_values($row));
                }
            } else {
                fputcsv($out, ['ไม่มีข้อมูลรายกรณี']);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function excel(Request $request, string $code)
    {
        $indicator = RduIndicator::where('code', strtoupper($code))->firstOrFail();
        $ctx = $this->resolveContext($request);
        [$item, $details] = $this->load($indicator, $ctx);

        $summary = [
            'numerator'   => $item['numerator'],
            'denominator' => $item['denominator'],
            'percentage'  => $item['percentage'],
            'target'      => $item['indicator']->targetLabel(),
            'status'      => $item['status'],
        ];

        $export = new KpiExport(
            summary:       $summary,
            details:       $details->toArray(),
            indicatorCode: $indicator->code,
            indicatorName: $indicator->name,
            periodLabel:   $ctx['periodLabel'],
        );

        return Excel::download($export, $this->filename($indicator->code, $ctx, 'xlsx'));
    }

    public function pdf(Request $request, string $code): Response
    {
        $indicator = RduIndicator::where('code', strtoupper($code))->firstOrFail();
        $ctx = $this->resolveContext($request);
        [$item, $details] = $this->load($indicator, $ctx);

        if ($details->count() > self::PDF_ROW_LIMIT) {
            throw ValidationException::withMessages([
                'date_to' => sprintf(
                    'ช่วงเวลานี้มีข้อมูล %s รายการ ซึ่งเกินกว่าที่ PDF จะสร้างได้ (สูงสุด %s รายการ) กรุณาใช้ Excel หรือ CSV แทน หรือเลือกช่วงเวลาที่แคบลง',
                    number_format($details->count()),
                    number_format(self::PDF_ROW_LIMIT)
                ),
            ]);
        }

        // DomPDF's layout engine needs considerably more headroom than typical requests for
        // large detail tables — bump for this request only (Laravel process, not the whole server).
        // The row-count check above is the real UX guard; this is just a defensive backstop.
        ini_set('memory_limit', '2048M');
        set_time_limit(120);

        $periodLabel = $ctx['periodLabel'];

        $pdf = Pdf::loadView('exports.kpi_pdf', compact('item', 'details', 'periodLabel'))
            ->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($indicator->code, $ctx, 'pdf'));
    }

    private function load(RduIndicator $indicator, array $ctx): array
    {
        if ($ctx['mode'] === 'between') {
            $num = $this->queryService->getNumeratorBetween($indicator->code, $ctx['dateFrom'], $ctx['dateTo']);
            $den = $this->queryService->getDenominatorBetween($indicator->code, $ctx['dateFrom'], $ctx['dateTo']);
            $pct = $den > 0 ? round($num / $den * 100, 1) : null;
            $item = [
                'indicator'   => $indicator,
                'numerator'   => $num,
                'denominator' => $den,
                'percentage'  => $pct,
                'status'      => $this->calcService->getStatus($pct, $indicator->target_value, $indicator->target_direction),
            ];
            $details = $this->queryService->getDetailsBetween($indicator->code, $ctx['dateFrom'], $ctx['dateTo']);

            return [$item, $details];
        }

        if ($ctx['quarter']) {
            $item    = $this->calcService->getOrCalculateQuarter($indicator, $ctx['year'], $ctx['quarter']);
            $details = $this->queryService->getDetailsForQuarter($indicator->code, $ctx['year'], $ctx['quarter']);
        } else {
            $item    = $this->calcService->getOrCalculate($indicator, $ctx['year'], $ctx['month']);
            $details = $this->queryService->getDetails($indicator->code, $ctx['year'], $ctx['month']);
        }

        return [$item, $details];
    }

    /**
     * Resolves the export period from the request into a single consistent shape used by every
     * export method. `date_from`/`date_to` (custom range, from the export modal) take precedence
     * over the fiscal year/month/quarter params (from the page's own filter toolbar).
     *
     * @return array{mode: string, year: ?int, month: int, quarter: ?int, dateFrom: ?string, dateTo: ?string, periodLabel: string}
     */
    private function resolveContext(Request $request): array
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        if ($dateFrom && $dateTo) {
            $request->validate([
                'date_from' => ['date', 'before_or_equal:date_to'],
                'date_to'   => ['date', 'after_or_equal:date_from'],
            ]);

            if (Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) > self::MAX_CUSTOM_RANGE_DAYS) {
                throw ValidationException::withMessages([
                    'date_to' => 'ช่วงวันที่ต้องไม่เกิน 1 ปีงบประมาณ (366 วัน)',
                ]);
            }

            return [
                'mode'        => 'between',
                'year'        => null,
                'month'       => 0,
                'quarter'     => null,
                'dateFrom'    => $dateFrom,
                'dateTo'      => $dateTo,
                'periodLabel' => "{$dateFrom} ถึง {$dateTo}",
            ];
        }

        $year    = $this->sanitizeYear((int) $request->input('year', $this->calcService->currentFiscalYear()));
        $quarter = $this->sanitizeQuarter($request->input('quarter'));
        $month   = $quarter ? 0 : $this->sanitizeMonth((int) $request->input('month', 0));

        $periodLabel = $quarter
            ? FiscalDateRange::quarterLabel($quarter).' ปีงบ '.$year
            : ($month === 0 ? 'ทั้งปีงบ '.$year : (RduCalculationService::thaiMonths()[$month] ?? $month).' ปีงบ '.$year);

        return [
            'mode'        => 'fiscal',
            'year'        => $year,
            'month'       => $month,
            'quarter'     => $quarter,
            'dateFrom'    => null,
            'dateTo'      => null,
            'periodLabel' => $periodLabel,
        ];
    }

    private function filename(string $code, array $ctx, string $ext): string
    {
        if ($ctx['mode'] === 'between') {
            return strtolower("{$code}-{$ctx['dateFrom']}-to-{$ctx['dateTo']}.{$ext}");
        }

        $suffix = $ctx['quarter'] ? sprintf('-q%d', $ctx['quarter']) : ($ctx['month'] > 0 ? sprintf('-%02d', $ctx['month']) : '');

        return strtolower("{$code}-{$ctx['year']}{$suffix}.{$ext}");
    }
}
