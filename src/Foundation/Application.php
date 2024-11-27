<?php

namespace Chaihao\Rap\Foundation;

use Illuminate\Foundation\Application as LaravelApplication;

class Application extends LaravelApplication
{
    /**
     * 自定义版本号
     */
    const VERSION = '1.0.0';

    /**
     * 重写基础路径
     */
    public function getNamespace()
    {
        if (!is_null($this->namespace)) {
            return $this->namespace;
        }

        $composerPath = $this->basePath('composer.json');

        if (!file_exists($composerPath)) {
            throw new \RuntimeException('composer.json 文件不存在');
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('composer.json 解析失败: ' . json_last_error_msg());
        }

        if (!isset($composer['autoload']['psr-4']) || empty($composer['autoload']['psr-4'])) {
            throw new \RuntimeException('composer.json 中未找到 psr-4 自动加载配置');
        }

        return $this->namespace = $composer['autoload']['psr-4'][array_key_first($composer['autoload']['psr-4'])];
    }

    /**
     * 注册基础服务提供者
     */
    protected function registerBaseServiceProviders()
    {
        parent::registerBaseServiceProviders();

        $providers = [
            \Chaihao\Rap\Providers\AppServiceProvider::class,
            \Chaihao\Rap\Providers\CurrentStaffServiceProvider::class,
        ];

        $this->ensureProvidersExist($providers);

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * 注册基础绑定
     */
    public function registerBaseBindings()
    {
        parent::registerBaseBindings();

        // 在这里可以添加额外的基础绑定
    }

    /**
     * 获取应用版本号
     */
    public function version()
    {
        return static::VERSION;
    }

    /**
     * 确保服务提供者存在
     */
    protected function ensureProvidersExist(array $providers)
    {
        foreach ($providers as $provider) {
            if (!class_exists($provider)) {
                throw new \RuntimeException("服务提供者 {$provider} 不存在");
            }
        }
    }
}
