<?php

namespace  Chaihao\Rap\Models\Sys;

use Chaihao\Rap\Models\BaseModel;

class SysAddress extends BaseModel
{



    // 基础配置
    protected $table = 'sys_address';
    protected $fillable = ["id", "code", "name", "parent_code"];
    protected $casts = [
        "code" => "integer",
        "name" => "string",
        "parent_code" => "integer"
    ];

    // 缓存配置
    protected bool $modelCache = true;
    protected int $cacheTTL = 3600;
    protected string $cachePrefix = '';

    // 验证配置
    public $scenarios = [
        'add' => ['code', 'name', 'parent_code'],
        'edit' => ['id', 'code', 'name', 'parent_code'],
        'delete' => ['id'],
        'detail' => ['id']
    ];
    public $rules = [
        "code" => "required|integer",
        "name" => "required|string|max:64",
        "parent_code" => "required|integer"
    ];

    /**
     * 获取验证器错误信息
     */
    public function setValidatorMessage(): array
    {
        return [
            "id.required" => "ID不能为空",
            "code.required" => "地址编码不能为空",
            "code.integer" => "地址编码必须是整数",
            "name.required" => "地址名称不能为空",
            "name.string" => "地址名称必须是字符串",
            "name.max" => "地址名称不能超过255个字符",
            "parent_code.required" => "父级编码不能为空",
            "parent_code.integer" => "父级编码必须是整数"
        ];
    }

    /**
     * 获取验证器自定义属性
     */
    public function getValidatorAttributes(): array
    {
        return [
            "code" => "地址编码",
            "name" => "地址名称",
            "parent_code" => "父级编码"
        ];
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
