<?php

declare(strict_types=1);

use EasyMqtt\Exceptions\MqttException;
use EasyMqtt\Exceptions\ConnectionException;

test('connection exception extends mqtt exception', function () {
    $exception = new ConnectionException('Test message');

    expect($exception)->toBeInstanceOf(MqttException::class);
});

test('can create failed to connect exception', function () {
    $exception = ConnectionException::failedToConnect('test_connection', 'localhost', 1883, 'Connection refused');

    expect($exception)->toBeInstanceOf(ConnectionException::class)
        ->and($exception->getMessage())->toContain("连接 MQTT broker 'test_connection' 失败")
        ->and($exception->getMessage())->toContain('localhost:1883')
        ->and($exception->getMessage())->toContain('Connection refused')
        ->and($exception->getContext())->toBe([
            'connection' => 'test_connection',
            'host' => 'localhost',
            'port' => 1883,
            'reason' => 'Connection refused',
        ]);
});

test('can create authentication failed exception', function () {
    $exception = ConnectionException::authenticationFailed('test_connection', 'test_user');

    expect($exception)->toBeInstanceOf(ConnectionException::class)
        ->and($exception->getMessage())->toContain("MQTT 连接 'test_connection' 认证失败")
        ->and($exception->getMessage())->toContain("用户名 'test_user'")
        ->and($exception->getContext())->toBe([
            'connection' => 'test_connection',
            'username' => 'test_user',
        ]);
});

test('can create connection timeout exception', function () {
    $exception = ConnectionException::connectionTimeout('test_connection', 30);

    expect($exception)->toBeInstanceOf(ConnectionException::class)
        ->and($exception->getMessage())->toContain("MQTT 连接 'test_connection' 超时")
        ->and($exception->getMessage())->toContain('30 秒')
        ->and($exception->getContext())->toBe([
            'connection' => 'test_connection',
            'timeout' => 30,
        ]);
});

test('can create connection lost exception', function () {
    $exception = ConnectionException::connectionLost('test_connection');

    expect($exception)->toBeInstanceOf(ConnectionException::class)
        ->and($exception->getMessage())->toContain("MQTT 连接 'test_connection' 意外断开")
        ->and($exception->getContext())->toBe(['connection' => 'test_connection']);
});

test('can create reconnection failed exception', function () {
    $exception = ConnectionException::reconnectionFailed('test_connection', 3);

    expect($exception)->toBeInstanceOf(ConnectionException::class)
        ->and($exception->getMessage())->toContain("重连 MQTT broker 'test_connection' 失败")
        ->and($exception->getMessage())->toContain('3 次')
        ->and($exception->getContext())->toBe([
            'connection' => 'test_connection',
            'attempts' => 3,
        ]);
});

test('can set context on exception', function () {
    $exception = new ConnectionException('Test message');
    $context = ['key' => 'value', 'number' => 123];

    $exception->setContext($context);

    expect($exception->getContext())->toBe($context);
});

test('can create exception with context', function () {
    $context = ['connection' => 'test', 'error' => 'invalid'];
    $exception = ConnectionException::withContext('Test message', $context);

    expect($exception)->toBeInstanceOf(ConnectionException::class)
        ->and($exception->getMessage())->toBe('Test message')
        ->and($exception->getContext())->toBe($context);
});
