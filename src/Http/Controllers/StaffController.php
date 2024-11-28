<?php

namespace Chaihao\Rap\Http\Controllers;

use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Http\Controllers\BaseController;
use Chaihao\Rap\Models\Auth\Staff;
use Chaihao\Rap\Services\Auth\StaffService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class StaffController extends BaseController
{
    protected function init(): void
    {
        $this->service = app(StaffService::class);
        $this->model = app(Staff::class);
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
        if ($staff) {
            return $this->success($staff);
        }
        return $this->failed('未找到用户信息');
    }

    /**
     * 退出登录
     */
    public function logout(): JsonResponse
    {
        try {
            auth()->guard()->logout();
            JWTAuth::invalidate();
            return $this->success('登出成功');
        } catch (ApiException $e) {
            return $this->failed('登出失敗: ' . $e->getMessage());
        }
    }
}
