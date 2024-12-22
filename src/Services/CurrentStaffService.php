<?php

namespace Chaihao\Rap\Services;

use Chaihao\Rap\Models\Auth\Staff;
use Illuminate\Support\Facades\Cache;

class CurrentStaffService extends BaseService
{
   private $staff = null;

   /**
    * 设置当前用户
    * 
    * @param Staff $staff 用户对象
    */
   public function setStaff($staff): void
   {
      $staffClass = config('rap.models.staff.class', Staff::class);
      if ($staff === null || $staff instanceof $staffClass) {
         $this->staff = $staff;
         return;
      }
      throw new \InvalidArgumentException('Staff must be null or instance of ' . $staffClass);
   }

   /**
    * 获取当前用户
    * 
    * @return ?Staff
    */
   public function getStaff(): ?Staff
   {
      return $this->staff;
   }

   /**
    * 获取当前用户ID
    * 
    * @return ?int
    */
   public function getId(): ?int
   {
      if (!$this->staff || !isset($this->staff->id)) {
         return null;
      }
      return $this->staff->id;
   }

   /**
    * 检查当前用户是否具有某个角色
    * 
    * @param string $role 角色名称
    * @return bool
    */
   public function hasRole($role): bool
   {
      return $this->staff?->hasRole($role) ?? false;
   }

   /**
    * 检查当前用户是否为超级管理员
    * 
    * @return bool
    */
   public function isSuper(): bool
   {
      if (!$this->staff || !($this->staff->is_super ?? false)) {
         return false;
      }
      return true;
   }

   /**
    * 检查当前用户是否为管理员
    * 
    * @return bool
    */
   public function isAdmin(): bool
   {
      if (!$this->staff || (!$this->hasRole('admin') && !($this->staff->is_super ?? false))) {
         return false;
      }
      return true;
   }

   /**
    * 检查当前用户是否具有某个权限
    * 
    * @param string $permission 权限名称
    * @return bool
    */
   public function hasPermission($permission): bool
   {
      return $this->staff?->hasPermissionTo($permission) ?? false;
   }

   /**
    * 使用缓存获取用户数据
    * 
    * @return ?Staff
    */
   public function getStaffWithCache(): ?Staff
   {
      if (!$this->staff || !isset($this->staff->id)) {
         return null;
      }

      return Cache::remember('staff_' . $this->staff->id, now()->addMinutes(10), function () {
         return $this->staff->load(['roles', 'permissions']);
      });
   }
}
