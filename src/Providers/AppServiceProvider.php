<?php

/**
 * 应用服务提供者
 *
 * @package Chaihao\Rap\Providers
 */

namespace Chaihao\Rap\Providers;

use Chaihao\Rap\Exception\ApiException;
use DateTime;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 注册配置文件
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/logging.php',
            'rap.logging'
        );


        // 注册语言文件路径
        $this->loadTranslationsFrom(
            __DIR__ . '/../resources/lang',
            'rap'
        );

        // 发布语言文件
        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang'),
        ], 'rap-lang');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 设置默认语言
        $locale = Config::get('app.locale', 'zh_CN');
        App::setLocale($locale);
        // 检查是否启用了SQL日志记录
        if (Config::get('rap.logging.enable_sql_logging')) {
            $this->setupSqlLogging();
        }
    }



    /**
     * 设置SQL日志记录
     * 
     * 此方法负责设置SQL日志的存储路径，并在配置允许的情况下启用SQL绑定参数替换
     */
    private function setupSqlLogging(): void
    {
        try {
            // 定义SQL日志存储路径
            $sqlLogPath = storage_path('logs/sql');

            // 如果日志目录不存在，则创建它
            if (!is_dir($sqlLogPath)) {
                if (!mkdir($sqlLogPath, 0755, true)) {
                    throw new \RuntimeException('无法创建SQL日志目录');
                }
            }
            // 确保日志通道已配置
            $this->replaceBinding();
        } catch (Throwable $e) {
            Log::error('设置SQL日志失败: ' . $e->getMessage());
        }
    }

    /**
     * 替换SQL绑定参数并记录完整SQL语句
     * 
     * 此方法监听数据库查询，处理绑定参数，并记录完整的SQL语句及执行时间到日志
     */
    public function replaceBinding()
    {
        DB::listen(function ($query) {
            $sql = $query->sql;
            $bindings = $query->bindings;
            $time = $query->time; // 获取查询执行时间（毫秒）

            // 处理不同类型的绑定参数
            foreach ($bindings as $i => $binding) {
                if ($binding instanceof DateTime) {
                    // 将DateTime对象转换为格式化的字符串
                    $bindings[$i] = $binding->format('\'Y-m-d H:i:s\'');
                } elseif (is_string($binding)) {
                    // 转义字符串并添加引号
                    $bindings[$i] = "'" . addslashes($binding) . "'";
                } elseif (is_bool($binding)) {
                    // 将布尔值转换为0或1
                    $bindings[$i] = $binding ? '1' : '0';
                } elseif (is_null($binding)) {
                    // 将null转换为NULL字符串
                    $bindings[$i] = 'NULL';
                }
            }

            // 准备SQL语句以插入绑定参数
            $sql = str_replace(['%', '?'], ['%%', '%s'], $sql);
            // 生成完整的SQL语句
            $fullSql = vsprintf($sql, $bindings);

            // 为不同类型的SQL语句添加标识和执行时间分类
            $sqlType = $this->getSqlType($fullSql);
            $timeCategory = $this->getTimeCategory($time);
            // 记录完整的SQL语句和执行时间到日志，添加SQL类型和时间分类标识
            Log::channel('daily_sql')->info("[{$sqlType} - {$timeCategory} - 执行时间: {$time}ms]");
            Log::channel('daily_sql')->debug("{$fullSql}");
        });
    }

    /**
     * 获取SQL语句类型
     * 
     * @param string $sql SQL语句
     * @return string SQL类型标识
     */
    private function getSqlType(string $sql): string
    {
        $sql = trim(strtoupper($sql));

        if (strpos($sql, 'SELECT') === 0) {
            return '查询';
        } elseif (strpos($sql, 'INSERT') === 0) {
            return '插入';
        } elseif (strpos($sql, 'UPDATE') === 0) {
            return '更新';
        } elseif (strpos($sql, 'DELETE') === 0) {
            return '删除';
        } elseif (strpos($sql, 'CREATE') === 0) {
            return '创建';
        } elseif (strpos($sql, 'ALTER') === 0) {
            return '修改';
        } elseif (strpos($sql, 'DROP') === 0) {
            return '删表';
        } else {
            return '其他';
        }
    }

    /**
     * 获取SQL执行时间分类
     * 
     * @param float $time 执行时间（毫秒）
     * @return string 时间分类标识
     */
    private function getTimeCategory(float $time): string
    {
        if ($time < 10) {
            return '快速';
        } elseif ($time < 100) {
            return '一般';
        } elseif ($time < 1000) {
            return '慢速';
        } else {
            return '超慢';
        }
    }
}
