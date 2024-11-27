<?php

namespace Chaihao\Rap\Http\Middleware;

use App\Facades\CurrentStaff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckAuth
{
    /**
     * 处理传入的请求。
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $guard  要使用的守卫
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $guard = null): Response
    {
        // 如果未指定守卫，使用默认的'api'守卫
        $guard = $guard ?? 'api';

        // 设置当前守卫
        auth()->shouldUse($guard);

        try {
            // 解析并验证 JWT 令牌
            $staff = JWTAuth::parseToken()->authenticate();

            CurrentStaff::setStaff($staff);
            // 如果未找到用户，返回未授权响应
            if (!$staff) {
                return $this->unauthorizedResponse('用户未登录或会话已过期');
            }

            // 用户验证成功，将用户信息添加到请求中
            $request->merge(['auth_staff' => $staff]);
        } catch (JWTException $e) {
            // 处理 JWT 相关异常
            return $this->handleJWTException($e);
        }

        // 验证通过，继续处理请求
        return $next($request);
    }

    /**
     * 处理 JWT 异常
     *
     * @param  \Tymon\JWTAuth\Exceptions\JWTException  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function handleJWTException(JWTException $exception): Response
    {
        $message = match (get_class($exception)) {
            \Tymon\JWTAuth\Exceptions\TokenExpiredException::class => '令牌已过期',
            \Tymon\JWTAuth\Exceptions\TokenInvalidException::class => '令牌无效',
            \Tymon\JWTAuth\Exceptions\TokenBlacklistedException::class => '令牌已被列入黑名单',
            default => '未提供令牌或令牌验证失败'
        };

        return $this->unauthorizedResponse($message);
    }

    /**
     * 返回未授权响应
     *
     * @param  string  $message
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'status' => false,
            'code' => 401,
            'msg' => $message,
        ], 401);
    }
}
