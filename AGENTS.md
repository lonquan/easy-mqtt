# AGENTS.md

## 适用范围

本文件适用于仓库根目录及其所有子目录。

## 项目定位

这是一个 PHP 库项目，不是完整 Laravel 应用。目标是为 Laravel 提供一个基于 `php-mqtt/client` 的 MQTT 工厂封装，重点在于：

- 稳定的公共 API
- 清晰的配置模型
- 可预测的异常语义
- 对 Hook、日志、Repository、自定义消息封装的支持

修改时优先考虑“库使用者的兼容性”，不要把实现便利性放在公共行为稳定性之前。

## 技术栈与基础约束

- PHP `^8.4`
- `php-mqtt/client ^2.0`
- 测试框架：Pest
- 静态分析：PHPStan（level 6）
- 代码风格：Laravel Pint

代码文件保持现有风格：

- 文件头保留 `declare(strict_types=1);`
- 命名空间为 `EasyMqtt\\...`
- 优先使用已有异常类型和支持类，不随意引入新的抽象层
- 现有注释、README、`docs/` 主要使用中文；新增文档默认也使用中文

## 仓库结构

- `src/Factory.php`
  核心入口，负责配置解析、连接创建、发布、订阅、批量操作、信号处理、Hook 注册。
- `src/Message.php`
  消息值对象，兼顾“发送消息构建”和“订阅消息读取”两种角色，支持 JSON、点号访问、`ArrayAccess`、`JsonSerializable`。
- `src/Support/`
  支持类，包括 `HookManager`、事件处理器、`MessageSerializer`、`ClientIdGenerator`。
- `src/Exceptions/`
  库对外暴露的异常模型。优先复用 `ConfigurationException`、`ConnectionException`、`PublishException`、`SubscriptionException`。
- `src/Contracts/`
  Hook 与消息处理相关接口。若改动这里，通常属于公共 API 变更。
- `src/LaravelMqttFactoryServiceProvider.php`
  Laravel 容器绑定和配置发布入口。
- `config/mqtt.example.php` / `config/env.example`
  配置模板。改动配置能力时必须同步。
- `docs/*.md` / `README.md`
  用户文档与示例。改动公共行为、配置、示例时必须同步。
- `tests/Unit/`
  以 Pest 编写的单元测试。当前测试尽量避免依赖真实 broker。

## 常用命令

首次安装依赖：

```bash
composer install
```

运行测试：

```bash
composer test
```

运行静态分析：

```bash
composer phpstan
```

检查格式：

```bash
composer check-style
```

自动格式化：

```bash
composer fix-style
```

提交前建议至少执行：

```bash
composer check
```

## 修改代码时的核心规则

### 1. 把公共 API 当成稳定接口对待

以下内容默认视为公共能力：

- `Factory` 的公开方法签名与行为
- `Message` 的构造/静态工厂/访问方式
- `Contracts` 下的接口
- `Exceptions` 的类型和消息语义
- `config/mqtt.example.php` 中暴露的配置键

如果必须修改这些内容：

- 先确认是否会破坏现有 README、`docs/`、测试和示例
- 尽量以向后兼容方式扩展，而不是直接重命名或改变参数含义
- 同步补测试

### 2. 异常语义要保持一致

该库已经把错误分类为配置、连接、发布、订阅等领域异常。新增错误分支时：

- 优先抛出已有自定义异常，而不是裸 `Exception`
- 尽量沿用 `withContext()` 和现有静态工厂方法的风格
- 不要吞掉调用者应感知的配置/发布/订阅失败

注意现有设计里有一类异常会被故意隔离：

- Hook 执行异常不会打断主流程
- 订阅回调内部异常会被吞掉以避免中断循环

这类行为属于库契约的一部分，修改前必须非常谨慎。

### 3. 保持 `Message` 的双重角色

`Message` 既用于接收，也用于发送。修改时不要破坏这些事实：

- 传入数组时会编码为 JSON
- 传入字符串时会保留原始内容，并在可能时解析 JSON
- `get()` / `has()` 支持点号语法
- `ArrayAccess` 为只读
- `send()` / `publishTo()` 依赖容器中的 `mqtt-factory`

如果改变消息编码、字符串化、JSON 判定或只读行为，必须补齐测试。

### 4. 配置校验不能只改示例，不改实现

当前代码显式依赖和校验的规则包括：

- 连接必须存在
- `host` 必填
- `reconnect_automatically=true` 与 `use_clean_session=true` 不能同时成立
- 发布主题不能包含 `#`、`+`，且不能以 `$` 开头
- 订阅主题中 `#` 和 `+` 的位置必须符合 MQTT 规则

如果调整配置键或校验逻辑，必须同步：

- `src/Factory.php`
- `config/mqtt.example.php`
- `config/env.example`
- `README.md`
- 对应 `docs/*.md`
- 对应单元测试

### 5. 文档和示例必须与代码一起演进

这个仓库已经有较完整的文档面。以下改动不能只改源码：

- 新增/删除/重命名配置项
- 改变发布、订阅、批量操作、Hook、Message 的使用方式
- 改变 Service Provider 暴露方式
- 改变日志、TLS、Repository 的接入方法

至少检查：

- `README.md`
- `docs/configuration.md`
- `docs/examples.md`
- `docs/hooks.md`
- `docs/logging.md`
- `docs/repository.md`

## 测试约定

- 使用 Pest，风格与 `tests/Unit/*.php` 保持一致。
- 优先为变更附近的类补单元测试，不要只写“快乐路径”。
- 仓库当前测试倾向于 mock/stub，而不是依赖真实 MQTT broker；沿用这个方向。
- 涉及异常、主题校验、消息序列化、Hook 隔离、配置分支时，必须覆盖失败路径。

如果改了以下区域，通常应同时补测试：

- `src/Factory.php`
- `src/Message.php`
- `src/Support/MessageSerializer.php`
- `src/Support/HookManager.php`
- `src/Exceptions/*.php`

## 实现建议

- 先复用现有支持类，再考虑新增类。
- 保持方法职责集中，不把简单逻辑拆成过多文件。
- 新增配置项时优先沿用现有数组结构和命名风格。
- 新增 Hook/Handler 能力时，先确认是否应落在 `Contracts`、`Support`、`Factory` 三层对应位置。
- 不要把 Laravel 运行时依赖扩散到纯值对象和纯支持类，除非仓库现有模式已经如此。

## 变更完成后的最小检查清单

- 相关测试已更新或新增
- `composer check-style` 通过
- `composer phpstan` 通过
- `composer test` 通过
- 相关 README / `docs/` / `config/*.example` 已同步
- 若改动公共 API，说明兼容性影响

## 禁止事项

- 不要引入与当前规模不匹配的大型架构重构
- 不要无理由改变公开方法签名
- 不要让文档示例与真实代码分叉
- 不要把需要显式暴露给调用方的错误静默吞掉
- 不要为了测试方便而削弱真实的配置校验或异常语义
