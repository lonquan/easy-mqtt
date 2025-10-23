<?php

declare(strict_types=1);

use EasyMqtt\Exceptions\MqttException;
use EasyMqtt\Exceptions\ConfigurationException;

test('configuration exception extends mqtt exception', function () {
    $exception = new ConfigurationException('Test message');

    expect($exception)->toBeInstanceOf(MqttException::class);
});

test('can create connection not found exception', function () {
    $exception = ConfigurationException::connectionNotFound('test_connection');

    expect($exception)->toBeInstanceOf(ConfigurationException::class)
        ->and($exception->getMessage())->toContain("MQTT connection 'test_connection' not found")
        ->and($exception->getContext())->toBe(['connection' => 'test_connection']);
});

test('can create missing parameter exception', function () {
    $exception = ConfigurationException::missingParameter('test_connection', 'host');

    expect($exception)->toBeInstanceOf(ConfigurationException::class)
        ->and($exception->getMessage())->toContain("Missing required parameter 'host'")
        ->and($exception->getMessage())->toContain("MQTT connection 'test_connection'")
        ->and($exception->getContext())->toBe([
            'connection' => 'test_connection',
            'parameter' => 'host',
        ]);
});

test('can create invalid configuration exception', function () {
    $reason = 'Invalid host format';
    $exception = ConfigurationException::invalidConfiguration('test_connection', $reason);

    expect($exception)->toBeInstanceOf(ConfigurationException::class)
        ->and($exception->getMessage())->toContain("Invalid configuration for MQTT connection 'test_connection'")
        ->and($exception->getMessage())->toContain($reason)
        ->and($exception->getContext())->toBe([
            'connection' => 'test_connection',
            'reason' => $reason,
        ]);
});

test('can set context on exception', function () {
    $exception = new ConfigurationException('Test message');
    $context = ['key' => 'value', 'number' => 123];

    $exception->setContext($context);

    expect($exception->getContext())->toBe($context);
});

test('can create exception with context', function () {
    $context = ['connection' => 'test', 'error' => 'invalid'];
    $exception = ConfigurationException::withContext('Test message', $context);

    expect($exception)->toBeInstanceOf(ConfigurationException::class)
        ->and($exception->getMessage())->toBe('Test message')
        ->and($exception->getContext())->toBe($context);
});

test('can create exception with context and code', function () {
    $context = ['connection' => 'test'];
    $exception = ConfigurationException::withContext('Test message', $context, 500);

    expect($exception)->toBeInstanceOf(ConfigurationException::class)
        ->and($exception->getMessage())->toBe('Test message')
        ->and($exception->getCode())->toBe(500)
        ->and($exception->getContext())->toBe($context);
});

test('can create exception with previous exception', function () {
    $previous = new Exception('Previous error');
    $context = ['connection' => 'test'];
    $exception = ConfigurationException::withContext('Test message', $context, 0, $previous);

    expect($exception)->toBeInstanceOf(ConfigurationException::class)
        ->and($exception->getPrevious())->toBe($previous);
});
