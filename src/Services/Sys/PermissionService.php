<?php

namespace  Chaihao\Rap\Services\Sys;

use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Jobs\AddPermissionJob;
use Chaihao\Rap\Models\Auth\Staff;
use Chaihao\Rap\Models\Sys\Permissions;
use Chaihao\Rap\Models\Sys\Roles;
use Chaihao\Rap\Services\BaseService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

class PermissionService extends BaseService
{
   /**
    * 给用户直接分配权限
    * @param int $userId 用户ID
    * @param string|array $permissions 权限名称或权限数组
    * @return Staff
    */
   public function givePermissionTo(int $userId, string|array $permissions)
   {
      $user = Staff::find($userId);
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
      $user = Staff::find($userId);
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
   public function syncPermissions(int $userId, array $permissions)
   {
      $user = Staff::find($userId);
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
    * 给用户分配权限
    * @param int $userId
    * @param array $permissions
    * @return Staff
    */
   public function assignPermission(int $userId, array $permissions)
   {
      $user = Staff::find($userId);
      if (!$user) {
         throw new ApiException('用户不存在');
      }
      $user->assignPermission($permissions);
      return $user;
   }

   /**
    * 获取用户的所有权限
    * @param int $userId 用户ID
    * @return array
    */
   public function getUserPermissions(int $userId, string $fieldColumn = null): array
   {
      $user = Staff::find($userId);
      if (!$user) {
         throw new ApiException('用户不存在');
      }
      if ($fieldColumn && in_array($fieldColumn, ['id', 'name',  'slug'])) {
         return $user->getAllPermissions()->pluck($fieldColumn)->toArray();
      }
      return $user->getAllPermissions()->select('id', 'name', 'method', 'uri', 'slug', 'group', 'group_name')->toArray();
   }

   /**
    * 获取用户的所有角色
    * @param int $userId
    * @return array
    */
   public function getUserRoles(int $userId): array
   {
      $user = Staff::find($userId);
      if (!$user) {
         throw new ApiException('用户不存在');
      }
      return $user->roles->select('id', 'name', 'slug', 'guard_name')->toArray();
   }

   /**
    * 分配角色
    * @param int $id
    * @param string|array $role
    * @return Staff
    */
   public function assignRole(int $id, string|array $role)
   {
      $user = Staff::find($id);
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
      $user = Staff::find($id);
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
      return Roles::create([
         'name' => $data['name'],
         'slug' => $data['slug'],
         'guard_name' => $data['guard_name'] ?? 'api'
      ]);
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
      // 获取按组分组的权限
      $groupedPermissions = Permissions::all(['id', 'name', 'method', 'uri', 'slug', 'group', 'group_name'])->groupBy('group');
      // 重组数据结构
      $result = [];
      foreach ($groupedPermissions as $group => $permissions) {
         // 获取第一个权限项的 group_name
         $groupName = $permissions->first()->group_name;
         $result[] = [
            'group' => $group,
            'group_name' => $groupName,
            'data' => $permissions->toArray()
         ];
      }
      return $result;
   }

   /**
    * 添加权限
    * 遍历所有路由并将其添加到权限表中
    * @return array 添加的权限ID数组
    * @throws Exception
    */
   public function addPermission(): array
   {
      try {
         $routeCollection = Route::getRoutes();
         $allRoutes = [];
         // 获取所有权限的URI
         $existingPermissions = Permissions::onlyTrashed()->pluck('uri')->toArray();


         // 如果要软删除所有未删除的记录，使用 whereNull('deleted_at')->delete()
         // 如果要软删除所有记录（包括已删除的），使用 withTrashed()->delete()
         // 如果要永久删除记录，使用 forceDelete()
         // 软删所有权限
         Permissions::whereNull('deleted_at')->delete();

         foreach ($routeCollection as $route) {
            $routeInfo = $this->buildRouteInfo($route);
            if (empty($routeInfo['group_name'])) {
               continue;
            }
            // 开发环境直接添加权限
            $id = $this->addData($routeInfo, $existingPermissions);
            if ($id) {
               $allRoutes[] = $id;
            }
         }

         return $allRoutes;
      } catch (Exception $e) {
         Log::error('添加权限失败：' . $e->getMessage());
         throw $e;
      }
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

      return [
         'method' => implode('|', $route->methods()),
         'uri' => $route->uri(),
         'name' => $this->convertPath($route->uri()),
         'action' => $route->getActionName(),
         'controller' => $controller,
         'slug' => $route->getName(),
         'prefix' => $route->getPrefix(),
         'status' => $this->isApiRoute($route->getPrefix()),
         'middleware' => $this->getMiddleware($route->gatherMiddleware()),
         'is_login' => $this->checkLoginRequired($route->gatherMiddleware()),
         'group' => $group,
         'group_name' => $this->translateToChinese($group),
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
      return in_array('routeGuard', $middleware) ? 1 : 0;
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
    * 添加权限数据到数据库
    * @param array $routes 路由信息
    * @param array $existingPermissions 已存在的权限URI列表
    * @return int|false 成功返回权限ID，失败返回false
    */
   public function addData(array $routes, array $existingPermissions): int|false
   {
      if (empty($routes['group']) || empty($routes['uri'])) {
         return false;
      }
      // 检查权限是否已存在
      if (!in_array($routes['uri'], $existingPermissions)) {
         $result =  Permissions::create($routes);
         return $result->id;
      } else {
         $result = Permissions::where('uri', $routes['uri'])->withTrashed()->first();
         // 恢复软删
         $result->restore();
         return $result->id;
      }
   }

   /**
    * 转换路径格式
    * 将URI路径转换为标准化的slug格式
    * @param string $path 原始路径
    * @return string 转换后的路径
    */
   function convertPath($path)
   {
      // 去掉大括号
      $path = str_replace(['{', '}'], '', $path);
      // 替换斜杠为点
      $path = str_replace('/', '_', $path);
      // 将大写字母前添加一个点，并转为小写
      $path = strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $path));

      return $path;
   }

   /**
    * 将控制器名称翻译成中文
    * @param string|null $controller
    * @return string|null
    */
   private function translateToChinese(?string $controller): ?string
   {
      if (!$controller) {
         return null;
      }
      // 这里可以定义控制器名称的中英文映射关系
      $translations = [
         // 系统相关
         'Permission' => '权限',
         'System' => '系统',
         'SystemDict' => '系统字典',
         'SystemDictType' => '系统字典类型',
         'SystemDictData' => '系统字典数据',
         'SysAppUpgrade' => '系统升级',

         // 用户相关
         'User' => '用户',
         'UserPay' => '用户支付',
         'UsersWithdrawal' => '用户提现',
         'UserPayRefund' => '支付退款',
         'UserScoreLog' => '积分日志',
         'UserRightsProfit' => '权益收益',
         'UserSign' => '用户签到',

         // 商户相关
         'Master' => '员工管理',
         'Merchant' => '商户管理',
         'MerchantStaffs' => '商户员工',
         'MerchantStore' => '店铺管理',

         // 商品相关
         'Product' => '商品管理',
         'ProductCategory' => '商品分类',
         'ProductsTag' => '商品标签',
         'ProductsTags' => '商品标签',

         // 订单相关
         'Order' => '订单管理',

         // 文档相关
         'Science' => '科普',
         'ScienceLog' => '科普日志',
         'Note' => '笔记管理',
         'NoteLog' => '笔记日志',

         // 工具相关
         'Address' => '地址管理',
         'AliPay' => '支付宝',
         'Upload' => '文件上传',
         'Logistics' => '物流',

         // 其他功能
         'Feedback' => '反馈管理',
         'GiftPackages' => '礼包管理',
         'GiftPackageItems' => '礼包内容',
         'GiftPackageOrders' => '礼包订单',

         // 任务相关
         'Task' => '任务管理',
         'TaskClassify' => '任务分类',
         'TaskDeposit' => '任务押金',

         // 部署相关
         'Deploy' => '部署管理'
      ];

      return $translations[$controller] ?? NULL;
   }



   /**
    * 过滤并获取基础控制器名称
    * 主要用于识别和提取基础控制器，避免重复和子控制器
    * @param array $controllers 控制器数组
    * @return array 过滤后的基础控制器名称数组，包含控制器名和对应的中文路由名
    */
   private function filterBaseControllers(array $controllers): array
   {
      $baseControllers = [];
      $excludedControllers = ['Closure', 'HealthCheck', 'ExecuteSolution', 'UpdateConfig'];

      // 首先收集所有可能的基础控制器
      foreach (array_keys($controllers) as $controller) {
         if (in_array($controller, $excludedControllers)) {
            continue;
         }

         // 检查是否是其他控制器的前缀
         $isPrefix = false;
         foreach ($baseControllers as $base) {
            if ($controller !== $base && strpos($controller, $base) === 0) {
               $isPrefix = true;
               break;
            }
         }

         if (!$isPrefix) {
            $baseControllers[] = $controller;
         }
      }

      return array_map(fn($base) => [
         'controller' => $base,
         'routeName' => $this->controllerToChinese($controllers[$base])
      ], array_unique($baseControllers));
   }

   /**
    * 从路由自动生成权限模块
    * 将系统中的路由信息转换为权限模块并存储到数据库
    * 整个过程在事务中进行，确保数据一致性
    * @return bool 生成成功返回true，失败抛出异常
    * @throws ApiException 当找不到控制器或生成过程出错时
    */
   public function generateModulesFromRoutes(): bool
   {
      try {
         DB::beginTransaction();

         $processedControllers = $this->processRoutes();
         if (empty($processedControllers)) {
            throw new ApiException('没有找到有效的控制器');
         }

         $baseControllers = $this->filterBaseControllers($processedControllers);
         $moduleData = $this->prepareModuleData($baseControllers, $processedControllers);

         // 清空并插入新模块
         DB::table('permission_modules')->truncate();
         if (!empty($moduleData)) {
            DB::table('permission_modules')->insert($moduleData);
         }

         DB::commit();
         return true;
      } catch (ApiException $e) {
         DB::rollBack();
         Log::error('生成权限模块失败：' . $e->getMessage());
         throw new ApiException('生成权限模块失败: ' . $e->getMessage());
      }
   }

   /**
    * 处理路由并收集控制器信息
    * 遍历所有路由，提取控制器信息并去重
    * @return array 返回格式为 ['控制器名' => '路由名称'] 的关联数组
    */
   private function processRoutes(): array
   {
      $processedControllers = [];
      foreach (Route::getRoutes() as $route) {
         $controller = $this->getControllerFromRoute($route);
         if (!$controller || isset($processedControllers[$controller])) {
            continue;
         }
         $processedControllers[$controller] = $route->getName();
      }
      return $processedControllers;
   }

   /**
    * 准备模块数据
    * 将控制器信息转换为数据库需要的模块数据格式
    * @param array $baseControllers 基础控制器数组
    * @param array $processedControllers 处理过的控制器信息
    * @return array 返回准备插入数据库的模块数据数组
    */
   private function prepareModuleData(array $baseControllers, array $processedControllers): array
   {
      $now = now();
      return array_map(function ($item) use ($processedControllers, $now) {
         $route = Route::getRoutes()->getByName($processedControllers[$item['controller']]);
         return [
            'controller' => $item['controller'],
            'module_name' => $item['routeName'],
            'prefix' => $route ? $this->getModulePrefix($route) : null,
            'sort' => 0,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now
         ];
      }, $baseControllers);
   }

   /**
    * 将控制器名称翻译成中文
    * 移除路由名称中的常见动作词，保留核心功能名称
    * @param string|null $routeName 路由名称
    * @return string 处理后的中文名称，如果处理后为空则返回"其他"
    */
   private function controllerToChinese(?string $routeName): string
   {
      if (!$routeName) {
         return "其他";
      }

      $removeWords = [
         '登录',
         '注册',
         '添加',
         '创建',
         '编辑',
         '删除',
         '获取',
         '详情',
         '列表',
         '查看',
         '搜索',
         '同步',
         '导出',
         '导入'
      ];

      return str_replace($removeWords, '', $routeName) ?: "其他";
   }

   /**
    * 从路由获取控制器名称
    * 解析路由的动作名称，提取控制器类名并移除Controller后缀
    * @param \Illuminate\Routing\Route $route 路由对象
    * @return string|null 返回控制器名称，如果无法解析则返回null
    */
   private function getControllerFromRoute($route): ?string
   {
      $parts = $route->getActionName() ? explode('\\', $route->getActionName()) : NULL;
      $controller = $parts ? explode('@', end($parts))[0] : null;
      $group = str_replace('Controller', '', $controller);
      return $group;
   }

   /**
    * 获取模块前缀
    * 从路由中提取URL前缀，用于模块分组
    * @param \Illuminate\Routing\Route $route 路由对象
    * @return string|null 返回处理后的前缀，如果没有前缀则返回null
    */
   private function getModulePrefix($route): ?string
   {
      $prefix = $route->getPrefix();
      return $prefix ? trim($prefix, '/') : null;
   }
}
