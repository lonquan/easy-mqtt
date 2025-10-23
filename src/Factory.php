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
     * @var array<string, MqttClient> 连接实例缓存
     */
    protected array $connections = [];

    /**
     * @param  callable(): array<string, mixed>|array<string, mixed>  $config
     */
    public function __construct(callable|array $config)
    {
        $this->config = is_array($config) ? $config : ($config)();
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
     */
    public function make(string $connection = 'default'): MqttClient
    {
        $actualConnection = $this->getActualConnectionName($connection);

        // 如果已经创建过该连接，检查连接状态
        if (isset($this->connections[$actualConnection])) {
            $mqtt = $this->connections[$actualConnection];

            // 检查连接是否仍然有效
            if (! $mqtt->isConnected()) {
                // 连接已断开，清除缓存，重新创建
                unset($this->connections[$actualConnection]);
            } else {
                return $mqtt;
            }
        }

        // 创建新连接并缓存
        $mqttClient = $this->createConnection($connection);
        $this->connections[$actualConnection] = $mqttClient;

        return $mqttClient;
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
                    throw PublishException::invalidTopic('', 'Message object must have a topic');
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
                $connection,
                $e->getMessage(),
            );
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
     * 中断 MQTT 客户端循环
     *
     * @param  string  $connection  连接名称
     *
     * @throws ConfigurationException
     * @throws ConnectionException
     */
    public function interrupt(string $connection = 'default'): void
    {
        try {
            // 获取连接实例
            $mqtt = $this->make($connection);

            // 调用 MQTT 客户端的 interrupt 方法
            $mqtt->interrupt();
        } catch (ConfigurationException|ConnectionException $e) {
            // 重新抛出自定义异常
            throw $e;
        } catch (Throwable $e) {
            // 包装其他异常为 ConnectionException
            throw new ConnectionException("Failed to interrupt MQTT client '{$connection}': {$e->getMessage()}");
        }
    }

    /**
     * 断开 MQTT 连接
     *
     * @param  string  $connection  连接名称
     *
     * @throws ConfigurationException
     * @throws ConnectionException
     */
    public function disconnect(string $connection = 'default'): void
    {
        try {
            $actualConnection = $this->getActualConnectionName($connection);

            // 如果缓存中有连接实例，先断开再清除缓存
            if (isset($this->connections[$actualConnection])) {
                $mqtt = $this->connections[$actualConnection];
                $mqtt->disconnect();
                // 断开后清除缓存
                unset($this->connections[$actualConnection]);
            }
        } catch (ConfigurationException|ConnectionException $e) {
            // 重新抛出自定义异常
            throw $e;
        } catch (Throwable $e) {
            // 包装其他异常为 ConnectionException
            throw new ConnectionException("Failed to disconnect from MQTT broker '{$connection}': {$e->getMessage()}");
        }
    }

    /**
     * 清除指定连接的缓存
     *
     * @param  string  $connection  连接名称
     */
    public function clearConnectionCache(string $connection = 'default'): void
    {
        $actualConnection = $this->getActualConnectionName($connection);
        unset($this->connections[$actualConnection]);
    }

    /**
     * 清除所有连接的缓存
     */
    public function clearAllConnectionsCache(): void
    {
        $this->connections = [];
    }

    /**
     * 检查连接是否已缓存
     *
     * @param  string  $connection  连接名称
     */
    public function hasConnectionCache(string $connection = 'default'): bool
    {
        $actualConnection = $this->getActualConnectionName($connection);

        return isset($this->connections[$actualConnection]);
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
     * 获取实际的配置名称
     */
    private function getActualConnectionName(string $connection): string
    {
        return $connection === 'default' ? $this->config['default'] : $connection;
    }

    /**
     * 创建实例
     *
     * @throws ProtocolNotSupportedException
     * @throws ConnectionException
     * @throws ConfigurationException
     * @throws Exception
     */
    private function createConnection(string $connection): MqttClient
    {
        $config = $this->getConnectionConfig($connection);

        $host = $config['host'];
        $port = isset($config['port']) && is_numeric($config['port']) ? (int) $config['port'] : 1883;
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

        // 直接连接，失败则抛出异常
        $this->connect($mqtt, $connection, $config);

        // 注册 Hook 到 MQTT 客户端
        $this->registerHooksToClient($mqtt);

        return $mqtt;
    }

    /**
     * 注册 Hook 到 MQTT 客户端
     */
    private function registerHooksToClient(MqttClient $mqtt): void
    {
        // 注册 Loop Event Handler
        $mqtt->registerLoopEventHandler(function (MqttClient $mqtt, float $elapsedTime) {
            $this->hookManager->executeLoopEventHandlers($mqtt, $elapsedTime);
        });

        // 注册 Publish Event Handler
        $mqtt->registerPublishEventHandler(function (
            MqttClient $mqtt,
            string $topic,
            string $message,
            ?int $messageId,
            int $qualityOfService,
            bool $retain
        ) {
            $this->hookManager->executePublishEventHandlers($mqtt, $topic, $message, $messageId, $qualityOfService,
                $retain);
        });

        // 注册 Message Received Event Handler
        $mqtt->registerMessageReceivedEventHandler(function (
            MqttClient $mqtt,
            string $topic,
            string $message,
            int $qualityOfService,
            bool $retained
        ) {
            $this->hookManager->executeMessageReceivedEventHandlers($mqtt, $topic, $message, $qualityOfService,
                $retained);
        });

        // 注册 Connected Event Handler
        $mqtt->registerConnectedEventHandler(function (MqttClient $mqtt, bool $isAutoReconnect) {
            $this->hookManager->executeConnectedEventHandlers($mqtt, $isAutoReconnect);
        });
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
                    "Repository class '{$className}' does not exist",
                );
            }

            // 检查类是否实现了 Repository 接口
            if (! is_subclass_of($className, Repository::class)) {
                throw ConfigurationException::invalidConfiguration(
                    'repository',
                    "Repository class '{$className}' must implement " . Repository::class,
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
                "Failed to create repository instance: {$e->getMessage()}",
            );
        }
    }

    /**
     * 直接连接 MQTT broker
     *
     * @param  array<string, mixed>  $config
     *
     * @throws Exceptions\ConnectionException
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

        try {
            $mqtt->connect(settings: $settings, useCleanSession: true);
        } catch (\Throwable $e) {
            throw new Exceptions\ConnectionException("Failed to connect to MQTT broker '{$name}' at {$config['host']}:" . ($config['port'] ?? 1883) . ": {$e->getMessage()}");
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
            throw ConfigurationException::invalidConfiguration('mqtt', 'MQTT configuration not found');
        }

        if (! isset($this->config['connections'][$connection])) {
            throw ConfigurationException::connectionNotFound($connection);
        }

        $config = $this->config['connections'][$connection];

        // 验证必需参数
        if (empty($config['host'])) {
            throw ConfigurationException::missingParameter($connection, 'host');
        }

        // 验证连接设置
        $this->validateConnectionSettings($connection, $config);

        return $config;
    }

    /**
     * 验证连接设置
     *
     * @param  string  $connection  连接名称
     * @param  array<string, mixed>  $config  连接配置
     *
     * @throws ConfigurationException
     */
    private function validateConnectionSettings(string $connection, array $config): void
    {
        $connectionSettings = $config['connection_settings'] ?? [];

        // 验证超时设置
        $this->validateTimeoutSetting($connection, $connectionSettings, 'connect_timeout', 300);
        $this->validateTimeoutSetting($connection, $connectionSettings, 'socket_timeout', 300);
        $this->validateTimeoutSetting($connection, $connectionSettings, 'resend_timeout', 300);
        $this->validateTimeoutSetting($connection, $connectionSettings, 'keep_alive_interval', 65535);

        // 验证重连设置
        if (isset($connectionSettings['max_reconnect_attempts'])) {
            $maxAttempts = $connectionSettings['max_reconnect_attempts'];
            if (! is_int($maxAttempts) || $maxAttempts < 0) {
                throw ConfigurationException::invalidConfiguration(
                    $connection,
                    "max_reconnect_attempts must be a non-negative integer, got: {$maxAttempts}",
                );
            }
        }

        if (isset($connectionSettings['delay_between_reconnect_attempts'])) {
            $delay = $connectionSettings['delay_between_reconnect_attempts'];
            if (! is_int($delay) || $delay < 0) {
                throw ConfigurationException::invalidConfiguration(
                    $connection,
                    "delay_between_reconnect_attempts must be a non-negative integer, got: {$delay}",
                );
            }
        }

        // 验证遗嘱消息设置
        $this->validateLastWillSettings($connection, $connectionSettings);

        // 验证 TLS 设置
        $this->validateTlsSettings($connection, $connectionSettings);
    }

    /**
     * 验证超时设置
     *
     * @param  string  $connection  连接名称
     * @param  array<string, mixed>  $connectionSettings  连接设置
     * @param  string  $settingName  设置名称
     * @param  int  $maxValue  最大值
     *
     * @throws ConfigurationException
     */
    private function validateTimeoutSetting(
        string $connection,
        array $connectionSettings,
        string $settingName,
        int $maxValue
    ): void {
        if (isset($connectionSettings[$settingName])) {
            $value = $connectionSettings[$settingName];
            if (! is_int($value) || $value < 1 || $value > $maxValue) {
                throw ConfigurationException::invalidConfiguration(
                    $connection,
                    "{$settingName} must be an integer between 1 and {$maxValue}, got: {$value}",
                );
            }
        }
    }

    /**
     * 验证遗嘱消息设置
     *
     * @param  string  $connection  连接名称
     * @param  array<string, mixed>  $connectionSettings  连接设置
     *
     * @throws ConfigurationException
     */
    private function validateLastWillSettings(string $connection, array $connectionSettings): void
    {
        $lastWill = $connectionSettings['last_will'] ?? [];

        if (empty($lastWill)) {
            return;
        }

        // 如果设置了遗嘱消息，必须同时设置主题和消息
        $hasTopic = ! empty($lastWill['topic']);
        $hasMessage = ! empty($lastWill['message']);

        if ($hasTopic && ! $hasMessage) {
            throw ConfigurationException::invalidConfiguration(
                $connection,
                'last_will.message is required when last_will.topic is set',
            );
        }

        if ($hasMessage && ! $hasTopic) {
            throw ConfigurationException::invalidConfiguration(
                $connection,
                'last_will.topic is required when last_will.message is set',
            );
        }

        // 验证 QoS 级别
        if (isset($lastWill['quality_of_service'])) {
            $qos = $lastWill['quality_of_service'];
            if (! is_int($qos) || $qos < 0 || $qos > 2) {
                throw ConfigurationException::invalidConfiguration(
                    $connection,
                    "last_will.quality_of_service must be 0, 1, or 2, got: {$qos}",
                );
            }
        }
    }

    /**
     * 验证 TLS 设置
     *
     * @param  string  $connection  连接名称
     * @param  array<string, mixed>  $connectionSettings  连接设置
     *
     * @throws ConfigurationException
     */
    private function validateTlsSettings(string $connection, array $connectionSettings): void
    {
        $tlsSettings = $connectionSettings['tls'] ?? [];

        if (empty($tlsSettings)) {
            return;
        }

        // 验证客户端证书设置
        $hasClientCert = ! empty($tlsSettings['client_certificate_file']);
        $hasClientKey = ! empty($tlsSettings['client_certificate_key_file']);

        if ($hasClientCert && ! $hasClientKey) {
            throw ConfigurationException::invalidConfiguration(
                $connection,
                'tls.client_certificate_key_file is required when tls.client_certificate_file is set',
            );
        }

        if ($hasClientKey && ! $hasClientCert) {
            throw ConfigurationException::invalidConfiguration(
                $connection,
                'tls.client_certificate_file is required when tls.client_certificate_key_file is set',
            );
        }

        // 验证证书文件路径
        $this->validateCertificateFile($connection, $tlsSettings, 'certificate_authority_file');
        $this->validateCertificateFile($connection, $tlsSettings, 'certificate_authority_path');
        $this->validateCertificateFile($connection, $tlsSettings, 'client_certificate_file');
        $this->validateCertificateFile($connection, $tlsSettings, 'client_certificate_key_file');
    }

    /**
     * 验证证书文件路径
     *
     * @param  string  $connection  连接名称
     * @param  array<string, mixed>  $tlsSettings  TLS 设置
     * @param  string  $settingName  设置名称
     *
     * @throws ConfigurationException
     */
    private function validateCertificateFile(string $connection, array $tlsSettings, string $settingName): void
    {
        if (isset($tlsSettings[$settingName])) {
            $filePath = $tlsSettings[$settingName];
            if (! is_string($filePath) || empty($filePath)) {
                throw ConfigurationException::invalidConfiguration(
                    $connection,
                    "{$settingName} must be a non-empty string, got: {$filePath}",
                );
            }

            // 对于文件路径，检查文件是否存在
            if (
                in_array($settingName, [
                    'certificate_authority_file', 'client_certificate_file', 'client_certificate_key_file',
                ])
            ) {
                if (! file_exists($filePath)) {
                    throw ConfigurationException::invalidConfiguration(
                        $connection,
                        "Certificate file does not exist: {$filePath}",
                    );
                }
            }

            // 对于目录路径，检查目录是否存在
            if ($settingName === 'certificate_authority_path') {
                if (! is_dir($filePath)) {
                    throw ConfigurationException::invalidConfiguration(
                        $connection,
                        "Certificate directory does not exist: {$filePath}",
                    );
                }
            }
        }
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
        if (empty($topic)) {
            throw PublishException::invalidTopic($topic, 'Topic cannot be empty');
        }

        // 发布主题不能包含通配符
        if (str_contains($topic, '#') || str_contains($topic, '+')) {
            throw PublishException::invalidTopic($topic, 'Publish topic cannot contain wildcards (# or +)');
        }

        // 主题不能以 $ 开头（系统主题）
        if (str_starts_with($topic, '$')) {
            throw PublishException::invalidTopic($topic, 'Publish topic cannot start with $ (system topic)');
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
        if (empty($topic)) {
            throw SubscriptionException::invalidTopic($topic, 'Topic cannot be empty');
        }

        // 验证通配符使用是否正确
        // 多层通配符 # 只能在末尾
        if (str_contains($topic, '#')) {
            if (! str_ends_with($topic, '#')) {
                throw SubscriptionException::invalidTopic(
                    $topic,
                    'Multi-level wildcard # can only be used at the end of the topic',
                );
            }

            // 检查 # 前面是否有 /（如果不是整个主题就是 #）
            if ($topic !== '#' && ! str_ends_with($topic, '/#')) {
                throw SubscriptionException::invalidTopic(
                    $topic,
                    'Multi-level wildcard # must be preceded by / (e.g., topic/#)',
                );
            }
        }

        // 单层通配符 + 必须占据完整的一层
        if (str_contains($topic, '+')) {
            $segments = explode('/', $topic);
            foreach ($segments as $segment) {
                if (str_contains($segment, '+') && $segment !== '+') {
                    throw SubscriptionException::invalidTopic(
                        $topic,
                        'Single-level wildcard + must occupy an entire level (e.g., topic/+/subtopic)',
                    );
                }
            }
        }
    }
}
