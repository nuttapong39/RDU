<?php

namespace App\Http\Controllers;

use App\Exports\KpiExport;
use App\Http\Controllers\Concerns\SanitizesDateParams;
use App\Models\RduIndicator;
use App\Services\RduCalculationService;
use App\Services\RduQueryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KpiExportController extends Controller
{
    use SanitizesDateParams;

    public function __construct(
        private RduCalculationService $calcService,
        private RduQueryService $queryService,
    ) {}

    public function csv(Request $request, string $code): StreamedResponse
    {
        $indicator = RduIndicator::where('code', strtoupper($code))->firstOrFail();
        [$item, $details] = $this->load($request, $indicator);

        $filename = $this->filename($indicator->code, $request, 'csv');

        return response()->streamDownload(function () use ($item, $details) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");

            fputcsv($out, ['ตัวชี้วัด', $item['indicator']->code . ' — ' . $item['indicator']->name]);
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
        [$item, $details] = $this->load($request, $indicator);

        $year  = $this->sanitizeYear((int) $request->input('year', $this->calcService->currentFiscalYear()));
        $month = $this->sanitizeMonth((int) $request->input('month', 0));

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
            year:          $year,
            month:         $month,
        );

        return Excel::download($export, $this->filename($indicator->code, $request, 'xlsx'));
    }

    public function pdf(Request $request, string $code): Response
    {
        $indicator = RduIndicator::where('code', strtoupper($code))->firstOrFail();
        [$item, $details] = $this->load($request, $indicator);

        $year  = $this->sanitizeYear((int) $request->input('year', $this->calcService->currentFiscalYear()));
        $month = $this->sanitizeMonth((int) $request->input('month', 0));

        $pdf = Pdf::loadView('exports.kpi_pdf', compact('item', 'details', 'year', 'month'))
            ->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($indicator->code, $request, 'pdf'));
    }

    private function load(Request $request, RduIndicator $indicator): array
    {
        $year    = $this->sanitizeYear((int) $request->input('year',  $this->calcService->currentFiscalYear()));
        $month   = $this->sanitizeMonth((int) $request->input('month', 0));
        $item    = $this->calcService->getOrCalculate($indicator, $year, $month);
        $details = $this->queryService->getDetails($indicator->code, $year, $month);
        return [$item, $details];
    }

    private function filename(string $code, Request $request, string $ext): string
    {
        $year  = $this->sanitizeYear((int) $request->input('year', $this->calcService->currentFiscalYear()));
        $month = $this->sanitizeMonth((int) $request->input('month', 0));
        $m     = $month > 0 ? sprintf('-%02d', $month) : '';
        return strtolower("{$code}-{$year}{$m}.{$ext}");
    }
}
