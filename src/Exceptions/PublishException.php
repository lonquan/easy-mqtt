<?php

declare(strict_types=1);

namespace EasyMqtt\Exceptions;

/**
 * 消息发布相关异常
 *
 * 当消息发布失败时抛出
 * 例如：主题验证失败、消息序列化失败、发布超时等
 */
class PublishException extends MqttException
{
    /**
     * 创建无效主题异常
     */
    public static function invalidTopic(string $topic, string $reason): static
    {
        return static::withContext(
            "无效的发布主题 '{$topic}': {$reason}",
            ['topic' => $topic, 'reason' => $reason]
        );
    }

    /**
     * 创建消息序列化失败异常
     */
    public static function serializationFailed(mixed $message, string $reason): static
    {
        return static::withContext(
            "消息序列化失败: {$reason}",
            ['message_type' => gettype($message), 'reason' => $reason]
        );
    }

    /**
     * 创建发布失败异常
     */
    public static function publishFailed(string $topic, string $connectionName, string $reason): static
    {
        return static::withContext(
            "发布消息到主题 '{$topic}' 失败（连接 '{$connectionName}'）: {$reason}",
            [
                'topic' => $topic,
                'connection' => $connectionName,
                'reason' => $reason,
            ]
        );
    }

    /**
     * 创建发布超时异常
     */
    public static function publishTimeout(string $topic, string $connectionName, int $timeout): static
    {
        return static::withContext(
            "发布主题 '{$topic}' 超时（连接 '{$connectionName}'，{$timeout} 秒）",
            [
                'topic' => $topic,
                'connection' => $connectionName,
                'timeout' => $timeout,
            ]
        );
    }
}
