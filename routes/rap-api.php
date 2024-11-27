<?php

use Chaihao\Rap\Http\Controllers\PermissionController;
use Chaihao\Rap\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;


// 根据变量添加前缀
$prefix = config('rap.api.prefix', '');

Route::prefix($prefix)->group(function () {
    // 不使用permission中间件
    Route::withoutMiddleware(['permission', 'routeGuard'])->name('员工管理.')->group(function () {
        Route::post('auth/login', [StaffController::class, 'login'])->name('登录账号');
        Route::post('auth/register', [StaffController::class, 'register'])->name('注册账号');
    });

    // 需要登录和权限
    Route::middleware(['routeGuard', 'permission'])->group(function () {
        // 员工
        Route::prefix('auth')->controller(StaffController::class)->name('员工管理.')->group(function () {
            Route::post('staff_info', 'staffInfo')->name('获取员工信息');
            Route::post('add', 'addStaff')->name('添加账户');
            Route::post('logout', 'logout')->name('登出');
            Route::post('edit', 'edit')->name('编辑账户');
            Route::post('edit_password', 'editPassword')->name('编辑账户密码');
        });


        // 权限
        Route::prefix('permission')->controller(PermissionController::class)->name('权限管理.')->group(function () {
            Route::post('add',  'addPermission')->name('添加权限');
            Route::post('get_all',  'getAllPermissions')->name('获取所有权限');
            Route::post('create_role',  'createRole')->name('创建角色');
            Route::post('get_all_roles',  'getAllRoles')->name('获取所有角色');
            Route::post('assign_role',  'assignRole')->name('分配角色');
            Route::post('remove_role',  'removeRole')->name('移除角色');
            Route::post('sync_role_permissions',  'syncRolePermissions')->name('同步角色权限');
            Route::post('get_role_permissions',  'getRolePermissions')->name('获取角色权限');
            Route::post('get_user_permissions',  'getUserPermissions')->name('获取用户权限');
            Route::post('get_user_roles',  'getUserRoles')->name('获取用户角色');
            Route::post('revoke_permission_to',  'revokePermissionTo')->name('撤销用户的指定权限');
            Route::post('sync_permissions',  'syncPermissions')->name('同步用户权限');
            Route::post('give_permission_to',  'givePermissionTo')->name('给用户直接分配权限');
        });
    });
});
