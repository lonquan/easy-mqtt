<?php

declare(strict_types=1);

namespace EasyMqtt\Support;

use EasyMqtt\Contracts\HookInterface;

/**
 * Hook 抽象基类
 *
 * 提供 Hook 的基础实现，包括 ID 生成和启用状态管理
 */
abstract class AbstractHook implements HookInterface
{
    protected string $id;

    protected bool $enabled;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? $this->generateId();
        $this->enabled = true;
    }

    /**
     * 获取 Hook 的唯一标识符
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 检查 Hook 是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * 启用或禁用 Hook
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * 生成唯一的 Hook ID
     */
    protected function generateId(): string
    {
        return uniqid(static::class . '_', true);
    }
}
