<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 28/12/2019
 *   Use   : Check specific User route permission
 *
**/
namespace App\Helpers;
use Log,Auth;

class RolePermissionCheck
{
	public static function isPermitted($routeName)
	{ 
        $permitted = 0;
        
        $user_id = auth('admin')->user()->id;
        $user_role_count = auth('admin')->user()->with(['user_permissions'=>function($query) use ($routeName){
                                $query->where('user_permissions.route_name',$routeName);
                            }])
                            ->where('id',$user_id)
                            ->first()->toArray();
        
        if(head($user_role_count['user_permissions'])) {
            $permitted = 1;
        }
        return $permitted;
    }
}