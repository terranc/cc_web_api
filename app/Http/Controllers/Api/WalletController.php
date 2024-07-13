<?php

namespace App\Http\Controllers\Api;

use App\UserLevelModel;
use Illuminate\Support\Carbon;
use App\Conversion;
use App\FlashAgainst;
use App\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use App\Utils\RPC;
use App\Http\Requests;
use App\Currency;
use App\Ltc;
use App\LtcBuy;
use App\TransactionComplete;
use App\NewsCategory;
use App\Address;
use App\AccountLog;
use App\Setting;
use App\Users;
use App\UsersWallet;
use App\UsersWalletOut;
use App\WalletLog;
use App\Levertolegal;
use App\LeverTransaction;
use App\Jobs\UpdateBalance;

class WalletController extends Controller
{
    
     public function getRateCurrency(Request $request){
        $id=$request->get('id');
        $price=Currency::where('id',$id)->first()->price??1;
        $rate=Setting::getValueByKey('USDTRate', 6.5);
        $rmb=$price*$rate;
        return $this->success([
            'rmb'=>$rmb
        ]);
    }

    public function getRechargeSetting(){
        $bankaccount=Setting::getValueByKey('recharge_bank_account');
        $bankname=Setting::getValueByKey('recharge_bank_name');
        $openbank=Setting::getValueByKey('recharge_open_bank');
        return $this->success([
            'bank_account'=>$bankaccount,
            'bank_name'=>$bankname,
            'open_bank'=>$openbank,
        ]);
    }
    //我的资产
    public function walletList(Request $request)
    {
        $currency_name = $request->input('currency_name', '');
        $user_id = Users::getUserId();
        if (empty($user_id)) {
            return $this->error('参数错误');
        }
        $legal_wallet['balance'] = UsersWallet::where('user_id', $user_id)
            ->whereHas('currencyCoin', function ($query) use ($currency_name) {
                empty($currency_name) || $query->where('name', 'like', '%' . $currency_name . '%');
                //$query->where("is_legal", 1)->where('show_legal', 1);
                $query->where("is_legal", 1);
            })->get(['id', 'currency', 'legal_balance', 'lock_legal_balance'])
            ->toArray();
            
            

        $legal_wallet['totle'] = 0;
        $legal_wallet['usdt_totle'] = 0;
        foreach ($legal_wallet['balance'] as $k => $v) {
            if(in_array($v['currency'],[3])){
                $legal_wallet['balance'][$k]['is_charge'] = true;
            }else{
                $legal_wallet['balance'][$k]['is_charge'] = false;
            }
            $num = $v['legal_balance'] + $v['lock_legal_balance'];
            //$legal_wallet['totle'] += $num * $v['cny_price'];
            $legal_wallet['usdt_totle'] += $num * $v['usdt_price'];
        }
        
        $legal_wallet['CNY'] = '';
        $change_wallet['balance'] = UsersWallet::where('user_id', $user_id)
            ->whereHas('currencyCoin', function ($query) use ($currency_name) {
                empty($currency_name) || $query->where('name', 'like', '%' . $currency_name . '%');
            })->get(['id', 'currency', 'change_balance', 'lock_change_balance'])
            ->toArray();
        $change_wallet['totle'] = 0;
        $change_wallet['usdt_totle'] = 0;
        foreach ($change_wallet['balance'] as $k => $v) {
            if(in_array($v['currency'],[1,2,3])){
                $change_wallet['balance'][$k]['is_charge'] = true;
            }else{
                $change_wallet['balance'][$k]['is_charge'] = false;
            }
            $num = $v['change_balance'] + $v['lock_change_balance'];
           // $change_wallet['totle'] += $num * $v['cny_price'];
            $change_wallet['usdt_totle'] += $num * $v['usdt_price'];
        }
        
        $change_wallet['CNY'] = '';
        $lever_wallet['balance'] = UsersWallet::where('user_id', $user_id)
            ->whereHas('currencyCoin', function ($query) use ($currency_name) {
                empty($currency_name) || $query->where('name', 'like', '%' . $currency_name . '%');
                $query->where("is_lever", 1);
            })->get(['id', 'currency', 'lever_balance', 'lock_lever_balance'])->toArray();
        $lever_wallet['totle'] = 0;
        $lever_wallet['usdt_totle'] = 0;
        foreach ($lever_wallet['balance'] as $k => $v) {
            if(in_array($v['currency'],[])){
                $lever_wallet['balance'][$k]['is_charge'] = true;
            }else{
                $lever_wallet['balance'][$k]['is_charge'] = false;
            }
            $num = $v['lever_balance'] + $v['lock_lever_balance'];
            $lever_wallet['usdt_totle'] += $num * $v['usdt_price'];
        }
        
        $lever_wallet['CNY'] = '';

        $micro_wallet['CNY'] = '';
        $micro_wallet['totle'] = 0;
        $micro_wallet['usdt_totle'] = 0;
        $micro_wallet['balance'] = UsersWallet::where('user_id', $user_id)
            ->whereHas('currencyCoin', function ($query) use ($currency_name) {
                empty($currency_name) || $query->where('name', 'like', '%' . $currency_name . '%');
                // $query->where("is_micro", 1);
            })->get(['id', 'currency', 'micro_balance', 'lock_micro_balance'])
            ->toArray();
        foreach ($micro_wallet['balance'] as $k => $v) {
            if(in_array($v['currency'],[1,2,3,6,10,29])){
                $micro_wallet['balance'][$k]['is_charge'] = true;
            }else{
                $micro_wallet['balance'][$k]['is_charge'] = false;
            }
            $num = $v['micro_balance'] + $v['lock_micro_balance'];
           // $micro_wallet['totle'] += $num * $v['cny_price'];
            $micro_wallet['usdt_totle'] += $num * $v['usdt_price'];
        }
        $ExRate = Setting::getValueByKey('USDTRate', 6.5);

        //读取是否开启充提币
        $is_open_CTbi = Setting::where("key", "=", "is_open_CTbi")->first()->value;
        return $this->success([
            'legal_wallet' => $legal_wallet,
            'change_wallet' => $change_wallet,
            'micro_wallet' => $micro_wallet,
            'lever_wallet' => $lever_wallet,
            'ExRate' => $ExRate,
            "is_open_CTbi" => $is_open_CTbi
        ]);
    }


    public function currencyList()
    {
        $user_id = Users::getUserId();
        $currency = Currency::where('is_display', 1)->orderBy('sort', 'asc')->get()->toArray();
        if (empty($currency)) {
            return $this->error("暂时还没有添加币种");
        }
        foreach ($currency as $k => $c) {
            $w = Address::where("user_id", $user_id)->where("currency", $c['id'])->count();
            $currency[$k]['has_address_num'] = $w; 
        }
        return $this->success($currency);
    }

    public function addAddress()
    {
        $user_id = Users::getUserId();
        $id = Input::get("currency_id", '');
        $address = Input::get("address", "");
        $notes = Input::get("notes", "");
        if (empty($user_id) || empty($id) || empty($address)) {
            return $this->error("参数错误");
        }
        $user = Users::find($user_id);
        if (empty($user)) {
            return $this->error("用户未找到");
        }
        $currency = Currency::find($id);
        if (empty($currency)) {
            return $this->error("此币种不存在");
        }
        $has = Address::where("user_id", $user_id)->where("currency", $id)->where('address', $address)->first();
        if ($has) {
            return $this->error("已经有此提币地址");
        }
        try {
            $currency_address = new Address();
            $currency_address->address = $address;
            $currency_address->notes = $notes;
            $currency_address->user_id = $user_id;
            $currency_address->currency = $id;
            $currency_address->save();
            return $this->success("添加提币地址成功");
        } catch (\Exception $ex) {
            return $this->error($ex->getMessage());
        }
    }

    public function addressDel()
    {
        $user_id = Users::getUserId();
        $address_id = Input::get("address_id", '');

        if (empty($user_id) || empty($address_id)) {
            return $this->error("参数错误");
        }
        $user = Users::find($user_id);
        if (empty($user)) {
            return $this->error("用户未找到");
        }
        $address = Address::find($address_id);

        if (empty($address)) {
            return $this->error("此提币地址不存在");
        }
        if ($address->user_id != $user_id) {
            return $this->error("您没有权限删除此地址");
        }

        try {
            $address->delete();
            return $this->success("删除提币地址成功");
        } catch (\Exception $ex) {
            return $this->error($ex->getMessage());
        }
    }
	public function chargeReq(){
		$user_id = Users::getUserId();

        $currency_id = xssCode(Input::get("currency", ''));
        $type = xssCode(Input::get("type", ''));
        $account = Input::get("account", '');
        $amount = xssCode(Input::get("amount",0));
        
        
        if(empty($currency_id) || empty($amount)) {
        	return $this->error('参数错误1');
        }
        $currency = Db::table('currency')->where('id',$currency_id)->first();
        if(!$currency) {
        		return $this->error('参数错误2');
        } 
        $user = Users::find($user_id);
        
        if (empty($user)){
            return $this->error('用户不存在');
        } 
        
        // if($amount < 1 || $amount > 999999){
        //     return $this->error('Valor de recarga 10-100000');
        // }
        
        $oid = "CZ".time().rand(10000,99999);
        $url = '';
        //通道信息
        $payments = Db::table("payments")->find($type);
        if($payments->id == 2){
            $paymentsRes = $this->KirinPay($oid,$amount,$payments->exchange_rate);
            if($paymentsRes['code'] != 200){
                 return $this->error($paymentsRes['message']);
            }
            $url = $paymentsRes["data"]["payUrl"];
        }elseif($payments->id == 3){
            $paymentsRes = $this->caolong($oid,$amount,$payments->exchange_rate);
            if($paymentsRes['respCode'] != "SUCCESS"){
                 return $this->error($paymentsRes['tradeMsg']);
            }
            $url = $paymentsRes["payInfo"];
        }elseif($payments->id == 4){
            $paymentsRes = $this->Wepay($oid,$amount,$payments->exchange_rate);
            if($paymentsRes['respCode'] != "SUCCESS"){
                 return $this->error($paymentsRes['tradeMsg']);
            }
            $url = $paymentsRes["payInfo"];
        }elseif($payments->id == 5){
            $paymentsRes = $this->cgbh_gbkyd($oid,$amount,$payments->exchange_rate);
            if(empty($paymentsRes["order_data"])){
                 return $this->error($paymentsRes['err_msg']);
            }
            $url = $paymentsRes['order_data'];
        }elseif($payments->id == 6){
            $paymentsRes = $this->speedly_pay($oid,$amount,$payments->exchange_rate);
            if($paymentsRes["state"] != "ok"){
                 return $this->error($paymentsRes['errorMsg']);
            }
            $url = $paymentsRes['data']["redirect_url"];
        }elseif($payments->id == 8){
            $paymentsRes = $this->gfpay($oid,$amount,$payments->exchange_rate);
           // dump($paymentsRes);die;
            if($paymentsRes["code"] != "00000"){
                 return $this->error($paymentsRes['msg']);
            }
            $url = json_decode($paymentsRes['cipher'],true)['paymentUrl'];
        }elseif($payments->id == 7){
            $paymentsRes = $this->typay($oid,$amount,$payments->exchange_rate);
           // dump($paymentsRes);die;
            if($paymentsRes["code"] != "00000"){
                 return $this->error($paymentsRes['msg']);
            }
            $url = $paymentsRes["data"]["payUrl"];
        }elseif($payments->id == 10){
            $paymentsRes = $this->stabpay($oid,$amount,$payments->exchange_rate);
           // dump($paymentsRes);die;
            if($paymentsRes["code"] != "200"){
                 return $this->error($paymentsRes['msg']);
            }
            $url = $paymentsRes['data']['jumpUrl'];
        }elseif($payments->id == 11){
            $paymentsRes = $this->Wakapay($oid,$amount,$payments->exchange_rate);
           // dump($paymentsRes);die;
            if($paymentsRes["status"] != "success"){
                 return $this->error($paymentsRes['status_mes']);
            }
            $url = $paymentsRes['order_data'];
        }elseif($payments->id == 12){
            $paymentsRes = $this->BetcatPay($oid,$amount,$payments->exchange_rate);
           // dump($paymentsRes);die;
            if($paymentsRes["code"] != "0"){
                 return $this->error($paymentsRes['error']);
            }
            $url = $paymentsRes['data']['params']['url'];
        }elseif($payments->id == 13){
            $paymentsRes = $this->mxpay($oid,$amount,$payments->exchange_rate);
         //   dump($paymentsRes);die;
            if($paymentsRes["resp_code"] != "200"){ 
                 return $this->error($paymentsRes['p_code']);
            }
            $url = 'https://cashier.bootcdn.cc/pay/'.$paymentsRes['data']['paycode'];
        }elseif($payments->id == 14){
            $paymentsRes = $this->deslespay($oid,$amount,$payments->exchange_rate);
           // dump($paymentsRes);die;
            if($paymentsRes["code"] != "200"){
                 return $this->error($paymentsRes['message']);
            }
            $url = $paymentsRes['paymentUrl'];
        }elseif($payments->id == 15){
            $paymentsRes = $this->ppay($oid,$amount,$payments->exchange_rate);
           // dump($paymentsRes);die;
            if($paymentsRes["code"] != "SUCCESS"){
                 return $this->error($paymentsRes['msg']);
            }
            $url = $paymentsRes['payLink'];
        }elseif($payments->id == 16){
            $paymentsRes = $this->Spe($oid,$amount,$payments->exchange_rate);
            if($paymentsRes["state"] != "ok"){
                 return $this->error($paymentsRes['errorMsg']);
            }
            $url = $paymentsRes['data']["redirect_url"];
        }elseif($payments->id == 17){
            $paymentsRes = $this->payment($oid,$amount,$payments->exchange_rate);
            if($paymentsRes['respCode'] != "SUCCESS"){
                 return $this->error($paymentsRes['tradeMsg']);
            }
            $url = $paymentsRes["payInfo"];
        }elseif($payments->id == 18){
            $paymentsRes = $this->chzfpay($oid,$amount,$payments->exchange_rate);
            if($paymentsRes['return_code'] != "SUCCESS"){
                 return $this->error($paymentsRes['return_msg']);
            }
            $url = $paymentsRes["mweb_url"];
        }elseif($payments->id == 20){
            $paymentsRes = $this->lexmpay($oid,$amount,$payments->exchange_rate);
            if($paymentsRes['respCode'] != "SUCCESS"){
                 return $this->error($paymentsRes['tradeMsg']);
            }
            $url = $paymentsRes["payInfo"];
        }elseif($payments->id == 21){
            $paymentsRes = $this->deslespays($oid,$amount,$payments->exchange_rate);
           // dump($paymentsRes);die;
            if($paymentsRes["code"] != "200"){
                 return $this->error($paymentsRes['message']);
            }
            $url = $paymentsRes['paymentUrl'];
        }elseif($payments->id == 22){
            $paymentsRes = $this->xmlypay($oid,$amount,$payments->exchange_rate);
           // dump($paymentsRes);die;
            if($paymentsRes["code"] != '0'){
                 return $this->error($paymentsRes['msg']);
            }
            $url = $paymentsRes["data"]["payData"];
        }elseif($payments->id == 23){
            $paymentsRes = $this->koalapay($oid,$amount,$payments->exchange_rate);
         
            if($paymentsRes["err"] != '0'){
                 return $this->error($paymentsRes['err_msg']);
            }
            $url = $paymentsRes["url"];
            
        }
      
        
        
        $userLevel = $user['user_level'] > 0 ? UserLevelModel::find($user['user_level']) : null;
        $give = $userLevel ? round(($amount * $userLevel['give'] / 100),8) : 0;
        $give_rate = $userLevel ? $userLevel['give'] : 0;
        $data = [
            "oid"=>$oid,
            'type'=>$type,
        	'uid' => $user_id,
        	'currency_id' => $currency_id,
        	'amount' => $amount,
        	'give' => $give,
        	'give_rate' => $give_rate,
        	'user_account' => $account,
        	'status' => 1,
        	'created_at' => date('Y-m-d H:i:s')
        	];
         Db::table('charge_req')->insert($data);
         return json_encode(["type"=>"ok",'message'=>__("massage.申请成功"),"url"=>$url]);
       
	}
	
	public function koalapay($oid,$money,$exchange_rate)
	{
	    $url = "http://pay.kaolapay.org/api/recharge";
        
        
        $pay["app_key"] = "KALM10273";
        $pay["ord_id"] = $oid;
        
        $pay["balance"] = $money;
        
        $pay["notify_url"] = "https://bitpietrx.com/api/koalapay";
        $pay["sign"] = $this->signkoalapay($pay,"46de508cef2b7bf976c2afb7597e5990");
       
       
        $jieguos = json_decode($this->curl_posts($url,$pay),true);
        
        return $jieguos;
	}
	
	function signkoalapay($param,$salt){
    $data = $param;
    ksort($data);

    $str="";
    foreach ($data as $key => $value)
    {
        $str=$str.$value;
    }
    $str = $str.$salt;
    return md5($str);
}
	
	public function xmlypay($oid,$money,$exchange_rate)
	{
	     $url = "https://pay.xmlypay.com/api/anon/pay/unifiedOrder";
        
        
        $pay["mchNo"] = "X1697447886";
        $pay["mchOrderNo"] = $oid;
        $pay["appId"] = "652cffe8e4b08ed80f2e9d1f";
        $pay["wayCode"] = '801'; 
        $pay["amount"] = $money;
        $pay["currency"] = 'BRL';
        $pay["subject"] = 'test';
        $pay["body"] = 'test';
        $pay["notifyUrl"] = "https://v77pro.icu/api/xmlypay";
        $pay["reqTime"] = time();
        $pay["version"] = "1.0";
        $pay["key"] = "lYmPsK7a0AYikSggi1tUvMdiCM3qNBC3";
        $pay["signType"] = "MD5";
        
        $jisoasas = $this->dsfdsf($pay);
        unset($jisoasas["key"]);
        
       
        
        $jieguos = json_decode($this->curl_posts($url,$jisoasas),true);
        
        return $jieguos;
	}
	
		/**
 * [ ASCII 编码 ]
 * @param array  编码数组 
 * @param string 签名键名   => sign
 * @param string 密钥键名   => key
 * @param bool   签名大小写 => false(大写)
 * @param string 签名是否包含密钥 => false(不包含)
 * @return array 编码好的数组
 */
function dsfdsf($asciiData, $asciiSign = 'sign', $asciiKey = 'key', $asciiSize = true, $asciiKeyBool = false)
{
    //编码数组从小到大排序
    ksort($asciiData);
    //拼接源文->签名是否包含密钥->密钥最后拼接
    $MD5str = "";
    foreach ($asciiData as $key => $val) {
        if (!$asciiKeyBool && $asciiKey == $key) continue;
        $MD5str .= $key . "=" . $val . "&";
    }
    $sign = $MD5str . $asciiKey . "=" . $asciiData[$asciiKey];
    //大小写->md5
    $asciiData[$asciiSign]  = $asciiSize ? strtoupper(md5($sign)) : strtolower(md5($sign));
    return $asciiData;
}
	
	public function lexmpay($oid,$money,$exchange_rate)
	{
	    $url = "https://payment.lexmpay.com/pay/web";
	            
        $pay["mch_id"] = 966966002;
        $pay["notify_url"] = 'https://gemvip.cc/api/Wepay';
        $pay["page_url"] = 'https://geminizxy.com/';
        $pay["version"] = "1.0";
        $pay["mch_order_no"] = $oid; 
        $pay["pay_type"] = 620; 
        $pay["trade_amount"] = $money * $exchange_rate;
         
        $pay["order_date"] = date("Y-m-d H:i:s");
        $pay["key"] = "f9616cdb340a4d3688ad4c6151bc83c1";
        
        $pay = $this->ASCII($pay);
        $pay["sign_type"] = "MD5"; 
         unset($pay["key"]);
         
       
        $ch = curl_init();    
        curl_setopt($ch,CURLOPT_URL,$url); //支付请求地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($pay));  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $response= curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response,true);
        return $response;
	}
	
	public function chzfpay($oid,$money,$exchange_rate)
	{
	   
        $params  = array(
            'member_id' => 'CH1002254',
            'body' => 'Member recharge',
            'out_trade_no' => $oid,
            'fee_type' => 'BRL',
            'total_fee' => $money*100,
            'spbill_create_ip' => $_SERVER["REMOTE_ADDR"],
            'notify_url' => 'https://bitpietrx.com/api/chzfpay',
            'trade_type' => '201',
            'sign_type' => 'MD5',
            // 'sign' => 'C380BEC2BFD727A4B6845133519F3AD6' // 请替换为实际的签名
        );
     
        
        ksort($params);
        $paramString = '';
        foreach ($params as $key => $value) {
            $paramString .= $key . '=' . $value . '&';
        }
        
        $paramString = rtrim($paramString, '&');
        
        $stringSignTemp = $paramString . '&key=' . 'c0cd96811fcc44ecbbd7c40a88fb3dcc';
        
        $sign = strtoupper(md5($stringSignTemp));
        
        $params['sign'] = $sign;
        
      
       // 转换为 JSON 格式
        $jsonData = json_encode($params);
        
        // 设置 API 请求 URL
        $url = 'https://api.chzfpay.com/api/pay/applyOrder'; // 替换为实际的接口地址
        
        // 初始化 cURL 会话
        $ch = curl_init($url);
        
        // 设置 cURL 选项
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // 设置请求头
        $headers = array(
            'Content-Type: application/json;charset=UTF-8'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // 执行请求并获取响应
        $response = curl_exec($ch);
        // 关闭 cURL 会话
        curl_close($ch);
        $response= json_decode($response,true);  
        return $response;
        
	}
	
	
		public function payment($oid,$money,$exchange_rate)
	{
	    $url = "https://payment.dzxum.com/pay/web";
	            
        $pay["mch_id"] = 300009559;
        $pay["notify_url"] = 'https://bitpietrx.com/api/Wepay';
        $pay["page_url"] = 'https://bitpietrx.com/';
        $pay["version"] = "1.0";
        $pay["mch_order_no"] = $oid; 
        $pay["pay_type"] = 620; 
        $pay["trade_amount"] = $money * $exchange_rate;
         
        $pay["order_date"] = date("Y-m-d H:i:s");
        $pay["key"] = "ff7cbf9ae91f43279eb02c6a0e794618";
        
        $pay = $this->ASCII($pay);
        $pay["sign_type"] = "MD5"; 
         unset($pay["key"]);
         
       
        $ch = curl_init();    
        curl_setopt($ch,CURLOPT_URL,$url); //支付请求地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($pay));  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $response= curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response,true);
      // dump($response);die;
        return $response;
	}
	
	public function Spe($oid,$money,$exchange_rate)
	{
	    $user = Users::find(Users::getUserId());
	    
	     //请求头
        $header[0] = "Content-Type:application/json;charset=UTF-8";
        $header[1] = "ApiVersion:1.0";
        $header[2] = "AppId:sp1699067324172210176m";
        $header[3] = "Noncestr:" . mt_rand(1, 99999999);
        $header[4] = "Timestamp:" . $this->speedly_getMillisecond();
    
        $domain = "https://api.speedlyglobal.com";
        $url = $domain . "/api/pay/payment";
    
        $pay_secert = 'HYN9YVF40PAINOBNLYTCNTATUIKC9BU1';
        
        //payee 不参与签名
        $payee = array(
            'name' => $user->account_number,
            'document' => $user->id,
            'ip' => $_SERVER["REMOTE_ADDR"]
        );
       
        $data = array(
            'country' => 'BR',
            'currency' => 'BRL',
            'payment_method_id' => 'PIX',
            'payment_method_flow' => 'REDIRECT',
            'order_id' => $oid,
            'amount' => sprintf("%01.2f",round($money * $exchange_rate,2)),
            'notification_url' => 'https://bitpietrx.com/api/speedly_pay',
            'success_redirect_url' => 'https://bitpietrx.com/h5',    
            'timestamp' => $this->speedly_getMillisecond(),    
        );
        $data['signature']=strtoupper($this->speedly_md5_sign($data,'&key='.$pay_secert));
        $data['payee']=$payee;
        $post_data = array(
            'merchant_no' => 'BC101129',
            'data' => $data,
        );
        $jsonData = json_encode($post_data);
      // dump($jsonData);die;
        $res= json_decode($this->speedly_post($url,$jsonData,$header),true);
        return $res;
       
	}
	
	public function ppay($oid,$money,$exchange_rate)
   {
       $url = "https://ord.pollsypay.com/pay/order";
	    $pay["merNo"] = "777986026";
	    $pay["merchantOrderNo"] = $oid;
	    $pay["amount"] = sprintf("%01.2f",round($money * $exchange_rate,2));
	     $pay["payCode"] = '623';
	    $pay["callbakUrl"] = "https://v77pro.com/h5/#/";
	    $pay["notifyUrl"] = "https://v77pro.com/api/ppay";
	    
	    $pay["currency"] = 'BRL';
	    $pay["goodsName"] = 'test';
	    $pay["sign"] = $this->md5Sign($pay,"1398b90fb9124a9d80519f4ffb51b320");
	    
         
       
        $response = json_decode($this->httpPost($url,$pay),true);
        
	    return $response;
   }
   
   function md5Sign($params, $merKey)
{
    ksort($params); //排序
    $signStr = '';
    foreach($params as $key => $val){
        if($val != null){
            $signStr .= $key .'='.$val.'&';
        }
    }
    $signStr .= 'key='.$merKey;
    return strtolower(md5($signStr));
}
	
	public function deslespays($oid,$money,$exchange_rate)
	{
	     $url = "http://pay.desles.xyz/pay/payOrder/bx/pay";
	    $pay["machId"] = "9d24f194a48041fbbbe078d38677a909";
	    $pay["merchantOrderNo"] = $oid;
	    $pay["amount"] = sprintf("%01.2f",round($money * $exchange_rate,2));
	    $pay["successUrl"] = "https://bitpietrx.com/h5";
	    $pay["returnUrl"] = "https://bitpietrx.com/api/deslespay";
	    $pay["key"] = "795a556a36de45cba8330169befb6f9e";
	    $pay = $this->ASCII($pay);
     
         unset($pay["key"]);
         
          
        $response = json_decode($this->curl_posts($url,$pay),true);
	    return $response;   
	}
	public function deslespay($oid,$money,$exchange_rate)
	{
	    $url = "http://pay.desles.xyz/pay/payOrder/bx/pay";
	    $pay["machId"] = "a06d12a19a4f4734aa9019734fe00567";
	    $pay["merchantOrderNo"] = $oid;
	    $pay["amount"] = sprintf("%01.2f",round($money * $exchange_rate,2));
	    $pay["successUrl"] = "https://bitpietrx.com/h5";
	    $pay["returnUrl"] = "https://bitpietrx.com/api/deslespay";
	    $pay["key"] = "25c77c68476f4a09b77ba172dee9ae42";
	    $pay = $this->ASCII($pay);
     
         unset($pay["key"]);
         
       
        $response = json_decode($this->curl_posts($url,$pay),true);
	    return $response;
	}
	
	public function mxpay($oid,$money,$exchange_rate)
	{
	    
        $pay_notifyurl   = "https://bitpietrx.com/api/mxpay";   //服务端返回地址
        $pay_callbackurl = "https://bitpietrx.com/h5";  //页面跳转返回地址
        
        $tjurl           = "https://pnice.live450.com/Pay_Index.html";   //提交地址
        $pay_memberid    = "230936681";//商户号
        $Md5key          = "z62uc64cj70twwbvlq1r7sy5msscu4dq";   //密钥 
        
        $pay_orderid = $oid;
        
        $native = array(
            "pay_memberid" =>$pay_memberid,
            "pay_orderid" => $pay_orderid,
            "pay_applydate" => date("Y-m-d H:i:s"),
            "pay_notifyurl" => $pay_notifyurl,
            "pay_callbackurl" => $pay_callbackurl,
            "pay_amount" => sprintf("%01.2f",round($money * $exchange_rate,2)),
            "pay_bankcode" => "956",
            
        );
        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $Md5key));
        $native["pay_md5sign"] = $sign;
        
        $native['type'] ='json';
      
        $result = json_decode($this->httpPost($tjurl,$native),true);//返回的是js代码跳转地址
        
        return $result;
        //dump($result);die;
	}
	
	
	function httpPost($url, $data){
        $headers = array('Content-Type: application/x-www-form-urlencoded');
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno'.curl_error($curl);//捕抓异常
        }
        curl_close($curl); // 关闭CURL会话
        return $result;
    }
	
	public function BetcatPay($oid,$money,$exchange_rate)
	{
	     $url = "https://v1.a.betcatpay.com/api/v1/payment/order/create";
	            
        $pay["appId"] = "9b92a878e96611dd2e3768c4f084d27a";
        $pay["merOrderNo"] = $oid; 
        $pay["currency"] = "BRL";
        $pay["amount"] = sprintf("%01.2f",round($money * $exchange_rate,2));
        $pay["returnUrl"] = 'https://v77pro.com/h5/#/';
        $pay["notifyUrl"] = 'https://v77pro.com/api/betcatpay';
        
        $pay["key"] = "237b65a50f2e1a71a0af90fc14c44544";
        
        $pay = $this->ASCII22($pay);
     
         unset($pay["key"]);
        //  dump($pay); 
       
        $response = json_decode($this->curl_posts($url,$pay),true);
        //dump($response);die;
        return $response;
	}
	 	/**
 * [ ASCII 编码 ]
 * @param array  编码数组 
 * @param string 签名键名   => sign
 * @param string 密钥键名   => key
 * @param bool   签名大小写 => false(大写)
 * @param string 签名是否包含密钥 => false(不包含)
 * @return array 编码好的数组
 */
public function ASCII22($asciiData, $asciiSign = 'sign', $asciiKey = 'key', $asciiSize = false, $asciiKeyBool = false)
{
    //编码数组从小到大排序
    ksort($asciiData);
    //拼接源文->签名是否包含密钥->密钥最后拼接
    $MD5str = "";
    foreach ($asciiData as $key => $val) {
        if (!$asciiKeyBool && $asciiKey == $key) continue;
        $MD5str .= $key . "=" . $val . "&";
    }
    $sign = $MD5str . $asciiKey . "=" . $asciiData[$asciiKey];
	$sign = $sign;
	//echo $sign;
    //大小写->md5
    $asciiData[$asciiSign]  = $asciiSize ? hash('sha256', $sign) : hash('sha256', $sign);
    return $asciiData;
}
	public function Wakapay($oid,$money,$exchange_rate)
	{
	    $url = "https://wakapayplus.com/gateway/";
	            
        $pay["mer_no"] = "8691211";
        $pay["order_no"] = $oid; 
        
        $pay["order_amount"] = sprintf("%01.2f",round($money * $exchange_rate,2));
        $pay["payname"] = "test";
        $pay["payemail"] = "xiaoming@email.com";
        $pay["payphone"] = "4354353";
        $pay["currency"] = "BRL";
        $pay["paytypecode"] = "18002";
        $pay["method"] = "trade.create";
        
        
        
        $pay["returnurl"] = 'https://bitpietrx.com/api/wakapay';
        $pay["pageurl"] = 'https://bitpietrx.com/h5/#/';
        
        $pay["key"] = "4176f1ebaf94498fe14b8bc780280c2d";
        
        $pay = $this->ASCII33($pay);
     
         unset($pay["key"]);
          
        $response = json_decode($this->curl_posts($url,$pay),true);
    
        return $response;
	}
	
	   	/**
 * [ ASCII 编码 ]
 * @param array  编码数组 
 * @param string 签名键名   => sign
 * @param string 密钥键名   => key
 * @param bool   签名大小写 => false(大写)
 * @param string 签名是否包含密钥 => false(不包含)
 * @return array 编码好的数组
 */
public function ASCII33($asciiData, $asciiSign = 'sign', $asciiKey = 'key', $asciiSize = false, $asciiKeyBool = false)
{
    //编码数组从小到大排序
    ksort($asciiData);
    //拼接源文->签名是否包含密钥->密钥最后拼接
    $MD5str = "";
    foreach ($asciiData as $key => $val) {
        if (!$asciiKeyBool && $asciiKey == $key) continue;
        if($key == "returnurl"){
            $MD5str .= $key . "=" . $val ;
        }else{
            $MD5str .= $key . "=" . $val . "&";
        }
        
       
    }
    $sign = $MD5str . $asciiData[$asciiKey];
	$sign = $sign;
//	echo $sign;
    //大小写->md5
    $asciiData[$asciiSign]  = $asciiSize ? strtoupper(md5($sign)) : strtolower(md5($sign));
    return $asciiData;
}
	
	public function stabpay($oid,$money,$exchange_rate)
	{
	    $url = "https://api.stabpay.com/payment/createOrder";
	    $pay["merchantId"] = "26bfa545428241aabd864138d832af6c";
	    $pay["country"] = '2';
	    $pay["orderNo"] = $oid;
	    $pay["amount"] = sprintf("%01.2f",round($money * $exchange_rate,2));
	    $pay["returnUrl"] = "https://bitpietrx.com/h5";
	     $pay["notifyUrl"] = "https://bitpietrx.com/api/stabpay";
	    $key = "YVWFS6PB1AE8NVO6ACU0VFMFF9RCCLHIG2DLX6L842IKB3TK32Z50658K697Q776";
        $string = $this->ascii_params($pay);
      
        $sign = $this->getSignature($string, $key);
       
	    $pay["sign"] = $sign;
	   
	   
	    
	    
	    
	   $zhis = $this->curl_post1($url,$pay);
	   return json_decode($zhis,true);
	}
	
	
	
	/**
 * 自定义ascii排序 返回字符串
 * @param array $params
 * @return string
 */
function ascii_params($params = array())
{
    if (!empty($params)) {
        $p = ksort($params);
        if ($p) {
            $str = '';
            foreach ($params as $k => $val) {
                $str .= $k . '=' . $val . '&';
            }
            $strs = rtrim($str, '&');
            return $strs;
        }
    }
    return '参数错误';
}

/**
 * @brief 使用HMAC-SHA1算法生成oauth_signature签名值
 *
 * @param  $str   string 源串
 * @param $keystring 密钥
 *
 * @return string
 */
function getSignature($str, $key)
{
    $signature = "";
    if (function_exists('hash_hmac')) {
        $signature = base64_encode(hash_hmac("sha1", $str, $key, true));
    } else {
        $blocksize = 64;
        $hashfunc = 'sha1';
        if (strlen($key) > $blocksize) {
            $key = pack('H*', $hashfunc($key));
        }
        $key = str_pad($key, $blocksize, chr(0x00));
        $ipad = str_repeat(chr(0x36), $blocksize);
        $opad = str_repeat(chr(0x5c), $blocksize);
        $hmac = pack(
            'H*', $hashfunc(
                ($key ^ $opad) . pack(
                    'H*', $hashfunc(
                        ($key ^ $ipad) . $str
                    )
                )
            )
        );
        $signature = base64_encode($hmac);
    }
//    echo($signature);
    return $signature;
}


	
	
	public function typay($oid,$money,$exchange_rate){
	    $url = "http://so0913.cn/newbankPay/crtOrder.do";
	    $pay["appId"] = "20409094";
	    $pay["appOrderNo"] = $oid;
	    $pay["orderAmt"] = sprintf("%01.2f",round($money ,2));
	    $pay["payId"] = 1137;
	    $pay["key"] = "08FA60CDC3BD2A2A4C8EAB0CC7D07D2F";
	    
	    
	    
	    $pay = $this->ASCII($pay,"sign","key",true);
      
         unset($pay["key"]);
        $pay["notifyURL"] = "https://v77pro.com/api/typay";
	    $pay["jumpURL"] = "https://v77pro.com/h5";
	    
	//  dump($pay);
        $ch = curl_init();    
        curl_setopt($ch,CURLOPT_URL,$url); //支付请求地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($pay));  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $response= curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response,true);
     //  dump($response);die;
        return $response;
	}
	
	public function gfpay($oid,$money,$exchange_rate)
	{ 
	    $url = "https://paymentapi.gfpays.com/paymentservice/pay/json";
	    
	    // $orderId = "EP".date("YmdHis").rand('0000','9999');
	     
	      //公共参数
        $data = array(
        	"version" => "v3",
        	"merchantId" => "10541",
        	"charset" => "UTF-8",
        	"signType" => "MD5",
        	"cipher" => ""
        );
        
        //业务参数
        $payload = array(
        	"reqNo" => $oid,
        	"price" => (int)sprintf("%01.2f",round($money * $exchange_rate,2)),
        	"payType" => 'BR-PIX',
        	"notifyUrl" => "https://bitpietrx.com/api/gfpay1",
        	"returnUrl" => "https://bitpietrx.com/",
        	"deviceType" => "WAP"
        );
        
        
        $plainReqPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        $data["cipher"] = $plainReqPayload;
        $data["sign"] = $this->generateMD5Signature($data,'94f20c8a08fe49058bfe7af8c8b9ab86');
        $respstring = $this->doPost($url, $data);	 
        
        
        $response = json_decode($respstring,true);
        return $response;
     
	}
	
   
    		// 请求数据
	function doPost($url, $data){
		$curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $res = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno'.curl_error($curl);//捕抓异常
        }
        curl_close($curl); // 关闭CURL会话
		return $res;
	}
	
	function generateMD5Signature($params, $mkey) {
    // 将参数按照字母升序排序
        ksort($params);
        
        // 拼接参数字符串
        $paramString = '';
        foreach ($params as $key => $value) {
            $paramString .= $key . '=' . $value . '&';
        }
        
        // 去除末尾的'&'
        $paramString = rtrim($paramString, '&');
        
        // 拼接商户收款密钥
        $paramString .= '&mkey=' . $mkey;
        
        // 计算MD5签名
        $signature = md5($paramString);
        
        return $signature;
    }
    
	public function speedly_pay($oid,$money,$exchange_rate)
	{
	    $user = Users::find(Users::getUserId());
	    
	     //请求头
        $header[0] = "Content-Type:application/json;charset=UTF-8";
        $header[1] = "ApiVersion:1.0";
        $header[2] = "AppId:sp1692943428385640448m";
        $header[3] = "Noncestr:" . mt_rand(1, 99999999);
        $header[4] = "Timestamp:" . $this->speedly_getMillisecond();
    
        $domain = "https://api.speedlyglobal.com";
        $url = $domain . "/api/pay/payment";
    
        $pay_secert = '6V9QEA1DG3IZ3ECJDA5J1FVFLNHJDDCZ';
        
        //payee 不参与签名
        $payee = array(
            'name' => $user->account_number,
            'document' => $user->id,
            'ip' => $_SERVER["REMOTE_ADDR"]
        );
       
        $data = array(
            'country' => 'BR',
            'currency' => 'BRL',
            'payment_method_id' => 'PIX',
            'payment_method_flow' => 'REDIRECT',
            'order_id' => $oid,
            'amount' => sprintf("%01.2f",round($money * $exchange_rate,2)),
            'notification_url' => 'https://bitpietrx.com/api/speedly_pay',
            'success_redirect_url' => 'https://bitpietrx.com/h5',    
            'timestamp' => $this->speedly_getMillisecond(),    
        );
        $data['signature']=strtoupper($this->speedly_md5_sign($data,'&key='.$pay_secert));
        $data['payee']=$payee;
        $post_data = array(
            'merchant_no' => 'BC100991',
            'data' => $data,
        );
        $jsonData = json_encode($post_data);
     
        $res= json_decode($this->speedly_post($url,$jsonData,$header),true);
        return $res;
        dump($res);die;
       
	}
	
	 /**
    * POST发送数据
    * @param String $url     请求的地址
    * @param Array  $header  自定义的header数据
    * @param Array  $data POST的数据
    * @return String
    */
   function speedly_post($url, $jsonData, $header = []){
       
       $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
         curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
         // POST数据
         curl_setopt($ch, CURLOPT_POST, 1);
         // 把post的变量加上
        // $header = array("content-type: application/json; charset=UTF-8");
         curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
         curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
         $output = curl_exec($ch);
         curl_close($ch);
         return $output;
       
       
   }
/**
* 获取utc时间戳
*/
   function speedly_getMillisecond(){
       $sysTimeZone = date_default_timezone_get(); //先取出系统所在服务器的时区
       date_default_timezone_set('UTC'); //设置时区为UTC时区
       list($s1, $s2) = explode(' ', microtime());
       $timestamp = (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
       date_default_timezone_set($sysTimeZone); //设置时区为服务器时区
       return $timestamp;
   }

    /** 
     * MD5 签名 
     * @param array $data 
     * @param string $key 
     * @param bool $is_md5 
     * @return string 
   */
   function speedly_md5_sign($data, $key,$is_md5 = true){   
       ksort($data);   
       $string = '';  
       foreach( $data as $k => $vo ){     
           if($vo !== '')           
               $string .=  $k . '=' . $vo . '&' ;    
       }   
       $string = rtrim($string, '&');    
       $result = $string . $key;   
       return $is_md5 ? md5($result) : $result;
   }
	
	
	public function cgbh_gbkyd($oid,$money,$exchange_rate)
	{
	    $url = "https://cktos.gtgbk.com/ty/orderPay";
        
        
        $pay["mer_no"] = "861100000039199";
        $pay["mer_order_no"] = $oid;
        $pay["pname"] = "zhang san";
        $pay["pemail"] = "test@gmail.com";
        $pay["phone"] = "13122336688";
        $pay["order_amount"] = $money * $exchange_rate;;
        $pay["ccy_no"] = "BRL";
        $pay["busi_code"] = "100601";
        $pay["notifyUrl"] = "https://gemvip.cc/api/cgbh_gbkyd";
        $pay["pageUrl"] = "https://geminizxy.com/h5";
        $jisoasas = $this->encrypt($pay);
        $jieguos = json_decode($this->curl_posts($url,$jisoasas),true);
        
        return $jieguos;
        
	}
	//加密
	public function encrypt($data){
		ksort($data);
		$str = '';
		foreach ($data as $k => $v){
		  if(!empty($v)){
			$str .=(string) $k.'='.$v.'&';
		  }
		}    
		$str = rtrim($str,'&');
		$encrypted = '';
		//替换成自己的私钥
		$pem = chunk_split('MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAJELYEiZ3yIYOo2NzbwcD5Fm3w5NWyUG0UaYbX8l+zlqtKrCGyUQhjxpDOGiz7QgudPlfVt4yc+zFbtxJGD9jTzIHCkydNiGVzhlLFju6yXnNTD7FU5v1eq+fFsv/oZbKviTVapgkkMbjLm5zfWqxQMOzTMf6T7RSPhS66oZ92wTAgMBAAECgYEAjJbeSQD8y2t4teSRWphIbsOryY0pn4YwK6Fr4SbLkCfh3vIupYqS0tNwbPUHJq3h8YYsMBGwa+ZGVl2gyXJ7Bs0t5/dEnHD5ArMTxhSc+CqKt54Y0b1/Z4U9XiU+qG1gkkZS5Gcxjwyc0kUW2M6uga46N2WrjkHnDWs+4spCXuECQQDMTrpXEHAwgmmvLssOlSgm56aI3FBKiI0UOlBEbI0P0KaDZc4OPg5BE/AmKlTDt84Mcg1PDw0JJJbq/0kv6PJHAkEAtb4ZMPArDqPWKG6EipT37xI6HhM1WNU4YI3jpECoiJaYH65vZB4M+uvz0bp+uOMRdj4LddPX8JTmawRjlefx1QJBALaSn/hPq0HeOJ0g3rpgVio2Fl71KhcA4bmyxqnuqzv3w+Vl43ZcxBYpwBALAgaISWxbu0Lr+0UxWmAT044px98CQFCgPui5A0EBafaR4Pbh04QZ3/KLrvTz0ojzKXQqwxmlRWN4rS4LLtL6bjYyuBkpkwuTxt3E112BkR8U2WEdfukCQDujWa09aQEGBCgw1w2uWiOJsuaOSefpF1DfVmHTwSsM7tj3hqoDiDivQWe//ftW2Ua+n1V6tIRK8udLWaVFcOE=', 64, "\n");
		$pem = "-----BEGIN PRIVATE KEY-----\n" . $pem . "-----END PRIVATE KEY-----\n";
		$private_key = openssl_pkey_get_private($pem);
		$crypto = '';
		foreach (str_split($str, 117) as $chunk) {
		  openssl_private_encrypt($chunk, $encryptData, $private_key);
		  $crypto .= $encryptData;
		}
		$encrypted = base64_encode($crypto);
		$encrypted = str_replace(array('+','/','='),array('-','_',''),$encrypted);
		
		$data['sign']=$encrypted;
		return $data;
	}
	
	public function sendpost($url, $data){
	        $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            return $response;
	}
	function curl_posts($url, $data = array())
     {
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
         curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
         // POST数据
         curl_setopt($ch, CURLOPT_POST, 1);
         // 把post的变量加上
         $header = array("content-type: application/json; charset=UTF-8");
         curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
         $output = curl_exec($ch);
         curl_close($ch);
         return $output;
     }
	
	
	
	public function Wepay($oid,$money,$exchange_rate)
	{
	    $url = "https://pyabxum.weglobalex.com/pay/web";
	            
        $pay["mch_id"] = 100789811;
        $pay["notify_url"] = 'https://gemvip.cc/api/Wepay';
        $pay["page_url"] = 'https://geminizxy.com/';
        $pay["version"] = "1.0";
        $pay["mch_order_no"] = $oid; 
        $pay["pay_type"] = 122; 
        $pay["trade_amount"] = $money * $exchange_rate;
         
        $pay["order_date"] = date("Y-m-d H:i:s");
        $pay["key"] = "c1a4a58ab6344377ba08cd18441f3e7e";
        
        $pay = $this->ASCII($pay);
        $pay["sign_type"] = "MD5"; 
         unset($pay["key"]);
         
       
        $ch = curl_init();    
        curl_setopt($ch,CURLOPT_URL,$url); //支付请求地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($pay));  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $response= curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response,true);
        return $response;
	}
	
	public function caolong($oid,$money,$exchange_rate)
	{  
	    $url = "https://pay.sunpayonline.xyz/pay/web";
	            
        $pay["mch_id"] = 688000250;
        $pay["notify_url"] = 'https://gemvip.cc/api/caolong';
        $pay["page_url"] = 'https://geminizxy.com/';
        $pay["version"] = "1.0";
        $pay["mch_order_no"] = $oid;
        $pay["pay_type"] = 620;
        $pay["trade_amount"] = $money * $exchange_rate;
        
        $pay["order_date"] = date("Y-m-d H:i:s");
        $pay["key"] = "8279e9adc02a48a8aa7a944d09012a2d";
        
        $pay = $this->ASCII($pay);
        $pay["sign_type"] = "MD5"; 
         unset($pay["key"]);
        
       
        $ch = curl_init();    
        curl_setopt($ch,CURLOPT_URL,$url); //支付请求地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($pay));  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $response= curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response,true);
        return $response;
	}
	
	/**
	 * KirinPay支付
	 * @parameter $oid 订单id
	 * @parameter $money 金额
	 * @parameter $exchange_rate 汇率
	 */ 
	public function KirinPay($oid,$money,$exchange_rate)
	{
	    $url = "http://api.kirinpay.vip/api/pay/order/payment/create";
	    $pay["mchId"] = "1734";
	    $pay["mchOrderNo"] = $oid;
	    $pay["productId"] = "27";
	    $pay["orderAmount"] = ($money * $exchange_rate) * 100;
	    $pay["notifyUrl"] = "https://india-work.company/api/KirinPay";
	    $pay["customerName"] = "test";
	    $pay["customerMobile"] = "919210011001";
	    $pay["customerEmail"] = "zhangs@gmail.com";
	    $pay["key"] = "4574fc7af0b34b3d94244f08025dacf7";
	    
	    $data = $this->ASCII($pay);
	    unset($data["key"]);
	    
	    $res = $this->curl_post1($url,$data);
	    return json_decode($res,true);
	}
	
	 function curl_post1($url, $post_data, $header = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }
    	/**
 * [ ASCII 编码 ]
 * @param array  编码数组 
 * @param string 签名键名   => sign
 * @param string 密钥键名   => key
 * @param bool   签名大小写 => false(大写)
 * @param string 签名是否包含密钥 => false(不包含)
 * @return array 编码好的数组
 */
public function ASCII($asciiData, $asciiSign = 'sign', $asciiKey = 'key', $asciiSize = false, $asciiKeyBool = false)
{
    //编码数组从小到大排序
    ksort($asciiData);
    //拼接源文->签名是否包含密钥->密钥最后拼接
    $MD5str = "";
    foreach ($asciiData as $key => $val) {
        if (!$asciiKeyBool && $asciiKey == $key) continue;
        $MD5str .= $key . "=" . $val . "&";
    }
    $sign = $MD5str . $asciiKey . "=" . $asciiData[$asciiKey];
	$sign = $sign;
	//echo $sign;
    //大小写->md5
    $asciiData[$asciiSign]  = $asciiSize ? strtoupper(md5($sign)) : strtolower(md5($sign));
    return $asciiData;
}
	

    public function hasLeverTrade($user_id)
    {
        $exist_close_trade = LeverTransaction::where('user_id', $user_id)
            ->whereNotIn('status', [LeverTransaction::CLOSED, LeverTransaction::CANCEL])
            ->count();
        return $exist_close_trade > 0 ? true : false;
    }


    private $fromArr = [
        'legal' => AccountLog::WALLET_LEGAL_OUT,
        'lever' => AccountLog::WALLET_LEVER_OUT,
        'micro' => AccountLog::WALLET_MCIRO_OUT,
        'change' => AccountLog::WALLET_CHANGE_OUT,
    ];
    private $toArr = [
        'legal' => AccountLog::WALLET_LEGAL_IN,
        'lever' => AccountLog::WALLET_LEVER_IN,
        'micro' => AccountLog::WALLET_MCIRO_IN,
        'change' => AccountLog::WALLET_CHANGE_IN,
    ];
    private $mome = [
        'legal' => 'c2c',
        'lever' => '合约',
        'micro' => '期权',
        'change' => '闪兑',
    ];

    public function changeWallet(Request $request)  //BY tian
    {
        $type = [
            'legal' => 1,
            'lever' => 3,
            'micro' => 4,
            'change' => 2,
        ];
        $user_id = Users::getUserId();
        $currency_id = Input::get("currency_id", '');
        $number = Input::get("number", '');

        $user = Users::find($user_id);
        if($user->frozen_funds==1){
            return $this->error('资金已冻结');
        }
        $from_field = $request->get('from_field', ""); 
        $to_field = $request->get('to_field', ""); 
        if (empty($from_field) || empty($number) || empty($to_field) || empty($currency_id)) {
            return $this->error('参数错误');
        }
        if ($number < 0) {
            return $this->error('输入的金额不能为负数');
        }
        $from_account_log_type = $this->fromArr[$from_field];
        $to_account_log_type =  $this->toArr[$to_field];
        $memo = $this->mome[$from_field] . '划转' . $this->mome[$to_field];
        if ($from_field == 'lever') {
            if ($this->hasLeverTrade($user_id)) {
                return $this->error('您有正在进行中的杆杠交易,不能进行此操作');
            }
        }
        try {
            DB::beginTransaction();
            $user_wallet = UsersWallet::where('user_id', $user_id)
                ->lockForUpdate()
                ->where('currency', $currency_id)
                ->first();
            if (!$user_wallet) {
                throw new \Exception('钱包不存在');
            }
            $result = change_wallet_balance($user_wallet, $type[$from_field], -$number, $from_account_log_type, $memo);
            if ($result !== true) {
                throw new \Exception($result);
            }
            $result = change_wallet_balance($user_wallet, $type[$to_field], $number, $to_account_log_type, $memo);
            if ($result !== true) {
                throw new \Exception($result);
            }
            DB::commit();
            return $this->success('划转成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('操作失败:' . $e->getMessage());
        }
    }

    public function hzhistory(Request $request)
    {
        $user_id = Users::getUserId();
        $limit = $request->get('limit', 10);

        $arr = [
            AccountLog::WALLET_LEGAL_OUT,
            AccountLog::WALLET_LEVER_OUT,
            AccountLog::WALLET_MCIRO_OUT,
            AccountLog::WALLET_CHANGE_OUT,
            AccountLog::WALLET_LEGAL_IN,
            AccountLog::WALLET_LEVER_IN,
            AccountLog::WALLET_MCIRO_IN,
            AccountLog::WALLET_CHANGE_IN,
        ];
        $result = AccountLog::where('user_id',$user_id)->whereIn('type', $arr)->orderBy('id', 'desc')->paginate($limit);
        return $this->success($result);
        
    }
    public function getCurrencyInfo()
    {
        $user_id = Users::getUserId();
        $currency_id = Input::get("currency", '');
        if (empty($currency_id)) return $this->error('参数错误');
        $currencyInfo = Currency::find($currency_id);
        if (empty($currencyInfo)) return $this->error('币种不存在');
        $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->first();
        $data = [
            'rate' => $currencyInfo->rate,
            'min_number' => $currencyInfo->min_number,
            'name' => $currencyInfo->name,
            'legal_balance' => $wallet->legal_balance,
            'change_balance' => $wallet->micro_balance,
        ];
        return $this->success($data);
    }

    public function getAddressByCurrency()
    {
        $user_id = Users::getUserId();
        $currency_id = Input::get("currency", '');
        if (empty($user_id) || empty($currency_id)) {
            return $this->error('参数错误');
        }
        $address = Address::where('user_id', $user_id)->where('currency', $currency_id)->get()->toArray();
        if (empty($address)) {
            return $this->error('您还没有添加提币地址');
        }
        return $this->success($address);
    }

    public function postWalletOut()
    {
        $user_id = Users::getUserId();
        $type = Input::get("type", '');
        $currency_id = Input::get("currency", '');
        $number = Input::get("number", '');
        $rate = Input::get("rate", '');
        $address = Input::get("address", '');
        $password = Input::get('pay_password');
        if (empty($currency_id) || empty($number) || ($type == 0 && empty($address))) {
            return $this->error('参数错误');
        }
        switch ($currency_id) {
        //BTC
        case '1':
            if (!(preg_match('/^(1|3)[a-zA-Z\d]{24,33}$/', $address) && preg_match('/^[^0OlI]{25,34}$/', $address))) {
                return $this->error('参数错误');
            }
            break;
        //ETH
        case '2':
            
            if (!(preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $address))) {
                 return $this->error('参数错误');
            }
            break;
        }
        if ($number < 0) {
            return $this->error('输入的金额不能为负数');
        }
        $user = Users::getById(Users::getUserId());
        if($user->frozen_funds == 1){
            return $this->error('资金已冻结');
        }
        
        if($user->score < 90){
              return $this->error('No puede retirar dinero porque su puntaje de crédito es inferior a 90');
        }
        
        $currencyInfo = Currency::find($currency_id);
        if ($number < $currencyInfo->min_number) {
            return $this->error('数量不能少于最小值');
        }
        try {
            DB::beginTransaction();
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->lockForUpdate()->first();
        
            if ($number > $wallet->micro_balance) {
                DB::rollBack();
                return $this->error('余额不足');
            }
            $walletOut = new UsersWalletOut();
            $walletOut->type=$type;
            $walletOut->user_id = $user_id;
            $walletOut->currency = $currency_id;
            $walletOut->number = $number;
            $walletOut->address = $address;
            $walletOut->rate = $rate;
            $walletOut->real_number = $number  - $rate;
            $walletOut->create_time = time();
            $walletOut->status = 1; 
            $walletOut->save();

            $result = change_wallet_balance($wallet, 4, -$number, AccountLog::WALLETOUT, '申请提币扣除余额');
            if ($result !== true) {
                throw new \Exception($result);
            }

            $result = change_wallet_balance($wallet, 4, $number, AccountLog::WALLETOUT, '申请提币锁定余额', true);
            if ($result !== true) {
                throw new \Exception($result);
            }
            DB::commit();
            return $this->success('提币申请已成功，等待审核');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
    }

    public function getWalletAddressIn()
    {
        $user_id = Users::getUserId();

        $currency_id = Input::get("currency", '');
        if (empty($user_id) || empty($currency_id)) {
            return $this->error('参数错误');
        }
        $currencyInfo = Currency::find($currency_id);
        if(!$currencyInfo){
        	 return $this->error('参数错误');
        }
        $legal = UsersWallet::where("user_id", $user_id)
            ->where("currency", $currency_id) //usdt
            ->first();
        if($currency_id==3){//usdt充值地址
            $address=[
                'erc20'=>$currencyInfo->address_erc ?? '', //erc20
                "trc20"=>$currencyInfo->address_omni ?? '' //trc20
                ];
        }else{
            $address=$currencyInfo->address_erc ?? '';
        }
        return $this->success($address);
    }
 
    public function getWalletDetail()
    {
        $user_id = Users::getUserId();
        $currency_id = Input::get("currency", '');
        $type = Input::get("type", '');
        if (empty($user_id) || empty($currency_id)) {
            return $this->error('参数错误');
        }
        $ExRate = Setting::getValueByKey('USDTRate', 6.5);
        if ($type == 'legal') {
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->first(['id', 'currency', 'legal_balance', 'lock_legal_balance','address']);
        } else if ($type == 'change') {
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->first(['id', 'currency', 'change_balance', 'lock_change_balance','address']);
            
        } else if ($type == 'lever') {
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->first(['id', 'currency', 'lever_balance', 'lock_lever_balance','address']);
        } else if ($type == 'micro') {
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency_id)->first(['id', 'currency', 'micro_balance', 'lock_micro_balance','address']);
        } else {
            return $this->error('类型错误');
        }
        if (empty($wallet)) return $this->error("钱包未找到");

        $wallet->ExRate = $ExRate;
  
        if(in_array($wallet->currency,[1,2,3])){
            $wallet->is_charge = true;
        }else{
            $wallet->is_charge = false;
        }
        
        if($wallet->id == 3){
            $wallet->usdt_price = 0.19803055;
        }

        $wallet->coin_trade_fee = Setting::getValueByKey('COIN_TRADE_FEE');
        return $this->success($wallet);
    }

    public function legalLog(Request $request)
    {
   
        $limit = $request->get('limit', 10);
        $account = $request->get('account', '');
        $currency = $request->get('currency', 0);
        $type= $request->get('type',0);
        $user_id = Users::getUserId();
        $list = new AccountLog();
        if (!empty($currency)) {
            $list = $list->where('currency', $currency);
        }
        if (!empty($user_id)) {
            $list = $list->where('user_id', $user_id);
        }
        if (!empty($type)) {
            // $list = $list->whereHas('walletLog',function($query) use($type){
            //   $query->where('balance_type',$type);
            // });
       }
        $list = $list->orderBy('id', 'desc')->paginate($limit);

        $is_open_CTbi = Setting::where("key", "=", "is_open_CTbi")->first()->value;

        return $this->success(array(
            "list" => $list->items(), 'count' => $list->total(),
            "limit" => $limit,
            "is_open_CTbi" => $is_open_CTbi
        ));
    }

    public function walletOutLog()
    {
        $id = Input::get("id", '');
        $walletOut = UsersWalletOut::find($id);
        return $this->success($walletOut);
    }



    public function getLtcKMB()
    {
        $address = Input::get('address', '');
        $money = Input::get('money', '');
        $wallet = UsersWallet::whereHas('currencyCoin', function ($query) {
            $query->where('name', 'PB');
        })->where('address', $address)->first();
        if (empty($wallet)) {
            return $this->error('钱包不存在');
        }
        DB::beginTransaction();
        try {

            $data_wallet1 = array(
                'balance_type' => 1,
                'wallet_id' => $wallet->id,
                'lock_type' => 0,
                'create_time' => time(),
                'before' => $wallet->change_balance,
                'change' => $money,
                'after' => $wallet->change_balance + $money,
            );
            $wallet->change_balance = $wallet->change_balance + $money;
            $wallet->save();
            AccountLog::insertLog([
                'user_id' => $wallet->user_id,
                'value' => $money,
                'currency' => $wallet->currency,
                'info' => '转账来自钱包的余额',
                'type' => AccountLog::LTC_IN,
            ], $data_wallet1);
            DB::commit();
            return $this->success('转账成功');
        } catch (\Exception $rex) {
            DB::rollBack();
            return $this->error($rex);
        }
    }
    public function sendLtcKMB()
    {
        $user_id = Users::getUserId();
        $account_number = Input::get('account_number', '');
        $money = Input::get('money', '');

        if (empty($account_number) || empty($money) || $money < 0) {
            return $this->error('参数错误');
        }
        $wallet = UsersWallet::whereHas('currencyCoin', function ($query) {
            $query->where('name', 'PB');
        })->where('user_id', $user_id)->first();
        if ($wallet->change_balance < $money) {
            return $this->error('余额不足');
        }

        DB::beginTransaction();
        try {

            $data_wallet1 = array(
                'balance_type' => 1,
                'wallet_id' => $wallet->id,
                'lock_type' => 0,
                'create_time' => time(),
                'before' => $wallet->change_balance,
                'change' => $money,
                'after' => $wallet->change_balance - $money,
            );
            $wallet->change_balance = $wallet->change_balance - $money;
            $wallet->save();
            AccountLog::insertLog([
                'user_id' => $wallet->user_id,
                'value' => $money,
                'currency' => $wallet->currency,
                'info' => '转账余额至钱包',
                'type' => AccountLog::LTC_SEND,
            ], $data_wallet1);

            $url = "http://walletapi.bcw.work/api/ltcGet?account_number=" . $account_number . "&money=" . $money;
            $data = RPC::apihttp($url);
            $data = @json_decode($data, true);
            //            var_dump($data);die;
            if ($data["type"] != 'ok') {
                DB::rollBack();
                return $this->error($data["message"]);
            }
            DB::commit();
            return $this->success('转账成功');
        } catch (\Exception $rex) {
            DB::rollBack();
            return $this->error($rex->getMessage());
        }
    }
    public function PB()
    {
        $user_id = Users::getUserId();
        $wallet = UsersWallet::whereHas('currencyCoin', function ($query) {
            $query->where('name', 'PB');
        })->where('user_id', $user_id)->first();
        return $this->success($wallet->change_balance);
    }
    public function flashAgainstList(Request $request)
    {
        $user_id = Users::getUserId();
        $left = Currency::where('is_match', 1)->get();
        foreach ($left as $k => $v) {
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $v->id)->first();
            if (empty($wallet)) {
                $balance = 0;
            } else {
                $balance = $wallet->change_balance;
            }
            $v->balance = $balance;
            $left[$k] = $v;
        }
        $right = Currency::where('is_micro', 1)->get();
        foreach ($right as $k => $v) {
            $wallet = UsersWallet::where('user_id', $user_id)->where('currency', $v->id)->first();
            if (empty($wallet)) {
                $balance = 0;
            } else {
                $balance = $wallet->change_balance;
            }
            $v->balance = $balance;
            $right[$k] = $v;
        }
        return $this->success(['left' => $left, 'right' => $right]);
    }

    public function flashAgainst(Request $request)
    {
        try {
            $l_currency_id = $request->get('l_currency_id', "");
            $r_currency_id = $request->get('r_currency_id', "");
            $num = $request->get('num', 0);

            $user_id = Users::getUserId();
            if ($num <= 0) return $this->error('数量不能小于等于0');
            $p = $request->get('price', 0);
            if ($p <= 0) return $this->error('价格不能小于等于0');

            if (empty($l_currency_id) || empty($r_currency_id))  return $this->error('参数错误哦');

            $left = Currency::where('id', $l_currency_id)->first();
            $right = Currency::where('id', $r_currency_id)->first();
            if (empty($left) || empty($right))  return $this->error('币种不存在');

            //$absolute_quantity = $p * $num / $right->price;
            $absolute_quantity = bc_div(bc_mul($p, $num), $right->price);
            DB::beginTransaction();

            $l_wallet = UsersWallet::where('currency', $l_currency_id)->where('user_id',$user_id)->lockForUpdate()->first();
            
            if (empty($l_wallet)){

                throw new \Exception('钱包不存在');
            }  

            if ($l_wallet->change_balance < $num){

                throw new \Exception('金额不足');
            } 

            $flash_against = new FlashAgainst();
            $flash_against->user_id = $user_id;
            $flash_against->price = $p;
            $flash_against->market_price = $left->price;
            $flash_against->num = $num;
            $flash_against->status = 0;
            $flash_against->left_currency_id = $l_currency_id;
            $flash_against->right_currency_id = $r_currency_id;
            $flash_against->create_time = time();
            $flash_against->absolute_quantity = $absolute_quantity; //实际数量
            $result = $flash_against->save();
            $result1=change_wallet_balance($l_wallet, 2, -$num, AccountLog::DEBIT_BALANCE_MINUS, '闪兑扣除余额');
            $result2=change_wallet_balance($l_wallet, 2, $num, AccountLog::DEBIT_BALANCE_ADD_LOCK, '闪兑增加锁定余额', true);
            if($result1 !== true){
                throw new \Exception($result1);
            }
            if ($result2 !== true) {
                throw new \Exception($result2);
            }

            DB::commit();
            return $this->success('兑换成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage() . '---' . $e->getLine());
        }
    }

    public function myFlashAgainstList(Request $request)
    {
        $limit = $request->get('limit', 10);
        $user_id = Users::getUserId();
        $list = FlashAgainst::orderBy('id', 'desc')->where('user_id', $user_id)->paginate($limit);
        return $this->success($list);
    }

    public function conversion(Request $request)
    {
        $form_currency_id = $request->get('form_currency', '');
        $to_currency_id = $request->get('to_currency', '');
        $balance_filed = 'legal_balance';
        $num = $request->get('num', '');
        if (empty($form_currency_id) || empty($to_currency_id) || empty($num)) {
            return $this->error('参数错误');
        }
        if($num <= 0){
            return $this->error('兑换数量必须大于0');
        }
        $user_id = Users::getUserId();       
        try {
            DB::beginTransaction();
            $form_wallet = UsersWallet::where('user_id', $user_id)->where('currency', $form_currency_id)->lockForUpdate()->first();
            $to_wallet = UsersWallet::where('user_id', $user_id)->where('currency', $to_currency_id)->lockForUpdate()->first();
            if(empty($form_wallet) || empty($to_wallet)){
                DB::rollBack();
                return $this->error('钱包不存咋');
            }
            if ($form_wallet->$balance_filed < $num) {
                DB::rollBack();
                return $this->error('余额不足');
            }
            if (strtoupper($form_wallet->currency_name) == 'USDT') {
                $fee = Setting::getValueByKey('currency_to_usdt_bmb_fee');
                $proportion = Setting::getValueByKey('currency_to_usdt_bmb');
            } elseif (strtoupper($form_wallet->currency_name) == UsersWallet::CURRENCY_DEFAULT) {
                $fee = Setting::getValueByKey('currency_to_bmb_usdt_fee');
                $proportion = Setting::getValueByKey('currency_to_bmb_usdt');
            }
            $totle_num_fee =bc_mul($num,$fee / 100);
            $totle_num = bc_sub($num,$totle_num_fee);
            $totle_num_sj = $proportion * $totle_num;


            $res1=change_wallet_balance($form_wallet, 1, -$totle_num, AccountLog::WALLET_USDT_MINUS, $form_wallet->currency_name . '兑换，' . $to_wallet->currency_name . ',减少' . $form_wallet->currency_name . $totle_num);

            $res2=change_wallet_balance($form_wallet, 1, -$totle_num_fee, AccountLog::WALLET_USDT_BMB_FEE,  $form_wallet->currency_name . '兑换，' . $to_wallet->currency_name . ',减少' . $form_wallet->currency_name . '手续费' . $totle_num_fee);

            $res3=change_wallet_balance($to_wallet, 1, $totle_num_sj, AccountLog::WALLET_BMB_ADD,     $form_wallet->currency_name . '兑换，' . $to_wallet->currency_name . ',增加' . $to_wallet->currency_name . $totle_num_sj);
            if($res1 !== true ){
                DB::rollBack();
                return $this->error($res1);
            }
            if($res2 !== true ){
                DB::rollBack();
                return $this->error($res2);
            }
            if($res3 !== true){
                DB::rollBack();
                return $this->error($res3);
            }

            $conversion = new Conversion();
            $conversion->user_id = $user_id;
            $conversion->create_time = time();
            $conversion->form_currency_id = $form_currency_id;
            $conversion->to_currency_id = $to_currency_id;
            $conversion->num = $num;
            $conversion->fee = $totle_num_fee;
            $conversion->sj_num = $totle_num_sj;
            $conversion->save();
            DB::commit();
            return $this->success('兑换成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }

    public function myConversion(Request $request)
    {
        $user_id = Users::getUserId();
        $limit = $request->get('limit', 10);
        $list = Conversion::orderBy('id', 'desc')->where('user_id', $user_id)->paginate($limit);
        return $this->success($list);
    }

    public function conversionList()
    {
        $currency = Currency::where('name', 'USDT')->orWhere('name', UsersWallet::CURRENCY_DEFAULT)->get();
        return $this->success($currency);
    }
    public function conversionSet()
    {
        $fee = Setting::getValueByKey('currency_to_usdt_bmb_fee');
        $proportion = Setting::getValueByKey('currency_to_usdt_bmb');
        $data['usdt_bmb_fee'] = $fee;
        $data['usdt_bmb_proportion'] = $proportion;
        $fee1 = Setting::getValueByKey('currency_to_bmb_usdt_fee');
        $proportion1 = Setting::getValueByKey('currency_to_bmb_usdt');
        $data['bmb_usdt_fee'] = $fee1;
        $data['bmb_usdt_proportion'] = $proportion1;
        $usdt = Currency::where('name', 'USDT')->first();
        $bmb = Currency::where('name', UsersWallet::CURRENCY_DEFAULT)->first();
        $user_id = Users::getUserId();
        $balance_filed = 'legal_balance';
        $usdt_wallet = UsersWallet::where('currency', $usdt->id)->where('user_id', $user_id)->first();
        $data['user_balance'] = $usdt_wallet->$balance_filed;
        $bmb_wallet = UsersWallet::where('currency', $bmb->id)->where('user_id', $user_id)->first();
        $data['bmb_balance'] = $bmb_wallet->$balance_filed;
        return $this->success($data);
    }

    //持险生币
    public function Insurancemoney()
    {     

        $user_id = Users::getUserId();
        $wallet = UsersWallet::where('lock_insurance_balance', '>', 0)->where('user_id', $user_id)->first();
        $data = [];

        $data['insurance_balance'] = $wallet->insurance_balance ?? 0;

        $data['lock_insurance_balance'] = $wallet->lock_insurance_balance ?? 0;
        //累计生币
        $data['sum_balance'] = AccountLog::where('user_id', $user_id)->where('type', AccountLog::INSURANCE_MONEY)->sum('value');
        //可用数量
        $data['usabled_balance'] = 0;

        return $this->success($data);
    }

    //持险生币日志
    public function Insurancemoneylogs()
    {

        $user_id = Users::getUserId();
        $limit = Input::get('limit', 10);

        $result = AccountLog::where('user_id', $user_id)->where('type', AccountLog::INSURANCE_MONEY)->orderBy('id', 'desc')->paginate($limit);

        return $this->success($result);
    }
}
