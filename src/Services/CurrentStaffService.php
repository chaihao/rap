<?php

namespace Chaihao\Rap\Services;

use Chaihao\Rap\Models\Auth\Staff;
use Illuminate\Support\Facades\Cache;

class CurrentStaffService extends BaseService
{
   private $staff = null;

   public function setStaff($staff): void
   {
      $staffClass = config('rap.auth.staff.model', Staff::class);
      if ($staff === null || $staff instanceof $staffClass) {
         $this->staff = $staff;
         return;
      }
      throw new \InvalidArgumentException('Staff must be null or instance of ' . $staffClass);
   }


   public function getStaff(): ?Staff
   {
      return $this->staff;
   }

   public function getId(): ?int
   {
      if (!$this->staff || !isset($this->staff->id)) {
         return null;
      }
      return $this->staff->id;
   }

   public function hasRole($role): bool
   {
      return $this->staff?->hasRole($role) ?? false;
   }

   // 检查当前用户是否为超级管理员
   public function isSuper(): bool
   {
      if (!$this->staff || !($this->staff->is_super ?? false)) {
         return false;
      }
      return true;
   }

   // 检查当前用户是否为管理员
   public function isAdmin(): bool
   {
      if (!$this->staff || (!$this->hasRole('admin') && !($this->staff->is_super ?? false))) {
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
      if (!$this->staff || !isset($this->staff->id)) {
         return null;
      }

      return Cache::remember('staff_' . $this->staff->id, now()->addMinutes(10), function () {
         return $this->staff->load(['roles', 'permissions']);
      });
   }
}
