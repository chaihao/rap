<?php

namespace Chaihao\Rap\Services\Export;

use Carbon\Carbon;
use Maatwebsite\Excel\Excel;
use Chaihao\Rap\Job\ExportJob;
use Chaihao\Rap\Services\BaseService;
use Illuminate\Support\Facades\Redis;
use Chaihao\Rap\Exception\ApiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class BaseExportService extends BaseService implements FromCollection, WithColumnFormatting, WithHeadings, WithMapping, WithCustomCsvSettings
{
    use Exportable;

    public $exportColumns;
    public $keyColumn;
    public $params;
    public $page;
    public $limit;
    public $column;
    public $sortColumn;
    public $sortType;
    protected string $filePathSuffix = 'export/';

    /**
     * 初始化服务
     */
    public function __construct()
    {
        $this->exportColumns = $this->getExportFields();
        $this->params = request()->all();
        $this->page = $params['page'] ?? 1;
        $this->limit = $params['page_size'] ?? 10000;
        $this->column = $params['column'] ?? [];
        $this->sortColumn = $params['sort_field'] ?? 'id';
        $this->sortType = $params['sort_type'] ?? 'desc';
    }

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
     * 自定义列格式化
     */
    public function customColumnFormats($column = ''): array
    {
        $formats = [
            'is_super' => [0 => '否', 1 => '是'],
            'status' => [0 => '禁用', 1 => '启用'],
        ];
        return $formats[$column] ?? [];
    }
    /**
     * 映射名称
     */
    public function mappingsName($column = ''): string
    {
        $mappingsName = [];
        return $mappingsName[$column] ?? '';
    }
    /**
     * 导出数据
     */
    public function map($row): array
    {
        $column = [];
        foreach ($this->keyColumn as $val) {
            // 使用映射数组
            $mappings = $this->customColumnFormats($val);
            if (!empty($mappings)) {
                $column[] = $mappings[$row->$val] ?? '';
                continue;
            }
            $mappingsName = $this->mappingsName($val);
            if (!empty($mappingsName)) {
                $column[] = $row->{$mappingsName} ?? '';
                continue;
            }
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


    /**
     * @Author: chaihao
     * @description: 导出excel
     */
    public function export($params)
    {

        $count = $this->filterQuery($params)->count();

        if (empty($params['ids']) && $count > 10000) {
            $result =  $this->asynchronousExport($params); // 调用异步导出方法
            if ($result['status']) {
                return '开始导出'; // 返回成功信息
            } else {
                throw new ApiException($result['msg']); // 返回错误信息
            }
        } else {
            $filename = $this->filePathSuffix . now()->format('YmdHis') . '.csv'; // 生成文件名

            $this->store($filename, 'public', Excel::CSV); // 存储CSV文件
            return Storage::disk('public')->url($filename); // 返回文件URL
        }
    }

    /**
     * @Author: chaihao
     * @description: 异步导出
     */
    public function asynchronousExport($params)
    {
        try {
            $redisKey = 'AsynchronousExportRedisKey' . $this->getModel()->getTable(); // Redis键名
            if (Redis::exists($redisKey)) {
                throw new ApiException("正在导出数据, 请稍后..."); // 检查是否已有导出任务
            }
            $limit = 10000; // 每次导出的记录数限制
            $count = $this->filterQuery($params)->count(); // 获取符合条件的记录数

            if ($count <= 0) {
                throw new ApiException("数据过滤结果为空,不执行导出操作"); // 记录数为零时抛出异常
            }
            $totalPage = ceil($count / $limit); // 计算总页数
            $fileName  = uniqid(now()->format('YmdHis')); // 生成唯一文件名
            for ($i = 0; $i < $totalPage; $i++) {
                if ($i == 0) {
                    Redis::setex($redisKey, $totalPage * 20, $fileName); // 设置Redis过期时间
                }
                ExportJob::dispatch($this, $fileName, $params, $i, $totalPage)->delay(Carbon::now()->addSeconds($i * 5)); // 调度导出任务
            }
            return ['status' => true, 'msg' => '开始导出']; // 返回导出开始状态
        } catch (ApiException $e) {
            throw new ApiException($e->getMessage()); // 捕获异常并抛出
        }
    }
}
