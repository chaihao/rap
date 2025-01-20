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

```bash
composer require chaihao/laravel-rap-admin
```

### 2. 基础配置

- 发布配置文件

```bash
php artisan vendor:publish --tag=rap-config
```

- 运行数据库迁移

```bash
php artisan migrate
```

- 生成 JWT 密钥

```bash
php artisan jwt:secret
```

### 3. 环境变量配置

```env

# 时区配置
APP_TIMEZONE=PRC

# 语言配置
APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh
APP_FAKER_LOCALE=zh-CN

# 缓存配置
CACHE_STORE=redis
CACHE_PREFIX=

# API 配置
RAP_API_PREFIX=api        # API 路由前缀
RAP_API_GUARD=api        # API 认证守卫
RAP_CONTROLLER_VERSION=V1\ # 控制器版本
RAP_CREATE_SERVICES_MODEL=true # 创建service时，同时创建model
RAP_CREATE_SERVICES_CONTROLLER=true # 创建service时，同时创建controller

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

[更多异常处理](Readme-ApiException.md)

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
    'request.response.logger'
]
```

[更多中间件配置](Readme-Middleware.md)

### 代码生成器

```bash
# 生成各类文件
php artisan rap:controller UserController  # 控制器
php artisan rap:model User                # 模型
php artisan rap:services UserService      # 服务类
php artisan rap:repositories UserRepo     # 仓储类
```

```php
php artisan rap:services User/UserService

# 生成文件
app/Http/Controllers/User/UserController.php
app/Models/User/User.php
app/Services/User/UserService.php
```

## 进阶使用

### 多语言支持

```bash
# 发布语言文件 (可选)
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

## 更新日志

### v1.1.0 (2024-03-25)

- 优化代码生成器
  - 支持自动创建关联文件(Model/Controller)
  - 改进模板结构,提供更多默认实现
  - 增强字段类型识别和验证规则生成
- 完善权限管理
  - 新增权限缓存机制
  - 支持权限组管理
  - 优化权限检查性能
- 增强异常处理
  - 支持更详细的调试信息
  - 统一异常响应格式
  - 新增常用异常类型
- 改进中间件
  - 优化请求日志记录
  - 增强跨域处理
  - 完善升级模式控制

## 开源协议

MIT

## 技术支持

如有问题,请提交 [Issue](https://github.com/chaihao/rap/issues) 或联系技术支持。
