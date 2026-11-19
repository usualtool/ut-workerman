<?php
namespace usualtool\Workerman;
use GatewayClient\Gateway;
class Push{
    private $address;
    public function __construct($address='127.0.0.1:1236'){
        $this->address=$address;
        Gateway::$registerAddress=$address;
    }
    public function ToClient($client_id,$message){
        return Gateway::sendToClient($client_id,is_string($message) ? $message : json_encode($message));
    }
    public function ToUid($uid,$message){
        return Gateway::sendToUid($uid,is_string($message) ? $message : json_encode($message));
    }
    public function ToGroup($group,$message){
        return Gateway::sendToGroup($group,is_string($message) ? $message : json_encode($message));
    }
    public function ToAll($message){
        return Gateway::sendToAll(is_string($message) ? $message : json_encode($message));
    }
    public function IsOnline($uid){
        return (bool)Gateway::isUidOnline($uid);
    }
    public function OnlineCount(){
        return (int)Gateway::getAllClientCount();
    }
    public function ClientCountByGroup($group){
        return (int)Gateway::getClientIdCountByGroup($group);
    }
    public function Unbind($client_id){
        Gateway::unbindUid($client_id);
    }
    public function LeaveGroup($client_id,$group){
        Gateway::leaveGroup($client_id,$group);
    }
}
