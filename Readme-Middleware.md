
## 中间件使用

### 1. 核心中间件说明

- `check.auth`: JWT 认证中间件
- `permission`: 权限验证中间件  
- `cors`: 跨域处理中间件
- `request.response.logger`: 请求响应日志中间件
- `upgrade`: 系统升级模式中间件

### 2. 中间件使用方式

#### 2.1 单个路由使用中间件

Route::post('/user/profile', [UserController::class, 'profile'])
    ->middleware(['check.auth']);
```

#### 2.2 路由组使用中间件

```php 
Route::middleware(['rap-api'])->group(function () {
    Route::post('/order/create', [OrderController::class, 'create']);
    Route::post('/order/cancel', [OrderController::class, 'cancel']);
});
```

#### 2.3 排除中间件

```php
Route::withoutMiddleware(['permission'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
});
```

### 3. 自定义中间件

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole 
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => '需要管理员权限'
            ]);
        }
        return $next($request);
    }
}
```

注册中间件:

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'check.role' => \App\Http\Middleware\CheckRole::class
];
```
