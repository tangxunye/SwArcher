<?php
namespace Swlib\Archer\Exception;
class AddNewTaskFailException extends \Exception {
    public function __construct() {
        parent::__construct('Add new task fail because channel closed unexpectedly');
    }
}