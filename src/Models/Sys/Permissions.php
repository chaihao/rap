<?php

namespace  Chaihao\Rap\Models\Sys;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission;

class Permissions extends Permission
{
    use SoftDeletes;
    /**
     * 表名(不含前缀)
     */
    protected $table = 'permissions';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = ["action", "controller", "group", "group_name", "guard_name", "is_login", "method", "middleware", "name", "prefix", "slug", "status", "uri"];

    /**
     * 数据类型转换
     */
    protected $casts = [
        "action" => "string",
        "controller" => "string",
        "group" => "string",
        "group_name" => "string",
        "guard_name" => "string",
        "is_login" => "integer",
        "method" => "string",
        "middleware" => "array",
        "name" => "string",
        "prefix" => "string",
        "slug" => "string",
        "status" => "integer",
        "uri" => "string"
    ];

    /**
     * 验证规则
     */
    public $rules = [
        "action" => "string|max:255",
        "controller" => "string|max:255",
        "group" => "string|max:255",
        "group_name" => "string|max:255",
        "guard_name" => "required|string|max:255",
        "is_login" => "required|integer",
        "method" => "string|max:255",
        "middleware" => "array",
        "name" => "required|string|max:255",
        "prefix" => "string|max:255",
        "slug" => "string|max:255",
        "status" => "required|integer",
        "uri" => "string|max:255"
    ];

    /**
     * 场景验证规则
     */
    public $scenarios = [
        'add' => ['action', 'controller', 'group', 'group_name', 'guard_name', 'is_login', 'method', 'middleware', 'name', 'prefix', 'slug', 'status', 'uri'],
        'edit' => ['id', 'action', 'controller', 'group', 'group_name', 'guard_name', 'is_login', 'method', 'middleware', 'name', 'prefix', 'slug', 'status', 'uri'],
        'delete' => ['id'],
        'detail' => ['id'],
        'status' => ['id', 'status']
    ];

    /**
     * 自定义验证属性
     */
    public function setValidatorAttributes(): array
    {
        return [
            'name' => '权限名称',
            'slug' => '权限标识',
            'guard_name' => '守卫名称',
            'status' => '状态',
            'is_login' => '登录状态',
            'action' => '操作方法',
            'controller' => '控制器',
            'group' => '分组',
            'group_name' => '分组名称',
            'method' => '请求方法',
            'middleware' => '中间件',
            'prefix' => '路由前缀',
            'uri' => '请求路径'
        ];
    }

    /**
     * 自定义验证消息
     */
    public function setValidatorMessage(): array
    {
        return [
            'name.required' => '权限名称不能为空',
            'name.unique' => '权限名称已存在',
            'slug.required' => '权限标识不能为空',
            'slug.unique' => '权限标识已存在',
            'guard_name.required' => '守卫名称不能为空',
            'status.required' => '状态不能为空',
            'status.in' => '状态值无效',
            'is_login.required' => '登录状态不能为空',
            'is_login.in' => '登录状态值无效'
        ];
    }

    /**
     * 列表关联
     */
    public function listWithRelations(): array
    {
        return [];
    }

    /**
     * 详情关联
     */
    public function getWithRelations(): array
    {
        return [];
    }

    /**
     * 列表字段
     */
    public function getListFields(): array
    {
        return [
            'id',
            'name',
            'slug',
            'guard_name',
            'status',
            'group',
            'group_name',
            'is_login',
            'created_at'
        ];
    }
}
