<?php
namespace usualtool\Workerman;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Http\Client;
class Queue{
    private $worker=null;
    private $http=null;
    public function __construct($host='0.0.0.0',$port='5555',$processes='4'){
        $this->worker=new Worker("text://{$host}:{$port}");
        $this->worker->name='UT-Queue';
        $this->worker->count=intval($processes);
        $this->worker->onMessage=array($this,'OnMessage');
        $this->http=new Client();
    }
    public function Run(){
        Worker::runAll();
    }
    public function OnMessage(TcpConnection $connection,$data){
        $array=json_decode($data,true);
        if(!is_array($array) || empty($array['url'])){
            $connection->send('ERR:invalid json {"url":..,"param":{..}}');
            return;
        }
        $url=$array['url'];
        if(!empty($array['param'])){
            $url.= (strpos($url,'?')!==false ? '&' : '?').http_build_query($array['param']);
        }
        $this->http->request($url,array(
            'method'=>'GET',
            'success'=>function($response) use ($connection){
                $connection->send('OK:'.$response->getStatusCode());
            },
            'error'=>function($exception) use ($connection){
                $connection->send('ERR:'.$exception->getMessage());
            }
        ));
    }
}