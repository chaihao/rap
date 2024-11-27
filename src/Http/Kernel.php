<?php

namespace Chaihao\Rap\Foundation\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * 全局中间件
     */
    protected $middleware = [
        \Chaihao\Rap\Http\Middleware\BaseMiddleware::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \Chaihao\Rap\Http\Middleware\Upgrade::class,
    ];

    /**
     * 中间件组
     */
    protected $middlewareGroups = [
        'web' => [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'check.auth',
            'permission',
            'throttle:api',
            'cors',
            'request.response.logger',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * 路由中间件
     */
    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'check.auth' => \Chaihao\Rap\Http\Middleware\CheckAuth::class,
        'permission' => \Chaihao\Rap\Http\Middleware\PermissionMiddleware::class,
        'cors' => \Chaihao\Rap\Http\Middleware\Cors::class,
        'request.response.logger' => \Chaihao\Rap\Http\Middleware\RequestResponseLogger::class,
    ];
}
