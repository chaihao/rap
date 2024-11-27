<?php

namespace Chaihao\Rap\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use App\Exception\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class BaseController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $request;
    protected $response;
    protected $service;
    protected $model;

    public function __construct()
    {
        $this->request = app(Request::class);
        $this->response = app(Response::class);
    }

    /**
     * 设置服务和模型
     */
    protected function setServiceAndModel($service, $model)
    {
        $this->service = $service;
        $this->model = $model;
        $this->service->setModel($this->model);
    }

    /**
     * 获取列表
     * @throws ApiException
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        try {
            $params = $this->request->all();
            $data = $this->service->getList($params);
            return $this->success($data);
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
    }

    /**
     * 添加数据
     * @throws ApiException
     * @return JsonResponse
     */
    public function add(): JsonResponse
    {
        try {
            $params = $this->request->all();
            $this->service->checkValidator($params, 'add');
            $data = $this->service->add($params);
            return $this->success($data);
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
    }

    /**
     * 删除数据
     * @throws ApiException
     * @return JsonResponse
     */
    public function del($id = null): JsonResponse
    {
        try {
            $params = $this->request->all();
            if ($id !== null) {
                $params['id'] = $id;
            }
            $this->service->checkValidator($params, 'delete');
            $data = $this->service->del($params['id']);
            return $this->message($data['message']);
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
    }

    /**
     * 编辑状态
     * @throws ApiException
     * @return JsonResponse
     */
    public function editStatus($id = null): JsonResponse
    {
        try {
            $params = $this->request->all();
            if ($id !== null) {
                $params['id'] = $id;
            }
            $this->service->checkValidator($params, 'status');
            $data = $this->service->editStatus($params['id'], $params['status'] ?? null);
            return $this->message($data['message']);
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
    }

    /**
     * 编辑数据
     * @throws ApiException
     * @return JsonResponse
     */
    public function edit(): JsonResponse
    {
        try {
            $params = $this->request->all();
            $this->service->checkValidator($params, 'edit');
            $data = $this->service->edit($params['id'], $params);
            return $this->message($data['message']);
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
    }

    /**
     * 获取详细数据
     * @throws ApiException
     * @return JsonResponse
     */
    public function get($id = null): JsonResponse
    {
        try {
            $params = $this->request->all();
            if ($id !== null) {
                $params['id'] = $id;
            }
            $this->service->checkValidator($params, 'get');
            $data = $this->service->get($params['id']);
            return $this->success($data);
        } catch (\Throwable $th) {
            return $this->failed($th->getMessage());
        }
    }

    /**
     * 返回消息
     * @param string $message
     * @return JsonResponse
     */
    public function message(string $message = '操作成功'): JsonResponse
    {
        return response()->json([
            'status' => true,
            'code' => 200,
            'msg' => $message,
        ]);
    }

    /**
     * 返回成功
     * @param mixed $data
     * @param string $message
     * @return JsonResponse
     */
    public function success(mixed $data = [], string $message = '处理成功'): JsonResponse
    {
        return response()->json([
            'status' => true,
            'code' => 200,
            'data' => $data,
            'msg' => $message,
        ]);
    }

    /**
     * 返回失败
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public function failed(string $message, int $code = 400): JsonResponse
    {
        return response()->json([
            'status' => false,
            'code' => $code,
            'msg' => $message,
        ]);
    }

    /**
     * 字段验证
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @throws ApiException
     */
    protected function checkValidator(array $data, array $rules, array $messages = []): void
    {
        $validator = Validator::make($data, $rules, $messages);
        if ($validator->fails()) {
            throw new ApiException($validator->errors()->first());
        }
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    function getClientIP(): string
    {
        $ip = request()->ip();

        if (!$ip || $ip === '::1' || $ip === '127.0.0.1') {
            $ip = request()->header('X-Forwarded-For');
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
        }

        if (!$ip) {
            $ip = request()->header('X-Real-IP');
        }

        return $ip ?: '0.0.0.0';
    }
}
