<?php

declare(strict_types=1);

namespace EasyMqtt\Contracts;

use EasyMqtt\Message;

/**
 * MQTT 消息处理器接口
 *
 * 定义订阅消息处理器的标准接口
 * 实现此接口的类可以作为订阅处理器使用
 */
interface MessageHandler
{
    /**
     * 处理接收到的 MQTT 消息
     *
     * @param  string  $topic  消息主题
     * @param  Message  $message  消息对象
     */
    public function handle(string $topic, Message $message): void;
}
