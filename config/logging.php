<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SQL日志配置
    |--------------------------------------------------------------------------
    |
    | enable_sql_logging: 是否启用SQL日志记录
    | sql_log_level: SQL日志记录级别
    | sql_log_days: SQL日志保留天数
    |
    */
    'enable_sql_logging' => env('RAP_ENABLE_SQL_LOGGING', false),
    'sql_log_level' => env('RAP_SQL_LOG_LEVEL', 'debug'),
    'sql_log_days' => env('RAP_SQL_LOG_DAYS', 14),

    'channels' => [
        // 自定义SQL日志通道   
        'daily_sql' => [
            'driver' => 'daily',
            'path' => storage_path('logs/sql/sql.log'),
            'level' => env('RAP_SQL_LOG_LEVEL', 'debug'),
            'days' => env('RAP_SQL_LOG_DAYS', 14),
            'permission' => 0755,
        ],
        // 自定义Error日志通道   
        'api_errors' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api_errors.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'permission' => 0755,
        ],
    ],
];
