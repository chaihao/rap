<?php

namespace Chaihao\Rap\Services\Export;

use Carbon\Carbon;
use Maatwebsite\Excel\Excel;
use Chaihao\Rap\Job\ExportJob;
use Illuminate\Http\JsonResponse;
use Chaihao\Rap\Facades\CurrentStaff;
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

abstract class BaseExportService extends BaseService implements FromCollection, WithColumnFormatting, WithHeadings, WithMapping, WithCustomCsvSettings
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


    /**
     * @Author: chaihao
     * @description: 导出excel
     */
    public function export($params)
    {
        if (CurrentStaff::isAdmin()) {
            // 管理员最大管理数限制
            $exportNum = 50000; // 设置导出数量上限
            $count = $this->filterQuery($params)->count(); // 获取符合条件的记录数
            if ($count > $exportNum) {
                throw new ApiException('导出数量上限 ' . $exportNum . ' 条 '); // 超过上限抛出异常
            }
        }
        if (empty($params['ids'])) {
            $result =  $this->asynchronousExport($params); // 调用异步导出方法
            if ($result['status']) {
                return $this->success('开始导出'); // 返回成功信息
            } else {
                throw new ApiException($result['msg']); // 返回错误信息
            }
        } else {
            $filename = now()->format('YmdHis') . '.csv'; // 生成文件名
            $this->store($filename, 'public', Excel::CSV); // 存储CSV文件
            return $this->success(Storage::disk('public')->url($filename)); // 返回文件URL
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
