<?php

namespace Chaihao\Rap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

abstract class BaseModel extends Model
{
    // 只在确实需要软删除时才使用 SoftDeletes
    // use SoftDeletes;  // 注释掉或根据需求决定是否使用

    protected $table = '';
    protected $fillable = [];

    protected $casts = [];  // 默认为空

    // 验证规则
    public $rules = [];
    public $scenarios = [];

    // 查询配置
    protected array $defaultLikeFields = ['name', 'title'];
    protected string $defaultOrderField = 'id';
    protected string $defaultOrderDirection = 'desc';
    protected int $defaultPageSize = 20;

    // 操作者记录配置
    protected bool $recordOperator = true;
    protected array $operatorFields = [
        'creator' => 'created_by',
        'updater' => 'updated_by'
    ];

    // 缓存配置
    protected bool $modelCache = false;
    protected int $cacheTTL = 3600;
    protected string $cachePrefix = '';

    // 状态常量
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    /**
     * 关联模型配置
     * 子类可以重写此属性来定义需要同步清理缓存的关联关系
     * 
     * 使用示例
     * protected function cacheRelations(): array
     * {
     *     return [
     *   // 关联模型名称 => 关联模型ID
     *         'classify' => function ($related) {
     *             return $related->classify_id;
     *         },
     *     ];
     * }
     */
    protected function cacheRelations(): array
    {
        return [];
    }

    /**
     * 初始化模型
     */
    protected function initializeTrait()
    {
        // 动态设置 casts
        if (Schema::hasColumn($this->getTable(), 'created_at')) {
            $this->casts['created_at'] = 'datetime';
        }
        if (Schema::hasColumn($this->getTable(), 'updated_at')) {
            $this->casts['updated_at'] = 'datetime';
        }
        if (Schema::hasColumn($this->getTable(), 'deleted_at')) {
            $this->casts['deleted_at'] = 'datetime';
        }
    }

    /**
     * 获取状态列表
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_INACTIVE => '禁用',
            self::STATUS_ACTIVE => '启用'
        ];
    }

    /**
     * 获取缓存键名
     */
    protected function getCacheKey(string $key): string
    {
        return sprintf(
            '%s:%s:%s',
            $this->cachePrefix ?: $this->getTable(),
            $this->getConnection()->getName(),
            $key
        );
    }

    /**
     * 是否启用缓存
     */
    public function shouldCache(): bool
    {
        return $this->modelCache;
    }

    /**
     * 获取所有相关的缓存标签
     */
    protected function getAllCacheTags(int $id): array
    {
        $tags = [
            $this->getDetailCacheTag($id),
            $this->getListCacheTag()
        ];

        // 添加自定义查询标签
        $tags[] = $this->getTable() . '_query';

        return $tags;
    }

    /**
     * 优化后的缓存清理方法
     */
    public function flushCache(int $id)
    {
        if ($this->shouldCache()) {
            try {
                $tags = $this->getAllCacheTags($id);
                Log::info("清理缓存: {$this->getTable()} - ID: {$id}, Tags: " . implode(', ', $tags));

                Cache::tags($tags)->flush();

                // 触发缓存清理事件（如果需要）
                event('model.cache.flushed', [
                    'model' => $this,
                    'id' => $id,
                    'tags' => $tags
                ]);
            } catch (\Exception $e) {
                Log::error("缓存清理失败: {$this->getTable()} - ID: {$id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * 缓存数据时使用的标签
     */
    public function getCacheTags($id = null): array
    {
        $tags = [$this->getListCacheTag()];

        if ($id) {
            $tags[] = $this->getDetailCacheTag($id);
        }

        // 添加查询标签
        $tags[] = $this->getTable() . '_query';

        return $tags;
    }

    public function getDetailCacheTag(int $id)
    {
        return $this->getTable() . '_detail_' . $id;
    }

    public function getListCacheTag()
    {
        return $this->getTable() . '_list';
    }

    /**
     * 获取缓存时间
     */
    public function getCacheTTL(): int
    {
        return $this->cacheTTL;
    }


    /**
     * 模型启动时注册事件监听
     */
    protected static function boot()
    {
        parent::boot();
        // 注册缓存事件
        $instance = new static;
        $instance->registerCacheEvents();
    }

    /**
     * 注册缓存事件处理
     */
    protected function registerCacheEvents(): void
    {
        // 基类提供默认的缓存清理实现
        if ($this->shouldCache()) {
            // 模型本身的缓存清理
            static::created(fn($model) => $model->flushCache($model->id));
            static::updated(fn($model) => $model->flushCache($model->id));
            static::deleted(fn($model) => $model->flushCache($model->id));

            // // 关联模型的缓存清理
            // foreach ($this->cacheRelations() as $relation => $callback) {
            //     static::created(function ($model) use ($relation, $callback) {
            //         $this->flushRelationCache($model, $relation, $callback);
            //     });
            //     static::updated(function ($model) use ($relation, $callback) {
            //         $this->flushRelationCache($model, $relation, $callback);
            //     });
            //     static::deleted(function ($model) use ($relation, $callback) {
            //         $this->flushRelationCache($model, $relation, $callback);
            //     });
            // }
        }
    }

    /**
     * 清理关联模型缓存
     */
    protected function flushRelationCache($model, string $relation, $callback): void
    {
        if (method_exists($model, $relation)) {
            $relatedModel = $model->$relation;
            if ($relatedModel) {
                // 如果是集合，则遍历处理
                if ($relatedModel instanceof \Illuminate\Database\Eloquent\Collection) {
                    foreach ($relatedModel as $related) {
                        if (is_callable($callback)) {
                            $id = $callback($related);
                        } else {
                            $id = $related->id;
                        }
                        $related->flushCache($id);
                    }
                } else {
                    // 单个模型的处理
                    if (is_callable($callback)) {
                        $id = $callback($relatedModel);
                    } else {
                        $id = $relatedModel->id;
                    }
                    $relatedModel->flushCache($id);
                }
            }
        }
    }

    /**
     * 获取列表字段
     */
    public function getListFields(): array
    {
        return $this->fillable;
    }

    /**
     * 获取详情字段
     */
    public function getDetailFields(): array
    {
        return ['*'];
    }

    /**
     * 获取导出字段
     */
    public function getExportFields(): array
    {
        return $this->getListFields();
    }

    /**
     * 获取分页大小
     */
    public function getPageSize(): int
    {
        return $this->defaultPageSize;
    }

    /**
     * 获取操作者字段
     */
    public function getOperatorFields(): array
    {
        return $this->operatorFields;
    }

    /**
     * 是否记录操作者
     */
    public function shouldRecordOperator(): bool
    {
        return $this->recordOperator;
    }

    /**
     * 获取模糊查询字段
     */
    public function getLikeFilterFields(): array
    {
        return $this->defaultLikeFields;
    }

    /**
     * 获取默认排序字段
     */
    public function getDefaultOrderField(): string
    {
        return $this->defaultOrderField;
    }

    /**
     * 获取默认排序方向
     */
    public function getDefaultOrderDirection(): string
    {
        return $this->defaultOrderDirection;
    }

    /**
     * 格式化数据输出
     */
    public function formatOutput(array $data): array
    {
        return $data;
    }

    /**
     * 格式化输入数据
     */
    public function formatAttributes(array $attributes): array
    {
        return $attributes;
    }

    /**
     * 获取自定义验证规则
     */
    public function getCustomValidationRules(): array
    {
        return [];
    }

    /**
     * 获取当前场景的验证规则
     */
    public function getScenarioRules(string $scenario = ''): array
    {
        if (empty($scenario) || empty($this->scenarios[$scenario])) {
            return $this->rules;
        }

        $scenarioRules = [];
        foreach ($this->scenarios[$scenario] as $field) {
            if (isset($this->rules[$field])) {
                $scenarioRules[$field] = $this->rules[$field];
            }
        }
        return $scenarioRules;
    }



    // 使用示例 -- 暂未验证

    // class User extends BaseModel 
    // {
    //     /**
    //      * 查询数据时使用缓存
    //      */
    //     public function scopeCached($query)
    //     {
    //         if ($this->shouldCache()) {
    //             $key = $this->getCacheKey('query_' . md5($query->toSql() . json_encode($query->getBindings())));
    //             return Cache::tags($this->getCacheTags())
    //                 ->remember($key, $this->getCacheTTL(), function() use ($query) {
    //                     return $query->get();
    //                 });
    //         }
    //         return $query->get();
    //     }

    //     /**
    //      * 获取详情时使用缓存
    //      */
    //     public static function findCached($id)
    //     {
    //         $instance = new static;
    //         if ($instance->shouldCache()) {
    //             $key = $instance->getCacheKey('detail_' . $id);
    //             return Cache::tags($instance->getCacheTags($id))
    //                 ->remember($key, $instance->getCacheTTL(), function() use ($id) {
    //                     return static::find($id);
    //                 });
    //         }
    //         return static::find($id);
    //     }
    // }

    // // 使用缓存查询
    // $users = User::where('status', 1)->cached();

    // // 使用缓存获取详情
    // $user = User::findCached(1);




}
