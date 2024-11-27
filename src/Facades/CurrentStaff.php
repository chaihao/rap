<?php

namespace Chaihao\Rap\Facades;

use Illuminate\Support\Facades\Facade;

class CurrentStaff extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'current_staff';
    }
}
