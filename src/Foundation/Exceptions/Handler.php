<?php

namespace Chaihao\Rap\Foundation\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
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
}
