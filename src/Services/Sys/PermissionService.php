<?php

namespace  Chaihao\Rap\Services\Sys;

use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Models\Auth\Staff;
use Chaihao\Rap\Models\Sys\Permissions;
use Chaihao\Rap\Models\Sys\Roles;
use Chaihao\Rap\Services\BaseService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\{Permission, Role};

class PermissionService extends BaseService
{
   protected $model;

   public function __construct()
   {
      $this->model = app(config('rap.models.staff.class'));
   }

   /**
    * 给用户直接分配权限
    * @param int $userId 用户ID
    * @param string|array $permissions 权限名称或权限数组
    * @return Staff
    */
   public function givePermissionTo(int $userId, string|array $permissions)
   {
      $user = $this->model::find($userId);
      if (!$user) {
         throw new ApiException('用户不存在');
      }
      $user->givePermissionTo($permissions);
      return $user;
   }

   /**
    * 撤销用户的指定权限
    * @param int $userId 用户ID
    * @param string|array $permissions 权限名称或权限数组
    * @return Staff
    */
   public function revokePermissionTo(int $userId, string|array $permissions)
   {
      $user = $this->model::find($userId);
      if (!$user) {
         throw new ApiException('用户不存在');
      }
      $user->revokePermissionTo($permissions);
      return $user;
   }

   /**
    * 同步用户权限(会删除之前的所有权限)
    * @param int $userId 用户ID
    * @param array $permissions 权限数组
    * @return Staff
    */
   public function syncPermissions(int $userId, array $permissions): Staff
   {
      $user = $this->model::find($userId);
      if (!$user) {
         throw new ApiException('用户不存在');
      }
      $user->syncPermissions($permissions);
      return $user;
   }


   /**
    * 同步角色权限
    * @param int $roleId
    * @param array $permissions
    * @return Role 返回角色模型
    */
   public function syncRolePermissions(int $roleId, array $permissions)
   {
      $role = Roles::find($roleId);
      if (!$role) {
         throw new ApiException('角色不存在');
      }
      return $role->syncPermissions($permissions);
   }


  /**
    * 获取用户的所有权限
    * @param int $userId 用户ID
    * @param string $fieldColumn 需要获取的字段名
    * @return array
    */
    public function getUserPermissions(int $userId, string $fieldColumn = ''): array
    {
       $user = $this->model::find($userId);
       if (!$user) {
          throw new ApiException('用户不存在');
       }

       $permissions = $user->getAllPermissions();

       if ($fieldColumn && in_array($fieldColumn, ['id', 'name', 'slug'])) {
          return $permissions->pluck($fieldColumn)->toArray();
       }

       return $permissions->map(function ($permission) {
          return [
             'id' => $permission->id,
             'name' => $permission->name,
             'method' => $permission->method,
             'uri' => $permission->uri,
             'slug' => $permission->slug,
             'group' => $permission->group,
             'group_name' => $permission->group_name
          ];
       })->toArray();
    }

    /**
     * 获取用户的所有角色
     * @param int $userId
     * @return array
     */
    public function getUserRoles(int $userId, string $fieldColumn = ''): array
    {
       $user = $this->model::find($userId);
       if (!$user) {
          throw new ApiException('用户不存在');
       }
       $roles = $user->roles;
       if ($fieldColumn && in_array($fieldColumn, ['id', 'name', 'slug'])) {
          return $roles->pluck($fieldColumn)->toArray();
       }
       return $roles->map(function ($role) {
          return [
             'id' => $role->id,
             'name' => $role->name,
             'slug' => $role->slug,
             'guard_name' => $role->guard_name
          ];
       })->toArray();
    }

   /**
    * 分配角色
    * @param int $id
    * @param string|array $role
    * @return Staff
    */
   public function assignRole(int $id, string|array $role)
   {
      $user = $this->model::find($id);
      if (!$user) {
         throw new ApiException('用户不存在');
      }
      $user->assignRole($role);
      return $user;
   }

   /**
    * 移除角色
    * @param int $id
    * @param string $role
    * @return Staff
    */
   public function removeRole(int $id, string $role)
   {
      $user = $this->model::find($id);
      if (!$user) {
         throw new ApiException('用户不存在');
      }
      $user->removeRole($role);
      return $user;
   }



   /**
    * 创建角色
    * @param array $data
    * @return Role
    */
   public function createRole(array $data): Role
   {
      try {
         // 检查角色名称是否已存在
         if (Role::where('name', $data['name'])->exists()) {
            throw new ApiException('角色名称已存在');
         }

         // 使用 Spatie\Permission\Models\Role 创建角色
         return Role::create([
            'name' => $data['name'],
            'guard_name' => config('rap.api.guard', 'api'),
            'slug' => $data['slug'] ?? null
         ]);
      } catch (Exception $e) {
         throw new ApiException($e->getMessage());
      }
   }

   /**
    * 获取角色的所有权限
    * @param int $roleId
    * @return array
    */
   public function getRolePermissions(int $roleId): array
   {
      $role = Roles::find($roleId);
      if (!$role) {
         throw new ApiException('角色不存在');
      }
      return $role->permissions->select('id', 'name', 'method', 'uri', 'slug', 'group', 'group_name')->toArray();
   }


   /**
    * 获取所有权限
    * @return array 重组后的权限数据
    */
   public function getAllPermissions(): array
   {
      return Permissions::select(['id', 'name', 'method', 'uri', 'slug', 'group', 'group_name'])
         ->orderBy('group')
         ->get()
         ->groupBy('group')
         ->map(function ($permissions) {
            return [
               'group_name' => $permissions->first()->group_name,
               'data' => $permissions->toArray()
            ];
         })
         ->values()
         ->toArray();
   }

   /**
    * 添加权限
    * @return array 添加的权限ID数组
    * @throws ApiException
    * @return array
    */
   public function addPermission(): array
   {
      try {
         return DB::transaction(function () {
            $routeCollection = Route::getRoutes();
            // 获取所有已存在的权限
            $existingPermissions = Permissions::withTrashed()
               ->select('id', 'name', 'guard_name', 'uri')
               ->get()
               ->keyBy(function ($item) {
                  return $item->name . '_' . ($item->guard_name ?? config('rap.api.guard', 'api'));
               });

            // 软删除现有权限
            Permissions::whereNull('deleted_at')->delete();
            // 设置超时时间
            ini_set('max_execution_time', 300);

            // 开发环境直接批量插入
            $this->addData($routeCollection, $existingPermissions);
            return ['message' => '添加权限成功'];
         });
      } catch (ApiException $e) {
         Log::error('添加权限失败：' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
         ]);
         throw new ApiException('添加权限失败：' . $e->getMessage());
      }
   }

   /**
    * 根据环境添加权限
    * @param  $routeCollection 路由集合
    * @param  $existingPermissions 已存在的权限集合
    */
   public function addData($routeCollection, $existingPermissions)
   {
      $batchData = [];
      // 收集所有需要添加的权限数据
      foreach ($routeCollection as $route) {
         $routeInfo = $this->buildRouteInfo($route);
         if (!$routeInfo['is_login']) {
            continue;
         }
         $routeInfo['guard_name'] = $routeInfo['guard_name'] ?? config('rap.api.guard', 'api');
         $key = $routeInfo['name'] . '_' . $routeInfo['guard_name'];

         if (isset($existingPermissions[$key])) {
            // 更新现有权限
            $existingPermission = $existingPermissions[$key];
            $this->updateExistingPermission($existingPermission, $routeInfo);
         } else {
            // 手动将 middleware 数组转换为 JSON 字符串
            $routeInfo['middleware'] = json_encode($routeInfo['middleware']);
            $routeInfo['created_at'] = now();
            $routeInfo['updated_at'] = now();
            $batchData[] = $routeInfo;
         }
      }

      // 批量插入新权限
      if (!empty($batchData)) {
         Permissions::insert($batchData);
      }
   }


   /**
    * 更新现有权限记录
    * @param Permissions $permission
    * @param array $routeInfo
    */
   private function updateExistingPermission(Permissions $permission, array $routeInfo): void
   {
      $permission->fill([
         'method' => $routeInfo['method'],
         'uri' => $routeInfo['uri'],
         'controller' => $routeInfo['controller'],
         'action' => $routeInfo['action'],
         'slug' => $routeInfo['slug'],
         'prefix' => $routeInfo['prefix'],
         'middleware' => $routeInfo['middleware'],
         'group' => $routeInfo['group'],
         'group_name' => $routeInfo['group_name'],
         'is_login' => $routeInfo['is_login'],
         'status' => $routeInfo['status'],
         'deleted_at' => null
      ]);
      $permission->save();
   }

   /**
    * 构建路由信息数组
    * @param mixed $route 路由对象
    * @return array 包含路由详细信息的数组
    */
   private function buildRouteInfo($route): array
   {
      $parts = $route->getActionName() ? explode('\\', $route->getActionName()) : NULL;
      $controller = $parts ? explode('@', end($parts))[0] : null;
      $group = str_replace('Controller', '', $controller);
      $slugName = explode('.', $route->getName());
      $slug = end($slugName);
      $groupName = count($slugName) > 1 ? $slugName[0] : $group;
      return [
         'method' => implode('|', $route->methods()),
         'uri' => $route->uri(),
         'name' => $this->convertPath($route->uri()),
         'action' => $route->getActionName(),
         'controller' => $controller,
         'slug' => $slug,
         'prefix' => $route->getPrefix(),
         'status' => $this->isApiRoute($route->getPrefix()),
         'middleware' => $this->getMiddleware($route->gatherMiddleware()),
         'is_login' => $this->checkLoginRequired($route->gatherMiddleware()),
         'group' => $group,
         'group_name' => $groupName
      ];
   }

   /**
    * 判断是否为API路由
    * @param string|null $prefix 路由前缀
    * @return int 1表示是API路由，0表示不是
    */
   private function isApiRoute(?string $prefix): int
   {
      return stripos($prefix ?? '', 'api') !== false ? 1 : 0;
   }

   /**
    * 检查路由是否需要登录
    * @param array $middleware 中间件数组
    * @return int 1表示需要登录，0表示不需要
    */
   private function checkLoginRequired(array $middleware): int
   {
      return (in_array('rap-api', $middleware) || in_array('check.auth', $middleware)) ? 1 : 0;
   }

   /**
    * 获取有效的中间件列表
    * @param array $middleware 原始中间件数组
    * @return array 过滤后的中间件数组
    */
   public function getMiddleware(array $middleware): array
   {
      return array_filter($middleware, fn($item) => !empty($item));
   }

   /**
    * 转换路径格式
    * 将URI路径转换为标准化的slug格式
    * @param string $path 原始路径
    * @return string 转换后的路径
    */
   public function convertPath($path)
   {
      // 去掉大括号
      $path = str_replace(['{', '}'], '', $path);
      // 替换斜杠为点
      $path = str_replace('/', '_', $path);
      // 将大写字母前添加一个点，并转为小写
      $path = strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $path));

      return $path;
   }
}
