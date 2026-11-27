<?php

declare(strict_types=1);

namespace EasyMqtt\Contracts;

/**
 * MQTT Hook 接口
 *
 * 定义所有 Hook 类型的基础接口
 */
interface HookInterface
{
    /**
     * 获取 Hook 的唯一标识符
     */
    public function getId(): string;

    /**
     * 检查 Hook 是否启用
     */
    public function isEnabled(): bool;

    /**
     * 启用或禁用 Hook
     */
    public function setEnabled(bool $enabled): void;
}
