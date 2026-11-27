<?php

declare(strict_types=1);

namespace EasyMqtt\Exceptions;

/**
 * 订阅相关异常
 *
 * 当订阅失败或订阅主题验证失败时抛出
 * 例如：主题格式错误、订阅失败、通配符使用不当等
 */
class SubscriptionException extends MqttException
{
    /**
     * 创建无效订阅主题异常
     */
    public static function invalidTopic(string $topic, string $reason): static
    {
        return static::withContext(
            "无效的订阅主题 '{$topic}': {$reason}",
            ['topic' => $topic, 'reason' => $reason]
        );
    }

    /**
     * 创建订阅失败异常
     */
    public static function subscribeFailed(string $topic, string $connectionName, string $reason): static
    {
        return static::withContext(
            "订阅主题 '{$topic}' 失败（连接 '{$connectionName}'）: {$reason}",
            [
                'topic' => $topic,
                'connection' => $connectionName,
                'reason' => $reason,
            ]
        );
    }

    /**
     * 创建无效处理器异常
     */
    public static function invalidHandler(string $topic): static
    {
        return static::withContext(
            "主题 '{$topic}' 的订阅处理器无效: 处理器必须是可调用的",
            ['topic' => $topic]
        );
    }

    /**
     * 创建处理器执行异常
     */
    public static function handlerError(string $topic, \Throwable $error): static
    {
        return static::withContext(
            "主题 '{$topic}' 的订阅处理器执行错误: {$error->getMessage()}",
            [
                'topic' => $topic,
                'error_message' => $error->getMessage(),
                'error_class' => get_class($error),
            ],
            0,
            $error
        );
    }
}
