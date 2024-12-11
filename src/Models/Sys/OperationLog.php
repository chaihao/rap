<?php

namespace Chaihao\Rap\Models\Sys;

use Chaihao\Rap\Models\BaseModel;


class OperationLog extends BaseModel
{

    // 基础配置
    protected $table = 'operation_log';
    protected $fillable = ["id", "method", "url", "ip", "user_agent", "payload", "response", "name", "created_by", "created_by_platform", "created_at", "updated_at"];
    protected $casts = [
        "method" => "string",
        "url" => "string",
        "ip" => "string",
        "user_agent" => "string",
        "payload" => "array",
        "response" => "array",
        "name" => "string",
        "created_by" => "integer",
        "created_by_platform" => "integer"
    ];

    // 缓存配置
    protected bool $modelCache = false;
    protected int $cacheTTL = 3600;
    protected string $cachePrefix = '';

    // 验证配置
    public $scenarios = [
        'add' => ['method', 'url', 'ip', 'user_agent', 'payload', 'response', 'name', 'created_by', 'created_by_platform'],
        'edit' => ['id', 'method', 'url', 'ip', 'user_agent', 'payload', 'response', 'name', 'created_by', 'created_by_platform'],
        'delete' => ['id'],
        'detail' => ['id']
    ];
    public $rules = [
        "method" => "string|max:255",
        "url" => "string|max:255",
        "ip" => "string|max:255",
        "user_agent" => "string",
        "payload" => "array",
        "response" => "array",
        "name" => "string|max:255",
        "created_by" => "integer",
        "created_by_platform" => "integer"
    ];

    /**
     * 获取验证器错误信息
     */
    public function setValidatorMessage(): array
    {
        return [
            "id.required" => "ID不能为空",
        ];
    }

    /**
     * 获取验证器自定义属性
     */
    public function setValidatorAttributes(): array
    {
        return [
            "method" => "请求方法",
            "url" => "请求地址",
            "ip" => "请求IP",
            "user_agent" => "用户代理",
            "payload" => "请求参数",
            "response" => "响应参数",
            "name" => "操作名称",
            "created_by" => "创建人ID",
            "created_by_platform" => "创建人类型 1 总管理平台员工 3 用户"
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
