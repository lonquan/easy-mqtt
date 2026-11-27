<?php

declare(strict_types=1);

test('composer metadata declares php 8.4 and laravel 13 or later support', function () {
    $composer = json_decode(
        file_get_contents(__DIR__ . '/../../composer.json') ?: '',
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($composer['require'])->toMatchArray([
        'php' => '^8.4',
        'illuminate/support' => '^13.0',
        'illuminate/contracts' => '^13.0',
    ])->and($composer['require-dev'])->toMatchArray([
        'illuminate/console' => '^13.0',
        'illuminate/container' => '^13.0',
    ]);
});

test('composer metadata declares the published package identity', function () {
    $composer = json_decode(
        file_get_contents(__DIR__ . '/../../composer.json') ?: '',
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($composer['name'])->toBe('longquan/easy-mqtt')
        ->and($composer['support'])->toMatchArray([
            // 仓库迁移到 longquan 账号之前，URL 暂时仍指向 lonquan
            'issues' => 'https://github.com/lonquan/easy-mqtt/issues',
            'source' => 'https://github.com/lonquan/easy-mqtt',
        ]);
});
