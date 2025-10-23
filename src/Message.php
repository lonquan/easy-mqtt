<?php

declare(strict_types=1);

namespace EasyMqtt;

use ArrayAccess;
use JsonException;
use JsonSerializable;
use EasyMqtt\Exceptions\PublishException;
use EasyMqtt\Exceptions\ConfigurationException;

/**
 * MQTT 消息封装类
 *
 * 双重角色：
 * 1. 接收消息：从订阅中接收的消息（包含原始内容、JSON 解析）
 * 2. 发送消息：构建要发布的消息（指定 connection 和 topic）
 *
 * 提供 Fluent API 风格的消息访问和构建
 * 实现 ArrayAccess 和 JsonSerializable 接口
 *
 * @implements ArrayAccess<string, mixed>
 */
class Message implements ArrayAccess, JsonSerializable
{
    /**
     * 原始消息内容
     */
    private readonly string $raw;

    /**
     * 解析后的数据（JSON 消息）
     *
     * @var array<string, mixed>|null
     */
    private readonly ?array $data;

    /**
     * 是否为 JSON 消息
     */
    private readonly bool $isJson;

    /**
     * 连接名称（用于发送）
     */
    private ?string $connection;

    /**
     * 主题名称（用于发送/接收）
     */
    private ?string $topic;

    /**
     * 构造函数
     *
     * 支持两种用途：
     * 1. 订阅接收：传入 string（原始消息），自动尝试 JSON 解码
     * 2. 发布构建：传入 array（消息数据），自动编码为 JSON
     *
     * @param  array<string, mixed>|string  $message  消息内容（array 或 string）
     * @param  string|null  $topic  主题
     *
     * @throws JsonException
     */
    public function __construct(array|string $message, ?string $topic = null)
    {
        $this->topic = $topic;
        $this->connection = null;

        // 如果是数组，编码为 JSON（发布场景）
        if (is_array($message)) {
            $this->raw = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->data = $message;
            $this->isJson = true;
        } else {
            // 如果是字符串，尝试 JSON 解码（订阅场景）
            $this->raw = $message;

            if (! empty($message)) {
                $decoded = json_decode($message, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->data = $decoded;
                    $this->isJson = true;
                } else {
                    $this->data = null;
                    $this->isJson = false;
                }
            } else {
                $this->data = null;
                $this->isJson = false;
            }
        }
    }

    /**
     * 创建用于发送的消息（静态工厂方法）
     *
     * @param  array<string, mixed>|string  $content  消息内容
     * @param  string  $topic  主题
     * @param  string  $connection  连接名称
     *
     * @throws JsonException
     */
    public static function make(array|string $content, string $topic, string $connection = 'default'): self
    {
        $instance = new self($content, $topic);
        $instance->connection = $connection;

        return $instance;
    }

    /**
     * 设置使用的连接（Fluent API，修改当前实例）
     *
     * @param  string  $connection  连接名称
     */
    public function useConnection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * 设置发送到的主题（Fluent API，修改当前实例）
     *
     * @param  string  $topic  主题
     */
    public function toTopic(string $topic): self
    {
        $this->topic = $topic;

        return $this;
    }

    /**
     * 发送消息（需要先设置 topic 和 connection）
     *
     * @param  int  $qos  QoS 级别（0, 1, 2）
     * @param  bool  $retain  是否保留消息
     *
     * @throws PublishException
     * @throws ConfigurationException
     */
    public function send(int $qos = 0, bool $retain = false): void
    {
        if ($this->topic === null) {
            throw new \InvalidArgumentException('Topic must be set before sending. Use toTopic() or publishTo() method.');
        }

        // 需要通过容器获取 Factory 实例
        $factory = app('mqtt-factory');
        $factory->publish($this, null, $this->connection ?? 'default', $qos, $retain);
    }

    /**
     * 发布到指定主题（一步到位）
     *
     * @param  string  $topic  主题
     * @param  string  $connection  连接名称
     * @param  int  $qos  QoS 级别（0, 1, 2）
     * @param  bool  $retain  是否保留消息
     *
     * @throws PublishException
     * @throws ConfigurationException
     */
    public function publishTo(string $topic, string $connection = 'default', int $qos = 0, bool $retain = false): void
    {
        $this->topic = $topic;
        $this->connection = $connection;

        // 需要通过容器获取 Factory 实例
        $factory = app('mqtt-factory');
        $factory->publish($this, null, $connection, $qos, $retain);
    }

    /**
     * 获取连接名称
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * 获取主题
     */
    public function getTopic(): ?string
    {
        return $this->topic;
    }

    /**
     * 检查消息是否为 JSON
     */
    public function isJson(): bool
    {
        return $this->isJson;
    }

    /**
     * 检查消息是否为空
     */
    public function isEmpty(): bool
    {
        return empty($this->raw);
    }

    /**
     * 获取原始消息内容
     */
    public function raw(): string
    {
        return $this->raw;
    }

    /**
     * 获取指定键的值（仅 JSON 消息）
     *
     * 支持点号语法访问嵌套字段，例如 'device.id'
     *
     * @param  string  $key  键名（支持点号语法）
     * @param  mixed  $default  默认值（键不存在时返回）
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->isJson || $this->data === null) {
            return $default;
        }

        // 支持点号语法
        if (str_contains($key, '.')) {
            return $this->getNestedValue($this->data, $key, $default);
        }

        return $this->data[$key] ?? $default;
    }

    /**
     * 获取所有数据（仅 JSON 消息）
     *
     * @return array<string, mixed>|null
     */
    public function all(): ?array
    {
        return $this->data;
    }

    /**
     * 检查键是否存在（仅 JSON 消息）
     *
     * 支持点号语法，例如 'device.id'
     *
     * @param  string  $key  键名（支持点号语法）
     */
    public function has(string $key): bool
    {
        if (! $this->isJson || $this->data === null) {
            return false;
        }

        // 支持点号语法
        if (str_contains($key, '.')) {
            return $this->hasNestedKey($this->data, $key);
        }

        return array_key_exists($key, $this->data);
    }

    /**
     * 魔术方法：属性访问
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * 魔术方法：检查属性是否存在
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * 魔术方法：转换为字符串
     */
    public function __toString(): string
    {
        return $this->raw;
    }

    /**
     * ArrayAccess: 检查偏移量是否存在
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * ArrayAccess: 获取偏移量的值
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * ArrayAccess: 设置偏移量的值（禁用，只读）
     *
     *
     * @throws \BadMethodCallException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('Message is read-only and cannot be modified');
    }

    /**
     * ArrayAccess: 删除偏移量（禁用，只读）
     *
     *
     * @throws \BadMethodCallException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Message is read-only and cannot be modified');
    }

    /**
     * JsonSerializable: 序列化为 JSON
     */
    public function jsonSerialize(): mixed
    {
        if ($this->isJson) {
            return $this->data;
        }

        return $this->raw;
    }

    /**
     * 获取嵌套值（点号语法支持）
     *
     * @param  array<string, mixed>  $data
     */
    private function getNestedValue(array $data, string $key, mixed $default): mixed
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * 检查嵌套键是否存在（点号语法支持）
     *
     * @param  array<string, mixed>  $data
     */
    private function hasNestedKey(array $data, string $key): bool
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }
}
