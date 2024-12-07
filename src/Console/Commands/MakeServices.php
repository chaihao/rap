<?php

namespace Chaihao\Rap\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeServices extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:services';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '创建自定义Services';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $type = 'Service';  // command type

    protected function getStub()
    {
        return __DIR__ . '/Stubs/service.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Services';
    }

    /**
     * 设置类名和自定义替换内容
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        // 解析服务类名和路径
        $serviceInfo = $this->parseServiceName($name);
        if (!$serviceInfo) {
            return false;
        }

        // 处理相关文件
        if (!$this->handleRelatedFiles($serviceInfo)) {
            return false;
        }

        // 替换模板内容
        return $this->replaceStubContent($stub, $serviceInfo, $name);
    }

    /**
     * 解析服务类名和路径
     * @param string $name
     * @return array|false
     */
    private function parseServiceName($name)
    {
        $serviceNameArray = explode('\\', $name);
        $serviceName = end($serviceNameArray);

        // 获取 Services 后的路径
        $path = $this->getServicePath($serviceNameArray);

        return [
            'serviceName' => $serviceName,
            'path' => $path,
            'modelName' => str_replace('Service', '', $serviceName),
        ];
    }

    /**
     * 获取服务类路径
     * @param array $serviceNameArray
     * @return string
     */
    private function getServicePath($serviceNameArray)
    {
        $path = '';
        $servicesFound = false;

        foreach ($serviceNameArray as $value) {
            if ($servicesFound && $value !== end($serviceNameArray)) {
                $path .= $value . '\\';
            }
            if ($value === 'Services') {
                $servicesFound = true;
            }
        }

        return $path;
    }

    /**
     * 处理相关文件（Model 和 Controller）
     * @param array $serviceInfo
     * @return bool
     */
    private function handleRelatedFiles($serviceInfo)
    {
        // 创建service时，同时创建model
        if (config('rap.create_services.model')) {
            // 处理 Model
            if (!$this->handleModelFile($serviceInfo)) {
                return false;
            }
        }

        // 创建service时，同时创建controller
        if (config('rap.create_services.controller')) {
            // 处理 Controller
            if (!$this->handleControllerFile($serviceInfo)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 替换模板内容
     * @param string $stub
     * @param array $serviceInfo
     * @param string $name
     * @return string
     */
    private function replaceStubContent($stub, $serviceInfo, $name)
    {
        $modelNamespace = 'App\\Models\\' . str_replace('\\\\', '\\', $serviceInfo['path']) . $serviceInfo['modelName'];

        $stub = str_replace('USED_DUMMY_MODEL', $modelNamespace, $stub);
        $stub = str_replace('DummyModel', $serviceInfo['modelName'], $stub);

        return parent::replaceClass($stub, $name);
    }

    /**
     * 替换自定义内容
     * @param $stub
     * @return mixed
     */
    protected function replaceModelName($stub)
    {
        //将输入的类名处理成表名
        $name =  $this->getNameInput();
        return str_replace('MODEL', $name, $stub);
    }

    /**
     * 处理 Model 文件
     * @param array $serviceInfo
     * @return bool
     */
    private function handleModelFile($serviceInfo)
    {
        $modelPath = app_path('Models/' . $serviceInfo['path'] . $serviceInfo['modelName'] . '.php');

        if (!file_exists($modelPath)) {
            $this->components->warn(sprintf("正在创建 Model [Models/{$serviceInfo['path']}{$serviceInfo['modelName']}]..."));

            // 处理模型名称，确保正确的目录分隔符
            $modelName = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $serviceInfo['path']);
            $modelName = trim($modelName, DIRECTORY_SEPARATOR);
            $modelName = $modelName ? $modelName . DIRECTORY_SEPARATOR . $serviceInfo['modelName'] : $serviceInfo['modelName'];

            try {
                $this->call('make:model', [
                    'name' => $modelName,
                ]);
            } catch (\Exception $e) {
                $this->components->error("创建 Model 失败：" . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * 处理 Controller 文件
     * @param array $serviceInfo
     * @return bool
     */
    private function handleControllerFile($serviceInfo)
    {
        // 获取并格式化版本号
        $version = $this->formatVersionPath(config('rap.namespace.controller.version'));

        // 构建控制器信息
        $controllerInfo = $this->buildControllerInfo($serviceInfo, $version);

        // 如果控制器不存在，则创建
        if (!file_exists($controllerInfo['path'])) {
            $this->components->warn(sprintf(
                "正在创建 Controller [Http/Controllers/%s%s%s]...",
                $version,
                $controllerInfo['directory'],
                $controllerInfo['name']
            ));

            try {
                $this->call('make:controller', [
                    'name' => $controllerInfo['fullName']
                ]);
            } catch (\Exception $e) {
                $this->components->error("创建 Controller 失败：" . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * 格式化版本路径
     * @param string|null $version
     * @return string
     */
    private function formatVersionPath($version)
    {
        if (empty($version)) {
            return '';
        }

        $version = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $version);
        return str_ends_with($version, DIRECTORY_SEPARATOR)
            ? $version
            : $version . DIRECTORY_SEPARATOR;
    }

    /**
     * 构建控制器信息
     * @param array $serviceInfo
     * @param string $version
     * @return array
     */
    private function buildControllerInfo($serviceInfo, $version)
    {
        $name = str_replace('Service', 'Controller', $serviceInfo['serviceName']);
        $directory = $serviceInfo['path'];
        $fullName = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $version . $directory . $name);

        return [
            'name' => $name,
            'directory' => $directory,
            'path' => app_path('Http/Controllers/' . $version . $directory . $name . '.php'),
            'fullName' => $fullName
        ];
    }

    /**
     * 执行命令
     */
    public function handle()
    {
        $name = $this->qualifyClass($this->getNameInput());
        $path = $this->getPath($name);

        // 检查文件是否已存在
        if ($this->alreadyExists($this->getNameInput())) {
            $this->components->warn(sprintf('%s 已经存在，将被覆盖。', $this->type));
            if (!$this->components->confirm('是否要覆盖现有服务？')) {
                return 1;
            }
        }

        // 生成文件
        $this->makeDirectory($path);
        $this->files->put($path, $this->sortImports($this->buildClass($name)));

        // 输出成功信息和文件路径
        $this->components->info(sprintf('%s 已经成功创建。', $this->type));
        $this->components->info(sprintf('文件路径: %s', $path));
    }
}
