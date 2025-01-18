<?php

namespace App\Services\Export;

use Chaihao\Rap\Models\Auth\Staff;
use Chaihao\Rap\Exception\ApiException;
use Illuminate\Database\Eloquent\Model;
use Chaihao\Rap\Services\Export\BaseExportService;


class StaffExportService extends BaseExportService
{
    /**
     * 初始化模型
     */
    protected function initModel(): Model
    {
        return app(Staff::class);
    }

    /**
     * 获取导出字段
     */
    public function getExportFields(): array
    {
        return parent::getExportFields();
    }

    /**
     * 自定义列格式化
     */
    public function customColumnFormats($column = ''): string
    {
        return parent::customColumnFormats($column);
    }
}
    