<?php

namespace App\Http\Controllers\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController {

    /**
     * @param array $data
     * @param string $msg
     * @param int $code
     */
    public function ajaxReturn( $data = [] , $msg = ''  , $code = 0){
        $result = array(
            'code' => $code,  //0成功，1失败，1001未登录
            'msg' => $msg,   //提示信息
            'data' => $data    //数据或其它信息
        );
        return response()->json($result);
    }
    
    public function getXiajiId($user_id)
    {
        $data = Db::table("users")->select("id","parent_id")->get();
        $res = $this->getTree($data,$user_id);
        if(count($res) == 0){
            $res = ["-1"];
        }
        return $res;
    }
    
     /**
     * 递归
     *
     * @param data 要递归的数据
     * @param $pid  上级id
     * @param $idName  表里面要递归的名字
     * @param $level  查看等级
     * @returns {HTMLElement | null}  返回标签数组，方便获取元素
     */
    public function getTree($data,$pid=0,$level=1){
        static $arr=array();
       
        foreach($data as $key=>$value){
          
            if($value->parent_id == $pid){
              //  if($level < 4){
                    $value->level=$level;     //用来作为在模版进行层级的区分
                    $arr[] = $value->id;            //把内容存进去
                   // $level = $level +1;    //第几层
                    $this->getTree($data,$value->id,$level+1);    //回调进行无线递归
               // }
            }
        }
    
        return $arr;


    }
    
    /**
     * @param string $data
     * @param string $info
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function success($msg = ''){
        $result = array(
            'code' => 0,  //0成功，1失败，1001未登录
            'msg' => $msg,   //提示信息
            'data' => []    //数据或其它信息
        );
        return response()->json($result);
    }

    /**
     * @param
     * @return \Illuminate\Http\JsonResponse
     */
    public function error($msg) {
        $result = array(
            'code' => 1,  //0成功，1失败，1001未登录
            'msg' => $msg,   //提示信息
            'data' => []    //数据或其它信息
        );
        return response()->json($result);
    }

    /**
     * @param 警告提示。⚠️
     * @return \Illuminate\Http\JsonResponse
     */
    public function notice($msg) {
        $result = array(
            'code' => 2,  //0成功，1失败，1001未登录
            'msg' => $msg,   //提示信息
            'data' => []    //数据或其它信息
        );
        return response()->json($result);
    }

    /**
     * @param string $data
     * @param string $info
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function outmsg($msg = ''){
        $result = array(
            'code' => 1001,  //0成功，1失败，1001未登录
            'msg' => $msg,   //提示信息
            'data' => []    //数据或其它信息
        );
        return response()->json($result);
    }

    /**
     * @param $paginateObj
     * @return \Illuminate\Http\JsonResponse
     */
    public function layuiData($paginateObj,$extra_data = ''){
        if ($paginateObj->total() >=1){
            return response()->json(['code'=>0,'msg'=>'','count'=>$paginateObj->total(),'data'=>$paginateObj->items(),'extra_data' => $extra_data]);
        }else{
            return response()->json(['code'=>1,'msg'=>'暂无数据','count'=>0,'data'=>[],'extra_data' => $extra_data]);
        }
    }
}