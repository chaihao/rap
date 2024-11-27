# Rap

Rap 是一个基于 Laravel 11.x 的基础组件包,提供了常用的功能模块。

## 功能特性

- JWT 认证
- RBAC 权限管理 
- 员工管理
- 多语言支持
- 异常处理
- 日志记录

## 系统要求

- PHP >= 8.2
- Laravel >= 11.9
- JWT Auth >= 2.1
- Laravel Permission >= 6.10

## 安装

1. 通过 Composer 安装:

```bash
composer require chaihao/rap
```

2. 发布配置文件:

```bash
# 发布所有配置
php artisan vendor:publish --tag=rap-config

# 分别发布配置
php artisan vendor:publish --tag=rap-config-core     # 核心配置
php artisan vendor:publish --tag=rap-config-jwt      # JWT配置
php artisan vendor:publish --tag=rap-config-permission # 权限配置
```

3. 运行数据库迁移:

```bash
php artisan migrate
```

## 配置

1. 在 `.env` 文件中配置数据库连接

2. 生成 JWT 密钥:

```bash
php artisan jwt:secret
```

3. 配置日志:

```php
# config/rap/logging.php

return [
    'enable_sql_logging' => env('RAP_ENABLE_SQL_LOGGING', false),
    'sql_log_level' => env('RAP_SQL_LOG_LEVEL', 'debug'),
    'sql_log_days' => env('RAP_SQL_LOG_DAYS', 14),
];
```

4. 配置ENV环境变量:

```bash
# 配置日志
RAP_ENABLE_SQL_LOGGING=true # 是否启用SQL日志记录
RAP_SQL_LOG_LEVEL=debug # SQL日志记录级别
RAP_SQL_LOG_DAYS=14 # SQL日志保留天数

# 配置api前缀
RAP_API_PREFIX=api # api前缀
```

## 使用说明

### 认证相关

提供以下接口:

- 登录: POST /auth/login
- 注册: POST /auth/register  
- 获取用户信息: POST /auth/staff_info
- 退出登录: POST /auth/logout

### 权限管理

提供以下接口:

- 添加权限: POST /permission/add
- 创建角色: POST /permission/create_role
- 分配角色: POST /permission/assign_role
- 获取权限列表: POST /permission/get_all
- 获取角色列表: POST /permission/get_all_roles

### 中间件

组件提供以下中间件:

- check.auth: JWT认证
- permission: 权限验证
- cors: 跨域处理
- request.response.logger: 请求响应日志

### 命令行工具

```bash
# 创建 Service
php artisan make:services UserService

# 创建 Repository 
php artisan make:repositories UserRepository

# 创建 Controller
php artisan make:controllers UserController

# 创建 Model
php artisan make:models User
```

## 多语言支持

组件内置中文语言包,位于 `resources/lang/zh_CN/` 目录。默认无需发布即可使用,如需自定义可通过以下命令发布语言文件:

```bash
php artisan vendor:publish --tag=rap-lang
```

包含:
- 验证消息
- 系统消息
- 错误提示

## 异常处理

自定义异常类 `ApiException` 提供统一的异常处理:

```php
throw new ApiException('操作失败', ApiException::BAD_REQUEST);
```

## 日志记录

支持 SQL 查询日志记录,可在配置文件中开启:

```php
'enable_sql_logging' => true
```

## 开源协议

MIT

## 技术支持

如有问题,请提交 Issue 或联系技术支持。