<?php

namespace App\Services;

use App\Exceptions\HdcUnavailableException;
use App\Models\RduIndicator;

/**
 * Builds the "เทียบ HDC" comparison: this system's HOSxP fiscal-year result next to the MOPH Open Data
 * (HDC) figures for every hospital in the province. HDC access lives in HdcDataService (external public
 * API, not HOSxP, so it does not go through RduQueryService); the HOSxP side comes from
 * RduCalculationService, which uses RduQueryService.
 */
class HdcComparisonService
{
    public function __construct(
        private RduCalculationService $calcService,
        private HdcDataService $hdc,
        private FacilityProfileService $facility
    ) {}

    /** @throws HdcUnavailableException */
    public function compare(RduIndicator $indicator, int $fyYear): array
    {
        $hospcode = $this->facility->hospcode();

        if (! $this->hdc->isLinked($indicator->code)) {
            return ['linked' => false, 'kpi' => $indicator->code, 'year' => $fyYear, 'hospcode' => $hospcode];
        }

        // Opening the modal is a deliberate user action (and "ลองใหม่" retries), so try HDC even if a
        // dashboard request just found it down.
        $rows = collect($this->hdc->rows($indicator->code, $fyYear, honourOutage: false))->map(fn (array $r) => $this->figure($r['num'], $r['den'], $indicator) + [
            'hospcode'     => $r['hospcode'],
            'label'        => $this->label($r['hospcode'], $r['areacode']),
            'is_ours'      => $r['hospcode'] === $hospcode,
            'processed_at' => HdcDataService::formatProcessedAt($r['date_com']),
        ]);

        $ours  = $rows->firstWhere('is_ours', true);
        $hosxp = $this->hosxpFullYear($indicator, $fyYear);

        $totalNum = $rows->sum('num');
        $totalDen = $rows->sum('den');

        return [
            'linked'    => true,
            'kpi'       => $indicator->code,
            'year'      => $fyYear,
            'hospcode'  => $hospcode,
            'hdc_table' => $this->hdc->table($indicator->code),
            'ours'      => [
                'hosxp'   => $hosxp,
                'hdc'     => $ours,
                'diff_pp' => $ours && $hosxp['pct'] !== null && $ours['pct'] !== null
                    ? round($hosxp['pct'] - $ours['pct'], 1) : null,
            ],
            'province' => [
                'rows'      => $this->sortRows($rows->all(), $indicator->target_direction),
                'total_num' => $totalNum,
                'total_den' => $totalDen,
                'avg_pct'   => RduCalculationService::percentage($totalNum, $totalDen),
            ],
        ];
    }

    /**
     * Full fiscal year (all 12 months) from HOSxP — the same whole-year figure the KPI page headline shows.
     * A KPI whose HOSxP query is not implemented yet has no HOSxP side (its headline shows HDC instead).
     */
    private function hosxpFullYear(RduIndicator $indicator, int $fyYear): array
    {
        if (! $indicator->hasHosxpQuery()) {
            return $this->figure(0, 0, $indicator);
        }

        $year = $this->calcService->getOrCalculate($indicator, $fyYear, 0);

        return $this->figure((int) $year['numerator'], (int) $year['denominator'], $indicator);
    }

    private function figure(int $num, int $den, RduIndicator $indicator): array
    {
        $pct = RduCalculationService::percentage($num, $den);

        return [
            'num'    => $num,
            'den'    => $den,
            'pct'    => $pct,
            'status' => $this->calcService->getStatus($pct, (float) $indicator->target_value, $indicator->target_direction),
        ];
    }

    /** Best result first (lowest % for 'lower', highest for 'higher'); rows with no cases last. */
    private function sortRows(array $rows, string $direction): array
    {
        usort($rows, function ($a, $b) use ($direction) {
            if ($a['pct'] === null || $b['pct'] === null) {
                return ($a['pct'] === null) <=> ($b['pct'] === null);
            }

            return $direction === 'higher' ? $b['pct'] <=> $a['pct'] : $a['pct'] <=> $b['pct'];
        });

        return array_values($rows);
    }

    private function label(string $hospcode, string $areacode): string
    {
        $district = FacilityProfileService::districtName($areacode);

        return $district ? "อ.{$district} ({$hospcode})" : $hospcode;
    }
}
