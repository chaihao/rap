<?php

namespace Chaihao\Rap\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Chaihao\Rap\Models\Auth\Staff;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\App;
use Chaihao\Rap\Facades\CurrentStaff;
use Illuminate\Support\Facades\Redis;
use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Services\Auth\StaffService;
use Chaihao\Rap\Services\Sys\PermissionService;
use Chaihao\Rap\Http\Controllers\BaseController;
use Chaihao\Rap\Services\Export\StaffExportService;

class StaffController extends BaseController
{
    protected function initServiceAndModel(): void
    {
        $this->service = App::make(StaffService::class);
        $this->model = App::make(Staff::class);
        $this->exportService = App::make(StaffExportService::class);
    }

    /**
     * 登录
     */
    public function login(): JsonResponse
    {
        try {
            $params = $this->request->all();

            $this->checkValidator($params, [
                'phone' => 'required|regex:/^1[3-9]\d{9}$/',
                'password' => 'required',
            ], [
                'phone.required' => '手机号不能为空',
                'phone.regex' => '手机号格式错误',
                'password.required' => '密码不能为空',
            ]);

            $result = $this->service->login($params);
            // 将 token 存入 Redis，设置与 JWT 相同的过期时间
            if (isset($result['data']['token'])) {
                $userId = $result['data']['user_id'];
                $tokenTTL = config('jwt.ttl', 60); // 获取 JWT 配置的过期时间（分钟）
                Redis::setex('jwt_token:' . $userId, $tokenTTL * 60, $result['data']['token']);
            }

            return $this->success($result['data'], $result['message'] ?? '登录成功');
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
    }

    /**
     * 注册
     */
    public function register(): JsonResponse
    {
        try {
            $params = $this->request->all();
            $this->checkValidator($params, [
                'phone' => 'required|unique:' . $this->model->getTable() . ',phone|regex:/^1[3456789]\d{9}$/',
                'password' => 'required|string|min:8|max:16|regex:/^[a-zA-Z0-9,.*%$#@]{8,16}$/',
                'verify_password' => 'required|string|min:8|max:16|regex:/^[a-zA-Z0-9,.*%$#@]{8,16}$/',
            ], [
                'phone.required' => '账号不能为空',
                'phone.unique' => '账号已存在',
                'password.required' => '密码不能为空',
                'password.min' => '密码不能少于8位',
                'password.max' => '密码不能多于16位',
                'password.regex' => '密码格式错误',
                'verify_password.required' => '确认密码不能为空',
                'verify_password.min' => '确认密码不能少于8位',
                'verify_password.max' => '确认密码不能多于16位',
                'verify_password.regex' => '确认密码格式错误',
            ]);

            $result = $this->service->register($params);
            return $this->success($result['data'], $result['message'] ?? '注册成功');
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
    }

    /**
     * 获取用户信息
     */
    public function staffInfo(): JsonResponse
    {
        $staff = auth()->guard()->user();
        $staff->permissions = App::make(PermissionService::class)->getUserPermissions($staff->id, 'name');
        $staff->roles = App::make(PermissionService::class)->getUserRoles($staff->id, 'name');

        if ($staff) {
            return $this->success($staff);
        }
        return $this->failed('未找到用户信息');
    }


    /**
     * 修改密码
     */
    public function changePassword(): JsonResponse
    {
        $params = $this->request->all();
        $this->checkValidator($params, [
            'id' => 'required|integer',
            'password' => 'required|string|min:6|max:18',
        ], [
            'id.required' => 'ID不能为空',
            'id.integer' => 'ID必须是整数',
            'password.required' => '密码不能为空',
            'password.min' => '密码不能少于6个字符',
            'password.max' => '密码不能超过18个字符',
        ]);

        return $this->success($this->service->changePassword($this->request->all()));
    }


    /**
     * 本人修改密码,需要验证旧密码
     */
    public function changePasswordBySelf(): JsonResponse
    {
        $params = $this->request->all();
        $this->checkValidator($params, [
            'old_password' => 'required|string|min:6|max:18',
            'password' => 'required|string|min:6|max:18',
        ]);

        $this->service->changePasswordBySelf($params);
        // 从 Redis 中删除用户的 JWT token  
        Redis::del('jwt_token:' . CurrentStaff::getId());

        return $this->success('修改密码成功');
    }


    /**
     * 添加个人信息
     */
    public function addStaff(): JsonResponse
    {
        $params = $this->request->all();
        $this->checkValidator($params, 'add');
        return $this->success($this->service->addStaff($this->request->all()));
    }

    /**
     * 修改个人信息
     */
    public function editStaff(): JsonResponse
    {
        $params = $this->request->all();
        $this->checkValidator($params, 'edit');
        return $this->success($this->service->editStaff($this->request->all()));
    }

    /**
     * 删除个人信息
     */
    public function deleteStaff(): JsonResponse
    {
        $params = $this->request->all();
        $this->checkValidator($params, 'delete');
        return $this->success($this->service->deleteStaff($this->request->all()));
    }

    /**
     * 退出登录
     */
    public function logout(): JsonResponse
    {
        try {
            $user = auth()->guard()->user();
            $userId = $user ? $user->id : null;

            // 从 Redis 中删除 token
            if ($userId) {
                Redis::del('jwt_token:' . $userId);
            }

            auth()->guard()->logout();
            JWTAuth::invalidate();
            return $this->success('登出成功');
        } catch (ApiException $e) {
            return $this->failed('登出失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取员工列表
     */
    public function list(): JsonResponse
    {
        $params = $this->request->all();
        $data = $this->service->getStaffList($params);
        return $this->success($data);
    }
}
