<?php

namespace Chaihao\Rap\Console\Commands;

use Chaihao\Rap\Services\Sys\PermissionService;
use Illuminate\Console\Command;

class TestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'rap:test-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle() {}
}
