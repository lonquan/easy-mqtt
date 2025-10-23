<?php

declare(strict_types=1);

namespace EasyMqtt\Support;

use PhpMqtt\Client\MqttClient;
use EasyMqtt\Contracts\PublishEventHandlerInterface;

/**
 * Publish Event Hook 实现
 *
 * 每次向 broker 发布消息时触发
 */
class PublishEventHandler extends AbstractHook implements PublishEventHandlerInterface
{
    /** @var callable */
    protected $callback;

    public function __construct(callable $callback, ?string $id = null)
    {
        parent::__construct($id);
        $this->callback = $callback;
    }

    /**
     * 处理发布事件
     */
    public function handle(
        MqttClient $mqtt,
        string $topic,
        string $message,
        ?int $messageId,
        int $qualityOfService,
        bool $retain
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        ($this->callback)($mqtt, $topic, $message, $messageId, $qualityOfService, $retain);
    }
}
