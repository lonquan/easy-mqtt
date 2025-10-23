<?php

declare(strict_types=1);

use EasyMqtt\Exceptions\MqttException;
use EasyMqtt\Exceptions\PublishException;

test('publish exception extends mqtt exception', function () {
    $exception = new PublishException('Test message');

    expect($exception)->toBeInstanceOf(MqttException::class);
});

test('can create invalid topic exception', function () {
    $exception = PublishException::invalidTopic('invalid/topic', 'Topic cannot be empty');

    expect($exception)->toBeInstanceOf(PublishException::class)
        ->and($exception->getMessage())->toContain("Invalid publish topic 'invalid/topic'")
        ->and($exception->getMessage())->toContain('Topic cannot be empty')
        ->and($exception->getContext())->toBe([
            'topic' => 'invalid/topic',
            'reason' => 'Topic cannot be empty',
        ]);
});

test('can create serialization failed exception', function () {
    $message = ['invalid' => 'data'];
    $reason = 'JSON encoding failed';
    $exception = PublishException::serializationFailed($message, $reason);

    expect($exception)->toBeInstanceOf(PublishException::class)
        ->and($exception->getMessage())->toContain('Failed to serialize message')
        ->and($exception->getMessage())->toContain($reason)
        ->and($exception->getContext())->toBe([
            'message_type' => 'array',
            'reason' => $reason,
        ]);
});

test('can create publish failed exception', function () {
    $exception = PublishException::publishFailed('test/topic', 'test_connection', 'Network error');

    expect($exception)->toBeInstanceOf(PublishException::class)
        ->and($exception->getMessage())->toContain("Failed to publish message to topic 'test/topic'")
        ->and($exception->getMessage())->toContain("connection 'test_connection'")
        ->and($exception->getMessage())->toContain('Network error')
        ->and($exception->getContext())->toBe([
            'topic' => 'test/topic',
            'connection' => 'test_connection',
            'reason' => 'Network error',
        ]);
});

test('can create publish timeout exception', function () {
    $exception = PublishException::publishTimeout('test/topic', 'test_connection', 30);

    expect($exception)->toBeInstanceOf(PublishException::class)
        ->and($exception->getMessage())->toContain("Publish timeout for topic 'test/topic'")
        ->and($exception->getMessage())->toContain("connection 'test_connection'")
        ->and($exception->getMessage())->toContain('30 seconds')
        ->and($exception->getContext())->toBe([
            'topic' => 'test/topic',
            'connection' => 'test_connection',
            'timeout' => 30,
        ]);
});

test('can set context on exception', function () {
    $exception = new PublishException('Test message');
    $context = ['key' => 'value', 'number' => 123];

    $exception->setContext($context);

    expect($exception->getContext())->toBe($context);
});

test('can create exception with context', function () {
    $context = ['topic' => 'test', 'error' => 'invalid'];
    $exception = PublishException::withContext('Test message', $context);

    expect($exception)->toBeInstanceOf(PublishException::class)
        ->and($exception->getMessage())->toBe('Test message')
        ->and($exception->getContext())->toBe($context);
});
