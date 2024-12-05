<?php

namespace Chaihao\Rap\Http\Middleware;

use Chaihao\Rap\Facades\CurrentStaff;
use Chaihao\Rap\Services\Sys\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        try {
            // 获取当前用户
            $staff = CurrentStaff::getStaff();

            // 超级管理员直接放行
            if ($staff?->is_super) {
                return $next($request);
            }

            // 检查豁免路径
            $path = $request->path();
            if ($this->isExceptPath($path)) {
                return $next($request);
            }

            // 获取当前请求路由权限
            $permission = $this->getCurrentUrl($path);

            // 优化权限检查逻辑
            if (!empty($roles)) {
                // 检查是否具有任一指定角色
                if ($staff?->hasRole($roles)) {
                    return $next($request);
                }
            }

            // 检查是否具有当前路由所需权限
            if ($staff?->hasPermissionTo($permission)) {
                return $next($request);
            }

            throw UnauthorizedException::forPermissions([$permission]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'msg' => '权限不足: ' . $e->getMessage()
            ], 403);
        }
    }

    /**
     * 获取当前请求的URL路径
     */
    public function getCurrentUrl($path)
    {
        return app(PermissionService::class)->convertPath($path);
    }

    /**
     * 检查是否为豁免路径
     */
    private function isExceptPath(string $path): bool
    {
        return in_array($path, config('rap.except_path', []));
    }
}
