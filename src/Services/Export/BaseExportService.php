<?php

namespace Chaihao\Rap\Services\Export;

use Chaihao\Rap\Services\BaseService;
use Chaihao\Rap\Exception\ApiException;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseExportService extends BaseService implements FromCollection, WithColumnFormatting, WithHeadings, WithMapping, WithCustomCsvSettings
{

    public $exportColumns;
    public $keyColumn;
    public $params;
    public $page;
    public $limit;
    public $column;
    public $sortColumn;
    public $sortType;
    /**
     * 初始化服务
     */
    public function __construct(array $params)
    {
        $this->exportColumns = $this->getExportFields();
        $this->params = $params;
        $this->page = $params['page'] ?? 1;
        $this->limit = $params['page_size'] ?? 10000;
        $this->column = $params['column'] ?? [];
        $this->sortColumn = $params['sort_field'] ?? 'id';
        $this->sortType = $params['sort_type'] ?? 'desc';
        $this->setModel($this->initModel());
    }
    /**
     * 初始化模型
     */
    abstract protected function initModel(): Model;

    /**
     * 获取导出字段
     */
    public function getExportFields(): array
    {
        return $this->getModel()->getValidatorAttributes();
    }

    /**
     * 过滤查询
     */
    public  function filterQuery($params): Builder
    {
        // 应用自定义查询条件
        $params = $this->customListQuery($params);

        $query = $this->getModel()->newQuery();

        // 应用基础查询
        $this->applyBaseQuery($query);

        // 检查是否需要应用搜索条件和排序
        if (!empty($params)) {
            // 应用搜索条件
            $this->applySearchConditions($query, $params);
            // 应用排序
            $this->applySorting($query, $params);
        }
        return $query;
    }
    /**
     * 自定义列格式化
     */
    public function customColumnFormats($column = ''): string
    {
        $formats = [
            'status' => [0 => '禁用', 1 => '启用'],
        ];
        return $formats[$column] ?? '';
    }

    /**
     * 获取导出字段
     */
    public function getKeyColumn($columns = []): array
    {
        if (!empty($columns)) {
            $result = [];
            foreach ($columns as  $column) {
                if (isset($this->exportColumns[$column])) {
                    $result[$column] =  $this->exportColumns[$column];
                }
            }
            return $result;
        }
        return $this->exportColumns;
    }
    /**
     * 导出数据
     */
    public function collection()
    {
        return $this->filterQuery($this->params)->get();
    }

    /**
     * 导出列格式化
     */
    public function columnFormats(): array
    {
        return [
            // 'H' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            // 'I' => NumberFormat::FORMAT_DATE_DDMMYYYY,
        ];
    }

    /**
     * 导出表头
     */
    public function headings(): array
    {
        $this->keyColumn = array_keys($this->exportColumns);
        // 导出标题
        return array_values($this->exportColumns);
    }

    /**
     * 导出数据
     */
    public function map($row): array
    {
        $column = [];
        // 统一管理映射关系
        $mappings = [
            'status' => $this->customColumnFormats('status'),
        ];
        $mappingsName = [
            'status' => 'status_name',
        ];

        foreach ($this->keyColumn as $val) {
            // 使用映射数组
            if (array_key_exists($val, $mappings)) {
                $column[] = $mappings[$val][$row->$val] ?? '';
                continue;
            }
            if (array_key_exists($val, $mappingsName)) {
                $column[] = $row->{$mappingsName[$val]} ?? '';
                continue;
            }
            // 直接映射的字段，包括'creator_id'等
            $column[] = $row->$val ?? '';
        }

        return $column;
    }

    /**
     * 导出CSV设置
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',', // 分隔符
            'enclosure' => '"', // 包裹符
            'line_ending' => PHP_EOL, // 行结束符
            'use_bom' => true, // 是否使用BOM
            'output_encoding' => 'UTF-8', // 输出编码
        ];
    }
}
