# RAP 组件

RAP 是一个基于 Laravel 的后台管理系统组件包,提供完整的 RBAC 权限管理、用户认证、日志记录等功能。

## 功能特性

- 用户认证与授权管理 (JWT + RBAC)
- 完整的权限控制系统
- 操作日志与 SQL 日志记录
- API 限流与跨域处理
- 统一异常处理
- 代码生成工具

## 系统要求

- PHP >= 8.2
- Laravel >= 11.0
- MySQL >= 5.7

## 快速开始

### 1. 安装

在主项目的 `composer.json` 文件中添加以下配置：

> **方式一:**

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/chaihao/rap.git"
        }
    ],
    "require": {
        "chaihao/rap": "dev-main"
    }
}
```

> **方式二:**

```bash
git clone https://github.com/chaihao/rap.git
```

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "chaihao/rap": "dev-main"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Chaihao\\Rap\\": "rap/src/"
        }
    },
    "repositories": [
        {
            "type": "path",
            "url": "rap",
            "options": {
                "symlink": true 
            }
        }
    ]
}


```

> **repositories 配置说明:**
> - `type: "vcs"` - 支持从版本控制系统获取包
>   - 支持的 VCS: git, svn, hg (mercurial)
>   - 自动检测版本控制系统类型
>   - 可以是 GitHub, GitLab, Bitbucket 等托管的仓库
> - 私有仓库需要确保有访问权限
> - 建议使用 HTTPS 或 SSH 链接

然后运行以下命令仅安装/更新 rap 组件：

```bash
composer update chaihao/rap --with-dependencies
```

> **注意:** 
> - 使用 `composer update` 会更新所有依赖包
> - 使用 `composer update chaihao/rap --with-dependencies` 只会更新 rap 组件及其依赖
> - 首次安装也可以直接使用 `composer require chaihao/rap`

### 2. 基础配置

* 发布配置文件
```bash
php artisan vendor:publish --tag=rap-config
```

* 运行数据库迁移
```bash
php artisan migrate
```

* 生成 JWT 密钥
```bash
php artisan jwt:secret
```

### 3. 环境变量配置

```env
# API 配置
RAP_API_PREFIX=api        # API 路由前缀
RAP_API_GUARD=api        # API 认证守卫

# 日志配置
RAP_ENABLE_SQL_LOGGING=true   # 启用 SQL 日志
RAP_SQL_LOG_LEVEL=debug      # 日志级别
RAP_SQL_LOG_DAYS=14         # 日志保留天数

# 系统配置
SYSTEM_UPGRADE=false        # 系统升级模式
```

## 核心功能

### 认证功能

```php
# 认证相关路由
POST /auth/login         # 登录
POST /auth/register     # 注册
POST /auth/staff_info   # 获取用户信息
POST /auth/logout       # 退出登录
```

### 权限管理

```php
# 权限管理路由
POST /permission/add                    # 添加权限
POST /permission/create_role           # 创建角色
POST /permission/assign_role           # 分配角色
POST /permission/get_all              # 获取权限列表
POST /permission/get_all_roles        # 获取角色列表
POST /permission/sync_role_permissions # 同步角色权限
```

### 异常处理

```php
# 统一异常处理
throw new ApiException('操作失败', ApiException::BAD_REQUEST);

# 支持的错误码
- BAD_REQUEST (400)      # 请求错误
- UNAUTHORIZED (401)     # 未授权
- FORBIDDEN (403)       # 禁止访问
- NOT_FOUND (404)       # 未找到
- VALIDATION_ERROR (422) # 验证错误
- SERVER_ERROR (500)    # 服务器错误
```

### 中间件

```php
# 核心中间件
'check.auth'              # JWT 认证
'permission'              # 权限验证
'cors'                    # 跨域处理
'request.response.logger' # 请求响应日志
'upgrade'                # 系统升级模式

# 默认中间件组 rap-api
[
    'check.auth',
    'permission', 
    'cors',
    'request.response.logger'
]
```

### 代码生成器

```bash
# 生成各类文件
php artisan make:controller UserController  # 控制器
php artisan make:model User                # 模型
php artisan make:services UserService      # 服务类
php artisan make:repositories UserRepo     # 仓储类
```

## 进阶使用

### 多语言支持

```bash
# 发布语言文件
php artisan vendor:publish --tag=rap-lang

# 支持的语言包内容
- 验证消息
- 系统消息
- 错误提示
```

### 日志系统

```php
# 日志类型
- SQL 查询日志
- 请求响应日志
- 操作日志

# 日志位置
- SQL日志: storage/logs/sql/
- 系统日志: storage/logs/laravel.log
```

## 升级指南

```bash
# 1. 更新组件
composer update chaihao/rap

# 2. 发布新资源
php artisan vendor:publish --tag=rap-config
php artisan vendor:publish --tag=rap-migrations

# 3. 执行迁移
php artisan migrate
```

## 常见问题

### 1. JWT 认证失败
- 检查是否已生成 JWT 密钥
- 确认 token 格式正确

### 2. 权限验证失败
- 检查用户权限分配
- 确认权限名称正确

### 3. 跨域问题
- 检查 CORS 配置
- 确认请求头设置

## 参与贡献

1. Fork 项目
2. 创建分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送分支 (`git push origin feature/AmazingFeature`)
5. 提交 PR

## 更新日志

### v1.0.0 (2024-03-20)
- 初始版本发布
- 基础功能实现

## 开源协议

MIT

## 技术支持

如有问题,请提交 [Issue](https://github.com/chaihao/rap/issues) 或联系技术支持。

# 中间件与异常处理使用指南

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

## 异常处理

### 1. ApiException 使用

#### 1.1 基础异常

```php
// 抛出一般错误
throw new ApiException('操作失败', ApiException::BAD_REQUEST);

// 抛出未授权异常
throw ApiException::unauthorized('请先登录');

// 抛出资源未找到异常
throw ApiException::notFound('订单不存在');

// 抛出验证错误异常
throw ApiException::validationError('验证失败', [
    'name' => ['名称不能为空'],
    'price' => ['价格必须大于0']
]); 
```

#### 1.2 在控制器中使用

```php
public function create(Request $request)
{
    try {
        // 验证参数
        $this->checkValidator($request->all(), [
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1'
        ]);
        
        // 检查商品
        $product = Product::find($request->product_id);
        if (!$product) {
            throw ApiException::notFound('商品不存在');
        }
        
        // 检查库存
        if ($product->stock < $request->quantity) {
            throw new ApiException('库存不足', ApiException::BAD_REQUEST);
        }
        
        // 创建订单...
        
    } catch (ApiException $e) {
        return $e->render();
    }
}
```

#### 1.3 在服务层使用

```php
public function createOrder(array $data)
{
    try {
        DB::beginTransaction();
        
        // 检查用户余额
        if ($this->getUserBalance() < $data['total_amount']) {
            throw new ApiException('账户余额不足', ApiException::BAD_REQUEST);
        }
        
        // 扣减库存
        if (!$this->deductStock($data['product_id'], $data['quantity'])) {
            throw new ApiException('库存扣减失败', ApiException::SERVER_ERROR);
        }
        
        // 创建订单记录
        $order = Order::create($data);
        if (!$order) {
            throw new ApiException('订单创建失败', ApiException::SERVER_ERROR);
        }
        
        DB::commit();
        return $this->success($order);
        
    } catch (\Exception $e) {
        DB::rollBack();
        throw new ApiException($e->getMessage(), ApiException::SERVER_ERROR);
    }
}
```

### 2. 错误码说明

| 错误码 | 说明 | 使用场景 |
|--------|------|----------|
| 400 | 请求错误 | 参数错误、业务逻辑错误 |
| 401 | 未授权 | 未登录、token失效 |
| 403 | 禁止访问 | 无权限访问 |
| 404 | 未找到 | 资源不存在 |
| 422 | 验证错误 | 表单验证失败 |
| 500 | 服务器错误 | 系统异常 |

### 3. 调试模式

在 `.env` 中设置 `APP_DEBUG=true` 时,异常响应会包含调试信息:

```json
{
    "success": false,
    "code": 500,
    "message": "系统错误",
    "debug": {
        "file": "/app/Services/OrderService.php",
        "line": 100,
        "trace": [...]
    }
}
```
