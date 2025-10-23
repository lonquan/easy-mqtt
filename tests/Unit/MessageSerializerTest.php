<?php

declare(strict_types=1);

use EasyMqtt\Support\MessageSerializer;
use EasyMqtt\Exceptions\PublishException;

test('can serialize string message', function () {
    $message = 'Hello MQTT';
    $serialized = MessageSerializer::serialize($message);

    expect($serialized)->toBeString()
        ->and($serialized)->toBe($message);
});

test('can serialize array message to json', function () {
    $message = ['temperature' => 25.5, 'unit' => 'C'];
    $serialized = MessageSerializer::serialize($message);

    expect($serialized)->toBeString()
        ->and($serialized)->toBeJson()
        ->and(json_decode($serialized, true))->toBe($message);
});

test('can serialize empty array', function () {
    $message = [];
    $serialized = MessageSerializer::serialize($message);

    expect($serialized)->toBeString()
        ->and($serialized)->toBe('[]');
});

test('can serialize nested array', function () {
    $message = [
        'device' => [
            'id' => 'sensor_001',
            'location' => 'room_1',
        ],
        'data' => [
            'temperature' => 25.5,
            'humidity' => 60,
        ],
    ];
    $serialized = MessageSerializer::serialize($message);

    expect($serialized)->toBeString()
        ->and($serialized)->toBeJson()
        ->and(json_decode($serialized, true))->toBe($message);
});

test('can serialize array with unicode characters', function () {
    $message = ['message' => '你好世界', 'status' => '正常'];
    $serialized = MessageSerializer::serialize($message);

    expect($serialized)->toBeString()
        ->and($serialized)->toContain('你好世界')
        ->and($serialized)->toContain('正常');
});

test('throws exception for invalid message type', function () {
    // 测试整数类型
    try {
        MessageSerializer::validateMessage(123);
        expect(false)->toBeTrue('Should have thrown exception');
    } catch (PublishException $e) {
        expect($e)->toBeInstanceOf(PublishException::class);
    }

    // 测试布尔类型
    try {
        MessageSerializer::validateMessage(true);
        expect(false)->toBeTrue('Should have thrown exception');
    } catch (PublishException $e) {
        expect($e)->toBeInstanceOf(PublishException::class);
    }

    // 测试 null
    try {
        MessageSerializer::validateMessage(null);
        expect(false)->toBeTrue('Should have thrown exception');
    } catch (PublishException $e) {
        expect($e)->toBeInstanceOf(PublishException::class);
    }

    // 测试对象类型
    try {
        MessageSerializer::validateMessage(new stdClass);
        expect(false)->toBeTrue('Should have thrown exception');
    } catch (PublishException $e) {
        expect($e)->toBeInstanceOf(PublishException::class);
    }
});

test('can deserialize string message', function () {
    $message = 'Hello MQTT';
    $deserialized = MessageSerializer::deserialize($message);

    expect($deserialized)->toBeString()
        ->and($deserialized)->toBe($message);
});

test('can deserialize json string', function () {
    $jsonString = '{"temperature": 25.5, "unit": "C"}';
    $deserialized = MessageSerializer::deserialize($jsonString);

    expect($deserialized)->toBeString()
        ->and($deserialized)->toBe($jsonString);
});

test('can deserialize empty string', function () {
    $deserialized = MessageSerializer::deserialize('');

    expect($deserialized)->toBeString()
        ->and($deserialized)->toBe('');
});

test('can validate string message', function () {
    expect(fn () => MessageSerializer::validateMessage('valid string'))
        ->not->toThrow(Exception::class);
});

test('can validate array message', function () {
    expect(fn () => MessageSerializer::validateMessage(['key' => 'value']))
        ->not->toThrow(Exception::class);
});

test('throws exception when validating invalid message', function () {
    expect(fn () => MessageSerializer::validateMessage(123))
        ->toThrow(PublishException::class);

    expect(fn () => MessageSerializer::validateMessage(true))
        ->toThrow(PublishException::class);

    expect(fn () => MessageSerializer::validateMessage(null))
        ->toThrow(PublishException::class);
});
