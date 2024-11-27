<?php

namespace Chaihao\Rap\Exception;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiException extends Exception
{
    // 定义常用错误码常量
    public const BAD_REQUEST = Response::HTTP_BAD_REQUEST;
    public const UNAUTHORIZED = Response::HTTP_UNAUTHORIZED;
    public const FORBIDDEN = Response::HTTP_FORBIDDEN;
    public const NOT_FOUND = Response::HTTP_NOT_FOUND;
    public const VALIDATION_ERROR = Response::HTTP_UNPROCESSABLE_ENTITY;
    public const SERVER_ERROR = Response::HTTP_INTERNAL_SERVER_ERROR;

    protected int $statusCode;
    protected mixed $errors = null;

    public function __construct(
        string $message = '操作失败',
        int $code = self::BAD_REQUEST,
        mixed $errors = null,
        ?int $statusCode = null
    ) {
        parent::__construct($message, $code);
        $this->statusCode = $statusCode ?? $code;
        $this->errors = $errors;
    }

    public function render(): JsonResponse
    {
        $response = [
            'success' => false,
            'code' => $this->code,
            'message' => $this->message,
        ];

        if (!is_null($this->errors)) {
            $response['errors'] = $this->errors;
        }

        if (config('app.debug')) {
            $response['debug'] = $this->getDebugInfo();
        }

        return response()->json($response, $this->statusCode);
    }

    /**
     * 获取调试信息
     */
    protected function getDebugInfo(): array
    {
        return [
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => collect($this->getTrace())
                ->map(
                    fn($trace) => collect($trace)
                        ->only(['file', 'line', 'function', 'class'])
                        ->toArray()
                )
                ->toArray()
        ];
    }

    /**
     * 创建验证错误异常
     */
    public static function validationError(string $message = '验证失败', mixed $errors = null): static
    {
        return new static($message, self::VALIDATION_ERROR, $errors);
    }

    /**
     * 创建未授权异常
     */
    public static function unauthorized(string $message = '未授权访问'): static
    {
        return new static($message, self::UNAUTHORIZED);
    }

    /**
     * 创建资源未找到异常
     */
    public static function notFound(string $message = '资源未找到'): static
    {
        return new static($message, self::NOT_FOUND);
    }

    /**
     * 创建禁止访问异常
     */
    public static function forbidden(string $message = '禁止访问'): static
    {
        return new static($message, self::FORBIDDEN);
    }
}
