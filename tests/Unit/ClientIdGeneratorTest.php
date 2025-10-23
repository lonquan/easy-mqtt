<?php

declare(strict_types=1);

use EasyMqtt\Support\ClientIdGenerator;

test('can generate client id with default prefix', function () {
    $clientId = ClientIdGenerator::generate();

    expect($clientId)->toBeString()
        ->and($clientId)->toStartWith('mqtt_')
        ->and(strlen($clientId))->toBeGreaterThan(10);
});

test('can generate client id with custom prefix', function () {
    $prefix = 'test';
    $clientId = ClientIdGenerator::generate($prefix);

    expect($clientId)->toBeString()
        ->and($clientId)->toStartWith('test_')
        ->and(strlen($clientId))->toBeGreaterThan(10);
});

test('can generate client id with null prefix uses default', function () {
    $clientId = ClientIdGenerator::generate(null);

    expect($clientId)->toBeString()
        ->and($clientId)->toStartWith('mqtt_');
});

test('generated client ids are unique', function () {
    $ids = [];

    for ($i = 0; $i < 10; $i++) {
        $ids[] = ClientIdGenerator::generate();
    }

    expect($ids)->toHaveCount(10)
        ->and(array_unique($ids))->toHaveCount(10);
});

test('can validate valid client id', function () {
    $validIds = [
        'mqtt_1234567890abcdef',
        'test_client_001',
        'my-app_abc123',
        'sensor_device_001',
    ];

    foreach ($validIds as $id) {
        expect(ClientIdGenerator::isValid($id))->toBeTrue();
    }
});

test('can validate invalid client id', function () {
    $invalidIds = [
        '', // 空字符串
        str_repeat('a', 65536), // 超过长度限制
        'invalid@client', // 包含特殊字符
        'client with spaces', // 包含空格
        'client.with.dots', // 包含点号
    ];

    foreach ($invalidIds as $id) {
        expect(ClientIdGenerator::isValid($id))->toBeFalse();
    }
});

test('generated client id passes validation', function () {
    $clientId = ClientIdGenerator::generate('test');

    expect(ClientIdGenerator::isValid($clientId))->toBeTrue();
});

test('client id format is correct', function () {
    $clientId = ClientIdGenerator::generate('test');

    // 应该匹配格式: prefix_ulid
    // Str::ulid() 生成26个字符
    expect($clientId)->toMatch('/^test_[A-Z0-9]{26}$/');
});

test('client id does not contain dots', function () {
    $clientId = ClientIdGenerator::generate('test');

    expect($clientId)->not->toContain('.');
});
