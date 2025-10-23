# MQTT Factory Hook 系统

MQTT Factory 包含一个灵活且强大的 Hook 系统，允许在 MQTT 生命周期的不同阶段自定义行为。Hook 使用闭包注册，可以在运行时动态添加或移除。

## 特性

- 🎯 **四种 Hook 类型**：Loop、Publish、MessageReceived、Connected
- 🔧 **动态管理**：运行时添加、移除、启用、禁用 Hook
- 🛡️ **异常安全**：每个 Hook 都在 try-catch 块中执行，确保单个异常不会崩溃循环
- 🆔 **唯一标识**：每个 Hook 都有唯一 ID，支持自定义 ID
- 📊 **完整访问**：所有 Hook 都接收 MQTT 客户端实例作为第一个参数

## Hook 类型

### 1. Loop Event Hook

在 MQTT 客户端循环的每次迭代中调用，特别适用于实现超时或死锁预防逻辑。

```php
$factory->registerLoopEventHandler(function (MqttClient $mqtt, float $elapsedTime) {
    echo "MQTT 客户端已运行 {$elapsedTime} 秒";
    
    // 实现超时逻辑
    if ($elapsedTime > 30) {
        echo "警告：运行时间过长";
    }
}, 'timeout-monitor');
```

### 2. Publish Event Hook

每次向 broker 发布消息时触发，适用于实现集中式日志记录或指标收集。

```php
$factory->registerPublishEventHandler(function (
    MqttClient $mqtt,
    string $topic,
    string $message,
    ?int $messageId,
    int $qualityOfService,
    bool $retain
) {
    echo "发布消息到 [{$topic}]: {$message}";
    echo "QoS: {$qualityOfService}, 保留: " . ($retain ? '是' : '否');
}, 'publish-logger');
```

### 3. Message Received Hook

当从 broker 接收到消息时执行，适用于实现集中式日志记录或指标收集。

```php
$factory->registerMessageReceivedEventHandler(function (
    MqttClient $mqtt,
    string $topic,
    string $message,
    int $qualityOfService,
    bool $retained
) {
    echo "接收到消息 [{$topic}]: {$message}";
    echo "QoS: {$qualityOfService}, 保留: " . ($retained ? '是' : '否');
}, 'message-logger');
```

### 4. Connected Hook

当客户端连接到 broker 时调用（初始连接或自动重连）。

```php
$factory->registerConnectedEventHandler(function (MqttClient $mqtt, bool $isAutoReconnect) {
    if ($isAutoReconnect) {
        echo "自动重连成功！";
    } else {
        echo "首次连接成功！";
    }
}, 'connection-monitor');
```

## Hook 管理

### 注册 Hook

```php
// 使用闭包注册
$handler = $factory->registerLoopEventHandler(function ($mqtt, $elapsedTime) {
    // Hook 逻辑
}, 'custom-id');

// 使用 Handler 实例注册
$handler = new LoopEventHandler(function ($mqtt, $elapsedTime) {
    // Hook 逻辑
}, 'custom-id');
$factory->registerLoopEventHandler($handler);
```

### 注销 Hook

```php
// 注销特定的 Hook
$factory->unregisterLoopEventHandler($handler);

// 注销所有同类型的 Hook
$factory->unregisterLoopEventHandler();
```

### 启用/禁用 Hook

```php
// 禁用 Hook
$handler->setEnabled(false);

// 重新启用 Hook
$handler->setEnabled(true);

// 检查状态
if ($handler->isEnabled()) {
    echo "Hook 已启用";
}
```

### 获取 Hook 信息

```php
// 获取 Hook 管理器
$hookManager = $factory->getHookManager();

// 获取所有 Hook
$allHooks = $hookManager->getAllHooks();

// 获取 Hook ID
$hookId = $handler->getId();
```

## 实际使用示例

```php
<?php

use EasyMqtt\Factory;

$config = [
    'default' => 'local',
    'connections' => [
        'local' => [
            'host' => '127.0.0.1',
            'port' => 1883,
        ],
    ],
];

$factory = new Factory($config);

// 注册各种 Hook
$factory->registerLoopEventHandler(function ($mqtt, $elapsedTime) {
    if ($elapsedTime > 10) {
        echo "运行时间: {$elapsedTime} 秒\n";
    }
}, 'runtime-monitor');

$factory->registerPublishEventHandler(function ($mqtt, $topic, $message, $messageId, $qos, $retain) {
    echo "发布到 [{$topic}]: {$message}\n";
}, 'publish-logger');

$factory->registerMessageReceivedEventHandler(function ($mqtt, $topic, $message, $qos, $retained) {
    echo "接收到 [{$topic}]: {$message}\n";
}, 'message-logger');

$factory->registerConnectedEventHandler(function ($mqtt, $isAutoReconnect) {
    echo ($isAutoReconnect ? "重连成功" : "连接成功") . "\n";
}, 'connection-logger');

// 现在使用 MQTT 客户端，所有 Hook 都会自动执行
$mqtt = $factory->make();
$mqtt->publish('test/topic', 'Hello World');
```

## 最佳实践

1. **异常处理**：Hook 中的异常会被自动捕获，不会影响 MQTT 客户端运行
2. **性能考虑**：避免在 Hook 中执行耗时操作，以免影响 MQTT 性能
3. **日志记录**：使用 Hook 进行集中式日志记录和监控
4. **资源管理**：及时注销不再需要的 Hook 以释放资源
5. **ID 命名**：使用有意义的 Hook ID 便于管理和调试

## 注意事项

- 所有 Hook 都在 try-catch 块中执行，确保单个异常不会崩溃循环或 Hook 处理
- Hook 可以动态添加或移除
- 每个 Hook 都可以启用或禁用
- Hook 接收 MQTT 客户端实例作为第一个参数，允许完全访问客户端功能
- Hook 的执行顺序与注册顺序相同
