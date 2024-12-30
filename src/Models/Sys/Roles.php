<?php

namespace  Chaihao\Rap\Models\Sys;

use Spatie\Permission\Models\Role;

class Roles extends Role
{

    /**
     * 表名
     */
    protected $table = 'roles';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = ["guard_name", "name", "slug"];

    /**
     * 数据类型转换
     */
    protected $casts = [
        "guard_name" => "string",
        "name" => "string",
        "slug" => "string"
    ];

    /**
     * 验证规则
     */
    public $rules = [
        "guard_name" => "required|string|max:255",
        "name" => "required|string|max:255",
        "slug" => "string|max:255"
    ];

    /**
     * 场景验证规则
     */
    public $scenarios = [
        'add' => ['guard_name', 'name', 'slug'],
        'edit' => ['id', 'guard_name', 'name', 'slug'],
        'delete' => ['id'],
        'detail' => ['id']
    ];

    /**
     * 格式化日期
     */
    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
