<?php

declare(strict_types=1);

use EasyMqtt\Exceptions\MqttException;

test('mqtt exception extends exception', function () {
    $exception = new MqttException('Test message');

    expect($exception)->toBeInstanceOf(Exception::class);
});

test('can set and get context', function () {
    $exception = new MqttException('Test message');
    $context = ['key' => 'value', 'number' => 123];

    $exception->setContext($context);

    expect($exception->getContext())->toBe($context);
});

test('set context returns self for chaining', function () {
    $exception = new MqttException('Test message');
    $context = ['key' => 'value'];

    $result = $exception->setContext($context);

    expect($result)->toBe($exception);
});

test('can create exception with context', function () {
    $context = ['connection' => 'test', 'error' => 'invalid'];
    $exception = MqttException::withContext('Test message', $context);

    expect($exception)->toBeInstanceOf(MqttException::class)
        ->and($exception->getMessage())->toBe('Test message')
        ->and($exception->getContext())->toBe($context);
});

test('can create exception with context and code', function () {
    $context = ['connection' => 'test'];
    $exception = MqttException::withContext('Test message', $context, 500);

    expect($exception)->toBeInstanceOf(MqttException::class)
        ->and($exception->getMessage())->toBe('Test message')
        ->and($exception->getCode())->toBe(500)
        ->and($exception->getContext())->toBe($context);
});

test('can create exception with previous exception', function () {
    $previous = new Exception('Previous error');
    $context = ['connection' => 'test'];
    $exception = MqttException::withContext('Test message', $context, 0, $previous);

    expect($exception)->toBeInstanceOf(MqttException::class)
        ->and($exception->getPrevious())->toBe($previous);
});

test('context is empty by default', function () {
    $exception = new MqttException('Test message');

    expect($exception->getContext())->toBe([]);
});

test('can update context multiple times', function () {
    $exception = new MqttException('Test message');

    $exception->setContext(['key1' => 'value1']);
    expect($exception->getContext())->toBe(['key1' => 'value1']);

    $exception->setContext(['key2' => 'value2']);
    expect($exception->getContext())->toBe(['key2' => 'value2']);
});
