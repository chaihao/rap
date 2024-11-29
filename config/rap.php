<?php

return [
    'name' => 'Rap',
    'log_action_name' => [],
    'api' => [
        'prefix' => env('RAP_API_PREFIX', 'api'),
        'guard' => env('RAP_API_GUARD', 'api'),
    ],
    'models' => [
        'staff' => [
            'class' => \Chaihao\Rap\Models\Auth\Staff::class,
            'table' => 'staff',
            'fillable' => [
                "id",
                "phone",
                "password",
                "name",
                "email",
                "salt",
                "avatar",
                "ip",
                "last_login_at",
                "sex",
                "is_super",
                "remark",
                "status"
            ],
        ],
    ],
];
