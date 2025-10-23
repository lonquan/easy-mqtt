# 日志功能详细说明

Laravel MQTT Factory 支持完整的日志集成功能，允许您为 MQTT 客户端配置日志记录。

## 功能特性

- ✅ **PSR-3 兼容** - 完全符合 PSR-3 LoggerInterface 标准
- ✅ **Laravel 集成** - 使用 Laravel 的 Log facade 和日志通道
- ✅ **静默失败** - 日志配置错误不会影响 MQTT 连接
- ✅ **多环境支持** - 不同环境可以使用不同的日志通道
- ✅ **自动记录** - MQTT 客户端自动记录连接、发布、订阅等操作

## 配置方式

### 1. 配置文件设置

在 `config/mqtt.php` 中配置日志：

```php
'connections' => [
    'default' => [
        'host' => 'localhost',
        'port' => 1883,
        
        // 日志配置
        'logging' => [
            'enabled' => env('MQTT_LOGGING_ENABLED', false),
            'channel' => env('MQTT_LOGGING_CHANNEL', 'mqtt'),
        ],
    ],
],
```

### 2. 环境变量配置

在 `.env` 文件中设置：

```env
# 日志配置
MQTT_LOGGING_ENABLED=true
MQTT_LOGGING_CHANNEL=mqtt
```

### 3. Laravel 日志通道配置

在 `config/logging.php` 中配置 MQTT 日志通道：

```php
'channels' => [
    'mqtt' => [
        'driver' => 'daily',
        'path' => storage_path('logs/mqtt.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

## 日志级别

MQTT 客户端会记录以下级别的日志：

- **debug** - 详细的调试信息
- **info** - 一般信息（连接、断开等）
- **warning** - 警告信息
- **error** - 错误信息

## 使用示例

### 启用日志记录

```php
use EasyMqtt\Factory;

$config = [
    'default' => 'mqtt_with_logging',
    'connections' => [
        'mqtt_with_logging' => [
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
$mqtt = $factory->make();
```

### 禁用日志记录

```php
$config = [
    'default' => 'mqtt_no_logging',
    'connections' => [
        'mqtt_no_logging' => [
            'host' => 'localhost',
            'port' => 1883,
            'logging' => [
                'enabled' => false,
            ],
        ],
    ],
];
```

### 使用自定义日志通道

```php
$config = [
    'default' => 'mqtt_custom_logging',
    'connections' => [
        'mqtt_custom_logging' => [
            'host' => 'localhost',
            'port' => 1883,
            'logging' => [
                'enabled' => true,
                'channel' => 'custom_mqtt',
            ],
        ],
    ],
];
```

## 日志内容

MQTT 客户端会自动记录以下操作：

- 连接建立和断开
- 消息发布
- 消息订阅
- 错误和异常
- 重连尝试
- 心跳包

## 注意事项

1. **性能影响** - 启用日志记录会有轻微的性能影响
2. **存储空间** - 日志文件会占用磁盘空间，建议定期清理
3. **敏感信息** - 避免在日志中记录密码等敏感信息
4. **日志轮转** - 建议使用 Laravel 的日志轮转功能

## 故障排除

### 日志通道不存在

如果配置的日志通道不存在，系统会静默失败，不会影响 MQTT 连接。

### 日志文件权限

确保 Laravel 有权限写入日志文件：

```bash
chmod -R 775 storage/logs
```

### 日志级别设置

根据环境调整日志级别：

- **生产环境**: `level => 'error'`
- **开发环境**: `level => 'debug'`
- **测试环境**: `level => 'warning'`
