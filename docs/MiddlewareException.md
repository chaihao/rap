# 中间件与异常处理使用指南

## 一、中间件使用

### 1. 核心中间件总览

| 中间件名称 | 功能描述 | 使用场景 |
|------------|----------|----------|
| check.auth | JWT认证 | 需要验证用户身份的接口 |
| permission | 权限验证 | 需要检查用户权限的接口 |
| request.response.logger | 请求响应日志 | 需要记录操作日志的接口 |
| cors | 跨域处理 | 需要支持跨域请求的接口 |
| upgrade | 系统升级模式 | 系统维护升级时的控制 |

### 2. 中间件使用方式

```php
// 1. 单个路由使用
Route::post('/user/profile', [UserController::class, 'profile'])
    ->middleware(['check.auth']);

// 2. 路由组使用
Route::middleware(['check.auth', 'permission', 'request.response.logger'])->group(function () {
    Route::post('/order/list', [OrderController::class, 'list']);
    Route::post('/order/detail', [OrderController::class, 'detail']);
});

// 3. 排除中间件
Route::withoutMiddleware(['permission'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// rap-api 中间件 (包含 check.auth, permission, request.response.logger 中间件)
Route::middleware(['rap-api'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

```

### 3. 自定义中间件开发

```php
// 1. 创建中间件
php artisan make:middleware CheckRole

// 2. 编写中间件逻辑
namespace App\Http\Middleware;

class CheckRole 
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()->hasRole('admin')) {
            throw ApiException::forbidden('需要管理员权限');
        }
        return $next($request);
    }
}

// 3. 注册中间件 (app/Http/Kernel.php)
protected $routeMiddleware = [
    'check.role' => \App\Http\Middleware\CheckRole::class
];
```

## 二、异常处理

### 1. ApiException 使用示例

```php
// 1. 基础异常
throw new ApiException('操作失败', ApiException::BAD_REQUEST);

// 2. 快捷方法
throw ApiException::unauthorized('请先登录');
throw ApiException::notFound('订单不存在');
throw ApiException::validationError('验证失败', [
    'name' => ['名称不能为空']
]);

// 3. 在控制器中使用
public function create(Request $request)
{
    try {
        $product = Product::findOrFail($request->product_id);
        
        if ($product->stock < $request->quantity) {
            throw new ApiException('库存不足');
        }
        
        // 业务逻辑...
        
    } catch (ApiException $e) {
        return $e->render();
    }
}
```

### 2. 错误码规范

| 错误码 | 说明 | 使用场景 | 对应方法 |
|--------|------|----------|----------|
| 400 | 请求错误 | 参数错误、业务逻辑错误 | `ApiException::badRequest()` |
| 401 | 未授权 | 未登录、token失效 | `ApiException::unauthorized()` |
| 403 | 禁止访问 | 无权限访问 | `ApiException::forbidden()` |
| 404 | 未找到 | 资源不存在 | `ApiException::notFound()` |
| 422 | 验证错误 | 表单验证失败 | `ApiException::validationError()` |
| 500 | 服务器错误 | 系统异常 | `ApiException::serverError()` |

### 3. 调试模式响应

```json
{
    "success": false,
    "code": 500,
    "message": "系统错误",
    "debug": {
        "file": "/app/Services/OrderService.php",
        "line": 100,
        "trace": "..." 
    }
}
```