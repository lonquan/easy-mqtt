<?php

declare(strict_types=1);

namespace EasyMqtt\Support;

use PhpMqtt\Client\MqttClient;
use EasyMqtt\Contracts\LoopEventHandlerInterface;

/**
 * Loop Event Hook 实现
 *
 * 在 MQTT 客户端循环的每次迭代中调用
 */
class LoopEventHandler extends AbstractHook implements LoopEventHandlerInterface
{
    /** @var callable */
    protected $callback;

    public function __construct(callable $callback, ?string $id = null)
    {
        parent::__construct($id);
        $this->callback = $callback;
    }

    /**
     * 处理循环事件
     */
    public function handle(MqttClient $mqtt, float $elapsedTime): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        ($this->callback)($mqtt, $elapsedTime);
    }
}
