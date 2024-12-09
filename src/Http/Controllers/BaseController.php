<?php

namespace Chaihao\Rap\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Chaihao\Rap\Exception\ApiException;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Response, Validator};

abstract class BaseController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected Request $request;
    protected $response;
    protected $service;
    protected $model;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->response = app(Response::class);
        $this->initServiceAndModel();
    }

    /**
     * 初始化服务和模型
     */
    abstract protected function initServiceAndModel(): void;

    /**
     * 列表接口
     */
    public function list(): JsonResponse
    {
        try {
            $params = $this->getValidatedParams();
            $data = $this->service->getList($params);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 添加接口
     */
    public function add(): JsonResponse
    {
        try {
            $params = $this->request->all();
            // 验证数据
            $this->checkValidator($params, 'add');

            $data = $this->service->add($params);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 编辑接口
     */
    public function edit(int|null $id = null): JsonResponse
    {
        try {
            if (empty($id)) {
                $params = $this->request->all();
            } else {
                $params['id'] = $id;
            }
            // 验证数据
            $this->checkValidator($params, 'edit');

            $data = $this->service->edit($id, $params);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 删除接口
     */
    public function delete(int $id): JsonResponse
    {
        try {
            // 验证ID
            $this->checkValidator(['id' => $id], 'delete');

            $this->service->delete($id);
            return $this->success(null, '删除成功');
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 详情接口
     */
    public function detail(int $id): JsonResponse
    {
        try {
            // 验证ID
            $this->checkValidator(['id' => $id], 'detail');

            $data = $this->service->detail($id);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 获取验证后的参数
     */
    protected function getValidatedParams(): array
    {
        $params = $this->request->all();
        $this->validateListParams($params);
        return $params;
    }

    /**
     * 验证列表参数
     */
    protected function validateListParams(array $params): void
    {
        $rules = [
            'page' => 'integer|min:1',
            'page_size' => 'integer|between:1,100',
            'sort_field' => 'string|max:50',
            'sort_type' => 'in:asc,desc'
        ];

        $this->checkValidator($params, $rules);
    }

    /**
     * 验证数据
     */
    protected function checkValidator(array $data, string|array $scenario, array $messages = []): void
    {
        if (is_string($scenario)) {
            $rules = $this->getRulesByScenario($scenario, $data);
        } else {
            $rules = $scenario;
        }

        $validator = Validator::make($data, $rules, $messages);
        if ($validator->fails()) {
            throw new ApiException($validator->errors()->first() ?? '验证失败', 400);
        }
    }

    /**
     * 根据场景获取验证规则
     */
    protected function getRulesByScenario(string $scenario, array $data): array
    {
        $allRules = $this->model->rules ?? [];
        $scenarioRules = [];

        $fields = $this->getScenarioFields($scenario, $data);
        foreach ($fields as $field) {
            if (isset($allRules[$field])) {
                $scenarioRules[$field] = $this->adjustRuleForScenario($allRules[$field], $field, $scenario, $data);
            }
        }

        return $scenarioRules;
    }

    /**
     * 获取指定场景的字段列表
     */
    private function getScenarioFields(string $scenario, array $data): array
    {
        return property_exists($this->model, 'scenarios') && isset($this->model->scenarios[$scenario])
            ? $this->model->scenarios[$scenario]
            : array_keys($data);
    }

    /**
     * 根据场景调整验证规则
     */
    private function adjustRuleForScenario(string $rule, string $field, string $scenario, array $data): string
    {
        switch ($scenario) {
            case 'edit':
                return $this->adjustRuleForEdit($rule, $field, $data);
            case 'delete':
            case 'detail':
            case 'status':
                return $field === 'id'
                    ? 'required|integer|exists:' . $this->model->getTable() . ',id'
                    : $rule;
            case 'add':
                return $this->adjustRuleForAdd($rule, $field);
            default:
                return $rule;
        }
    }

    /**
     * 调整添加场景的验证规则
     */
    private function adjustRuleForAdd(string $rule, string $field): string
    {
        // 添加场景可能需要的特殊处理
        return $rule;
    }

    /**
     * 调整编辑场景的验证规则
     */
    private function adjustRuleForEdit(string $rule, string $field, array $data): string
    {
        // 移除 required 规则
        $rule = preg_replace('/\b(required\|?|(\|required))\b/', '', $rule);

        // 处理唯一性验证
        if (strpos($rule, 'unique:') !== false) {
            $rule .= ',' . ($data['id'] ?? '');
        }

        // 处理 ID 字段
        if ($field === 'id') {
            $rule = 'required|integer|exists:' . $this->model->getTable() . ',id';
        }

        return $rule;
    }

    /**
     * 处理异常
     */
    protected function handleException(\Throwable $e): JsonResponse
    {
        $message = $e instanceof ApiException ? $e->getMessage() : (config('app.debug') ? $e->getMessage() : '系统错误');
        $code = $e instanceof ApiException ? $e->getCode() : 500;

        return $this->failed($message, $code);
    }

    /**
     * 成功响应
     */
    protected function success($data = null, string $message = '操作成功'): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * 失败响应
     */
    protected function failed(string $message, int $code = 400): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => null
        ]);
    }

    /**
     * 修改状态
     */
    public function status(int $id): JsonResponse
    {
        try {
            // 验证ID
            $this->checkValidator(['id' => $id], 'status');

            $status = $this->request->input('status');
            $data = $this->service->editStatus($id, $status);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
