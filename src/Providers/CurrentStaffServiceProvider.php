<?php

namespace Chaihao\Rap\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Chaihao\Rap\Services\CurrentStaffService;

class CurrentStaffServiceProvider extends ServiceProvider
{
    public function register()
    {
        // 添加调试信息
        Log::info('Registering CurrentStaffServiceProvider');

        $this->app->singleton('current_staff', function () {
            return new CurrentStaffService();
        });
    }
}