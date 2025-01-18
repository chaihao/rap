<?php

namespace Chaihao\Rap\Services\Export;

use Chaihao\Rap\Models\Auth\Staff;
use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Services\Export\BaseExportService;


class StaffExportService extends BaseExportService
{
    public function __construct(Staff $model)
    {
        $this->setModel($model);
        parent::__construct();
    }

    /**
     * 获取导出字段
     */
    public function getExportFields(): array
    {
        $fields = parent::getExportFields();
        unset($fields['password']);
        return $fields;
    }

    /**
     * 自定义列格式化
     */
    public function customColumnFormats($column = ''): array
    {
        $result = parent::customColumnFormats($column);
        return $result;
    }
}
