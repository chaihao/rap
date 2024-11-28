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
    protected $table = 'staff';

    protected $fillable = ["id", "phone", "password", "name", "email", "salt", "ip", "last_login_at", "sex", "is_super", "remark", "status"];

    protected $casts = [];


    const DEFAULT_IS_SUPPER_YES = 1;
    const DEFAULT_IS_SUPPER_NO = 0;

    protected $hidden = ['password', 'salt'];
    public $rules = [
        "phone" => "required|max:11",
        "password" => "required|max:255",
        "name" => "max:128",
        "email" => "max:255",
        "salt" => "max:255",
        "ip" => "max:16",
        "sex" => "in:0,1,2",
        "is_super" => "integer",
        "remark" => "max:255",
        "status" => "integer",
    ];

    public $scenarios = [
        'add' => ['phone', 'password', 'name', 'email', 'salt', 'ip', 'last_login_at', 'sex', 'is_super', 'remark', 'status'],
        'edit' => ['id', 'phone', 'password', 'name', 'email', 'salt', 'ip', 'last_login_at', 'sex', 'is_super', 'remark', 'status'],
        'delete' => ['id'],
        'detail' => ['id'],
        'status' => ['id', 'status'],
    ];

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
        // 生成盐值
        $salt = bin2hex(random_bytes(16));

        // 使用盐值和密码生成哈希值
        $hashedPassword = hash('sha256', $salt . $password);

        $this->salt = $salt;
        $this->password = $hashedPassword;
    }

    /**
     * 验证密码
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        // 验证密码是否正确
        $hashedPassword = hash('sha256', $this->salt . $password);
        return $hashedPassword === $this->password;
    }
}
