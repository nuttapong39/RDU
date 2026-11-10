<?php

namespace App\Support;

/**
 * The drug code sets (CONTEXT.md "ชุดรหัสยา") and which KPI reads which. This mapping is part of each KPI's
 * definition, so it lives in code: pharmacists edit what is inside a set, never which set a KPI uses (ADR 0004).
 * A new KPI that counts drugs must declare its sets here (docs/KPI-REFERENCE-IMPLEMENTATION.md).
 */
final class DrugCodeSetRegistry
{
    public const KIND_DRUG = 'drug';
    public const KIND_LAB  = 'lab';

    public const ATTR_INGREDIENT = 'ingredient';
    public const ATTR_FORM       = 'form';

    /**
     * key => label, kind, attribute (a per-code value every item must carry, or null), max items (or null), KPIs.
     *
     * @return array<string, array{label: string, kind: string, attribute: ?string, max: ?int, kpis: list<string>}>
     */
    public static function all(): array
    {
        return [
            'antibiotic' => [
                'label' => 'ยาปฏิชีวนะ', 'kind' => self::KIND_DRUG, 'attribute' => null, 'max' => null,
                'kpis' => ['KPI01', 'KPI03', 'KPI04', 'KPI05'],
            ],
            'antibiotic_fresh_wound' => [
                'label' => 'ยาปฏิชีวนะ (บาดแผลสด)', 'kind' => self::KIND_DRUG, 'attribute' => null, 'max' => null,
                'kpis' => ['KPI02'],
            ],
            'nsaid' => [
                'label' => 'ยากลุ่ม NSAIDs', 'kind' => self::KIND_DRUG, 'attribute' => null, 'max' => null,
                'kpis' => ['KPI07', 'KPI09'],
            ],
            'ics' => [
                'label' => 'ยา Inhaled Corticosteroid (ICS)', 'kind' => self::KIND_DRUG, 'attribute' => null, 'max' => null,
                'kpis' => ['KPI08'],
            ],
            'metformin' => [
                'label' => 'Metformin (ยาที่วัด)', 'kind' => self::KIND_DRUG, 'attribute' => null, 'max' => null,
                'kpis' => ['KPI10'],
            ],
            'glucose_lowering' => [
                'label' => 'ยาลดระดับน้ำตาล (ตัวหาร)', 'kind' => self::KIND_DRUG, 'attribute' => null, 'max' => null,
                'kpis' => ['KPI10'],
            ],
            'egfr_lab' => [
                'label' => 'รายการ lab eGFR', 'kind' => self::KIND_LAB, 'attribute' => null, 'max' => 1,
                'kpis' => ['KPI10'],
            ],
            'non_sedating_antihistamine' => [
                'label' => 'ยาแก้แพ้ non-sedating', 'kind' => self::KIND_DRUG, 'attribute' => self::ATTR_FORM, 'max' => null,
                'kpis' => ['KPI11'],
            ],
            'ras_blockade' => [
                'label' => 'ยากลุ่ม RAS blockade', 'kind' => self::KIND_DRUG, 'attribute' => self::ATTR_INGREDIENT, 'max' => null,
                'kpis' => ['KPI12'],
            ],
            'long_acting_bzd' => [
                'label' => 'Long-acting Benzodiazepine', 'kind' => self::KIND_DRUG, 'attribute' => self::ATTR_FORM, 'max' => null,
                'kpis' => ['KPI13'],
            ],
        ];
    }

    /** @return array{label: string, kind: string, attribute: ?string, max: ?int, kpis: list<string>} */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException("Unknown drug code set '{$key}'");
    }

    /** Set keys a KPI needs, in registry order. @return list<string> */
    public static function forKpi(string $kpiCode): array
    {
        return array_values(array_keys(array_filter(self::all(), fn (array $s) => in_array($kpiCode, $s['kpis'], true))));
    }

    /** KPI codes that need at least one set. @return list<string> */
    public static function kpiCodes(): array
    {
        $codes = array_unique(array_merge(...array_column(self::all(), 'kpis')));
        sort($codes);

        return array_values($codes);
    }
}
