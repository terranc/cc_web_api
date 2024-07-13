<?php

namespace App\Http\Controllers\Agent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\{AccountLog, Agent, Users, UsersWalletOut, Currency, LeverTransaction, UsersWallet, AgentMoneylog};

class CapitalController extends Controller
{

    //充币
    public function rechargeIndex()
    {
        //币币
        $legal_currencies = Currency::get();
        //下级代理
        $son_agents = Agent::getAllChildAgent(Agent::getAgentId());
        return view("agent.capital.recharge", [
            'legal_currencies' => $legal_currencies,
            'son_agents' => $son_agents,
        ]);
    }

    //提币
    public function withdrawIndex()
    {
        //币币
        $legal_currencies = Currency::get();
        //下级代理
        $son_agents = Agent::getAllChildAgent(Agent::getAgentId());
        return view("agent.capital.withdraw", [
            'legal_currencies' => $legal_currencies,
            'son_agents' => $son_agents,
        ]);
    }
    
    //提币
    public function logIndex()
    {
         //获取type类型
        $type = array(
            AccountLog::ADMIN_LEGAL_BALANCE => '后台调节法币账户余额',
            AccountLog::ADMIN_LOCK_LEGAL_BALANCE => '后台调节法币账户锁定余额',
            AccountLog::ADMIN_CHANGE_BALANCE => '后台调节币币账户余额',
            AccountLog::ADMIN_LOCK_CHANGE_BALANCE => '后台调节币币账户锁定余额',
            AccountLog::ADMIN_LEVER_BALANCE => '后台调节杠杆账户余额',
            AccountLog::ADMIN_LOCK_LEVER_BALANCE => '后台调节杠杆账户锁定余额',
            AccountLog::ADMIN_MICRO_BALANCE => '后台调节期权账户余额', //后台调节期权账户余额
            AccountLog::ADMIN_LOCK_MICRO_BALANCE => '后台调节期权账户锁定余额', //后台调节期权账户锁定余额
            AccountLog::WALLET_CURRENCY_OUT => '法币账户转出至交易账户',
            AccountLog::WALLET_CURRENCY_IN => '交易账户转入至法币账户',
            9 => 'c2c划转期权',
            10 => '期权划转c2c',
            AccountLog::MICRO_TRADE_CLOSE_SETTLE => '期权平仓结算',
            AccountLog::USER_BUY_INSURANCE => '用户购买保险',
            AccountLog::USER_CLAIM_COMPENSATION => '赔偿用户',
            AccountLog::INSURANCE_RESCISSION1 => '保险解约，清除受保金额',
            AccountLog::INSURANCE_RESCISSION2 => '保险解约，清除保险金额',
            AccountLog::INSURANCE_RESCISSION_ADD => '保险解约，赔付用户',
            AccountLog::RETURN_INSURANCE_TRADE_FEE => '释放保险交易手续费',


            AccountLog::LOWER_REBATE => '下级返利',
            AccountLog::INSURANCE_MONEY => '用户持险生币',

            AccountLog::LEGAL_USER_BUY => '用户购买商家法币成功',
            AccountLog::LEGAL_SELLER_BUY => '商家购买用户法币成功',
            AccountLog::TRANSACTIONOUT_SUBMIT_REDUCE => '提交卖出，扣除',
            AccountLog::TRANSACTIONIN_REDUCE => '买入扣除',

            AccountLog::INVITATION_TO_RETURN => '邀请返佣金',
            AccountLog::ETH_EXCHANGE => '链上充币',

        );
        $currency_type = Currency::all();
        return view("agent.capital.log", [
            'types' => $type,
            'currency_type' => $currency_type
        ]);
    }
    
    public function logList(Request $request)
    {
         $limit = $request->get('limit', 10);
        $account = $request->get('account', '');
        $start_time = strtotime($request->get('start_time', 0));
        $end_time = strtotime($request->get('end_time', 0));
        $currency = $request->get('currency_type', 0);
        $type = $request->get('type', 0);
        $sign = $request->get('sign', 0);//正负号，0所有，1，正，-1，负号

        $list = new AccountLog();
        if (!empty($currency)) {
            $list = $list->where('currency', $currency);
        }
        if (!empty($type)) {
            $list = $list->where('type', $type);
        }
        if (!empty($start_time)) {
            $list = $list->where('created_time', '>=', $start_time);
        }
        if (!empty($end_time)) {
            $list = $list->where('created_time', '<=', $end_time);
        }
        if (!empty($sign)) {
            if($sign > 0){
                $list = $list->where('value', '>', 0);
            }else{
                $list = $list->where('value', '<', 0);
            }

        }
        //根据关联模型的时间
        /*if(!empty($start_time)){
            $list = $list->whereHas('walletLog', function ($query) use ($start_time) {
                $query->where('create_time','>=',$start_time);
            });
        }
        if(!empty($end_time)){
            $list = $list->whereHas('walletLog', function ($query) use ($end_time) {
                $query->where('create_time','<=',$end_time);
            });
        }*/
        if (!empty($account)) {
            $list = $list->whereHas('user', function ($query) use ($account) {
                $query->where("phone", 'like', '%' . $account . '%')->orwhere('email', 'like', '%' . $account . '%');
            });
        }

      /* if (!empty($account_number)) {
            $list = $list->whereHas('user', function($query) use ($account_number) {
            $query->where('account_number','like','%'.$account_number.'%'); 
             } );
        }*/

        if (!empty($type)) {
            $sum = $list->sum('value');
        }else{
            $sum = '选择日志类型进行统计';
        }
        
        $user_id = Db::table("agent")->where("id",Agent::getAgentId())->value("user_id");
        $ids =  $this->getXiajiId($user_id);

        $list = $list->orderBy('id', 'desc')->whereIn("user_id",$ids)->paginate($limit);
        //dd($list->items());
        return response()->json(['code' => 0, 'data' => $list->items(), 'count' => $list->total(), 'sum'=>$sum]);
    }

    public function rechargeList(Request $request)
    {
        $limit = $request->input('limit', 20);
       
         $user_id = Db::table("agent")->where("id",Agent::getAgentId())->value("user_id");
        $ids =  implode(",",$this->getXiajiId($user_id));
        //$node_users = Users::whereRaw("id in (".$ids.")")->pluck('id')->all();
        
     // dump($ids);die;
        
        
         $limit = $request->get('limit', 20);
	    //  $rate=Setting::getValueByKey('USDTRate', 6.5);
	   
	    $list = DB::table('charge_req')
            ->join('users', 'users.id', '=', 'charge_req.uid')
            ->join('currency', 'currency.id', '=', 'charge_req.currency_id')
            ->join('payments', 'payments.id', '=', 'charge_req.type')
            ->leftjoin('user_cash_info', 'user_cash_info.user_id', '=', 'charge_req.uid')
            ->select('charge_req.*','user_cash_info.bank_account','currency.price','currency.rmb_relation', 'users.account_number', 'currency.name',"payments.name as paymentsName")
            ->whereRaw("uid in (".$ids.")")
            ->orderBy('charge_req.id', 'desc');
            
            
            if($request->get('account_number','')){
              $list = $list->where("users.account_number","like",'%' .$request->get('account_number',''). '%');
            }
            if($request->get('start_time','')){
             $list =   $list->where("charge_req.created_at",">=",$request->get('start_time',''));
            }
             if($request->get('end_time','')){
               $list = $list->where("charge_req.created_at","<=",$request->get('end_time',''));
            }
            
             if($request->get('status','')){
               $list = $list->where("charge_req.status",$request->get('status',''));
            }
            
            
            $list = $list->paginate($limit);
            
        // $userWalletOut = new UsersWalletOut();
        // $userWalletOutList = $userWalletOut->orderBy('id', 'desc')->paginate($limit);
        // var_dump($list);exit;
        
        return $this->layuiData($list);
    }

    //提币
    public function withdrawList(Request $request)
    {
        $limit = $request->input('limit', 20);
        // $agent = Agent::getAgent();
        // $child_agents = Agent::getAllChildAgent($agent->id);
        // $agents = $child_agents->pluck('id')->all();
        // $child_users = Users::whereIn('agent_note_id', $agents)->get();
       $user_id = Db::table("agent")->where("id",Agent::getAgentId())->value("user_id");
        $ids =  $this->getXiajiId($user_id);
      
        
      $limit = $request->get('limit', 20);
        $account_number = $request->input('account_number', '');
        $userWalletOut = new UsersWalletOut();
        $userWalletOutList = $userWalletOut->whereIn("user_id",$ids)->whereHas('user', function ($query) use ($account_number) {
            if ($account_number != '') {
                $query->where('phone', $account_number)
                    ->orWhere('account_number', $account_number)
                    ->orWhere('email', $account_number)
                    ->orWhere('id', $account_number);
            }
        })->orderBy('id', 'desc')->paginate($limit);
      
        return $this->layuiData($userWalletOutList);
    }

    //用户资金
    public function wallet(Request $request)
    {
        $id = $request->get('id', null);
        if (empty($id)) {
            return $this->error('参数错误');
        }

        return view("agent.capital.wallet", ['user_id' => $id]);
    }

    public function wallettotalList(Request $request)
    {
        $limit = $request->get('limit', 10);
        $user_id = $request->get('user_id', null);
        if (empty($user_id)) {
            return $this->error('参数错误');
        }

        $list = Currency::orderBy('id', 'asc')->select(['id', 'name'])->paginate($limit);

        foreach ($list->items() as &$value) {
            $value->_ru = AccountLog::whereIn('type', [AccountLog::CHAIN_RECHARGE,AccountLog::WALLET_CURRENCY_IN])
                ->where('user_id', $user_id)
                ->where('currency', $value->id)
                ->sum('value');

            $value->_chu = UsersWalletOut::where('status', 2)
                ->where('user_id', $user_id)
                ->where('currency', $value->id)
                ->sum('real_number');
            $change_balance=UsersWallet::where(['user_id'=>$user_id,'currency'=>$value->id])->sum('change_balance');
            $lock_change_balance=UsersWallet::where(['user_id'=>$user_id,'currency'=>$value->id])->sum('lock_change_balance');
            $legal_balance=UsersWallet::where(['user_id'=>$user_id,'currency'=>$value->id])->sum('legal_balance');
            $lock_legal_balance=UsersWallet::where(['user_id'=>$user_id,'currency'=>$value->id])->sum('lock_legal_balance');
            $lever_balance=UsersWallet::where(['user_id'=>$user_id,'currency'=>$value->id])->sum('lever_balance');
            $lock_lever_balance=UsersWallet::where(['user_id'=>$user_id,'currency'=>$value->id])->sum('lock_lever_balance');
            $micro_balance=UsersWallet::where(['user_id'=>$user_id,'currency'=>$value->id])->sum('micro_balance');
            $lock_micro_balance=UsersWallet::where(['user_id'=>$user_id,'currency'=>$value->id])->sum('lock_micro_balance');
            $value->_zongyue=$change_balance*1+$lock_change_balance*1+$legal_balance*1+$lock_legal_balance*1+$lever_balance*1+$lock_lever_balance*1+$micro_balance*1+$lock_micro_balance*1;
            $value->_caution_money = LeverTransaction::where('user_id', $user_id)->whereIn('status', [0, 1, 2])->where('legal', $value->id)->sum('caution_money');
        }

        return $this->layuiData($list);
    }

    //结算 提现到账
    public function walletOut(Request $request)
    {
        $id = $request->get('id', '');

        if (!$id) {
            return $this->error('参数错误');
        }

        try {
            DB::beginTransaction();
            $agent_log = AgentMoneylog::lockForUpdate()->find($id);
            if (empty($agent_log)) {
                throw new \Exception('操作失败:信息有误');
            }
            if ($agent_log->status != 0) {
                throw new \Exception('操作失败:该账单已提现,请勿重复操作或刷新后重试');
            }
            $agent = Agent::find($agent_log->agent_id);
            if ($agent->is_admin != 1) {
                $wallet = UsersWallet::where('user_id', $agent->user_id)->where('currency', $agent_log->legal_id)->first();
                if (empty($wallet)) {
                    throw new \Exception('用户钱包不存在');
                }
                if ($agent_log->type == 1) {

                    $account_type = AccountLog::AGENT_JIE_TC_MONEY;
                    $account_info = '代理商结算头寸收益 划转到账';
                } else {
                    $account_type = AccountLog::AGENT_JIE_SX_MONEY;
                    $account_info = '代理商结算手续费收益 划转到账';
                }
                $change_result = change_wallet_balance($wallet, 1, $agent_log->change, $account_type, $account_info);
                if ($change_result !== true) {
                    throw new \Exception($change_result);
                }
            } else {
                throw new \Exception('超级代理商无法提现');
            }


            $agent_log->status = 1; //
            $agent_log->updated_time = time(); //

            $agent_log->save();

            DB::commit();
            return $this->success('操作成功:)');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }
}
