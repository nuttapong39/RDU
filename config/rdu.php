<?php

return [
    // Usernames (matching HOSxP officer_login_name) allowed to open /admin/settings/*
    'settings_admins' => array_values(array_filter(array_map(
        fn ($u) => strtolower(trim($u)),
        explode(',', (string) env('SETTINGS_ADMIN_USERNAMES', 'nuttapong'))
    ))),
];
