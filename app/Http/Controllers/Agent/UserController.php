<?php

/**
 * Created by PhpStorm.
 * User: YSX
 * Date: 2018/12/4
 * Time: 16:36
 */

namespace App\Http\Controllers\Agent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\{AccountLog, Agent, Currency, Users, UsersWalletOut};

class UserController extends Controller
{

    //用户管理
    public function index()
    {
        //某代理商下用户时
        $parent_id = request()->get('parent_id', 0);
        //币币  
        $legal_currencies = Currency::get();
        return view("agent.user.index", ['parent_id' => $parent_id, 'legal_currencies' => $legal_currencies]);
    }

    //用户列表
    public function lists(Request $request)
    {
       
      
        
        $limit = $request->get('limit', 10);
        $id = request()->input('id', 0);
        $parent_id = request()->input('parent_id', 0);
        $account_number = request()->input('account_number', '');
        $start = request()->input('start', '');
        $end = request()->input('end', '');

        $users = new Users();

        $users = $users->leftjoin("user_real", "users.id", "=", "user_real.user_id");

        if ($id) {
            $users = $users->where('users.id', $id);
        }
        // if ($parent_id > 0) {
        //     $users = $users->where('users.agent_note_id', $parent_id);
        // }
        if ($account_number) {
           $users = $users->where("users.account_number","like",'%' .$account_number. '%');
        }
        if (!empty($start) && !empty($end)) {
            $users->whereBetween('users.time', [strtotime($start . ' 0:0:0'), strtotime($end . ' 23:59:59')]);
        }
      
      
        $user_id = Db::table("agent")->where("id",Agent::getAgentId())->value("user_id");
        $ids =  implode(",",$this->getXiajiId($user_id));
        $users = $users->whereRaw("users.id in (".$ids.")");

        $list = $users->select("users.*", "user_real.card_id")->orderBy("users.id","desc")->paginate($limit);
        
        
        foreach($list as $v){
             $v->micro_balance = Db::table("users_wallet")->where("currency",3)->where("user_id",$v->id)->value("micro_balance");
        }

        return $this->layuiData($list);
    }

    /**
     * 获取用户管理的统计
     * @param Request $r
     */
    public function get_user_num(Request $request)
    {

        $id             = request()->input('id', 0);
        $account_number = request()->input('account_number', '');
        $parent_id            = request()->input('parent_id', 0);//代理商id
        $start = request()->input('start', '');
        $end = request()->input('end', '');
        $currency_id = request()->input('currency_id', '');

        $users = new Users();

        if ($id) {
            $users = $users->where('id', $id);
        }
        if ($parent_id > 0) {
            $users = $users->where('agent_note_id', $parent_id);
        }
        if ($account_number) {
            $users = $users->where('account_number', $account_number);
        }
        if (!empty($start) && !empty($end)) {
            $users->whereBetween('time', [strtotime($start . ' 0:0:0'), strtotime($end . ' 23:59:59')]);
        }

        $user_id = Db::table("agent")->where("id",Agent::getAgentId())->value("user_id");
        $uid = $this->getXiajiId($user_id);
        $ids =  implode(",",$uid);
        $users = $users->whereRaw("users.id in (".$ids.")");
        
        
        $users_id = $users->get()->pluck('id')->all();
        $_daili = 0;
        $_ru = 0.00;
        $_chu = 0.00;
        $_num = 0;

        $_num = $users->count();
        
        
        
        $_daili =  Db::table("account_log")->whereIn("user_id",$uid)->where("type",7)->where("dail",2)->sum("value");


        $_ru = Db::table("charge_req")->whereIn("uid",$uid)->where("status",2)->sum("amount");

        $_chu = Db::table("users_wallet_out")->whereIn("user_id",$uid)->where("status",2)->sum("number");

        $data = [];
        $data['_num'] = $_num;
        $data['_daili'] = $_daili;
        $data['_ru'] = $_ru;
        $data['_chu'] = $_chu;


        return $this->ajaxReturn($data);
    }

    //我的邀请二维码
    public function get_my_invite_code()
    {

        $_self = Agent::getAgent();

        if ($_self == null) {
            $this->outmsg('超时');
        }

        $use = Users::getById($_self->user_id);

        return $this->ajaxReturn(['invite_code' => $use->extension_code, 'is_admin' => $_self->is_admin]);
    }

    //代理商管理
    public function salesmenIndex()
    {
        $self = Agent::getAgent();
        if($self->level > 1){
           echo "没有权限"; die;
        }
       
        return view("agent.salesmen.index",["id"=>$self->id]);
    }

    //添加代理商页面
    public function salesmenAdd()
    {
        $data = request()->all();

        return view("agent.salesmen.add", ['d' => $data]);
    }

    public function salesmenEdit()
    {
        $data = request()->all();
        return view("agent.salesmen.add", ['d' => $data]);
    }
    //出入金管理
    public function transferIndex()
    {
        return view("agent.user.transfer");
    }

     //用户点控
     public function risk()
     {
         
         $user_id = request()->get('id', 0);
         $user=Users::find($user_id);
         
         return view("agent.user.risk", ['result' => $user]);
     }

     public function postRisk()
     {
         
        $user_id = request()->get('id', 0);
        $risk = request()->get('risk', 0);
        $user=Users::find($user_id);
        $agent_id = Agent::getAgentId();
        $parent_agent = explode(',', $user->agent_path);

        if (!in_array($agent_id, $parent_agent)) {
            return $this->error('不是您的伞下用户，不可操作');
        }
        try {
            //code...
            $user->risk=$risk;
            $user->save();
            return $this->success("操作成功");

        } catch (\Throwable $th) {
            //throw $th;
            return $this->error($th->getMessage()); 
        }
        
        
     }


}
