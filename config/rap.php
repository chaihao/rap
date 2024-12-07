<?php

return [
    'name' => 'Rap',
    'log_action_name' => [],
    'api' => [
        'prefix' => env('RAP_API_PREFIX', 'api'),
        'guard' => env('RAP_API_GUARD', 'api'),
    ],
    'namespace' => [
        'controller' => [
            'version' => env('RAP_CONTROLLER_VERSION', 'V1\\'),
        ],
    ],
    'create_services' => [
        'model' => env('RAP_CREATE_SERVICES_MODEL', true), // 创建service时，同时创建model
        'controller' => env('RAP_CREATE_SERVICES_CONTROLLER', true), // 创建service时，同时创建controller
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
