# 权限管理 API 文档

## 概述
本文档详细说明了RAP组件系统中用于管理权限和角色的API接口。

## 基础URL
- 默认环境：`http://w.rap.com/api`
- Mock环境：`https://mock.apipost.net/mock/38f62f188c07000`

## 认证方式
所有API接口都需要Bearer token认证：
```
Authorization: Bearer {token}
```

## API接口列表

### 1. 添加权限
向系统添加新的权限。

**接口地址：** `POST /permission/add`

**请求参数：**
```json
{
    "parent_id": 74  // 可选：父级权限ID
}
```

**返回示例：**
```json
{
    "guard_name": "api",
    "name": "管理员",
    "updated_at": "2024-11-08T09:32:46.000000Z",
    "created_at": "2024-11-08T09:32:46.000000Z",
    "id": 26
}
```

### 2. 获取所有权限
获取系统中所有权限的列表。

**接口地址：** `POST /permission/get_all`

**请求参数：**
```json
{
    "parent_id": 74  // 可选：按父级ID筛选
}
```

### 3. 创建角色
在系统中创建新角色。

**接口地址：** `POST /permission/create_role`

**请求参数：**
```json
{
    "name": "admin",
    "slug": "管理员"
}
```

**返回示例：**
```json
{
    "guard_name": "api",
    "name": "管理员",
    "updated_at": "2024-11-08T09:32:46.000000Z",
    "created_at": "2024-11-08T09:32:46.000000Z",
    "id": 26
}
```

### 4. 获取所有角色
获取系统中所有角色的列表。

**接口地址：** `POST /permission/get_all_roles`

### 5. 分配角色
为用户分配一个或多个角色。

**接口地址：** `POST /permission/assign_role`

**请求参数：**
```json
{
    "id": "1",        // 用户ID
    "role": ["admin", "operation"]  // 角色名称数组
}
```

### 6. 移除角色
移除用户的指定角色。

**接口地址：** `POST /permission/remove_role`

**请求参数：**
```json
{
    "id": "1",     // 用户ID
    "role": "admin"  // 要移除的角色名称
}
```

## 响应状态码
- `200`：操作成功
- `404`：未找到/操作失败

## 系统特性
- 基于角色的访问控制
- 分层权限管理（父子关系）
- 多角色支持
- Bearer token认证机制
- 自定义异常处理

## 注意事项
- 所有接口都需要通过Bearer token进行身份验证
- 角色名称必须唯一
- 系统使用Spatie Laravel Permission包作为底层支持
- 支持单个和多个角色分配
- 所有响应均为JSON格式
- 接口调用需要相应的权限
- 建议在进行角色和权限操作时做好日志记录
