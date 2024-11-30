<?php

namespace  Chaihao\Rap\Services\Auth;

use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Services\BaseService;
use Chaihao\Rap\Models\Auth\Staff;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

class StaffService extends BaseService
{
   /**
    * 登录
    */
   public function login(array $params): array
   {
      $user = Staff::query()->where('phone', $params['phone'])->first();

      if (!$user) {
         throw ApiException::notFound('账号不存在');
      }

      if (!$user || !$user->verifyPassword($params['password'])) {
         throw ApiException::failed('账号或密码错误');
      }

      $user->ip = request()->getClientIp();
      $user->last_login_at = Carbon::now()->toDateTimeString();
      $user->save();

      $token = JWTAuth::fromUser($user);
      return $this->success([
         'token' => $token,
         'user_id' => $user->id,
      ]);
   }

   /**
    * 注册
    */
   public function register(array $params): array
   {
      if ($params['password'] != $params['verify_password']) {
         throw new ApiException('密码不一致');
      }

      $super = Staff::query()->where('is_super', Staff::DEFAULT_IS_SUPPER_YES)->first();

      $user = new Staff();
      $user->name = $params['name'] ?? '';
      $user->phone = $params['phone'];
      $user->setPassword($params['password']);
      $user->ip = request()->getClientIp();
      $user->is_super = $super ? Staff::DEFAULT_IS_SUPPER_NO : Staff::DEFAULT_IS_SUPPER_YES;
      $user->save();

      $token = JWTAuth::fromUser($user);
      return $this->success([
         'token' => $token,
         'user_id' => $user->id
      ]);
   }

   /**
    * 添加账户
    */
   public function addStaff(array $params): array
   {
      if (!isset($params['password']) || empty($params['password'])) {
         $params['password'] = "123456";
      }

      $user = new Staff();
      $user->name = $params['name'] ?? '';
      $user->phone = $params['phone'] ?? '';
      $user->status = $params['status'] ?? 1;
      $user->sex = $params['sex'] ?? '0';
      $user->email = $params['email'] ?? '';
      $user->is_super = $params['is_super'] ?? 0;
      $user->setPassword($params['password']);
      $user->save();

      return $this->message();
   }

   /**
    * 退出登录
    */
   public function logout(): array
   {
      auth()->guard()->logout();
      JWTAuth::invalidate();
      return $this->success('登出成功');
   }
}
