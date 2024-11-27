<?php

namespace Chaihao\Rap\Services;

use Chaihao\Rap\Exception\ApiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BaseService
{
    protected $model;

    /**
     * 设置模型
     * 
     * @param Model $model
     */
    public function setModel(Model $model)
    {
        $this->model = $model;
    }

    /**
     * 获取或创建模型实例
     *
     * @return Model
     * @throws \RuntimeException
     */
    protected function getModel(): Model
    {
        if (!$this->model) {
            throw new \RuntimeException('Model has not been set.');
        }
        return $this->model;
    }

    /**
     * 获取验证规则
     * 
     * @param string $scenario 场景名称
     * @param array $data 需要验证的数据
     * @return array
     */
    public function getRules(string $scenario = '', array $data = []): array
    {
        if (empty($scenario)) {
            return $this->getModel()->rules ?? [];
        }
        return $this->getRulesByScenario($scenario, $data);
    }

    /**
     * 根据场景获取验证规则
     * 
     * @param string $scenario 场景名称（如 'add', 'edit'）
     * @param array $data 需要验证的数据
     * @return array 根据场景筛选后的验证规则
     */
    protected function getRulesByScenario(string $scenario, array $data): array
    {
        $allRules = $this->getRules();
        $scenarioRules = [];

        $fields = $this->getScenarioFields($scenario, $data);
        foreach ($fields as $field) {
            $scenarioRules[$field] = $this->getFieldRule($field, $scenario, $allRules, $data);
        }

        return $scenarioRules;
    }

    /**
     * 获取指定场景的字段列表
     * 
     * @param string $scenario 场景名称
     * @param array $data 需要验证的数据
     * @return array 场景对应的字段列表
     */
    private function getScenarioFields(string $scenario, array $data): array
    {
        return property_exists($this->getModel(), 'scenarios') && isset($this->getModel()->scenarios[$scenario])
            ? $this->getModel()->scenarios[$scenario]
            : array_keys($data);
    }

    /**
     * 获取单个字段的验证规则
     * 
     * @param string $field 字段名
     * @param string $scenario 场景名称
     * @param array $allRules 所有验证规则
     * @param array $data 需要验证的数据
     * @return string 字段的验证规则
     */
    private function getFieldRule(string $field, string $scenario, array $allRules, array $data): string
    {
        if (!isset($allRules[$field]) && $field !== 'id') {
            return '';
        }

        $rule = $allRules[$field] ?? '';

        $adjustmentMethods = [
            'edit' => 'adjustRuleForEdit',
            'get' => 'adjustRuleForGet',
            'status' => 'adjustRuleForStatus',
        ];

        if (isset($adjustmentMethods[$scenario])) {
            $method = $adjustmentMethods[$scenario];
            $rule = $this->$method($rule, $field, $data);
        }

        return $rule;
    }

    /**
     * 调整编辑状态场景下的验证规则
     * 
     * @param string $rule 原始规则
     * @param string $field 字段名
     * @param array $data 需要验证的数据
     * @return string 调整后的规则
     */
    private function adjustRuleForStatus(string $rule, string $field, array $data): string
    {
        return $field === 'id' ? 'required|integer|exists:' . $this->getTable() . ',id' : $rule;
    }

    /**
     * 调整获取场景下的验证规则
     * 
     * @param string $rule 原始规则
     * @param string $field 字段名
     * @param array $data 需要验证的数据
     * @return string 调整后的规则
     */
    private function adjustRuleForGet(string $rule, string $field, array $data): string
    {
        return $field === 'id' ? 'required|integer|exists:' . $this->getTable() . ',id' : $rule;
    }

    /**
     * 调整编辑场景下的验证规则
     *    
     * @param string $rule 原始规则
     * @param string $field 字段名
     * @param array $data 需要验证的数据
     * @return string 调整后的规则
     */
    private function adjustRuleForEdit(string $rule, string $field, array $data): string
    {
        $rule = preg_replace('/\b(required\|?|(\|required))\b/', '', $rule);

        if (strpos($rule, 'unique:') !== false) {
            $rule .= ',' . ($data['id'] ?? '');
        }

        if ($field === 'id') {
            $rule = 'required|integer|exists:' . $this->getTable() . ',id';
        }

        return $rule;
    }

    /**
     * 获取表名
     * 
     * @return string
     */
    public function getTable(): string
    {
        return $this->getModel()->getTable();
    }


    /**
     * 编辑状态
     * 
     * @param int $id 记录ID
     * @param int|null $status 新状态，如果为null则切换当前状态
     * @return array
     * @throws ApiException
     */
    public function editStatus(int $id, ?int $status = null): array
    {
        // 验证输入数据
        $this->checkValidator(['id' => $id], 'status');

        try {
            DB::beginTransaction();

            // 使用现有的findRecord方法查找记录
            $record = $this->findRecord($id);
            if (!$record) {
                throw new ApiException('记录不存在', 404);
            }

            // 更新状态
            $record->status = $status ?? !$record->status;
            $record->save();

            // 清除缓存
            $this->clearModelCache();

            DB::commit();
            return $this->message('状态更新成功');
        } catch (ApiException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw new ApiException('状态更新失败', 400);
        }
    }

    /**
     * 创建新记录
     * 
     * @param array $data 要创建的数据
     * @param bool $isCheckData 是否检查数据
     * @return array
     * @throws ApiException
     */
    public function add(array $data, bool $isCheckData = false): array
    {
        try {
            DB::beginTransaction();

            // 如果需要检查数据，则进行验证
            if ($isCheckData) {
                $this->checkValidator($data, 'add');
            }

            // 获取可填充字段并过滤数据
            $fillableData = $this->getModel()->getFillable();
            $filteredData = array_intersect_key($data, array_flip($fillableData));

            // 添加创建者ID
            $this->addCreatorId($filteredData);

            // 创建新记录
            $record = $this->getModel()->create($filteredData);

            // 清除缓存
            $this->clearModelCache();

            DB::commit();
            return $this->success($record, '创建成功');
        } catch (ApiException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw new ApiException('创建失败: ' . $th->getMessage(), 400);
        }
    }

    /**
     * 通过ID更新记录
     * 
     * @param int $id 记录ID
     * @param array $params 要更新的数据
     * @return array
     * @throws ApiException
     */
    public function edit(int $id, array $params)
    {
        try {
            DB::beginTransaction();

            // 合并ID到参数中用于验证
            $params['id'] = $id;

            // 查找记录
            $record = $this->findRecord($id);
            if (!$record) {
                throw new ApiException('记录不存在', 404);
            }

            // 根据场景过滤参数
            $params = $this->filterParamsByScenario($record, $params, 'edit');

            // 添加更新者ID
            $this->addUpdaterId($params);

            // 更新记录
            $record->fill($params)->save();

            // 清除缓存
            $this->clearModelCache();

            DB::commit();
            return $this->message('编辑成功');
        } catch (ApiException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw new ApiException('编辑失败', 400);
        }
    }


    /**
     * 清除模型缓存
     */
    protected function clearModelCache(): void
    {
        // 检测模型是否支持缓存
        if (method_exists($this->getModel(), 'shouldCache')) {
            // 如果支持缓存，则清除缓存
            if ($this->getModel()->shouldCache()) {
                $this->getModel()->flushCache();
            }
        }
    }


    /**
     * 通过ID查找记录
     * 
     * @param int $id 记录ID
     * @return array
     * @throws ApiException
     */
    public function get(int $id)
    {
        try {
            // 验证ID
            $this->checkValidator(['id' => $id], 'get');

            $query = $this->getModel()->newQuery();

            // 加载关联关系
            $this->loadRelations($query);

            // 应用创建者范围
            $this->applyCreateByScope($query);

            // 添加缓存支持
            $this->applyCacheSupport($query);

            // 查找记录
            $info = $query->find($id);
            if (!$info) {
                throw new ApiException('记录不存在', 404);
            }

            // 格式化数据
            $info = $this->formatData($info);

            return $this->success($info);
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $th) {
            throw new ApiException('获取记录失败', 400);
        }
    }

    /**
     * 格式化数据
     * 
     * @param mixed $data 数据
     * @return mixed
     */
    public function formatData($data)
    {
        return $data;
    }

    /**
     * 删除记录
     * 
     * @param int $id 记录ID
     */
    public function del(int $id)
    {
        $record = $this->findRecord($id);
        if (!$record) {
            throw new ApiException('记录不存在', 404);
        }

        $record->delete();
        return $this->message('删除成功');
    }


    /**
     * 获取列表数据 - 优化查询性能
     * 
     * @param array $params 查询参数
     * @return array 包含分页数据的数组
     */
    public function getList(array $params = []): array
    {
        $query = $this->getModel()->newQuery();

        $this->applyListFields($query);

        $this->applyListRelations($query);
        // 应用创建者范围
        $this->applyCreateByScope($query);
        // 应用过滤条件
        $this->applyFilters($query, $params);
        // 应用排序
        $this->applySorting($query, $params);

        // 添加缓存支持
        $this->applyCacheSupport($query);

        $data = $query->paginate($params['page_size'] ?? 20);

        // 返回成功的列表数据
        return paginateFormat($data);
    }



    /**
     * 添加缓存支持   
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器实例
     * @param int $ttl 缓存时间（秒）
     */
    protected function applyCacheSupport($query, int $ttl = 3600): void
    {
        // 检测模型是否支持缓存
        if (method_exists($this->getModel(), 'shouldCache')) {
            // 如果支持缓存，则添加缓存
            if ($this->getModel()->shouldCache()) {
                $query->remember($ttl);
            }
        }
    }



    /**
     * 应用列表字段
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器实例
     */
    protected function applyListFields($query): void
    {
        // 使用 select 指定需要的字段，避免查询所有字段
        if (method_exists($this->getModel(), 'getListFields')) {
            $query->select($this->getModel()->getListFields());
        }
    }

    /**
     * 应用列表关联
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器实例
     */
    protected function applyListRelations($query): void
    {
        // 如果模型有 listWithRelations 方法，则应用相关的关联
        if (method_exists($this->getModel(), 'listWithRelations')) {
            $query->with($this->getModel()->listWithRelations());
        }
    }

    /**
     * 应用创建者范围
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器实例
     */
    protected function applyCreateByScope($query): void
    {
        // 如果模型有 scopeGetCreateBy 方法，则应用该范围
        if (method_exists($this->getModel(), 'scopeGetCreateBy')) {
            $query->getCreateBy();
        }
    }

    /**
     * 应用过滤条件
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器实例
     * @param array $params 查询参数
     */
    protected function applyFilters($query, array $params): void
    {
        // 获取模型的可填充字段
        $fillable = $this->getModel()->getFillable();

        foreach ($params as $field => $value) {
            // 跳过无效字段和空值
            if ($this->shouldSkipField($field, $value, $fillable)) {
                continue;
            }

            // 处理模糊搜索字段
            if (in_array($field, $this->likeFilter())) {
                $this->applyLikeFilter($query, $field, $value);
                continue;
            }

            // 处理数组条件
            if (is_array($value)) {
                $this->handleArrayCondition($query, $field, $value);
            } else {
                $query->where($field, $value);
            }
        }
    }

    /**
     * 判断是否应该跳过该字段
     * 
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @param array $fillable 可填充字段
     * @return bool
     */
    private function shouldSkipField(string $field, $value, array $fillable): bool
    {
        return !in_array($field, $fillable)
            || $field === 'limit'
            || $value === null
            || $value === '';
    }

    /**
     * 处理数组条件
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field
     * @param array $value
     */
    private function handleArrayCondition($query, string $field, array $value): void
    {
        // 处理比较运算符条件
        if ($this->isComparisonCondition($value)) {
            $this->applyComparisonFilter($query, $value);
            return;
        }

        // 处理区间或IN条件
        $this->applyArrayFilter($query, $field, $value);
    }

    /**
     * 判断是否为比较运算符条件
     * 
     * @param array $value
     * @return bool
     */
    private function isComparisonCondition(array $value): bool
    {
        $operators = ['=', '>', '<', '>=', '<=', '<>', 'like'];
        return count($value) === 3 && in_array($value[1], $operators);
    }

    /**
     * 应用比较运算符过滤
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $value
     */
    private function applyComparisonFilter($query, array $value): void
    {
        if ($value[1] === 'like') {
            $this->applyLikeFilter($query, $value[0], $value[2]);
        } else {
            $query->where($value[0], $value[1], $value[2]);
        }
    }

    /**
     * 获取like过滤字段
     * 
     * @return array
     */
    public function likeFilter(): array
    {
        // 基础的模糊匹配字段
        $defaultLikeFields = ['name', 'title'];

        // 如果模型定义了自定义的模糊匹配字段，则合并
        if (method_exists($this->getModel(), 'getLikeFilterFields')) {
            return array_merge($defaultLikeFields, $this->getModel()->getLikeFilterFields());
        }

        return $defaultLikeFields;
    }

    /**
     * 应用like过滤条件
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器实例
     * @param string $field 字段名
     * @param string|array $value 字段值
     */
    public function applyLikeFilter($query, string $field, $value): void
    {
        // 如果模型定义了自定义的模糊匹配处理方法
        $methodName = 'apply' . ucfirst($field) . 'LikeFilter';
        if (method_exists($this->getModel(), $methodName)) {
            $this->getModel()->$methodName($query, $value);
            return;
        }

        // 处理数组形式的模糊匹配
        if (is_array($value)) {
            $query->where(function ($q) use ($field, $value) {
                foreach ($value as $item) {
                    $q->orWhere($field, 'like', '%' . $item . '%');
                }
            });
            return;
        }

        // 默认的模糊匹配处理
        $query->where($field, 'like', '%' . $value . '%');
    }



    /**
     * 应用数组过滤条件
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器实例
     * @param string $field 字段名
     * @param array $value 字段值（数组）
     */
    protected function applyArrayFilter($query, string $field, array $value): void
    {
        // 如果数组有两个元素且字段名不包含 'status'，则使用 whereBetween
        if (count($value) == 2 && strpos($field, 'status') === false) {
            $query->whereBetween($field, $value);
        } else {
            // 否则使用 whereIn
            $query->whereIn($field, $value);
        }
    }

    /**
     * 应用排序
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $params
     */
    protected function applySorting($query, array $params): void
    {
        // 获取默认排序字段
        $orderField = $this->getDefaultOrderField($params);

        // 获取排序方向
        $direction = $params['sort_type'] ?? 'desc';

        // 应用排序
        $query->orderBy($orderField, $direction);

        // 如果不是按ID排序,添加ID作为第二排序字段
        if ($orderField !== 'id') {
            $query->orderBy('id', 'desc');
        }
    }

    /**
     * 获取默认排序字段
     */
    private function getDefaultOrderField(array $params): string
    {
        // 如果指定了排序字段则使用
        if (!empty($params['sort_field'])) {
            return $params['sort_field'];
        }

        // 检查模型是否支持sort字段排序
        $fillable = $this->getModel()->getFillable();
        if (in_array('sort', $fillable)) {
            return 'sort';
        }

        // 默认使用id排序
        return 'id';
    }


    /**
     * 将价格从元转换为分
     *
     * @param float|string $price 输入的价格（元）
     * @return int 转换后的价格（分）
     */
    public function convertPriceToCents($price): int
    {
        $price = floatval($price);
        return (int)round($price * 100);
    }

    /**
     * 返回重组数据
     * @param mixed $data
     * @param string $message
     * @return array
     */
    public function successList(mixed $data = [], string $message = '处理成功'): array
    {
        return [
            'status' => true,
            'code' => 200,
            'data' => paginateFormat($data),
            'message' => $message,
        ];
    }

    /**
     * 返回成功数据
     * @param mixed $data
     * @param string $message
     * @return array
     */
    public function success(mixed $data = [], string $message = '处理成功'): array
    {
        return [
            'status' => true,
            'code' => 200,
            'data' => $data,
            'message' => $message,
        ];
    }

    /**
     * 返回成功消息提示
     * @param string $message
     * @return array
     */
    public function message(string $message = '操作成功'): array
    {
        return [
            'status' => true,
            'code' => 200,
            'message' => $message,
        ];
    }

    /**
     * 返回错误信息
     * @param string $message
     * @param int $code
     */
    public function failed(string $message, int $code = 400)
    {
        return [
            'status' => false,
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * 验证数据
     * 
     * @param array $data 需要验证的数据
     * @param string|array $scenario 场景名称|验证规则
     * @param array $message 错误信息
     * @throws ApiException
     */
    public function checkValidator(array $data, string|array $scenario, array $message = []): void
    {
        if (is_string($scenario)) {
            $rules = $this->getRulesByScenario($scenario, $data);
        } else {
            $rules = $scenario;
        }

        $validator = Validator::make($data, $rules, $message);
        if ($validator->fails()) {
            throw new ApiException($validator->errors()->first() ?? [], 400);
        }
    }

    /**
     * 获取单条记录
     * @param array $params 查询参数
     * @return array
     */
    public function getOne(array $params = [])
    {
        $data = $this->getModel()->where($params)->first();
        if (!$data) {
            throw new ApiException('记录不存在');
        }
        return $data;
    }


    /**
     * 根据字段进行自增
     * @param array $where 条件
     * @param string $field 自增字段
     * @param int $value 值
     * @return bool
     */
    public function increment(array $where = [], string $field = "", int $value = 1): bool
    {
        return (bool)$this->getModel()->where($where)->lockForUpdate()->increment($field, $value);
    }

    /**
     * 根据条件进行自减
     * @param array $where 条件
     * @param string $field 自减字段
     * @param int $value 值（正数）
     * @return bool
     */
    public function decrement(array $where = [], string $field = "", int $value = 1): bool
    {
        return (bool)$this->getModel()->where($field, ">", 0)->where($where)->lockForUpdate()->decrement($field, $value);
    }



    /**
     * 根据条件判断是否存在
     * @param string $field
     * @param string $value
     * @return bool
     */
    public function checkExist(string $field = "", string $value = ""): bool
    {
        return $this->getModel()->where($field, $value)->exists();
    }

    /**
     * 搜索方法
     * 
     * @param array $params 搜索参数
     * @param int $pageSize 每页数量
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function search(array $params = [], int $pageSize = 20)
    {
        $query = $this->getModel()->newQuery();

        foreach ($params as $field => $value) {
            // 跳过空值
            if (empty($value) && $value !== 0) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } elseif (strpos($value, '%') !== false) {
                // 只在确实有对应索引时才强制使用索引
                if (Schema::hasIndex($this->getTable(), "idx_{$field}")) {
                    $query->where($field, 'like', $value)->forceIndex("idx_{$field}");
                } else {
                    $query->where($field, 'like', $value);
                }
            } else {
                $query->where($field, $value);
            }
        }

        return $query->paginate($pageSize);
    }

    /**
     * 添加创建者ID到数据中
     * 
     * @param array &$data 引用传递，直接修改原数组
     */
    private function addCreatorId(array &$data): void
    {
        $fillableData = $this->getModel()->getFillable();
        if (in_array('created_user_id', $fillableData)) {
            $data['created_user_id'] = $data['created_user_id'] ?? Auth::id();
        } elseif (in_array('created_by', $fillableData)) {
            $data['created_by'] = $data['created_by'] ?? Auth::id();
        }
    }

    /**
     * 添加更新者ID到数据中
     * 
     * @param array &$data 引用传递，直接修改原数组
     */
    private function addUpdaterId(array &$data): void
    {
        $fillableData = $this->getModel()->getFillable();
        if (in_array('updated_user_id', $fillableData)) {
            $data['updated_user_id'] = $data['updated_user_id'] ?? Auth::id();
        } elseif (in_array('updated_by', $fillableData)) {
            $data['updated_by'] = $data['updated_by'] ?? Auth::id();
        }
    }

    /**
     * 查找记录并应用创建者范围
     * 
     * @param int $id 记录ID
     * @return Model|null
     */
    public function findRecord(int $id): ?Model
    {
        $query = $this->getModel()->newQuery();
        if (method_exists($this->getModel(), 'scopeGetCreateBy')) {
            $query->getCreateBy();
        }
        return $query->find($id);
    }

    /**
     * 根据场景过滤参数
     * 
     * @param Model $record 记录实例
     * @param array $params 原始参数
     * @param string $scenario 场景名称
     * @return array 过滤后的参数
     */
    private function filterParamsByScenario(Model $record, array $params, string $scenario): array
    {
        $scenarios = $record->scenarios ?? [];
        if (isset($scenarios[$scenario])) {
            return array_intersect_key($params, array_flip($scenarios[$scenario]));
        }
        return $params;
    }

    /**
     * 加载关联关系
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function loadRelations($query): void
    {
        if (method_exists($this->getModel(), 'getWithRelations')) {
            $relations = $this->getModel()->getWithRelations();
            $query->with($relations);
        }
    }
}
