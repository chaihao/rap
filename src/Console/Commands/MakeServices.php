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

        // $stub = $this->replaceModelName($stub); //替换自定义内容


        $modelName = explode('\\', $name);
        $modelName = end($modelName);
        $modelName = str_replace('Service', 'Model', $modelName);
        $stub = str_replace('DummyModel', $modelName, $stub);
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
}
