<?php

declare(strict_types=1);

namespace EasyMqtt\Support;

use EasyMqtt\Exceptions\PublishException;

/**
 * MQTT 消息序列化器
 *
 * 负责消息的序列化和反序列化
 * - 字符串消息直接返回
 * - 数组消息转换为 JSON
 */
class MessageSerializer
{
    /**
     * 序列化消息
     *
     * @param  array<mixed>|string  $message
     *
     * @throws PublishException
     */
    public static function serialize(array|string $message): string
    {
        // 验证消息类型
        self::validateMessage($message);

        // 字符串直接返回
        if (is_string($message)) {
            return $message;
        }

        // 数组转换为 JSON
        try {
            return json_encode(
                $message,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw PublishException::serializationFailed($message, $e->getMessage());
        }
    }

    /**
     * 反序列化消息
     *
     * @return string 原始字符串（JSON 不会在这里解析，Message 类负责解析）
     */
    public static function deserialize(string $message): string
    {
        // 直接返回原始字符串
        // Message 类的构造函数会自动检测并解析 JSON
        return $message;
    }

    /**
     * 验证消息类型
     *
     * @throws PublishException
     */
    public static function validateMessage(mixed $message): void
    {
        if (! is_string($message) && ! is_array($message)) {
            throw PublishException::serializationFailed(
                $message,
                'Message must be either string or array, ' . gettype($message) . ' given'
            );
        }
    }
}
