<?php
namespace Swlib\Archer\Task;
class Defer extends \Swlib\Archer\Task {
    protected $result_receiver;
    public function __construct(callable $task_callback, ?array $params) {
        parent::__construct($task_callback, $params);
        $this->result_receiver = new \Swoole\Coroutine\Channel(1);
    }
    /**
     * 不要手动执行该方法！！！
     */
    public function execute() {
        $ret = null;
        $e = $this->callFunc($ret);
        if (isset($e))
            $this->result_receiver->push($e);
        else
            // 将返回值放入数组中是为了，在pop时，区分开因超时返回的false和用户函数可能返回的false
            $this->result_receiver->push([
                $ret
            ]);
    }
    public function recv(?float $timeout = null) {
        $ret = $this->result_receiver->pop($timeout ?? 0);
        if ($ret === false)
            throw new \Swlib\Archer\Exception\TaskTimeoutException();
        if (is_array($ret))
            return current($ret);
        throw $ret;
    }
}