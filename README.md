# Archer

[![Php Version](https://img.shields.io/badge/php-%3E=7.1-brightgreen.svg?maxAge=2592000)](https://secure.php.net/)
[![Swoole Version](https://img.shields.io/badge/swoole-%3E=4.2.8-brightgreen.svg?maxAge=2592000)](https://github.com/swoole/swoole-src)
[![Saber License](https://img.shields.io/hexpm/l/plug.svg?maxAge=2592000)](https://github.com/swlib/saber/blob/master/LICENSE)

## 简介

 协程Task弓兵, `Swoole人性化组件库`之PHP高性能Task队列, 基于Swoole原生协程, 底层提供无额外I/O的高性能解决方案, 让开发者专注于功能开发, 从繁琐的传统Task队列或协程并发旋涡中解放.

- 基于Swoole协程开发, 以单进程协程实现Swoole Task提供的所有功能
- 人性化使用风格, API简单易用, 符合传统同步代码开发逻辑习惯
- 完备的Exception异常事件, 符合面向对象的基本思路, 避免陷入若类型陷阱
- 多种Task模式（伪异步、协程同步、Defer模式多任务集合）等，满足各种开发情景
- 轻松将任意协程代码变为Defer模式，不用刻意修改为defer()与recv()。
- 可以将任意协程代码并发执行而不改变原先设计模式。

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

Swoole底层实现协程调度, **业务层无需感知**, 开发者可以无感知的**用同步的代码编写方式达到异步IO的效果和超高性能**，避免了传统异步回调所带来的离散的代码逻辑和陷入多层回调中导致代码无法维护。Task队列循环与各Task的执行都处于独立的协程中，不会占用用户自己创建的协程。可以将任意协程变为Defer模式，无需手动触发defer()与recv()。

需要在`onRequet`, `onReceive`, `onConnect`等事件回调函数中使用, 或是使用go关键字包裹 (`swoole.use_shortname`默认开启).

```php
go(function () {
    echo \Swlib\Archer::taskWait(function (string $target): string {
        co::sleep(5);
        return "Hello {$target}";
    }, ['world']);
})
```


------

## 接口

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
### Defer模式
获取Task：
```php
/*定义*/ \Swlib\Archer::taskDefer(callable $task_callback, ?array $params = null): \Swlib\Archer\Task\Defer;
$task = \Swlib\Archer::taskDefer($task_callback, ['foo', 'bar']);
```

| 返回模式 | 协程说明 | 异常处理 |
| :-- | :-- | :-- |
| 立即返回Task对象 | $task_callback与当前协程不是同一个 | 若Task抛出了任何异常，Archer会捕获后在执行recv时抛出。 |

获取执行结果：
```
/*定义*/ \Swlib\Archer\Task\Defer->recv(?float $timeout = null);
$task->recv(0.5);
```

| 返回模式 | 协程说明 | 异常处理 |
| :-- | :-- | :-- |
| 若Task已执行完则直接返回结果。否则协程挂起，等待执行完毕后恢复并返回结果。 | $task_callback与当前协程不是同一个 | 若Task抛出了任何异常，Archer会捕获后会在此处抛出。 |
### Task集模式
获取容器：
```php
$container = \Swlib\Archer::getMultiTask();
```
向队列投递Task并立即返回Task id。
```php
$container->addTask(callable $task_callback, ?array $params = null): int;
```
两种执行模式：
###### 等待全部结果：等待所有Task全部执行完。返回值为键值对，键为Taskid，值为其对应的返回值
```php
$container->waitForAll(?float $timeout = null): array;
```
- `$timeout` 超时时间，超时后函数会直接抛出`Swlib\Archer\Exception\TaskTimeoutException`。注意：超时返回后所有Task仍会继续执行，不会中断，不会移出队列。若缺省则表示不会超时

| 返回模式 | 协程说明 | 异常处理 |
| :-- | :-- | :-- |
| 若运行时所有Task已执行完，则会直接以键值对的形式返回所有Task的返回值。否则当前协程挂起。当该所有Task执行完成后，会恢复投递的协程，并返回结果。 | 所有Task所处协程均不同 | 若某个Task抛出了任何异常，不会影响其他Task的执行，但在返回值中不会出现该Task id对应的项，需要通过`getError(int $taskid)`或`getErrorMap()`方法获取异常对象 |
###### 先完成先返回：各Task的执行结果会根据其完成的顺序，以键值对的形式yield出来
```php
$container->yieldEachOne(?float $timeout = null): \Generator;
```
- `$timeout` 超时时间，超时后函数会直接抛出`Swlib\Archer\Exception\TaskTimeoutException`（该时间表示花费在本方法内的时间，外界调用该方法处理每个返回值所耗费的时间不计入）。注意：超时返回后所有Task仍会继续执行，不会中断，不会移出队列。若缺省则表示不会超时
- 生成器遍历完成后，可以通过 `Generator->getReturn()` 方法获取返回值的键值对

| 返回模式 | 协程说明 | 异常处理 |
| :-- | :-- | :-- |
| 若运行时已经有些Task已执行完，则会按执行完毕的顺序将他们先yield出来。若这之后仍存在未执行完的Task，则当前协程将会挂起，每有一个Task执行完，当前协程将恢复且其结果就会以以键值对的方式yield出来，然后协程会挂起等待下一个执行完的Task。 | 所有Task所处协程均不同 | 若某个Task抛出了任何异常，不会影响其他Task的执行，但这个Task不会被`yield`出来，需要通过`getError(int $taskid)`或`getErrorMap()`方法获取异常对象 |

获取某Task抛出的异常（若Task未抛出异常则返回null）
```php
$container->getError(int $id): ?\Throwable;
```
获取所有异常Task与他们抛出的异常，返回值为键值对，键为Taskid，值为其抛出的异常
```php
$container->getErrorMap(): array;
```

### 注册一个全局回调函数
```php
\Swlib\Archer\Task::registerTaskFinishFunc(callable $func): void;
```
这里注册的回调函数会在每个Task结束时执行，不论Task是否抛出了异常，不论Task模式，格式如下：
```php
function (int $task_id, $task_return_value, ?\Throwable $e) {
    // $task_id 为\Swlib\Archer::task()或\Swlib\Archer\MultiTask->addTask() 返回的Task id。\Swlib\Archer::taskWait()由于无法获取Taskid，所以可以忽略该项。
    // $task_return_value 为Task闭包 $task_callback 的返回值，若没有返回值或抛出了异常，则该项为null
    // $e为Task闭包 $task_callback 中抛出的异常，正常情况下为null
}
```
不建议在该方法中执行会引起阻塞或协程切换的操作，因为会影响到Task运行结果的传递效率；也不要在该方法中抛出任何异常，会导致catch不到而使进程退出。  
该方法所处的协程与Task所处的协程为同一个，所以可以利用该函数清理执行Task所留下的Context。  
- Task为伪异步模式时，该方法会在 $finish_callback 之前执行
- Task为协程同步返回模式或集模式时，该方法会在返回或抛出异常给原协程之前调用。

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

## 例子
(待补充)

------

## 重中之重

**欢迎提交issue和PR.**

