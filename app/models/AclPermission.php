<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class AclPermission extends Model
{
    protected $table = 'acl_permissions';

    protected $fillable = ['route_name','method_name','module','route_method','action_name','main_module','sub_module','description','order'];

    //Get The all permission
    public static function getPermission(){
        $routes_not_to_show_array = ['horizon','passport','show','demo','demo1','canvasmaker','excel','preview','templateMaster','idcards_validation','pgconfig_fetch_dropdown_value','user-master','scanningHistory','sand-box','template-map','raisoniMaster','degreeCertificate'];
        $user_prems_temp = self::select('*')->whereNotIn('main_module',$routes_not_to_show_array)->orderBy('order','asc')->get()->toArray();
        $user_prems = [];

        foreach ($user_prems_temp as $key => $value) {
            $user_prems[$value['main_module']."_".$value['sub_module']][$value['id']] = $value['description_alias'];
        }
        return $user_prems;
    }



    public static function getPermissions(){
        $routes_not_to_show_array = ['horizon','passport','show','demo','demo1','canvasmaker','excel','preview','templateMaster','idcards_validation','pgconfig_fetch_dropdown_value','user-master','scanningHistory','sand-box','template-map','raisoniMaster','degreeCertificate','verify'];
        $user_prems_temp = self::select('*')->whereNotIn('main_module',$routes_not_to_show_array)->orderBy('order','asc')->get()->toArray();
        $user_prems = [];

        foreach ($user_prems_temp as $key => $value) {
            $user_prems[$value['main_module']."_".$value['sub_module']][$value['id']] = $value['description_alias'];
        }
        return $user_prems;
    }
}
