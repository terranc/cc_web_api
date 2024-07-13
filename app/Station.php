<?php
/**
 * create by vscode
 * @author lion
 */
namespace App;


use Illuminate\Database\Eloquent\Model;

class Station extends ShopModel
{
    protected $table = 'station_message';
    //自动时间戳
    // protected $dateFormat = 'U';
    // const CREATED_AT = 'create_time';
    
    public $timestamps = false;

    public function getReadtimeAttribute()
    {
        $value = $this->attributes['readtime'];
        return $value ? date('Y-m-d H:i:s', $value ) : '';
    }
    
        public function getCreateTimeAttribute()
    {
        $value = $this->attributes['create_time'];
        return $value ? date('Y-m-d H:i:s', $value ) : '';
    }
  
}
