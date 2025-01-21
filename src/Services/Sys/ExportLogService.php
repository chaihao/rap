<?php

namespace Chaihao\Rap\Services\Sys;

use Chaihao\Rap\Models\Sys\ExportLog;
use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Services\BaseService;

class ExportLogService extends BaseService
{
    /**
     * 可搜索字段
     */
    protected array $searchableFields = [];

    /**
     * 可导出字段
     */
    protected array $exportableFields = [];

    /**
     * 允许排序的字段
     */
    protected array $allowedSortFields = [];

    const STATUS_COMPLETED = 1; // 导出完成
    const STATUS_FAILED = 2; // 导出失败
    const STATUS_DELETED = 3; // 文件已删除


    const IS_DOWNLOAD_YES = 1; // 已下载
    const IS_DOWNLOAD_NO = 0; // 未下载

    /**
     * 初始化服务
     */
    public function __construct(ExportLog $model)
    {
        $this->setModel($model);
    }

    /**
     * 自定义列表查询条件
     * @param array $params
     * @return array
     */
    public function customListQuery(array $params): array
    {
        return $params;
    }

    /**
     * 自定义添加前的数据处理
     * @param array $params
     * @return array
     */
    public function beforeAdd(array $params): array
    {
        return $params;
    }

    /**
     * 自定义编辑前的数据处理
     * @param array $params
     * @return array
     */
    public function beforeEdit(array $params): array
    {
        return $params;
    }

    /**
     * 自定义格式化输出
     * @param mixed $data
     * @return mixed
     */
    public function formatData($data)
    {
        return parent::formatData($data);
    }
}
