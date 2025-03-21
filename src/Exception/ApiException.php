<?php

namespace Chaihao\Rap\Exception;

use Exception;
use Throwable;
use RedisException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Client\ConnectionException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Predis\Connection\ConnectionException as PredisConnectionException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * @method static static from(\Throwable $e)
 * @method static static redisConnectionError(string $message = 'Redis连接失败')
 * @method static static mysqlConnectionError(string $message = 'MySQL连接失败')
 * @method static static invalidCredentials(string $message = '用户名或密码错误')
 * @method static static failed(string $message = '操作失败')
 * @method static static forbidden(string $message = '禁止访问')
 * @method static static notFound(string $message = '资源未找到')
 * @method static static unauthorized(string $message = '未授权访问')
 * @method static static validationError(string $message = '验证失败', mixed $errors = null)
 */
class ApiException extends Exception
{
    // HTTP 状态码常量定义
    // 请求错误
    public const BAD_REQUEST = Response::HTTP_BAD_REQUEST;           // 400
    // 未授权
    public const UNAUTHORIZED = Response::HTTP_UNAUTHORIZED;         // 401
    // 禁止访问
    public const FORBIDDEN = Response::HTTP_FORBIDDEN;              // 403
    // 未找到
    public const NOT_FOUND = Response::HTTP_NOT_FOUND;             // 404
    // 验证错误
    public const VALIDATION_ERROR = Response::HTTP_UNPROCESSABLE_ENTITY; // 422
    // 服务器错误
    public const SERVER_ERROR = Response::HTTP_INTERNAL_SERVER_ERROR;    // 500
    // 数据库连接错误
    public const DB_CONNECTION_ERROR = self::SERVER_ERROR;    // 500
    // Redis 连接错误
    public const REDIS_CONNECTION_ERROR = self::SERVER_ERROR; // 500

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
        // 仅在调试模式下返回调试信息
        if (config('app.debug')) {
            if (!is_null($this->errors)) {
                $response['debug']['errors'] = $this->errors;
            }
            $response['debug']['trace'] = $this->getDebugInfo();
        }
        // statusCode 为 http 状态码, 范围在 100 - 599 之间
        if ($this->statusCode < 100 || $this->statusCode > 599) {
            $this->statusCode = self::BAD_REQUEST;
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
                ->take(5)  // 只取前5条堆栈信息
                ->map(function ($trace) {
                    return [
                        'file' => isset($trace['file']) ? basename($trace['file']) : null,
                        'line' => $trace['line'] ?? null,
                        'function' => $trace['function'] ?? null,
                        'class' => $trace['class'] ?? null,
                    ];
                })
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

    public static function failed(string $message = '操作失败'): static
    {
        return new static($message, self::BAD_REQUEST);
    }

    /**
     * 创建无效凭证异常
     */
    public static function invalidCredentials(string $message = '用户名或密码错误'): static
    {
        return new static($message, self::UNAUTHORIZED);
    }

    /**
     * 创建 MySQL 连接失败异常
     */
    public static function mysqlConnectionError(string $message = 'MySQL连接失败'): static
    {
        // return new static($message, self::DB_CONNECTION_ERROR);
        $msgCode = explode(':', $message);
        return new static($msgCode[0], self::DB_CONNECTION_ERROR, $message);
    }


    /**
     * 创建 Redis 连接失败异常
     */
    public static function redisConnectionError(string $message = 'Redis连接失败'): static
    {
        // 根据错误信息判断具体的Redis错误类型
        if (str_contains($message, 'WRONGPASS')) {
            return new static('Redis认证失败：用户名或密码错误', self::REDIS_CONNECTION_ERROR);
        }

        if (str_contains($message, 'Connection refused')) {
            return new static('Redis连接被拒绝：请检查Redis服务是否正常运行', self::REDIS_CONNECTION_ERROR);
        }

        return new static($message, self::REDIS_CONNECTION_ERROR);
    }

    /**
     * 从其他异常创建 ApiException
     * @param Throwable $e 原始异常
     * @return static
     */
    public static function from(Throwable $e): static
    {
        // 检测 MySQL 连接异常
        if ($e instanceof \PDOException) {
            return self::mysqlConnectionError($e->getMessage());
        }

        // 检测数据库查询异常
        if ($e instanceof \Illuminate\Database\QueryException) {
            $message = '数据库操作错误';
            $debug = [
                'sql_error' => $e->getMessage(),
                'sql' => $e->getSql() ?? '',
                'bindings' => $e->getBindings() ?? []
            ];
            return new static($message, self::SERVER_ERROR, $debug);
        }


        // 检测 Redis 连接异常
        if (
            $e instanceof RedisException ||
            $e instanceof PredisConnectionException ||
            $e instanceof ConnectionException
        ) {
            return self::redisConnectionError($e->getMessage());
        }

        // 检测 HTTP 请求方法
        if ($e instanceof MethodNotAllowedHttpException) {
            $allowedMethods = $e->getHeaders()['Allow'] ?? '无';
            return new static(sprintf('不支持的请求方法，支持的方法：%s', $allowedMethods), self::SERVER_ERROR);
        }

        // 处理 HTTP 异常
        $statusCode = $e instanceof HttpExceptionInterface
            ? $e->getStatusCode()
            : self::SERVER_ERROR;

        return new static(
            $e->getMessage() ?: '服务器错误',
            $statusCode,
            null,
            $statusCode
        );
    }
}
