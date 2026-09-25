<?php

namespace App\Http\Controllers;

use App\Exceptions\HdcUnavailableException;
use App\Http\Controllers\Concerns\SanitizesDateParams;
use App\Models\RduIndicator;
use App\Services\HdcComparisonService;
use App\Services\RduCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HdcComparisonController extends Controller
{
    use SanitizesDateParams;

    public function __construct(
        private HdcComparisonService $hdc,
        private RduCalculationService $calcService
    ) {}

    public function show(Request $request, string $code): JsonResponse
    {
        $indicator = RduIndicator::where('code', strtoupper($code))->firstOrFail();
        $year      = $this->sanitizeYear((int) $request->input('year', $this->calcService->currentFiscalYear()));

        try {
            return response()->json($this->hdc->compare($indicator, $year));
        } catch (HdcUnavailableException $e) {
            Log::warning('HDC comparison unavailable', ['kpi' => $indicator->code, 'year' => $year, 'reason' => $e->getMessage()]);

            return response()->json([
                'error'   => 'hdc_unavailable',
                'message' => 'ไม่สามารถดึงข้อมูลจาก HDC ได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง',
            ], 502);
        }
    }
}
