# RAP Laravel Admin 组件文档

## 1. 组件概述

RAP Laravel Admin 是一个基于 Laravel 框架的后台管理系统组件,提供了完整的权限管理、用户认证、异常处理等功能。

## 2. 目录结构

```bash
rap/
├── config/ # 配置文件目录
│ ├── auth.php # 认证配置
│ ├── jwt.php # JWT配置
│ ├── logging.php # 日志配置
│ ├── permission.php # 权限配置
│ └── rap.php # 组件主配置
├── Console/ # 命令行工具目录
│ └── Commands/ # 命令行命令
│ ├── MakeController.php # 生成控制器命令
│ ├── MakeModel.php # 生成模型命令
│ ├── artisan rap:.php # 生成仓库命令
│ ├── MakeServices.php # 生成服务命令
│ └── Stubs/ # 代码模板
├── Database/ # 数据库相关
│ └── migrations/ # 数据库迁移文件
├── docs/ # 文档目录
├── Exception/ # 异常处理
│ └── ApiException.php # API异常类
├── routes/ # 路由目录
│ └── rap-api.php # API路由定义
└── src/ # 源代码目录
```

## 3. 核心功能

### 3.1 权限管理

- 基于角色的访问控制(RBAC)
- 支持多角色分配
- 灵活的权限验证中间件
- 权限缓存机制

### 3.2 用户认证

- JWT Token 认证
- 支持多用户认证守卫
- 可配置的 Token 过期时间
- 黑名单机制

### 3.3 异常处理

- 统一的 API 异常处理
- 标准化的错误响应
- 支持调试模式
- 错误码规范化

### 3.4 代码生成器

- 控制器生成器
- 模型生成器
- 服务层生成器
- 数据仓库生成器

## 4. 中间件

| 中间件名称              | 说明           | 用途             |
| ----------------------- | -------------- | ---------------- |
| check.auth              | JWT 认证中间件 | 验证用户身份     |
| permission              | 权限验证中间件 | 检查用户权限     |
| cors                    | 跨域处理中间件 | 处理跨域请求     |
| request.response.logger | 日志中间件     | 记录请求响应日志 |
| upgrade                 | 系统升级中间件 | 系统维护模式控制 |

## 5. 错误码说明

| 错误码 | 说明       | 使用场景               |
| ------ | ---------- | ---------------------- |
| 400    | 请求错误   | 参数错误、业务逻辑错误 |
| 401    | 未授权     | 未登录、token 失效     |
| 403    | 禁止访问   | 无权限访问             |
| 404    | 未找到     | 资源不存在             |
| 422    | 验证错误   | 表单验证失败           |
| 500    | 服务器错误 | 系统异常               |

## 6. 使用说明

### 6.1 安装

```bash
composer require chaihao/laravel-rap-admin:@dev
```

### 6.2 配置

在 `config/rap.php` 中配置:

```php
return [
    'name' => 'Rap',
    'api' => [
        'prefix' => env('RAP_API_PREFIX', 'api'),
        'guard' => env('RAP_API_GUARD', 'api'),
    ],
    // ... 其他配置
];
```

### 6.3 数据库迁移

```bash
php artisan migrate
```

### 6.4 使用代码生成器

```bash
# 生成控制器
php artisan rap:controller UserController

# 生成模型
php artisan rap:model User

# 生成服务层
php artisan rap:services UserService

# 生成仓库
php artisan rap:repositories UserRepository
```

## 7. 注意事项

1. 确保 PHP 版本 >= 8.0
2. 需要安装并配置 JWT 扩展包
3. 建议在进行权限操作时做好日志记录
4. 所有 API 接口都需要通过 Bearer token 进行身份验证
5. 角色名称必须唯一

## 8. 更新日志

### v1.0.0 (2024-03-20)

- 初始版本发布
- 完整的 RBAC 权限管理
- JWT 认证集成
- 代码生成器支持

### v1.0.1 (2024-03-21)

- 修复 Service 层方法返回类型不一致问题
- 优化代码生成器模板
- 规范化异常处理
- 完善文档说明

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

### v1.1.1 发布说明

#### 新增功能

- 添加自定义异常处理器
- 支持多语言错误消息
- 增加请求频率限制中间件

#### 系统优化

- 优化数据库查询性能
- 改进缓存策略
- 完善日志记录格式

#### Bug 修复

- 修复权限验证缓存问题
- 解决跨域请求头设置不全的问题
- 修正代码生成器模板格式错误

### v1.1.2 发布说明

#### 新增功能

- 添加带锁操作功能
- 增强异常处理

#### 系统优化

- 改进缓存策略
- 完善日志记录格式

#### 升级建议

建议所有用户升级到此版本以获得最新的功能改进和问题修复。

#### 升级方法

```bash
composer require chaihao/laravel-rap-admin:^1.1.2
```
