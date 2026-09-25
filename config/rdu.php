<?php

return [
    // Usernames (matching HOSxP officer_login_name) allowed to open /admin/settings/*
    'settings_admins' => array_values(array_filter(array_map(
        fn ($u) => strtolower(trim($u)),
        explode(',', (string) env('SETTINGS_ADMIN_USERNAMES', 'nuttapong'))
    ))),

    // KPIs whose HOSxP query is implemented (not a stub). These always use HOSxP; every other KPI
    // falls back to its HDC whole-year figure when one is linked (see HdcDataService::MAPPING).
    'hosxp_kpis' => ['KPI01', 'KPI03', 'KPI04', 'KPI05'],

    // MOPH Open Data (HDC) — comparison modal, and the stand-in result for KPIs listed above as not yet on HOSxP
    'hdc' => [
        'base_url'  => env('HDC_BASE_URL', 'https://opendata.moph.go.th/api'),
        'hospcode'  => (string) env('HDC_HOSPCODE', '11179'),
        'province'  => (string) env('HDC_PROVINCE', '55'),
        'cache_ttl' => (int) env('HDC_CACHE_TTL', 21600), // seconds; HDC processes once a day
        'timeout'   => (int) env('HDC_TIMEOUT', 15),
    ],
];
