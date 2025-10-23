<?php

declare(strict_types=1);

/**
 * Laravel MQTT Factory 配置模板
 *
 * Laravel 12 深度融合的 MQTT 工厂封装库配置
 * 复制此文件并根据您的需求进行修改
 */

return [
    /*
    |--------------------------------------------------------------------------
    | 默认连接
    |--------------------------------------------------------------------------
    |
    | 指定默认使用的 MQTT 连接名称
    |
    */
    'default' => env('MQTT_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | MQTT 连接配置
    |--------------------------------------------------------------------------
    |
    | 这里可以配置多个 MQTT 连接
    | 每个连接都可以有不同的 broker 和认证信息
    |
    */
    'connections' => [
        'default' => [
            // 基本连接配置
            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => (int) env('MQTT_PORT', 1883),
            'username' => env('MQTT_USERNAME', null),
            'password' => env('MQTT_PASSWORD', null),
            'client_id_prefix' => env('MQTT_CLIENT_ID_PREFIX', 'app_'),
            'client_id' => env('MQTT_CLIENT_ID', null), // 固定客户端ID（可选）

            // 日志配置
            'logging' => [
                'enabled' => env('MQTT_LOGGING_ENABLED', false),
                'channel' => env('MQTT_LOGGING_CHANNEL', 'mqtt'),
            ],

            // Repository 配置
            'repository' => [
                'class' => env('MQTT_REPOSITORY_CLASS', null),
            ],

            // 连接设置
            'connection_settings' => [
                'use_blocking_socket' => env('MQTT_USE_BLOCKING_SOCKET', false),
                'connect_timeout' => (int) env('MQTT_CONNECT_TIMEOUT', 60),
                'socket_timeout' => (int) env('MQTT_SOCKET_TIMEOUT', 5),
                'resend_timeout' => (int) env('MQTT_RESEND_TIMEOUT', 10),
                'reconnect_automatically' => env('MQTT_RECONNECT_AUTOMATICALLY', false),
                'max_reconnect_attempts' => (int) env('MQTT_MAX_RECONNECT_ATTEMPTS', 3),
                'delay_between_reconnect_attempts' => (int) env('MQTT_DELAY_BETWEEN_RECONNECT_ATTEMPTS', 0),
                'keep_alive_interval' => (int) env('MQTT_KEEP_ALIVE_INTERVAL', 10),

                // 遗嘱消息配置
                'last_will' => [
                    'topic' => env('MQTT_LAST_WILL_TOPIC', null),
                    'message' => env('MQTT_LAST_WILL_MESSAGE', null),
                    'quality_of_service' => (int) env('MQTT_LAST_WILL_QOS', 0),
                    'retain' => env('MQTT_RETAIN_LAST_WILL', false),
                ],

                // TLS/SSL 配置
                'tls' => [
                    'use_tls' => env('MQTT_USE_TLS', false),
                    'verify_peer' => env('MQTT_TLS_VERIFY_PEER', true),
                    'verify_peer_name' => env('MQTT_TLS_VERIFY_PEER_NAME', true),
                    'self_signed_allowed' => env('MQTT_TLS_SELF_SIGNED_ALLOWED', false),
                    'certificate_authority_file' => env('MQTT_TLS_CA_FILE', null),
                    'certificate_authority_path' => env('MQTT_TLS_CA_PATH', null),
                    'client_certificate_file' => env('MQTT_TLS_CLIENT_CERT_FILE', null),
                    'client_certificate_key_file' => env('MQTT_TLS_CLIENT_KEY_FILE', null),
                    'client_certificate_key_passphrase' => env('MQTT_TLS_CLIENT_KEY_PASSPHRASE', null),
                    'alpn' => env('MQTT_TLS_ALPN', null),
                ],
            ],
        ],
    ],
];
