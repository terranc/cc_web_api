<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use App\News as NewsModel;
use App\Station as StationModel;
use App\NewsCategory;
use App\NewsDiscuss;
use Illuminate\Support\Facades\DB;
use Validator;
use App\Users;
use App\Agent;

class StationController extends Controller
{
    use ValidatesRequests;


    
    
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
    
    public static function newsList($num = 10,$type = 1)
    {
        
        $news_query = StationModel::orderBy('id', 'desc');
        
        if($type != 1){
      
          $news_query = $news_query->whereIn('uid', $type);
        }
        
        $news = $num != 0 ? $news_query->paginate($num) : $news_query->get();
        
        foreach ($news as $v) {
            $user = Users::find($v->uid); // 查询关联的用户信息
            if ($user) {
                $v->username = $user->account_number; // 添加用户名到每条数据中
            } else {
                $v->username = 'Unknown'; // 如果找不到用户，设置默认值
            }
        }
        
        return $news;
    }

    public function index(Request $request, $c_id = 0, $keyword = '')
    {
        
        $type = $request->input('type',1);
        if($type == 2){
         
              $user_id = Db::table("agent")->where("id",Agent::getAgentId())->value("user_id");
            $type =  $this->getXiajiId($user_id);
            
        }
        $news = self::newsList(10,$type);
        
        
        $count = count($news);
        
     
        
        $data = [
            'count'=> $count,
            'news' => $news
        ];
        return view('admin.station.index', [
            'data'=> $data,
        ]);
    }
    
        /**
     * 后台添加新闻表单
     *
     * @return
     */

    public function add(Request $request)
    {
        $Users = Users::all();
           
        return view('admin.station.add', [
        'user'=> $Users,
        ]);
    }

    /**
     * 处理添加新闻表单数据
     *
     * @return
     */

    public function postAdd(Request $request)
    {
        $news = new StationModel();
        
        $news->title = $request->input('title');
      
        $news->create_time = $request->input('create_time') == date('Y-m-d') ? strtotime(date('Y-m-d H:i:s')) : strtotime($request->input('create_time'));
       
        $news->content = $request->input('content', '') ?? '';
        
        $zhh = $request->input('uid', '');
        
        $uid = Db::table("users")->where("account_number",$zhh)->value("id");
       
        if(!$uid){
            return $this->error('账号不存在');
        }
     
        $news->uid = $uid;
        
        
        
        
        //$list = new Users();
        // $account = $news->uid;
        // $list = $list->where("phone", 'like', '%' . $account . '%')
        //         ->orwhere('email', 'like', '%' . $account . '%')
        //         ->orwhere('users.id', 'like', '%' . $account . '%')
        //         ->orWhere('account_number', 'like', '%' . $account . '%')->first();
                
       // $news->uid = $uid;
      
        $result = $news->save();
        return $result ? $this->success('添加成功!') : $this->error('添加失败!');
    }

    /**
     * 编辑新闻表单
     *
     * @return
     */

    public function edit(Request $request, $id = 0)
    {
        $news = StationModel::find($id);
         $Users = Users::all();
        $data = [
            'news' => $news,
            'user' => $Users,
        ];
        return view('admin.station.add', $data);
    }


  public function postEdit(Request $request, $id = 0)
    {
        $news = StationModel::find($id);
    
        $news->title = $request->input('title');
      
        $news->create_time = $request->input('create_time') == date('Y-m-d') ? strtotime(date('Y-m-d H:i:s')) : strtotime($request->input('create_time'));
       
        $news->content = $request->input('content', '') ?? '';
        
        $news->uid = $request->input('uid', '') ?? '';
        
        $result = $news->save();
        return $result ? $this->success('编辑成功！') : $this->error('编辑失败！');
    }
    
      public function del(Request $request, $id = 0)
    {
        // $result = NewsModel::destroy($id);
        
        
        // return $result ? $this->success('删除成功！') : $this->error('删除失败！');
        
        $record = StationModel::find($id);
        
       
        if ($record) {
            $record->delete();
            return $this->success('删除成功！');
        } else {
           return $this->error('删除失败！');
        }
                
    }

}
