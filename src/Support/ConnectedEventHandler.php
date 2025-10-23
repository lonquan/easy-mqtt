<?php

declare(strict_types=1);

namespace EasyMqtt\Support;

use PhpMqtt\Client\MqttClient;
use EasyMqtt\Contracts\ConnectedEventHandlerInterface;

/**
 * Connected Hook 实现
 *
 * 当客户端连接到 broker 时调用
 */
class ConnectedEventHandler extends AbstractHook implements ConnectedEventHandlerInterface
{
    /** @var callable */
    protected $callback;

    public function __construct(callable $callback, ?string $id = null)
    {
        parent::__construct($id);
        $this->callback = $callback;
    }

    /**
     * 处理连接事件
     */
    public function handle(MqttClient $mqtt, bool $isAutoReconnect): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        ($this->callback)($mqtt, $isAutoReconnect);
    }
}
