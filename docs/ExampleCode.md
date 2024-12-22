### 加锁操作使用示例

```php
// 基础使用
$record = $service->addWithLock($data);

// 自定义锁选项
$record = $service->editWithLock($id, $data, [
    'timeout' => 20,
    'waitTimeout' => 5
]);

// 批量操作
$result = $service->batchWithLock('update-status', [1, 2, 3], function() use ($ids) {
    // 批量更新操作
    return $this->getModel()->whereIn('id', $ids)->update(['status' => 1]);
});

// 自定义锁操作
$result = $service->withLock(
    'custom-operation',
    function() {
        // 自定义操作
    },
    ['timeout' => 30]
);
```
### 创建Service使用示例


```php
# .env 配置
# RAP_CREATE_SERVICES_MODEL=true # 创建service时，同时创建model
# RAP_CREATE_SERVICES_CONTROLLER=true # 创建service时，同时创建
# RAP_CONTROLLER_VERSION=V1 # 控制器版本

# config/rap.php 配置
'controller' => [
    'version' => env('RAP_CONTROLLER_VERSION', 'V1\\'), // 控制器版本
],

php artisan make:services User/UserService

# 生成文件
app/Http/Controllers/User/UserController.php

app/Models/User/User.php

app/Services/User/UserService.php

```
**注意**：
- 如果配置了RAP_CONTROLLER_VERSION=V1，则生成文件为app/Http/Controllers/V1/User/UserController.php
- 如果配置了RAP_CONTROLLER_VERSION=V2，则生成文件为app/Http/Controllers/V2/User/UserController.php
- 如果未配置RAP_CONTROLLER_VERSION，则生成文件为app/Http/Controllers/User/UserController.php

### 路由配置

```php

# .env 配置
RAP_API_PREFIX=api/v1 # 路由前缀

# config/rap.php 配置
'api' => [
    'prefix' => env('RAP_API_PREFIX', 'api'), // api前缀
],

```

### 路由示例

```php

# 路由组
// 'rap-api' => [
//     'check.auth',
//     'permission',
//     'request.response.logger'
// ];

Route::prefix(config('rap.api.prefix'))->middleware(['rap-api'])->name('用户管理.')->controller(UserController::class)->group(function () {
    Route::get('list', 'list')->name('用户列表')->withoutMiddleware(['rap-api']);
    Route::get('list', 'list')->name('用户列表')->withoutMiddleware(['permission','check.auth']);
    Route::get('detail', 'detail')->name('用户详情')->withoutMiddleware(['permission']);
    Route::post('add', 'add')->name('添加用户')->withoutMiddleware(['request.response.logger']);
    Route::post('edit', 'edit')->name('编辑用户');
    Route::post('delete', 'delete')->name('删除用户');
});
```

### 异常示例

```php
// 抛出一般错误
throw new ApiException('操作失败', ApiException::BAD_REQUEST);

// 抛出未授权异常
throw ApiException::unauthorized('请先登录');

// 抛出资源未找到异常
throw ApiException::notFound('订单不存在');

// 抛出操作失败异常
throw ApiException::failed('操作失败');

// 抛出验证错误异常
throw ApiException::validationError('验证失败', [
    'name' => ['名称不能为空'],
    'price' => ['价格必须大于0']
]); 
```

### 参数验证示例

```php

$this->service->checkValidator($request->all(), [
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
    'password' => 'required|string|min:8',
],[
    'name'=>'名称不能为空',
    'email'=>'邮箱不能为空',
    'email.unique'=>'邮箱已存在',
    'password'=>'密码不能为空'
]);

$this->service->checkValidator($request->all(),'add');

```