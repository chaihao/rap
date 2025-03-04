<?php

namespace Chaihao\Rap\Http\Controllers;

use Chaihao\Rap\Services\Sys\OperationLogService;
use Chaihao\Rap\Http\Controllers\BaseController;
use Chaihao\Rap\Models\Sys\OperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

class OperationLogController extends BaseController
{
    /**
     * 初始化服务和模型
     */
    protected function initServiceAndModel(): void
    {
        $this->service = App::make(OperationLogService::class);
        $this->model = App::make(OperationLog::class);
    }
}
