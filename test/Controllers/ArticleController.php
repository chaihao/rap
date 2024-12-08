<?php

namespace Chaihao\Rap\Test\Controllers;

use Chaihao\Rap\Http\Controllers\BaseController;
use Chaihao\Rap\Test\Models\Article;
use Chaihao\Rap\Test\Services\ArticleService;
use Illuminate\Http\Request;

/**
 * Article 控制器示例
 * 展示了 BaseController 的完整使用方式
 */
class ArticleController extends BaseController
{
    /**
     * 初始化服务和模型
     * 必须实现的抽象方法
     */
    protected function initServiceAndModel(): void
    {
        $this->service = app(ArticleService::class);
        $this->model = app(Article::class);
    }

    /**
     * 增加浏览次数
     * 自定义业务接口示例
     */
    public function incrementView(int $id)
    {
        try {
            $this->checkValidator(['id' => $id], 'detail');
            $data = $this->service->incrementViewCount($id);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 获取热门文章
     * 自定义查询接口示例
     */
    public function hot()
    {
        try {
            $data = $this->service->getHotArticles();
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 按分类获取文章
     * 展示了参数处理方式
     */
    public function byCategory(int $categoryId)
    {
        try {
            $params = $this->request->all();
            $params['category_id'] = $categoryId;
            $data = $this->service->getList($params);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
