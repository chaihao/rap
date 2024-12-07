<?php

namespace Chaihao\Rap\Models\Sys;

use Chaihao\Rap\Models\BaseModel;


class OperationLog extends BaseModel
{


    /**
     * 表名(不含前缀)
     */
    protected $table = 'operation_log';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = ["created_by", "created_by_platform", "ip", "method", "name", "payload", "response", "url", "user_agent"];

    /**
     * 数据类型转换
     */
    protected $casts = [
        "created_by" => "integer",
        "created_by_platform" => "integer",
        "ip" => "string",
        "method" => "string",
        "name" => "string",
        "payload" => "array",
        "response" => "array",
        "url" => "string",
        "user_agent" => "string"
    ];

    /**
     * 验证规则
     */
    public $rules = [
        "created_by" => "required|integer",
        "created_by_platform" => "required|integer",
        "ip" => "required|string|max:255",
        "method" => "required|string|max:255",
        "name" => "required|string|max:255",
        "payload" => "array",
        "response" => "array",
        "url" => "required|string|max:255",
        "user_agent" => "string"
    ];

    /**
     * 场景验证规则
     */
    public $scenarios = [
        'add' => ['created_by', 'created_by_platform', 'ip', 'method', 'name', 'payload', 'response', 'url', 'user_agent'],
        'edit' => ['id', 'created_by', 'created_by_platform', 'ip', 'method', 'name', 'payload', 'response', 'url', 'user_agent'],
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
