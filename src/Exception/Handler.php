<?php

namespace Chaihao\Rap\Exception;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * 注册异常处理回调函数
     */
    public function register(): void
    {
        $this->reportable(function (ApiException $e) {
            // 可以在这里添加日志记录逻辑
            Log::error('Handler ApiException: ' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
        });

        $this->renderable(function (Throwable $e) {
            // 处理验证异常
            if ($e instanceof ValidationException) {
                return ApiException::validationError(
                    '验证失败',
                    $e->errors()
                )->render();
            }
            // 检测模型未找到异常
            if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
                return ApiException::notFound($e->getMessage())->render();
            }

            // 处理 API 异常
            if ($e instanceof ApiException) {
                return $e->render();
            }

            // 其他异常转换为 API 异常
            return ApiException::from($e)->render();
        });
    }

    /**
     * 确定是否应该报告异常
     */
    public function shouldReport(Throwable $e)
    {
        // 某些异常不需要记录日志
        if ($e instanceof ApiException && $e->getCode() < 500) {
            return false;
        }
        return parent::shouldReport($e);
    }

    /**
     * 自定义异常报告
     */
    public function report(Throwable $e)
    {
        // 可以在这里添加自定义的日志记录逻辑
        // if ($e instanceof ApiException) {
        Log::channel('api_errors')->error($e->getMessage(), [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'message' => $e->getMessage(),
        ]);
        // } else {
        //     // 其他异常
        //     parent::report($e);
        // }
    }
}
