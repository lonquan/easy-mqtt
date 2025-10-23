<?php

declare(strict_types=1);

namespace EasyMqtt\Contracts;

use PhpMqtt\Client\MqttClient;

/**
 * Publish Event Hook 接口
 *
 * 每次向 broker 发布消息时触发
 * 用于实现集中式日志记录或指标收集
 */
interface PublishEventHandlerInterface extends HookInterface
{
    /**
     * 处理发布事件
     *
     * @param  MqttClient  $mqtt  MQTT 客户端实例
     * @param  string  $topic  发布主题
     * @param  string  $message  消息内容
     * @param  int|null  $messageId  消息ID
     * @param  int  $qualityOfService  QoS 级别
     * @param  bool  $retain  是否保留消息
     */
    public function handle(
        MqttClient $mqtt,
        string $topic,
        string $message,
        ?int $messageId,
        int $qualityOfService,
        bool $retain
    ): void;
}
