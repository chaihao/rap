<?php

namespace Chaihao\Rap\Models\Sys;

use Chaihao\Rap\Models\BaseModel;


class ExportLog extends BaseModel
{


    // 基础配置
    protected $table = 'export_log';
    protected $fillable = ["id", "name", "path", "url", "is_download", "download_time", "status", "error_msg", "created_at", "updated_at"];
    protected $casts = [
        "name" => "string",
        "path" => "string",
        "is_download" => "integer",
        "download_time" => "datetime",
        "status" => "integer",
        "error_msg" => "string"
    ];

    // 缓存配置
    protected bool $modelCache = false;
    protected int $cacheTTL = 3600;
    protected string $cachePrefix = '';

    // 验证配置
    public $scenarios = [
        'add' => ['name', 'path', 'url', 'is_download', 'download_time', 'status', 'error_msg'],
        'edit' => ['id', 'name', 'path', 'url', 'is_download', 'download_time', 'status', 'error_msg'],
        'delete' => ['id'],
        'detail' => ['id'],
        'status' => ['id', 'status']
    ];
    public $rules = [
        "name" => "required|string|max:255",
        "path" => "nullable|string|max:255|required_if:status,1", // status = 1 时必填
        "url" => "nullable|string|max:255|required_if:status,1", // status = 1 时必填
        "is_download" => "nullable|integer|in:0,1",
        "download_time" => "nullable|datetime",
        "status" => "nullable|integer|in:1,2,3",
        "error_msg" => "nullable|string|max:255"
    ];

    /**
     * 格式化日期
     */
    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

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
    public function getValidatorAttributes(): array
    {
        return [
            "id" => "Id",
            "name" => "标题",
            "path" => "文件路径",
            "url" => "文件URL",
            "is_download" => "是否下载 1 已下载 0 未下载",
            "download_time" => "下载时间",
            "status" => "状态 1 导出完成 2 导出失败 3 文件已删除",
            "error_msg" => "错误信息",
            "created_at" => "Created At",
            "updated_at" => "Updated At"
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
    /**
     * 获取关联数据
     */
    public function getWithRelation(): array
    {
        return [];
    }

    /**
     * 获取列表关联数据
     */
    public function listWithRelationData(): array
    {
        return [];
    }

    /**
     * 获取导出关联数据
     */
    public function getExportRelation(): array
    {
        return [];
    }
}
