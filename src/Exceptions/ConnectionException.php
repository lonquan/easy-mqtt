<?php

declare(strict_types=1);

namespace EasyMqtt\Exceptions;

/**
 * 连接相关异常
 *
 * 当 MQTT 连接失败、断开或连接状态异常时抛出
 * 例如：无法连接到 broker、连接超时、认证失败等
 */
class ConnectionException extends MqttException
{
    /**
     * 创建连接失败异常
     */
    public static function failedToConnect(string $connectionName, string $host, int $port, string $reason): static
    {
        return static::withContext(
            "Failed to connect to MQTT broker '{$connectionName}' at {$host}:{$port}: {$reason}",
            [
                'connection' => $connectionName,
                'host' => $host,
                'port' => $port,
                'reason' => $reason,
            ]
        );
    }

    /**
     * 创建认证失败异常
     */
    public static function authenticationFailed(string $connectionName, string $username): static
    {
        return static::withContext(
            "Authentication failed for MQTT connection '{$connectionName}' with username '{$username}'",
            [
                'connection' => $connectionName,
                'username' => $username,
            ]
        );
    }

    /**
     * 创建连接超时异常
     */
    public static function connectionTimeout(string $connectionName, int $timeout): static
    {
        return static::withContext(
            "Connection timeout for MQTT connection '{$connectionName}' after {$timeout} seconds",
            [
                'connection' => $connectionName,
                'timeout' => $timeout,
            ]
        );
    }

    /**
     * 创建连接断开异常
     */
    public static function connectionLost(string $connectionName): static
    {
        return static::withContext(
            "MQTT connection '{$connectionName}' lost unexpectedly",
            ['connection' => $connectionName]
        );
    }

    /**
     * 创建重连失败异常
     */
    public static function reconnectionFailed(string $connectionName, int $attempts): static
    {
        return static::withContext(
            "Failed to reconnect to MQTT broker '{$connectionName}' after {$attempts} attempts",
            [
                'connection' => $connectionName,
                'attempts' => $attempts,
            ]
        );
    }
}
