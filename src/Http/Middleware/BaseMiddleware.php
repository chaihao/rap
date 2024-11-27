<?php

namespace Chaihao\Rap\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BaseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 在这里添加通用的中间件逻辑
        return $next($request);
    }
}
