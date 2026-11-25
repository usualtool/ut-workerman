<?php
namespace usualtool\Workerman;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Http\Client;
class Tcp{
    private $worker=null;
    private $http=null;
    private $callback='';
    private $type='text';
    public function __construct($host='0.0.0.0',$port='6000',$callback='',$type='text',$processes='4'){
        $this->callback=$callback;
        $this->type=in_array($type,array('text','frame')) ? $type : 'text';
        $this->worker=new Worker(($this->type==='frame' ? 'frame://' : 'text://')."{$host}:{$port}");
        $this->worker->name='UT-Tcp';
        $this->worker->count=intval($processes);
        $this->worker->onMessage=array($this,'OnMessage');
        $this->http=new Client();
    }
    public function Run(){
        Worker::runAll();
    }
    public function OnMessage(TcpConnection $connection,$data){
        $ip=$connection->getRemoteIp();
        if($this->callback!==''){
            $this->http->request($this->callback,array(
                'method'=>'POST',
                'data'=>json_encode(array('ip'=>$ip,'data'=>$data)),
                'headers'=>array('Content-Type'=>'application/json'),
                'success'=>function($response) use ($connection){
                    $connection->send('OK:'.$response->getStatusCode());
                },
                'error'=>function($exception) use ($connection){
                    $connection->send('ERR:'.$exception->getMessage());
                }
            ));
            return;
        }
        $array=json_decode(is_string($data) ? $data : '',true);
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