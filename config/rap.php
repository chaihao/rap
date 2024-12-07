<?php

return [
    'name' => 'Rap',
    // 需要记录操作日志的请求方式和action名称类型
    'log_action_name' => [
        // 需要记录操作日志的请求方式(若为空，则不记录操作日志)
        'methods' => ['POST', 'PUT', 'DELETE'],
        // 需要记录操作日志的action名称类型(若为空，默认action判断验证通过)
        'types' => ['create', 'update', 'delete', 'add', 'edit', 'remove'],
    ],
    'api' => [
        'prefix' => env('RAP_API_PREFIX', 'api'), // api前缀
        'guard' => env('RAP_API_GUARD', 'api'), // 默认api守卫
    ],
    'namespace' => [
        'controller' => [
            'version' => env('RAP_CONTROLLER_VERSION', 'V1\\'), // 控制器版本
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
