<?php

namespace  Chaihao\Rap\Models\Auth;

use DateTimeInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class Staff extends Authenticatable implements JWTSubject
{
    use SoftDeletes, HasRoles;

    // 基础配置
    protected $table = 'staff';
    protected $fillable = ["id", "phone", "password", "name", "email", "avatar", "ip", "last_login_at", "sex", "is_super", "remark", "status", "created_at", "updated_at", "deleted_at"];
    protected $hidden = ['password'];
    protected $casts = [
        "phone" => "string",
        "password" => "string",
        "name" => "string",
        "email" => "string",
        "avatar" => "string",
        "ip" => "string",
        "last_login_at" => "datetime",
        "sex" => "integer",
        "is_super" => "integer",
        "remark" => "string",
        "status" => "integer"
    ];

    // 缓存配置
    protected bool $modelCache = false;
    protected int $cacheTTL = 3600;
    protected string $cachePrefix = '';

    /**
     * 默认分页大小
     */
    protected int $defaultPageSize = 20;
    /**
     * 是否记录操作者
     */
    protected bool $recordOperator = true;
    /**
     * 操作者字段
     */
    protected array $operatorFields = [
        'creator' => 'created_by',
        'updater' => 'updated_by'
    ];
    /**
     * 超级管理员
     */
    const IS_SUPPER_YES = 1;
    /**
     * 非超级管理员
     */
    const IS_SUPPER_NO = 0;

    // 验证配置
    public $scenarios = [
        'add' => ['phone', 'password', 'name', 'email', 'avatar', 'ip', 'last_login_at', 'sex', 'is_super', 'remark', 'status'],
        'edit' => ['id', 'phone', 'name', 'email', 'avatar', 'ip', 'last_login_at', 'sex', 'is_super', 'remark', 'status'],
        'delete' => ['id'],
        'detail' => ['id'],
        'status' => ['id', 'status']
    ];
    public $rules = [
        "phone" => "required|regex:/^1[3-9]\d{9}$/|string|max:11",
        "password" => "required|min:6|string|max:18",
        "name" => "nullable|string|max:128",
        "email" => "nullable|email|string|max:255",
        "avatar" => "nullable|string|max:255",
        "ip" => "nullable|ip|string|max:16",
        "last_login_at" => "nullable|date",
        "sex" => "nullable|integer|in:0,1,2",
        "is_super" => "nullable|integer|in:0,1",
        "remark" => "nullable|string|max:255",
        "status" => "nullable|integer|in:0,1"
    ];

    /**
     * 获取验证器错误信息
     */
    public function setValidatorMessage(): array
    {
        return [
            "id.required" => "ID不能为空",
            "phone.required" => "手机号不能为空",
            "phone.regex" => "手机号格式错误",
            "phone.string" => "手机号必须是字符串",
            "phone.max" => "手机号不能超过11个字符",
            "password.required" => "密码不能为空",
            "password.min" => "密码不能少于6个字符",
            "password.string" => "密码必须是字符串",
            "password.max" => "密码不能超过18个字符",
            "status.integer" => "状态必须是整数",
            "status.in" => "状态必须是0或1",
            "sex.integer" => "性别必须是整数",
            "sex.in" => "性别必须是0、1或2",
            "is_super.integer" => "是否超级管理员必须是整数",
            "is_super.in" => "是否超级管理员必须是0或1",
        ];
    }

    /**
     * 获取验证器自定义属性
     */
    public function getValidatorAttributes(): array
    {
        return [
            "phone" => "手机号",
            "password" => "密码",
            "name" => "名称",
            "email" => "邮箱",
            "avatar" => "头像",
            "ip" => "IP",
            "last_login_at" => "最后登录时间",
            "sex" => "性别 0 未知 1 男 2 女",
            "is_super" => "是否超级管理员",
            "remark" => "备注",
            "status" => "状态 1 启用 0 禁用"
        ];
    }

    /**
     * 格式化输入数据
     */
    public function formatAttributes(array $attributes): array
    {
        return $attributes;
    }

    /**
     * 是否记录操作者
     */
    public function shouldRecordOperator(): bool
    {
        return $this->recordOperator;
    }
    /**
     * 获取操作者字段
     */
    public function getOperatorFields(): array
    {
        return $this->operatorFields;
    }
    /**
     * 自定义列表展示字段
     */
    public function getListFields(): array
    {
        return array_merge(parent::getListFields(), [
            // 在此添加额外的列表字段
        ]);
    }

    /**
     * 自定义详情展示字段
     */
    public function getDetailFields(): array
    {
        return parent::getDetailFields();
    }

    /**
     * 格式化输出
     */
    public function formatOutput(array $data): array
    {
        $data = parent::formatOutput($data);
        return $data;
    }
    /**
     * 获取 JWT 中的标识符
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * 返回 JWT 中包含的自定义声明
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    /**
     * 序列化日期
     * @param DateTimeInterface $date
     * @return string
     */
    public function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }


    /**
     * 设置密码
     * @param string $password
     */
    public function setPassword(string $password)
    {
        $this->password = bcrypt($password);
    }

    /**
     * 验证密码
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * 获取分页大小
     */
    public function getPageSize(): int
    {
        return $this->defaultPageSize;
    }
}
