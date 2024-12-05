<?php

namespace Chaihao\Rap\Exception;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
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

        $this->renderable(function (Throwable $e) {
            // 处理验证异常
            if ($e instanceof ValidationException) {
                return ApiException::validationError(
                    '验证失败',
                    $e->errors()
                )->render();
            }

            // 处理 API 异常
            if ($e instanceof ApiException) {
                return $e->render();
            }

            // 其他异常转换为 API 异常
            return ApiException::from($e)->render();
        });
    }
}
