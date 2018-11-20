<?php
namespace Swlib\Archer;
class MultiTask implements \Countable {
    private const STATUS_PREPARING = 0, STATUS_EXECUTING = 1, STATUS_DONE = 2;
    private const TYPE_WAIT_FOR_ALL = 0, TYPE_YIELD_EACH_ONE = 1;
    private static $counter = 0;
    private $id, $result_map, $error_map, $tasks, $status, $size, $result_receiver, $type;
    /**
     * 键值对，用来记录每个Task的执行状态
     *
     * @var array
     */
    private $task_ids;
    public function __construct() {
        $this->id = ++ self::$counter;
        $this->status = self::STATUS_PREPARING;
        $this->tasks = [];
    }
    public function getId(): int {
        return $this->id;
    }
    /**
     * 这个方法不会向队列投递Task，只是会临时记录下来在 execute 时一并投递
     *
     * @param callable $task_callback
     * @param array $params
     * @return int
     */
    public function addTask(callable $task_callback, ?array $params = null): int {
        if ($this->status !== self::STATUS_PREPARING)
            throw new Exception\RuntimeException('Wrong status when adding task:' . $this->status);

        $task = new Task\CoPackUnit($task_callback, $params);
        $this->tasks[] = $task;
        return $task->getId();
    }
    public function count() {
        if ($this->status === self::STATUS_PREPARING)
            return count($this->tasks);
        return $this->size;
    }
    /**
     * 重置容器到初始状态
     *
     * @deprecated 不要使用该方法，在某些情况想下会引发错误
     *
     * @throws Exception\RuntimeException
     * @return self
     */
    public function reset(): self {
        if ($this->status === self::STATUS_EXECUTING)
            throw new Exception\RuntimeException('Wrong status when resetting:' . $this->status);
        $this->id = ++ self::$counter;
        $this->status = self::STATUS_PREPARING;
        $this->tasks = [];
        unset($this->result_map);
        unset($this->error_map);
        return $this;
    }
    private function execute() {
        if ($this->status !== self::STATUS_PREPARING)
            throw new Exception\RuntimeException('Wrong status when executing:' . $this->status);

        if (empty($this->tasks)) {
            $this->status = self::STATUS_DONE;
            return [];
        }

        $this->status = self::STATUS_EXECUTING;

        // 因为稍后要将$this赋值到Task中作为成员变量，为了防止循环引用，将 tasks 从成员变量中删除
        $tasks = $this->tasks;
        unset($this->tasks);
        $this->size = count($tasks);
        $this->result_map = [];
        $this->error_map = [];
        $this->task_ids = [];

        $this->result_receiver = new \Swoole\Coroutine\Channel(
            $this->type === self::TYPE_YIELD_EACH_ONE ? $this->size : 1);

        foreach ($tasks as $task) {
            /**
             *
             * @var Task\CoPackUnit $task
             */
            $task->setMultiTask($this);
            $this->task_ids[$task->getId()] = null;

            if (! Queue::getInstance()->push($task))
                throw new Exception\AddNewTaskFailException();
        }
    }
    /**
     * 投递 addTask 添加的所有 Task，同时当前协程挂起。当该所有Task执行完成后，会恢复投递的协程，并以键值对的形式返回所有Task的返回值
     * 注意1：若Task抛出了任何\Throwable异常，本方法返回的结果集中将不包含该Task对应的id，需要使用getError($id)方法获取异常对象
     * 注意2：Task执行时的协程与当前协程不是同一个
     *
     * @param float $timeout
     *            超时时间，缺省表示不超时
     * @throws Exception\RuntimeException 因状态错误抛出的Exception，这是一种正常情况不应该出现的Exception
     * @throws Exception\AddNewTaskFailException 因channel状态错误AddTask失败，这是一种正常情况不应该出现的Exception
     * @throws Exception\TaskTimeoutException 超时时抛出的Exception，注意这个超时不会影响Task的执行。
     * @return array
     */
    public function executeAndWaitForAll(?float $timeout = null): array {
        if (isset($timeout))
            $start_time = microtime(true);
        $this->type = self::TYPE_WAIT_FOR_ALL;
        $this->execute();

        if (isset($timeout)) {
            // 由于上面的操作可能会发生协程切换占用时间，这里调整一下pop的timeout减少时间误差
            $time_pass = microtime(true) - $start_time;
            if ($time_pass < $timeout) {
                $result = $this->result_receiver->pop($timeout - $time_pass);
                unset($this->result_receiver);
                if ($result === true)
                    return $this->result_map;
            }
            throw new Exception\TaskTimeoutException();
        } else {
            $this->result_receiver->pop();
            unset($this->result_receiver);
            return $this->result_map;
        }
    }
    /**
     * 投递 addTask 添加的所有 Task。
     * 当前协程将会挂起，每有一个Task执行完，当前协程将恢复且其结果就会以以键值对的方式yield出来，然后协程会挂起等待下一个执行完的Task。
     * 注意1：若Task抛出了任何\Throwable异常，本方法将不会yild出该Task对应的键值对，getReturn()获取结果集数组也不会包含，需要使用getError($id)方法获取异常对象
     * 注意2：Task执行时的协程与当前协程不是同一个
     *
     * @param float $timeout
     *            总超时时间，缺省表示不超时。（注意该时间表示花费在本方法内的时间，外界调用该方法处理每个返回值所耗费的时间不计入）
     * @throws Exception\RuntimeException 因状态错误抛出的Exception，这是一种正常情况不应该出现的Exception
     * @throws Exception\AddNewTaskFailException 因channel状态错误AddTask失败，这是一种正常情况不应该出现的Exception
     * @throws Exception\TaskTimeoutException 超时时抛出的Exception，注意这个超时不会影响Task的执行。
     * @return \Generator 迭代完所有项之后，可以通过 getReturn() 获取结果集数组
     */
    public function executeAndYieldEachOne(?float $timeout = null): \Generator {
        if (isset($timeout))
            $start_time = microtime(true);
        $this->type = self::TYPE_YIELD_EACH_ONE;
        $this->execute();

        if (isset($timeout)) {
            $outside_time_cost = 0;
            for($i = 0; $i < $this->size; ++ $i) {
                $time_pass = microtime(true) - $start_time - $outside_time_cost;
                if ($time_pass < $timeout) {
                    $id = $this->result_receiver->pop($timeout - $time_pass);
                    if (is_numeric($id)) {
                        // 若不存在于 $this->result_map 中，表示Task抛出了异常
                        if (array_key_exists($id, $this->result_map)) {
                            $yield_time = microtime(true);
                            yield $id => $this->result_map[$id];
                            $outside_time_cost += microtime(true) - $yield_time;
                        }
                        continue;
                    }
                }
                unset($this->result_receiver);
                throw new Exception\TaskTimeoutException();
            }
        } else {
            for($i = 0; $i < $this->size; ++ $i) {
                $id = $this->result_receiver->pop();
                // 若不存在于 $this->result_map 中，表示Task抛出了异常
                if (array_key_exists($id, $this->result_map))
                    yield $id => $this->result_map[$id];
            }
        }
        $this->status = self::STATUS_DONE;
        unset($this->result_receiver);
        return $this->result_map;
    }
    private function checkRegisterPrecondition(int $id) {
        if ($this->status !== self::STATUS_EXECUTING)
            throw new Exception\RuntimeException('Wrong status when registering result:' . $this->status);
        if (! array_key_exists($id, $this->task_ids))
            throw new Exception\RuntimeException('Task not found when registering result');
        if (array_key_exists($id, $this->result_map))
            throw new Exception\RuntimeException('Result already present when registering result');
    }
    private function notifyReceiver(int $id) {
        if ($this->type === self::TYPE_WAIT_FOR_ALL) {
            if (count($this->result_map) + count($this->error_map) === $this->size) {
                $this->status = self::STATUS_DONE;
                if (isset($this->result_receiver))
                    $this->result_receiver->push(true);
            }
        } elseif (isset($this->result_receiver)) {
            $this->result_receiver->push($id);
        }
    }
    public function registerResult(int $id, $result): void {
        $this->checkRegisterPrecondition($id);
        $this->result_map[$id] = $result;
        $this->notifyReceiver($id);
    }
    public function registerError(int $id, \Throwable $e): void {
        $this->checkRegisterPrecondition($id);
        $this->error_map[$id] = $e;
        $this->notifyReceiver($id);
    }
    public function getError(int $id): ?\Throwable {
        if (isset($this->error_map) && array_key_exists($id, $this->error_map))
            return $this->error_map[$id];
        return null;
    }
    public function getErrorMap(): array {
        if (! isset($this->error_map))
            return [];
        return $this->error_map;
    }
}