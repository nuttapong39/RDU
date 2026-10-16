<?php

namespace App\Http\Controllers;

use App\Models\RduIndicator;
use App\Services\CountingMethodService;
use Illuminate\View\View;

/** One page explaining how every KPI is counted, for the pharmacists to review together (CONTEXT.md: วิธีนับ). */
class CountingMethodController extends Controller
{
    public function __construct(private CountingMethodService $countingMethods) {}

    public function index(): View
    {
        $indicators = RduIndicator::active()->ordered()->get();

        return view('dashboard.counting-methods', [
            'entries' => $indicators->map(fn (RduIndicator $ind) => [
                'indicator' => $ind,
                'cm'        => $this->countingMethods->describe($ind),
            ]),
        ]);
    }
}
