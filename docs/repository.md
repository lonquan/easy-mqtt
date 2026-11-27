# Repository 功能详细说明

Laravel MQTT Factory 支持自定义 Repository 实现，用于管理 MQTT 消息 ID、订阅和待处理消息的存储。

## Repository 接口

Repository 必须实现 `PhpMqtt\Client\Contracts\Repository` 接口，该接口提供以下功能：

- **消息 ID 管理** - 生成和管理唯一的消息标识符
- **订阅管理** - 存储和跟踪订阅信息
- **待处理消息管理** - 存储和跟踪未确认的消息

## 配置方式

### 1. 配置文件设置

在 `config/mqtt.php` 中配置 Repository：

```php
'connections' => [
    'default' => [
        'host' => 'localhost',
        'port' => 1883,
        
        // Repository 配置
        'repository' => [
            'class' => env('MQTT_REPOSITORY_CLASS', null),
        ],
    ],
],
```

### 2. 环境变量配置

在 `.env` 文件中设置：

```env
# Repository 配置
MQTT_REPOSITORY_CLASS=App\Repositories\CustomMqttRepository
```

## Repository 接口方法

### 消息 ID 管理

```php
public function newMessageId(): int;
public function countPendingOutgoingMessages(): int;
public function countPendingIncomingMessages(): int;
```

### 订阅管理

```php
public function addSubscription(Subscription $subscription): void;
public function removeSubscription(string $topicFilter): bool;
public function getSubscriptionsMatchingTopic(string $topicName): array;
public function countSubscriptions(): int;
```

### 待处理消息管理

```php
public function addPendingOutgoingMessage(PendingMessage $message): void;
public function addPendingIncomingMessage(PendingMessage $message): void;
public function removePendingOutgoingMessage(int $messageId): bool;
public function removePendingIncomingMessage(int $messageId): bool;
public function getPendingOutgoingMessage(int $messageId): ?PendingMessage;
public function getPendingIncomingMessage(int $messageId): ?PendingMessage;
```

### 状态管理

```php
public function reset(): void;
public function markPendingOutgoingPublishedMessageAsReceived(int $messageId): bool;
```

## 自定义 Repository 实现

### 基础实现

```php
<?php

namespace App\Repositories;

use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\PendingMessage;
use PhpMqtt\Client\Subscription;

class CustomMqttRepository implements Repository
{
    private int $nextMessageId = 1;
    private array $pendingOutgoingMessages = [];
    private array $pendingIncomingMessages = [];
    private array $subscriptions = [];

    public function __construct()
    {
        // 初始化 Repository
    }

    public function reset(): void
    {
        $this->nextMessageId = 1;
        $this->pendingOutgoingMessages = [];
        $this->pendingIncomingMessages = [];
        $this->subscriptions = [];
    }

    public function newMessageId(): int
    {
        return $this->nextMessageId++;
    }

    // 实现其他接口方法...
}
```

### 数据库 Repository 实现

```php
<?php

namespace App\Repositories;

use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\PendingMessage;
use PhpMqtt\Client\Subscription;
use Illuminate\Support\Facades\DB;

class DatabaseMqttRepository implements Repository
{
    public function __construct()
    {
        // 使用 Laravel 数据库连接
    }

    public function reset(): void
    {
        DB::table('mqtt_pending_messages')->truncate();
        DB::table('mqtt_subscriptions')->truncate();
    }

    public function newMessageId(): int
    {
        return DB::table('mqtt_message_ids')
            ->insertGetId(['created_at' => now()]);
    }

    public function addSubscription(Subscription $subscription): void
    {
        DB::table('mqtt_subscriptions')
            ->updateOrInsert(
                ['topic_filter' => $subscription->getTopicFilter()],
                [
                    'qos' => $subscription->getQualityOfService(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
    }

    // 实现其他接口方法...
}
```

### Redis Repository 实现

```php
<?php

namespace App\Repositories;

use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\PendingMessage;
use PhpMqtt\Client\Subscription;
use Illuminate\Support\Facades\Redis;

class RedisMqttRepository implements Repository
{
    public function __construct()
    {
        // 使用 Redis 存储
    }

    public function reset(): void
    {
        $keys = Redis::keys('mqtt:*');
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }

    public function newMessageId(): int
    {
        return Redis::incr('mqtt:message_id');
    }

    public function addSubscription(Subscription $subscription): void
    {
        Redis::hset(
            'mqtt:subscriptions',
            $subscription->getTopicFilter(),
            json_encode([
                'qos' => $subscription->getQualityOfService(),
                'created_at' => now()->toISOString(),
            ])
        );
    }

    // 实现其他接口方法...
}
```

## 使用示例

### 使用默认 Repository

```php
use EasyMqtt\Factory;

$config = [
    'default' => 'mqtt_default',
    'connections' => [
        'mqtt_default' => [
            'host' => 'localhost',
            'port' => 1883,
            // 没有 repository 配置，使用默认的 MemoryRepository
        ],
    ],
];

$factory = new Factory($config);
$mqtt = $factory->make();
```

### 使用自定义 Repository

```php
$config = [
    'default' => 'mqtt_custom',
    'connections' => [
        'mqtt_custom' => [
            'host' => 'localhost',
            'port' => 1883,
            'repository' => [
                'class' => 'App\Repositories\CustomMqttRepository',
            ],
        ],
    ],
];

$factory = new Factory($config);
$mqtt = $factory->make();
```

### 使用数据库 Repository

```php
$config = [
    'default' => 'mqtt_database',
    'connections' => [
        'mqtt_database' => [
            'host' => 'localhost',
            'port' => 1883,
            'repository' => [
                'class' => 'App\Repositories\DatabaseMqttRepository',
            ],
        ],
    ],
];
```

## 错误处理

### 类不存在异常

```php
throw ConfigurationException::invalidConfiguration(
    'repository',
    "Repository class 'NonExistentClass' does not exist"
);
```

### 接口实现异常

```php
throw ConfigurationException::invalidConfiguration(
    'repository',
    "Repository class 'InvalidClass' must implement PhpMqtt\Client\Contracts\Repository"
);
```

### 实例创建异常

```php
throw ConfigurationException::invalidConfiguration(
    'repository',
    "Failed to create repository instance: Constructor error message"
);
```

## 性能考虑

### 内存 Repository

- ✅ **优点**: 速度快，无外部依赖
- ❌ **缺点**: 数据不持久，重启后丢失

### 数据库 Repository

- ✅ **优点**: 数据持久，支持复杂查询
- ❌ **缺点**: 性能较慢，需要数据库连接

### Redis Repository

- ✅ **优点**: 性能好，数据持久
- ❌ **缺点**: 需要 Redis 服务

## 最佳实践

1. **选择合适的 Repository** - 根据应用需求选择内存、数据库或 Redis
2. **错误处理** - 实现完善的错误处理机制
3. **性能优化** - 使用连接池和缓存
4. **数据清理** - 定期清理过期的消息和订阅
5. **监控** - 监控 Repository 的性能和错误率

## 故障排除

### Repository 类不存在

检查类名是否正确，确保类文件已正确加载。

### 接口实现错误

确保 Repository 类实现了所有必需的方法。

### 构造函数参数错误

检查参数类型和数量是否正确。

### 数据库连接问题

确保数据库连接配置正确，连接可用。
