<?php

namespace Chaihao\Rap\Http\Controllers;

use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Http\Controllers\BaseController;
use Chaihao\Rap\Models\Auth\StaffModel;
use Chaihao\Rap\Services\Auth\StaffService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class StaffController extends BaseController
{
    public function __construct(StaffService $service, StaffModel $model)
    {
        parent::__construct();
        $this->setServiceAndModel($service, $model);
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

            $user = StaffModel::query()->where('phone', $params['phone'])->first();

            if (!$user) {
                return $this->failed('账号不存在');
            }

            if (!$user || !$user->verifyPassword($params['password'])) {
                return $this->failed('账号或密码错误');
            }

            $user->ip = $this->getClientIp();
            $user->last_login_at = Carbon::now()->toDateTimeString();
            $user->save();
            $token = JWTAuth::fromUser($user);
            return $this->success([
                'token' => $token,
                'user_id' => $user->id,
            ]);
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
                'phone' => 'required|unique:master,phone|regex:/^1[3456789]\d{9}$/',
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

            if ($params['password'] != $params['verify_password']) {
                throw new ApiException('密码不一致');
            }

            $super = StaffModel::query()->where('is_super', StaffModel::DEFAULT_IS_SUPPER_YES)->first();

            // 使用盐值和密码生成哈希
            $user = new StaffModel();
            $user->name = $params['name'] ?? '';
            $user->phone = $params['phone'];
            $user->setPassword($params['password']);
            $user->ip = $this->getClientIP();
            $user->is_super = $super ? StaffModel::DEFAULT_IS_SUPPER_NO : StaffModel::DEFAULT_IS_SUPPER_YES;
            $user->save();

            //设置JWT要使用的守卫
            $token = JWTAuth::fromUser($user);
            return $this->success([
                'token' => $token,
                'user_id' => $user->id
            ]);
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
    }


    /**
     * 添加账户
     * @throws \Exception
     * @return JsonResponse|mixed
     */
    public function addStaff(): JsonResponse
    {
        try {
            $params = $this->request->all();
            if (!isset($params['password']) || empty($params['password'])) {
                $params['password'] = "123456";
            }
            $this->checkValidator($params, [
                'phone' => 'required|unique:master,phone|regex:/^1[3456789]\d{9}$/',
                'password' => 'required|string|min:6|max:16',
                'name' => 'required|max:20',
                'sex' => 'in:0,1,2',
                'email' => 'email',
                'is_super' => 'in:0,1',
            ]);

            // 使用盐值和密码生成哈希
            $user = new StaffModel();
            $user->name = $params['name'] ?? '';
            $user->phone = $params['phone'] ?? '';
            $user->status = $params['status'] ?? 1;
            $user->sex = $params['sex'] ?? '0';
            $user->email = $params['email'] ?? '';
            $user->is_super = $params['is_super'] ?? 0;
            $user->setPassword($params['password']);
            $user->save();
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
        return $this->message();
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
