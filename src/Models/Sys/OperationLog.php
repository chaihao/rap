<?php

namespace  Chaihao\Rap\Models\Sys;

use Chaihao\Rap\Models\BaseModel;


class OperationLog extends BaseModel
{

    protected $table = 'operation_log';

    protected $fillable = ["id", "method", "url", "ip", "user_agent", "payload", "response", "name", "created_by", "created_by_platform"];

    protected $casts = [
        "payload" => "array",
        "response" => "array",
    ];

    public $rules = [
        "method" => "required|max:255",
        "url" => "required|max:255",
        "ip" => "required|max:255",
        "name" => "required|max:255",
        "created_by" => "required|integer",
        "created_by_platform" => "required|integer",
    ];

    public $scenarios = [
        'add' => ['method', 'url', 'ip', 'user_agent', 'payload', 'response', 'name', 'created_by', 'created_by_platform'],
        'edit' => ['id', 'method', 'url', 'ip', 'user_agent', 'payload', 'response', 'name', 'created_by', 'created_by_platform'],
        'delete' => ['id'],
        'detail' => ['id'],
    ];
}
