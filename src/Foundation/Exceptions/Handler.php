<?php

namespace Chaihao\Rap\Foundation\Exceptions;

use Chaihao\Rap\Exception\ApiException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * 不需要报告的异常类型
     */
    protected $dontReport = [
        //
    ];

    /**
     * 验证异常时不需要加密的输入
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * 注册异常处理回调
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * 渲染异常为 HTTP 响应
     */
    public function render($request, Throwable $e)
    {
        // 处理验证异常
        if ($e instanceof ValidationException) {
            return ApiException::validationError('验证失败', $e->errors())->render();
        }

        // 处理 ApiException
        if ($e instanceof ApiException) {
            return $e->render();
        }

        // 其他异常处理
        if ($request->expectsJson()) {
            return ApiException::from($e)->render();
        }

        return parent::render($request, $e);
    }
}
