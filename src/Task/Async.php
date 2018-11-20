<?php
namespace Swlib\Archer\Task;
class Async extends \Swlib\Archer\Task {
    private $finish_callback;
    public function __construct(callable $task_callback, ?array $params, ?callable $finish_callback) {
        parent::__construct($task_callback, $params);
        $this->finish_callback = $finish_callback;
    }
    public function execute() {
        $ret = null;
        $e = $this->callFunc($ret);
        if (isset($e)) {
            if (isset($this->finish_callback)) {
                ($this->finish_callback)($this->id, null, $e);
                unset($this->finish_callback);
            } else {
                trigger_error(
                    "Throwable catched in Atcher async task, but no finish callback found:{$e->getMessage()} in {$e->getFile()}({$e->getLine()})");
            }
        } elseif (isset($this->finish_callback)) {
            ($this->finish_callback)($this->id, $ret, null);
            unset($this->finish_callback);
        }
    }
}