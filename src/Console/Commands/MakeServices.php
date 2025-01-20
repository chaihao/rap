<?php

namespace Chaihao\Rap\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeServices extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rap:services [-m|--create-model] [-c|--create-controller]';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '创建自定义Services';

    /**
     * 生成器命令类型
     * 用于在命令执行过程中标识正在创建的资源类型
     * @var string
     */
    protected $type = 'Service';

    /**
     * 获取存根文件的路径
     * 该文件包含服务类的基本模板
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/Stubs/service.stub';
    }

    /**
     * 指定生成的服务类默认所在的命名空间
     * @param string $rootNamespace 根命名空间
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Services';
    }
    /**
     * 获取命令行选项
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['create-model', 'm', InputOption::VALUE_OPTIONAL, '是否创建模型', true],
            ['create-controller', 'c', InputOption::VALUE_OPTIONAL, '是否创建控制器', true],
        ];
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
        return $this->replaceStubModel($stub, $serviceInfo, $name);
    }

    /**
     * 解析服务类名和路径
     * @param string $name 完整的类名（包含命名空间）
     * @return array|false 返回包含服务信息的数组或失败时返回 false
     *                    返回数组包含：
     *                    - serviceName: 服务类名
     *                    - path: 服务类路径
     *                    - modelName: 对应的模型名称
     */
    private function parseServiceName($name)
    {
        $serviceNameArray = explode('\\', $name);
        $serviceName = end($serviceNameArray);

        // 验证服务名称格式并确保以 'Service' 结尾
        $serviceName = str_ends_with($serviceName, 'Service') ? $serviceName : $serviceName . 'Service';

        $path = $this->getServicePath($serviceNameArray);
        $modelName = str_replace('Service', '', $serviceName);

        return compact('serviceName', 'path', 'modelName');
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
     * 根据配置决定是否自动创建对应的 Model 和 Controller 文件
     * @param array $serviceInfo 服务信息数组
     * @return bool 处理成功返回 true，失败返回 false
     */
    private function handleRelatedFiles($serviceInfo)
    {
        try {
            // 使用数组来存储需要处理的文件
            $filesToHandle = [
                'model' => $this->option('create-model'),
                'controller' => $this->option('create-controller')
            ];

            foreach ($filesToHandle as $type => $shouldHandle) {
                if ($shouldHandle) {
                    $method = 'handle' . ucfirst($type) . 'File';
                    $this->$method($serviceInfo);
                }
            }
            return true;
        } catch (\Exception $e) {
            $this->components->error("处理相关文件时发生错误：" . $e->getMessage());
            return false;
        }
    }

    /**
     * 替换模板内容
     * 将模板中的占位符替换为实际的命名空间和类名
     * @param string $stub 模板内容
     * @param array $serviceInfo 服务信息数组
     * @param string $name 完整的类名
     * @return string 替换后的内容
     */
    private function replaceStubModel($stub, $serviceInfo, $name)
    {
        // 实例化 Filesystem
        $filesystem = new Filesystem();
        $files = $filesystem->allFiles(base_path() . '/app/Models');

        // 检测目录下是否存在 $serviceInfo['modelName'] 文件名的文件
        $modelFileName = $serviceInfo['modelName'] . '.php';
        // 使用 array_filter 过滤出文件名匹配的文件
        $matchedFiles = array_filter($files, fn($file) => $file->getFilename() === $modelFileName);
        // 获取匹配文件的相对路径
        $path = !empty($matchedFiles) ? str_replace(base_path(), '', reset($matchedFiles)->getRealPath()) : '';
        // 将路径中的斜杠替换为反斜杠，并去除 app 和 .php 后缀
        $path = str_replace(['/', '\\app', '.php'], ['\\', 'App', ''], $path);

        // 替换模板内容
        $stub = str_replace('USED_DUMMY_MODEL', $path ? 'use ' . $path . ';' : '', $stub);
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
        // if (!file_exists($modelPath)) {
        $displayPath = str_replace('\\', '/', sprintf(
            "Models/%s%s",
            $serviceInfo['path'],
            $serviceInfo['modelName']
        ));

        $this->components->warn(sprintf("正在创建 Model [%s]...", $displayPath));

        // 处理模型名称，确保正确的目录分隔符
        $modelName = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $serviceInfo['path']);
        $modelName = trim($modelName, DIRECTORY_SEPARATOR);
        $modelName = $modelName ? $modelName . DIRECTORY_SEPARATOR . $serviceInfo['modelName'] : $serviceInfo['modelName'];

        try {
            $this->call('rap:model', [
                'name' => $modelName,
            ]);
        } catch (\Exception $e) {
            $this->components->error("创建 Model 失败：" . $e->getMessage());
            return false;
        }
        // }
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
        $version = $this->formatVersionPath(config('rap.controller.version', ''));

        // 构建控制器信息
        $controllerInfo = $this->buildControllerInfo($serviceInfo, $version);

        // 如果控制器不存在，则创建
        // if (!file_exists($controllerInfo['path'])) {
        $displayPath = str_replace('\\', '/', sprintf(
            "Http/Controllers/%s%s%s",
            $version,
            $controllerInfo['directory'],
            $controllerInfo['name']
        ));

        $this->components->warn(sprintf("正在创建 Controller [%s]...", $displayPath));

        try {
            $this->call('rap:controller', [
                'name' => $controllerInfo['fullName']
            ]);
        } catch (\Exception $e) {
            $this->components->error("创建 Controller 失败：" . $e->getMessage());
            return false;
        }
        // }

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
        try {
            $name = $this->qualifyClass($this->getNameInput());
            // 验证服务名称格式
            $name = str_ends_with($name, 'Service') ? $name : $name . 'Service';
            // 获取服务类路径
            $path = $this->getPath($name);
            // 如果文件已存在,提示是否覆盖
            if ($this->alreadyExists($name) && !$this->confirmOverwrite()) {
                return 1;
            }

            $this->makeDirectory($path);
            $this->files->put($path, $this->sortImports($this->buildClass($name)));

            $this->showSuccessMessage($path);
            return 0;
        } catch (\Exception $e) {
            $this->components->error("命令执行失败：" . $e->getMessage());
            return 1;
        }
    }

    /**
     * 确认是否覆盖现有文件
     * @return bool
     */
    private function confirmOverwrite(): bool
    {
        $this->components->warn(sprintf('%s 已经存在，将被覆盖。', $this->type));
        return $this->components->confirm('是否要覆盖现有服务？');
    }

    /**
     * 显示成功消息
     * @param string $path
     */
    private function showSuccessMessage(string $path): void
    {
        $this->components->info(sprintf('%s 已经成功创建。', $this->type));
        $this->components->info(sprintf('文件路径: %s', $path));
    }
}
