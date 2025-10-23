<?php

declare(strict_types=1);

use EasyMqtt\Factory;
use Illuminate\Support\ServiceProvider;
use EasyMqtt\LaravelMqttFactoryServiceProvider;
use Illuminate\Contracts\Foundation\Application;

// ==================== 基础功能测试 ====================

test('service provider extends laravel service provider', function () {
    $app = Mockery::mock(Application::class);
    $provider = new LaravelMqttFactoryServiceProvider($app);

    expect($provider)->toBeInstanceOf(ServiceProvider::class);
});

test('service provider can be instantiated', function () {
    $app = Mockery::mock(Application::class);
    $provider = new LaravelMqttFactoryServiceProvider($app);

    expect($provider)->toBeInstanceOf(LaravelMqttFactoryServiceProvider::class);
});

test('service provider has required methods', function () {
    $app = Mockery::mock(Application::class);
    $provider = new LaravelMqttFactoryServiceProvider($app);

    expect(method_exists($provider, 'boot'))->toBeTrue()
        ->and(method_exists($provider, 'register'))->toBeTrue()
        ->and(method_exists($provider, 'provides'))->toBeTrue();
});

test('service provider methods are callable', function () {
    $app = Mockery::mock(Application::class);
    $provider = new LaravelMqttFactoryServiceProvider($app);

    expect(is_callable([$provider, 'boot']))->toBeTrue()
        ->and(is_callable([$provider, 'register']))->toBeTrue()
        ->and(is_callable([$provider, 'provides']))->toBeTrue();
});

// ==================== provides 方法测试 ====================

test('service provider provides correct services', function () {
    $app = Mockery::mock(Application::class);
    $provider = new LaravelMqttFactoryServiceProvider($app);

    $provides = $provider->provides();

    expect($provides)->toBeArray()
        ->and($provides)->toContain('mqtt-factory')
        ->and($provides)->toContain(Factory::class)
        ->and(count($provides))->toBe(2);
});

// ==================== register 方法测试 ====================

test('register method binds mqtt-factory as singleton', function () {
    $app = Mockery::mock(Application::class);

    // 模拟 config 服务
    $configMock = Mockery::mock();
    $configMock->shouldReceive('get')
        ->with('mqtt')
        ->andReturn([
            'default' => 'test',
            'connections' => [
                'test' => [
                    'host' => 'localhost',
                    'port' => 1883,
                ],
            ],
        ]);

    $app->shouldReceive('make')
        ->with('config')
        ->andReturn($configMock);

    // 模拟单例绑定
    $app->shouldReceive('singleton')
        ->with('mqtt-factory', Mockery::type('Closure'))
        ->once();

    // 模拟类绑定
    $app->shouldReceive('singleton')
        ->with(Factory::class, Mockery::type('Closure'))
        ->once();

    $provider = new LaravelMqttFactoryServiceProvider($app);
    $provider->register();
});

test('register method creates factory with config callback', function () {
    $app = Mockery::mock(Application::class);

    $configData = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1883,
            ],
        ],
    ];

    // 模拟 config 服务
    $configMock = Mockery::mock();
    $configMock->shouldReceive('get')
        ->with('mqtt')
        ->andReturn($configData);

    $app->shouldReceive('make')
        ->with('config')
        ->andReturn($configMock);

    // 捕获传递给 singleton 的回调
    $factoryCallback = null;
    $app->shouldReceive('singleton')
        ->with('mqtt-factory', Mockery::on(function ($callback) use (&$factoryCallback) {
            $factoryCallback = $callback;

            return true;
        }));

    $app->shouldReceive('singleton')
        ->with(Factory::class, Mockery::type('Closure'));

    $provider = new LaravelMqttFactoryServiceProvider($app);
    $provider->register();

    // 验证回调创建的 Factory 实例
    expect($factoryCallback)->not->toBeNull();

    // 模拟 Application 参数
    $mockApp = Mockery::mock(Application::class);
    $mockApp->shouldReceive('make')
        ->with('config')
        ->andReturn($configMock);

    $factory = $factoryCallback($mockApp);
    expect($factory)->toBeInstanceOf(Factory::class)
        ->and($factory->getConfig())->toBe($configData);
});

test('register method binds factory class to container', function () {
    $app = Mockery::mock(Application::class);

    $configMock = Mockery::mock();
    $configMock->shouldReceive('get')
        ->with('mqtt')
        ->andReturn([]);

    $app->shouldReceive('make')
        ->with('config')
        ->andReturn($configMock);

    // 模拟单例绑定
    $factoryInstance = Mockery::mock(Factory::class);
    $app->shouldReceive('singleton')
        ->with('mqtt-factory', Mockery::type('Closure'))
        ->andReturnUsing(function ($key, $callback) use ($factoryInstance) {
            // 模拟容器返回
            return $factoryInstance;
        });

    // 捕获传递给 singleton 的回调
    $bindCallback = null;
    $app->shouldReceive('singleton')
        ->with(Factory::class, Mockery::on(function ($callback) use (&$bindCallback) {
            $bindCallback = $callback;

            return true;
        }));

    $provider = new LaravelMqttFactoryServiceProvider($app);
    $provider->register();

    // 验证 bind 回调
    expect($bindCallback)->not->toBeNull();
});

test('register method handles empty config gracefully', function () {
    $app = Mockery::mock(Application::class);

    $configMock = Mockery::mock();
    $configMock->shouldReceive('get')
        ->with('mqtt')
        ->andReturn([]);

    $app->shouldReceive('make')
        ->with('config')
        ->andReturn($configMock);

    $app->shouldReceive('singleton')
        ->with('mqtt-factory', Mockery::type('Closure'));

    $app->shouldReceive('singleton')
        ->with(Factory::class, Mockery::type('Closure'));

    $provider = new LaravelMqttFactoryServiceProvider($app);
    $provider->register();

    // 验证没有抛出异常
    expect($provider)->toBeInstanceOf(LaravelMqttFactoryServiceProvider::class);
});

// ==================== boot 方法测试 ====================

test('boot method publishes config file when running in console', function () {
    $app = Mockery::mock(Application::class);

    // 模拟运行在控制台
    $app->shouldReceive('runningInConsole')
        ->andReturn(true);

    $provider = new LaravelMqttFactoryServiceProvider($app);

    // 由于 publishes 是 ServiceProvider 的方法，我们需要使用部分模拟
    $provider = Mockery::mock(LaravelMqttFactoryServiceProvider::class, [$app])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $provider->shouldReceive('publishes')
        ->with(Mockery::type('array'), 'config')
        ->once();

    $provider->boot();
});

test('boot method does not publish config file when not running in console', function () {
    $app = Mockery::mock(Application::class);

    // 模拟不在控制台运行
    $app->shouldReceive('runningInConsole')
        ->andReturn(false);

    // 不应该调用 publishes
    $app->shouldNotReceive('publishes');

    $provider = new LaravelMqttFactoryServiceProvider($app);
    $provider->boot();
});

// ==================== 集成测试 ====================

test('service provider integration test', function () {
    $app = Mockery::mock(Application::class);

    $configData = [
        'default' => 'primary',
        'connections' => [
            'primary' => [
                'host' => 'localhost',
                'port' => 1883,
            ],
            'secondary' => [
                'host' => 'localhost',
                'port' => 1884,
            ],
        ],
    ];

    // 模拟 config 服务
    $configMock = Mockery::mock();
    $configMock->shouldReceive('get')
        ->with('mqtt')
        ->andReturn($configData);

    $app->shouldReceive('make')
        ->with('config')
        ->andReturn($configMock);

    // 模拟单例和绑定
    $app->shouldReceive('singleton')
        ->with('mqtt-factory', Mockery::type('Closure'))
        ->once();

    $app->shouldReceive('singleton')
        ->with(Factory::class, Mockery::type('Closure'))
        ->once();

    $provider = new LaravelMqttFactoryServiceProvider($app);

    // 只测试 register 方法
    $provider->register();

    // 验证 provides
    $provides = $provider->provides();
    expect($provides)->toBeArray()
        ->and($provides)->toContain('mqtt-factory')
        ->and($provides)->toContain(Factory::class);
});

// ==================== 错误处理测试 ====================

test('service provider handles invalid config gracefully', function () {
    $app = Mockery::mock(Application::class);

    // 模拟 config 服务返回 null
    $configMock = Mockery::mock();
    $configMock->shouldReceive('get')
        ->with('mqtt')
        ->andReturn(null);

    $app->shouldReceive('make')
        ->with('config')
        ->andReturn($configMock);

    $app->shouldReceive('singleton')
        ->with('mqtt-factory', Mockery::type('Closure'));

    $app->shouldReceive('singleton')
        ->with(Factory::class, Mockery::type('Closure'));

    $provider = new LaravelMqttFactoryServiceProvider($app);

    // 应该不会抛出异常
    expect(fn () => $provider->register())->not->toThrow(Exception::class);
});

// 辅助函数
if (! function_exists('config_path')) {
    function config_path($path = '')
    {
        return '/config/' . $path;
    }
}
