<?php
/**
 * 业务逻辑代码
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），再平滑重启reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);
use \GatewayWorker\Lib\Gateway;
/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events{
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id){
        // 向当前client_id发送数据 
        Gateway::sendToClient($client_id, "Hello $client_id\r\n");
    }
   /**
    * 当WebSocket握手时触发，支持 ws://host:port?uid=x&group=y 直连绑定
    * @param int $client_id 连接id
    * @param array $data 握手信息 get/server/header
    */
   public static function onWebSocketConnect($client_id, $data){
        if(isset($data['get']['uid']) && $data['get']['uid']!==''){
            Gateway::bindUid($client_id, $data['get']['uid']);
        }
        if(isset($data['get']['group']) && $data['get']['group']!==''){
            Gateway::joinGroup($client_id, $data['get']['group']);
        }
   }
   /**
    * 当客户端发来消息时触发
    * 消息协议：JSON {"type":"login|speak|group|private|online|ping",...}
    * @param int $client_id 连接id
    * @param mixed $message 具体消息
    */
   public static function onMessage($client_id, $message){
        $data=json_decode($message, true);
        if(!is_array($data) || empty($data['type'])){
            Gateway::sendToClient($client_id, json_encode(array("type"=>"error","msg"=>"invalid format")));
            return;
        }
        $msg=isset($data["msg"]) ? $data["msg"] : "";
        switch($data['type']){
            case "ping":
                Gateway::sendToClient($client_id, json_encode(array("type"=>"pong")));
                return;
            case "login":
                if(isset($data["uid"]) && $data["uid"]!==""){
                    Gateway::bindUid($client_id, $data["uid"]);
                }
                if(isset($data["group"]) && $data["group"]!==""){
                    Gateway::joinGroup($client_id, $data["group"]);
                }
                Gateway::sendToClient($client_id, json_encode(array("type"=>"login","client_id"=>$client_id,"msg"=>"ok")));
                return;
            case "speak":
                Gateway::sendToAll(json_encode(array("type"=>"speak","from"=>$client_id,"msg"=>$msg)));
                return;
            case "group":
                if(isset($data["group"]) && $data["group"]!==""){
                    Gateway::sendToGroup($data["group"], json_encode(array("type"=>"speak","from"=>$client_id,"msg"=>$msg)));
                }
                return;
            case "private":
                if(isset($data["uid"]) && $data["uid"]!==""){
                    Gateway::sendToUid($data["uid"], json_encode(array("type"=>"speak","from"=>$client_id,"msg"=>$msg)));
                }
                return;
            case "online":
                Gateway::sendToClient($client_id, json_encode(array("type"=>"online","count"=>Gateway::getAllClientCount())));
                return;
            default:
                Gateway::sendToClient($client_id, json_encode(array("type"=>"error","msg"=>"unknown type")));
        }
   }
   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id){
       // 向所有人发送 
       Gateway::sendToAll("$client_id logout\r\n");
   }
}
