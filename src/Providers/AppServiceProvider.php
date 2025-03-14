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
            $this->configureLoggingChannel();
            $this->replaceBinding();
        } catch (Throwable $e) {
            Log::error('设置SQL日志失败: ' . $e->getMessage());
        }
    }

    /**
     * 配置日志通道
     */
    private function configureLoggingChannel(): void
    {
        $sqlConfig = [
            'driver' => 'daily',
            'path' => storage_path('logs/sql/sql.log'),
            'level' => Config::get('rap.logging.sql_log_level', 'debug'),
            'days' => Config::get('rap.logging.sql_log_days', 14),
        ];

        Config::set('logging.channels.daily_sql', $sqlConfig);
    }




    /**
     * 替换SQL绑定参数并记录完整SQL语句
     * 
     * 此方法监听数据库查询，处理绑定参数，并记录完整的SQL语句到日志
     */
    public function replaceBinding()
    {
        DB::listen(function ($query) {
            $sql = $query->sql;
            $bindings = $query->bindings;

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

            // 记录完整的SQL语句到日志
            Log::channel('daily_sql')->debug($fullSql);
        });
    }
}
