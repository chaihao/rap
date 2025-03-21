<?php

namespace Chaihao\Rap\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * 自定义Model生成命令类
 */
class MakeModel extends GeneratorCommand
{
    protected $name = 'rap:model {name} {table?}';
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
     * 软删除字段
     */
    const SOFT_DELETE_FIELDS = ['deleted_at'];

    /**
     * 过滤字段
     */
    const FILTER_FIELDS = ['deleted_at'];
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
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the model'],
            ['table', InputArgument::OPTIONAL, 'The name of the table']
        ];
    }

    /**
     * 替换类名并填充模板
     */
    protected function replaceClass($stub, $name)
    {
        $tableName = $this->argument('table') ?: $this->getTableName();
        // 获取过滤后的字段列表（不包含时间戳字段）
        $list = $tableName ? $this->organizeData($tableName) : [];

        // 移除表前缀
        $stub = str_replace('TABLE', $tableName ? str_replace(env('DB_PREFIX', ''), '', $tableName) : '', $stub);

        // 替换验证规则、场景等信息
        $stub = $this->replaceRules($stub, $list, $tableName);

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
     * 组织表数据 - 优化异常处理和代码结构
     */
    public function organizeData($tableName)
    {
        try {
            // 首先尝试使用 INFORMATION_SCHEMA
            return DB::table('INFORMATION_SCHEMA.COLUMNS')
                ->where('TABLE_SCHEMA', env('DB_DATABASE'))
                ->where('TABLE_NAME', $tableName)
                ->get()
                // ->reject(function ($column) {
                //     // 跳过时间戳字段
                //     return in_array($column->COLUMN_NAME, self::TIMESTAMP_FIELDS);
                // })
                ->map(function ($column) {
                    return (object)[
                        'Field' => $column->COLUMN_NAME,
                        'Type' => $column->COLUMN_TYPE,
                        'Null' => $column->IS_NULLABLE,
                        'Key' => $column->COLUMN_KEY === 'UNI' ? 'UNI' : '',
                        'Default' => $column->COLUMN_DEFAULT,
                        'Comment' => $column->COLUMN_COMMENT ?? '',
                    ];
                })
                ->all();
        } catch (\Throwable $th) {
            // 如果失败，使用 SHOW FULL COLUMNS 替代 DESCRIBE
            try {
                $columns = DB::select("SHOW FULL COLUMNS FROM `{$tableName}`");
                return collect($columns)->map(function ($column) {
                    return (object)[
                        'Field' => $column->Field,
                        'Type' => $column->Type,
                        'Null' => $column->Null,
                        'Key' => $column->Key === 'UNI' ? 'UNI' : '',
                        'Default' => $column->Default,
                        'Comment' => $column->Comment ?? '',
                    ];
                })->all();
            } catch (\Throwable $th2) {
                $this->error("无法获取表 {$tableName} 的结构信息：" . $th2->getMessage());
                return [];
            }
        }
    }

    /**
     * 添加软删除
     * @param mixed $stub 模板
     * @param mixed $hasSoftDelete 是否存在软删除字段
     * @return array|string
     */
    protected function addSoftDeletes($stub, $hasSoftDelete)
    {
        if ($hasSoftDelete) {
            $useStatement = 'use Illuminate\Database\Eloquent\SoftDeletes;';
            $traitUse = 'use SoftDeletes;';
            $stub = str_replace('USE_SOFT_DELETES_STATEMENT', $useStatement, $stub);
            $stub = str_replace('USE_SOFT_DELETES', $traitUse, $stub);
        } else {
            // 如果不存在软删除字段，移除相关的占位符
            $stub = str_replace('USE_SOFT_DELETES_STATEMENT', '', $stub);
            // 移除软删除trait
            $stub = str_replace('USE_SOFT_DELETES', '', $stub);
        }
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

        $prefixTableName = env('DB_PREFIX', '') .  $name;
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
            $rules = [];
            $fillable = [];
            $casts = [];
            $getValidatorAttributes = [];
            $scenarioFields = [
                'add' => [],
                'edit' => []
            ];

            // 检查是否存在软删除字段
            $hasSoftDelete = false;

            foreach ($list as $item) {
                // 添加可填充字段
                $fillable[] = $item->Field;

                // 检查是否有软删除字段
                if (in_array($item->Field, self::SOFT_DELETE_FIELDS)) {
                    $hasSoftDelete = true;
                }

                if (!in_array($item->Field, self::FILTER_FIELDS)) {
                    // 添加验证器属性
                    $getValidatorAttributes[$item->Field] = $item->Comment ?: $this->getValidatorAttributes($item->Field);
                }

                // 跳过id字段 和 时间戳字段
                if ($item->Field === 'id' || in_array($item->Field, self::TIMESTAMP_FIELDS)) {
                    continue;
                }

                // 获取字段规则和类型转换
                [$fieldRules, $cast] = $this->getFieldRules($item, $tableName);

                // 添加验证规则
                if ($fieldRules) {
                    $rules[$item->Field] = $fieldRules;
                }
                // 添加类型转换
                if ($cast) {
                    $casts[$item->Field] = $cast;
                }

                // 添加到场景
                $scenarioFields['add'][] = $item->Field;
                $scenarioFields['edit'][] = $item->Field;
            }

            // 生成场景配置
            $scenarios = [
                'add' => $scenarioFields['add'],
                'edit' => array_merge(['id'], $scenarioFields['edit']),
                'delete' => ['id'],
                'detail' => ['id']
            ];

            // 添加状态场景
            if (in_array('status', $fillable)) {
                $scenarios['status'] = ['id', 'status'];
            }

            // 添加可填充字段
            $stub = str_replace('FILLABLE', '"' . implode('","', $fillable) . '"', $stub);
            // 添加类型转换
            $stub = str_replace('CASTS', $this->arrayToString($casts), $stub);
            // 添加验证规则
            $stub = str_replace('RULES', $this->arrayToString($rules), $stub);
            // 添加场景
            $stub = str_replace('SCENARIOS', $this->scenariosToString($scenarios), $stub);
            // 添加验证器自定义属性
            $stub = str_replace('SET_VALIDATOR_ATTRIBUTES', $this->arrayToString($getValidatorAttributes), $stub);
            // 添加软删除
            $stub = $this->addSoftDeletes($stub, $hasSoftDelete);
            return $stub;
        } catch (\Exception $e) {
            $this->error("生成规则时出错：" . $e->getMessage());
            return str_replace(['RULES', 'SCENARIOS', 'CASTS', 'FILLABLE', 'SET_VALIDATOR_ATTRIBUTES', 'USE_SOFT_DELETES_STATEMENT', 'USE_SOFT_DELETES'], '', $stub);
        }
    }

    /**
     * 获取验证器自定义属性
     */
    public function getValidatorAttributes($field)
    {
        $explode = explode('_', $field);
        return implode(' ', array_map('ucfirst', $explode));
    }

    /**
     * 将数组转换为字符串格式
     */
    private function arrayToString(array $array): string
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[] = "\"$key\" => \"$value\"";
        }
        return implode(',' . PHP_EOL, $result);
    }

    /**
     * 将场景数组转换为字符串格式
     */
    private function scenariosToString(array $scenarios): string
    {
        $result = [];
        foreach ($scenarios as $key => $fields) {
            $result[] = "'{$key}' => ['" . implode("', '", $fields) . "']";
        }
        return implode(',' . PHP_EOL, $result);
    }

    /**
     * 获取字段规则 - 优化规则生成逻辑
     */
    protected function getFieldRules($item, $tableName)
    {
        $rules = [];
        $casts = '';

        // 解析字段类型信息（类型、长度、小数位）
        $typeInfo = $this->parseFieldType($item->Type);
        $baseType = $typeInfo['type'];
        $length = $typeInfo['length'];
        $decimals = $typeInfo['decimals'];

        // 添加必填规则 - 只有当字段不允许为空且没有默认值时才添加
        if ($item->Null === 'NO' && !isset($item->Default)) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // 根据字段名称添加特殊规则
        $specialRules = $this->getSpecialFieldRules($item->Field);
        if ($specialRules) {
            $rules = array_merge($rules, $specialRules);
        }

        // 枚举类型特殊处理
        if ($baseType === 'enum') {
            // 修改正则表达式以正确匹配枚举值
            preg_match("/enum\((.*?)\)/i", $item->Type, $matches);

            if (!empty($matches[1])) {
                // 去除引号并分割枚举值
                $enumValues = explode(',', str_replace("'", '', $matches[1]));
                $rules[] = 'in:' . implode(',', $enumValues);
                $casts = 'string';
            }
        } else {
            // 根据字段类型设置对应的验证规则和类型转换
            $typeRules = $this->getTypeBasedRules($baseType, $length, $decimals);
            $rules = array_merge($rules, $typeRules['rules']);
            $casts = $typeRules['cast'];
        }

        // 添加唯一字段验证规则
        if ($item->Key === 'UNI') {
            $tableName = ltrim($tableName, env('DB_PREFIX', ''));
            // 检查是否存在软删除字段
            $hasSoftDelete = collect($this->getAllFields($tableName))->contains('deleted_at');
            $uniqueRule = "unique:{$tableName},{$item->Field},NULL,id";
            if ($hasSoftDelete) {
                $uniqueRule .= ",deleted_at,NULL";
            }
            $rules[] = $uniqueRule;
        }

        return [implode('|', array_filter($rules)), $casts];
    }

    /**
     * 解析字段类型
     */
    private function parseFieldType($type)
    {
        $matches = [];
        preg_match('/(\w+)(?:\((\d+)(?:,(\d+))?\))?/', strtolower($type), $matches);

        return [
            'type' => $matches[1] ?? '',
            'length' => $matches[2] ?? null,
            'decimals' => $matches[3] ?? null
        ];
    }

    /**
     * 获取基于类型的规则
     */
    private function getTypeBasedRules($baseType, $length = null, $decimals = null)
    {
        $rules = [];
        $cast = '';

        // 定义数据类型映射关系
        $typeMap = [
            'integer' => ['int', 'bigint', 'tinyint'],        // 整数类型
            'numeric' => ['decimal', 'float', 'double'],      // 数值类型
            'string' => ['char', 'varchar', 'text', 'mediumtext', 'longtext'],  // 字符串类型
            'datetime' => ['timestamp', 'datetime'],          // 日期时间类型
            'array' => ['json'],                             // JSON类型
            'enum' => ['enum']                               // 枚举类型
        ];

        // 根据字段类型设置相应的验证规则
        foreach ($typeMap as $castType => $types) {
            if (in_array($baseType, $types)) {
                $cast = $castType === 'numeric' ? 'double' : $castType;
                $rules[] = $castType === 'datetime' ? 'date' : ($castType === 'numeric' ? 'numeric' : $castType);

                // 字符串类型添加长度限制
                if ($length && in_array($baseType, ['char', 'varchar'])) {
                    $rules[] = "max:{$length}";
                }

                // 数值类型添加格式验证
                if ($castType === 'numeric' && $length && $decimals) {
                    $rules[] = "regex:/^\d{1,{$length}}(\.\d{1,{$decimals}})?$/";
                }
                break;
            }
        }

        return [
            'rules' => $rules,
            'cast' => $cast
        ];
    }

    /**
     * 获取特殊字段的验证规则
     */
    private function getSpecialFieldRules($fieldName)
    {
        $specialRules = [
            'email' => ['email'],
            'url' => ['url'],
            'ip' => ['ip'],
            'phone' => ['regex:/^1[3-9]\d{9}$/'],
            'password' => ['min:6'],
            'age' => ['integer', 'min:0', 'max:150'],
            'amount' => ['numeric', 'min:0'],
            'sort' => ['integer', 'min:0'],
        ];

        // 使用模糊匹配检查字段名是否包含特殊关键词
        foreach ($specialRules as $key => $rules) {
            // if (stripos($fieldName, $key) !== false) {
            //     return $rules;
            // }
            if ($fieldName == $key) {
                return $rules;
            }
        }

        return [];
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
