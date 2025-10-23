<?php

declare(strict_types=1);

namespace EasyMqtt\Exceptions;

/**
 * 配置相关异常
 *
 * 当 MQTT 配置缺失、不正确或无法读取时抛出
 * 例如：连接名称不存在、必需配置参数缺失等
 */
class ConfigurationException extends MqttException
{
    /**
     * 创建连接不存在异常
     */
    public static function connectionNotFound(string $connectionName): static
    {
        return static::withContext(
            "MQTT connection '{$connectionName}' not found in configuration",
            ['connection' => $connectionName]
        );
    }

    /**
     * 创建配置参数缺失异常
     */
    public static function missingParameter(string $connectionName, string $parameter): static
    {
        return static::withContext(
            "Missing required parameter '{$parameter}' for MQTT connection '{$connectionName}'",
            ['connection' => $connectionName, 'parameter' => $parameter]
        );
    }

    /**
     * 创建无效配置异常
     */
    public static function invalidConfiguration(string $connectionName, string $reason): static
    {
        return static::withContext(
            "Invalid configuration for MQTT connection '{$connectionName}': {$reason}",
            ['connection' => $connectionName, 'reason' => $reason]
        );
    }
}
