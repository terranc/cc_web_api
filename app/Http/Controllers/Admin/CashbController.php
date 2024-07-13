<?php

/**
 * 提币控制器
 */
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use App\UsersWalletOut;
use App\UsersWallet;
use App\AccountLog;
use App\Currency;
use App\Setting;
use App\Users;
use App\Utils\RPC;
use App\DAO\BlockChain;
use App\UserCashInfo;
use App\UserCashInfoInternational;

class CashbController extends Controller
{
    public function index()
    {
        return view('admin.cashb.index');
    }

    public function cashbList(Request $request)
    {
        $limit = $request->get('limit', 20);
        $account_number = $request->input('account_number', '');
        $userWalletOut = new UsersWalletOut();
        $userWalletOutList = $userWalletOut->whereHas('user', function ($query) use ($account_number) {
            if ($account_number != '') {
                $query->where('phone', $account_number)
                    ->orWhere('account_number', $account_number)
                    ->orWhere('email', $account_number)
                    ->orWhere('id', $account_number);
            }
        })->orderBy('id', 'desc')->paginate($limit);
      
        return $this->layuiData($userWalletOutList);
    }

    public function show(Request $request)
    {
        $id = $request->get('id', '');
        if (!$id) {
            return $this->error('参数小错误');
        }
        $walletout = UsersWalletOut::find($id);
        $card_info = null;
        $card_info_data = [];
        if ($walletout->type == 1) { // 提现到银行卡
            $card_info = UserCashInfo::where('user_id', $walletout->user_id)->first();
        } else if ($walletout->type == 2) { // 提现到国际卡
            // 获取表单设置
            $forms = Setting::getValueByKey("form_international");
            $list = explode("\n", $forms);
            $lang = session('lang', 'en');
            $langs = ['en', 'zh', 'hk', 'fra', 'jp', 'kor', 'spa', 'th'];
            $new_forms = [];

            $card_info = UserCashInfoInternational::where('user_id', $walletout->user_id)->first();
            $data = explode("\n", $card_info->data);
            $card_info_data = [];
            foreach ($list as $i => $item) {
                $_item = explode('|', $item);  // 多语言用|分隔开，分别是en|zh|hk|fra|jp|kor|spa|th
                $_values = [];
                foreach ($langs as $_k => $_lang) {
                    try {
                        $_values[$_lang] = $_item[$_k];
                    } catch (\Exception $e) {
                        $_values[$_lang] = $_item[0];
                    }
                }
                $new_forms[] = $_values[$lang];
                try{
                    $card_info_data[$_values[$lang]] = $data[$i];
                } catch (\Exception $e) {
                    $card_info_data[$_values[$lang]] = '';
                }
            }
        }
        $use_chain_api = Setting::getValueByKey('use_chain_api', 0);
        
        $dailis = $request->get("dailis",1);
        return view('admin.cashb.edit', ['wallet_out' => $walletout,'use_chain_api' => $use_chain_api, 'card_info'=>$card_info, 'card_info_data' => $card_info_data,'dailis'=>$dailis]);
        
    }

    //test
    public function done(Request $request)
    {
        set_time_limit(0);
        $id = $request->get('id', '');
        $method = $request->get('method', '');
        $notes = $request->get('notes', '');
        $verificationcode = $request->input('verificationcode', '');
        $txid =  $request->input('txid', '');
        if (!$id) {
            return $this->error('参数错误');
        }
       
        try {
            DB::beginTransaction();
            $wallet_out = UsersWalletOut::where('status', '<=', 1)->lockForUpdate()->findOrFail($id);
            $number = $wallet_out->number;
            $real_number = bc_mul($wallet_out->number, bc_sub(1, bc_div($wallet_out->rate, 100)));
            $user_id = $wallet_out->user_id;
            $currency = $wallet_out->currency;
            $currency_type = $wallet_out->currency_type;
            
            $currency_model = Currency::find($currency);
            $contract_address = $currency_model->contract_address;
            $total_account = $currency_model->total_account;


            $user_wallet = UsersWallet::where('user_id', $user_id)->where('currency', $currency)->lockForUpdate()->first();

            if ($method == 'done') {//确认提币
                // $key = $currency_model->origin_key;
                // //以太坊确认提币后。返回成功执行后台操作ldh
                // $chain_address = $wallet_out->address;
                // if (empty($total_account) || empty($key)) {
                //     throw new \Exception('请检查您的币种设置:(');
                // }
                // if (!in_array($currency_type, ['eth', 'erc20', 'usdt', 'btc'])) {
                //     throw new \Exception('币种类型暂不支持:(');
                // }
                // if ($currency_type == 'erc20') {
                //     if (empty($contract_address)) {
                //         throw new \Exception('币种设置缺少合约地址:(');
                //     }                   
                // }
                // $use_chain_api = Setting::getValueByKey('use_chain_api', 0);
                // if ($use_chain_api == 1) {
                //     //调用链上接口
                //     if ($verificationcode == '') {
                //         throw new Exception('请填写验证码');
                //     }
                //     $params =  [
                //         'currency_name' => $currency_model->name,
                //         'chain_address' => $chain_address,
                //         'real_number' => $wallet_out->real_number,
                //         'total_account' => $total_account,
                //         'key' => $key
                //     ];
                //     $query_str = md5(http_build_query($params));
                //     if (Cache::has($query_str)) {
                //         throw new \Exception('请勿重复操作,以免给您带来损失,建议用区块链浏览器查询该提币地址的交易记录');
                //     }
                //     Cache::put($query_str, 1, 5);
                //     $transfer_result = BlockChain::transfer($currency_model->name, $currency_model->type, $chain_address, $wallet_out->real_number, $total_account, $key, 3, 0, $verificationcode);
                //     if (isset($transfer_result['code']) && $transfer_result['code'] == 0) {
                //         //链上请求成功
                //         $wallet_out->txid = $transfer_result['txid']; //写入信息
                //     } else {
                //         throw new \Exception('链上请求出错:(' . ' 错误信息:' . $transfer_result);
                //     }
                // }else{
                //     if ($txid == '') {
                //         throw new Exception('当前提币没有使用接口,请填写交易哈希以便于用户查询');
                //     }
                //     $wallet_out->txid = $txid;

                // }
                $wallet_out->status = 2;//提币成功状态
                $wallet_out->notes = $notes;//反馈的信息
                $wallet_out->verificationcode = $verificationcode;
                $wallet_out->update_time = time();
                $wallet_out->save();
                $change_result = change_wallet_balance($user_wallet, 4, -$number, AccountLog::WALLETOUTDONE, '提币成功', true);
                if ($change_result !== true) {
                    throw new Exception($change_result);
                }
            }else if($method == 'caolong'){
                
                
                    $oid = "TX".time().rand(10000,99999);
            
                    $card_info = UserCashInfo::where('user_id', $user_id)->first();
                   
                    
                    $payments = Db::table("payments")->where("key","cgbh_gbkyd")->first();
                    
                   
                     $url = "https://pay.sunpayonline.xyz/pay/transfer";
	            
                    $pay["mch_id"] = 688000250;
                    $pay["mch_transferId"] = $oid;
                    $pay["transfer_amount"] = round($number,2);
                    $pay["apply_date"] = date("Y-m-d H:i:s");
                    $pay["bank_code"] = "PIXPAY";
                    $pay["receive_name"] = $card_info->bank_name;
                    $pay["receive_account"] = $card_info->bank_account;
                    $pay["document_id"] = $card_info->bank_dizhi;
                    $pay["document_type"] = $card_info->alipay_account;
                    $pay["back_url"] = 'https://gemvip.cc/api/caolongs';
                 
                    $pay["key"] = "ULIAKJ3RAQX0YLXNERTO0R7I32PC3FV9";
                    
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
                    $result = json_decode($response,true);
                  
                
                     if ($result["respCode"] == "SUCCESS") {
                     		//提交成功，
                     		
                     		$wallet_out->oid = $oid;
                            $wallet_out->status = 4;//提币成功状态
                            $wallet_out->notes = $notes;//反馈的信息
                            $wallet_out->verificationcode = $verificationcode;
                            $wallet_out->update_time = time();
                            $wallet_out->save();
                            // $change_result = change_wallet_balance($user_wallet, 4, -$number, AccountLog::WALLETOUTDONE, '提币成功', true);
                            // if ($change_result !== true) {
                                // throw new Exception($change_result);
                            // }
                     	
                     }else{
                           return $this->error($result["errorMsg"]);
                     }
            }else if($method == 'E253'){
                
                
                    $oid = "TX".time().rand(10000,99999);
            
                    $card_info = UserCashInfo::where('user_id', $user_id)->first();
                   
                    
                    $payments = Db::table("payments")->where("key","cgbh_gbkyd")->first();
                    
                
                     $url = "https://fnjk68dsb0ods-notice.mansidon.com/proxypay/order";
	            
                    $pay["mch_account"] = "huixiong";
                    $pay["order_no"] = $oid;
                    $pay["amount"] = round($number*10000,2);
                    $pay["account_type"] = '0';
                    $pay["account_no"] = $card_info->bank_account;
                    $pay["account_name"] = $card_info->real_name;
                    $pay["bank_code"] = $card_info->alipay_account;
                    
                    $pay["bank_province"] = "bank_province";
                    $pay["bank_city"] = "bank_city";
                    
                    $pay["bank_name"] = "bank_name";
                    
                    $pay["call_back_url"] = 'https://hdwqj7.com/api/traE253';
                    $pay["pay_type"] = "1";
                    $pay["key"] = "umw1EydR7uFK8Dvv";
                    
                    $pay = $this->ASCII($pay);
                   
                     unset($pay["key"]);
                 //   dump($pay);
                    $response = $this->curl_posts($url,$pay);
                    $result = json_decode($response,true);
                  
                
                     if ($result["ret"] == "200") {
                     		//提交成功，
                     		
                     		$wallet_out->oid = $oid;
                            $wallet_out->status = 4;//提币成功状态
                            $wallet_out->notes = $notes;//反馈的信息
                            $wallet_out->verificationcode = $verificationcode;
                            $wallet_out->update_time = time();
                            $wallet_out->save();
                            // $change_result = change_wallet_balance($user_wallet, 4, -$number, AccountLog::WALLETOUTDONE, '提币成功', true);
                            // if ($change_result !== true) {
                                // throw new Exception($change_result);
                            // }
                     	
                     }else{
                           return $this->error($result["msg"]);
                     }
            }else if($method == 'Ppay'){
                
                
                    $oid = "TX".time().rand(10000,99999);
            
                    $card_info = UserCashInfo::where('user_id', $user_id)->first();
                   
                    
                    $payments = Db::table("payments")->where("key","cgbh_gbkyd")->first();
                    
                
                     $url = "https://withdraw.ppayglobal.com/withdraw/createOrder";
	            
                //    $url = "https://ord.ppayglobal.com/pay/order";
            	    $pay["merNo"] = "777986007";
            	    $pay["merchantOrderNo"] = $oid;
            	    $pay["amount"] = sprintf("%01.2f",$number);
            	//    $pay["payCode"] = '623';
            	     
            	    $pay["bankCode"] = "BR000";
            	    $pay["customerName"] = $card_info->real_name;
            	    $pay["customerAccount"] = $card_info->bank_account;
            	    $pay["accth"] = $card_info->bank_dizhi;
            	   
            	    
            	    $pay["notifyUrl"] = "https://bitpietrx.com/api/ppay1";
            	    
            	    $pay["currency"] = 'BRL';
            
            	    $pay["sign"] = $this->md5Sign($pay,"64092aa326ee478282d4b2b9ed069fdc");
            	  
                     
                   
                    $result = json_decode($this->httpPost($url,$pay),true);
                  
                
                     if ($result["code"] == "SUCCESS") {
                     		//提交成功，
                     		
                     		$wallet_out->oid = $oid;
                            $wallet_out->status = 4;//提币成功状态
                            $wallet_out->notes = $notes;//反馈的信息
                            $wallet_out->verificationcode = $verificationcode;
                            $wallet_out->update_time = time();
                            $wallet_out->save();
                            // $change_result = change_wallet_balance($user_wallet, 4, -$number, AccountLog::WALLETOUTDONE, '提币成功', true);
                            // if ($change_result !== true) {
                                // throw new Exception($change_result);
                            // }
                     	
                     }else{
                           return $this->error($result["msg"]);
                     }
            }else if($method == 'tyPay'){
                
                
                    $oid = "TX".time().rand(10000,99999);
            
                    $card_info = UserCashInfo::where('user_id', $user_id)->first();
                   
                    
                    $payments = Db::table("payments")->where("key","typay")->first();
                    
                
                    $url = "http://so0913.cn/newbankPay/crtAgencyOrder.do";
            	    $pay["appId"] = $payments->root;
            	    $pay["appOrderNo"] = $oid;
            	    $pay["orderAmt"] = sprintf("%01.2f",round($number ,2));
            	    $pay["payId"] = 1201;
            	    $pay["accNo"] = $card_info->bank_account;
            	    $pay["accName"] = $card_info->real_name;
            	    $pay["bankName"] = $card_info->bank_name;
            	    $pay["key"] = $payments->address;
            	    
            	    
            	    
            	    $pay = $this->ASCII($pay,"sign","key",true);
                  
                     unset($pay["key"]);
                    $pay["notifyURL"] = "https://v77pro.com/api/typay2";
            	    $pay["docNumber"] = $card_info->bank_dizhi;
            	    
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
                    $result = json_decode($response,true);
                  
                
                     if ($result["code"] == "00000") {
                     		//提交成功，
                     		
                     		$wallet_out->oid = $oid;
                            $wallet_out->status = 4;//提币成功状态
                            $wallet_out->notes = $notes;//反馈的信息
                            $wallet_out->verificationcode = $verificationcode;
                            $wallet_out->update_time = time();
                            $wallet_out->save();
                            // $change_result = change_wallet_balance($user_wallet, 4, -$number, AccountLog::WALLETOUTDONE, '提币成功', true);
                            // if ($change_result !== true) {
                                // throw new Exception($change_result);
                            // }
                     	
                     }else{
                           return $this->error($result["msg"]);
                     }
            } else {
                $wallet_out->status = 3;//提币失败状态
                $wallet_out->notes = $notes;//反馈的信息
                $wallet_out->verificationcode = $verificationcode;
                $wallet_out->update_time = time();
                
                $wallet_out->save();
                $change_result = change_wallet_balance($user_wallet, 4, -$number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额减少', true);
                if ($change_result !== true) {
                    throw new Exception($change_result);
                }
                $change_result = change_wallet_balance($user_wallet, 4, $number, AccountLog::WALLETOUTBACK, '提币失败,锁定余额撤回');
                if ($change_result !== true) {
                    throw new Exception($change_result);
                }
            }
            DB::commit();
            return $this->success('操作成功:)');
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->error($ex->getMessage());
        }
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
    
    
    	/**
 * [ ASCII 编码 ]
 * @param array  编码数组 
 * @param string 签名键名   => sign
 * @param string 密钥键名   => key
 * @param bool   签名大小写 => false(大写)
 * @param string 签名是否包含密钥 => false(不包含)
 * @return array 编码好的数组
 */
public function ASCII($asciiData, $asciiSign = 'sign', $asciiKey = 'key', $asciiSize = true, $asciiKeyBool = false)
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
    
    //导出用户列表至excel
    public function csv()
    {
        $data = USersWalletOut::all()->toArray();
        return Excel::create('提币记录', function ($excel) use ($data) {
            $excel->sheet('提币记录', function ($sheet) use ($data) {
                $sheet->cell('A1', function ($cell) {
                    $cell->setValue('ID');
                });
                $sheet->cell('B1', function ($cell) {
                    $cell->setValue('账户名');
                });
                $sheet->cell('C1', function ($cell) {
                    $cell->setValue('虚拟币');
                });
                $sheet->cell('D1', function ($cell) {
                    $cell->setValue('提币数量');
                });
                $sheet->cell('E1', function ($cell) {
                    $cell->setValue('手续费');
                });
                $sheet->cell('F1', function ($cell) {
                    $cell->setValue('实际提币');
                });
                $sheet->cell('G1', function ($cell) {
                    $cell->setValue('提币地址');
                });
                $sheet->cell('H1', function ($cell) {
                    $cell->setValue('反馈信息');
                });
                $sheet->cell('I1', function ($cell) {
                    $cell->setValue('状态');
                });
                $sheet->cell('J1', function ($cell) {
                    $cell->setValue('提币时间');
                });
                if (!empty($data)) {
                    foreach ($data as $key => $value) {
                        $i = $key + 2;
                        if ($value['status'] == 1) {
                            $value['status'] = '申请提币';
                        } else if ($value['status'] == 2) {
                            $value['status'] = '提币成功';
                        } else {
                            $value['status'] = '提币失败';
                        }
                        $sheet->cell('A' . $i, $value['id']);
                        $sheet->cell('B' . $i, $value['account_number']);
                        $sheet->cell('C' . $i, $value['currency_name']);
                        $sheet->cell('D' . $i, $value['number']);
                        $sheet->cell('E' . $i, $value['rate']);
                        $sheet->cell('F' . $i, $value['real_number']);
                        $sheet->cell('G' . $i, $value['address']);
                        $sheet->cell('H' . $i, $value['notes']);
                        $sheet->cell('I' . $i, $value['status']);
                        $sheet->cell('I' . $i, $value['create_time']);
                    }
                }
            });
        })->download('xlsx');
    }
}
