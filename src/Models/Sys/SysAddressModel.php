<?php

namespace  Chaihao\Rap\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysAddressModel extends Model
{
    protected $table = 'sys_address';

    protected $fillable = ["id", "code", "name", "parent_code"];

    protected $casts = [];

    public $rules = [
        "code" => "required|integer",
        "name" => "required|max:64",
        "parent_code" => "required|integer",
    ];

    public $scenarios = [
        'add' => ['code', 'name', 'parent_code'],
        'edit' => ['id', 'code', 'name', 'parent_code'],
        'delete' => ['id'],
        'get' => ['id'],
    ];
    // 关闭自动维护created_at和updated_at
    public $timestamps = false;
}
