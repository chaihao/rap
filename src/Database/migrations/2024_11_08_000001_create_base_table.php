<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operation_log', function (Blueprint $table) {
            $table->id();
            $table->string('method')->default('')->comment('请求方法');
            $table->string('url')->default('')->comment('请求地址');
            $table->string('ip')->default('')->comment('请求IP');
            $table->text('user_agent')->nullable()->comment('用户代理');
            $table->json('payload')->nullable()->comment('请求参数');
            $table->json('response')->nullable()->comment('响应参数');
            $table->string('name')->default('')->comment('操作名称');
            // 创建人ID
            $table->integer('created_by')->default(0)->comment('创建人ID');
            // 创建人类型 1 总管理平台员工 2 分公司员工 3 用户
            $table->tinyInteger('created_by_platform')->default(0)->comment('创建人类型 1 总管理平台员工 3 用户');
            $table->timestamps();
            $table->index('created_by', 'idx_created_by');
            $table->index('name', 'idx_name');
        });

        Schema::create('sys_address', function (Blueprint $table) {
            $table->id();
            $table->integer('code')->comment('地址编码');
            $table->string('name', 64)->comment('地址名称');
            $table->integer('parent_code')->comment('父级编码');
            $table->comment('地址信息');
            $table->index('parent_code', 'idx_parent_code');
        });

        Schema::create(config('rap.models.staff.table'), function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11)->default('')->comment('手机号');
            $table->string('password', 255)->comment('密码');
            $table->string('name', 128)->nullable()->default('')->comment('名称');
            $table->string('email', 255)->nullable()->default('')->comment('邮箱');
            $table->string('salt', 255)->nullable()->default('')->comment('盐值');
            $table->string('avatar', 255)->nullable()->default('')->comment('头像');
            $table->string('ip', 16)->nullable()->default('')->comment('IP');
            $table->dateTime('last_login_at')->nullable()->comment('最后登录时间');
            $table->enum('sex', ['0', '1', '2'])->nullable()->default('0')->comment('性别 0 未知 1 男 2 女');
            $table->tinyInteger('is_super')->nullable()->default(0)->comment('是否超级管理员');
            $table->string('remark', 255)->nullable()->default('')->comment('备注');
            $table->tinyInteger('status')->nullable()->default(1)->comment('状态 1 启用 0 禁用');
            $table->timestamps();
            $table->softDeletes();
            $table->index('phone', 'idx_phone');
        });
    }
};
