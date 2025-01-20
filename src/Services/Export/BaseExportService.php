<?php

namespace Chaihao\Rap\Services\Export;

use Throwable;
use ZipArchive;
use Carbon\Carbon;
use Maatwebsite\Excel\Excel;
use Chaihao\Rap\Job\ExportJob;
use Illuminate\Support\Facades\Log;
use Chaihao\Rap\Services\BaseService;
use Illuminate\Support\Facades\Redis;
use Chaihao\Rap\Exception\ApiException;
use Illuminate\Database\Eloquent\Model;
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
    protected string $filePathSuffix = 'export/';

    /**
     * 初始化服务
     */
    public function __construct(Model $model = null)
    {
        if (!$this->model) {
            if ($model) {
                $this->setModel($model);
            } else {
                throw new ApiException('模型未设置');
            }
        }
        $this->getParams();
        $this->exportColumns = $this->getKeyColumn($this->getColumn()); // 获取导出字段
    }
    /**
     * 设置页码
     */
    public function setPage(int $page = 0): int
    {
        if (empty($page)) {
            $page = $this->params['page'] ?? 0;
        }
        return $this->page = $page;
    }

    /**
     * 设置限制
     */
    public function setLimit(int $limit = 0): int
    {
        if (empty($limit)) {
            $limit = $this->params['page_size'] ?? 10000;
        }
        return $this->limit = $limit;
    }

    /**
     * 设置列
     */
    public function setColumn(array $column = []): array
    {
        if (empty($column)) {
            $column = $this->params['column'] ?? [];
        }
        return $this->column = $column;
    }

    /**
     * 设置参数
     */
    public function setParams(array $params = []): array
    {
        if (empty($params)) {
            $params = request()->all();
        }
        return $this->params = $params;
    }

    /**
     * 获取页码
     */
    public function getPage(): int
    {
        if (empty($this->page)) {
            $this->page = $this->params['page'] ?? 0;
        }
        return $this->page;
    }

    /**
     * 获取限制
     */
    public function getLimit(): int
    {
        if (empty($this->limit)) {
            $this->limit = $this->params['page_size'] ?? 10000;
        }
        return $this->limit;
    }

    /**
     * 获取列
     */
    public function getColumn(): array
    {
        if (empty($this->column)) {
            $this->column = $this->params['column'] ?? [];
        }
        return $this->column;
    }

    /**
     * 获取参数
     */
    public function getParams(): array
    {
        if (empty($this->params)) {
            $this->params = request()->all();
        }
        return $this->params;
    }

    /**
     * 过滤查询
     */
    public  function filterQuery($params): Builder
    {
        // 应用自定义查询条件
        $params = $this->customListQuery($params);

        $query = $this->getModel()->newQuery();

        // 应用导出基础查询
        $this->applyExportBaseQuery($query);

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
        $this->exportColumns = $this->getExportFields(); // 获取导出字段
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
        return $this->filterQuery($this->getParams())
            ->offset($this->getPage() * $this->getLimit())
            ->limit($this->getLimit())->get();
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
                $column[] = isset($mappings[$row->$val]) ? strip_tags($mappings[$row->$val]) : '';
                continue;
            }
            $mappingsName = $this->mappingsName($val);
            Log::info('mappingsName: ' . $mappingsName);
            if (!empty($mappingsName)) {
                if (strpos($mappingsName, '.') !== false) {
                    // 通用方式获取关联模型的字段值
                    list($relation, $field) = explode('.', $mappingsName);
                    Log::info('relation: ' . $relation);
                    Log::info('field: ' . $field);
                    // 获取关联模型的字段值
                    $relatedModel = $row->$relation ?? null;
                    $column[] = $relatedModel && isset($relatedModel->$field) ? strip_tags($relatedModel->$field) : '';
                } else {
                    // 直接取对应参数
                    $column[] = isset($row->$mappingsName) ? strip_tags($row->$mappingsName) : '';
                }
                continue;
            }
            $column[] = isset($row->$val) ? strip_tags($row->$val) : '';
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
        if (empty($params['ids']) && $count > $this->getLimit()) {
            $result =  $this->asynchronousExport($params); // 调用异步导出方法
            if ($result['status']) {
                return '开始导出'; // 返回成功信息
            } else {
                throw new ApiException($result['msg']); // 返回错误信息
            }
        } else {
            $filename = $this->filePathSuffix . now()->format('YmdHis') . '.csv'; // 生成文件名
            $this->store($filename, 'public', Excel::CSV); // 存储CSV文件
            // return rtrim(env('APP_URL'), '/') . Storage::url($filename); // 返回文件URL
            return asset('storage/' . $filename); // 返回文件URL
        }
    }

    /**
     * @Author: chaihao
     * @description: 异步导出
     */
    public function asynchronousExport($params)
    {
        try {
            $model = $this->getModel(); // 确保获取模型实例
            if (!$model) {
                throw new ApiException("模型未设置"); // 添加模型未设置的异常处理
            }
            $redisKey = 'AsynchronousExportRedisKey' . $model->getTable(); // Redis键名
            if (Redis::exists($redisKey)) {
                throw new ApiException("正在导出数据, 请稍后..."); // 检查是否已有导出任务
            }
            $limit = $this->getLimit(); // 每次导出的记录数限制
            $count = $this->filterQuery($params)->count(); // 获取符合条件的记录数

            if ($count <= 0) {
                throw new ApiException("数据过滤结果为空,不执行导出操作"); // 记录数为零时抛出异常
            }
            $totalPage = ceil($count / $limit); // 计算总页数
            $fileName  = $this->filePathSuffix . uniqid(now()->format('YmdHis')); // 生成唯一文件名
            for ($i = 0; $i < $totalPage; $i++) {
                if ($i == 0) {
                    Redis::setex($redisKey, $totalPage * 0, $fileName); // 设置Redis过期时间
                }
                // 导出任务
                $this->exportJob($model, $fileName, $params, $i, $limit, $totalPage, $redisKey);
            }
            return ['status' => true, 'msg' => '开始导出', 'fileName' => $fileName]; // 返回导出开始状态
        } catch (ApiException $e) {
            Log::error($e->getMessage()); // 记录错误信息
            throw new ApiException($e->getMessage()); // 捕获异常并抛出
        }
    }
    /**
     * @Author: chaihao
     * @description: 导出任务
     */
    public function exportJob($fileName, $params, $page, $limit, int $totalPage, $redisKey)
    {
        // 不直接传递model对象，而是传递模型的类名
        $serviceClass = get_class($this);
        ExportJob::dispatch($serviceClass, $fileName, $params, $page, $limit, $totalPage, $redisKey)
            ->delay(Carbon::now()->addSeconds($page * 3));
    }
}
