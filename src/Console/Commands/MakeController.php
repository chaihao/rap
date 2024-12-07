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


    protected $signature = 'make:controller {name}';

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
        $controllerName = $this->getControllerName($name);

        // 使用数组存储替换规则，提高代码可维护性
        $replacements = [
            'TABLE_NAME' => $this->convertToSnakeCase($controllerName),
            'DummyModel' => $controllerName,
            'DummyService' => $controllerName . 'Service'
        ];

        // 处理模型和服务的命名空间
        $modelName = str_replace('Controller', '', $name);
        $serviceName = str_replace('Controller', 'Service', $name);

        // 获取命名空间并设置替换规则
        $replacements['USED_DUMMY_MODEL'] = $this->getNamespaceReplacement($modelName);
        $replacements['USED_DUMMY_SERVICE'] = $this->getNamespaceReplacement($serviceName);

        // 批量执行替换
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );
    }

    /**
     * 将驼峰命名转换为下划线命名
     */
    private function convertToSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
    }

    /**
     * 获取命名空间替换内容
     */
    private function getNamespaceReplacement(string $name): string
    {
        $namespace = $this->findClassNamespace($name);
        return $namespace ? 'use ' . $namespace . ';' : '';
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
    private function getControllerName($name): string
    {
        // 处理控制器名称，确保正确的目录分隔符
        $controllerName = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $name);
        $exName = explode(DIRECTORY_SEPARATOR, $controllerName);
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


    /**
     * 检测类名并返回完整命名空间
     * @param string $className 类名
     * @param array $namespaces 要搜索的命名空间列表
     * @return string|null 返回完整的类名（包含命名空间）或 null
     */
    private function findClassNamespace($className, array $namespaces = []): ?string
    {
        // 标准化类名，统一使用反斜杠
        $className = $this->normalizeClassName($className);

        // 处理版本号
        $version = config('rap.namespace.controller.version');
        if ($version) {
            // 确保版本号格式统一（添加斜线）
            $normalizedVersion = trim($version, '\\/') . '\\';
            if (strpos($className, $normalizedVersion) !== false) {
                $className = str_replace($normalizedVersion, '', $className);
            }
        }

        // 如果提供的是完整类名且类存在，直接返回
        if (class_exists($className)) {
            return $className;
        }

        // 合并并搜索命名空间
        $searchNamespaces = array_merge([
            'App\\Models\\',
            'App\\Services\\',
            'App\\Http\\Controllers\\',
        ], $namespaces);

        foreach ($searchNamespaces as $namespace) {
            $fullClassName = $namespace . $className;
            if ($this->isValidClass($fullClassName, $className)) {
                return $fullClassName;
            }
        }

        return null;
    }

    /**
     * 标准化类名
     */
    private function normalizeClassName(string $className): string
    {
        return str_replace(['\\', '/'], '\\', $className);
    }

    /**
     * 检查是否为有效的类
     */
    private function isValidClass(string $fullClassName, string $className): bool
    {
        return class_exists($fullClassName) ||
            (str_contains($className, 'Service') && str_contains($fullClassName, '\\Services\\'));
    }
}
