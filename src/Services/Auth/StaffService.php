<?php

namespace  Chaihao\Rap\Services\Auth;

use Carbon\Carbon;
use Chaihao\Rap\Models\Auth\Staff;
use Tymon\JWTAuth\Facades\JWTAuth;
use Chaihao\Rap\Services\BaseService;
use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Facades\CurrentStaff;
use Chaihao\Rap\Services\Sys\PermissionService;

class StaffService extends BaseService
{

   /**
    * 构造函数中注入模型
    */
   public function __construct(Staff $model)
   {
      $this->setModel($model);
   }

   /**
    * 登录
    * 
    * @param array $params 参数
    * @return array
    */
   public function login(array $params): array
   {
      $user = Staff::query()->where('phone', $params['phone'])->first();

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
    * 
    * @param array $params 参数
    * @return array
    */
   public function register(array $params): array
   {
      $this->checkValidator($params, [
         'phone' => 'required|unique:staff',
         'password' => 'required|min:6',
         'verify_password' => 'required|same:password'
      ], [
         'phone.required' => '手机号不能为空',
         'phone.unique' => '手机号已存在',
         'password.required' => '密码不能为空',
         'password.min' => '密码长度不能小于6位',
         'verify_password.required' => '确认密码不能为空',
         'verify_password.same' => '确认密码与密码不一致'
      ]);

      if ($params['password'] != $params['verify_password']) {
         throw new ApiException('密码不一致');
      }

      $super = Staff::query()->where('is_super', Staff::IS_SUPPER_YES)->first();

      $user = new Staff();
      $user->name = $params['name'] ?? '';
      $user->phone = $params['phone'];
      $user->setPassword($params['password']);
      $user->ip = request()->getClientIp();
      $user->is_super = $super ? Staff::IS_SUPPER_NO : Staff::IS_SUPPER_YES;
      $user->save();

      $token = JWTAuth::fromUser($user);
      return $this->success([
         'token' => $token,
         'user_id' => $user->id
      ]);
   }

   /**
    * 添加账户
    * 
    * @param array $params 参数
    */
   public function addStaff(array $params)
   {
      // 设置默认值
      $params = array_merge([
         'name' => '',
         'phone' => '',
         'status' => 1,
         'sex' => '0',
         'email' => '',
         'is_super' => 0,
         'password' => '123456'
      ], $params);

      // 对密码进行加密处理
      $params['password'] = $this->model->encryptPassword($params['password']);

      // 直接调用父类的 add 方法
      return $this->add($params);
   }

   /**
    * 编辑账户
    * 
    * @param array $params 参数
    */
   public function editStaff(array $params)
   {
      try {
         if (isset($params['roles']) && !empty($params['roles'])) {
            app(PermissionService::class)->assignRole(CurrentStaff::getId(), $params['roles']);
         }
         return $this->edit($params['id'], $params);
      } catch (\Exception $e) {
         throw ApiException::failed($e->getMessage());
      }
   }

   /**
    * 删除账户
    * 
    * @param array $params 参数
    */
   public function deleteStaff(array $params)
   {
      return $this->delete($params['id']);
   }


   /**
    * 修改密码
    * 
    * @param array $params 参数
    */
   public function changePassword(array $params)
   {
      $user = $this->model::query()->find($params['id']);
      $user->setPassword($params['password']);
      $user->save();
      return $this->success('修改密码成功');
   }

   /**
    * 本人修改密码
    */
   public function changePasswordBySelf(array $params)
   {
      $user = $this->model::query()->find($params['id']);
      if (!$user->verifyPassword($params['old_password'])) {
         throw ApiException::failed('旧密码错误');
      }
      $user->setPassword($params['password']);
      $user->save();
      return $this->success('修改密码成功');
   }

   /**
    * 退出登录
    * 
    * @return array
    */
   public function logout(): array
   {
      auth()->guard()->logout();
      JWTAuth::invalidate();
      return $this->success('登出成功');
   }

   /**
    * 获取员工列表
    * 
    * @param array $params 参数
    * @return array
    */
   public function getStaffList(array $params): array
   {
      $query = Staff::query();

      // 使用可选参数构建查询
      $query->when(!empty($params['name']), function ($query) use ($params) {
         $query->where('name', 'like', '%' . $params['name'] . '%');
      })->when(!empty($params['phone']), function ($query) use ($params) {
         $query->where('phone', 'like', '%' . $params['phone'] . '%');
      })->when(!empty($params['email']), function ($query) use ($params) {
         $query->where('email', 'like', '%' . $params['email'] . '%');
      })->when(isset($params['status']) && is_numeric($params['status']), function ($query) use ($params) {
         $query->where('status', $params['status']);
      })->when(isset($params['sex']) && is_numeric($params['sex']), function ($query) use ($params) {
         $query->where('sex', $params['sex']);
      })->when(isset($params['is_super']) && is_numeric($params['is_super']), function ($query) use ($params) {
         $query->where('is_super', $params['is_super']);
      });

      // 获取分页数据
      $pageSize = $params['page_size'] ?? $this->getModel()->getPageSize();
      $data = $query->paginate($pageSize);
      return $this->paginateFormat($data);
   }
}
