<?php

namespace Chaihao\Rap\Http\Controllers;

use Chaihao\Rap\Exception\ApiException;
use Chaihao\Rap\Models\Sys\RolesModel;
use Chaihao\Rap\Services\Sys\PermissionService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PermissionController extends BaseController
{
    protected function init(): void
    {
        $this->service = app(PermissionService::class);
        $this->model = app(Permission::class);
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
     * 分配权限
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignPermission(): JsonResponse
    {
        $this->checkValidator($this->request->all(), ['id' => 'required|integer', 'permissions' => 'required|array']);
        $this->service->assignPermission($this->request->id, $this->request->permissions);
        return $this->success('权限分配成功');
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
        $roles = Role::with('permissions')->get();
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
        $permissionService = new PermissionService();
        $data = $permissionService->getAllPermissions();
        return $this->success($data);
    }

    /**
     * 添加权限
     */
    public function addPermission(): JsonResponse
    {
        $permissionService = new PermissionService();
        $data = $permissionService->addPermission();
        return $this->success($data);
    }
}
