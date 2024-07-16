<?php
/**
 * Created by PhpStorm.
 * User: YSX
 * Date: 2018/12/4
 * Time: 19:08
 */

namespace App\Http\Controllers\Agent;


use App\Agent;
use App\Users;
use Illuminate\Support\Facades\DB;
class AgentController extends Controller
{

    /**代理商信息
     * 可以传用户id也可以传代理商id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function info()
    {
        $agent_id = request('agent_id', 0);
        $user_id  = request('user_id', Users::getUserId());

        if (!$agent_id && !$user_id) {
            return $this->error('参数错误');
        }

        $agent = new Agent();

        if ($agent_id) {
            $agent = $agent->where('id', $agent_id);
        }

        if ($user_id) {
            $user  = Users::find($user_id);
            $agent = $agent->where('id', 0);
        }

        $agent = $agent->first();

        return $this->success($agent);
    }
    
    public function huodaixiaj()
    {
        
        
         $datas = Db::table("agent")->where("parent_agent_id",Agent::getAgentId())->get();
        foreach($datas as $value){
            $value->user_codes = Db::table("users")->where("id",$value->user_id)->value("extension_code");
            if($value->is_lock == 0){
                $value->is_lock = "正常";
            }else{
                $value->is_lock = "锁定";
            }
            
           //统计
           $ids = $this->getXiajiId($value->user_id);
          
           //下级数量
           $value->user_count = count($ids);
           //代理金额调整数量
           $value->dailimoney1 = Db::table("account_log")->whereIn("user_id",$ids)->where("type",7)->where("dail",2)->sum("value");
           //下级系统金额调整数量
           $value->dailimoney2 = Db::table("account_log")->whereIn("user_id",$ids)->where("type",7)->where("dail",1)->sum("value");
           
           //系统前端充值金额
           $value->qdmoney = Db::table("charge_req")->whereIn("uid",$ids)->where("status",2)->sum("amount");
           
           //提现数量
           $value->tixian = Db::table("users_wallet_out")->whereIn("user_id",$ids)->where("status",2)->sum("number");
           
           $ids = [];
        }
        return response()->json(['code'=>0,'count'=>count($datas),'msg'=>'','data'=>$datas]);
    }
    
    public function getXiajiId($user_id)
    {
        session(["zhis"=>[]]);
        $data = Db::table("users")->select("id","parent_id")->get();
        $res = $this->getTree($data,$user_id);
        return session("zhis");
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
    public function getTree($data,$pid=0,$level=1,$zhis=[]){
      
        static $arr=array();
       
        foreach($data as $key=>$value){
          
            if($value->parent_id == $pid){
              //  if($level < 4){
                    $value->level=$level;     //用来作为在模版进行层级的区分
                    $arr[] = $value->id;            //把内容存进去
                   // $level = $level +1;    //第几层
                 //  $zhis[] = $value->id;
                    $zhis = session("zhis");
                    $zhis[] = $value->id;
                    session(["zhis"=>$zhis]);
                    $this->getTree($data,$value->id,$level+1,$zhis);    //回调进行无线递归
               // }
            }
        }
       
        return $zhis;
    }

}