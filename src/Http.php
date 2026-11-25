<?php
namespace usualtool\Workerman;
use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use usualtool\Lib\Kernel;
class Http{
    private $worker=null;
    private $static_root='';
    public function __construct($host='0.0.0.0',$port='8888',$count='4'){
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
        $this->worker=new Worker("http://{$host}:{$port}");
        $this->worker->name='UT-Http';
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
        $server=array();
        foreach((array)$request->header() as $key=>$val){
            $server['HTTP_'.strtoupper(str_replace('-','_',$key))]=$val;
        }
        $server['REQUEST_METHOD']=$request->method();
        $server['REQUEST_URI']=$uri;
        $parts=parse_url($uri);
        $server['QUERY_STRING']=isset($parts['query']) ? $parts['query'] : '';
        $server['SCRIPT_NAME']='/index.php';
        $server['REMOTE_ADDR']=$connection->getRemoteIp();
        $server['SERVER_NAME']=isset($server['HTTP_HOST']) ? $server['HTTP_HOST'] : 'localhost';
        $server['SERVER_SOFTWARE']='ut-workerman';
        if(strtolower($server['HTTP_X_FORWARDED_PROTO'] ?? '')==='https'){
            $server['HTTPS']='on';
        }
        $get=is_array($request->get()) ? $request->get() : array();
        $post=is_array($request->post()) ? $request->post() : array();
        $cookie=is_array($request->cookie()) ? $request->cookie() : array();
        try{
            $result=Kernel::Handle(array(
                'server'=>$server,
                'get'=>$get,
                'post'=>$post,
                'cookie'=>$cookie,
                'files'=>array(),
                'session'=>true
            ));
        }catch(\Throwable $e){
            $connection->send(new Response(500,array('Content-Type'=>'text/plain; charset=utf-8'),'Workerman Error:'.$e->getMessage()));
            return;
        }
        $headers=array();
        $cookies=array();
        foreach((array)$result['headers'] as $header){
            $pos=strpos($header,':');
            if($pos===false){
                continue;
            }
            $name=trim(substr($header,0,$pos));
            $value=trim(substr($header,$pos+1));
            if(strcasecmp($name,'Set-Cookie')===0){
                $cookies[]=$value;
            }else{
                $headers[$name]=$value;
            }
        }
        $status=isset($result['status']) && $result['status'] ? intval($result['status']) : 200;
        $response=new Response($status,$headers,$result['output']);
        foreach($cookies as $c){
            $parts=explode(';',$c);
            $kv=explode('=',trim(array_shift($parts)),2);
            $cname=$kv[0];
            $cvalue=isset($kv[1]) ? $kv[1] : '';
            $cmax=0;
            $cpath='/';
            $cdomain='';
            $csecure=false;
            $chttponly=false;
            $csamesite='';
            foreach($parts as $attr){
                $ap=strpos($attr,'=');
                $an=trim($ap===false ? $attr : substr($attr,0,$ap));
                $av=trim($ap===false ? '' : substr($attr,$ap+1));
                $al=strtolower($an);
                if($al==='path'){$cpath=$av;}
                elseif($al==='domain'){$cdomain=$av;}
                elseif($al==='max-age'){$cmax=intval($av);}
                elseif($al==='secure'){$csecure=true;}
                elseif($al==='httponly'){$chttponly=true;}
                elseif($al==='samesite'){$csamesite=$av;}
            }
            $response->cookie($cname,$cvalue,$cmax,$cpath,$cdomain,$csecure,$chttponly,$csamesite);
        }
        $connection->send($response);
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