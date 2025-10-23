<?php

declare(strict_types=1);

namespace EasyMqtt\Support;

use PhpMqtt\Client\MqttClient;
use EasyMqtt\Contracts\MessageReceivedEventHandlerInterface;

/**
 * Message Received Hook 实现
 *
 * 当从 broker 接收到消息时执行
 */
class MessageReceivedEventHandler extends AbstractHook implements MessageReceivedEventHandlerInterface
{
    /** @var callable */
    protected $callback;

    public function __construct(callable $callback, ?string $id = null)
    {
        parent::__construct($id);
        $this->callback = $callback;
    }

    /**
     * 处理消息接收事件
     */
    public function handle(
        MqttClient $mqtt,
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retained
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        ($this->callback)($mqtt, $topic, $message, $qualityOfService, $retained);
    }
}
