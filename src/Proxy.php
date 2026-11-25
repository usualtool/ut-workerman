<?php
namespace usualtool\Workerman;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
class Proxy{
    private $worker=null;
    private $sock='';
    private $static_root='';
    public function __construct($host='0.0.0.0',$port='80',$sock='php-fpm.sock',$count='4'){
        if(!defined('UTF_ROOT')){
            $root=getenv('UT_ROOT') ?: '';
            if($root===''){
                $dir=__DIR__;
                for($i=0;$i<8;$i++){
                    if(is_file($dir.'/.ut.config')){
                        $root=$dir;
                        break;
                    }
                    $next=dirname($dir);
                    if($next===$dir){
                        break;
                    }
                    $dir=$next;
                }
            }
            define('UTF_ROOT',$root==='' ? dirname(__DIR__,2) : $root);
        }
        require_once UTF_ROOT.'/autoload.php';
        $this->static_root=defined('OPEN_ROOT') ? OPEN_ROOT : UTF_ROOT.'/open';
        $this->sock=$sock;
        $this->worker=new Worker("http://{$host}:{$port}");
        $this->worker->name='UT-Proxy';
        $this->worker->count=intval($count);
        $this->worker->onMessage=array($this,'OnMessage');
    }
    public function Run(){
        Worker::runAll();
    }
    public function OnMessage(TcpConnection $connection,Request $request){
        $uri=$request->uri();
        $path=parse_url($uri,PHP_URL_PATH);
        $file=$this->static_root.$path;
        if($path!=='/' && $path!=='' && is_file($file) && strpos(realpath($file),realpath($this->static_root))===0){
            $connection->send(new Response(200,array('Content-Type'=>$this->Mime($file)),file_get_contents($file)));
            return;
        }
        $this->FastCgiPass($connection,$request,$path);
    }
    private function FastCgiPass(TcpConnection $connection,Request $request,$path){
        $remote=new AsyncTcpConnection("unix:///tmp/{$this->sock}");
        $remote->buffer='';
        $remote->out='';
        $remote->client=$connection;
        $params=array(
            'SCRIPT_FILENAME'=>$this->static_root.'/index.php',
            'SCRIPT_NAME'=>'/index.php',
            'DOCUMENT_ROOT'=>$this->static_root,
            'DOCUMENT_URI'=>$path,
            'REQUEST_URI'=>$request->uri(),
            'REQUEST_METHOD'=>$request->method(),
            'QUERY_STRING'=>(string)(parse_url($request->uri(),PHP_URL_QUERY)),
            'SERVER_PROTOCOL'=>'HTTP/1.1',
            'SERVER_SOFTWARE'=>'ut-workerman',
            'REMOTE_ADDR'=>$connection->getRemoteIp(),
            'REMOTE_PORT'=>(string)$connection->getRemotePort(),
            'SERVER_NAME'=>(string)($request->header('host') ?? 'localhost'),
            'REDIRECT_STATUS'=>'200',
            'CONTENT_TYPE'=>(string)($request->header('content-type') ?? ''),
            'CONTENT_LENGTH'=>(string)strlen((string)$request->rawBody())
        );
        foreach((array)$request->header() as $key=>$val){
            if($key==='content-type' || $key==='content-length'){
                continue;
            }
            $params['HTTP_'.strtoupper(str_replace('-','_',$key))]=$val;
        }
        $body=(string)$request->rawBody();
        $remote->onConnect=function($conn) use ($params,$body){
            $conn->send($this->Record(1,1,pack('nc',1,0).str_repeat("\0",5)));
            $conn->send($this->Record(1,4,$this->Pairs($params)));
            $conn->send($this->Record(1,4,''));
            $conn->send($this->Record(1,5,$body));
            $conn->send($this->Record(1,5,''));
        };
        $remote->onMessage=function($conn,$data){
            $conn->buffer.=$data;
            while(true){
                if(strlen($conn->buffer)<8){
                    return;
                }
                $head=unpack('Cversion/Ctype/nreqid/nlen/Cpad/Cres',substr($conn->buffer,0,8));
                $total=8+$head['len']+$head['pad'];
                if(strlen($conn->buffer)<$total){
                    return;
                }
                $payload=substr($conn->buffer,8,$head['len']);
                $conn->buffer=substr($conn->buffer,$total);
                if($head['type']===6){
                    $conn->out.=$payload;
                }elseif($head['type']===7){
                    $errlog=defined('APP_ROOT') ? APP_ROOT.'/log/fastcgi_err.log' : '/tmp/ut_fastcgi_err.log';
                    file_put_contents($errlog,$payload,FILE_APPEND);
                }elseif($head['type']===3){
                    $this->Respond($conn->client,$conn->out);
                    $conn->close();
                    return;
                }
            }
        };
        $remote->onError=function($conn,$code,$msg) use ($connection){
            $connection->send(new Response(502,array('Content-Type'=>'text/plain; charset=utf-8'),'FastCGI Error:'.$msg));
            $conn->close();
        };
        $remote->connect();
    }
    private function Respond(TcpConnection $connection,$out){
        $pos=strpos($out,"\r\n\r\n");
        $status=200;
        $headers=array();
        if($pos!==false){
            $rawHeaders=explode("\r\n",substr($out,0,$pos));
            $body=substr($out,$pos+4);
        }else{
            $rawHeaders=array();
            $body=$out;
        }
        foreach($rawHeaders as $line){
            $colon=strpos($line,':');
            if($colon===false){
                continue;
            }
            $name=trim(substr($line,0,$colon));
            $value=trim(substr($line,$colon+1));
            if(strcasecmp($name,'Status')===0){
                $status=intval($value);
            }else{
                $headers[$name]=$value;
            }
        }
        if(!isset($headers['Content-Type'])){
            $headers['Content-Type']='text/html; charset=utf-8';
        }
        $connection->send(new Response($status,$headers,$body));
    }
    private function Pairs($params){
        $out='';
        foreach($params as $name=>$value){
            $nlen=strlen($name);
            $vlen=strlen($value);
            $out.=($nlen<128 ? chr($nlen) : pack('N',$nlen|0x80000000)).($vlen<128 ? chr($vlen) : pack('N',$vlen|0x80000000)).$name.$value;
        }
        return $out;
    }
    private function Record($reqid,$type,$content){
        return pack('CCnnCx7',1,$type,$reqid,strlen($content),0).$content;
    }
    private function Mime($file){
        $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
        $map=array(
            'html'=>'text/html; charset=utf-8','htm'=>'text/html; charset=utf-8','css'=>'text/css','js'=>'application/javascript',
            'json'=>'application/json','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif',
            'svg'=>'image/svg+xml','ico'=>'image/x-icon','woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf',
            'mp3'=>'audio/mpeg','mp4'=>'video/mp4','pdf'=>'application/pdf','txt'=>'text/plain; charset=utf-8'
        );
        return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
    }
}