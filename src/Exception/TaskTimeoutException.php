<?php
namespace Swlib\Archer\Exception;
class TaskTimeoutException extends \Exception {
    public function __construct() {
        parent::__construct('Task timeout. Noted that the task itself will continue running normaly.');
    }
}