# 使用示例

Easy MQTT 提供了丰富的使用示例，帮助您快速上手。

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
$factory->subscribe('test/topic', function ($message) {
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
$factory->publish('important/message', 'Critical alert', 1, true);
```

### 使用 Message 对象

```php
use EasyMqtt\Message;

$message = new Message([
    'topic' => 'sensors/temperature',
    'data' => ['value' => 25.5, 'unit' => 'celsius'],
]);

$factory->publish($message);
```

## 消息订阅

### 简单订阅

```php
$factory->subscribe('sensors/+', function ($message) {
    echo "收到传感器数据: " . $message . "\n";
});
```

### 订阅带 QoS

```php
$factory->subscribe('important/+', function ($message) {
    echo "重要消息: " . $message . "\n";
}, 1);
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
$factory->subscribe('sensors/temperature', function ($message) {
    echo "温度: " . $message . "\n";
});

$factory->subscribe('sensors/humidity', function ($message) {
    echo "湿度: " . $message . "\n";
});

$factory->subscribe('sensors/pressure', function ($message) {
    echo "压力: " . $message . "\n";
});
```

## 事件处理

### 连接事件

```php
$factory->registerConnectedEventHandler(function () {
    echo "MQTT 连接已建立\n";
});
```

### 循环事件

```php
$factory->registerLoopEventHandler(function () {
    // 定期执行的任务
    echo "循环事件触发\n";
});
```

### 发布事件

```php
$factory->registerPublishEventHandler(function ($topic, $message) {
    echo "发布消息到主题: {$topic}\n";
});
```

### 消息接收事件

```php
$factory->registerMessageReceivedEventHandler(function ($topic, $message) {
    echo "从主题 {$topic} 收到消息: {$message}\n";
});
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
    $factory->subscribe('test/topic', function ($message) {
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
$factory->subscribe('devices/+/sensors/+', function ($message) {
    $data = json_decode($message, true);
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

// 定期发送心跳
$factory->registerLoopEventHandler(function () {
    $factory->publish('devices/heartbeat', [
        'device_id' => 'monitor_001',
        'timestamp' => time(),
    ]);
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
                'parameters' => ['mysql'],
            ],
        ],
    ],
];

$factory = new Factory($config);

// 订阅任务队列
$factory->subscribe('tasks/+', function ($message) {
    $task = json_decode($message, true);
    
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
$factory->subscribe('users/+/profile', function ($message) {
    $userData = json_decode($message, true);
    if ($userData) {
        // 更新本地数据库
        User::updateOrCreate(
            ['id' => $userData['id']],
            $userData
        );
    }
});

// 同步订单数据
$factory->subscribe('orders/+/status', function ($message) {
    $orderData = json_decode($message, true);
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
