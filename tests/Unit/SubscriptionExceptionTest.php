<?php

declare(strict_types=1);

use EasyMqtt\Exceptions\MqttException;
use EasyMqtt\Exceptions\SubscriptionException;

test('subscription exception extends mqtt exception', function () {
    $exception = new SubscriptionException('Test message');

    expect($exception)->toBeInstanceOf(MqttException::class);
});

test('can create invalid topic exception', function () {
    $exception = SubscriptionException::invalidTopic('invalid/topic', 'Topic cannot be empty');

    expect($exception)->toBeInstanceOf(SubscriptionException::class)
        ->and($exception->getMessage())->toContain("Invalid subscription topic 'invalid/topic'")
        ->and($exception->getMessage())->toContain('Topic cannot be empty')
        ->and($exception->getContext())->toBe([
            'topic' => 'invalid/topic',
            'reason' => 'Topic cannot be empty',
        ]);
});

test('can create subscribe failed exception', function () {
    $exception = SubscriptionException::subscribeFailed('test/topic', 'test_connection', 'Network error');

    expect($exception)->toBeInstanceOf(SubscriptionException::class)
        ->and($exception->getMessage())->toContain("Failed to subscribe to topic 'test/topic'")
        ->and($exception->getMessage())->toContain("connection 'test_connection'")
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
        ->and($exception->getMessage())->toContain("Invalid subscription handler for topic 'test/topic'")
        ->and($exception->getMessage())->toContain('handler must be callable')
        ->and($exception->getContext())->toBe(['topic' => 'test/topic']);
});

test('can create handler error exception', function () {
    $error = new Exception('Handler error');
    $exception = SubscriptionException::handlerError('test/topic', $error);

    expect($exception)->toBeInstanceOf(SubscriptionException::class)
        ->and($exception->getMessage())->toContain("Error in subscription handler for topic 'test/topic'")
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
