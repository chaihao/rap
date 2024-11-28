<?php

namespace Chaihao\Rap\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\InputOption;

/**
 * 自定义Model生成命令类
 */
class MakeModel extends GeneratorCommand
{
    protected $name = 'make:model';
    protected $description = '创建自定义Model';
    protected $type = 'Model';

    /**
     * 数据类型字典
     */
    public static $dictionary = [
        'string' => ['char', 'text'],
        'int' => ['int', 'numeric'],
        'float' => ['double', 'float', 'decimal']
    ];

    /**
     * 时间戳字段
     */
    const TIMESTAMP_FIELDS = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * 获取stub文件路径
     */
    protected function getStub()
    {
        return __DIR__ . '/Stubs/model.stub';
    }

    /**
     * 获取默认命名空间
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Models';
    }

    /**
     * 获取命令选项
     */
    protected function getOptions()
    {
        return [
            ['table', null, InputOption::VALUE_OPTIONAL, 'The name of the table']
        ];
    }

    /**
     * 替换类名并填充模板
     */
    protected function replaceClass($stub, $name)
    {
        $tableName = $this->option('table') ?: $this->getTableName();
        // 获取过滤后的字段列表（不包含时间戳字段）
        $list = $tableName ? $this->organizeData($tableName) : [];
        // 获取所有字段，包括时间戳字段
        $allFields = $tableName ? $this->getAllFields($tableName) : [];

        $stub = str_replace('TABLE', $tableName ? str_replace(env('DB_TABLE_PREFIX', ''), '', $tableName) : '', $stub);

        $stub = $this->replaceRules($stub, $list, $tableName);

        // 检查是否存在 deleted_at 字段（使用包含所有字段的列表）
        $hasSoftDeletes = in_array('deleted_at', $allFields);

        if ($hasSoftDeletes) {
            $stub = $this->addSoftDeletes($stub);
        } else {
            // 如果不存在 deleted_at 字段，移除相关的占位符
            $stub = str_replace('USE_SOFT_DELETES_STATEMENT', '', $stub);
            $stub = str_replace('USE_SOFT_DELETES', '', $stub);
        }

        return parent::replaceClass($stub, $name);
    }

    /**
     * 获取所有字段
     * 
     * 此方法返回表的所有字段，包括时间戳字段
     * 主要用于检测 deleted_at 字段的存在
     */
    protected function getAllFields($tableName)
    {
        try {
            $info = DB::select(sprintf("DESC %s;", $tableName));
            return array_column($info, 'Field');
        } catch (\Throwable $th) {
            return [];
        }
    }

    /**
     * 组织表数据
     * 
     * 此方法返回过滤后的字段列表，不包含时间戳字段
     * 用于生成模型的其他部分，如规则、填充等
     */
    public function organizeData($tableName)
    {
        try {
            $info = collect(DB::select(sprintf("DESC %s;", $tableName)))->toArray();
            return array_filter($info, function ($item) {
                return !in_array($item->Field, self::TIMESTAMP_FIELDS);
            });
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * 添加软删除
     */
    protected function addSoftDeletes($stub)
    {
        $useStatement = 'use Illuminate\Database\Eloquent\SoftDeletes;';
        $traitUse = 'use SoftDeletes;';

        $stub = str_replace('USE_SOFT_DELETES_STATEMENT', $useStatement, $stub);
        $stub = str_replace('USE_SOFT_DELETES', $traitUse, $stub);
        return $stub;
    }


    /**
     * 替换模型名称
     */
    protected function replaceModelName($stub)
    {
        $name = str_replace('Controller', '', $this->getNameInput());
        return str_replace('MODEL', $name, $stub);
    }

    /**
     * 获取表名
     */
    protected function getTableName()
    {
        $name = str_replace('Model', '', $this->getNameInput());
        $name = $this->convertToSnakeCase($name);
        $databaseName = env('DB_DATABASE');

        if ($this->tableExists($databaseName, $name)) {
            return $name;
        }

        $prefixTableName = env('DB_TABLE_PREFIX', '') .  $name;
        if ($this->tableExists($databaseName, $prefixTableName)) {
            return $prefixTableName;
        }

        return false;
    }

    /**
     * 检查表是否存在
     */
    protected function tableExists($databaseName, $tableName)
    {
        return DB::select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [$databaseName, $tableName]) ? true : false;
    }

    /**
     * 将驼峰命名转换为蛇形命名
     */
    protected function convertToSnakeCase($name)
    {
        if (strripos($name, '/') !== false) {
            $name = substr($name, strripos($name, '/') + 1);
        }
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }


    /**
     * 替换规则
     */
    protected function replaceRules($stub, $list, $tableName)
    {
        try {
            $rules = '';
            $fillable = '';
            $casts = '';
            $scenarios = '';
            $scenarioFields = [];

            foreach ($list as $key => $item) {
                $fieldRules = $this->getFieldRules($item, $tableName);
                if ($fieldRules && $item->Field != 'id') {
                    $rules .= sprintf('"%s" => "%s",%s', $item->Field, $fieldRules, PHP_EOL);
                }
                $fillable .= sprintf('"%s",', $item->Field);
                if ($item->Type == 'json') {
                    $casts .= sprintf('"%s" => "%s",%s', $item->Field, 'array', PHP_EOL);
                }

                // 添加字段到场景
                if ($item->Field != 'id') {
                    $scenarioFields['add'][] = $item->Field;
                    $scenarioFields['edit'][] = $item->Field;
                }
            }

            // 生成场景配置
            $scenarios .= "'add' => ['" . implode("', '", $scenarioFields['add']) . "']," . PHP_EOL;
            $scenarios .= "'edit' => ['id', '" . implode("', '", $scenarioFields['edit']) . "']," . PHP_EOL;
            $scenarios .= "'delete' => ['id']," . PHP_EOL;
            $scenarios .= "'detail' => ['id']," . PHP_EOL;
            // 字段中存在status字段，则生成status场景   
            if (in_array('status', $scenarioFields['add']) || in_array('status', $scenarioFields['edit'])) {
                $scenarios .= "'status' => ['id', 'status']," . PHP_EOL;
            }

            $stub = str_replace('FILLABLE', rtrim($fillable, ','), $stub);
            $stub = str_replace('CASTS', rtrim($casts, PHP_EOL), $stub);
            $stub = str_replace('RULES', rtrim($rules, PHP_EOL), $stub);
            $stub = str_replace('SCENARIOS', rtrim($scenarios, PHP_EOL), $stub);
            return $stub;
        } catch (\Exception $e) {
            return str_replace(['RULES', 'SCENARIOS'], '', $stub);
        }
    }

    /**
     * 获取字段规则
     */
    protected function getFieldRules($item, $tableName)
    {
        $rules = [];
        $type = strtolower($item->Type);

        $item->Null === 'NO' && $rules[] = 'required';
        preg_match('/(\w+)(\((\d+)(?:,(\d+))?\))?/', $type, $matches);

        $baseType = $matches[1] ?? '';
        $length = $matches[3] ?? null;

        switch ($baseType) {
            case 'int':
            case 'bigint':
            case 'tinyint':
                $rules[] = 'integer';
                break;
            case 'decimal':
            case 'float':
            case 'double':
                $rules[] = 'numeric';
                $length && $rules[] = "regex:/^\d{1,$length}(\.\d{1," . ($matches[4] ?? 2) . "})?$/";
                break;
            case 'char':
            case 'varchar':
                $length && $rules[] = "max:$length";
                break;
            case 'enum':
                preg_match_all("/'([^']+)'/", $type, $enumMatches);
                $rules[] = 'in:' . implode(',', $enumMatches[1]);
                break;
        }

        $item->Key === 'UNI' && $rules[] = 'unique:' . ltrim($tableName, env('DB_TABLE_PREFIX', '')) . ',' . $item->Field;

        return implode('|', $rules);
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
    }
}
