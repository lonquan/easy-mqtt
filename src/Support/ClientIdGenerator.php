<?php

declare(strict_types=1);

namespace EasyMqtt\Support;

use Exception;
use Illuminate\Support\Str;

/**
 * MQTT Client ID 生成器
 *
 * 根据配置生成符合规则的 client_id
 * 格式: {prefix}_{ulid}
 */
class ClientIdGenerator
{
    /**
     * 默认前缀
     */
    private const string DEFAULT_PREFIX = 'mqtt';

    /**
     * 生成 Client ID
     *
     * @param  string|null  $prefix  前缀（为 null 时使用默认值 'mqtt'）
     * @return string 格式: prefix_ulid
     *
     * @throws Exception
     */
    public static function generate(?string $prefix = null): string
    {
        $prefix = $prefix ?: self::DEFAULT_PREFIX;
        $id = Str::ulid()->toString();

        return sprintf('%s_%s', $prefix, $id);
    }

    /**
     * 验证 Client ID 格式
     */
    public static function isValid(string $clientId): bool
    {
        // Client ID 不能为空
        if (empty($clientId)) {
            return false;
        }

        // MQTT 规范: Client ID 长度限制 1-23 字符（MQTT 3.1）或 1-65535 字符（MQTT 3.1.1+）
        // 大多数 broker 支持更长的 Client ID，这里采用宽松的验证
        if (strlen($clientId) > 65535) {
            return false;
        }

        // MQTT 规范: Client ID 应只包含字母、数字、下划线、连字符
        // 实际上很多 broker 支持更多字符，这里采用宽松验证
        return preg_match('/^[a-zA-Z0-9_-]+$/', $clientId) === 1;
    }
}
