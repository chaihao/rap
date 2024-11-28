<?php

namespace  Chaihao\Rap\Models\Sys;

use Chaihao\Rap\Models\BaseModel;


class Roles extends BaseModel
{

    protected $table = 'roles';

    protected $fillable = ["id", "name", "slug", "guard_name"];

    protected $casts = [];

    public $rules = [
        "name" => "required|max:255",
        "slug" => "max:255",
        "guard_name" => "required|max:255",
    ];

    public $scenarios = [
        'add' => ['name', 'slug', 'guard_name'],
        'edit' => ['id', 'name', 'slug', 'guard_name'],
        'delete' => ['id'],
        'detail' => ['id'],
    ];
}
