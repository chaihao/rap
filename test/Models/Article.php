<?php

namespace Chaihao\Rap\Test\Models;

use Chaihao\Rap\Facades\CurrentStaff;
use Chaihao\Rap\Models\BaseModel;

/**
 * Article 模型示例
 * 展示了 BaseModel 的完整使用方式
 */
class Article extends BaseModel
{
    protected $table = 'articles';

    /**
     * 可填充字段
     * 定义模型中允许批量赋值的字段
     */
    protected $fillable = [
        'title',
        'content',
        'category_id',
        'cover_image',
        'status',
        'view_count',
        'created_by',
        'updated_by'
    ];

    /**
     * 验证规则定义
     * 用于表单验证,支持所有 Laravel 验证规则
     */
    public $rules = [
        'title' => 'required|string|max:200|unique:articles,title',
        'content' => 'required|string',
        'category_id' => 'required|integer|exists:categories,id',
        'cover_image' => 'nullable|string|max:255',
        'status' => 'required|integer|in:0,1',
        'view_count' => 'integer|min:0'
    ];

    /**
     * 验证场景定义
     * 不同操作场景使用不同的验证字段
     */
    public $scenarios = [
        'add' => ['title', 'content', 'category_id', 'cover_image', 'status'],
        'edit' => ['id', 'title', 'content', 'category_id', 'cover_image', 'status'],
        'status' => ['id', 'status']
    ];

    /**
     * 自定义配置
     */
    protected bool $modelCache = true; // 启用缓存
    protected int $cacheTTL = 3600; // 缓存1小时
    protected string $cachePrefix = 'article';
    protected int $defaultPageSize = 15;

    /**
     * 定义模糊搜索字段
     */
    protected array $defaultLikeFields = ['title', 'content'];

    /**
     * 定义默认排序
     */
    protected string $defaultOrderField = 'created_at';
    protected string $defaultOrderDirection = 'desc';

    /**
     * 获取列表显示字段
     */
    public function getListFields(): array
    {
        return [
            'id',
            'title',
            'category_id',
            'cover_image',
            'view_count',
            'status',
            'created_by',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * 定义关联关系
     */
    public function category()
    {
        // return $this->belongsTo(Category::class);
    }

    public function creator()
    {
        // return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 定义列表需要加载的关联
     * 用于优化 N+1 问题
     */
    public function listWithRelations(): array
    {
        return ['category:id,name', 'creator:id,name'];
    }

    /**
     * 注册缓存事件
     */
    protected function registerCacheEvents(): void
    {
        if ($this->shouldCache()) {
            static::created(fn($model) => $model->flushCache());
            static::updated(fn($model) => $model->flushCache());
            static::deleted(fn($model) => $model->flushCache());
        }
    }



    /**
     * 自定义数据输出格式化
     */
    public function formatOutput(array $data): array
    {
        $data = parent::formatOutput($data);

        // 添加分类名称
        $data['category_name'] = $data['category']['name'] ?? '';
        unset($data['category']);

        // 添加作者名称
        $data['creator_name'] = $data['creator']['name'] ?? '';
        unset($data['creator']);

        // 格式化封面图
        if (!empty($data['cover_image'])) {
            $data['cover_image_url'] = asset($data['cover_image']);
        }

        return $data;
    }

    /**
     * 数据入库前的格式化处理
     */
    public function formatAttributes(array $attributes): array
    {
        // 处理封面图路径
        if (isset($attributes['cover_image']) && !empty($attributes['cover_image'])) {
            $attributes['cover_image'] = str_replace(asset(''), '', $attributes['cover_image']);
        }

        return $attributes;
    }

    /**
     * 定义查询范围
     * 用于数据权限控制
     */
    public function scopeGetCreateBy($query)
    {
        if (!CurrentStaff::isAdmin()) {
            $query->where('created_by', CurrentStaff::getId());
        }
    }
}
