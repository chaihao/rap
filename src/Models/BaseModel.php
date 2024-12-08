<?php

namespace Chaihao\Rap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\SoftDeletes;
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
     * 获取缓存时间
     */
    public function getCacheTTL(): int
    {
        return $this->cacheTTL;
    }

    /**
     * 清除指定缓存
     */
    public function clearCache(string $key): void
    {
        if ($this->shouldCache()) {
            Cache::forget($this->getCacheKey($key));
        }
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
     * 子类可以重写此方法来实现自定义的缓存清理逻辑
     */
    protected function registerCacheEvents(): void
    {
        // 基类提供默认的缓存清理实现
        if ($this->shouldCache()) {
            static::created(fn($model) => $model->clearCache('list'));
            static::updated(fn($model) => $model->clearCache('list'));
            static::deleted(fn($model) => $model->clearCache('list'));
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
}
