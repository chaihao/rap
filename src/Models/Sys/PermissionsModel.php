<?php

namespace  Chaihao\Rap\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission;

class PermissionsModel extends Permission
{
    use SoftDeletes;
    protected $table = 'permissions';

    protected $fillable = ["id", "name", "method", "uri", "controller", "action", "slug", "prefix", "guard_name", "middleware", "group", "group_name", "is_login", "status"];

    protected $casts = ["middleware" => "array",];

    public $rules = [
        "name" => "required|max:255",
        "method" => "max:255",
        "uri" => "max:255",
        "controller" => "max:255",
        "action" => "max:255",
        "slug" => "max:255",
        "prefix" => "max:255",
        "guard_name" => "required|max:255",
        "group" => "max:255",
        "group_name" => "max:255",
        "is_login" => "required|integer",
        "status" => "required|integer",
    ];

    public $scenarios = [
        'add' => ['name', 'method', 'uri', 'controller', 'action', 'slug', 'prefix', 'guard_name', 'middleware', 'group', 'group_name', 'is_login', 'status'],
        'edit' => ['id', 'name', 'method', 'uri', 'controller', 'action', 'slug', 'prefix', 'guard_name', 'middleware', 'group', 'group_name', 'is_login', 'status'],
        'delete' => ['id'],
        'get' => ['id'],
        'status' => ['id', 'status'],
    ];
}
