<?php

declare(strict_types=1);

namespace EasyMqtt\Support;

use PhpMqtt\Client\MqttClient;
use EasyMqtt\Contracts\HookInterface;
use EasyMqtt\Contracts\LoopEventHandlerInterface;
use EasyMqtt\Contracts\PublishEventHandlerInterface;
use EasyMqtt\Contracts\ConnectedEventHandlerInterface;
use EasyMqtt\Contracts\MessageReceivedEventHandlerInterface;

/**
 * Hook 管理器
 *
 * 统一管理所有类型的 Hook，提供注册、注销和执行功能
 */
class HookManager
{
    /**
     * @var array<string, LoopEventHandlerInterface>
     */
    protected array $loopEventHandlers = [];

    /**
     * @var array<string, PublishEventHandlerInterface>
     */
    protected array $publishEventHandlers = [];

    /**
     * @var array<string, MessageReceivedEventHandlerInterface>
     */
    protected array $messageReceivedEventHandlers = [];

    /**
     * @var array<string, ConnectedEventHandlerInterface>
     */
    protected array $connectedEventHandlers = [];

    /**
     * 注册 Loop Event Handler
     */
    public function registerLoopEventHandler(LoopEventHandlerInterface $handler): void
    {
        $this->loopEventHandlers[$handler->getId()] = $handler;
    }

    /**
     * 注销 Loop Event Handler
     */
    public function unregisterLoopEventHandler(?LoopEventHandlerInterface $handler = null): void
    {
        if ($handler === null) {
            $this->loopEventHandlers = [];
        } else {
            unset($this->loopEventHandlers[$handler->getId()]);
        }
    }

    /**
     * 注册 Publish Event Handler
     */
    public function registerPublishEventHandler(PublishEventHandlerInterface $handler): void
    {
        $this->publishEventHandlers[$handler->getId()] = $handler;
    }

    /**
     * 注销 Publish Event Handler
     */
    public function unregisterPublishEventHandler(?PublishEventHandlerInterface $handler = null): void
    {
        if ($handler === null) {
            $this->publishEventHandlers = [];
        } else {
            unset($this->publishEventHandlers[$handler->getId()]);
        }
    }

    /**
     * 注册 Message Received Event Handler
     */
    public function registerMessageReceivedEventHandler(MessageReceivedEventHandlerInterface $handler): void
    {
        $this->messageReceivedEventHandlers[$handler->getId()] = $handler;
    }

    /**
     * 注销 Message Received Event Handler
     */
    public function unregisterMessageReceivedEventHandler(?MessageReceivedEventHandlerInterface $handler = null): void
    {
        if ($handler === null) {
            $this->messageReceivedEventHandlers = [];
        } else {
            unset($this->messageReceivedEventHandlers[$handler->getId()]);
        }
    }

    /**
     * 注册 Connected Event Handler
     */
    public function registerConnectedEventHandler(ConnectedEventHandlerInterface $handler): void
    {
        $this->connectedEventHandlers[$handler->getId()] = $handler;
    }

    /**
     * 注销 Connected Event Handler
     */
    public function unregisterConnectedEventHandler(?ConnectedEventHandlerInterface $handler = null): void
    {
        if ($handler === null) {
            $this->connectedEventHandlers = [];
        } else {
            unset($this->connectedEventHandlers[$handler->getId()]);
        }
    }

    /**
     * 执行 Loop Event Handlers
     */
    public function executeLoopEventHandlers(MqttClient $mqtt, float $elapsedTime): void
    {
        foreach ($this->loopEventHandlers as $handler) {
            try {
                $handler->handle($mqtt, $elapsedTime);
            } catch (\Throwable $e) {
                // 静默处理异常，避免中断循环
                error_log('Loop event handler error: ' . $e->getMessage());
            }
        }
    }

    /**
     * 执行 Publish Event Handlers
     */
    public function executePublishEventHandlers(
        MqttClient $mqtt,
        string $topic,
        string $message,
        ?int $messageId,
        int $qualityOfService,
        bool $retain
    ): void {
        foreach ($this->publishEventHandlers as $handler) {
            try {
                $handler->handle($mqtt, $topic, $message, $messageId, $qualityOfService, $retain);
            } catch (\Throwable $e) {
                // 静默处理异常，避免中断发布流程
                error_log('Publish event handler error: ' . $e->getMessage());
            }
        }
    }

    /**
     * 执行 Message Received Event Handlers
     */
    public function executeMessageReceivedEventHandlers(
        MqttClient $mqtt,
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retained
    ): void {
        foreach ($this->messageReceivedEventHandlers as $handler) {
            try {
                $handler->handle($mqtt, $topic, $message, $qualityOfService, $retained);
            } catch (\Throwable $e) {
                // 静默处理异常，避免中断消息处理流程
                error_log('Message received event handler error: ' . $e->getMessage());
            }
        }
    }

    /**
     * 执行 Connected Event Handlers
     */
    public function executeConnectedEventHandlers(MqttClient $mqtt, bool $isAutoReconnect): void
    {
        foreach ($this->connectedEventHandlers as $handler) {
            try {
                $handler->handle($mqtt, $isAutoReconnect);
            } catch (\Throwable $e) {
                // 静默处理异常，避免中断连接流程
                error_log('Connected event handler error: ' . $e->getMessage());
            }
        }
    }

    /**
     * 获取所有注册的 Hook
     *
     * @return array<string, HookInterface>
     */
    public function getAllHooks(): array
    {
        return array_merge(
            $this->loopEventHandlers,
            $this->publishEventHandlers,
            $this->messageReceivedEventHandlers,
            $this->connectedEventHandlers
        );
    }

    /**
     * 清空所有 Hook
     */
    public function clearAllHooks(): void
    {
        $this->loopEventHandlers = [];
        $this->publishEventHandlers = [];
        $this->messageReceivedEventHandlers = [];
        $this->connectedEventHandlers = [];
    }
}
