<?php

namespace Chaihao\Rap\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Upgrade
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (env('SYSTEM_UPGRADE', false)) {
            return response()->json([
                'status'   => false,
                'code'      => 400,
                'message'   => '系统正在升级，请稍后操作...'
            ]);
        }
        return $next($request);
    }
}
