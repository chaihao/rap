<?php

namespace Chaihao\Rap;

use Illuminate\Routing\Router;
use Chaihao\Rap\Exception\Handler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Tymon\JWTAuth\Providers\Storage\Illuminate;

class RapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 合并 rap 配置
        $this->mergeConfigFrom(
            __DIR__ . '/../config/rap.php',
            'rap'
        );

        // 使用完整的命名空间引用
        $this->app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            Handler::class
        );

        // 注册jwt缓存
        $this->app->singleton(Illuminate::class, function () {
            // 从配置文件获取缓存驱动，默认使用redis
            $store = config('rap.jwt.cache_store', 'redis');
            return new Illuminate(Cache::store($store));
        });
        // 注册自定义命令
        if ($this->app->runningInConsole()) {
            $kernel = new \Chaihao\Rap\Foundation\Kernel($this->app, $this->app['events']);
            $this->commands($kernel->all());
        }

        // 修改 auth 配置合并方式
        $this->app->booting(function () {
            $config = $this->app['config']->get('auth', []);    
            $rapAuth = require __DIR__ . '/../config/auth.php';

            // 优化配置合并逻辑
            $merged = [
                'defaults' => $config['defaults'] ?? $rapAuth['defaults'] ?? [],
                'guards' => array_merge($rapAuth['guards'] ?? [], $config['guards'] ?? []),
                'providers' => array_merge($rapAuth['providers'] ?? [], $config['providers'] ?? []),
                'passwords' => array_merge($rapAuth['passwords'] ?? [], $config['passwords'] ?? []),
                'password_timeout' => $config['password_timeout'] ?? $rapAuth['password_timeout'] ?? null,
            ];

            $this->app['config']->set('auth', $merged);
        });
    }

    public function boot(): void
    {
        $router = $this->app['router'];

        // 注册中间件
        $this->registerMiddleware($router);

        // 配置 API 限流
        $this->configureRateLimiting();

        // 加载路由
        $this->loadRoutesFrom(__DIR__ . '/../routes/rap-api.php');

        // 加载迁移文件
        $this->loadMigrationsFrom(__DIR__ . '/Database/migrations');

        // 发布迁移文件
        $this->publishes([
            __DIR__ . '/Database/migrations' => database_path('migrations')
        ], 'rap-migrations');

        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/rap.php' => config_path('rap.php'), // 发布rap配置
            __DIR__ . '/../config/permission.php' => config_path('permission.php'), // 发布权限配置
            __DIR__ . '/../config/jwt.php' => config_path('jwt.php'), // 发布jwt配置
            __DIR__ . '/resources/lang' => resource_path('lang'), // 发布语言文件
        ], 'rap-config');

        // 可选: 如果需要分别发布，可以保留单独的标签
        $this->publishes([
            __DIR__ . '/../config/rap.php' => config_path('rap.php'),
        ], 'rap-config-core');

        $this->publishes([
            __DIR__ . '/../config/permission.php' => config_path('permission.php'),
        ], 'rap-config-permission');

        $this->publishes([
            __DIR__ . '/../config/jwt.php' => config_path('jwt.php'),
        ], 'rap-config-jwt');
    }

    protected function registerMiddleware(Router $router): void
    {
        // 注册全局中间件
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        // 使用数组批量注册全局中间件
        $globalMiddlewares = [
            \Chaihao\Rap\Http\Middleware\BaseMiddleware::class,
            \Chaihao\Rap\Http\Middleware\Cors::class,
            \Chaihao\Rap\Http\Middleware\Upgrade::class,
        ];

        foreach ($globalMiddlewares as $middleware) {
            $kernel->pushMiddleware($middleware);
        }

        // 注册路由中间件
        $router->aliasMiddleware('check.auth', \Chaihao\Rap\Http\Middleware\CheckAuth::class);
        $router->aliasMiddleware('permission', \Chaihao\Rap\Http\Middleware\PermissionMiddleware::class);
        $router->aliasMiddleware('request.response.logger', \Chaihao\Rap\Http\Middleware\RequestResponseLogger::class);

        // 注册中间件组
        $router->middlewareGroup('rap-api', [
            'check.auth',
            'permission',
            // 'throttle:api',
            'request.response.logger',
        ]);
    }

    // 添加这个新方法来配置限流
    protected function configureRateLimiting(): void
    {

        // 使用throttle:api 每分钟 60 次
        // throttle:30,1 每分钟30次
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });




        // 示例：在路由中使用登录限流
        // Route::post('/login', 'AuthController@login')->middleware('throttle:login');

        // 示例：在路由中使用敏感接口限流
        // Route::post('/sensitive-data', 'DataController@sensitive')->middleware('throttle:sensitive');

        // 定义敏感操作的限流器：每IP每分钟最多5次请求
        RateLimiter::for('sensitive', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // 定义登录接口的限流器：每IP每分钟最多3次请求
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(3)->by($request->ip());
        });
    }
}
