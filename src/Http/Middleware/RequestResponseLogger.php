<?php

namespace Chaihao\Rap\Http\Middleware;

use Chaihao\Rap\Models\Sys\OperationLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestResponseLogger
{
    /**
     * 处理传入的请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $type
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 记录请求信息
        $requestLog = $this->getRequestLog($request);
        Log::info('请求信息:', $requestLog);

        // 处理请求
        $response = $next($request);

        // 记录响应信息
        $responseLog = $this->getResponseLog($response);
        Log::info('响应信息:', $responseLog);

        // 检查是否需要记录操作日志
        if ($this->shouldLogOperation($request)) {
            $this->logOperation($requestLog, $responseLog, $request);
        }

        return $response;
    }

    /**
     * 获取请求日志信息
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    private function getRequestLog(Request $request): array
    {
        $payload = $request->except(['password', 'password_confirmation']);

        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $this->getClientIP(),
            'user_agent' => $request->userAgent(),
            'payload' => $payload,
        ];
    }

    /**
     * 获取响应日志信息
     *
     * @param  \Illuminate\Http\Response  $response
     * @return array
     */
    private function getResponseLog($response): array
    {
        try {
            // 使用 Laravel 的响应对象方法判断
            if ($response->headers->get('Content-Type') === 'application/json') {
                return [
                    'status' => $response->status(),
                    'content' => json_decode($response->getContent(), true) ?? ['error' => '无效的 JSON 内容']
                ];
            }

            return [
                'status' => $response->status(),
                'content' => ['raw' => $response->getContent()]
            ];
        } catch (\Exception $e) {
            Log::error('响应日志解析失败：' . $e->getMessage());
            return [
                'status' => $response->status(),
                'content' => ['error' => '无法解析的响应内容']
            ];
        }
    }

    /**
     * 判断是否应该记录操作日志
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    private function shouldLogOperation(Request $request): bool
    {
        $actionName = $request->route()->getActionName() ?? '';
        $actionName = explode('@', $actionName)[1] ?? '';
        $actionTypes = config('rap.log_action_name');

        return collect($actionTypes)->contains(function ($type) use ($actionName) {
            return strpos($actionName, $type) !== false;
        });
    }

    /**
     * 记录操作日志
     *
     * @param  string|null  $type
     * @param  array  $requestLog
     * @param  array  $responseLog
     * @param  \Illuminate\Http\Request  $request
     */
    private function logOperation($requestLog, $responseLog, $request): void
    {
        $actionCustomName = $request->route()->getName() ?? '';

        $this->addOperationLog($requestLog, $responseLog, $actionCustomName);
    }



    /**
     * 添加操作日志
     *
     * @param  array  $requestLog
     * @param  array  $responseLog
     * @param  string  $actionName
     */
    public function addOperationLog($requestLog, $responseLog, $actionName): void
    {
        try {
            $payload = $requestLog['payload'] ?? [];

            $operationLog = new OperationLog();
            $operationLog->fill([
                'method' => $requestLog['method'] ?? '',
                'url' => $requestLog['url'] ?? '',
                'ip' => $requestLog['ip'] ?? '',
                'userAgent' => $requestLog['user_agent'] ?? '',
                'payload' => $payload, // 直接传入数组，让 Model 的 cast 处理转换
                'response' => $responseLog['content'], // 直接传入数组，让 Model 的 cast 处理转换
                'merchant_id' => (int)($payload['merchant_id'] ?? 0),
                'name' => $actionName,
                'action_user_id' => (int)($payload['action_user_id'] ?? 0),
                'action_user_type' => (int)($payload['action_user_type'] ?? 0),
            ]);

            if (!$operationLog->save()) {
                throw new \Exception('操作日志保存失败');
            }
        } catch (\Exception $e) {
            Log::error('操作日志记录失败：' . $e->getMessage(), [
                'request' => $requestLog,
                'response' => $responseLog,
                'action_name' => $actionName
            ]);
        }
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    protected function getClientIP(): string
    {
        $ip = request()->ip();

        if (!$ip || $ip === '::1' || $ip === '127.0.0.1') {
            $ip = request()->header('X-Forwarded-For');
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
        }

        if (!$ip) {
            $ip = request()->header('X-Real-IP');
        }

        return $ip ?: '0.0.0.0';
    }
}
