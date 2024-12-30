<?php

namespace  Chaihao\Rap\Models\Sys;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission;

class Permissions extends Permission
{
    use SoftDeletes;

    // 基础配置
    protected $table = 'permissions';
    protected $fillable = ["id", "name", "method", "uri", "controller", "action", "slug", "prefix", "guard_name", "middleware", "group", "group_name", "is_login", "status", "created_at", "updated_at", "deleted_at"];
    protected $casts = [
        "name" => "string",
        "method" => "string",
        "uri" => "string",
        "controller" => "string",
        "action" => "string",
        "slug" => "string",
        "prefix" => "string",
        "guard_name" => "string",
        "middleware" => "array",
        "group" => "string",
        "group_name" => "string",
        "is_login" => "integer",
        "status" => "integer"
    ];

    // 缓存配置
    protected bool $modelCache = false;
    protected int $cacheTTL = 3600;
    protected string $cachePrefix = '';

    // 验证配置
    public $scenarios = [
        'add' => ['name', 'method', 'uri', 'controller', 'action', 'slug', 'prefix', 'guard_name', 'middleware', 'group', 'group_name', 'is_login', 'status'],
        'edit' => ['id', 'name', 'method', 'uri', 'controller', 'action', 'slug', 'prefix', 'guard_name', 'middleware', 'group', 'group_name', 'is_login', 'status'],
        'delete' => ['id'],
        'detail' => ['id'],
        'status' => ['id', 'status']
    ];
    public $rules = [
        "name" => "required|string|max:255",
        "method" => "nullable|string|max:255",
        "uri" => "nullable|string|max:255",
        "controller" => "nullable|string|max:255",
        "action" => "nullable|string|max:255",
        "slug" => "nullable|string|max:255",
        "prefix" => "nullable|string|max:255",
        "guard_name" => "required|string|max:255",
        "middleware" => "nullable|array",
        "group" => "nullable|string|max:255",
        "group_name" => "nullable|string|max:255",
        "is_login" => "nullable|integer",
        "status" => "nullable|integer"
    ];

    /**
     * 获取验证器错误信息
     */
    public function setValidatorMessage(): array
    {
        return [
            "id.required" => "ID不能为空",
            "name.required" => "名称不能为空",
            "name.string" => "名称必须是字符串",
            "name.max" => "名称不能超过255个字符",
            "method.string" => "方法必须是字符串",
            "method.max" => "方法不能超过255个字符",
            "uri.string" => "URI必须是字符串",
            "uri.max" => "URI不能超过255个字符",
            "controller.string" => "控制器必须是字符串",
            "controller.max" => "控制器不能超过255个字符",
            "action.string" => "操作必须是字符串",
            "action.max" => "操作不能超过255个字符",
            "slug.string" => "别名必须是字符串",
            "slug.max" => "别名不能超过255个字符",
            "prefix.string" => "路由前缀必须是字符串",
            "prefix.max" => "路由前缀不能超过255个字符",
            "guard_name.required" => "角色名称不能为空",
            "guard_name.string" => "角色名称必须是字符串",
            "guard_name.max" => "角色名称不能超过255个字符",
            "middleware.array" => "中间件必须是数组",
            "group.string" => "分组必须是字符串",
            "group.max" => "分组不能超过255个字符",
            "group_name.string" => "分组名称必须是字符串",
            "group_name.max" => "分组名称不能超过255个字符",
            "is_login.integer" => "是否需要登录必须是整数",
            "status.integer" => "状态必须是整数"
        ];
    }

    /**
     * 获取验证器自定义属性
     */
    public function setValidatorAttributes(): array
    {
        return [
            "method" => "方法",
            "uri" => "URI 路径",
            "controller" => "控制器",
            "action" => "控制器操作",
            "slug" => "别名",
            "prefix" => "路由前缀",
            "middleware" => "中间件",
            "group" => "分组",
            "group_name" => "分组名称",
            "is_login" => "是否需要登录",
            "status" => "状态 1:启用 0:禁用"
        ];
    }

    /**
     * 格式化日期
     */
    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    /**
     * 自定义列表展示字段
     */
    public function getListFields(): array
    {
        return array_merge(parent::getListFields(), [
            // 在此添加额外的列表字段
        ]);
    }

    /**
     * 自定义详情展示字段
     */
    public function getDetailFields(): array
    {
        return parent::getDetailFields();
    }

    /**
     * 格式化输出
     */
    public function formatOutput(array $data): array
    {
        $data = parent::formatOutput($data);
        return $data;
    }
}
