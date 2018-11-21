<?php
namespace Swlib\Archer;
abstract class Task {
    private static $counter = 0;
    protected static $finish_func;
    /**
     * 这里设置的回调函数会在每个Task结束时执行，不论Task是否抛出了异常，不论Task模式
     *
     * @param callable $func
     */
    public static function registerTaskFinishFunc(callable $func): void {
        self::$finish_func = $func;
    }
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
            $return = null;
            if (isset(self::$finish_func))
                (self::$finish_func)($this->id, $ret, null);
        } catch(\Throwable $e) {
            $return = $e;
            if (isset(self::$finish_func))
                (self::$finish_func)($this->id, null, $e);
        }
        unset($this->task_callback);
        unset($this->params);
        \Swlib\Archer\Queue::getInstance()->taskOver();
        return $return;
    }
    abstract public function execute();
}