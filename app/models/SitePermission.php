<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Auth;
class SitePermission extends Model
{
    public $table="site_permissions";
    public $fillable=['site_id','permission_id','route_name','status','main_module','sub_module','description'];

        //Get The all permission
    public static function getPermission(){
        $site_id=Auth::guard('admin')->user()->site_id;
        $routes_not_to_show_array = ['horizon','passport','show','demo','demo1','canvasmaker','excel','preview','templateMaster','idcards_validation','pgconfig_fetch_dropdown_value','user-master','scanningHistory','sand-box','template-map','raisoniMaster','degreeCertificate'];
        $user_prems_temp = self::select('*')->leftjoin('acl_permissions','site_permissions.permission_id','=','acl_permissions.id')->whereNotIn('site_permissions.main_module',$routes_not_to_show_array)->where('site_permissions.site_id',$site_id)->orderBy('acl_permissions.order','asc')->get()->toArray();
        
        $user_prems = [];

        foreach ($user_prems_temp as $key => $value) {
            $user_prems[$value['main_module']."_".$value['sub_module']][$value['permission_id']] = $value['description_alias'];
        }
        return $user_prems;
    }
}
