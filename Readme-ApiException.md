
## 异常处理

### 1. ApiException 使用

#### 1.1 基础异常

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

#### 1.2 在控制器中使用

```php
public function create(Request $request)
{
    try {
        // 验证参数
        $this->checkValidator($request->all(), [
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1'
        ]);
        
        // 检查商品
        $product = Product::find($request->product_id);
        if (!$product) {
            throw ApiException::notFound('商品不存在');
        }
        
        // 检查库存
        if ($product->stock < $request->quantity) {
            throw new ApiException('库存不足', ApiException::BAD_REQUEST);
        }
        
        // 创建订单...
        
    } catch (ApiException $e) {
        return $e->render();
    }
}
```

#### 1.3 在服务层使用

```php
public function createOrder(array $data)
{
    try {
        DB::beginTransaction();
        
        // 检查用户余额
        if ($this->getUserBalance() < $data['total_amount']) {
            throw new ApiException('账户余额不足', ApiException::BAD_REQUEST);
        }
        
        // 扣减库存
        if (!$this->deductStock($data['product_id'], $data['quantity'])) {
            throw new ApiException('库存扣减失败', ApiException::SERVER_ERROR);
        }
        
        // 创建订单记录
        $order = Order::create($data);
        if (!$order) {
            throw new ApiException('订单创建失败', ApiException::SERVER_ERROR);
        }
        
        DB::commit();
        return $this->success($order);
        
    } catch (\Exception $e) {
        DB::rollBack();
        throw new ApiException($e->getMessage(), ApiException::SERVER_ERROR);
    }
}
```

### 2. 错误码说明

| 错误码 | 说明 | 使用场景 |
|--------|------|----------|
| 400 | 请求错误 | 参数错误、业务逻辑错误 |
| 401 | 未授权 | 未登录、token失效 |
| 403 | 禁止访问 | 无权限访问 |
| 404 | 未找到 | 资源不存在 |
| 422 | 验证错误 | 表单验证失败 |
| 500 | 服务器错误 | 系统异常 |

### 3. 调试模式

在 `.env` 中设置 `APP_DEBUG=true` 时,异常响应会包含调试信息:

```json
{
    "success": false,
    "code": 500,
    "message": "系统错误",
    "debug": {
        "file": "/app/Services/OrderService.php",
        "line": 100,
        "trace": [...]
    }
}
```
