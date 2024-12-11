<?php

namespace Chaihao\Rap\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ModelCacheListener
{
    public function handle($event)
    {
        $model = $event['model'];
        $id = $event['id'];
        $tags = $event['tags'];

        // 记录日志
        Log::info("模型缓存已清理", [
            'model' => get_class($model),
            'id' => $id,
            'tags' => $tags
        ]);

        // // 清理相关的其他缓存
        // if ($model instanceof \App\Models\User) {
        //     Cache::tags(['user_permissions'])->flush();
        // }

        // 通知其他服务
        // event(new OtherServiceCacheInvalidated($model));
    }
}
