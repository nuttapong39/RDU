<?php

return [
    // Usernames (matching HOSxP officer_login_name) allowed to open /admin/settings/*
    'settings_admins' => array_values(array_filter(array_map(
        fn ($u) => strtolower(trim($u)),
        explode(',', (string) env('SETTINGS_ADMIN_USERNAMES', 'nuttapong'))
    ))),

    // KPIs whose HOSxP query is implemented (not a stub). These always use HOSxP; every other KPI
    // falls back to its HDC whole-year figure when one is linked (see HdcDataService::MAPPING).
    'hosxp_kpis' => ['KPI01', 'KPI03', 'KPI04', 'KPI05', 'KPI07', 'KPI08', 'KPI09', 'KPI10', 'KPI11', 'KPI12', 'KPI13'],

    // The drug each KPI judges (CONTEXT.md: กลุ่มยาของตัวชี้วัด) and the wording the KPI page uses for it.
    // KPIs not listed in `kpi_drug_group` use `antibiotic`. Read through RduIndicator::drugGroup().
    'drug_groups' => [
        'antibiotic' => [
            'name'         => 'ยาปฏิชีวนะ',
            'short'        => 'ATB',
            'received'     => 'ได้ยาปฏิชีวนะ',
            'not_received' => 'ไม่ได้ยาปฏิชีวนะ',
            'cost'         => 'มูลค่า ATB (บาท)',
            'doctor_rate'  => 'อัตราการสั่งยาปฏิชีวนะรายแพทย์ผู้ตรวจ',
            'usage'        => 'สัดส่วนยาปฏิชีวนะที่สั่งจ่าย',
        ],
        // Every KPI12 visit already got at least one RAS drug, so the "not counted" side is "1 ชนิด", not "ไม่ได้ยา".
        'ras' => [
            'name'         => 'ยากลุ่ม RAS blockade',
            'short'        => 'RAS ≥2 ชนิด',
            'received'     => 'ได้ RAS ≥ 2 ชนิด',
            'not_received' => 'ได้ RAS 1 ชนิด',
            'cost'         => 'มูลค่ายา RAS (บาท)',
            'doctor_rate'  => 'อัตราการสั่ง RAS blockade ≥ 2 ชนิดรายแพทย์ผู้ตรวจ',
            'usage'        => 'สัดส่วนยา RAS ที่สั่งจ่าย (visit ที่ได้ ≥ 2 ชนิด)',
        ],
        // KPI08 is "higher is better": getting the drug is the good outcome.
        'ics' => [
            'name'         => 'ยา Inhaled Corticosteroid (ICS)',
            'short'        => 'ICS',
            'received'     => 'ได้ ICS',
            'not_received' => 'ไม่ได้ ICS',
            'cost'         => 'มูลค่ายา ICS (บาท)',
            'doctor_rate'  => 'อัตราการสั่ง ICS รายแพทย์ผู้ตรวจ',
            'usage'        => 'สัดส่วนยา ICS ที่สั่งจ่าย',
        ],
        // KPI07 counts drug items (icodes), so the wording says "รายการ", not "ชนิด" (CONTEXT.md: NSAIDs ซ้ำซ้อน).
        'nsaid' => [
            'name'         => 'ยากลุ่ม NSAIDs',
            'short'        => 'NSAIDs ≥2 รายการ',
            'received'     => 'ได้ NSAIDs ≥ 2 รายการ',
            'not_received' => 'ได้ NSAIDs 1 รายการ',
            'cost'         => 'มูลค่ายา NSAIDs (บาท)',
            'doctor_rate'  => 'อัตราการสั่ง NSAIDs ซ้ำซ้อนรายแพทย์ผู้ตรวจ',
            'usage'        => 'สัดส่วนยา NSAIDs ที่สั่งจ่าย (visit ที่ได้ ≥ 2 รายการ)',
        ],
        // KPI09: same NSAID list as KPI07, but the numerator is getting any NSAID at all (CONTEXT.md:
        // ผู้ป่วยโรคไตเรื้อรังระดับ 3 ขึ้นไปที่ได้ NSAIDs).
        'nsaid_ckd' => [
            'name'         => 'ยากลุ่ม NSAIDs',
            'short'        => 'NSAIDs',
            'received'     => 'ได้ NSAIDs',
            'not_received' => 'ไม่ได้ NSAIDs',
            'cost'         => 'มูลค่ายา NSAIDs (บาท)',
            'doctor_rate'  => 'อัตราการสั่ง NSAIDs ในผู้ป่วยโรคไตเรื้อรังรายแพทย์ผู้ตรวจ',
            'usage'        => 'สัดส่วนยา NSAIDs ที่สั่งจ่าย',
        ],
        // KPI10 is "higher is better": getting metformin is the good outcome (CONTEXT.md: ผู้ป่วยเบาหวานชนิดที่ 2
        // ที่ใช้ Metformin โดยไม่มีข้อห้ามใช้).
        'metformin' => [
            'name'         => 'ยา Metformin',
            'short'        => 'Metformin',
            'received'     => 'ได้ Metformin',
            'not_received' => 'ไม่ได้ Metformin',
            'cost'         => 'มูลค่ายา Metformin (บาท)',
            'doctor_rate'  => 'อัตราการสั่ง Metformin ในผู้ป่วยเบาหวานชนิดที่ 2 รายแพทย์ผู้ตรวจ',
            'usage'        => 'สัดส่วนยา Metformin ที่สั่งจ่าย',
        ],
        // KPI11: Cetirizine syrup and tablet (CONTEXT.md: เด็กติดเชื้อทางเดินหายใจที่ได้ยาแก้แพ้ชนิด non-sedating).
        'antihistamine' => [
            'name'         => 'ยาแก้แพ้ชนิด non-sedating',
            'short'        => 'non-sedating',
            'received'     => 'ได้ยาแก้แพ้ non-sedating',
            'not_received' => 'ไม่ได้ยาแก้แพ้ non-sedating',
            'cost'         => 'มูลค่ายาแก้แพ้ non-sedating (บาท)',
            'doctor_rate'  => 'อัตราการสั่งยาแก้แพ้ชนิด non-sedating ในเด็กติดเชื้อทางเดินหายใจรายแพทย์ผู้ตรวจ',
            'usage'        => 'สัดส่วนยาแก้แพ้ชนิด non-sedating ที่สั่งจ่าย',
        ],
        // KPI13: Diazepam tablets and injection (CONTEXT.md: ผู้สูงอายุที่ได้ยากลุ่ม Long-acting Benzodiazepine).
        'long_acting_bzd' => [
            'name'         => 'ยากลุ่ม Long-acting Benzodiazepine',
            'short'        => 'Long-acting BZD',
            'received'     => 'ได้ Long-acting BZD',
            'not_received' => 'ไม่ได้ Long-acting BZD',
            'cost'         => 'มูลค่ายา Long-acting BZD (บาท)',
            'doctor_rate'  => 'อัตราการสั่งยากลุ่ม Long-acting Benzodiazepine ในผู้สูงอายุรายแพทย์ผู้ตรวจ',
            'usage'        => 'สัดส่วนยา Long-acting BZD ที่สั่งจ่าย',
        ],
    ],
    'kpi_drug_group' => [
        'KPI07' => 'nsaid',
        'KPI08' => 'ics',
        'KPI09' => 'nsaid_ckd',
        'KPI10' => 'metformin',
        'KPI11' => 'antihistamine',
        'KPI12' => 'ras',
        'KPI13' => 'long_acting_bzd',
    ],

    // KPIs whose numerator is the cases that did NOT get the KPI's drug (KPI03: deliveries without an
    // antibiotic). Every other KPI's numerator is the cases that got it. Read through
    // RduIndicator::numeratorIsDrugReceived().
    'numerator_without_drug' => ['KPI03'],

    // What one denominator case is (CONTEXT.md: ผู้ป่วยโรคหืดที่ได้ ICS). `additive` = a period's count is
    // the sum of its months' counts; false (patients seen in several months) means a quarter or a
    // year-to-date figure must be counted over its whole date range. KPIs not listed in `kpi_count_unit`
    // count visits. Read through RduIndicator::countUnit().
    'count_units' => [
        'visit'   => ['name' => 'visit',   'title' => 'VISIT',   'counter' => 'ครั้ง', 'additive' => true],
        'patient' => ['name' => 'ผู้ป่วย', 'title' => 'ผู้ป่วย', 'counter' => 'ราย',  'additive' => false],
    ],
    'kpi_count_unit' => [
        'KPI08' => 'patient',
        'KPI09' => 'patient',
        'KPI10' => 'patient',
    ],

    // MOPH Open Data (HDC) — comparison modal, and the stand-in result for KPIs listed above as not yet on HOSxP
    'hdc' => [
        'base_url'  => env('HDC_BASE_URL', 'https://opendata.moph.go.th/api'),
        'hospcode'  => (string) env('HDC_HOSPCODE', '11179'),
        'province'  => (string) env('HDC_PROVINCE', '55'),
        'cache_ttl' => (int) env('HDC_CACHE_TTL', 21600), // seconds; HDC processes once a day
        'timeout'   => (int) env('HDC_TIMEOUT', 15),
    ],
];
