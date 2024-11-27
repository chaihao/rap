<?php

namespace Chaihao\Rap\Exception;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * 注册异常处理回调函数
     */
    public function register(): void
    {
        $this->reportable(function (ApiException $e) {
            // 可以在这里添加日志记录逻辑
        });

        $this->renderable(function (ApiException $e) {
            return $e->render();
        });
    }
}
