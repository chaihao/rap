<?php

namespace Chaihao\Rap\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Console\GeneratorCommand;

class MakeController extends GeneratorCommand
{

    /**
     * 命令签名
     * @var string
     */
    protected $signature = 'rap:controller {name}'; // 统一命令签名

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

    /**
     * 获取控制器模板文件的路径
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/Stubs/controller.stub';
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
        return parent::replaceClass($stub, $name);
    }

    /**
     * replaceModelName 方法
     * 负责替换模板中的所有占位符
     * @param string $stub
     * @return string
     */
    protected function replaceModelName($stub)
    {
        $name = $this->getNameInput();
        $controllerName = $this->getControllerName($name);

        // 定义替换规则映射
        $replacements = [
            'TABLE_NAME' => $this->convertToSnakeCase($controllerName),    // 表名（蛇形命名）
            'DummyModel' => $controllerName,                               // 模型名
            'DummyService' => $controllerName . 'Service'                  // 服务名
        ];
        $modelName = $replacements['DummyModel'];
        $serviceName = $replacements['DummyService'];

        // 获取命名空间并设置替换规则
        $replacements['USED_DUMMY_MODEL'] = $this->getNamespaceReplacement($modelName, 'Models');
        $replacements['USED_DUMMY_SERVICE'] = $this->getNamespaceReplacement($serviceName, 'Services');

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
    private function getNamespaceReplacement(string $name, string $type): string
    {
        $namespace = $this->findClassNamespace($name, $type);
        return $namespace ? 'use ' . $namespace . ';' : '';
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
     * handle 方法
     * 控制器生成的主要逻辑处理
     * 包括验证、创建和错误处理
     * @return int
     */
    public function handle()
    {
        $name = $this->qualifyClass($this->getNameInput());
        $path = $this->getPath($name);

        try {
            // 检查控制器是否已存在
            if ($this->alreadyExists($this->getNameInput())) {
                if (!$this->handleExistingController()) {
                    return 1;
                }
            }

            $this->createController($path, $name);
            $this->showSuccessMessage($path);

            return 0;
        } catch (\Exception $e) {
            $this->components->error("创建控制器失败：" . $e->getMessage());
            return 1;
        }
    }

    /**
     * 处理已存在的控制器
     * @return bool
     */
    private function handleExistingController(): bool
    {
        $this->components->warn(sprintf('%s 已经存在', $this->type));
        return $this->components->confirm('是否要覆盖现有控制器？');
    }

    /**
     * 创建控制器
     * @param string $path
     * @param string $name
     */
    private function createController(string $path, string $name): void
    {
        $this->makeDirectory($path);
        $this->files->put($path, $this->sortImports($this->buildClass($name)));
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

    /**
     * findClassNamespace 方法
     * 根据类名查找对应的命名空间
     * 支持 Model 和 Service 的自动查找
     * @param string $className
     * @param array $type
     * @return string|null
     */
    private function findClassNamespace($className, string $type = 'Models'): string
    {
        $className = $this->normalizeClassName($className);

        $version = config('rap.controller.version', '');
        if ($version) {
            $className = $this->removeVersionFromClassName($className, $version);
        }
        // 实例化 Filesystem
        $filesystem = new Filesystem();
        $files = $filesystem->allFiles(base_path() . '/app/' . $type);
        // 检测目录下是否存在 $serviceInfo['modelName'] 文件名的文件
        $modelFileName = $className . '.php';
        // 使用 array_filter 优化文件查找
        $matchedFiles = array_filter($files, fn($file) => $file->getFilename() === $modelFileName);

        if ($matchedFiles) {
            $path = str_replace(base_path(), '', reset($matchedFiles)->getRealPath()); // 返回相对路径
            return str_replace(['/', '\\app', '.php'], ['\\', 'App', ''], $path);
        }
        return '';
    }
    /**
     * 从类名中移除版本号
     */
    private function removeVersionFromClassName(string $className, string $version): string
    {
        $normalizedVersion = trim($version, '\\/') . '\\';
        return str_replace($normalizedVersion, '', $className);
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
