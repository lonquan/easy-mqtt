<?php

declare(strict_types=1);

use EasyMqtt\Exceptions\MqttException;
use EasyMqtt\Exceptions\SubscriptionException;

test('subscription exception extends mqtt exception', function () {
    $exception = new SubscriptionException('Test message');

    expect($exception)->toBeInstanceOf(MqttException::class);
});

test('can create invalid topic exception', function () {
    $exception = SubscriptionException::invalidTopic('invalid/topic', '主题不能为空');

    expect($exception)->toBeInstanceOf(SubscriptionException::class)
        ->and($exception->getMessage())->toContain("无效的订阅主题 'invalid/topic'")
        ->and($exception->getMessage())->toContain('主题不能为空')
        ->and($exception->getContext())->toBe([
            'topic' => 'invalid/topic',
            'reason' => '主题不能为空',
        ]);
});

test('can create subscribe failed exception', function () {
    $exception = SubscriptionException::subscribeFailed('test/topic', 'test_connection', 'Network error');

    expect($exception)->toBeInstanceOf(SubscriptionException::class)
        ->and($exception->getMessage())->toContain("订阅主题 'test/topic' 失败")
        ->and($exception->getMessage())->toContain("连接 'test_connection'")
        ->and($exception->getMessage())->toContain('Network error')
        ->and($exception->getContext())->toBe([
            'topic' => 'test/topic',
            'connection' => 'test_connection',
            'reason' => 'Network error',
        ]);
});

test('can create invalid handler exception', function () {
    $exception = SubscriptionException::invalidHandler('test/topic');

    expect($exception)->toBeInstanceOf(SubscriptionException::class)
        ->and($exception->getMessage())->toContain("主题 'test/topic' 的订阅处理器无效")
        ->and($exception->getMessage())->toContain('处理器必须是可调用的')
        ->and($exception->getContext())->toBe(['topic' => 'test/topic']);
});

test('can create handler error exception', function () {
    $error = new Exception('Handler error');
    $exception = SubscriptionException::handlerError('test/topic', $error);

    expect($exception)->toBeInstanceOf(SubscriptionException::class)
        ->and($exception->getMessage())->toContain("主题 'test/topic' 的订阅处理器执行错误")
        ->and($exception->getMessage())->toContain('Handler error')
        ->and($exception->getPrevious())->toBe($error)
        ->and($exception->getContext())->toBe([
            'topic' => 'test/topic',
            'error_message' => 'Handler error',
            'error_class' => Exception::class,
        ]);
});

test('can set context on exception', function () {
    $exception = new SubscriptionException('Test message');
    $context = ['key' => 'value', 'number' => 123];

    $exception->setContext($context);

    expect($exception->getContext())->toBe($context);
});

test('can create exception with context', function () {
    $context = ['topic' => 'test', 'error' => 'invalid'];
    $exception = SubscriptionException::withContext('Test message', $context);

    expect($exception)->toBeInstanceOf(SubscriptionException::class)
        ->and($exception->getMessage())->toBe('Test message')
        ->and($exception->getContext())->toBe($context);
});
