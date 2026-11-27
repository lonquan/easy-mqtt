<?php

declare(strict_types=1);

namespace EasyMqtt\Exceptions;

use Exception;

/**
 * MQTT 模块基础异常类
 *
 * 所有 MQTT 相关的异常都继承自此类
 */
class MqttException extends Exception
{
    /**
     * 异常上下文信息
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * 设置异常上下文信息
     *
     * @param  array<string, mixed>  $context
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * 获取异常上下文信息
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 创建带上下文的异常实例
     *
     * @param  array<string, mixed>  $context
     *
     * @phpstan-return static
     */
    public static function withContext(string $message, array $context = [], int $code = 0, ?\Throwable $previous = null): static
    {
        /** @phpstan-ignore-next-line */
        $exception = new static($message, $code, $previous);
        $exception->setContext($context);

        return $exception;
    }
}
