<?php

namespace App\Http\Controllers\Admin;


use App\Admin;
use App\AdminRole;
use App\Agent;
use App\Users;
use App\UsersWallet;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

class AdminController extends Controller{
    
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
    
    public function agentTonji()
    {
       // echo 1;die;
        return view('admin.manager.agent_tonji');
    }
    
    public function agentTonjis(){
        $datas = Db::table("agent")->where("level",1)->get();
        
        $arr = [];
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
          // $value->qdmoney = Db::table("charge_req")->whereIn("uid",$ids)->where("status",2)->sum("amount");
           
           //提现数量
           $value->tixian = Db::table("users_wallet_out")->whereIn("user_id",$ids)->where("status",2)->sum("number");
           
           //订单盈利
           $value->fact_profits = Db::table("micro_orders")->whereIn("user_id",$ids)->sum("fact_profits");
           $value->order_number = Db::table("micro_orders")->whereIn("user_id",$ids)->sum("number");
           $value->order_count = Db::table("micro_orders")->whereIn("user_id",$ids)->count();
           
           $arr[] = $value;
           //下级代理
           $xiaji = Db::table("agent")->where("parent_agent_id",$value->id)->get();
           foreach($xiaji as $vo){
               
               $vo->username = "---".$vo->username;
               $vo->user_codes = Db::table("users")->where("id",$value->user_id)->value("extension_code");
                if($vo->is_lock == 0){
                    $vo->is_lock = "正常";
                }else{
                    $vo->is_lock = "锁定";
                }
                
               //统计
               $ids = $this->getXiajiId($vo->user_id);
              
               //下级数量
               $vo->user_count = count($ids);
               //代理金额调整数量
               $vo->dailimoney1 = Db::table("account_log")->whereIn("user_id",$ids)->where("type",7)->where("dail",2)->sum("value");
               //下级系统金额调整数量
               $vo->dailimoney2 = Db::table("account_log")->whereIn("user_id",$ids)->where("type",7)->where("dail",1)->sum("value");
               
               //系统前端充值金额
               $vo->qdmoney = Db::table("charge_req")->whereIn("uid",$ids)->where("status",2)->sum("amount");
              // $value->qdmoney = Db::table("charge_req")->whereIn("uid",$ids)->where("status",2)->sum("amount");
               
               //提现数量
               $vo->tixian = Db::table("users_wallet_out")->whereIn("user_id",$ids)->where("status",2)->sum("number");
               
               //订单盈利
               $vo->fact_profits = Db::table("micro_orders")->whereIn("user_id",$ids)->sum("fact_profits");
               $vo->order_number = Db::table("micro_orders")->whereIn("user_id",$ids)->sum("number");
               $vo->order_count = Db::table("micro_orders")->whereIn("user_id",$ids)->count();
               
               $arr[] = $vo;
           }
           
           
           
           
        }
        return response()->json(['code'=>0,'count'=>count($arr),'msg'=>'','data'=>$arr]);
    }
    
    public function delete_agent()
    {
        $id = Input::get('id', '');
        $res = Db::table("agent")->where("id",$id)->delete();
        if($res){
                return $this->success('删除成功');
            }
             return $this->error("删除失败");
    }
    
    public function edit_agent()
    {
        if($_POST){
            
           $id =  Input::get('id', '');   
           $userName = Input::get('username', '');
            
           $password = Input::get('password', '');
           
           $admin_id = Input::get("admin_id",0);
          
           
           
           $user_id = Input::get("user_id",'0');
           
           if(!$userName){
               return $this->error("请填写账号");
           }
           
           if(!$user_id){
               return $this->error("请填写前端账号id");
           }
          
           $userRes = Db::table("agent")->where("id",$id)->first();
          
         
           
           
           
           if($userRes->user_id != $user_id){
               
               if(Agent::getAgentId()){
                   $user_ida = Db::table("agent")->where("id",Agent::getAgentId())->value("user_id");
                   $ids =  $this->getXiajiId($user_ida);
                   
                   $qda = Db::table("users")->whereIn("id",$ids)->where("id",$user_id)->first();
                   if(!$qda){
                       return $this->error("前端账号id错误");
                   }
                   
                 
               }else{
                   $qda = Db::table("users")->find($user_id);
                   if(!$qda){
                       return $this->error("前端账号id错误");
                   }
               }
               
             // echo $userRes->user_id;die;
               
               $user_idRes = Db::table("agent")->where("user_id",$user_id)->first();
               if($user_idRes){
                    return $this->error("前端账号已有绑定");
               }
           }
           
           
           
          // $insert["userName"] = $userName;
            if($password){
               $password = Users::MakePassword($password);
                $insert["password"] = $password;
           }
          
         //  $insert["level"] = 1;
           $insert["user_id"] = $user_id;
          // $insert["reg_time"] = time();
           $insert["is_lock"] = Input::get('is_lock', '0');
           
           $res = Db::table("agent")->where("id",$id)->update($insert);
            if($res){
                return $this->success('修改成功');
            }
             return $this->error("修改失败");
        }
        $userData = Db::table("agent")->where("id",Input::get('id', ''))->first();
     
         return view('admin.manager.edit_agent',['admin_user' => $userData,"admin_id"=>Input::get("admin_id",0)]);
    }
    
    public function add_agent()
    {
        if($_POST){
            
           $dailis = Input::get('dailis', '1');
           $id = Input::get("id",0);
           
           $userName = Input::get('username', '');
            
           $password = Input::get('password', '');
           
           $password = Users::MakePassword($password);
           
           $user_id = Input::get("user_id",'0');
           
           if(!$userName){
               return $this->error("请填写账号");
           }
           if(!$password){
               return $this->error("请填写密码");
           }
           if(!$user_id){
               return $this->error("请填写前端账号id");
           }
          
           $userRes = Db::table("agent")->where("username",$userName)->first();
          
           if($userRes){
                return $this->error("账号已存在");
           }
           
           if($id){
               $user_ida = Db::table("agent")->where("id",Agent::getAgentId())->value("user_id");
               $ids =  $this->getXiajiId($user_ida);
               
               $qda = Db::table("users")->whereIn("id",$ids)->where("id",$user_id)->first();
               if(!$qda){
                   return $this->error("前端账号id错误");
               }
               
               $insert["parent_agent_id"] = $id;
           }else{
               $qda = Db::table("users")->find($user_id);
               if(!$qda){
                   return $this->error("前端账号id错误");
               }
           }
           
            
           
           
           
           $user_idRes = Db::table("agent")->where("user_id",$user_id)->first();
           if($user_idRes){
                return $this->error("前端账号已有绑定");
           }
           
           
           
           $insert["userName"] = $userName;
           $insert["password"] = $password;
           $insert["level"] = $dailis;
           $insert["user_id"] = $user_id;
           $insert["reg_time"] = time();
           $insert["is_lock"] = Input::get('is_lock', '0');
            
           $res = Db::table("agent")->insert($insert);
            if($res){
                return $this->success('添加成功');
            }
             return $this->error("添加失败");
        }
        
         return view('admin.manager.add_agent',["dailis"=>Input::get("dailis",1),"id"=>Input::get("id",0)]);
    }
    
    public function users(){
        $adminuser = Admin::where('id', '>', 0);
        if(session()->get('admin_is_super') != '1') {
            $adminuser->where('role_id', '>', 1);
        }
        $adminuser = $adminuser->get();
        $count = $adminuser -> count();
        return response()->json(['code'=>0,'count'=>$count,'msg'=>'','data'=>$adminuser]);
    }
    public function editAddress(){
         $id = Input::get('id',null);
         $address  = Input::get('address');
         $address_2 = Input::get('address_2');
         $data = [
             'address_2' => $address_2,'address' => $address];
             
         UsersWallet::where('id',$id)->update($data);
         
      return $this->success('编辑成功');
    }
    public function add()
    {
        // if(session()->get('admin_is_super') != '1') {
        //     abort(403);
        // }
        $id = Input::get('id',null);
        if(empty($id)) {
            $adminUser = new Admin();
        }else{
            $adminUser = Admin::find($id);
            if($adminUser == null) {
                abort(404);
            }
        }
        $roles = AdminRole::all();
        return view('admin.manager.add', ['admin_user' => $adminUser, 'roles' => $roles]);
    }

    public function postAdd(Request $request)
    {
        // if(session()->get('admin_is_super') != '1') {
        //     abort(403);
        // }
        $id = Input::get('id', null);
        $validator = Validator::make(Input::all(), [
            'username' => 'required',
            'role_id' => 'required|numeric'
        ], [
            'username.required' => '姓名必须填写',
            'role_id.required'  => '角色必须选择',
            'role_id.numeric'   => '角色必须为数字'
        ]);
        if(empty($id)) {
            $adminUser = new Admin();
        }else{
            $adminUser = Admin::find($id);
            if($adminUser == null) {
                return redirect()->back();
            }
        }
        $password = Input::get('password', '');
        $adminUser->role_id = Input::get('role_id', '0');
        if(Input::get('password', '') != '') {
            $adminUser->password = Users::MakePassword($password);
        }
        $validator->after(function($validator) use ($adminUser, $id)
        {
            if(empty($id)) {
                if (Admin::where('username', Input::get('username'))->exists()) {
                    $validator->errors()->add('username', '用户已经存在');
                }
            }
        });

        $adminUser->username = Input::get('username', '');
        if($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        try {
            $adminUser->save();
        }catch (\Exception $ex){
            $validator->errors()->add('error', $ex->getMessage());
            return $this->error($validator->errors()->first());
        }
        return $this->success('添加成功');
    }

    public function del()
    {
        $admin = Admin::find(Input::get('id'));
        if($admin == null) {
            abort(404);
        }
        $bool = $admin->delete();
        if($bool){
            return $this->success('删除成功');
        }else{
            return $this->error('删除失败');
        }
    }

    public function agent(){

        $admin = Agent::where('is_admin' , 1)->where('level' , 0)->first();

        if ($admin != null ){
            return redirect(route('agent'));
        }else{
            $hkok = DB::table('admin')->where('id' , 1)->first();

            if ($hkok != null ){
                $insertData = [];
                $insertData['user_id'] = $hkok->id;
                $insertData['username'] = $hkok->username;
                $insertData['password'] = $hkok->password;
                $insertData['level'] = 0;
                $insertData['is_admin'] = 1;
                $insertData['reg_time'] = time();
                $insertData['pro_loss'] = 100.00;
                $insertData['pro_ser'] = 100.00;

                $id = DB::table('agent')->insertGetId($insertData);

                if ($id>0){
                    return redirect(route('agent'));
                }else{
                    return $this->error('失败');
                }
            }
        }
    }


}
?>