<?php

namespace App\Http\Controllers;

use App\Models\RduIndicator;
use App\Services\DrugCodeSetService;
use App\Support\DrugCodeSetRegistry;
use Illuminate\View\View;

/** The hospital's drug code sets (CONTEXT.md: ชุดรหัสยา) — every signed-in user may look; editing is for the ผู้ตั้งค่ารหัสยา. */
class DrugCodeSetController extends Controller
{
    public function __construct(private DrugCodeSetService $sets) {}

    public function index(): View
    {
        $indicators = RduIndicator::active()->ordered()->get()
            ->filter(fn (RduIndicator $i) => DrugCodeSetRegistry::forKpi($i->code) !== []);

        $kpis = $indicators->map(fn (RduIndicator $i) => [
            'indicator'  => $i,
            'configured' => $this->sets->isKpiConfigured($i->code),
            'sets'       => array_map(fn (string $key) => [
                'key'        => $key,
                'label'      => DrugCodeSetRegistry::get($key)['label'],
                'count'      => count($this->sets->items($key)),
                'sharedWith' => array_values(array_diff(DrugCodeSetRegistry::get($key)['kpis'], [$i->code])),
            ], DrugCodeSetRegistry::forKpi($i->code)),
        ]);

        return view('dashboard.drug-code-sets', ['kpis' => $kpis]);
    }
}
