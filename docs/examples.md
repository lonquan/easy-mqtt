# 使用示例

Easy MQTT 提供了丰富的使用示例，帮助您快速上手。示例适用于 PHP 8.4–8.6、Laravel 13 及后续版本。

未显式指定连接名时，`make()`、`publish()`、`subscribe()` 及批量方法使用配置中的 `default` 连接。订阅回调始终接收实际主题和 `EasyMqtt\Message` 对象：`function (string $topic, Message $message): void`。

## 基础使用

### 简单连接

```php
use EasyMqtt\Factory;

$config = [
    'default' => 'mqtt',
    'connections' => [
        'mqtt' => [
            'host' => 'localhost',
            'port' => 1883,
        ],
    ],
];

$factory = new Factory($config);
$mqtt = $factory->make();

// 发布消息
$factory->publish('test/topic', 'Hello MQTT!');

// 订阅主题
$factory->subscribe('test/topic', function ($topic, $message) {
    echo "收到消息: " . $message . "\n";
});
```

### 带认证的连接

```php
$config = [
    'default' => 'mqtt_auth',
    'connections' => [
        'mqtt_auth' => [
            'host' => 'localhost',
            'port' => 1883,
            'username' => 'your_username',
            'password' => 'your_password',
        ],
    ],
];

$factory = new Factory($config);
$mqtt = $factory->make();
```

### TLS 连接

```php
$config = [
    'default' => 'mqtt_tls',
    'connections' => [
        'mqtt_tls' => [
            'host' => 'mqtt.example.com',
            'port' => 8883,
            'username' => 'your_username',
            'password' => 'your_password',
            'connection_settings' => [
                'tls' => [
                    'use_tls' => true,
                    'verify_peer' => true,
                    'certificate_authority_file' => '/path/to/ca.crt',
                ],
            ],
        ],
    ],
];

$factory = new Factory($config);
$mqtt = $factory->make();
```

## 消息发布

### 发布字符串消息

```php
$factory->publish('sensors/temperature', '25.5');
```

### 发布 JSON 消息

```php
$data = [
    'temperature' => 25.5,
    'humidity' => 60,
    'timestamp' => time(),
];

$factory->publish('sensors/data', $data);
```

### 发布带 QoS 的消息

```php
$factory->publish('important/message', 'Critical alert', qos: 1, retain: true);
```

### 使用 Message 对象

```php
use EasyMqtt\Message;

$message = new Message([
    'data' => ['value' => 25.5, 'unit' => 'celsius'],
], 'sensors/temperature');

$factory->publish($message);
```

### 批量发布

```php
use EasyMqtt\Message;

$factory->publishMany([
    ['topic' => 'sensors/temperature', 'message' => '25.5'],
    ['topic' => 'sensors/data', 'message' => ['humidity' => 60]],
    Message::make(['status' => 'online'], 'devices/status'),
], qos: 1, retain: false);
```

批量发布会复用同一个客户端连接，统一使用 `connection`、`qos` 和 `retain` 参数；数组中的 `Message` 对象自身设置的连接名会被忽略。

## 消息订阅

### 简单订阅

```php
$factory->subscribe('sensors/+', function ($topic, $message) {
    echo "收到传感器数据: " . $message . "\n";
});
```

### 订阅带 QoS

```php
$factory->subscribe('important/+', function ($topic, $message) {
    echo "重要消息: " . $message . "\n";
}, qos: 1);
```

### 订阅带信号处理

```php
// 启用信号处理器，支持优雅停止
$factory->subscribe(
    'sensors/+', 
    function ($topic, $message) {
        echo "收到传感器数据: " . $message . "\n";
    },
    'default',  // 连接名称
    0,          // QoS 级别
    true        // 启用信号处理器
);
```

### 订阅多个主题

```php
$factory->subscribe('sensors/temperature', function ($topic, $message) {
    echo "温度: " . $message . "\n";
});

$factory->subscribe('sensors/humidity', function ($topic, $message) {
    echo "湿度: " . $message . "\n";
});

$factory->subscribe('sensors/pressure', function ($topic, $message) {
    echo "压力: " . $message . "\n";
});
```

### 批量订阅

```php
use EasyMqtt\Message;

$factory->subscribeMany(
    ['sensors/+', 'devices/+/status'],
    function (string $topic, Message $message): void {
        echo "收到 [{$topic}] 的消息: " . $message->raw() . "\n";
    },
    qos: 1,
);
```

批量订阅会复用同一个客户端连接，所有主题共享同一个消息处理器。`#` 必须单独占据主题最后一层，`+` 必须单独占据一层。

## 事件处理

### 连接事件

```php
$factory->registerConnectedEventHandler(function ($mqtt, $isAutoReconnect, $connection) {
    echo "MQTT 连接 [{$connection}] 已建立\n";
});
```

### 循环事件

```php
$factory->registerLoopEventHandler(function ($mqtt, $elapsedTime, $connection) {
    echo "连接 [{$connection}] 的循环已运行 {$elapsedTime} 秒\n";
});
```

### 发布事件

```php
$factory->registerPublishEventHandler(
    function ($mqtt, $topic, $message, $messageId, $qos, $retain, $connection) {
        echo "通过 [{$connection}] 发布消息到主题: {$topic}\n";
    }
);
```

### 消息接收事件

```php
$factory->registerMessageReceivedEventHandler(
    function ($mqtt, $topic, $message, $qos, $retained, $connection) {
        echo "通过 [{$connection}] 收到主题 [{$topic}] 的消息: {$message}\n";
    }
);
```

## 高级功能

### 使用日志记录

```php
$config = [
    'default' => 'mqtt_logging',
    'connections' => [
        'mqtt_logging' => [
            'host' => 'localhost',
            'port' => 1883,
            'logging' => [
                'enabled' => true,
                'channel' => 'mqtt',
            ],
        ],
    ],
];

$factory = new Factory($config);
```

### 使用自定义 Repository

```php
$config = [
    'default' => 'mqtt_repository',
    'connections' => [
        'mqtt_repository' => [
            'host' => 'localhost',
            'port' => 1883,
            'repository' => [
                'class' => 'App\Repositories\CustomMqttRepository',
            ],
        ],
    ],
];

$factory = new Factory($config);
```

### 遗嘱消息

```php
$config = [
    'default' => 'mqtt_will',
    'connections' => [
        'mqtt_will' => [
            'host' => 'localhost',
            'port' => 1883,
            'connection_settings' => [
                'last_will' => [
                    'topic' => 'device/offline',
                    'message' => 'Device disconnected',
                    'quality_of_service' => 1,
                    'retain' => true,
                ],
            ],
        ],
    ],
];

$factory = new Factory($config);
```

## 错误处理

### 捕获连接异常

```php
try {
    $factory = new Factory($config);
    $mqtt = $factory->make();
} catch (ConnectionException $e) {
    echo "连接失败: " . $e->getMessage() . "\n";
}
```

### 捕获发布异常

```php
try {
    $factory->publish('test/topic', 'Hello');
} catch (PublishException $e) {
    echo "发布失败: " . $e->getMessage() . "\n";
}
```

### 捕获订阅异常

```php
try {
    $factory->subscribe('test/topic', function ($topic, $message) {
        echo $message . "\n";
    });
} catch (SubscriptionException $e) {
    echo "订阅失败: " . $e->getMessage() . "\n";
}
```

## 实际应用示例

### IoT 设备监控

```php
use EasyMqtt\Factory;

$config = [
    'default' => 'iot_monitoring',
    'connections' => [
        'iot_monitoring' => [
            'host' => 'iot.mqtt.example.com',
            'port' => 8883,
            'username' => 'iot_user',
            'password' => 'iot_password',
            'connection_settings' => [
                'tls' => ['use_tls' => true],
                'last_will' => [
                    'topic' => 'devices/offline',
                    'message' => 'Device offline',
                ],
            ],
            'logging' => ['enabled' => true],
        ],
    ],
];

$factory = new Factory($config);

// 订阅设备数据
$factory->subscribe('devices/+/sensors/+', function ($topic, $message) {
    $data = json_decode($message->raw(), true);
    if ($data && isset($data['temperature'])) {
        if ($data['temperature'] > 30) {
            // 温度过高，发送告警
            $factory->publish('alerts/temperature', [
                'device_id' => $data['device_id'],
                'temperature' => $data['temperature'],
                'timestamp' => time(),
            ]);
        }
    }
});

// 每 30 秒发送一次心跳
$lastHeartbeat = 0.0;
$factory->registerLoopEventHandler(function ($mqtt, $elapsedTime) use ($factory, &$lastHeartbeat) {
    if ($elapsedTime - $lastHeartbeat < 30) {
        return;
    }

    $factory->publish('devices/heartbeat', [
        'device_id' => 'monitor_001',
        'timestamp' => time(),
    ]);
    $lastHeartbeat = $elapsedTime;
});
```

### 消息队列处理

```php
$config = [
    'default' => 'message_queue',
    'connections' => [
        'message_queue' => [
            'host' => 'mqtt.example.com',
            'port' => 1883,
            'repository' => [
                'class' => 'App\Repositories\DatabaseMqttRepository',
            ],
        ],
    ],
];

$factory = new Factory($config);

// 订阅任务队列
$factory->subscribe('tasks/+', function ($topic, $message) {
    $task = json_decode($message->raw(), true);
    
    if ($task && isset($task['type'])) {
        switch ($task['type']) {
            case 'email':
                $this->processEmailTask($task);
                break;
            case 'sms':
                $this->processSmsTask($task);
                break;
            case 'notification':
                $this->processNotificationTask($task);
                break;
        }
        
        // 发送任务完成通知
        $factory->publish('tasks/completed', [
            'task_id' => $task['id'],
            'status' => 'completed',
            'timestamp' => time(),
        ]);
    }
});
```

### 实时数据同步

```php
$config = [
    'default' => 'data_sync',
    'connections' => [
        'data_sync' => [
            'host' => 'sync.mqtt.example.com',
            'port' => 1883,
            'logging' => [
                'enabled' => true,
                'channel' => 'mqtt_sync',
            ],
        ],
    ],
];

$factory = new Factory($config);

// 同步用户数据
$factory->subscribe('users/+/profile', function ($topic, $message) {
    $userData = json_decode($message->raw(), true);
    if ($userData) {
        // 更新本地数据库
        User::updateOrCreate(
            ['id' => $userData['id']],
            $userData
        );
    }
});

// 同步订单数据
$factory->subscribe('orders/+/status', function ($topic, $message) {
    $orderData = json_decode($message->raw(), true);
    if ($orderData) {
        // 更新订单状态
        Order::where('id', $orderData['id'])
            ->update(['status' => $orderData['status']]);
    }
});
```

## 信号处理

MQTT Factory 支持信号处理功能，允许优雅地停止订阅循环。这对于在 Laravel Command 中运行长时间订阅任务特别有用。

### 启用信号处理

```php
$factory->subscribe(
    'sensors/+',
    function ($topic, $message) {
        echo "收到消息: {$message}\n";
    },
    'default',  // 连接名称
    0,          // QoS 级别
    true        // 启用信号处理器
);
```

### 支持的信号

- **SIGTERM** (15) - PM2 停止信号
- **SIGINT** (2) - Ctrl+C 中断信号
- **SIGQUIT** (3) - 退出信号

### Laravel Command 示例

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use EasyMqtt\Factory;
use EasyMqtt\Message;

class MqttSubscribeCommand extends Command
{
    protected $signature = 'mqtt:subscribe {topic} {--signal-handler}';
    protected $description = 'Subscribe to MQTT topic with signal handling';

    public function handle(): int
    {
        $factory = app(Factory::class);
        $topic = $this->argument('topic');
        $enableSignalHandler = $this->option('signal-handler');

        if ($enableSignalHandler) {
            $this->info('信号处理器已启用 - 按 Ctrl+C 来优雅停止');
        }

        try {
            $factory->subscribe(
                $topic,
                function (string $topic, Message $message) {
                    $this->line("收到消息 [{$topic}]: {$message->raw()}");
                },
                'default',
                0,
                $enableSignalHandler
            );
        } catch (\Exception $e) {
            $this->error("订阅失败: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
```

### PM2 配置示例

```javascript
module.exports = {
  apps: [{
    name: 'mqtt-subscriber',
    script: 'artisan',
    args: 'mqtt:subscribe sensors/+ --signal-handler',
    interpreter: 'php',
    instances: 1,
    autorestart: true,
    kill_timeout: 5000,
    wait_ready: true
  }]
};
```

### 注意事项

1. **pcntl 扩展**: 信号处理功能需要 `pcntl` 扩展支持
2. **优雅停止**: 启用信号处理器后，可以通过发送信号来优雅地停止订阅循环
3. **资源清理**: 信号处理器会自动调用 `interrupt()` 方法来停止 MQTT 循环
4. **兼容性**: 如果 `pcntl` 扩展未安装，信号处理功能会被自动禁用

## 连接管理

### 中断 MQTT 客户端循环

如果需要手动中断正在运行的 MQTT 客户端循环，可以使用 `interrupt()` 方法：

```php
use EasyMqtt\Factory;
use PhpMqtt\Client\MqttClient;

$factory = new Factory($config);

// 创建 MQTT 客户端实例
$mqtt = $factory->make('default');

// 在另一个线程或进程中中断循环
$factory->interrupt($mqtt);
```

### 断开 MQTT 连接

当不再需要连接时，可以调用 `disconnect()` 方法来断开连接：

```php
use EasyMqtt\Factory;
use PhpMqtt\Client\MqttClient;

$factory = new Factory($config);

// 创建 MQTT 客户端实例
$mqtt = $factory->make('default');

// 使用完毕后断开连接
$factory->disconnect($mqtt);
```

### 完整示例

```php
use EasyMqtt\Factory;
use PhpMqtt\Client\MqttClient;

$factory = new Factory($config);

try {
    // 创建连接
    $mqtt = $factory->make('default');
    
    // 使用同一个客户端发布消息
    $mqtt->publish('test/topic', 'Hello');
    
    // 断开连接
    $factory->disconnect($mqtt);
} catch (\Exception $e) {
    echo "操作失败: {$e->getMessage()}\n";
}
```

### 重要提示

- `make()` 方法每次调用都会返回一个新的 `MqttClient` 实例
- `interrupt()` 和 `disconnect()` 方法需要传入 `MqttClient` 实例，而不是连接名称
- 请确保在不再需要连接时调用 `disconnect()` 来释放资源
