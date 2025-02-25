<?php

namespace Chaihao\Rap\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Chaihao\Rap\Facades\CurrentStaff;
use Chaihao\Rap\Models\Sys\OperationLog;
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
        // 先获取响应，避免在不需要记录日志时执行额外操作
        $response = $next($request);

        // 检查是否需要记录操作日志
        if ($this->shouldLogOperation($request)) {
            // 记录请求和响应信息
            $requestLog = $this->getRequestLog($request);
            $responseLog = $this->getResponseLog($response);
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
        // 排除密码字段
        $payload = $request->except(['password', 'password_confirmation']);

        $payload['auth_staff'] = $this->getAuthStaff($payload);
        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $this->getClientIP(),
            'user_agent' => $request->userAgent(),
            'payload' => $payload,
            'created_by' => CurrentStaff::getId(),
            'created_by_platform' => 1,
        ];
    }

    /**
     * 获取当前登录用户信息
     *
     * @param  array  $payload
     * @return array
     */
    public function getAuthStaff(array $payload): array
    {
        if (empty($payload['auth_staff'])) {
            return [];
        }
        $authStaff = [
            'id' => $payload['auth_staff']['id'],
            'phone' => $payload['auth_staff']['phone'],
            'name' => $payload['auth_staff']['name'],
            'email' => $payload['auth_staff']['email'],
            'avatar' => $payload['auth_staff']['avatar'],
            'is_super' => $payload['auth_staff']['is_super'],
        ];
        return $authStaff;
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
            $status = $response->status();
            $contentType = $response->headers->get('Content-Type');

            // 优化内容类型判断
            if (str_contains($contentType, 'application/json')) {
                $content = json_decode($response->getContent(), true);
                return [
                    'status' => $status,
                    'content' => $content ?: ['error' => '无效的 JSON 内容']
                ];
            }

            return [
                'status' => $status,
                'content' => ['raw' => mb_substr($response->getContent(), 0, 1000)] // 限制内容长度
            ];
        } catch (\Exception $e) {
            Log::error('响应日志解析失败：' . $e->getMessage());
            return [
                'status' => $response instanceof Response ? $response->getStatusCode() : 500,
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
        // 1. 验证请求方法是否需要记录(POST/PUT/DELETE)
        $method = $request->method();
        if (!in_array($method, config('rap.log_action_name.methods', []))) {
            return false;
        }

        // 2. 获取控制器方法名称(例如: UserController@store)
        $action = $request->route()?->getActionName() ?? '';
        // 提取方法名部分(例如: store)
        $actionName = explode('@', $action)[1] ?? '';
        if (empty($actionName)) {
            return false;
        }

        // 3. 获取需要记录的操作类型(create/update/delete等)
        $actionTypes = config('rap.log_action_name.types', []);
        if (empty($actionTypes)) {
            return true;
        }

        // 4. 检查方法名是否包含配置的操作类型关键字
        return collect($actionTypes)->contains(
            fn($type) => \Illuminate\Support\Str::contains($actionName, $type)
        );
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

            // 构建操作日志数据
            $operationLog = new OperationLog();
            $operationLog->fill([
                'method' => $requestLog['method'] ?? '',
                'url' => $requestLog['url'] ?? '',
                'ip' => $requestLog['ip'] ?? '',
                'user_agent' => $requestLog['user_agent'] ?? '',
                // 使用 Model 的 cast 特性自动处理数组转换
                'payload' => $payload,
                'response' => $responseLog['content'],
                'name' => $actionName,
                'created_by' => CurrentStaff::getId(),
                'created_by_platform' => (int)($payload['created_by_platform'] ?? 0),
            ]);

            if (!$operationLog->save()) {
                throw new \Exception('操作日志保存失败');
            }
        } catch (\Exception $e) {
            // 记录详细的错误信息
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
        $request = request();
        $ip = $request->ip();

        if (!filter_var($ip, FILTER_VALIDATE_IP) || in_array($ip, ['::1', '127.0.0.1'])) {
            foreach (['X-Forwarded-For', 'X-Real-IP'] as $header) {
                $headerIp = $request->header($header);
                if ($headerIp) {
                    $ip = trim(explode(',', $headerIp)[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        break;
                    }
                }
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
