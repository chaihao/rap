<?php

namespace Chaihao\Rap\Foundation;

use Chaihao\Rap\Console\Commands\AddAddress;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Chaihao\Rap\Console\Commands\MakeModel;
use Chaihao\Rap\Console\Commands\MakeController;
use Chaihao\Rap\Console\Commands\MakeServices;
use Chaihao\Rap\Console\Commands\MakeRepositories;

class Kernel
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Dispatcher
     */
    protected $events;

    /**
     * 命令列表
     */
    protected $commands = [
        MakeModel::class,
        MakeController::class,
        MakeServices::class,
        MakeRepositories::class,
        AddAddress::class,
    ];

    /**
     * @param Application $app
     * @param Dispatcher $events
     */
    public function __construct(Application $app, Dispatcher $events)
    {
        $this->app = $app;
        $this->events = $events;
    }

    /**
     * 获取所有命令
     *
     * @return array
     */
    public function all()
    {
        return $this->commands;
    }
}
