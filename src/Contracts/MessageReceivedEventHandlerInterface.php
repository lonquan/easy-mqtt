<?php

declare(strict_types=1);

namespace EasyMqtt\Contracts;

use PhpMqtt\Client\MqttClient;

/**
 * Message Received Hook 接口
 *
 * 当从 broker 接收到消息时执行
 * 用于实现集中式日志记录或指标收集
 */
interface MessageReceivedEventHandlerInterface extends HookInterface
{
    /**
     * 处理消息接收事件
     *
     * @param  MqttClient  $mqtt  MQTT 客户端实例
     * @param  string  $topic  消息主题
     * @param  string  $message  消息内容
     * @param  int  $qualityOfService  QoS 级别
     * @param  bool  $retained  是否保留消息
     */
    public function handle(
        MqttClient $mqtt,
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retained
    ): void;
}
