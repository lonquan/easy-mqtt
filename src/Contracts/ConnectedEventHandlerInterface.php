<?php

declare(strict_types=1);

namespace EasyMqtt\Contracts;

use PhpMqtt\Client\MqttClient;

/**
 * Connected Hook 接口
 *
 * 当客户端连接到 broker 时调用（初始连接或自动重连）
 */
interface ConnectedEventHandlerInterface extends HookInterface
{
    /**
     * 处理连接事件
     *
     * @param  MqttClient  $mqtt  MQTT 客户端实例
     * @param  bool  $isAutoReconnect  是否为自动重连
     */
    public function handle(MqttClient $mqtt, bool $isAutoReconnect): void;
}
