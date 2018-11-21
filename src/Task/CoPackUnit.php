<?php
namespace Swlib\Archer\Task;
class CoPackUnit extends \Swlib\Archer\Task {
    protected $multi_task;
    public function __construct(callable $task_callback, ?array $params, \Swlib\Archer\MultiTask $multi_task) {
        parent::__construct($task_callback, $params);
        $this->multi_task = $multi_task;
    }
    public function execute() {
        $ret = null;
        $e = $this->callFunc($ret);
        if (isset($e))
            $this->multi_task->registerError($this->id, $e);
        else
            $this->multi_task->registerResult($this->id, $ret);

        unset($this->multi_task);
    }
}