<?php
namespace usualtool\Workerman;
use Workerman\Worker;
class Start{
    public function __construct($host='',$port=''){
        if(strpos(strtolower(PHP_OS), 'win') === 0){
            exit("start.php not support windows, please use start_for_win.bat\n");
        }
        if(!extension_loaded('pcntl')){
            exit("Please install pcntl extension.\r\n");
        }
        if(!extension_loaded('posix')){
            exit("Please install posix extension.\r\n");
        }
        if(!empty($host) && !empty($port)){
            $this->host=$host.":".$port;
            $this->ip = $host;
            $this->port = $port;
            $this->sport= $this->port+1;
            $register = new \GatewayWorker\Register("text://".$this->ip.":".$this->port);
            $gateway = new \GatewayWorker\Gateway("Websocket://0.0.0.0:".$this->sport);
            $gateway->name = 'Gateway-Websocket';
            $gateway->count = 2;
            $gateway->lanIp = $this->ip;
            $gateway->startPort = 1025;
            $gateway->pingInterval = 10;
            $gateway->pingData = '{"type":"ping"}';
            $gateway->registerAddress = $this->ip.":".$this->port;
            $worker = new \GatewayWorker\BusinessWorker();
            $worker->name = 'UT-BusinessWorker';
            $worker->count = 2;
            $worker->registerAddress = $this->ip.":".$this->port;
            require_once __DIR__.'/Events.php';
            $worker->eventHandler = '\Events';
        }
        define('GLOBAL_START', 1);
        Worker::runAll();
    }
}
