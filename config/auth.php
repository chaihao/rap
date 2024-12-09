<?php

return [
    'guards' => [
        config('rap.api.guard', 'api') => [
            'driver' => 'jwt',
            'provider' =>  config('rap.api.guard', 'api'),
        ],
    ],

    'providers' => [
        config('rap.api.guard', 'api') => [
            'driver' => 'eloquent',
            'model' => config('rap.auth.staff.model', \Chaihao\Rap\Models\Auth\Staff::class),
        ],
    ],

    'passwords' => [
        config('rap.api.guard', 'api') => [
            'provider' =>  config('rap.api.guard', 'api'),
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
];
