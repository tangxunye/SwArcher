# Archer

[![Php Version](https://img.shields.io/badge/php-%3E=7.1-brightgreen.svg?maxAge=2592000)](https://secure.php.net/)
[![Swoole Version](https://img.shields.io/badge/swoole-%3E=4.2.8-brightgreen.svg?maxAge=2592000)](https://github.com/swoole/swoole-src)
[![Saber License](https://img.shields.io/hexpm/l/plug.svg?maxAge=2592000)](https://github.com/swlib/saber/blob/master/LICENSE)

## 简介

 协程Task弓手, `Swoole人性化组件库`之PHP高性能Task队列, 基于Swoole原生协程, 底层提供无额外I/O的高性能解决方案, 让开发者专注于功能开发, 从繁琐的传统Task队列中解放.

- 基于Swoole协程开发, 以单进程协程实现Swoole Task提供的所有功能
- 人性化使用风格, API简单易用, 符合传统同步代码开发逻辑习惯
- 完备的Exception异常事件, 符合面向对象的基本思路, 避免陷入若类型陷阱
- 多种Task模式（伪异步、协程同步、多任务集合）等，满足各种开发情景

------
<br>

## 安装

最好的安装方法是通过 [Composer](http://getcomposer.org/) 包管理器 :

```shell
composer require swlib/archer
```

------

## 依赖

- **PHP71** or later
- **Swoole 4.2.8 or later**

------
<br>

## 协程调度

Swoole底层实现协程调度, **业务层无需感知**, 开发者可以无感知的**用同步的代码编写方式达到异步IO的效果和超高性能**，避免了传统异步回调所带来的离散的代码逻辑和陷入多层回调中导致代码无法维护。Task队列循环与各Task的执行都处于独立的协程中，不会占用用户自己创建的协程。

需要在`onRequet`, `onReceive`, `onConnect`等事件回调函数中使用, 或是使用go关键字包裹 (`swoole.use_shortname`默认开启).

```php
go(function () {
    echo \Swlib\Archer::taskWait(function (string $target): string {
        return "Hello {$target}";
    }, ['world']);
})
```


------

## 例子

### 伪异步模式
```php
\Swlib\Archer::task(callable $task_callback, ?array $params = null, ?callable $finish_callback = null): int;
```
- `$task_callback` Task闭包，
- `$params` 传入`$task_callback`中的参数，可缺省
- `$finish_callback` Task执行完之后的回调，可缺省，格式如下：

```php
function (int $task_id, $task_return_value, ?\Throwable $e) {
    // $task_id 为\Swlib\Archer::task() 返回的Task id
    // $task_return_value 为Task闭包 $task_callback 的返回值，若没有返回值或抛出了异常，则该项为null
    // $e为Task闭包 $task_callback 中抛出的异常，正常情况下为null
}
```
| 返回模式 | 协程说明 | 异常处理 |
| :-- | :-- | :-- |
| 立即返回 Taskid | $task_callback与$finish_callback处于同一个协程，但与当前协程不处于同一个 | 通过第3个参数传递给$finish_callback，若缺省则会产生一个warnning |
### 协程同步返回模式
```php
\Swlib\Archer::taskWait(callable $task_callback, ?array $params = null, ?float $timeout = null): mixed;
```
- `$task_callback` Task闭包，
- `$params` 传入`$task_callback`中的参数，可缺省
- `$timeout` 超时时间，超时后函数会直接抛出`Swlib\Archer\Exception\TaskTimeoutException`。注意：超时返回后Task仍会继续执行，不会中断，不会移出队列。若缺省则表示不会超时

| 返回模式 | 协程说明 | 异常处理 |
| :-- | :-- | :-- |
| 当前协程挂起，直到Task执行完成并返回结果 | $task_callback与当前协程不是同一个 | 若Task抛出了任何异常，Archer会捕获后在这里抛出。 |
### Task集模式
获取容器：
```php
$container = \Swlib\Archer::getMultiTask();
```
添加Task，但暂时先不投递到队列中执行。返回值为Task id
```php
$container->addTask(callable $task_callback, ?array $params = null): int;
```
两种执行模式：
###### 等待全部结果：投递所有Task进入队列，并等待所有Task全部执行完。返回值为键值对，键为Taskid，值为其对应的返回值
```php
$container->executeAndWaitForAll(?float $timeout = null): array;
```
- `$timeout` 超时时间，超时后函数会直接抛出`Swlib\Archer\Exception\TaskTimeoutException`。注意：超时返回后所有Task仍会继续执行，不会中断，不会移出队列。若缺省则表示不会超时

| 返回模式 | 协程说明 | 异常处理 |
| :-- | :-- | :-- |
| 当前携程挂起，直到所有Task执行完成并返回结果 | 所有Task所处协程均不同 | 若某个Task抛出了任何异常，不会影响其他Task的执行，但在返回值中不会出现该Task id对应的项，需要通过`getError(int $taskid)`或`getErrorMap()`方法获取异常对象 |
###### 先完成先返回：投递所有Task进入队列，各Task的执行结果会根据其完成的顺序，以键值对的形式yield出来
```php
$container->executeAndYieldEachOne(?float $timeout = null): \Generator;
```
- `$timeout` 超时时间，超时后函数会直接抛出`Swlib\Archer\Exception\TaskTimeoutException`（该时间表示花费在本方法内的时间，外界调用该方法处理每个返回值所耗费的时间不计入）。注意：超时返回后所有Task仍会继续执行，不会中断，不会移出队列。若缺省则表示不会超时
- 生成器遍历完成后，可以通过 `Generator->getReturn()` 方法获取返回值的键值对
| 返回模式 | 协程说明 | 异常处理 |
| :-- | :-- | :-- |
| 当前协程将会挂起，每有一个Task执行完，当前协程将恢复且其结果就会以以键值对的方式yield出来，然后协程会挂起等待下一个执行完的Task。 | 所有Task所处协程均不同 | 若某个Task抛出了任何异常，不会影响其他Task的执行，但这个Task不会被`yield`出来，需要通过`getError(int $taskid)`或`getErrorMap()`方法获取异常对象 |

获取某Task抛出的异常（若Task未抛出异常则返回null）
```php
$container->getError(int $id): ?\Throwable;
```
获取所有异常Task与他们抛出的异常，返回值为键值对，键为Taskid，值为其抛出的异常
```php
$container->getErrorMap(): array;
```

## 配置
```php
\Swlib\Archer\Queue::setQueueSize(int $size): void;
\Swlib\Archer\Queue::setConcurrent(int $concurrent): void;
```
- 队列的size，默认为8192。当待执行的Task数量超过size时，再投递Task会导致协程切换，直到待执行的Task数量小于size后才可恢复
- 最大并发数concurrent，默认为2048，表示同时处于执行状态的Task的最大数量。
- 这两个方法，必须在第一次投递任何Task之前调用。建议在 `onWorkerStart` 中调用

## 异常
Archer会抛出以下几种异常：
- `Swlib\Archer\Exception\AddNewTaskFailException` 将task加入队列时发生错误，由 \Swoole\Coroutine\Channel->pop 报错引起，这往往是由内核错误导致的
- `Swlib\Archer\Exception\RuntimeException` Archer内部状态错误，通常由用户错误地调用了底层函数引起
- `Swlib\Archer\Exception\TaskTimeoutException` Task超时，因用户在某些地方设置了`timeout`，Task排队+执行时间超过了该时间引发的异常。用户应该在需要设置`timeout`的地方捕获这个异常以完成超时逻辑。注意Task执行时间超时不会引起Task中断或被移出队列。

------

## IDE Helper

将本项目源文件加入到IDE的 `Include Path` 中.

 (使用composer安装,则可以包含整个vendor文件夹, PHPStorm会自动包含)

良好的注释书写使得Saber完美支持IDE自动提示, 只要在对象后书写箭头符号即可查看所有对象方法名称, 名称都十分通俗易懂, 大量方法都遵循**PSR**规范或是参考[Guzzle](https://github.com/guzzle/guzzle)项目(感谢)而实现.

对于底层Swoole相关类的IDE提示则需要引入eaglewu的[swoole-ide-helper](https://github.com/eaglewu/swoole-ide-helper)(composer在dev环境下会默认安装), 但是该项目为手动维护, 不太完整, 也可以使用[swoft-ide-helper](https://github.com/swoft-cloud/swoole-ide-helper)或:

**Swoole官方的[ide-helper](https://github.com/swoole/ide-helper/)并运行`php dump.php`生成一下.**


------

## 重中之重

**欢迎提交issue和PR.**
