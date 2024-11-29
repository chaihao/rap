<?php

namespace Chaihao\Rap\Services;

use Chaihao\Rap\Models\Auth\Staff;
use Illuminate\Support\Facades\Cache;

class CurrentStaffService extends BaseService
{
   private $staff = null;

   public function setStaff($staff): void
   {
      $staffClass = config('rap.models.staff.class');
      if ($staff !== false && ($staff === null || $staff instanceof $staffClass)) {
         $this->staff = $staff;
      }
   }

   public function getStaff(): ?Staff
   {
      return $this->staff;
   }

   public function getId(): ?int
   {
      return $this->staff?->id;
   }

   public function hasRole($role): bool
   {
      return $this->staff?->hasRole($role) ?? false;
   }

   // 检查当前用户是否为超级管理员
   public function isSuper(): bool
   {
      if (!$this->staff->is_super) {
         return false;
      }
      return true;
   }

   // 检查当前用户是否为管理员
   public function isAdmin(): bool
   {
      if (!$this->hasRole('admin') && !$this->staff->is_super) {
         return false;
      }
      return true;
   }


   public function hasPermission($permission): bool
   {
      return $this->staff?->hasPermissionTo($permission) ?? false;
   }

   // 使用缓存获取用户数据
   public function getStaffWithCache(): ?Staff
   {
      if (!$this->staff) {
         return null;
      }

      return Cache::remember('staff_' . $this->staff->id, now()->addMinutes(10), function () {
         return $this->staff->load(['roles', 'permissions']);
      });
   }
}
