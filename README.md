# Easy MQTT

一个简单易用的 Laravel MQTT 客户端工厂，基于 [php-mqtt/client](https://github.com/php-mqtt/client) 构建。

## ✨ 特性

- 🚀 **简单易用** - 简洁的 API 设计，快速上手
- 🔧 **灵活配置** - 支持多种连接方式和高级配置
- 📊 **日志集成** - 内置日志记录功能
- 🗄️ **Repository 支持** - 可自定义消息存储实现
- 🎣 **事件钩子** - 丰富的事件处理机制
- 🔒 **安全连接** - 支持 TLS/SSL 加密连接
- 🏗️ **Laravel 集成** - 完美融入 Laravel 生态系统
- 📦 **智能消息封装** - Message 类提供强大的消息处理能力
- 🆔 **自动客户端 ID** - 智能生成符合规范的 MQTT 客户端 ID
- 🔄 **Fluent API** - 支持链式调用的流畅接口
- 📍 **点号语法** - 支持嵌套数据的点号访问语法
- 🎯 **信号处理** - 支持优雅停止和信号处理

## 📦 安装

### 系统要求

- PHP 8.4+
- Laravel 12.0+
- Composer

### 安装

```bash
composer require lonquan/easy-mqtt
```

## ⚙️ 配置

发布配置文件：

```bash
php artisan vendor:publish --provider="EasyMqtt\LaravelMqttFactoryServiceProvider"
```

配置文件 `config/mqtt.php`：

```php
<?php

return [
    'default' => env('MQTT_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => (int) env('MQTT_PORT', 1883),
            'username' => env('MQTT_USERNAME', null),
            'password' => env('MQTT_PASSWORD', null),
            'client_id_prefix' => env('MQTT_CLIENT_ID_PREFIX', 'mqtt'),
            'client_id' => env('MQTT_CLIENT_ID', null),
            
            // 日志配置
            'logging' => [
                'enabled' => env('MQTT_LOGGING_ENABLED', false),
                'channel' => env('MQTT_LOGGING_CHANNEL', 'mqtt'),
            ],
            
            // Repository 配置
            'repository' => [
                'class' => env('MQTT_REPOSITORY_CLASS', null),
            ],
            
            // 连接设置
            'connection_settings' => [
                'use_blocking_socket' => env('MQTT_USE_BLOCKING_SOCKET', false),
                'connect_timeout' => (int) env('MQTT_CONNECT_TIMEOUT', 60),
                'socket_timeout' => (int) env('MQTT_SOCKET_TIMEOUT', 5),
                'resend_timeout' => (int) env('MQTT_RESEND_TIMEOUT', 10),
                'reconnect_automatically' => env('MQTT_RECONNECT_AUTOMATICALLY', false),
                'max_reconnect_attempts' => (int) env('MQTT_MAX_RECONNECT_ATTEMPTS', 3),
                'delay_between_reconnect_attempts' => (int) env('MQTT_DELAY_BETWEEN_RECONNECT_ATTEMPTS', 0),
                'keep_alive_interval' => (int) env('MQTT_KEEP_ALIVE_INTERVAL', 10),

                'last_will' => [
                    'topic' => env('MQTT_LAST_WILL_TOPIC', null),
                    'message' => env('MQTT_LAST_WILL_MESSAGE', null),
                    'quality_of_service' => (int) env('MQTT_LAST_WILL_QOS', 0),
                    'retain' => env('MQTT_RETAIN_LAST_WILL', false),
                ],

                'tls' => [
                    'use_tls' => env('MQTT_USE_TLS', false),
                    'verify_peer' => env('MQTT_TLS_VERIFY_PEER', true),
                    'verify_peer_name' => env('MQTT_TLS_VERIFY_PEER_NAME', true),
                    'self_signed_allowed' => env('MQTT_TLS_SELF_SIGNED_ALLOWED', false),
                    'certificate_authority_file' => env('MQTT_TLS_CA_FILE', null),
                    'certificate_authority_path' => env('MQTT_TLS_CA_PATH', null),
                    'client_certificate_file' => env('MQTT_TLS_CLIENT_CERT_FILE', null),
                    'client_certificate_key_file' => env('MQTT_TLS_CLIENT_KEY_FILE', null),
                    'client_certificate_key_passphrase' => env('MQTT_TLS_CLIENT_KEY_PASSPHRASE', null),
                    'alpn' => env('MQTT_TLS_ALPN', null),
                ],
            ],
        ],
    ],
];
```

## 🚀 快速开始

### 基础用法

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

### Laravel 中使用

```php
use EasyMqtt\Factory;

// 使用配置
$factory = app(Factory::class);

// 发布消息
$factory->publish('sensors/temperature', '25.5');

// 订阅主题
$factory->subscribe('sensors/+', function ($message) {
    echo "传感器数据: " . $message . "\n";
});
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

### 使用 Message 类

```php
use EasyMqtt\Message;

// 创建消息对象
$message = Message::make([
    'device_id' => 'sensor_001',
    'temperature' => 25.5,
    'humidity' => 60,
], 'sensors/data');

// 使用 Fluent API
$message->useConnection('production')
        ->toTopic('sensors/temperature')
        ->send();

// 或者一步到位
$message->publishTo('sensors/data', 'production', 1, true);
```

### 消息处理

```php
// 订阅消息处理
$factory->subscribe('sensors/+', function ($topic, Message $message) {
    // 检查是否为 JSON 消息
    if ($message->isJson()) {
        // 使用点号语法访问嵌套数据
        $deviceId = $message->get('device.id');
        $temperature = $message->get('data.temperature');
        
        // 检查键是否存在
        if ($message->has('alerts')) {
            $alerts = $message->get('alerts');
        }
        
        // 获取所有数据
        $allData = $message->all();
    } else {
        // 处理普通字符串消息
        echo "收到消息: " . $message->raw() . "\n";
    }
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
```

### 自动客户端 ID 生成

```php
use EasyMqtt\Support\ClientIdGenerator;

// 生成默认格式的客户端 ID (mqtt_xxxxxxxxxx)
$clientId = ClientIdGenerator::generate();

// 生成自定义前缀的客户端 ID
$clientId = ClientIdGenerator::generate('sensor');

// 验证客户端 ID 格式
if (ClientIdGenerator::isValid($clientId)) {
    echo "客户端 ID 格式正确: " . $clientId . "\n";
}
```

### 信号处理

```php
// 启用信号处理器，支持优雅停止
$factory->subscribe(
    'sensors/+', 
    function ($topic, Message $message) {
        echo "收到传感器数据: " . $message->raw() . "\n";
    },
    'default',  // 连接名称
    0,          // QoS 级别
    true        // 启用信号处理器
);
```

## 🆕 新功能亮点

### Message 类 - 智能消息封装

Message 类提供了强大的消息处理能力：

- **双重角色**: 既可用于接收消息，也可用于构建发送消息
- **JSON 自动处理**: 自动检测和解析 JSON 消息
- **Fluent API**: 支持链式调用的流畅接口
- **点号语法**: 支持嵌套数据的点号访问（如 `device.id`）
- **类型安全**: 实现 ArrayAccess 和 JsonSerializable 接口

### ClientIdGenerator - 自动客户端 ID

- **智能生成**: 基于 ULID 生成唯一、有序的客户端 ID
- **格式规范**: 符合 MQTT 规范的客户端 ID 格式
- **自定义前缀**: 支持自定义前缀，便于识别和管理
- **格式验证**: 内置验证功能，确保生成的 ID 符合规范

### 信号处理支持

- **优雅停止**: 支持 SIGTERM、SIGINT、SIGQUIT 信号处理
- **生产就绪**: 特别适合在 Laravel Command 和 PM2 环境中使用
- **自动清理**: 信号触发时自动清理资源

## 📚 详细文档

- [配置详细说明](docs/configuration.md) - 完整的配置选项说明
- [日志功能](docs/logging.md) - 日志集成功能详解
- [Repository 功能](docs/repository.md) - 自定义存储实现
- [事件钩子](docs/hooks.md) - 事件处理机制
- [使用示例](docs/examples.md) - 丰富的使用示例和最佳实践

## 🧪 测试

```bash
# 运行测试
composer test

# 代码风格检查
composer check-style

# 静态分析
composer analyse

# 完整检查
composer check
```

## 📄 许可证

MIT License. 详见 [LICENSE](LICENSE) 文件。

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📞 支持

如有问题，请提交 Issue 或查看文档。
