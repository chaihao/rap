<?php

namespace Chaihao\Rap\Http\Controllers;

use Illuminate\Http\Request;
use Chaihao\Rap\Models\Sys\Roles;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Chaihao\Rap\Services\Sys\PermissionService;
use Illuminate\Support\Facades\App;

class PermissionController extends BaseController
{
    protected function initServiceAndModel(): void
    {
        $this->service = App::make(PermissionService::class);
        $this->model = App::make(Permission::class);
    }
    /**
     * 给用户直接分配权限
     * @return \Illuminate\Http\JsonResponse
     */
    public function givePermissionTo(): JsonResponse
    {
        $this->checkValidator($this->request->all(), ['id' => 'required|integer', 'permissions' => 'required|array']);
        $this->service->givePermissionTo($this->request->id, $this->request->permissions);
        return $this->success('权限分配成功');
    }

    /**
     * 撤销用户的指定权限
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokePermissionTo(): JsonResponse
    {
        $this->checkValidator($this->request->all(), [
            'id' => 'required|integer',
            'permissions' => 'required|array'
        ]);
        $this->service->revokePermissionTo($this->request->id, $this->request->permissions);
        return $this->success('角色权限同步成功');
    }

    /**
     * 同步用户权限
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncPermissions(): JsonResponse
    {
        $this->checkValidator($this->request->all(), [
            'id' => 'required|integer',
            'permissions' => 'required|array'
        ]);
        $this->service->syncPermissions($this->request->id, $this->request->permissions);
        return $this->success('用户权限同步成功');
    }

    /**
     * 获取用户的所有权限
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPermissions(): JsonResponse
    {
        $this->checkValidator($this->request->all(), ['id' => 'required|integer']);
        $data = $this->service->getUserPermissions($this->request->id, 'name');
        return $this->success($data);
    }


    /**
     * 获取用户的所有角色
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserRoles(): JsonResponse
    {
        $this->checkValidator($this->request->all(), ['id' => 'required|integer']);
        $data = $this->service->getUserRoles($this->request->id);
        return $this->success($data);
    }


    /**
     * 分配角色
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRole(): JsonResponse
    {
        $this->checkValidator($this->request->all(), [
            'role' => 'required',
            'id' => 'required|integer'
        ]);
        $this->service->assignRole($this->request->id, $this->request->role);
        return $this->success('角色分配成功');
    }

    /**
     * 移除角色
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeRole(): JsonResponse
    {
        $this->checkValidator($this->request->all(), [
            'id' => 'required|integer',
            'role' => 'required|string'
        ]);
        $this->service->removeRole($this->request->id, $this->request->role);

        return $this->success('角色移除成功');
    }

    /**
     * 同步角色权限
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncRolePermissions()
    {
        $this->checkValidator($this->request->all(), [
            'role_id' => 'required|integer',
            'permissions' => 'required|array'
        ]);
        $this->service->syncRolePermissions($this->request->role_id, $this->request->permissions);
        return $this->success('权限同步成功');
    }



    /**
     * 获取所有角色
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllRoles()
    {
        $roles = Roles::all();
        return $this->success($roles);
    }


    /**
     * 创建角色
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createRole(): JsonResponse
    {
        $this->checkValidator($this->request->all(), [
            'name' => 'required|string|unique:roles,name',
            'slug' => 'required|string|unique:roles,slug',
            'guard_name' => 'string|nullable'
        ]);
        $role = $this->service->createRole($this->request->all());

        return $this->success($role, '角色创建成功');
    }

    /**
     * 更新角色
     */
    public function updateRole(): JsonResponse
    {
        $params = $this->request->all();
        $this->checkValidator($params, [
            'id' => 'required|integer',
            'name' => 'required|string|unique:roles,name,' . $params['id'],
            'guard_name' => 'string|nullable'
        ], [
            'id.required' => '角色ID不能为空',
            'name.required' => '角色名称不能为空',
            'name.unique' => '角色名称已存在',
            'guard_name.string' => 'guard_name必须是字符串'
        ]);
        $role = $this->service->updateRole($params['id'], $params);
        return $this->success($role, '角色更新成功');
    }

    public function getRolePermissions(): JsonResponse
    {
        $this->checkValidator($this->request->all(), ['role_id' => 'required|integer']);
        $data = $this->service->getRolePermissions($this->request->role_id);
        return $this->success($data);
    }

    /**
     * 获取所有权限
     */
    public function getAllPermissions(): JsonResponse
    {
        $data = $this->service->getAllPermissions();
        return $this->success($data);
    }

    /**
     * 添加权限
     */
    public function addPermission(): JsonResponse
    {
        $data = $this->service->addPermission();
        return $this->success($data);
    }
    /**
     * 获取角色列表
     */
    public function getRolesList(): JsonResponse
    {
        $params = $this->request->all();
        $data = $this->service->getRolesList($params);
        return $this->success($data);
    }

    /**
     * 获取权限列表
     */
    public function getPermissionsList(): JsonResponse
    {
        $params = $this->request->all();
        $data = $this->service->getPermissionsList($params);
        return $this->success($data);
    }
}
