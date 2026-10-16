<?php

namespace App\Queries\Kpi;

/**
 * How one KPI is counted, in words a pharmacist can check (CONTEXT.md: วิธีนับ).
 * Built by the KPI's own query class from the same constants its SQL uses, so the drug and
 * diagnosis codes shown can never drift from the codes actually counted.
 */
final class CountingMethod
{
    /** Conditions most KPI queries share — one wording everywhere. */
    public const EXCLUDES_TEST_PATIENTS = 'ไม่นับผู้ป่วยทดสอบ (ชื่อมีคำว่า "ทดสอบ")';
    public const DISPENSED_ONLY = 'นับเฉพาะยาที่จ่ายจริง (จำนวน > 0) ไม่นับรายการคืนยา/ยกเลิก';
    public const ANY_DIAGNOSIS_POSITION = 'รหัสวินิจฉัยอยู่ตำแหน่งใดก็ได้ (โรคหลักหรือโรคร่วม)';
    public const OPD_ONLY = 'ผู้ป่วยนอกเท่านั้น ไม่นับวิสิตที่รับไว้นอนโรงพยาบาล';
    public const ALL_VISITS = 'นับทุกวิสิตตามวันที่มารับบริการ รวมวิสิตที่ต่อมารับไว้นอนโรงพยาบาล';

    /**
     * @param  string  $unit  what one denominator case is, e.g. "วิสิตผู้ป่วยนอก (VN)"
     * @param  list<string>  $denominator  who is in the denominator
     * @param  list<string>  $numerator  who of them is in the numerator
     * @param  list<string>  $conditions  rules that apply to both (OPD/IPD, dispensed only, test patients…)
     * @param  string  $drugLabel  the drug group's name, e.g. "ยาปฏิชีวนะ"
     * @param  array<string, string|null>  $drugs  `drugitems.icode` => note (e.g. the active ingredient), in SQL order
     * @param  list<array{label: string, codes: list<string>, match: 'exact'|'prefix', note?: string}>  $diagnoses
     *         diagnosis code groups; `prefix` = every code starting with it (J45 = J45, J450, J451…)
     * @param  array<string, string>  $excludedDiagnoses  ICD-10 code deliberately NOT counted => why
     * @param  string  $doctor  whose rate the per-doctor table shows, and which visit stands for a patient
     * @param  string  $source  where the definition came from (the reference SQL file and who wrote it)
     * @param  string|null  $agreedOn  date (Y-m-d) the definition was agreed, when recorded
     */
    public function __construct(
        public readonly string $unit,
        public readonly array $denominator,
        public readonly array $numerator,
        public readonly array $conditions,
        public readonly string $drugLabel,
        public readonly array $drugs,
        public readonly array $diagnoses,
        public readonly array $excludedDiagnoses,
        public readonly string $doctor,
        public readonly string $source,
        public readonly ?string $agreedOn = null,
    ) {}
}
