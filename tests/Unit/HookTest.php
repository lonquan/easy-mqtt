<?php

declare(strict_types=1);

use PhpMqtt\Client\MqttClient;
use EasyMqtt\Support\HookManager;
use EasyMqtt\Support\AbstractHook;
use EasyMqtt\Contracts\HookInterface;
use EasyMqtt\Support\LoopEventHandler;
use EasyMqtt\Support\PublishEventHandler;
use EasyMqtt\Support\ConnectedEventHandler;
use EasyMqtt\Contracts\LoopEventHandlerInterface;
use EasyMqtt\Support\MessageReceivedEventHandler;
use EasyMqtt\Contracts\PublishEventHandlerInterface;
use EasyMqtt\Contracts\ConnectedEventHandlerInterface;
use EasyMqtt\Contracts\MessageReceivedEventHandlerInterface;

beforeEach(function () {
    $this->mockMqttClient = Mockery::mock(MqttClient::class);
});

describe('AbstractHook', function () {
    it('生成唯一的 ID', function () {
        $hook1 = new TestHook;
        $hook2 = new TestHook;

        expect($hook1->getId())->not->toBe($hook2->getId());
        expect($hook1->getId())->toContain('TestHook');
    });

    it('可以使用自定义 ID', function () {
        $customId = 'custom-hook-id';
        $hook = new TestHook($customId);

        expect($hook->getId())->toBe($customId);
    });

    it('默认启用状态', function () {
        $hook = new TestHook;

        expect($hook->isEnabled())->toBeTrue();
    });

    it('可以设置启用状态', function () {
        $hook = new TestHook;

        $hook->setEnabled(false);
        expect($hook->isEnabled())->toBeFalse();

        $hook->setEnabled(true);
        expect($hook->isEnabled())->toBeTrue();
    });
});

describe('LoopEventHandler', function () {
    it('实现 LoopEventHandlerInterface', function () {
        $handler = new LoopEventHandler(function () {
        });

        expect($handler)->toBeInstanceOf(LoopEventHandlerInterface::class);
        expect($handler)->toBeInstanceOf(HookInterface::class);
    });

    it('执行回调函数', function () {
        $executed = false;
        $handler = new LoopEventHandler(function ($mqtt, $elapsedTime) use (&$executed) {
            $executed = true;
            expect($mqtt)->toBe($this->mockMqttClient);
            expect($elapsedTime)->toBe(123.45);
        });

        $handler->handle($this->mockMqttClient, 123.45);

        expect($executed)->toBeTrue();
    });

    it('禁用时不执行回调', function () {
        $executed = false;
        $handler = new LoopEventHandler(function () use (&$executed) {
            $executed = true;
        });

        $handler->setEnabled(false);
        $handler->handle($this->mockMqttClient, 123.45);

        expect($executed)->toBeFalse();
    });
});

describe('PublishEventHandler', function () {
    it('实现 PublishEventHandlerInterface', function () {
        $handler = new PublishEventHandler(function () {
        });

        expect($handler)->toBeInstanceOf(PublishEventHandlerInterface::class);
        expect($handler)->toBeInstanceOf(HookInterface::class);
    });

    it('执行回调函数', function () {
        $executed = false;
        $handler = new PublishEventHandler(function ($mqtt, $topic, $message, $messageId, $qos, $retain) use (&$executed) {
            $executed = true;
            expect($mqtt)->toBe($this->mockMqttClient);
            expect($topic)->toBe('test/topic');
            expect($message)->toBe('test message');
            expect($messageId)->toBe(123);
            expect($qos)->toBe(1);
            expect($retain)->toBeTrue();
        });

        $handler->handle($this->mockMqttClient, 'test/topic', 'test message', 123, 1, true);

        expect($executed)->toBeTrue();
    });

    it('禁用时不执行回调', function () {
        $executed = false;
        $handler = new PublishEventHandler(function () use (&$executed) {
            $executed = true;
        });

        $handler->setEnabled(false);
        $handler->handle($this->mockMqttClient, 'test/topic', 'test message', 123, 1, true);

        expect($executed)->toBeFalse();
    });
});

describe('MessageReceivedEventHandler', function () {
    it('实现 MessageReceivedEventHandlerInterface', function () {
        $handler = new MessageReceivedEventHandler(function () {
        });

        expect($handler)->toBeInstanceOf(MessageReceivedEventHandlerInterface::class);
        expect($handler)->toBeInstanceOf(HookInterface::class);
    });

    it('执行回调函数', function () {
        $executed = false;
        $handler = new MessageReceivedEventHandler(function ($mqtt, $topic, $message, $qos, $retained) use (&$executed) {
            $executed = true;
            expect($mqtt)->toBe($this->mockMqttClient);
            expect($topic)->toBe('test/topic');
            expect($message)->toBe('received message');
            expect($qos)->toBe(2);
            expect($retained)->toBeFalse();
        });

        $handler->handle($this->mockMqttClient, 'test/topic', 'received message', 2, false);

        expect($executed)->toBeTrue();
    });

    it('禁用时不执行回调', function () {
        $executed = false;
        $handler = new MessageReceivedEventHandler(function () use (&$executed) {
            $executed = true;
        });

        $handler->setEnabled(false);
        $handler->handle($this->mockMqttClient, 'test/topic', 'received message', 2, false);

        expect($executed)->toBeFalse();
    });
});

describe('ConnectedEventHandler', function () {
    it('实现 ConnectedEventHandlerInterface', function () {
        $handler = new ConnectedEventHandler(function () {
        });

        expect($handler)->toBeInstanceOf(ConnectedEventHandlerInterface::class);
        expect($handler)->toBeInstanceOf(HookInterface::class);
    });

    it('执行回调函数', function () {
        $executed = false;
        $handler = new ConnectedEventHandler(function ($mqtt, $isAutoReconnect) use (&$executed) {
            $executed = true;
            expect($mqtt)->toBe($this->mockMqttClient);
            expect($isAutoReconnect)->toBeTrue();
        });

        $handler->handle($this->mockMqttClient, true);

        expect($executed)->toBeTrue();
    });

    it('禁用时不执行回调', function () {
        $executed = false;
        $handler = new ConnectedEventHandler(function () use (&$executed) {
            $executed = true;
        });

        $handler->setEnabled(false);
        $handler->handle($this->mockMqttClient, false);

        expect($executed)->toBeFalse();
    });
});

describe('HookManager', function () {
    beforeEach(function () {
        $this->hookManager = new HookManager;
    });

    it('注册和注销 Loop Event Handler', function () {
        $handler1 = new LoopEventHandler(function () {
        });
        $handler2 = new LoopEventHandler(function () {
        });

        $this->hookManager->registerLoopEventHandler($handler1);
        $this->hookManager->registerLoopEventHandler($handler2);

        expect($this->hookManager->getAllHooks())->toHaveCount(2);

        $this->hookManager->unregisterLoopEventHandler($handler1);
        expect($this->hookManager->getAllHooks())->toHaveCount(1);

        $this->hookManager->unregisterLoopEventHandler();
        expect($this->hookManager->getAllHooks())->toHaveCount(0);
    });

    it('注册和注销 Publish Event Handler', function () {
        $handler1 = new PublishEventHandler(function () {
        });
        $handler2 = new PublishEventHandler(function () {
        });

        $this->hookManager->registerPublishEventHandler($handler1);
        $this->hookManager->registerPublishEventHandler($handler2);

        expect($this->hookManager->getAllHooks())->toHaveCount(2);

        $this->hookManager->unregisterPublishEventHandler($handler1);
        expect($this->hookManager->getAllHooks())->toHaveCount(1);

        $this->hookManager->unregisterPublishEventHandler();
        expect($this->hookManager->getAllHooks())->toHaveCount(0);
    });

    it('注册和注销 Message Received Event Handler', function () {
        $handler1 = new MessageReceivedEventHandler(function () {
        });
        $handler2 = new MessageReceivedEventHandler(function () {
        });

        $this->hookManager->registerMessageReceivedEventHandler($handler1);
        $this->hookManager->registerMessageReceivedEventHandler($handler2);

        expect($this->hookManager->getAllHooks())->toHaveCount(2);

        $this->hookManager->unregisterMessageReceivedEventHandler($handler1);
        expect($this->hookManager->getAllHooks())->toHaveCount(1);

        $this->hookManager->unregisterMessageReceivedEventHandler();
        expect($this->hookManager->getAllHooks())->toHaveCount(0);
    });

    it('注册和注销 Connected Event Handler', function () {
        $handler1 = new ConnectedEventHandler(function () {
        });
        $handler2 = new ConnectedEventHandler(function () {
        });

        $this->hookManager->registerConnectedEventHandler($handler1);
        $this->hookManager->registerConnectedEventHandler($handler2);

        expect($this->hookManager->getAllHooks())->toHaveCount(2);

        $this->hookManager->unregisterConnectedEventHandler($handler1);
        expect($this->hookManager->getAllHooks())->toHaveCount(1);

        $this->hookManager->unregisterConnectedEventHandler();
        expect($this->hookManager->getAllHooks())->toHaveCount(0);
    });

    it('执行所有类型的 Handler', function () {
        $loopExecuted = false;
        $publishExecuted = false;
        $messageReceivedExecuted = false;
        $connectedExecuted = false;

        $this->hookManager->registerLoopEventHandler(new LoopEventHandler(function () use (&$loopExecuted) {
            $loopExecuted = true;
        }));

        $this->hookManager->registerPublishEventHandler(new PublishEventHandler(function () use (&$publishExecuted) {
            $publishExecuted = true;
        }));

        $this->hookManager->registerMessageReceivedEventHandler(new MessageReceivedEventHandler(function () use (&$messageReceivedExecuted) {
            $messageReceivedExecuted = true;
        }));

        $this->hookManager->registerConnectedEventHandler(new ConnectedEventHandler(function () use (&$connectedExecuted) {
            $connectedExecuted = true;
        }));

        $this->hookManager->executeLoopEventHandlers($this->mockMqttClient, 123.45);
        $this->hookManager->executePublishEventHandlers($this->mockMqttClient, 'test/topic', 'test message', 123, 1, true);
        $this->hookManager->executeMessageReceivedEventHandlers($this->mockMqttClient, 'test/topic', 'received message', 2, false);
        $this->hookManager->executeConnectedEventHandlers($this->mockMqttClient, true);

        expect($loopExecuted)->toBeTrue();
        expect($publishExecuted)->toBeTrue();
        expect($messageReceivedExecuted)->toBeTrue();
        expect($connectedExecuted)->toBeTrue();
    });

    it('异常处理不影响其他 Handler', function () {
        $executed = false;

        $this->hookManager->registerLoopEventHandler(new LoopEventHandler(function () {
            throw new Exception('Test exception');
        }));

        $this->hookManager->registerLoopEventHandler(new LoopEventHandler(function () use (&$executed) {
            $executed = true;
        }));

        // 不应该抛出异常
        expect(fn () => $this->hookManager->executeLoopEventHandlers($this->mockMqttClient, 123.45))->not->toThrow(Exception::class);

        expect($executed)->toBeTrue();
    });

    it('清空所有 Hook', function () {
        $this->hookManager->registerLoopEventHandler(new LoopEventHandler(function () {
        }));
        $this->hookManager->registerPublishEventHandler(new PublishEventHandler(function () {
        }));
        $this->hookManager->registerMessageReceivedEventHandler(new MessageReceivedEventHandler(function () {
        }));
        $this->hookManager->registerConnectedEventHandler(new ConnectedEventHandler(function () {
        }));

        expect($this->hookManager->getAllHooks())->toHaveCount(4);

        $this->hookManager->clearAllHooks();

        expect($this->hookManager->getAllHooks())->toHaveCount(0);
    });
});

// 测试用的 Hook 实现
class TestHook extends AbstractHook
{
    public function __construct(?string $id = null)
    {
        parent::__construct($id);
    }
}
