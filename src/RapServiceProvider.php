<?php

namespace Chaihao\Rap;


use Illuminate\Support\ServiceProvider;
use Chaihao\Rap\Exception\Handler;
use Chaihao\Rap\Foundation\Kernel;

class RapServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 加载迁移文件
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // 发布迁移文件
        $this->publishes([
            __DIR__ . '/Database/Migrations' => database_path('migrations')
        ], 'rap-migrations');

        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/rap.php' => config_path('rap.php'),
            __DIR__ . '/../config/permission.php' => config_path('permission.php'),
            __DIR__ . '/../config/jwt.php' => config_path('jwt.php'),
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

        // 加载语言文件
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'rap');

        // 发布语言文件
        $this->publishes([
            __DIR__ . '/resources/lang' => resource_path('lang'),
        ], 'lang');

        // 加载路由
        $this->loadRoutesFrom(__DIR__ . '/../routes/rap-api.php');

        if ($this->app->runningInConsole()) {
            $this->commands($this->app['rap.console.kernel']->all());
        }
    }

    public function register()
    {
        // 正确的写法
        $this->mergeConfigFrom(
            __DIR__ . '/../config/rap.php',
            'rap'
        );
        $this->app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            Handler::class
        );
        $this->app->singleton('rap.console.kernel', function ($app) {
            return new Kernel($app, $app['events']);
        });
    }
}
