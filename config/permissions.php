<?php

return [
    'name' => 'Main',

    'permissions' => [
        'setting' => [ // this group is required if you want add any module setting, because the permissions are created under this group with the same name (setting)
            'manage-setting',
            'manage-theme-setting',
            'manage-notifications-setting',
            'manage-google-setting',
        ],
        'system' => [ // this group is required if you want add any module setting, because the permissions are created under this group with the same name (setting)
            'update-system',
        ],
        'audit' => [
            'view-audit-logs',
        ],
    ],
];
