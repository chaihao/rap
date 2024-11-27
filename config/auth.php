<?php

return [
    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'staff',
        ],
    ],

    'providers' => [
        'staff' => [
            'driver' => 'eloquent',
            'model' => \Chaihao\Rap\Models\Auth\StaffModel::class,
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
