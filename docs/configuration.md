# 配置详细说明

Laravel MQTT Factory 提供了丰富的配置选项，支持多种连接方式和高级功能。

## 配置文件结构

```php
<?php

return [
    // 默认连接名称
    'default' => env('MQTT_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            // 基础连接配置
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => (int) env('MQTT_PORT', 1883),
            'username' => env('MQTT_USERNAME', null),
            'password' => env('MQTT_PASSWORD', null),
            'client_id_prefix' => env('MQTT_CLIENT_ID_PREFIX', 'app_'),
            'client_id' => env('MQTT_CLIENT_ID', null),

            // 日志配置
            'logging' => [
                'enabled' => env('MQTT_LOGGING_ENABLED', false),
                'channel' => env('MQTT_LOGGING_CHANNEL', 'mqtt'),
            ],

            // Repository 配置
            'repository' => [
                'class' => env('MQTT_REPOSITORY_CLASS', null),
                'parameters' => env('MQTT_REPOSITORY_PARAMETERS', []),
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

## 基础连接配置

### 必需配置

- **host** - MQTT broker 地址
- **port** - MQTT broker 端口

### 可选配置

- **username** - 用户名（如果需要认证）
- **password** - 密码（如果需要认证）
- **client_id_prefix** - 客户端 ID 前缀
- **client_id** - 固定客户端 ID

## 连接设置

### 套接字设置

```php
'connection_settings' => [
    'use_blocking_socket' => false,        // 是否使用阻塞套接字
    'connect_timeout' => 60,              // 连接超时时间（秒）
    'socket_timeout' => 5,                 // 套接字超时时间（秒）
    'resend_timeout' => 10,               // 重发超时时间（秒）
],
```

### 重连设置

```php
'connection_settings' => [
    'reconnect_automatically' => false,    // 是否自动重连
    'max_reconnect_attempts' => 3,        // 最大重连次数
    'delay_between_reconnect_attempts' => 0, // 重连间隔时间（秒）
    'keep_alive_interval' => 10,          // 心跳间隔时间（秒）
],
```

### 遗嘱消息

```php
'last_will' => [
    'topic' => 'device/offline',           // 遗嘱主题
    'message' => 'Device disconnected',   // 遗嘱消息
    'quality_of_service' => 0,            // QoS 级别
    'retain' => false,                     // 是否保留消息
],
```

## TLS 配置

### 基础 TLS 设置

```php
'tls' => [
    'use_tls' => true,                     // 启用 TLS
    'verify_peer' => true,                 // 验证对等证书
    'verify_peer_name' => true,            // 验证对等名称
    'self_signed_allowed' => false,        // 允许自签名证书
],
```

### 证书配置

```php
'tls' => [
    'certificate_authority_file' => '/path/to/ca.crt',      // CA 证书文件
    'certificate_authority_path' => '/path/to/ca/',         // CA 证书目录
    'client_certificate_file' => '/path/to/client.crt',     // 客户端证书
    'client_certificate_key_file' => '/path/to/client.key', // 客户端私钥
    'client_certificate_key_passphrase' => 'password',     // 私钥密码
    'alpn' => 'mqtt',                                       // ALPN 协议
],
```

## 环境变量配置

### 基础配置

```env
# MQTT 连接配置
MQTT_CONNECTION=default
MQTT_HOST=localhost
MQTT_PORT=1883
MQTT_USERNAME=
MQTT_PASSWORD=
MQTT_CLIENT_ID_PREFIX=app_
MQTT_CLIENT_ID=
```

### 日志配置

```env
# 日志配置
MQTT_LOGGING_ENABLED=false
MQTT_LOGGING_CHANNEL=mqtt
```

### Repository 配置

```env
# Repository 配置
MQTT_REPOSITORY_CLASS=
MQTT_REPOSITORY_PARAMETERS=
```

### 连接设置

```env
# 连接设置
MQTT_USE_BLOCKING_SOCKET=false
MQTT_CONNECT_TIMEOUT=60
MQTT_SOCKET_TIMEOUT=5
MQTT_RESEND_TIMEOUT=10
MQTT_RECONNECT_AUTOMATICALLY=false
MQTT_MAX_RECONNECT_ATTEMPTS=3
MQTT_DELAY_BETWEEN_RECONNECT_ATTEMPTS=0
MQTT_KEEP_ALIVE_INTERVAL=10
```

### 遗嘱消息配置

```env
# 遗嘱消息
MQTT_LAST_WILL_TOPIC=
MQTT_LAST_WILL_MESSAGE=
MQTT_LAST_WILL_QOS=0
MQTT_RETAIN_LAST_WILL=false
```

### TLS 配置

```env
# TLS 配置
MQTT_USE_TLS=false
MQTT_TLS_VERIFY_PEER=true
MQTT_TLS_VERIFY_PEER_NAME=true
MQTT_TLS_SELF_SIGNED_ALLOWED=false
MQTT_TLS_CA_FILE=
MQTT_TLS_CA_PATH=
MQTT_TLS_CLIENT_CERT_FILE=
MQTT_TLS_CLIENT_KEY_FILE=
MQTT_TLS_CLIENT_KEY_PASSPHRASE=
MQTT_TLS_ALPN=
```

## 多连接配置

```php
'connections' => [
    'local' => [
        'host' => '127.0.0.1',
        'port' => 1883,
        'username' => 'local_user',
        'password' => 'local_pass',
    ],
    
    'production' => [
        'host' => 'mqtt.example.com',
        'port' => 8883,
        'username' => 'prod_user',
        'password' => 'prod_pass',
        'connection_settings' => [
            'use_tls' => true,
            'verify_peer' => true,
        ],
    ],
    
    'test' => [
        'host' => 'test.mqtt.example.com',
        'port' => 1883,
        'username' => 'test_user',
        'password' => 'test_pass',
        'logging' => [
            'enabled' => true,
            'channel' => 'mqtt_test',
        ],
    ],
],
```

## 配置验证

### 必需字段检查

```php
// 检查必需配置
if (empty($config['host'])) {
    throw new ConfigurationException::missingParameter('host');
}

if (empty($config['port'])) {
    throw new ConfigurationException::missingParameter('port');
}
```

### 类型验证

```php
// 验证端口号
if (!is_numeric($config['port']) || $config['port'] < 1 || $config['port'] > 65535) {
    throw new ConfigurationException::invalidConfiguration('port', 'Invalid port number');
}

// 验证超时时间
if (isset($config['connect_timeout']) && $config['connect_timeout'] < 0) {
    throw new ConfigurationException::invalidConfiguration('connect_timeout', 'Timeout must be positive');
}
```

## 配置最佳实践

### 1. 环境分离

```php
// 开发环境
'local' => [
    'host' => 'localhost',
    'port' => 1883,
    'logging' => ['enabled' => true],
],

// 生产环境
'production' => [
    'host' => 'mqtt.example.com',
    'port' => 8883,
    'connection_settings' => [
        'use_tls' => true,
        'verify_peer' => true,
    ],
],
```

### 2. 安全配置

```php
// 使用环境变量存储敏感信息
'username' => env('MQTT_USERNAME'),
'password' => env('MQTT_PASSWORD'),
```

### 3. 性能优化

```php
// 根据网络环境调整超时时间
'connection_settings' => [
    'connect_timeout' => env('MQTT_CONNECT_TIMEOUT', 60),
    'socket_timeout' => env('MQTT_SOCKET_TIMEOUT', 5),
],
```

### 4. 监控配置

```php
// 启用日志记录
'logging' => [
    'enabled' => env('MQTT_LOGGING_ENABLED', true),
    'channel' => env('MQTT_LOGGING_CHANNEL', 'mqtt'),
],
```

## 故障排除

### 连接失败

1. 检查 host 和 port 配置
2. 验证网络连接
3. 检查防火墙设置
4. 确认 MQTT broker 状态

### 认证失败

1. 检查 username 和 password
2. 验证用户权限
3. 确认认证方式

### TLS 连接问题

1. 检查证书文件路径
2. 验证证书有效性
3. 确认 TLS 版本兼容性
4. 检查证书链完整性

### 配置错误

1. 检查配置文件语法
2. 验证环境变量
3. 确认配置项类型
4. 查看错误日志
