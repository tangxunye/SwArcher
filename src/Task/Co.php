<?php
namespace Swlib\Archer\Task;
class Co extends \Swlib\Archer\Task {
    protected $result_receiver;
    public function __construct(callable $task_callback, ?array $params, \Swoole\Coroutine\Channel $result_receiver) {
        parent::__construct($task_callback, $params);
        $this->result_receiver = $result_receiver;
    }
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

        unset($this->result_receiver);
    }
}