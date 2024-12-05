<?php

namespace Chaihao\Rap\Test\Services;

use Chaihao\Rap\Services\BaseService;
use Chaihao\Rap\Test\Models\Article;
use Chaihao\Rap\Exception\ApiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Article 服务层示例
 * 展示了 BaseService 的完整使用方式
 */
class ArticleService extends BaseService
{
    /**
     * 构造函数中注入模型
     */
    public function __construct(Article $model)
    {
        $this->setModel($model);
    }

    /**
     * 自定义业务方法示例:增加文章浏览次数
     */
    public function incrementViewCount(int $id): array
    {
        try {
            DB::beginTransaction();

            $article = $this->findRecord($id);
            if (!$article) {
                throw new ApiException('文章不存在');
            }

            // 增加浏览次数
            $article->increment('view_count');

            // 清除缓存
            $this->clearModelCache();

            DB::commit();
            return $this->success($article);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 获取热门文章示例
     * 展示了缓存的使用方式
     */
    public function getHotArticles(int $limit = 10): array
    {
        $cacheKey = $this->generateCacheKey('hot_articles', ['limit' => $limit]);

        if ($this->getModel()->shouldCache()) {
            return cache()->remember($cacheKey, $this->getModel()->getCacheTTL(), function () use ($limit) {
                return $this->fetchHotArticles($limit);
            });
        }

        return $this->fetchHotArticles($limit);
    }


    /**
     * 获取热门文章
     */
    protected function fetchHotArticles(int $limit): array
    {
        return $this->getModel()->getHotArticles($limit);
    }

    /**
     * 重写添加方法,展示了文件上传处理
     * 
     * @param array $data 创建数据
     * @param bool $validate 是否验证数据
     * @return Model
     * @throws ApiException
     */
    public function add(array $data, bool $validate = true): Model
    {
        // 处理封面图上传
        if (isset($data['cover_image']) && is_uploaded_file($data['cover_image'])) {
            $data['cover_image'] = $this->uploadCoverImage($data['cover_image']);
        }

        return parent::add($data, $validate);
    }

    /**
     * 重写编辑方法,展示了文件更新处理
     * 
     * @param int $id 记录ID
     * @param array $data 更新数据
     * @return Model
     * @throws ApiException
     */
    public function edit(int $id, array $data): Model
    {
        // 处理封面图上传
        if (isset($data['cover_image']) && is_uploaded_file($data['cover_image'])) {
            // 检查文件是否有效
            if (!$data['cover_image']->isValid()) {
                throw new ApiException('上传文件无效');
            }

            $data['cover_image'] = $this->uploadCoverImage($data['cover_image']);

            // 删除旧图片
            $oldArticle = $this->findRecord($id);
            if ($oldArticle && $oldArticle->cover_image) {
                // 从storage路径中提取实际的文件路径
                $oldPath = str_replace('storage/', '', $oldArticle->cover_image);
                $this->deleteCoverImage($oldPath);
            }
        }

        return parent::edit($id, $data);
    }

    /**
     * 上传封面图
     */
    protected function uploadCoverImage($file): string
    {
        $path = $file->store('articles/covers', 'public');
        return 'storage/' . $path;
    }

    /**
     * 删除封面图
     */
    protected function deleteCoverImage(string $path): void
    {
        Storage::disk('public')->delete($path);
    }

    // ... 其他辅助方法省略
}
