<?php

namespace Chaihao\Rap\Http\Controllers;

use Chaihao\Rap\Services\Sys\OperationLogService;
use Chaihao\Rap\Http\Controllers\BaseController;
use Chaihao\Rap\Models\Sys\OperationLog;
use Illuminate\Http\JsonResponse;

class OperationLogController extends BaseController
{
    /**
     * 初始化服务和模型
     */
    protected function initServiceAndModel(): void
    {
        $this->service = app(OperationLogService::class);
        $this->model = app(OperationLog::class);
    }
}
