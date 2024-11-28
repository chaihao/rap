<?php

return [
    'guards' => [
        config('rap.api.guard') => [
            'driver' => 'jwt',
            'provider' => 'staff',
        ],
    ],

    'providers' => [
        'staff' => [
            'driver' => 'eloquent',
            'model' => \Chaihao\Rap\Models\Auth\Staff::class,
        ],
    ],

    'passwords' => [
        'staff' => [
            'provider' => 'staff',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
];
