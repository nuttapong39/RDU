<?php

namespace App\Services;

use App\DataSources\HosxpRduDataSource;
use App\Models\RduIndicator;
use App\Queries\Kpi\CountingMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Puts together the "วิธีนับ" explanation of a KPI (CONTEXT.md: วิธีนับ): the KPI's own query class
 * describes how it counts (its codes come from the same constants as its SQL), and this adds the
 * drug and diagnosis names looked up in HOSxP through RduQueryService.
 */
class CountingMethodService
{
    /** Set after the first failed name lookup, so a page describing many KPIs waits on a dead HOSxP only once. */
    private bool $hosxpUnreachable = false;

    public function __construct(
        // Counting rules are code, not data: read from the query classes whatever data source is active.
        private HosxpRduDataSource $queries,
        private RduQueryService $queryService,
    ) {}

    /**
     * @return array{
     *     method: CountingMethod|null,
     *     drugs: list<array{icode: string, name: string|null, strength: string|null, units: string|null, note: string|null}>,
     *     diagnoses: list<array{label: string, match: string, note: string|null, codes: list<array{code: string, name: string|null}>}>,
     *     excluded: list<array{code: string, name: string|null, reason: string}>,
     *     agreedOn: string|null,
     *     namesAvailable: bool,
     * }
     * `method` is null for a KPI whose query is still a stub — its definition awaits the pharmacist.
     * `agreedOn` is the agreement date as a Thai (Buddhist-era) d/m/Y string.
     */
    public function describe(RduIndicator $indicator): array
    {
        $method = $this->queries->countingMethodFor($indicator->code);
        if ($method === null) {
            return ['method' => null, 'drugs' => [], 'diagnoses' => [], 'excluded' => [], 'agreedOn' => null, 'namesAvailable' => true];
        }

        [$drugNames, $diagnosisNames] = $this->names(
            $indicator->code,
            array_keys($method->drugs),
            array_merge(array_keys($method->excludedDiagnoses), ...array_column($method->diagnoses, 'codes')),
        );

        $diagnosisName = fn (string $code) => ($diagnosisNames[$code]['tname'] ?? null) ?: ($diagnosisNames[$code]['name'] ?? null);

        $drugs = [];
        foreach ($method->drugs as $icode => $note) {
            $drugs[] = [
                'icode'    => (string) $icode,
                'name'     => $drugNames[$icode]['name'] ?? null,
                'strength' => $drugNames[$icode]['strength'] ?? null,
                'units'    => $drugNames[$icode]['units'] ?? null,
                'note'     => $note,
            ];
        }

        $diagnoses = array_map(fn (array $group) => [
            'label' => $group['label'],
            'match' => $group['match'],
            'note'  => $group['note'] ?? null,
            'codes' => array_map(fn (string $code) => ['code' => $code, 'name' => $diagnosisName($code)], $group['codes']),
        ], $method->diagnoses);

        $excluded = [];
        foreach ($method->excludedDiagnoses as $code => $reason) {
            $excluded[] = ['code' => $code, 'name' => $diagnosisName($code), 'reason' => $reason];
        }

        return [
            'method'         => $method,
            'drugs'          => $drugs,
            'diagnoses'      => $diagnoses,
            'excluded'       => $excluded,
            'agreedOn'       => $method->agreedOn ? $this->thaiDate($method->agreedOn) : null,
            'namesAvailable' => ! $this->hosxpUnreachable,
        ];
    }

    /**
     * Drug and diagnosis names. They are a convenience: if HOSxP can't be reached the codes are still shown.
     *
     * @return array{0: array, 1: array}
     */
    private function names(string $kpiCode, array $icodes, array $diagnosisCodes): array
    {
        if ($this->hosxpUnreachable) {
            return [[], []];
        }

        try {
            return [
                $this->queryService->getDrugDescriptions($icodes),
                $this->queryService->getIcd10Descriptions($diagnosisCodes),
            ];
        } catch (Throwable $e) {
            $this->hosxpUnreachable = true;
            Log::warning('Counting method: drug/diagnosis names unavailable', ['kpi' => $kpiCode, 'error' => get_class($e)]);

            return [[], []];
        }
    }

    private function thaiDate(string $date): string
    {
        $d = Carbon::parse($date);

        return $d->format('d/m/').($d->year + 543);
    }
}
