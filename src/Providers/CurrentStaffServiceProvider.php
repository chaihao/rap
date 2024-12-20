<?php

namespace Chaihao\Rap\Providers;

use Illuminate\Support\ServiceProvider;
use Chaihao\Rap\Services\CurrentStaffService;

class CurrentStaffServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('current_staff', function () {
            return new CurrentStaffService();
        });
    }
}
