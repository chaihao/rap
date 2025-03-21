<?php

namespace Chaihao\Rap\Services;

use Illuminate\Support\Facades\Log;
use Chaihao\Rap\Exception\ApiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\{Auth, DB, Validator, Schema, Cache};

abstract class BaseService
{
    protected $model;
    protected array $searchableFields = [];
    protected array $exportableFields = [];
    protected array $allowedSortFields = [];

    /**
     * 锁配置
     */
    protected array $lockConfig = [
        'timeout' => 10,      // 锁超时时间（秒）
        'waitTimeout' => 3,   // 等待获取锁超时时间（秒）
        'prefix' => 'lock',   // 锁前缀
    ];

    /**
     * 设置模型
     * 
     * @param Model $model
     */
    public function setModel(Model $model)
    {
        $this->model = $model;
        return $this;
    }

    /**
     * 获取或创建模型实例
     *
     * @return Model
     * @throws \RuntimeException
     */
    public function getModel(): Model
    {
        if (!$this->model) {
            throw new \RuntimeException('Model has not been set.');
        }
        return $this->model;
    }


    /**
     * 获取列表数据
     * 
     * @param array $params 查询参数
     * @return array
     */
    public function getList(array $params = []): array
    {
        // 如果有关联查询,则直接返回关联查询结果
        if ($this->hasListRelations()) {
            return $this->fetchListData($params);
        }

        $cacheKey = $this->generateCacheKey('list', $params);
        if ($this->getModel()->shouldCache()) {
            return Cache::tags($this->getModel()->getCacheTags())->remember($cacheKey, $this->getModel()->getCacheTTL(), function () use ($params) {
                return $this->fetchListData($params);
            });
        }

        return $this->fetchListData($params);
    }

    /**
     * 检查是否有关联查询
     * 
     * @return bool
     */
    protected function hasListRelations(): bool
    {
        return method_exists($this->getModel(), 'listWithRelation') &&
            !empty($this->getModel()->listWithRelation());
    }
    /**
     * 获取列表数据
     * 
     * @param array $params 查询参数
     * @return array
     */
    protected function fetchListData(array $params): array
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

        // 获取分页数据
        $pageSize = $params['page_size'] ?? $this->getModel()->getPageSize();
        $data = $query->paginate($pageSize);

        // 格式化输出
        $this->formatListOutput($data);

        return $this->paginateFormat($data);
    }



    /**
     * 处理查询结果
     * 
     * @param \Illuminate\Pagination\LengthAwarePaginator $data 分页数据
     */
    protected function formatListOutput($data): void
    {
        if (!$data->isEmpty()) {
            $data->setCollection($data->getCollection()->map(function ($item) {
                // 先将模型转换为数组，再进行格式化
                return $this->getModel()->formatOutput($item->toArray());
            }));
        }
    }

    /**
     * 自定义列表查询条件
     */
    public function customListQuery(array $params): array
    {
        return $params;
    }


    /**
     * 应用基础查询
     */
    protected function applyBaseQuery($query): void
    {
        // 选择字段
        $query->select($this->getModel()->getListFields());

        // 加载关联
        if (method_exists($this->getModel(), 'listWithRelation')) {
            $relations = $this->getModel()->listWithRelation();
            if (is_array($relations) && !empty($relations)) {
                $query->with($relations);
            }
        }
        // 应用创建者范围
        $this->applyCreateByScope($query);
    }



    /**
     * 应用导出查询
     */
    protected function applyExportBaseQuery($query): void
    {
        // 选择字段
        $query->select($this->getModel()->getListFields());

        // 加载关联
        if (method_exists($this->getModel(), 'getExportRelation')) {
            $relations = $this->getModel()->getExportRelation();
            if (is_array($relations) && !empty($relations)) {
                $query->with($relations);
            }
        }
        // 应用创建者范围
        $this->applyCreateByScope($query);
    }

    /**
     * 获取导出字段
     */
    public function getExportFields(): array
    {
        return $this->getModel()->getValidatorAttributes();
    }

    /**
     * 应用搜索条件
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $params 查询参数
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applySearchConditions($query, array $params): Builder
    {
        // 参数处理
        $fillable = $this->getModel()->getFillable();
        foreach ($params as $field => $value) {
            // 检查字段是否可填充
            if (!in_array($field, $fillable)) {
                continue;
            }
            // 如果值为空或未定义，则跳过
            if (is_null($value) || (is_string($value) && trim($value) === '' && $value !== '0')) {
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

            // json字段查询 (使用与json数组, json 对象不适用) 
            if (in_array($field, $this->jsonFilter())) {
                $this->applyJsonFilter($query, $field, $value);
                continue;
            }

            // 处理普通条件
            $query->where($field, $value);
        }

        return $query;
    }
    /**
     * 获取需要 JSON 过滤的字段
     * 
     * @return array
     */
    protected function jsonFilter(): array
    {
        // 检测 $casts 中是否包含 json
        $casts = $this->getModel()->getCasts();
        // 获取所有被转换为 array 类型的字段名
        return array_keys($casts, 'array');
    }

    /**
     * 应用 JSON 过滤条件
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field
     * @param mixed $value
     */
    protected function applyJsonFilter($query, string $field, $value): void
    {
        $query->whereJsonContains($field, $value);
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
     * 创建记录
     * 
     * @param array $data 创建数据
     * @param bool $validate 是否验证数据
     * @return Model
     * @throws ApiException
     */
    public function add(array $data, bool $validate = true): Model
    {
        try {
            DB::beginTransaction();

            // 验证数据
            if ($validate) {
                $this->checkValidator($data, 'add');
            }

            // 添加前的数据处理
            $data = $this->beforeAdd($data);

            // 格式化数据
            $data = $this->getModel()->formatAttributes($data);
            // 过滤可填充数据,传入add场景
            $fillableData = $this->filterFillableData($data, 'add');

            // 添加创建者ID
            $this->addCreatorId($fillableData);

            // 创建记录
            $record = $this->getModel()->create($fillableData);

            DB::commit();
            return $record;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 添加前的数据处理
     */
    public function beforeAdd(array $data): array
    {
        return $data;
    }
    /**
     * 更新记录
     * 
     * @param int $id 记录ID
     * @param array $data 更新数据
     * @throws ApiException
     * @return Model
     */
    public function edit(int $id, array $data): Model
    {
        try {
            DB::beginTransaction();

            // 查找记录
            $record = $this->findRecord($id);
            if (!$record) {
                throw new ApiException('记录不存在');
            }
            // 编辑前的数据处理
            $data = $this->beforeEdit($data);

            // 格式化数据
            $data = $this->getModel()->formatAttributes($data);

            // 过滤可填充数据,传入edit场景
            $fillableData = $this->filterFillableData($data, 'edit');

            // 添加更新者ID
            $this->addUpdaterId($fillableData);
            // 更新记录
            $record->update($fillableData);

            DB::commit();
            return $record;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }
    }
    /**
     * 编辑前的数据处理
     */
    public function beforeEdit(array $data): array
    {
        return $data;
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
            // unset($params['page']);

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
     * @param string $scenario 场景名称
     * @return array 过滤后的数据
     */
    protected function filterFillableData(array $data, string $scenario = ''): array
    {
        // 获取可填充字段
        $fillable = $this->getModel()->getFillable();

        // 如果没有定义可填充字段,返回原始数据
        if (empty($fillable)) {
            return $data;
        }

        // 如果指定了场景且模型中定义了对应场景的字段限制
        if (
            $scenario && property_exists($this->getModel(), 'scenarios')
            && isset($this->getModel()->scenarios[$scenario])
        ) {
            // 使用场景中定义的字段与可填充字段的交集
            $fillable = array_intersect(
                $this->getModel()->scenarios[$scenario],
                $fillable
            );
        }

        // 只保留可填充字段
        return array_intersect_key(
            $data,
            array_flip($fillable)
        );
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
        // 如果不需要记录操作者,则不添加更新者ID
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
            'detail' => 'adjustRuleForDetail',
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
    private function adjustRuleForDetail(string $rule, string $field, array $data): string
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
     * @throws ApiException
     */
    public function editStatus(int $id, ?int $status = null): Model
    {
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

            DB::commit();
            return $record;
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
        // 完全禁用有关联查询时的缓存
        if ($this->hasGetRelations()) {
            return $this->fetchDetailData($id);
        }

        $cacheKey = $this->generateCacheKey('detail', ['id' => $id]);
        if ($this->getModel()->shouldCache()) {
            return Cache::tags($this->getModel()->getCacheTags($id))->remember($cacheKey, $this->getModel()->getCacheTTL(), function () use ($id) {
                return $this->fetchDetailData($id);
            });
        }

        return $this->fetchDetailData($id);
    }

    /**
     * 检查是否有关联查询
     * 
     * @return bool
     */
    protected function hasGetRelations(): bool
    {
        return method_exists($this->getModel(), 'getWithRelation') &&
            !empty($this->getModel()->getWithRelation());
    }


    /**
     * 获取详情数据
     * 
     * @param int $id 记录ID
     * @return array
     * @throws ApiException
     */
    public function fetchDetailData(int $id)
    {
        try {
            // 验证ID
            $this->checkValidator(['id' => $id], 'get');

            $query = $this->getModel()->newQuery();

            // 加载关联关系
            $this->loadRelations($query);

            // 应用创建者范围
            $this->applyCreateByScope($query);

            // 查找记录
            $info = $query->find($id);
            if (!$info) {
                throw new ApiException('记录不存在', 404);
            }

            // 格式化数据
            $info = $this->formatData($info);

            return $info;
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
    public function delete(int $id): bool
    {
        $record = $this->findRecord($id);
        if (!$record) {
            throw new ApiException('记录不存在', 404);
        }

        $record->delete();
        return true;
    }

    /**
     * 批量删除
     * 
     * @param array $ids 记录ID数组
     */
    public function batchDelete(array $ids)
    {
        // 使用事务确保批量删除的原子性
        return DB::transaction(function () use ($ids) {
            return $this->getModel()->whereIn('id', $ids)->delete() > 0; // 返回是否有记录被删除
        });
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
        // 如果模型有 listWithRelation 方法，则应用相关的关联
        if (method_exists($this->getModel(), 'listWithRelation')) {
            $query->with($this->getModel()->listWithRelation());
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
            $data ? $this->paginateFormat($data) : null,
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
     * @param array $attributes 自定义参数说明
     * @throws ApiException
     */
    public function checkValidator(array $data, string|array $scenario, array $message = [], array $attributes = []): void
    {
        $rules = is_string($scenario) ? $this->getRulesByScenario($scenario, $data) : $scenario;

        $message = $message ?: $this->getValidatorMessage();
        // 验证数据
        $validator = Validator::make($data, $rules, $message);

        // 如果没有传入自定义属性，则使用模型中定义的属性
        $customAttributes = $attributes ?: $this->getValidatorAttributes();

        if (!empty($customAttributes)) {
            $validator->setAttributeNames($customAttributes);
        }
        if ($validator->fails()) {
            throw ApiException::validationError($validator->errors()->first() ?? []);
        }
    }
    /**
     * 获取验证器错误信息
     * @return array
     */
    public function getValidatorMessage(): array
    {
        if (method_exists($this->getModel(), 'setValidatorMessage')) {
            return $this->getModel()->setValidatorMessage();
        }
        return [];
    }

    /**
     * 获取验证器自定义属性
     * @return array
     */
    public function getValidatorAttributes(): array
    {
        if (method_exists($this->getModel(), 'getValidatorAttributes')) {
            return $this->getModel()->getValidatorAttributes();
        }
        return [];
    }
    /**
     * 获取单条记录
     * @param array $params 查询参数
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getOne($params, string $errorMessage = '记录不存在')
    {
        $data = $this->getModel()->where($params)->first();
        if (!$data) {
            throw new ApiException($errorMessage);
        }
        return $data;  // 返回的是模型实例(对象)
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
        // 查找记录
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
        if (method_exists($this->getModel(), 'getWithRelation')) {
            $relations = $this->getModel()->getWithRelation();
            if (is_array($relations) && !empty($relations)) {
                $query->with($relations);
            }
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

    /**
     * 格式化分页数据
     * 
     * @param \Illuminate\Pagination\LengthAwarePaginator $data
     * @return array
     */
    protected function paginateFormat($data): array
    {

        $pageSizeColumn = config('rap.paginate_columns.page_size', 'page_size');
        $lastPageColumn = config('rap.paginate_columns.last_page', 'last_page');
        $pageColumn = config('rap.paginate_columns.page', 'page');
        $totalColumn = config('rap.paginate_columns.total', 'total');
        $listColumn = config('rap.paginate_columns.list', 'list');

        return [
            $listColumn => $data->items(),
            $totalColumn => $data->total(),
            $pageColumn => $data->currentPage(),
            $lastPageColumn => $data->lastPage(),
            $pageSizeColumn => $data->perPage(),
        ];
    }

    /**
     * 使用锁执行操作
     * 
     * @param string $lockKey 锁键名
     * @param callable $callback 需要在锁内执行的操作
     * @param array $options 自定义选项
     * @return mixed
     * @throws ApiException
     */
    protected function withLock(string $lockKey, callable $callback, array $options = []): mixed
    {
        // 合并自定义选项
        $config = array_merge($this->lockConfig, $options);

        // 获取锁实例
        $lock = $this->getLock($lockKey, $config);

        try {
            // 尝试获取锁
            if (!$this->acquireLock($lock, $config['waitTimeout'])) {
                throw new ApiException('操作太频繁，请稍后再试', 429);
            }

            // 执行回调
            return $callback();
        } catch (\Throwable $e) {
            // 记录错误日志
            Log::error('Lock operation failed', [
                'key' => $lockKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        } finally {
            // 确保锁被释放
            $this->releaseLock($lock);
        }
    }

    /**
     * 获取锁实例
     */
    protected function getLock(string $key, array $config): \Illuminate\Contracts\Cache\Lock
    {
        return Cache::lock(
            $this->getFullLockKey($key),
            $config['timeout']
        );
    }

    /**
     * 获取完整的锁键名
     */
    protected function getFullLockKey(string $key): string
    {
        return sprintf('%s:%s', $this->lockConfig['prefix'], $key);
    }

    /**
     * 尝试获取锁
     */
    protected function acquireLock(\Illuminate\Contracts\Cache\Lock $lock, int $waitTimeout): bool
    {
        return $lock->block($waitTimeout);
    }

    /**
     * 释放锁
     */
    protected function releaseLock(\Illuminate\Contracts\Cache\Lock $lock): void
    {
        try {
            optional($lock)->release();
        } catch (\Throwable $e) {
            Log::warning('Failed to release lock', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 生成锁键名
     */
    protected function generateLockKey(string $operation, $identifier = null, string $suffix = null): string
    {
        $parts = [
            $this->getModel()->getTable(),
            $operation
        ];

        if ($identifier !== null) {
            $parts[] = $identifier;
        }

        if ($suffix !== null) {
            $parts[] = $suffix;
        }

        return implode(':', $parts);
    }

    /**
     * 创建记录（带锁）
     */
    public function addWithLock(array $data, bool $validate = true, array $lockOptions = []): Model
    {
        $lockKey = $this->generateLockKey('add');

        return $this->withLock($lockKey, function () use ($data, $validate) {
            return DB::transaction(function () use ($data, $validate) {
                return $this->add($data, $validate);
            });
        }, $lockOptions);
    }

    /**
     * 更新记录（带锁）
     */
    public function editWithLock(int $id, array $data, array $lockOptions = []): Model
    {
        $lockKey = $this->generateLockKey('edit', $id);

        return $this->withLock($lockKey, function () use ($id, $data) {
            return DB::transaction(function () use ($id, $data) {
                return $this->edit($id, $data);
            });
        }, $lockOptions);
    }

    /**
     * 批量操作带锁
     */
    public function batchWithLock(string $operation, array $ids, callable $callback, array $lockOptions = []): mixed
    {
        $lockKey = $this->generateLockKey('batch', implode('-', $ids), $operation);

        return $this->withLock($lockKey, function () use ($callback) {
            return DB::transaction($callback);
        }, $lockOptions);
    }
}
