<?php

namespace Chaihao\Rap\Models;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    protected $guarded = [];

    // 添加通用的模型方法
    public function scopeActive($query)
    {
        // return $query->where('is_active', 1);
    }
}
