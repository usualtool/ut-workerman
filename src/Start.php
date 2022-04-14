<?php
namespace usualtool\Workerman;
use Workerman\Worker;
class Start{
    public function __construct(){
        if(strpos(strtolower(PHP_OS), 'win') === 0){
            echo "start.php not support windows, please use start_for_win.bat\n";
        }
        if(!extension_loaded('pcntl')){
            echo "Please install pcntl extension.\r\n";
        }
        if(!extension_loaded('posix')){
            echo "Please install posix extension.\r\n";
        }
        define('GLOBAL_START', 1);
        Worker::runAll();
    }
}
