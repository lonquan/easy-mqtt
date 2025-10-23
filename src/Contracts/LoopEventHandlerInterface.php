<?php

declare(strict_types=1);

namespace EasyMqtt\Contracts;

use PhpMqtt\Client\MqttClient;

/**
 * Loop Event Hook 接口
 *
 * 在 MQTT 客户端循环的每次迭代中调用
 * 用于实现超时或其他死锁预防逻辑
 */
interface LoopEventHandlerInterface extends HookInterface
{
    /**
     * 处理循环事件
     *
     * @param  MqttClient  $mqtt  MQTT 客户端实例
     * @param  float  $elapsedTime  已运行时间（秒）
     */
    public function handle(MqttClient $mqtt, float $elapsedTime): void;
}
