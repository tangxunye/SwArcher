<?php
namespace Swlib\Archer;
abstract class Task {
    private static $counter = 0;
    protected $task_callback, $params, $id;
    public function __construct(callable $task_callback, ?array $params = null) {
        $this->id = ++ self::$counter;
        $this->task_callback = $task_callback;
        $this->params = $params ?? [];
    }
    public function getId(): int {
        return $this->id;
    }
    protected function callFunc(&$ret): ?\Throwable {
        try {
            $ret = ($this->task_callback)(...$this->params);
            unset($this->task_callback);
            unset($this->params);
            \Swlib\Archer\Queue::getInstance()->taskOver();
            return null;
        } catch(\Throwable $e) {
            unset($this->task_callback);
            unset($this->params);
            \Swlib\Archer\Queue::getInstance()->taskOver();
            return $e;
        }
    }
    abstract public function execute();
}