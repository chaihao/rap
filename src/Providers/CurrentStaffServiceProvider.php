<?php

namespace Chaihao\Rap\Providers;

use Chaihao\Rap\Services\CurrentStaffService;
use Illuminate\Support\ServiceProvider;

class CurrentStaffServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 单例模式
        $this->app->singleton('current_staff', function () {
            return new CurrentStaffService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
