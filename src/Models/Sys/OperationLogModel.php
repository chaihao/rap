<?php

namespace  Chaihao\Rap\Models\Sys;

use Chaihao\Rap\Models\BaseModel;

class OperationLogModel extends BaseModel
{

    protected $table = 'operation_log';

    protected $fillable = ["id", "method", "url", "ip", "user_agent", "payload", "response", "merchant_id", "name", "action_user_id", "action_user_type"];

    protected $casts = [
        "payload" => "array",
        "response" => "array",
    ];

    public $rules = [
        "method" => "required|max:255",
        "url" => "required|max:255",
        "ip" => "required|max:255",
        "user_agent" => "nullable",
        "merchant_id" => "required|integer",
        "name" => "required|max:255",
        "action_user_id" => "required|integer",
        "action_user_type" => "required|integer",
    ];

    public $scenarios = [
        'add' => ['method', 'url', 'ip', 'user_agent', 'payload', 'response', 'merchant_id', 'name', 'action_user_id', 'action_user_type'],
        'edit' => ['id', 'method', 'url', 'ip', 'user_agent', 'payload', 'response', 'merchant_id', 'name', 'action_user_id', 'action_user_type'],
        'delete' => ['id'],
        'get' => ['id'],
    ];
}
