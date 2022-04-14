<?php
namespace usualtool\workerman;
use Workerman\Worker;
class Start{
    public function __construct($host=''){
        if(strpos(strtolower(PHP_OS), 'win') === 0){
            echo "start.php not support windows, please use start_for_win.bat\n";
        }
        if(!extension_loaded('pcntl')){
            echo "Please install pcntl extension.\r\n";
        }
        if(!extension_loaded('posix')){
            echo "Please install posix extension.\r\n";
        }
        if(!empty($host)){
            $this->host=$host;
            $this->ip = explode(':',$host)[0];
            $this->port = explode(':',$host)[1];
            $this->sport= $this->port+1;
            $register = new \GatewayWorker\Register("text://0.0.0.0:".$this->port);
            $gateway = new \GatewayWorker\Gateway("Websocket://0.0.0.0:".$this->sport);
            $gateway->name = 'UTGateway';
            $gateway->count = 2;
            $gateway->lanIp = $this->ip;
            $gateway->startPort = 1025;
            $gateway->pingInterval = 10;
            $gateway->pingData = '{"type":"ping"}';
            $gateway->registerAddress = "127.0.0.1:".$this->port;
            $worker = new \GatewayWorker\BusinessWorker();
            $worker->name = 'UTBusinessWorker';
            $worker->count = 2;
            $worker->registerAddress = "127.0.0.1:".$this->port;
            //$worker->eventHandler = '\usualtool\Workerman\Events';
        }
        define('GLOBAL_START', 1);
        Worker::runAll();
    }
}
