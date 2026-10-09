<?php

return [
    // Usernames (matching HOSxP officer_login_name) allowed to open /admin/settings/*
    'settings_admins' => array_values(array_filter(array_map(
        fn ($u) => strtolower(trim($u)),
        explode(',', (string) env('SETTINGS_ADMIN_USERNAMES', 'nuttapong'))
    ))),

    // KPIs whose HOSxP query is implemented (not a stub). These always use HOSxP; every other KPI
    // falls back to its HDC whole-year figure when one is linked (see HdcDataService::MAPPING).
    'hosxp_kpis' => ['KPI01', 'KPI03', 'KPI04', 'KPI05', 'KPI12'],

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
    ],
    'kpi_drug_group' => [
        'KPI12' => 'ras',
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
