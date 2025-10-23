<?php

declare(strict_types=1);

namespace EasyMqtt;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;

/**
 * Laravel MQTT Factory Service Provider
 *
 * 为 Laravel 应用提供 MQTT Factory 服务
 * 自动发布配置文件并注册服务到容器
 */
class LaravelMqttFactoryServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册 Factory 为单例
        $this->app->singleton('mqtt-factory', function (Application $app) {
            return new Factory($app->make('config')->get('mqtt'));
        });

        // 绑定 Factory 类
        $this->app->singleton(Factory::class, function (Application $app) {
            return $app['mqtt-factory'];
        });
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 发布配置文件
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mqtt.example.php' => config_path('mqtt.php'),
            ], 'config');
        }
    }

    /**
     * 获取服务提供的服务
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'mqtt-factory',
            Factory::class,
        ];
    }
}
