<?php
namespace Swlib\Archer\Task;
class CoPackUnit extends \Swlib\Archer\Task {
    protected $multi_task;
    /**
     * 不在构造方法中设置这个成员，是为了防止循环引用，具体代码逻辑见 \Swlib\Archer\MultiTask->execute
     *
     * @param \Swlib\Archer\MultiTask $multi_task
     */
    public function setMultiTask(\Swlib\Archer\MultiTask $multi_task) {
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