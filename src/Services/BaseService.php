<?php

namespace Chaihao\Rap\Services;

use Chaihao\Rap\Exception\ApiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\{Auth, DB, Validator, Schema, Cache};

abstract class BaseService
{
    protected $model;
    protected array $searchableFields = [];
    protected array $exportableFields = [];
    protected array $allowedSortFields = [];


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
     * 获取列表数据
     */
    public function getList(array $params = []): array
    {
        $cacheKey = $this->generateCacheKey('list', $params);

        if ($this->getModel()->shouldCache()) {
            return Cache::remember($cacheKey, $this->getModel()->getCacheTTL(), function () use ($params) {
                return $this->fetchListData($params);
            });
        }

        return $this->fetchListData($params);
    }

    /**
     * 获取列表数据
     */
    protected function fetchListData(array $params): array
    {
        $query = $this->getModel()->newQuery();

        // 应用基础查询
        $this->applyBaseQuery($query);

        // 应用搜索条件
        $this->applySearchConditions($query, $params);

        // 应用排序
        $this->applySorting($query, $params);

        // 获取分页数据
        $pageSize = $params['page_size'] ?? $this->getModel()->getPageSize();
        $data = $query->paginate($pageSize);

        // 格式化输出
        return $this->formatListOutput($data);
    }



    /**
     * 应用基础查询
     */
    protected function applyBaseQuery($query): void
    {
        // 选择字段
        $query->select($this->getModel()->getListFields());

        // 加载关联
        if (method_exists($this->getModel(), 'listWithRelations')) {
            $query->with($this->getModel()->listWithRelations());
        }

        // 应用创建者范围
        $this->applyCreateByScope($query);
    }


    /**
     * 应用搜索条件
     */
    protected function applySearchConditions($query, array $params): void
    {
        foreach ($params as $field => $value) {
            if (empty($value) && $value !== '0') {
                continue;
            }

            // 处理模糊查询
            if (in_array($field, $this->getModel()->getLikeFilterFields())) {
                $this->applyLikeFilter($query, $field, $value);
                continue;
            }

            // 处理数组条件
            if (is_array($value)) {
                $this->applyArrayFilter($query, $field, $value);
                continue;
            }

            // 处理普通条件
            $query->where($field, $value);
        }
    }

    /**
     * 应用模糊查询
     */
    protected function applyLikeFilter($query, string $field, $value): void
    {
        if (is_array($value)) {
            $query->where(function ($q) use ($field, $value) {
                foreach ($value as $item) {
                    $q->orWhere($field, 'like', "%{$item}%");
                }
            });
            return;
        }

        $query->where($field, 'like', "%{$value}%");
    }


    /**
     * 应用数组过滤
     */
    protected function applyArrayFilter($query, string $field, array $value): void
    {
        if (count($value) === 2 && !str_contains($field, 'status')) {
            $query->whereBetween($field, $value);
        } else {
            $query->whereIn($field, $value);
        }
    }
    /**
     * 应用排序
     */
    protected function applySorting($query, array $params): void
    {
        $orderField = $params['sort_field'] ?? $this->getModel()->getDefaultOrderField();
        $direction = $params['sort_type'] ?? $this->getModel()->getDefaultOrderDirection();

        if (!empty($this->allowedSortFields) && !in_array($orderField, $this->allowedSortFields)) {
            $orderField = $this->getModel()->getDefaultOrderField();
        }

        $query->orderBy($orderField, $direction);

        if ($orderField !== 'id') {
            $query->orderBy('id', 'desc');
        }
    }
    /**
     * 格式化列表输出
     */
    protected function formatListOutput($data): array
    {
        $result = $data->toArray();
        $result['data'] = array_map(function ($item) {
            return $this->getModel()->formatOutput($item);
        }, $result['data']);

        return $result;
    }
    /**
     * 创建记录
     */
    public function add(array $data, bool $validate = true): array
    {
        try {
            DB::beginTransaction();

            if ($validate) {
                $this->checkValidator($data, 'add');
            }

            $data = $this->getModel()->formatAttributes($data);
            $fillableData = $this->filterFillableData($data);

            $this->addCreatorId($fillableData);

            $record = $this->getModel()->create($fillableData);

            $this->clearModelCache();

            DB::commit();
            return $this->success($record);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }
    }
    /**
     * 更新记录
     */
    public function edit(int $id, array $data): array
    {
        try {
            DB::beginTransaction();

            $record = $this->findRecord($id);
            if (!$record) {
                throw new ApiException('记录不存在');
            }

            // $this->checkValidator(array_merge(['id' => $id], $data), 'edit');

            $data = $this->getModel()->formatAttributes($data);
            $fillableData = $this->filterFillableData($data);

            $this->addUpdaterId($fillableData);

            $record->update($fillableData);

            $this->clearModelCache();

            DB::commit();
            return $this->success($record);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }
    }



    /**
     * 生成缓存键名
     * 
     * @param string $type 缓存类型
     * @param array $params 参数数组
     * @return string
     */
    protected function generateCacheKey(string $type, array $params = []): string
    {
        // 基础键名
        $key = sprintf(
            '%s:%s',
            $this->getModel()->getTable(),
            $type
        );

        // 如果有参数,将参数序列化后添加到键名中
        if (!empty($params)) {
            // 移除不需要的参数
            unset($params['page']);

            // 对参数进行排序,确保相同参数生成相同的键名
            ksort($params);

            // 将参数数组转换为字符串
            $paramString = http_build_query($params);

            // 使用md5压缩参数字符串
            $key .= ':' . md5($paramString);
        }

        return $key;
    }

    /**
     * 过滤可填充数据
     * 
     * @param array $data 原始数据
     * @return array 过滤后的数据
     */
    protected function filterFillableData(array $data): array
    {
        $fillable = $this->getModel()->getFillable();

        // 如果没有定义可填充字段,返回原始数据
        if (empty($fillable)) {
            return $data;
        }

        // 只保留可填充字段
        return array_intersect_key(
            $data,
            array_flip($fillable)
        );
    }

    /**
     * 清除模型相关的所有缓存
     */
    protected function clearModelCache(): void
    {
        if ($this->getModel()->shouldCache()) {
            $this->getModel()->flushCache();
        }
    }

    /**
     * 添加创建者ID
     */
    protected function addCreatorId(array &$data): void
    {
        if (!$this->getModel()->shouldRecordOperator()) {
            return;
        }

        $operatorFields = $this->getModel()->getOperatorFields();
        $creatorField = $operatorFields['creator'] ?? null;

        if ($creatorField && in_array($creatorField, $this->getModel()->getFillable())) {
            $data[$creatorField] = $data[$creatorField] ?? Auth::id();
        }
    }

    /**
     * 添加更新者ID
     */
    protected function addUpdaterId(array &$data): void
    {
        if (!$this->getModel()->shouldRecordOperator()) {
            return;
        }

        $operatorFields = $this->getModel()->getOperatorFields();
        $updaterField = $operatorFields['updater'] ?? null;

        if ($updaterField && in_array($updaterField, $this->getModel()->getFillable())) {
            $data[$updaterField] = $data[$updaterField] ?? Auth::id();
        }
    }

    /**
     * 检查记录是否存在
     */
    protected function checkRecordExists(string $field, $value, ?int $excludeId = null): bool
    {
        $query = $this->getModel()->newQuery()
            ->where($field, $value);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 返回成功响应
     */
    protected function success($data = null, string $message = '操作成功'): array
    {
        return [
            'status' => true,
            'code' => 200,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * 返回失败响应
     */
    protected function failed(string $message = '', int $code = 400): array
    {
        return [
            'status' => false,
            'code' => $code,
            'message' => $message,
            'data' => null
        ];
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
        // $this->checkValidator(['id' => $id], 'status');

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
     * 通过ID查找记录
     * 
     * @param int $id 记录ID
     * @return array
     * @throws ApiException
     */
    public function detail(int $id)
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
    public function delete(int $id)
    {
        $record = $this->findRecord($id);
        if (!$record) {
            throw new ApiException('记录不存在', 404);
        }

        $record->delete();
        return $this->message('删除成功');
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
     * 处理数组条件查询
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query 查询构建器
     * @param string $field 字段名
     * @param array $value 条件值
     * @throws \InvalidArgumentException 当数组格式不正确时抛出异常
     */
    private function handleArrayCondition($query, string $field, array $value): void
    {
        // 验证数组不为空
        if (empty($value)) {
            return;
        }

        // 处理直接指定查询方法的情况
        if ($this->isQueryMethodCondition($value)) {
            [$method, $field, $condition] = $value;
            $query->$method($field, $condition);
            return;
        }

        // 处理比较运算符条件
        if ($this->isComparisonCondition($value)) {
            [$field, $operator, $condition] = $value;
            $this->applyComparisonFilter($query, $field, $operator, $condition);
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
     * 检查是否为查询方法条件
     * 
     * @param array $value
     * @return bool
     */
    private function isQueryMethodCondition(array $value): bool
    {
        return count($value) === 3
            && is_string($value[0])
            && in_array($value[0], $this->getAllowedQueryMethods(), true);
    }

    /**
     * 应用比较运算符过滤
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field
     * @param string $operator
     * @param mixed $condition
     */
    private function applyComparisonFilter($query, string $field, string $operator, $condition): void
    {
        if ($operator === 'like') {
            $this->applyLikeFilter($query, $field, $condition);
        } else {
            $query->where($field, $operator, $condition);
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
     * 返回列表成功响应
     */
    protected function successList($data = null, string $message = '操作成功'): array
    {
        return $this->success(
            $data ? paginateFormat($data) : null,
            $message
        );
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

    /**
     * 获取允许的查询方法列表
     * 
     * @return array<string> 允许使用的查询方法名称数组
     */
    private function getAllowedQueryMethods(): array
    {
        return [
            'where',
            'whereIn',
            'whereNotIn',
            'whereBetween',
            'whereNotBetween',
            'whereNull',
            'whereNotNull',
            'whereDate',
            'whereMonth',
            'whereDay',
            'whereYear',
            'whereTime',
            'whereColumn',
            'whereExists',
            'whereNotExists',
            'whereHas',
            'whereDoesntHave',
            'whereMorphedTo',
            'whereJsonContains',
            'whereJsonLength',
        ];
    }
}
