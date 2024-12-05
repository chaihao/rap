
## 中间件使用

### 1. 核心中间件说明

- `check.auth`: JWT 认证中间件
- `permission`: 权限验证中间件  
- `cors`: 跨域处理中间件
- `request.response.logger`: 请求响应日志中间件
- `upgrade`: 系统升级模式中间件

### 2. 中间件使用方式

#### 2.1 单个路由使用中间件
```
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

#### 2.3 指定角色或权限

```php
// 检测当前路由权限 -- 默认检查权限, 如果指定角色, 则同时检查角色和权限
Route::middleware(['rap-api', 'permission'])->group(function () {
    Route::post('/order/create', [OrderController::class, 'create']);
});

// 指定角色 -- 同时检查角色和权限 -- 角色优先级高于权限 -- 符合任意一个即可 -- 'permission:admin' 表示角色为 admin 的用户可以访问
Route::middleware(['rap-api', 'permission:admin'])->group(function () {
    Route::post('/order/create', [OrderController::class, 'create']);
});
```

#### 2.4 排除中间件

```php
Route::withoutMiddleware(['permission', 'check.auth'])->group(function () {
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
