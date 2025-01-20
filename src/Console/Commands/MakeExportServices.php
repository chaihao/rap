<?php

namespace Chaihao\Rap\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;

class MakeExportServices extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rap:export {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '创建自定义导出服务类';

    /**
     * 生成器命令类型
     * 用于在命令执行过程中标识正在创建的资源类型
     * @var string
     */
    protected $type = 'ExportService';

    /**
     * 获取存根文件的路径
     * 该文件包含服务类的基本模板
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/Stubs/export.stub';
    }

    /**
     * 指定生成的服务类默认所在的命名空间
     * @param string $rootNamespace 根命名空间
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Services\Export';
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

        // 验证服务名称格式并确保以 'ExportService' 结尾
        $serviceName = $this->getModelName($serviceName);
        $path = $this->getServicePath($serviceNameArray);
        $modelName = str_replace('ExportService', '', $serviceName);

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
        $path = '';

        // 使用 array_filter 优化文件查找
        $matchedFiles = array_filter($files, function ($file) use ($modelFileName) {
            return $file->getFilename() === $modelFileName;
        });
        if (!empty($matchedFiles)) {
            $path = str_replace(base_path(), '', reset($matchedFiles)->getRealPath()); // 返回相对路径
            $path = str_replace(['/', '\\app', '.php'], ['\\', 'App', ''], $path);
        }
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
     * 获取模型名称
     * @param string $name 完整的类名
     * @return string 模型名称
     */
    public function getModelName($name)
    {
        $nameParts = explode('\\', $name);
        $endName = end($nameParts);
        // 优化：直接使用正则表达式替换
        $endName = preg_replace('/(Export|Service|ExportService)$/', '', $endName) . 'ExportService';
        $nameParts[count($nameParts) - 1] = $endName;
        return implode('\\', $nameParts);
    }

    /**
     * 执行命令
     */
    public function handle()
    {
        try {
            // 获取Services类名
            $name = $this->qualifyClass($this->getNameInput());
            // 验证服务名称格式
            $name = $this->getModelName($name);
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
