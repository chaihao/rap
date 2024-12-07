<?php

namespace  Chaihao\Rap\Models\Sys;

use Chaihao\Rap\Models\BaseModel;

class SysAddress extends BaseModel
{


    /**
     * 表名
     */
    protected $table = 'sys_address';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = ["code", "name", "parent_code"];

    /**
     * 数据类型转换
     */
    protected $casts = [
        "code" => "integer",
        "name" => "string",
        "parent_code" => "integer"
    ];

    /**
     * 验证规则
     */
    public $rules = [
        "code" => "required|integer",
        "name" => "required|string|max:64",
        "parent_code" => "required|integer"
    ];

    /**
     * 场景验证规则
     */
    public $scenarios = [
        'add' => ['code', 'name', 'parent_code'],
        'edit' => ['id', 'code', 'name', 'parent_code'],
        'delete' => ['id'],
        'detail' => ['id']
    ];

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

        // 在此添加自定义格式化逻辑

        return $data;
    }
}
