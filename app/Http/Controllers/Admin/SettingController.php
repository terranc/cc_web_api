<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Setting;
use App\Payment;

class SettingController extends Controller
{
    public function index()
    {
        $settingList = Setting::all()->toArray();
        $setting = [];
        foreach ($settingList as $key => $value) {
            $setting[$value['key']] = $value['value'];
        }
        // var_dump($setting);
        return view('admin.setting.base', ['setting' => $setting]);
    }

    public function index_second(){
        $settingList = Setting::all()->toArray();
        $setting = [];
        foreach ($settingList as $key => $value) {
            $setting[$value['key']] = $value['value'];
        }
        // var_dump($setting);
        return view('admin.setting.index_second', ['setting' => $setting]);
    }
    
    public function dataSetting()
    {
        $settingList = Setting::all()->toArray();
        $setting = [];
        foreach ($settingList as $key => $value) {
            $setting[$value['key']] = $value['value'];
        }
        return view('admin.setting.data', ['setting' => $setting]);
    }

    public function postAdd(Request $request)
    {
        $data = $request->all();
        $generation = $request->input('generation');
        $reward_ratio = $request->input('reward_ratio');
        $need_has_trades = $request->input('need_has_trades');
        unset($data['generation'], $data['reward_ratio'], $data['need_has_trades']);
        $lever_fee_options = compact('generation', 'reward_ratio', 'need_has_trades');
        $lever_fee_options = make_multi_array(['generation', 'reward_ratio', 'need_has_trades'], count($generation), $lever_fee_options);

        $generation = array_column($lever_fee_options, 'generation');
        $reward_ratio = array_column($lever_fee_options, 'reward_ratio');
        array_multisort($generation, SORT_ASC, SORT_NUMERIC, $lever_fee_options);

        $data['lever_fee_options'] = serialize($lever_fee_options);
        try {
            foreach ($data as $key => $value) {
                $setting = Setting::where('key', $key)->first();

                if (!$setting) {
                    $setting = new Setting();
                    $setting->key = $key;
                }

                $setting->value = $value;
                $setting->save();
            }
            return $this->success('操作成功');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }
    
    
    /**
     * 支付通道配置
     */ 
    public function paymentList()
    {
         return view('admin.setting.paymentList');
    }
    /**
     * 通道数据
     */ 
     public function tdaoshu(Request $request)
     {
          $limit = $request->get('limit', 10);
        $result = new Payment();
        $result = $result->orderBy('sort', 'desc')->paginate($limit);
        return $this->layuiData($result);
     }
    
    
    //编辑通道
    public function paymentadd(Request $request)
    {
        $result = new Payment();
         $id = $request->get('id', 0);
        $result = Payment::find($id);

        return view('admin.setting.paymentadd')->with('result', $result);
    }
    
    
    public function paymentadds(Request $request)
    {
         $id = $request->get('id', 0);
       
        $result = new Payment();
       
       $result = Payment::find($id);
        if ($result == null) {
            return redirect()->back();
        }
            
        if($request->get('name', '')){
            $result->name = $request->get('name', '');
        }
        
        if($request->get('image', '')){
            $result->image = $request->get('image', '');
        }
        
        if($request->get('address', '')){
            $result->address = $request->get('address', '');
        }
        
        if($request->get('exchange_rate', '')){
            $result->exchange_rate = $request->get('exchange_rate', '');
        }
        if($request->get('sort', '')){
            $result->sort = $request->get('sort', '');
        }
        
        if($request->get('root', '')){
            $result->root = $request->get('root', '');
        }
        
       
      

      
        try {
            $result->save(); //保存币种
         
            return $this->success('操作成功');
        } catch (\Exception $exception) {
           
            return $this->error($exception->getMessage());
        }
    }
    
    
    /**
     * 通道状态编辑
     */ 
    
     public function paymentstatus(Request $request)
    {
        $id = $request->get('id', 0);
        $result = Payment::find($id);
        if (empty($result)) {
            return $this->error('参数错误');
        }
        if ($result->status == 1) {
            $result->status = 0;
        } else {
            $result->status = 1;
        }
        try {
            $result->save();
            return $this->success('操作成功');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }
    
    
    
    public function dogeneralaccount(Request $request)
    {
        $data = $request->all();
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'contract_address':
                    break;
                case 'total_account_address':
                    break;
                case 'total_account_key':
                    break;
            }
            Setting::updateValueByKey($key, $value);
        }
        return $this->success('操作成功');
    }
}
