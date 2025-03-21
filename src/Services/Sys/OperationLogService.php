<?php

namespace Chaihao\Rap\Services\Sys;

use Chaihao\Rap\Models\Sys\OperationLog;
use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Services\BaseService;

class OperationLogService extends BaseService
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

    /**
     * 初始化服务
     */
    public function __construct(OperationLog $model)
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
