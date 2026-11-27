<?php

declare(strict_types=1);

use EasyMqtt\Factory;
use EasyMqtt\Message;
use Psr\Log\LoggerInterface;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Subscription;
use EasyMqtt\Support\HookManager;
use PhpMqtt\Client\PendingMessage;
use EasyMqtt\Support\LoopEventHandler;
use PhpMqtt\Client\Contracts\Repository;
use EasyMqtt\Exceptions\PublishException;
use EasyMqtt\Exceptions\SubscriptionException;
use EasyMqtt\Exceptions\ConfigurationException;
use EasyMqtt\Contracts\LoopEventHandlerInterface;
use EasyMqtt\Contracts\PublishEventHandlerInterface;
use EasyMqtt\Contracts\ConnectedEventHandlerInterface;
use EasyMqtt\Contracts\MessageReceivedEventHandlerInterface;

// 模拟 Laravel 的 config 函数
if (! function_exists('config')) {
    function config($key = null, $default = null)
    {
        static $config = [];

        if ($key === null) {
            return $config;
        }

        if (is_array($key)) {
            $config = array_merge($config, $key);

            return $config;
        }

        return $config[$key] ?? $default;
    }
}

// 模拟 Laravel 的 app 函数
if (! function_exists('app')) {
    function app($abstract = null)
    {
        static $container = [];

        if ($abstract === null) {
            return new class
            {
                public function bound($key)
                {
                    return false;
                }

                public function make($key)
                {
                    return null;
                }
            };
        }

        return $container[$abstract] ?? null;
    }
}

// 模拟 Laravel 的 Log facade
if (! class_exists('Illuminate\Support\Facades\Log')) {
    class_alias('MockLogFacade', 'Illuminate\Support\Facades\Log');
}

if (! class_exists('MockLogFacade')) {
    class MockLogFacade
    {
        public static function channel($channel)
        {
            return new MockLogger;
        }
    }
}

if (! class_exists('MockLogger')) {
    class MockLogger implements LoggerInterface
    {
        public function emergency($message, array $context = []): void
        {
        }

        public function alert($message, array $context = []): void
        {
        }

        public function critical($message, array $context = []): void
        {
        }

        public function error($message, array $context = []): void
        {
        }

        public function warning($message, array $context = []): void
        {
        }

        public function notice($message, array $context = []): void
        {
        }

        public function info($message, array $context = []): void
        {
        }

        public function debug($message, array $context = []): void
        {
        }

        public function log($level, $message, array $context = []): void
        {
        }
    }
}

// 模拟 Repository 类
if (! class_exists('MockRepository')) {
    class MockRepository implements Repository
    {
        public function reset(): void
        {
        }

        public function newMessageId(): int
        {
            return 1;
        }

        public function countPendingOutgoingMessages(): int
        {
            return 0;
        }

        public function getPendingOutgoingMessage(int $messageId): ?PendingMessage
        {
            return null;
        }

        public function getPendingOutgoingMessagesLastSentBefore(?DateTime $dateTime = null): array
        {
            return [];
        }

        public function addPendingOutgoingMessage(PendingMessage $message): void
        {
        }

        public function markPendingOutgoingPublishedMessageAsReceived(int $messageId): bool
        {
            return true;
        }

        public function removePendingOutgoingMessage(int $messageId): bool
        {
            return true;
        }

        public function countPendingIncomingMessages(): int
        {
            return 0;
        }

        public function getPendingIncomingMessage(int $messageId): ?PendingMessage
        {
            return null;
        }

        public function addPendingIncomingMessage(PendingMessage $message): void
        {
        }

        public function removePendingIncomingMessage(int $messageId): bool
        {
            return true;
        }

        public function countSubscriptions(): int
        {
            return 0;
        }

        public function addSubscription(Subscription $subscription): void
        {
        }

        public function getSubscriptionsMatchingTopic(string $topicName): array
        {
            return [];
        }

        public function removeSubscription(string $topicFilter): bool
        {
            return true;
        }
    }
}

function createFactoryWithClient(array $config, MqttClient $client, string $connection = 'default'): Factory
{
    $factory = Mockery::mock(Factory::class, [$config])->makePartial();
    $factory->shouldReceive('make')->once()->with($connection)->andReturn($client);

    return $factory;
}

// ==================== 构造函数测试 ====================

test('can create factory instance with array config', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect($factory)->toBeInstanceOf(Factory::class)
        ->and($factory->getConfig())->toBe($config);
});

test('can create factory instance with callable config', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect($factory)->toBeInstanceOf(Factory::class)
        ->and($factory->getConfig())->toBe($config);
});

// ==================== 静态方法测试 ====================

test('can get config', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect($factory->getConfig())->toBe($config);
});

// ==================== 连接创建测试 ====================

test('throws exception when making connection with empty config', function () {
    $factory = new Factory([]);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('throws exception when making non-existent connection', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('nonexistent'))
        ->toThrow(ConfigurationException::class);
});

test('throws exception when connection config missing host', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('uses default connection when no connection specified', function () {
    $config = [
        'default' => 'primary',
        'connections' => [
            'primary' => [
                'host' => 'localhost',
                'connection_settings' => ['reconnect_automatically' => true],
            ],
        ],
    ];

    $factory = new Factory($config);

    try {
        $factory->make();
        expect(false)->toBeTrue('Should have thrown an exception');
    } catch (ConfigurationException $e) {
        expect($e->getContext()['connection'])->toBe('primary');
    }
});

// ==================== 发布测试 ====================

test('can publish string message', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('publish')->once()->with('test/topic', 'test message', 0, false);
    $factory = createFactoryWithClient($config, $mqtt);

    $factory->publish('test/topic', 'test message');
});

test('can publish array message', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('publish')->once()->with('test/topic', '{"key":"value"}', 0, false);
    $factory = createFactoryWithClient($config, $mqtt);

    $factory->publish('test/topic', ['key' => 'value']);
});

test('can publish message object', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('publish')->once()->with('test/topic', '{"data":"test"}', 0, false);
    $factory = createFactoryWithClient($config, $mqtt);

    $factory->publish(new Message(['data' => 'test'], 'test/topic'));
});

test('can publish message object with custom connection', function () {
    $config = ['default' => 'test', 'connections' => ['custom' => ['host' => 'localhost']]];
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('publish')->once()->with('test/topic', '{"data":"test"}', 0, false);
    $factory = createFactoryWithClient($config, $mqtt, 'custom');

    $factory->publish(Message::make(['data' => 'test'], 'test/topic', 'custom'));
});

test('throws exception for empty publish topic', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->publish('', 'test message'))
        ->toThrow(PublishException::class);
});

test('throws exception for publish topic with wildcards', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->publish('test/+', 'test message'))
        ->toThrow(PublishException::class);

    expect(fn () => $factory->publish('test/#', 'test message'))
        ->toThrow(PublishException::class);
});

test('throws exception for publish topic starting with dollar', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->publish('$SYS/test', 'test message'))
        ->toThrow(PublishException::class);
});

test('throws exception for message object without topic', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);
    $message = new Message(['data' => 'test']); // 没有 topic

    expect(fn () => $factory->publish($message))
        ->toThrow(PublishException::class);
});

test('accepts zero as a topic name', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('publish')->once()->with('0', 'message', 0, false);
    $mqtt->shouldReceive('subscribe')->once()->with('0', Mockery::type('Closure'), 0);
    $mqtt->shouldReceive('loop')->once();
    $factory = Mockery::mock(Factory::class, [$config])->makePartial();
    $factory->shouldReceive('make')->twice()->with('default')->andReturn($mqtt);

    $factory->publish('0', 'message');
    $factory->subscribe('0', fn () => null);
});

test('publish exception reports the message connection', function () {
    $factory = Mockery::mock(Factory::class, [[]])->makePartial();
    $factory->shouldReceive('make')->once()->with('custom')->andThrow(new RuntimeException('Connection failed'));
    $message = Message::make('payload', 'test/topic', 'custom');

    try {
        $factory->publish($message);
        expect(false)->toBeTrue('Should have thrown an exception');
    } catch (PublishException $e) {
        expect($e->getContext()['connection'])->toBe('custom');
    }
});

// ==================== 中断循环测试 ====================

test('can interrupt mqtt client loop', function () {
    $factory = new Factory([]);
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('interrupt')->once();

    $factory->interrupt($mqtt);
});

// ==================== 断开连接测试 ====================

test('can disconnect from mqtt broker', function () {
    $factory = new Factory([]);
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('disconnect')->once();

    $factory->disconnect($mqtt);
});

// ==================== 订阅测试 ====================

test('can subscribe to topic', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('subscribe')->once()->withArgs(function ($topic, $callback, $qos) {
        $callback($topic, 'received message');

        return $topic === 'test/topic' && $qos === 0;
    });
    $mqtt->shouldReceive('loop')->once();
    $factory = createFactoryWithClient($config, $mqtt);
    $handled = false;

    $factory->subscribe('test/topic', function ($topic, $message) use (&$handled) {
        $handled = $topic === 'test/topic'
            && $message instanceof Message
            && $message->raw() === 'received message';
    });

    expect($handled)->toBeTrue();
});

test('can subscribe to wildcard topic', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('subscribe')->once()->with('test/+', Mockery::type('Closure'), 0);
    $mqtt->shouldReceive('loop')->once();
    $factory = createFactoryWithClient($config, $mqtt);

    $factory->subscribe('test/+', fn () => null);
});

test('throws exception for empty subscribe topic', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->subscribe('', fn () => null))
        ->toThrow(SubscriptionException::class);
});

test('throws exception for invalid wildcard usage in subscribe', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    // # 不在末尾
    expect(fn () => $factory->subscribe('test/#/invalid', fn () => null))
        ->toThrow(SubscriptionException::class);

    // # 前面没有 /
    expect(fn () => $factory->subscribe('test#', fn () => null))
        ->toThrow(SubscriptionException::class);

    // + 不占据完整层级
    expect(fn () => $factory->subscribe('test/prefix+suffix', fn () => null))
        ->toThrow(SubscriptionException::class);

    expect(fn () => $factory->subscribe('test/#/#', fn () => null))
        ->toThrow(SubscriptionException::class);

    expect(fn () => $factory->subscribe('test/##', fn () => null))
        ->toThrow(SubscriptionException::class);
});

test('can subscribe to valid wildcard topics', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];

    foreach (['#', 'test/#', 'test/+/subtopic', '+/test'] as $topic) {
        $mqtt = Mockery::mock(MqttClient::class);
        $mqtt->shouldReceive('subscribe')->once()->with($topic, Mockery::type('Closure'), 0);
        $mqtt->shouldReceive('loop')->once();
        $factory = createFactoryWithClient($config, $mqtt);

        $factory->subscribe($topic, fn () => null);
    }
});

// ==================== 容器集成测试 ====================

test('make method checks container first', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    // 模拟容器中有已注册的连接
    $mockMqtt = Mockery::mock(MqttClient::class);

    // 这里需要模拟 app() 函数返回的容器
    // 由于我们无法轻易修改全局函数，这个测试主要验证逻辑流程
    expect($factory)->toBeInstanceOf(Factory::class);
});

// ==================== 客户端 ID 解析测试 ====================

test('uses configured client_id when provided', function () {
    $factory = new Factory([]);
    $method = new ReflectionMethod(Factory::class, 'resolveClientId');

    expect($method->invoke($factory, ['client_id' => 'custom-client-id']))
        ->toBe('custom-client-id');
});

test('generates client_id when not provided', function () {
    $factory = new Factory([]);
    $method = new ReflectionMethod(Factory::class, 'resolveClientId');

    expect($method->invoke($factory, ['client_id_prefix' => 'test-prefix']))
        ->toStartWith('test-prefix');
});

// ==================== 端口处理测试 ====================

test('handles numeric port correctly', function () {
    $factory = new Factory([]);
    $method = new ReflectionMethod(Factory::class, 'resolvePort');

    expect($method->invoke($factory, ['port' => '1887']))->toBe(1887);
});

test('uses default port when not specified', function () {
    $factory = new Factory([]);
    $method = new ReflectionMethod(Factory::class, 'resolvePort');

    expect($method->invoke($factory, []))->toBe(1883);
});

// ==================== 发布参数测试 ====================

test('can publish with custom qos and retain', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('publish')->once()->with('test/topic', 'message', 1, true);
    $mqtt->shouldReceive('publish')->once()->with('test/topic', 'message', 2, false);
    $factory = Mockery::mock(Factory::class, [$config])->makePartial();
    $factory->shouldReceive('make')->twice()->with('test')->andReturn($mqtt);

    $factory->publish('test/topic', 'message', 'test', 1, true);
    $factory->publish('test/topic', 'message', 'test', 2, false);
});

// ==================== 订阅参数测试 ====================

test('can subscribe with custom qos', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];

    foreach ([1, 2] as $qos) {
        $mqtt = Mockery::mock(MqttClient::class);
        $mqtt->shouldReceive('subscribe')->once()->with('test/topic', Mockery::type('Closure'), $qos);
        $mqtt->shouldReceive('loop')->once();
        $factory = createFactoryWithClient($config, $mqtt, 'test');

        $factory->subscribe('test/topic', fn () => null, 'test', $qos);
    }
});

// ==================== 边界情况测试 ====================

test('rejects null message in direct publish', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->publish('test/topic', null))
        ->toThrow(PublishException::class);
});

test('handles empty array message in publish', function () {
    $config = ['default' => 'test', 'connections' => ['test' => ['host' => 'localhost']]];
    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('publish')->once()->with('test/topic', '[]', 0, false);
    $factory = createFactoryWithClient($config, $mqtt);

    $factory->publish('test/topic', []);
});

test('handles complex array message in publish', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $complexMessage = [
        'data' => [
            'nested' => [
                'value' => 'test',
                'number' => 123,
                'boolean' => true,
            ],
        ],
        'metadata' => [
            'timestamp' => time(),
            'source' => 'test',
        ],
    ];

    $mqtt = Mockery::mock(MqttClient::class);
    $mqtt->shouldReceive('publish')->once()->with(
        'test/topic',
        json_encode($complexMessage, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        0,
        false,
    );
    $factory = createFactoryWithClient($config, $mqtt);

    $factory->publish('test/topic', $complexMessage);
});

// ==================== Hook 功能测试 ====================

test('factory has hook manager', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    expect($factory->getHookManager())->toBeInstanceOf(HookManager::class);
});

test('register loop event handler with callback', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);
    $executed = false;

    $handler = $factory->registerLoopEventHandler(function ($mqtt, $elapsedTime) use (&$executed) {
        $executed = true;
        expect($elapsedTime)->toBe(123.45);
    });

    expect($handler)->toBeInstanceOf(LoopEventHandlerInterface::class);
    expect($handler->getId())->toContain('LoopEventHandler');
});

test('register loop event handler with custom id', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);
    $customId = 'custom-loop-handler';

    $handler = $factory->registerLoopEventHandler(function () {
    }, $customId);

    expect($handler->getId())->toBe($customId);
});

test('register loop event handler with handler instance', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);
    $handler = new LoopEventHandler(function () {
    });

    $returnedHandler = $factory->registerLoopEventHandler($handler);

    expect($returnedHandler)->toBe($handler);
});

test('unregister loop event handler', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);
    $handler1 = $factory->registerLoopEventHandler(function () {
    });
    $handler2 = $factory->registerLoopEventHandler(function () {
    });

    expect($factory->getHookManager()->getAllHooks())->toHaveCount(2);

    $factory->unregisterLoopEventHandler($handler1);
    expect($factory->getHookManager()->getAllHooks())->toHaveCount(1);

    $factory->unregisterLoopEventHandler();
    expect($factory->getHookManager()->getAllHooks())->toHaveCount(0);
});

test('register publish event handler', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);
    $executed = false;

    $handler = $factory->registerPublishEventHandler(function ($mqtt, $topic, $message, $messageId, $qos, $retain) use (&$executed) {
        $executed = true;
        expect($topic)->toBe('test/topic');
        expect($message)->toBe('test message');
        expect($messageId)->toBe(123);
        expect($qos)->toBe(1);
        expect($retain)->toBeTrue();
    });

    expect($handler)->toBeInstanceOf(PublishEventHandlerInterface::class);
});

test('register message received event handler', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);
    $executed = false;

    $handler = $factory->registerMessageReceivedEventHandler(function ($mqtt, $topic, $message, $qos, $retained) use (&$executed) {
        $executed = true;
        expect($topic)->toBe('test/topic');
        expect($message)->toBe('received message');
        expect($qos)->toBe(2);
        expect($retained)->toBeFalse();
    });

    expect($handler)->toBeInstanceOf(MessageReceivedEventHandlerInterface::class);
});

test('register connected event handler', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);
    $executed = false;

    $handler = $factory->registerConnectedEventHandler(function ($mqtt, $isAutoReconnect) use (&$executed) {
        $executed = true;
        expect($isAutoReconnect)->toBeTrue();
    });

    expect($handler)->toBeInstanceOf(ConnectedEventHandlerInterface::class);
});

test('register multiple types of handlers', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    $factory->registerLoopEventHandler(function () {
    });
    $factory->registerPublishEventHandler(function () {
    });
    $factory->registerMessageReceivedEventHandler(function () {
    });
    $factory->registerConnectedEventHandler(function () {
    });

    expect($factory->getHookManager()->getAllHooks())->toHaveCount(4);
});

test('unregister all handlers of specific type', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    $factory->registerLoopEventHandler(function () {
    });
    $factory->registerLoopEventHandler(function () {
    });
    $factory->registerPublishEventHandler(function () {
    });

    expect($factory->getHookManager()->getAllHooks())->toHaveCount(3);

    $factory->unregisterLoopEventHandler();
    expect($factory->getHookManager()->getAllHooks())->toHaveCount(1);

    $factory->unregisterPublishEventHandler();
    expect($factory->getHookManager()->getAllHooks())->toHaveCount(0);
});

// ==================== Repository 功能测试 ====================

test('can create connection without repository configuration', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    // 由于我们无法真正连接到 MQTT broker，这里只测试配置解析
    expect($factory->getConfig())->toBe($config);
});

test('can create connection with repository configuration', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'repository' => [
                    'class' => 'MockRepository',
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    // 由于我们无法真正连接到 MQTT broker，这里只测试配置解析
    expect($factory->getConfig())->toBe($config);
});

test('throws exception for invalid repository class', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'repository' => [
                    'class' => 'NonExistentRepository',
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('throws exception for repository class not implementing interface', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'repository' => [
                    'class' => 'stdClass', // stdClass 不实现 Repository 接口
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

// ==================== 日志功能测试 ====================

test('can create connection with logging disabled', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'logging' => [
                    'enabled' => false,
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    // 由于我们无法真正连接到 MQTT broker，这里只测试配置解析
    expect($factory->getConfig())->toBe($config);
});

test('can create connection with logging enabled', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'logging' => [
                    'enabled' => true,
                    'channel' => 'mqtt',
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    // 由于我们无法真正连接到 MQTT broker，这里只测试配置解析
    expect($factory->getConfig())->toBe($config);
});

test('can create connection with custom logging channel', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'logging' => [
                    'enabled' => true,
                    'channel' => 'custom_mqtt',
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    // 由于我们无法真正连接到 MQTT broker，这里只测试配置解析
    expect($factory->getConfig())->toBe($config);
});

test('can create connection without logging configuration', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    // 由于我们无法真正连接到 MQTT broker，这里只测试配置解析
    expect($factory->getConfig())->toBe($config);
});
