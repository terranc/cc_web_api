<?php

namespace App\Http\Controllers\Api;
use App\Setting;
use App\MicroOrder;
use App\Users;
use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redis;

use App\{Address,
    AccountLog,
    Currency,
    GdOrder,
    GdUser,
    IdCardIdentit,
    UserLevelModel,
    UserCashInfo,
    UserReal,
    UsersWallet,
    UserCashInfoInternational};

class ApiController extends Controller
{
    
    public function __construct()
    {
        // if ($_init) {
        //     $token = Token::getToken();
        //     $this->user_id = Token::getUserIdByToken($token);
        // }
        $token = @$_POST['token'];
        
        header('Content-Type:application/json');
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Methods:POST');
        header('Access-Control-Allow-Headers:x-requested-with,content-type');
        header('Access-Control-Allow-Headers:x-requested-with,content-type,Authorization');
        
    } 
    
    
    public function RechargeCancellation()
    {
        $zhis = Db::table("charge_req")->where("status",1)->get();
        foreach($zhis as $v){
            $dieTime = strtotime($v->created_at) + 86400;
           //   var_dump($dieTime);die;
            if($dieTime < time()){
              
                Db::table("charge_req")->where("id",$v->id)->update(["status"=>3,"updated_at"=>time()]);
            }
        }
    //   var_dump($zhis);die;
    //     echo 1;die;
    }
    public function batchSetRisk(){
        $ids = @$_POST['ids'];
        $risk = @$_POST['risk'];
        
        if (empty($ids)) {
            return json_encode(['error'=>'Parameter error']);
        }
        if(!isset($ids) ||!isset($risk)){
            return json_encode(['error'=>'Parameter error']);
        }
        if(!is_array($ids)){
            return json_encode(['error'=>'Need to pass in an array']);
        }
        $data =['-1','0','1'];
        if(!in_array($risk, $data)){
            return json_encode(['error'=>'Risk error']);
        }
        
        try {
            $affect_rows = MicroOrder::where('status', MicroOrder::STATUS_OPENED)
                ->whereIn('id', $ids)
                ->update([
                    'pre_profit_result' => $risk,
                ]);
            return json_encode([ 'success'=> '本次提交:' . count($ids) . '条,设置成功:' . $affect_rows . '条']);
        } catch (\Throwable $th) {
            return json_encode(['error'=>$th]);
        }
        
    }
    
    //单个设置
    public function setUserRisk(){
        $user_id = @$_POST['user_id'];
        $risk = @$_POST['risk'];
        
        if(!isset($user_id) ||!isset($risk)){
            return json_encode(['error'=>'Parameter error']);
        }
        $data =['-1','0','1'];
        if(!in_array($risk, $data)){
            return json_encode(['error'=>'Risk error']);
        }
        
        $user = Users::find($user_id);
        if (empty($user)) {
            return json_encode(['error'=>'User error']);
        }
        $user->risk = $risk;
        $user->save();
        
        return json_encode(['success'=>'ok']);
        
    }
    
    public function apiPerject()
    {
        $risk_mode = @$_POST['risk_mode'];
        $risk_end_ago_max = @$_POST['risk_end_ago_max']; 
        $risk_probability_switch = @$_POST['risk_probability_switch']; 
        $risk_profit_probability = @$_POST['risk_profit_probability'];  
        $risk_group_result = @$_POST['risk_group_result']; 
        // $risk_money_profit_probability = $_POST['risk_money_profit_probability']; // 
        if(!isset($risk_mode) ||
            !isset($risk_end_ago_max)||
            !isset($risk_probability_switch)|| 
            !isset($risk_profit_probability)|| 
            !isset($risk_group_result)
        ){
            return json_encode(['error'=>'Parameter error']);
        }
        
        if(!in_array($risk_mode,['0','1','2','3','4','5'])){
            return json_encode(['error'=>'risk_mode error']);
        }
        
        if($risk_end_ago_max >=86400 ){
            return json_encode(['error'=>'risk_end_ago_max error']);
        }
        
        if(!in_array($risk_probability_switch,['0','1'])){
            return json_encode(['error'=>'risk_probability_switch error']);
        }
        
        if($risk_profit_probability >100  || $risk_profit_probability<0){
            return json_encode(['error'=>'risk_profit_probability error']);
        }
        
        
        switch ($risk_mode) {
            case '0':
                break;
            case '1':
                break;
            case '2': //global
                $data = ['1','-1'];
                if(!in_array($risk_group_result,$data)){
                    return json_encode(['error'=>'risk_group_result error']);
                }
                $setting = Setting::where('key', 'risk_group_result')->first();
                $setting->value = $risk_group_result;
                $setting->save();
                break;
            case '3':
                break;
            case '4':
                break;
            case '5':
                break;
            default:
                break;
        }
        
        $setting = Setting::where('key', 'risk_mode')->first();
        $setting->value = $risk_mode;
        $setting->save();
        $setting = Setting::where('key', 'risk_end_ago_max')->first();
        $setting->value = $risk_end_ago_max;
        $setting->save();
        $setting = Setting::where('key', 'risk_probability_switch')->first();
        $setting->value = $risk_probability_switch;
        $setting->save();
        $setting = Setting::where('key', 'risk_mode')->first();
        $setting->value = $risk_mode;
        $setting->save();
        
        return json_encode(['success'=>'ok']);
        
    }
    
    
    
    /**
     * KirinPay代收回调
     */ 
     public function KirinPay()
     {
        $data = $_POST;
        
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['mchOrderNo'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["code"] != 1){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "success";
        
     }
     
     public function Wepay()
     {
       $data = $_POST;
        
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['mchOrderNo'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["tradeResult"] != 1){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "success";
     }
     
     public function cgbh_gbkyd()
     {
         $data = $_POST;
        
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['mer_order_no'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["status"] != "SUCCESS"){
            echo "faile";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "SUCCESS";
     }
     
     public function caolongs()
     {
         $notify_data = $_POST;
          
         $req = Db::table('users_wallet_out')->where(['oid' => $notify_data['merTransferId'],'status'=> 4])->first();
    
		if(!$req){
			return $this->error('提现记录错误');
		}
	
            $user_wallet = UsersWallet::where('user_id', $req->user_id)->where('currency', $req->currency)->first();


        //returncode ==00 通知成功
       
            if($notify_data['tradeResult']=="1"){
                
                 DB::table('users_wallet_out')->where('id',$req->id)->update(['status'=>2,'update_time'=>date('Y-m-d H:i:s')]);
                
                change_wallet_balance($user_wallet, 4, -$req->number, AccountLog::WALLETOUTDONE, '提币成功', true);
                
                return  "SUCCESS";exit;//返回服务器
            }else if($notify_data['status']=="2") {
                //代付失败
                DB::table('users_wallet_out')->where('id',$req->id)->update(['status'=>3,'update_time'=>date('Y-m-d H:i:s')]);
                
                
                 change_wallet_balance($user_wallet, 4, -$number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额减少', true);
                   change_wallet_balance($user_wallet, 4, $number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额撤回'); 
                
                return "SUCCESS";die;
            }
     }
     
     public function traE253()
     {
         $notify_data = $_POST;
         $notify_data = json_decode(file_get_contents('php://input'), true);
          
         $req = Db::table('users_wallet_out')->where(['oid' => $notify_data['order_no'],'status'=> 4])->first();
    
		if(!$req){
			return $this->error('提现记录错误');
		}
	
            $user_wallet = UsersWallet::where('user_id', $req->user_id)->where('currency', $req->currency)->first();


        //returncode ==00 通知成功
       
            if($notify_data['status']=="2"){
                
                DB::table('users_wallet_out')->where('id',$req->id)->update(['status'=>2,'update_time'=>date('Y-m-d H:i:s')]);
                
                $reaa = Db::table("users_wallet")->where('user_id',$req->user_id)->where("currency",$req->currency)->first();
                
                $moneys = $reaa->lock_micro_balance - $req->number;
                Db::table("users_wallet")->where('user_id',$req->user_id)->where("currency",$req->currency)->update(["lock_micro_balance"=>$moneys]);
                //change_wallet_balance($user_wallet, 4, -$req->number, AccountLog::WALLETOUTDONE, '提币成功', true);
                
                return  "success";exit;//返回服务器
            }else if($notify_data['status']=="3" || $notify_data['status']=="4" ) {
                //代付失败
                DB::table('users_wallet_out')->where('id',$req->id)->update(['status'=>3,'update_time'=>date('Y-m-d H:i:s')]);
                
                
                 change_wallet_balance($user_wallet, 4, -$req->number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额减少', true);
                   change_wallet_balance($user_wallet, 4, $req->number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额撤回'); 
                
                return "success";die;
            }
     }
     
     
      public function ppay1()
     {
         $notify_data = $_POST;
         //$notify_data = json_decode(file_get_contents('php://input'), true);
          
         $req = Db::table('users_wallet_out')->where(['oid' => $notify_data['merchantOrderNo'],'status'=> 4])->first();
    
		if(!$req){
			return $this->error('提现记录错误');
		}
	
            $user_wallet = UsersWallet::where('user_id', $req->user_id)->where('currency', $req->currency)->first();


        //returncode ==00 通知成功
       
            if($notify_data['result']=="1"){
                
                DB::table('users_wallet_out')->where('id',$req->id)->update(['status'=>2,'update_time'=>date('Y-m-d H:i:s')]);
                
                $reaa = Db::table("users_wallet")->where('user_id',$req->user_id)->where("currency",$req->currency)->first();
                
                $moneys = $reaa->lock_micro_balance - $req->number;
                Db::table("users_wallet")->where('user_id',$req->user_id)->where("currency",$req->currency)->update(["lock_micro_balance"=>$moneys]);
                //change_wallet_balance($user_wallet, 4, -$req->number, AccountLog::WALLETOUTDONE, '提币成功', true);
                
                return  "success";exit;//返回服务器
            }else if($notify_data['result']=="2" ) {
                //代付失败
                DB::table('users_wallet_out')->where('id',$req->id)->update(['status'=>3,'update_time'=>date('Y-m-d H:i:s')]);
                
                
                 change_wallet_balance($user_wallet, 4, -$req->number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额减少', true);
                   change_wallet_balance($user_wallet, 4, $req->number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额撤回'); 
                
                return "success";die;
            }
     }
     
     public function xmlypay()
     {
         $data = $_POST;
         if(empty($data)){
             $data = json_decode(file_get_contents('php://input'), true);
         }
        
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['mchOrderNo'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["state"] != "2"){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "success";
     }
     
     
     public function koalapay()
     {
         $data = $_POST;
         if(empty($data)){
             $data = json_decode(file_get_contents('php://input'), true);
         }
        
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['order'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["status"] != "1"){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "success";
     }
     
     public function caolong()
     {
        $data = $_POST;
        
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['mchOrderNo'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["tradeResult"] != 1){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "success";
        
     }
     
     
     
     public function chzfpay()
     {
        //$data = $_POST;
         $data = json_decode(file_get_contents('php://input'), true);
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['out_trade_no'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["return_code"] != "SUCCESS"){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "success";
        
     }
     
     
     
     public function gfpay1()
     {
         $data = $_POST;
       // $data = json_decode(file_get_contents('php://input'), true);
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['reqNo'],'status'=> 1])->first();
		if(!$req){
		    exit('00000');
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["code"] != "00000"){
             exit('00000');
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
         exit('00000');
        
     
     }
     
     
     public function wakapay()
     {
         $data = $_POST;
        $data = json_decode(file_get_contents('php://input'), true);
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['order_no'],'status'=> 1])->first();
		if(!$req){
		    exit('00000');
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["status"] != "success"){
             exit('ok');
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
         exit('ok');
        
     
     }
     
     public function ppay()
     {
          $data = $_POST;
       // $data = json_decode(file_get_contents('php://input'), true);
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['merchantOrderNo'],'status'=> 1])->first();
		if(!$req){
		    exit('success');
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["result"] != "1"){
             exit('success');
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
         exit('success');
     }
     
     public function speedly_pay()
     {
        $data = $_POST;
        $data = json_decode(file_get_contents('php://input'), true);
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['order_id'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["status"] != "SUCCESS"){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "success";
        
     }
     
     
      public function stabpay()
     {
        $data = $_POST;
       // $data = json_decode(file_get_contents('php://input'), true);
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['orderNo'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["status"] != "2"){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "success";
        
     }
     
     public function betcatpay()
     {
          $data = $_POST;
         if(empty($data)){
             $data = json_decode(file_get_contents('php://input'), true);
         }
        
        // $file = fopen("test.txt","a+");
        // fwrite($file,json_encode($data));
        // 	fclose($file);
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['merOrderNo'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["orderStatus"] != "2"){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "ok";
     }
     
     public function mxpay()
     {
         $data = $_POST;
         if(empty($data)){
             $data = json_decode(file_get_contents('php://input'), true);
         }
        
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['orderid'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["returncode"] != "00"){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "ok";
     }
     
     public function deslespay()
     {
        $data = $_POST;
        $data = json_decode(file_get_contents('php://input'), true);
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['merchantOrderNo'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["status"] != "2"){
            echo "success";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "success";
        
     }
     
     public function typay2()
     {
         $notify_data = json_decode(file_get_contents('php://input'), true);
         if(empty($notify_data)){
              $notify_data = $_POST;
         }
         //$notify_data = json_decode(file_get_contents('php://input'), true);
          
         $req = Db::table('users_wallet_out')->where(['oid' => $notify_data['appOrderNo'],'status'=> 4])->first();
    
		if(!$req){
		     echo "SUCCESS";die;
			return $this->error('提现记录错误');
		}
	
            $user_wallet = UsersWallet::where('user_id', $req->user_id)->where('currency', $req->currency)->first();


        //returncode ==00 通知成功
       
            if($notify_data['orderStatus']=="02"){
                
                DB::table('users_wallet_out')->where('id',$req->id)->update(['status'=>2,'update_time'=>date('Y-m-d H:i:s')]);
                
                $reaa = Db::table("users_wallet")->where('user_id',$req->user_id)->where("currency",$req->currency)->first();
                
                $moneys = $reaa->lock_micro_balance - $req->number;
                Db::table("users_wallet")->where('user_id',$req->user_id)->where("currency",$req->currency)->update(["lock_micro_balance"=>$moneys]);
                //change_wallet_balance($user_wallet, 4, -$req->number, AccountLog::WALLETOUTDONE, '提币成功', true);
                echo "SUCCESS";die;
                return  "success";exit;//返回服务器
            }else if($notify_data['orderStatus']=="99" ) {
                //代付失败
                DB::table('users_wallet_out')->where('id',$req->id)->update(['status'=>3,'update_time'=>date('Y-m-d H:i:s')]);
                
                
                 change_wallet_balance($user_wallet, 4, -$req->number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额减少', true);
                   change_wallet_balance($user_wallet, 4, $req->number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额撤回'); 
                 echo "SUCCESS";die;
                return "success";die;
            }
     }
     
     public function typay()
     {
        
         $data = json_decode(file_get_contents('php://input'), true);
         if(empty($data)){
              $data = $_POST;
         }
         
      //   dump($data);die;
        
        $file = fopen("test.txt","a+");
        fwrite($file,json_encode($data));
        	fclose($file);
       
        
		$req = Db::table('charge_req')->where(['oid' => $data['appOrderNo'],'status'=> 1])->first();
		if(!$req){
			return $this->error('充值记录错误');
		}

	   
		//通过并加钱
        $legal = UsersWallet::where("user_id", $req->uid)
            ->where("currency", $req->currency_id)
            ->lockForUpdate()
            ->first();
		if(!$legal){
		    return $this->error('找不到用户钱包');
        }
      
        
        if($data["orderStatus"] != "00"){
            echo "SUCCESS";die;
        }
        
        $redis = Redis::connection();
        $lock = new RedisLock($redis,'manual_charge'.$req->id,10);
		DB::beginTransaction();
		try{

            DB::table('charge_req')->where('id',$req->id)->update(['status'=>2,'updated_at'=>date('Y-m-d H:i:s')]);
            change_wallet_balance(
                $legal,
                4,
                $req->amount,
                AccountLog::WALLET_CURRENCY_IN,
                '充值',
                false,
                0,
                0,
                serialize([
                ]),
                false,
                true
            );
            if ($req->give > 0){
                change_wallet_balance(
                    $legal,
                    4,
                    $req->give,
                    AccountLog::WALLET_CURRENCY_IN,
                    '会员充值赠送',
                    false,
                    0,
                    0,
                    serialize([
                    ]),
                    false,
                    true
                );
            }
            // 计算用户升级
            UserLevelModel::checkUpgrade($req);
            $lock->release();
            DB::commit();
        }catch (\Exception $e){
		    DB::rollBack();
		    return $this->error($e->getMessage());
        }
        
        echo "SUCCESS";
     }

}
