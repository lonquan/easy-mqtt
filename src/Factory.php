<?php

declare(strict_types=1);

namespace EasyMqtt;

use Exception;
use Throwable;
use Psr\Log\LoggerInterface;
use PhpMqtt\Client\MqttClient;
use EasyMqtt\Support\HookManager;
use Illuminate\Support\Facades\Log;
use EasyMqtt\Support\LoopEventHandler;
use PhpMqtt\Client\ConnectionSettings;
use EasyMqtt\Support\ClientIdGenerator;
use EasyMqtt\Support\MessageSerializer;
use PhpMqtt\Client\Contracts\Repository;
use EasyMqtt\Exceptions\PublishException;
use EasyMqtt\Support\PublishEventHandler;
use EasyMqtt\Support\ConnectedEventHandler;
use EasyMqtt\Exceptions\ConnectionException;
use EasyMqtt\Exceptions\SubscriptionException;
use EasyMqtt\Exceptions\ConfigurationException;
use EasyMqtt\Contracts\LoopEventHandlerInterface;
use EasyMqtt\Support\MessageReceivedEventHandler;
use EasyMqtt\Contracts\PublishEventHandlerInterface;
use EasyMqtt\Contracts\ConnectedEventHandlerInterface;
use EasyMqtt\Contracts\MessageReceivedEventHandlerInterface;
use PhpMqtt\Client\Exceptions\ProtocolNotSupportedException;

use function is_array;

// 信号常量定义（如果 pcntl 扩展未加载，定义默认值）
if (! defined('SIGTERM')) {
    define('SIGTERM', 15);
}
if (! defined('SIGINT')) {
    define('SIGINT', 2);
}
if (! defined('SIGQUIT')) {
    define('SIGQUIT', 3);
}

class Factory
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected HookManager $hookManager;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->hookManager = new HookManager;
    }

    /**
     * 获取 MQTT 配置
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 获取 MQTT 客户端实例
     *
     * @param  string  $connection  连接名称
     *
     * @throws ConfigurationException
     * @throws ConnectionException
     * @throws ProtocolNotSupportedException
     * @throws Exception
     */
    public function make(string $connection = 'default'): MqttClient
    {
        if ($connection === 'default' && isset($this->config['default']) && is_string($this->config['default'])) {
            $connection = $this->config['default'];
        }

        $config = $this->getConnectionConfig($connection);

        $host = $config['host'];
        $port = $this->resolvePort($config);
        $clientId = $this->resolveClientId($config);

        $logger = $this->resolveLogger($config);

        $repository = $this->resolveRepository($config);

        $mqtt = new MqttClient(
            host: $host,
            port: $port,
            clientId: $clientId,
            protocol: MqttClient::MQTT_3_1_1,
            repository: $repository,
            logger: $logger,
        );

        // 注册 Hook 到 MQTT 客户端
        $this->registerHooksToClient($mqtt, $connection);

        // 直接连接，失败则抛出异常
        $this->connect($mqtt, $connection, $config);

        return $mqtt;
    }

    /**
     * 发布消息
     *
     * 支持两种方式：
     * 1. 直接指定 topic 和 message
     * 2. 传入 Message 对象（会从 Message 中提取 topic 和 connection）
     *
     * @param  string|Message  $topic  主题或 Message 对象
     * @param  array<string, mixed>|string|null  $message  消息内容（当 $topic 是 Message 对象时忽略）
     * @param  string  $connection  连接名称（当 $topic 是 Message 对象时可被覆盖）
     * @param  int  $qos  QoS 级别（0, 1, 2）
     * @param  bool  $retain  是否保留消息
     *
     * @throws ConfigurationException
     * @throws PublishException
     */
    public function publish(
        string|Message $topic,
        array|string|null $message = null,
        string $connection = 'default',
        int $qos = 0,
        bool $retain = false
    ): void {
        try {
            // 如果第一个参数是 Message 对象，从中提取信息
            if ($topic instanceof Message) {
                $messageObj = $topic;
                $actualTopic = $messageObj->getTopic();
                $actualConnection = $messageObj->getConnection() ?? $connection;
                $serializedMessage = $messageObj->raw();

                if ($actualTopic === null) {
                    throw PublishException::invalidTopic('', 'Message 对象必须包含主题');
                }
            } else {
                // 传统方式：直接指定 topic 和 message
                $actualTopic = $topic;
                $actualConnection = $connection;
                $serializedMessage = MessageSerializer::serialize($message);
            }

            // 验证发布主题
            self::validatePublishTopic($actualTopic);

            // 获取连接实例
            $mqtt = $this->make($actualConnection);

            // 发布消息
            $mqtt->publish(
                topic: $actualTopic,
                message: $serializedMessage,
                qualityOfService: $qos,
                retain: $retain,
            );
        } catch (ConfigurationException|PublishException $e) {
            // 重新抛出自定义异常
            throw $e;
        } catch (Throwable $e) {
            // 包装其他异常为 PublishException
            throw PublishException::publishFailed(
                $topic instanceof Message ? ($topic->getTopic() ?? 'unknown') : $topic,
                $topic instanceof Message ? ($topic->getConnection() ?? $connection) : $connection,
                $e->getMessage(),
            );
        }
    }

    /**
     * 批量发布消息
     *
     * 使用同一个 MQTT 连接实例批量发布多条消息。
     *
     * @param  array<int, Message|array<string, mixed>>  $messages  消息数组，每个元素可以是 Message 对象或包含 topic 和 message 的数组
     * @param  string  $connection  连接名称，默认为 'default'，所有消息使用同一个连接
     * @param  int  $qos  QoS 级别（0, 1, 2），应用于所有消息
     * @param  bool  $retain  是否保留消息，应用于所有消息
     *
     * @throws ConfigurationException
     * @throws PublishException
     */
    public function publishMany(
        array $messages,
        string $connection = 'default',
        int $qos = 0,
        bool $retain = false
    ): void {
        // 如果消息数组为空，静默返回
        if (empty($messages)) {
            return;
        }

        try {
            // 只调用一次 make 获取 MQTT 实例（所有消息共享同一个连接）
            $mqtt = $this->make($connection);

            // 遍历消息数组，处理每个消息
            foreach ($messages as $index => $messageItem) {
                $actualTopic = null;
                $serializedMessage = null;

                try {
                    // 如果元素是 Message 对象
                    if ($messageItem instanceof Message) {
                        $messageObj = $messageItem;
                        $actualTopic = $messageObj->getTopic();

                        if ($actualTopic === null) {
                            throw PublishException::invalidTopic('', "消息数组第 {$index} 项：Message 对象必须包含主题");
                        }

                        // 忽略 Message 对象中的 connection，使用统一的 $connection 参数
                        $serializedMessage = $messageObj->raw();
                    } elseif (is_array($messageItem)) {
                        // 验证数组格式，只允许 topic 和 message 字段
                        $allowedKeys = ['topic', 'message'];
                        $extraKeys = array_diff(array_keys($messageItem), $allowedKeys);
                        if (! empty($extraKeys)) {
                            throw PublishException::invalidTopic(
                                $messageItem['topic'] ?? '',
                                "消息数组第 {$index} 项：数组格式只支持 'topic' 和 'message' 字段，不支持: " . implode(', ',
                                    $extraKeys),
                            );
                        }

                        // 提取 topic
                        if (! isset($messageItem['topic']) || ! is_string($messageItem['topic'])) {
                            throw PublishException::invalidTopic('',
                                "消息数组第 {$index} 项：必须包含字符串类型的 'topic' 字段");
                        }

                        $actualTopic = $messageItem['topic'];

                        // 提取 message
                        $messageContent = $messageItem['message'] ?? null;
                        $serializedMessage = MessageSerializer::serialize($messageContent);
                    } else {
                        throw PublishException::invalidTopic(
                            '',
                            "消息数组第 {$index} 项：必须是 Message 对象或包含 'topic' 和 'message' 的数组",
                        );
                    }

                    // 验证发布主题
                    self::validatePublishTopic($actualTopic);

                    // 发布消息
                    $mqtt->publish(
                        topic: $actualTopic,
                        message: $serializedMessage,
                        qualityOfService: $qos,
                        retain: $retain,
                    );
                } catch (ConfigurationException|PublishException $e) {
                    // 重新抛出自定义异常
                    throw $e;
                } catch (Throwable $e) {
                    // 包装其他异常为 PublishException，包含当前处理的 topic
                    throw PublishException::publishFailed(
                        $actualTopic ?? '',
                        $connection,
                        "消息数组第 {$index} 项发布失败: {$e->getMessage()}",
                    );
                }
            }
        } catch (ConfigurationException|PublishException $e) {
            // 重新抛出自定义异常
            throw $e;
        } catch (Throwable $e) {
            // 包装其他异常为 PublishException
            throw PublishException::publishFailed('', $connection, $e->getMessage());
        }
    }

    /**
     * 订阅主题
     *
     * @param  string  $topic  订阅主题（支持通配符 + 和 #）
     * @param  callable  $handler  消息处理器 function(string $topic, Message $message): void
     * @param  string  $connection  连接名称
     * @param  int  $qos  QoS 级别（0, 1, 2）
     * @param  bool  $enableSignalHandler  是否启用信号处理器（处理 SIGTERM、SIGINT、SIGQUIT）
     *
     * @throws ConfigurationException
     * @throws SubscriptionException
     */
    public function subscribe(
        string $topic,
        callable $handler,
        string $connection = 'default',
        int $qos = 0,
        bool $enableSignalHandler = false
    ): void {
        try {
            // 验证订阅主题
            self::validateSubscribeTopic($topic);

            // 获取连接实例
            $mqtt = $this->make($connection);

            // 如果启用信号处理器，注册信号处理
            if ($enableSignalHandler) {
                $this->registerSignalHandlers($mqtt);
            }

            // 订阅主题，提供回调包装器
            $mqtt->subscribe(
                $topic,
                function ($topic, $message) use ($handler) {
                    try {
                        // 反序列化消息（实际上就是返回原始字符串）
                        $rawMessage = MessageSerializer::deserialize($message);

                        // 创建 Message 对象，传入 topic
                        $messageObject = new Message($rawMessage, $topic);

                        // 调用用户处理器
                        $handler($topic, $messageObject);
                    } catch (Throwable $e) {
                        // 捕获处理器异常，不中断订阅（由用户在handler中处理日志）
                        // 静默处理，避免中断订阅循环
                    }
                },
                $qos,
            );

            // 进入阻塞循环，处理接收到的消息
            $mqtt->loop();
        } catch (ConfigurationException|SubscriptionException $e) {
            // 重新抛出自定义异常
            throw $e;
        } catch (Throwable $e) {
            // 包装其他异常为 SubscriptionException
            throw SubscriptionException::subscribeFailed($topic, $connection, $e->getMessage());
        }
    }

    /**
     * 批量订阅主题
     *
     * 使用同一个 MQTT 连接实例批量订阅多个主题，所有主题共享同一个消息处理器。
     *
     * @param  array<int, string>  $topics  主题数组，每个元素是字符串主题名称
     * @param  callable  $handler  共享的消息处理器 function(string $topic, Message $message): void
     * @param  string  $connection  连接名称，默认为 'default'，所有订阅使用同一个连接
     * @param  int  $qos  QoS 级别（0, 1, 2），应用于所有订阅
     * @param  bool  $enableSignalHandler  是否启用信号处理器（处理 SIGTERM、SIGINT、SIGQUIT）
     *
     * @throws ConfigurationException
     * @throws SubscriptionException
     */
    public function subscribeMany(
        array $topics,
        callable $handler,
        string $connection = 'default',
        int $qos = 0,
        bool $enableSignalHandler = false
    ): void {
        // 如果主题数组为空，静默返回
        if (empty($topics)) {
            return;
        }

        try {
            // 只调用一次 make 获取 MQTT 实例（所有订阅共享同一个连接）
            $mqtt = $this->make($connection);

            // 如果启用信号处理器，注册一次
            if ($enableSignalHandler) {
                $this->registerSignalHandlers($mqtt);
            }

            // 遍历主题数组，注册每个订阅
            foreach ($topics as $index => $topic) {
                try {
                    // 验证主题是字符串类型
                    if (! is_string($topic)) {
                        throw SubscriptionException::invalidTopic('', "主题数组第 {$index} 项：必须是字符串类型");
                    }

                    // 验证订阅主题格式
                    self::validateSubscribeTopic($topic);

                    // 订阅主题，提供回调包装器（复用单个 subscribe 的逻辑）
                    $mqtt->subscribe(
                        $topic,
                        function ($receivedTopic, $message) use ($handler) {
                            try {
                                // 反序列化消息（实际上就是返回原始字符串）
                                $rawMessage = MessageSerializer::deserialize($message);

                                // 创建 Message 对象，传入 topic
                                $messageObject = new Message($rawMessage, $receivedTopic);

                                // 调用用户处理器
                                $handler($receivedTopic, $messageObject);
                            } catch (Throwable $e) {
                                // 捕获处理器异常，不中断订阅（由用户在handler中处理日志）
                                // 静默处理，避免中断订阅循环
                            }
                        },
                        $qos,
                    );
                } catch (ConfigurationException|SubscriptionException $e) {
                    // 重新抛出自定义异常
                    throw $e;
                } catch (Throwable $e) {
                    // 包装其他异常为 SubscriptionException，包含当前处理的 topic
                    throw SubscriptionException::subscribeFailed(
                        $topic ?? '',
                        $connection,
                        "主题数组第 {$index} 项订阅失败: {$e->getMessage()}",
                    );
                }
            }

            // 所有订阅完成后，进入阻塞循环，处理接收到的消息
            $mqtt->loop();
        } catch (ConfigurationException|SubscriptionException $e) {
            // 重新抛出自定义异常
            throw $e;
        } catch (Throwable $e) {
            // 包装其他异常为 SubscriptionException
            throw SubscriptionException::subscribeFailed('', $connection, $e->getMessage());
        }
    }

    /**
     * 中断 MQTT 客户端循环
     *
     * @param  MqttClient  $mqtt  MQTT 客户端实例
     *
     * @throws ConnectionException
     */
    public function interrupt(MqttClient $mqtt): void
    {
        try {
            $mqtt->interrupt();
        } catch (Throwable $e) {
            throw new ConnectionException("中断 MQTT 客户端失败: {$e->getMessage()}");
        }
    }

    /**
     * 断开 MQTT 连接
     *
     * @param  MqttClient  $mqtt  MQTT 客户端实例
     *
     * @throws ConnectionException
     */
    public function disconnect(MqttClient $mqtt): void
    {
        try {
            $mqtt->disconnect();
        } catch (Throwable $e) {
            throw new ConnectionException("断开 MQTT broker 连接失败: {$e->getMessage()}");
        }
    }

    /**
     * 注册 Loop Event Handler
     *
     * @param  string|null  $id  可选的 Hook ID
     */
    public function registerLoopEventHandler(
        callable|LoopEventHandlerInterface $handler,
        ?string $id = null
    ): LoopEventHandlerInterface {
        if (is_callable($handler)) {
            $handler = new LoopEventHandler($handler, $id);
        }

        $this->hookManager->registerLoopEventHandler($handler);

        return $handler;
    }

    /**
     * 注销 Loop Event Handler
     *
     * @param  LoopEventHandlerInterface|null  $handler  指定要注销的 handler，null 表示注销所有
     */
    public function unregisterLoopEventHandler(?LoopEventHandlerInterface $handler = null): void
    {
        $this->hookManager->unregisterLoopEventHandler($handler);
    }

    /**
     * 注册 Publish Event Handler
     *
     * @param  string|null  $id  可选的 Hook ID
     */
    public function registerPublishEventHandler(
        callable|PublishEventHandlerInterface $handler,
        ?string $id = null
    ): PublishEventHandlerInterface {
        if (is_callable($handler)) {
            $handler = new PublishEventHandler($handler, $id);
        }

        $this->hookManager->registerPublishEventHandler($handler);

        return $handler;
    }

    /**
     * 注销 Publish Event Handler
     *
     * @param  PublishEventHandlerInterface|null  $handler  指定要注销的 handler，null 表示注销所有
     */
    public function unregisterPublishEventHandler(?PublishEventHandlerInterface $handler = null): void
    {
        $this->hookManager->unregisterPublishEventHandler($handler);
    }

    /**
     * 注册 Message Received Event Handler
     *
     * @param  string|null  $id  可选的 Hook ID
     */
    public function registerMessageReceivedEventHandler(
        callable|MessageReceivedEventHandlerInterface $handler,
        ?string $id = null
    ): MessageReceivedEventHandlerInterface {
        if (is_callable($handler)) {
            $handler = new MessageReceivedEventHandler($handler, $id);
        }

        $this->hookManager->registerMessageReceivedEventHandler($handler);

        return $handler;
    }

    /**
     * 注销 Message Received Event Handler
     *
     * @param  MessageReceivedEventHandlerInterface|null  $handler  指定要注销的 handler，null 表示注销所有
     */
    public function unregisterMessageReceivedEventHandler(?MessageReceivedEventHandlerInterface $handler = null): void
    {
        $this->hookManager->unregisterMessageReceivedEventHandler($handler);
    }

    /**
     * 注册 Connected Event Handler
     *
     * @param  string|null  $id  可选的 Hook ID
     */
    public function registerConnectedEventHandler(
        callable|ConnectedEventHandlerInterface $handler,
        ?string $id = null
    ): ConnectedEventHandlerInterface {
        if (is_callable($handler)) {
            $handler = new ConnectedEventHandler($handler, $id);
        }

        $this->hookManager->registerConnectedEventHandler($handler);

        return $handler;
    }

    /**
     * 注销 Connected Event Handler
     *
     * @param  ConnectedEventHandlerInterface|null  $handler  指定要注销的 handler，null 表示注销所有
     */
    public function unregisterConnectedEventHandler(?ConnectedEventHandlerInterface $handler = null): void
    {
        $this->hookManager->unregisterConnectedEventHandler($handler);
    }

    /**
     * 获取 Hook 管理器实例
     */
    public function getHookManager(): HookManager
    {
        return $this->hookManager;
    }

    /**
     * 注册 Hook 到 MQTT 客户端
     */
    private function registerHooksToClient(MqttClient $mqtt, string $connection): void
    {
        // 注册 Loop Event Handler
        $mqtt->registerLoopEventHandler(function (MqttClient $mqtt, float $elapsedTime) use ($connection) {
            $this->hookManager->executeLoopEventHandlers($mqtt, $elapsedTime, $connection);
        });

        // 注册 Publish Event Handler
        $mqtt->registerPublishEventHandler(function (
            MqttClient $mqtt,
            string $topic,
            string $message,
            ?int $messageId,
            int $qualityOfService,
            bool $retain
        ) use ($connection) {
            $this->hookManager->executePublishEventHandlers(
                $mqtt, $topic, $message, $messageId, $qualityOfService, $retain, $connection,
            );
        });

        // 注册 Message Received Event Handler
        $mqtt->registerMessageReceivedEventHandler(function (
            MqttClient $mqtt,
            string $topic,
            string $message,
            int $qualityOfService,
            bool $retained
        ) use ($connection) {
            $this->hookManager->executeMessageReceivedEventHandlers(
                $mqtt, $topic, $message, $qualityOfService, $retained, $connection,
            );
        });

        // 注册 Connected Event Handler
        $mqtt->registerConnectedEventHandler(function (MqttClient $mqtt, bool $isAutoReconnect) use ($connection) {
            $this->hookManager->executeConnectedEventHandlers($mqtt, $isAutoReconnect, $connection);
        });
    }

    /**
     * 解析端口
     *
     * @param  array<string, mixed>  $config
     */
    private function resolvePort(array $config): int
    {
        return isset($config['port']) && is_numeric($config['port']) ? (int) $config['port'] : 1883;
    }

    /**
     * 解析 Client ID
     *
     * @param  array<string, mixed>  $config
     *
     * @throws Exception
     */
    private function resolveClientId(array $config): string
    {
        // 如果配置中指定了 client_id，直接使用
        if (! empty($config['client_id']) && is_string($config['client_id'])) {
            return $config['client_id'];
        }

        // 否则，根据 client_id_prefix 生成
        $prefix = isset($config['client_id_prefix']) && is_string($config['client_id_prefix'])
            ? $config['client_id_prefix']
            : null;

        return ClientIdGenerator::generate($prefix);
    }

    /**
     * 解析日志器实例
     *
     * @param  array<string, mixed>  $config
     */
    private function resolveLogger(array $config): ?LoggerInterface
    {
        $loggingConfig = $config['logging'] ?? [];

        // 如果未启用日志记录，返回 null
        if (! ($loggingConfig['enabled'] ?? false)) {
            return null;
        }

        // 获取日志通道名称
        $channel = $loggingConfig['channel'] ?? 'mqtt';

        try {
            // 通过 Laravel 的 Log facade 获取指定通道的日志器
            return Log::channel($channel);
        } catch (Throwable $e) {
            // 如果获取日志通道失败，返回 null（静默失败）
            // 这样可以避免因为日志配置错误导致 MQTT 连接失败
            return null;
        }
    }

    /**
     * 解析 Repository 实例
     *
     * @param  array<string, mixed>  $config
     *
     * @throws ConfigurationException
     */
    private function resolveRepository(array $config): ?Repository
    {
        $repositoryConfig = $config['repository'] ?? [];

        // 如果没有配置 Repository 类，返回 null（使用默认的 MemoryRepository）
        if (empty($repositoryConfig['class'])) {
            return null;
        }

        $className = $repositoryConfig['class'];

        try {
            // 检查类是否存在
            if (! class_exists($className)) {
                throw ConfigurationException::invalidConfiguration(
                    'repository',
                    "Repository 类 '{$className}' 不存在",
                );
            }

            // 检查类是否实现了 Repository 接口
            if (! is_subclass_of($className, Repository::class)) {
                throw ConfigurationException::invalidConfiguration(
                    'repository',
                    "Repository 类 '{$className}' 必须实现 " . Repository::class,
                );
            }

            // 创建 Repository 实例
            return new $className;
        } catch (ConfigurationException $e) {
            // 重新抛出自定义异常
            throw $e;
        } catch (Throwable $e) {
            // 包装其他异常为 ConfigurationException
            throw ConfigurationException::invalidConfiguration(
                'repository',
                "创建 Repository 实例失败: {$e->getMessage()}",
            );
        }
    }

    /**
     * 直接连接 MQTT broker
     *
     * @param  array<string, mixed>  $config
     *
     * @throws ConnectionException
     *
     * @noinspection PhpExpressionResultUnusedInspection
     */
    private function connect(MqttClient $mqtt, string $name, array $config): void
    {
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $connectionSettings = $config['connection_settings'] ?? [];

        $settings = (new ConnectionSettings)
            ->setUsername(is_string($username) ? $username : null)
            ->setPassword(is_string($password) ? $password : null)
            ->useBlockingSocket($connectionSettings['use_blocking_socket'] ?? false)
            ->setConnectTimeout($connectionSettings['connect_timeout'] ?? 60)
            ->setSocketTimeout($connectionSettings['socket_timeout'] ?? 5)
            ->setResendTimeout($connectionSettings['resend_timeout'] ?? 10)
            ->setReconnectAutomatically($connectionSettings['reconnect_automatically'] ?? false)
            ->setMaxReconnectAttempts($connectionSettings['max_reconnect_attempts'] ?? 3)
            ->setDelayBetweenReconnectAttempts($connectionSettings['delay_between_reconnect_attempts'] ?? 0)
            ->setKeepAliveInterval($connectionSettings['keep_alive_interval'] ?? 10);

        // 配置遗嘱消息
        $lastWill = $connectionSettings['last_will'] ?? [];
        if (! empty($lastWill['topic']) && ! empty($lastWill['message'])) {
            $settings
                ->setLastWillTopic($lastWill['topic'])
                ->setLastWillMessage($lastWill['message'])
                ->setLastWillQualityOfService($lastWill['quality_of_service'] ?? 0)
                ->setRetainLastWill($lastWill['retain'] ?? false);
        }

        // 配置 TLS 设置
        $tlsSettings = $connectionSettings['tls'] ?? [];
        if (! empty($tlsSettings)) {
            $settings
                ->setUseTls($tlsSettings['use_tls'] ?? false)
                ->setTlsVerifyPeer($tlsSettings['verify_peer'] ?? true)
                ->setTlsVerifyPeerName($tlsSettings['verify_peer_name'] ?? true)
                ->setTlsSelfSignedAllowed($tlsSettings['self_signed_allowed'] ?? false);

            // CA 证书文件
            if (! empty($tlsSettings['certificate_authority_file'])) {
                $settings->setTlsCertificateAuthorityFile($tlsSettings['certificate_authority_file']);
            }

            // CA 证书目录
            if (! empty($tlsSettings['certificate_authority_path'])) {
                $settings->setTlsCertificateAuthorityPath($tlsSettings['certificate_authority_path']);
            }

            // 客户端证书文件
            if (! empty($tlsSettings['client_certificate_file'])) {
                $settings->setTlsClientCertificateFile($tlsSettings['client_certificate_file']);
            }

            // 客户端证书密钥文件
            if (! empty($tlsSettings['client_certificate_key_file'])) {
                $settings->setTlsClientCertificateKeyFile($tlsSettings['client_certificate_key_file']);
            }

            // 客户端证书密钥密码
            if (! empty($tlsSettings['client_certificate_key_passphrase'])) {
                $settings->setTlsClientCertificateKeyPassphrase($tlsSettings['client_certificate_key_passphrase']);
            }

            // TLS ALPN
            if (! empty($tlsSettings['alpn'])) {
                $settings->setTlsAlpn($tlsSettings['alpn']);
            }
        }

        // 从配置读取 use_clean_session，默认为 true
        $useCleanSession = $connectionSettings['use_clean_session'] ?? true;

        try {
            $mqtt->connect(settings: $settings, useCleanSession: $useCleanSession);
        } catch (Throwable $e) {
            throw new ConnectionException("连接 MQTT broker '{$name}' ({$config['host']}:" . ($config['port'] ?? 1883) . ") 失败: {$e->getMessage()}");
        }
    }

    /**
     * 获取连接配置
     *
     * @param  string  $connection  连接名称
     * @return array<string, mixed>
     *
     * @throws ConfigurationException
     */
    private function getConnectionConfig(string $connection): array
    {
        if (empty($this->config)) {
            throw ConfigurationException::invalidConfiguration('mqtt', '未找到 MQTT 配置');
        }

        if (! isset($this->config['connections'][$connection])) {
            throw ConfigurationException::connectionNotFound($connection);
        }

        $config = $this->config['connections'][$connection];

        // 验证必需参数
        if (empty($config['host'])) {
            throw ConfigurationException::missingParameter($connection, 'host');
        }

        // 验证 reconnect_automatically 和 use_clean_session 不能同时使用
        $connectionSettings = $config['connection_settings'] ?? [];
        $reconnectAutomatically = $connectionSettings['reconnect_automatically'] ?? false;
        $useCleanSession = $connectionSettings['use_clean_session'] ?? true;

        if ($reconnectAutomatically && $useCleanSession) {
            throw ConfigurationException::invalidConfiguration(
                $connection,
                '自动重连不能与清理会话同时使用。启用 reconnect_automatically 时，请将 use_clean_session 设置为 false。',
            );
        }

        return $config;
    }

    /**
     * 注册信号处理器
     *
     * @param  MqttClient  $mqtt  MQTT 客户端实例
     */
    private function registerSignalHandlers(MqttClient $mqtt): void
    {
        // 检查是否支持 pcntl 扩展
        if (! extension_loaded('pcntl')) {
            return;
        }

        // 注册 SIGTERM 信号处理器 请求程序正常终止（默认由 kill 命令发送）
        pcntl_signal(SIGTERM, function () use ($mqtt) {
            echo 'SIGTERM';
            $mqtt->interrupt();
        });

        // 注册 SIGINT 信号处理器 用户按下 Ctrl+C 发出，用于中断前台进程
        pcntl_signal(SIGINT, function () use ($mqtt) {
            echo 'SIGINT';
            $mqtt->interrupt();
        });

        // 注册 SIGQUIT 信号处理器 用户按下 Ctrl+\，会生成 core dump 后终止
        pcntl_signal(SIGQUIT, function () use ($mqtt) {
            echo 'SIGQUIT';
            $mqtt->interrupt();
        });
    }

    /**
     * 验证发布主题
     *
     * @throws PublishException
     */
    private static function validatePublishTopic(string $topic): void
    {
        // 主题不能为空
        if ($topic === '') {
            throw PublishException::invalidTopic($topic, '主题不能为空');
        }

        // 发布主题不能包含通配符
        if (str_contains($topic, '#') || str_contains($topic, '+')) {
            throw PublishException::invalidTopic($topic, '发布主题不能包含通配符 (# 或 +)');
        }

        // 主题不能以 $ 开头（系统主题）
        if (str_starts_with($topic, '$')) {
            throw PublishException::invalidTopic($topic, '发布主题不能以 $ 开头（系统主题）');
        }
    }

    /**
     * 验证订阅主题
     *
     * @throws SubscriptionException
     */
    private static function validateSubscribeTopic(string $topic): void
    {
        // 主题不能为空
        if ($topic === '') {
            throw SubscriptionException::invalidTopic($topic, '主题不能为空');
        }

        $segments = explode('/', $topic);
        $lastIndex = array_key_last($segments);

        foreach ($segments as $index => $segment) {
            if (str_contains($segment, '#') && ($segment !== '#' || $index !== $lastIndex)) {
                throw SubscriptionException::invalidTopic(
                    $topic,
                    '多层通配符 # 必须单独占据主题的最后一层（例如：topic/#）',
                );
            }

            if (str_contains($segment, '+') && $segment !== '+') {
                throw SubscriptionException::invalidTopic(
                    $topic,
                    '单层通配符 + 必须占据完整的一层（例如：topic/+/subtopic）',
                );
            }
        }
    }
}
