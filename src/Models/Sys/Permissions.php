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
}
