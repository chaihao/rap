<?php

namespace Chaihao\Rap;

use Illuminate\Routing\Router;
use Chaihao\Rap\Exception\Handler;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;

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

        // 注册自定义命令
        if ($this->app->runningInConsole()) {
            $kernel = new \Chaihao\Rap\Foundation\Kernel($this->app, $this->app['events']);
            $this->commands($kernel->all());
        }

        if ($this->app->environment('local')) {
            if (class_exists(IdeHelperServiceProvider::class)) {
                $this->app->register(IdeHelperServiceProvider::class);
            }
        }


        // 修改 auth 配置合并方式
        $this->app->booting(function () {
            $config = $this->app['config']->get('auth', []);
            $rapAuth = require __DIR__ . '/../config/auth.php';

            // 正确的合并顺序：保留两边的配置，但允许主项目配置覆盖组件配置
            $merged = array_merge_recursive([
                'defaults' => $config['defaults'] ?? [],
                'guards' => array_merge($rapAuth['guards'] ?? [], $config['guards'] ?? []),
                'providers' => array_merge($rapAuth['providers'] ?? [], $config['providers'] ?? []),
                'passwords' => array_merge($rapAuth['passwords'] ?? [], $config['passwords'] ?? []),
                'password_timeout' => $config['password_timeout'] ?? null,
            ]);
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
        $kernel->pushMiddleware(\Chaihao\Rap\Http\Middleware\BaseMiddleware::class);
        $kernel->pushMiddleware(\Chaihao\Rap\Http\Middleware\Cors::class);
        $kernel->pushMiddleware(\Chaihao\Rap\Http\Middleware\Upgrade::class);

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
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });
    }




    // 限流配置
    //     protected function configureRateLimiting(): void
    // {
    //     // ��义不同的限流规则
    //     RateLimiter::for('login', function ($request) {
    //         return Limit::perMinute(5)->by($request->ip());
    //     });

    //     RateLimiter::for('sensitive-api', function ($request) {
    //         return Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
    //     });
    // }

    //     // 登录接口限制每IP每分钟5次
    // Route::post('/login', 'AuthController@login')->middleware('throttle:login');

    // // 敏感接口限制每用户每分钟30次
    // Route::post('/sensitive-data', 'DataController@sensitive')->middleware('throttle:sensitive-api');

    // // 普通接口不做限制
    // Route::post('/normal-data', 'DataController@normal');


}
