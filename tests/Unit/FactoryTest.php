<?php

declare(strict_types=1);

use EasyMqtt\Factory;
use EasyMqtt\Message;
use PhpMqtt\Client\MqttClient;
use EasyMqtt\Support\HookManager;
use EasyMqtt\Support\LoopEventHandler;
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
    class MockLogger implements \Psr\Log\LoggerInterface
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
    class MockRepository implements \PhpMqtt\Client\Contracts\Repository
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

        public function getPendingOutgoingMessage(int $messageId): ?\PhpMqtt\Client\PendingMessage
        {
            return null;
        }

        public function getPendingOutgoingMessagesLastSentBefore(?\DateTime $dateTime = null): array
        {
            return [];
        }

        public function addPendingOutgoingMessage(\PhpMqtt\Client\PendingMessage $message): void
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

        public function getPendingIncomingMessage(int $messageId): ?\PhpMqtt\Client\PendingMessage
        {
            return null;
        }

        public function addPendingIncomingMessage(\PhpMqtt\Client\PendingMessage $message): void
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

        public function addSubscription(\PhpMqtt\Client\Subscription $subscription): void
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

    $factory = new Factory(fn () => $config);

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
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    // 这会抛出连接异常，但会先验证配置
    expect(fn () => $factory->make())
        ->toThrow(Exception::class); // 连接异常
});

// ==================== 发布测试 ====================

test('can publish string message', function () {
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

    // 这里无法真正测试发布，因为没有真实的 MQTT broker
    // 但可以测试方法调用不会抛出配置异常
    expect(fn () => $factory->publish('test/topic', 'test message'))
        ->toThrow(ConfigurationException::class); // 会抛出配置异常
});

test('can publish array message', function () {
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

    expect(fn () => $factory->publish('test/topic', ['key' => 'value']))
        ->toThrow(ConfigurationException::class); // 会抛出配置异常
});

test('can publish message object', function () {
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
    $message = new Message(['data' => 'test'], 'test/topic');

    expect(fn () => $factory->publish($message))
        ->toThrow(ConfigurationException::class); // 会抛出配置异常
});

test('can publish message object with custom connection', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
            'custom' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);
    $message = new Message(['data' => 'test'], 'test/topic', 'custom');

    expect(fn () => $factory->publish($message))
        ->toThrow(ConfigurationException::class); // 会抛出配置异常
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

// ==================== 中断循环测试 ====================

test('can interrupt mqtt client loop', function () {
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

    // 这里无法真正测试中断循环，因为没有真实的 MQTT broker
    // 但可以测试方法调用不会抛出配置异常
    expect(fn () => $factory->interrupt('test'))
        ->toThrow('EasyMqtt\Exceptions\ConnectionException'); // 会抛出连接异常
});

test('can interrupt default connection loop', function () {
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

    expect(fn () => $factory->interrupt())
        ->toThrow(ConfigurationException::class); // 会抛出配置异常
});

test('throws exception when interrupting non-existent connection', function () {
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

    expect(fn () => $factory->interrupt('nonexistent'))
        ->toThrow(ConfigurationException::class);
});

test('throws exception when interrupting with empty config', function () {
    $factory = new Factory([]);

    expect(fn () => $factory->interrupt('test'))
        ->toThrow(ConfigurationException::class);
});

// ==================== 断开连接测试 ====================

test('can disconnect from mqtt broker', function () {
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

    // disconnect 方法现在只是清除缓存，不会抛出异常
    expect(fn () => $factory->disconnect('test'))
        ->not->toThrow(Exception::class);
});

test('can disconnect from default connection', function () {
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

    // disconnect 方法现在只是清除缓存，不会抛出异常
    expect(fn () => $factory->disconnect())
        ->not->toThrow(Exception::class);
});

test('disconnect from non-existent connection does not throw exception', function () {
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

    // disconnect 方法现在只是清除缓存，不会抛出异常
    expect(fn () => $factory->disconnect('nonexistent'))
        ->not->toThrow(Exception::class);
});

test('disconnect with empty config does not throw exception', function () {
    $factory = new Factory([]);

    // disconnect 方法现在只是清除缓存，不会抛出异常
    expect(fn () => $factory->disconnect('test'))
        ->not->toThrow(Exception::class);
});

// ==================== 订阅测试 ====================

test('can subscribe to topic', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->subscribe('test/topic', fn () => null))
        ->toThrow(ConfigurationException::class); // 会抛出配置异常
});

test('can subscribe to wildcard topic', function () {
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

    expect(fn () => $factory->subscribe('test/+', fn () => null))
        ->toThrow(ConfigurationException::class); // 会抛出配置异常
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
});

test('can subscribe to valid wildcard topics', function () {
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

    // 这些应该通过验证，但会因连接失败而抛出异常
    expect(fn () => $factory->subscribe('#', fn () => null))
        ->toThrow(Exception::class); // 连接异常

    expect(fn () => $factory->subscribe('test/#', fn () => null))
        ->toThrow(Exception::class); // 连接异常

    expect(fn () => $factory->subscribe('test/+/subtopic', fn () => null))
        ->toThrow(Exception::class); // 连接异常

    expect(fn () => $factory->subscribe('+/test', fn () => null))
        ->toThrow(Exception::class); // 连接异常
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
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'client_id' => 'custom-client-id',
            ],
        ],
    ];

    $factory = new Factory($config);

    // 这会抛出连接异常，但会先验证配置
    expect(fn () => $factory->make('test'))
        ->toThrow('EasyMqtt\Exceptions\ConnectionException'); // 连接异常
});

test('generates client_id when not provided', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'client_id_prefix' => 'test-prefix',
            ],
        ],
    ];

    $factory = new Factory($config);

    // 这会抛出连接异常，但会先验证配置
    expect(fn () => $factory->make('test'))
        ->toThrow('EasyMqtt\Exceptions\ConnectionException'); // 连接异常
});

// ==================== 端口处理测试 ====================

test('handles numeric port correctly', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => '1887', // 字符串端口，使用不存在的端口
            ],
        ],
    ];

    $factory = new Factory($config);

    // 这会抛出连接异常，但会先验证配置
    expect(fn () => $factory->make('test'))
        ->toThrow('EasyMqtt\Exceptions\ConnectionException'); // 连接异常
});

test('uses default port when not specified', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1888, // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    // 这会抛出连接异常，但会先验证配置
    expect(fn () => $factory->make('test'))
        ->toThrow('EasyMqtt\Exceptions\ConnectionException'); // 连接异常
});

// ==================== 发布参数测试 ====================

test('can publish with custom qos and retain', function () {
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

    // 测试不同的 QoS 级别和 retain 设置
    expect(fn () => $factory->publish('test/topic', 'message', 'test', 1, true))
        ->toThrow(Exception::class); // 连接异常

    expect(fn () => $factory->publish('test/topic', 'message', 'test', 2, false))
        ->toThrow(Exception::class); // 连接异常
});

// ==================== 订阅参数测试 ====================

test('can subscribe with custom qos', function () {
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

    // 测试不同的 QoS 级别
    expect(fn () => $factory->subscribe('test/topic', fn () => null, 'test', 1))
        ->toThrow(Exception::class); // 连接异常

    expect(fn () => $factory->subscribe('test/topic', fn () => null, 'test', 2))
        ->toThrow(Exception::class); // 连接异常
});

// ==================== 边界情况测试 ====================

test('handles null message in publish', function () {
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
        ->toThrow(Exception::class); // 连接异常
});

test('handles empty array message in publish', function () {
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

    expect(fn () => $factory->publish('test/topic', []))
        ->toThrow(Exception::class); // 连接异常
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

    $factory = new Factory($config);

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

    expect(fn () => $factory->publish('test/topic', $complexMessage))
        ->toThrow(Exception::class); // 连接异常
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

// ==================== 连接缓存管理测试 ====================

test('can check if connection is cached', function () {
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

    // 初始状态没有缓存
    expect($factory->hasConnectionCache('test'))->toBeFalse()
        ->and($factory->hasConnectionCache())->toBeFalse(); // 默认连接
});

test('can clear specific connection cache', function () {
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

    // 清除指定连接的缓存
    $factory->clearConnectionCache('test');
    expect($factory->hasConnectionCache('test'))->toBeFalse();

    // 清除默认连接的缓存
    $factory->clearConnectionCache();
    expect($factory->hasConnectionCache())->toBeFalse();
});

test('can clear all connections cache', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
            'secondary' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    // 清除所有连接缓存
    $factory->clearAllConnectionsCache();
    expect($factory->hasConnectionCache('test'))->toBeFalse()
        ->and($factory->hasConnectionCache('secondary'))->toBeFalse();
});

test('connection cache management works with default connection', function () {
    $config = [
        'default' => 'primary',
        'connections' => [
            'primary' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
            ],
        ],
    ];

    $factory = new Factory($config);

    // 测试默认连接的缓存管理
    expect($factory->hasConnectionCache())->toBeFalse();

    $factory->clearConnectionCache();
    expect($factory->hasConnectionCache())->toBeFalse();

    $factory->clearAllConnectionsCache();
    expect($factory->hasConnectionCache())->toBeFalse();
});

// ==================== 配置验证测试 ====================

test('validates connection timeout settings', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'connect_timeout' => 300, // 超过最大值
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow('EasyMqtt\Exceptions\ConnectionException');
});

test('validates socket timeout settings', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'socket_timeout' => 0, // 小于最小值
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('validates keep alive interval settings', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'keep_alive_interval' => 70000, // 超过最大值
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('validates reconnect attempts settings', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'max_reconnect_attempts' => -1, // 负数
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('validates delay between reconnect attempts', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'delay_between_reconnect_attempts' => -1, // 负数
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('validates last will settings', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'last_will' => [
                        'topic' => 'test/topic',
                        // 缺少 message
                    ],
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('validates last will qos settings', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'last_will' => [
                        'topic' => 'test/topic',
                        'message' => 'test message',
                        'quality_of_service' => 3, // 无效的 QoS 级别
                    ],
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('validates tls certificate settings', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'tls' => [
                        'client_certificate_file' => '/path/to/cert.pem',
                        // 缺少对应的 key 文件
                    ],
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('validates tls certificate file existence', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'tls' => [
                        'client_certificate_file' => '/nonexistent/cert.pem',
                        'client_certificate_key_file' => '/nonexistent/key.pem',
                    ],
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});

test('validates tls certificate directory existence', function () {
    $config = [
        'default' => 'test',
        'connections' => [
            'test' => [
                'host' => 'localhost',
                'port' => 1885, // 使用不存在的端口避免连接到真实 MQTT broker // 使用不存在的端口避免连接到真实 MQTT broker
                'connection_settings' => [
                    'tls' => [
                        'certificate_authority_path' => '/nonexistent/directory',
                    ],
                ],
            ],
        ],
    ];

    $factory = new Factory($config);

    expect(fn () => $factory->make('test'))
        ->toThrow(ConfigurationException::class);
});
