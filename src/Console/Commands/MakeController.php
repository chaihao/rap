<?php

namespace Chaihao\Rap\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeController extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:controller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '创建自定义Controller';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $type = 'Controller';  // command type


    protected $needFunctionNameList = false;

    protected $signature = 'make:controller {name} {--with-functions : Include predefined functions}';

    protected function getStub()
    {
        return __DIR__ . '/Stubs/controller.stub';
    }

    /**
     * 获取默认命名空间
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\Controllers';
    }

    /**
     * 设置类名和自定义替换内容
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceClass($stub, $name): string
    {
        $stub = $this->replaceModelName($stub);
        $stub = $this->replaceNamespaceData($stub, $name);
        return parent::replaceClass($stub, $name);
    }

    /**
     * 替换模型名称
     * @param string $stub
     * @return string
     */
    protected function replaceModelName($stub)
    {
        $name = $this->getNameInput();
        $modelName = $this->getModelName($name);

        $modelNameSnakeCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $modelName));
        $stub = str_replace('TABLE_NAME', $modelNameSnakeCase, $stub);
        $stub = str_replace('DummyModel', $modelName . 'Model', $stub);
        $stub = str_replace('DummyService', $modelName . 'Service', $stub);
        if ($this->needFunctionNameList) {
            $stub = str_replace('FUNCTION_NAME_LIST', $this->getFunctionNameList(), $stub);
        } else {
            $stub = str_replace('FUNCTION_NAME_LIST', '', $stub);
        }
        return $stub;
    }

    /**
     * 替换命名空间数据
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceNamespaceData($stub, $name)
    {
        $namespace = $this->getNamespace($name);
        $baseControllerNamespace = $this->getBaseControllerNamespace($namespace);

        return str_replace('USE_BASE_CONTROLLER', $baseControllerNamespace, $stub);
    }

    /**
     * 获取模型名称
     * @param string $name
     * @return string
     */
    private function getModelName($name): string
    {
        $exName = explode('/', $name);
        return str_replace('Controller', '', end($exName));
    }

    /**
     * 获取基础控制器命名空间
     * @param string $namespace
     * @return string
     */
    private function getBaseControllerNamespace($namespace): string
    {
        $segments = ['Admin', 'Web', 'App'];

        foreach ($segments as $segment) {
            if (strpos($namespace, $segment) !== false) {
                return substr($namespace, 0, strpos($namespace, $segment) + strlen($segment)) . '\BaseController';
            }
        }

        return $namespace . '\BaseController';
    }

    /**
     * 执行控制台命令。
     *
     * @return int
     */
    public function handle()
    {
        $this->needFunctionNameList = $this->option('with-functions');
        $name = $this->qualifyClass($this->getNameInput());
        $path = $this->getPath($name);

        // 检查文件是否已存在
        if ($this->alreadyExists($this->getNameInput())) {
            $this->components->warn(sprintf('%s 已经存在', $this->type));
            if (!$this->components->confirm('是否要覆盖现有控制器？')) {
                return 1;
            }
        }

        // 生成文件
        $this->makeDirectory($path);
        $this->files->put($path, $this->sortImports($this->buildClass($name)));

        // 输出成功信息和文件路径
        $this->components->info(sprintf('%s 已经成功创建。', $this->type));
        $this->components->info(sprintf('文件路径: %s', $path));

        return 0;
    }

    /**
     * 删除现有文件
     * @param string $path
     */
    protected function deleteExistingFile($path)
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * 创建新控制器
     * @param string $path
     * @param string $name
     */
    protected function createNewController($path, $name)
    {
        $this->makeDirectory($path);
        $this->files->put($path, $this->buildClass($name));
    }




    public function getFunctionNameList()
    {
        $functionNameList = "
        /**
         * 获取列表
         * @throws \Exception
         * @return JsonResponse
         */
        public function list(): JsonResponse
        {
            try {
                \$params = \$this->request->all();
                \$data = \$this->service->getList(\$params);
                if (!\$data['status']) {
                    throw new Exception(\$data['msg']);
                }
                return \$this->success(\$data['data']);
            } catch (\Throwable \$th) {
                return \$this->failed(\$th->getMessage());
            }
        }

        /**
         * 添加数据
         * @throws \Exception
         * @return JsonResponse
         */
        public function add(): JsonResponse
        {
            try {
                \$params = \$this->request->all();
                \$this->service->checkValidator(\$params, 'add');
                \$data = \$this->service->add(\$params);
                if (!\$data['status']) {
                    throw new Exception(\$data['msg']);
                }
                return \$this->success([], \$data['msg']);
            } catch (\Throwable \$th) {
                return \$this->failed(\$th->getMessage());
            }
        }

        /**
         * 删除数据
         * @throws \Exception
         * @return \Illuminate\Http\JsonResponse
         */
        public function del(): JsonResponse
        {
            try {
                \$params = \$this->request->all();
                \$this->service->checkValidator(\$params, 'delete');
                \$data = \$this->service->del(\$params['id']);
                if (!\$data['status']) {
                    throw new Exception(\$data['msg']);
                }
            } catch (\Throwable \$th) {
                return \$this->failed(\$th->getMessage());
            }
            return \$this->message(\$data['msg']);
        }

        /**
         * 编辑状态
         * @throws \Exception
         * @return \Illuminate\Http\JsonResponse
         */
        public function editStatus(): JsonResponse
        {
            try {
                \$params = \$this->request->all();
                \$this->service->checkValidator(\$params, 'status');
                \$data = \$this->service->editStatus(\$params['id'], \$params['status'] ?? null);
                if (!\$data['status']) {
                    throw new Exception(\$data['msg']);
                }
            } catch (\Throwable \$th) {
                return \$this->failed(\$th->getMessage());
            }
            return \$this->message(\$data['msg']);
        }

        /**
         * 编辑数据
         * @throws \Exception
         * @return \Illuminate\Http\JsonResponse
         */
        public function edit(): JsonResponse
        {
            try {
                \$params = \$this->request->all();
                \$this->service->checkValidator(\$params, 'edit');
                \$data = \$this->service->edit(\$params['id'], \$params);
                if (!\$data['status']) {
                    throw new Exception(\$data['msg']);
                }
                return \$this->success([], \$data['msg']);
            } catch (\Throwable \$th) {
                return \$this->failed(\$th->getMessage());
            }
        }

        /**
         * 获取详细数据
         * @throws \Exception
         * @return \Illuminate\Http\JsonResponse
         */
        public function get(): JsonResponse
        {
            try {
                \$params = \$this->request->all();
                \$this->service->checkValidator(\$params, 'get');
                \$data = \$this->service->get(\$params['id']);
                if (!\$data['status']) {
                    throw new Exception(\$data['msg']);
                }
            } catch (\Throwable \$th) {
                return \$this->failed(\$th->getMessage());
            }

            return \$this->success(\$data['data']);
        }
        ";

        return $functionNameList;
    }
}
