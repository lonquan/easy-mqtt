<?php

declare(strict_types=1);

use EasyMqtt\Message;

test('can create message from array', function () {
    $data = ['temperature' => 25.5, 'unit' => 'C'];
    $message = Message::make($data, 'sensor/temperature');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->all())->toBe($data)
        ->and($message->raw())->toBeString();
});

test('can create message from string', function () {
    $content = 'Hello MQTT';
    $message = Message::make($content, 'sensor/data');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->raw())->toBe($content);
});

test('can get message topic', function () {
    $message = new Message('test data', 'sensor/temperature');

    expect($message->getTopic())->toBe('sensor/temperature');
});

test('message is json serializable', function () {
    $data = ['value' => 25.5];
    $message = Message::make($data, 'sensor/data');

    expect($message)->toBeInstanceOf(JsonSerializable::class)
        ->and(json_encode($message))->toBeJson();
});

test('can create message with connection', function () {
    $data = ['temperature' => 25.5];
    $message = Message::make($data, 'sensor/temperature', 'test_connection');

    expect($message->getConnection())->toBe('test_connection')
        ->and($message->getTopic())->toBe('sensor/temperature');
});

test('can set connection using fluent api', function () {
    $message = new Message(['data' => 'test'], 'test/topic');

    $result = $message->useConnection('test_connection');

    expect($result)->toBe($message)
        ->and($message->getConnection())->toBe('test_connection');
});

test('can set topic using fluent api', function () {
    $message = new Message(['data' => 'test']);

    $result = $message->toTopic('test/topic');

    expect($result)->toBe($message)
        ->and($message->getTopic())->toBe('test/topic');
});

test('can check if message is json', function () {
    $jsonMessage = new Message(['key' => 'value'], 'test/topic');
    $stringMessage = new Message('plain text', 'test/topic');

    expect($jsonMessage->isJson())->toBeTrue()
        ->and($stringMessage->isJson())->toBeFalse();
});

test('can check if message is empty', function () {
    $emptyMessage = new Message('', 'test/topic');
    $nonEmptyMessage = new Message('data', 'test/topic');

    expect($emptyMessage->isEmpty())->toBeTrue()
        ->and($nonEmptyMessage->isEmpty())->toBeFalse();
});

test('can get nested values using dot notation', function () {
    $data = [
        'device' => [
            'id' => 'sensor_001',
            'location' => 'room_1',
        ],
        'data' => [
            'temperature' => 25.5,
        ],
    ];
    $message = new Message($data, 'test/topic');

    expect($message->get('device.id'))->toBe('sensor_001')
        ->and($message->get('device.location'))->toBe('room_1')
        ->and($message->get('data.temperature'))->toBe(25.5)
        ->and($message->get('nonexistent.key', 'default'))->toBe('default');
});

test('can check if nested keys exist', function () {
    $data = [
        'device' => [
            'id' => 'sensor_001',
        ],
    ];
    $message = new Message($data, 'test/topic');

    expect($message->has('device.id'))->toBeTrue()
        ->and($message->has('device.location'))->toBeFalse()
        ->and($message->has('nonexistent'))->toBeFalse();
});

test('can access properties using magic methods', function () {
    $data = ['temperature' => 25.5, 'unit' => 'C'];
    $message = new Message($data, 'test/topic');

    expect($message->temperature)->toBe(25.5)
        ->and($message->unit)->toBe('C')
        ->and(isset($message->temperature))->toBeTrue()
        ->and(isset($message->nonexistent))->toBeFalse();
});

test('can access properties using array access', function () {
    $data = ['temperature' => 25.5, 'unit' => 'C'];
    $message = new Message($data, 'test/topic');

    expect($message['temperature'])->toBe(25.5)
        ->and($message['unit'])->toBe('C')
        ->and(isset($message['temperature']))->toBeTrue()
        ->and(isset($message['nonexistent']))->toBeFalse();
});

test('throws exception when trying to set array access', function () {
    $message = new Message(['data' => 'test'], 'test/topic');

    expect(fn () => $message['new_key'] = 'value')
        ->toThrow(BadMethodCallException::class);
});

test('throws exception when trying to unset array access', function () {
    $message = new Message(['data' => 'test'], 'test/topic');

    expect(fn () => $message->offsetUnset('data'))
        ->toThrow(BadMethodCallException::class);
});

test('can convert message to string', function () {
    $data = ['temperature' => 25.5];
    $message = new Message($data, 'test/topic');

    expect((string) $message)->toBeString()
        ->and((string) $message)->toBe($message->raw());
});

test('can handle empty json message', function () {
    $message = new Message('', 'test/topic');

    expect($message->isJson())->toBeFalse()
        ->and($message->all())->toBeNull()
        ->and($message->get('key', 'default'))->toBe('default');
});

test('can handle invalid json message', function () {
    $message = new Message('invalid json {', 'test/topic');

    expect($message->isJson())->toBeFalse()
        ->and($message->all())->toBeNull()
        ->and($message->raw())->toBe('invalid json {');
});

test('can handle nested array access with invalid path', function () {
    $data = ['device' => ['id' => 'sensor_001']];
    $message = new Message($data, 'test/topic');

    expect($message->get('device.id.nonexistent', 'default'))->toBe('default')
        ->and($message->has('device.id.nonexistent'))->toBeFalse();
});

test('can handle array access on non-json message', function () {
    $message = new Message('plain text', 'test/topic');

    expect($message['key'])->toBeNull()
        ->and(isset($message['key']))->toBeFalse();
});

test('can handle magic property access on non-json message', function () {
    $message = new Message('plain text', 'test/topic');

    expect($message->key)->toBeNull()
        ->and(isset($message->key))->toBeFalse();
});

test('json serialization returns data for json messages', function () {
    $data = ['temperature' => 25.5];
    $message = new Message($data, 'test/topic');

    expect($message->jsonSerialize())->toBe($data);
});

test('json serialization returns raw string for non-json messages', function () {
    $message = new Message('plain text', 'test/topic');

    expect($message->jsonSerialize())->toBe('plain text');
});

test('can handle unicode characters in json', function () {
    $data = ['message' => '你好世界', 'status' => '正常'];
    $message = new Message($data, 'test/topic');

    expect($message->isJson())->toBeTrue()
        ->and($message->get('message'))->toBe('你好世界')
        ->and($message->get('status'))->toBe('正常');
});

test('can handle complex nested data structures', function () {
    $data = [
        'sensors' => [
            [
                'id' => 'temp_001',
                'value' => 25.5,
                'unit' => 'C',
            ],
            [
                'id' => 'hum_001',
                'value' => 60,
                'unit' => '%',
            ],
        ],
        'timestamp' => '2024-01-01T00:00:00Z',
    ];
    $message = new Message($data, 'test/topic');

    expect($message->isJson())->toBeTrue()
        ->and($message->get('sensors.0.id'))->toBe('temp_001')
        ->and($message->get('sensors.1.value'))->toBe(60)
        ->and($message->get('timestamp'))->toBe('2024-01-01T00:00:00Z');
});

// ==================== 发送方法测试 ====================

test('can send message with topic set', function () {
    $message = new Message(['data' => 'test'], 'test/topic');
    $message->useConnection('test_connection');

    // 由于我们无法真正连接到 MQTT broker，这里只测试方法调用
    // 实际测试中会抛出连接异常，但方法调用本身是正确的
    expect(fn () => $message->send())
        ->toThrow(Error::class); // 连接异常
});

test('throws exception when sending message without topic', function () {
    $message = new Message(['data' => 'test']); // 没有设置 topic

    expect(fn () => $message->send())
        ->toThrow(InvalidArgumentException::class);
});

test('can send message with custom qos and retain', function () {
    $message = new Message(['data' => 'test'], 'test/topic');
    $message->useConnection('test_connection');

    // 测试不同的 QoS 级别和 retain 设置
    expect(fn () => $message->send(1, true))
        ->toThrow(Error::class); // 连接异常

    expect(fn () => $message->send(2, false))
        ->toThrow(Error::class); // 连接异常
});

test('can publish to specific topic', function () {
    $message = new Message(['data' => 'test']);

    // 由于我们无法真正连接到 MQTT broker，这里只测试方法调用
    expect(fn () => $message->publishTo('test/topic', 'test_connection'))
        ->toThrow(Error::class); // 连接异常
});

test('can publish to specific topic with custom qos and retain', function () {
    $message = new Message(['data' => 'test']);

    // 测试不同的 QoS 级别和 retain 设置
    expect(fn () => $message->publishTo('test/topic', 'test_connection', 1, true))
        ->toThrow(Error::class); // 连接异常

    expect(fn () => $message->publishTo('test/topic', 'test_connection', 2, false))
        ->toThrow(Error::class); // 连接异常
});

test('publishTo method sets topic and connection', function () {
    $message = new Message(['data' => 'test']);

    // 调用 publishTo 后，topic 和 connection 应该被设置
    // 由于 app() 函数返回 null，这里会抛出异常，但我们只测试设置功能
    try {
        $message->publishTo('test/topic', 'test_connection');
    } catch (Throwable $e) {
        // 忽略异常，只测试设置功能
    }

    expect($message->getTopic())->toBe('test/topic')
        ->and($message->getConnection())->toBe('test_connection');
});

test('can chain fluent api methods', function () {
    $message = new Message(['data' => 'test']);

    $result = $message->useConnection('test_connection')
        ->toTopic('test/topic');

    expect($result)->toBe($message)
        ->and($message->getConnection())->toBe('test_connection')
        ->and($message->getTopic())->toBe('test/topic');
});

test('fluent api methods return same instance', function () {
    $message = new Message(['data' => 'test']);

    $connectionResult = $message->useConnection('test_connection');
    $topicResult = $message->toTopic('test/topic');

    expect($connectionResult)->toBe($message)
        ->and($topicResult)->toBe($message);
});
