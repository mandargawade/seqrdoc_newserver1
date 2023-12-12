<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 28/12/2019
 *   Use   : Check specific User route permission
 *
**/
namespace App\Helpers;
use App\Models\Site;
use App\Models\SitePermission;
use Log,Auth;

class SitePermissionCheck
{
	public static function GetSiteId($site_url)
	{ 
       $site_null_id=null;  
       $site_id=Site::select('site_id')->where('site_url',$site_url)->first();
       
       
    //  print_r($site_id);
       if(!empty($site_id['site_id']))
       {
          return $site_id['site_id'];
       } 
       else
       {
         return $site_null_id;
         //return 5;
       }
    }
   public static function isPermitted($routeName)
   { 


        $permitted = 0;
        $site_id = auth('admin')->user()->site_id;
        $site_permission_count = SitePermission::where('site_id',$site_id)
                                         ->where('route_name',$routeName)
                                         ->first();

         if($site_id==201&&$site_permission_count>1){
        echo $site_id['site_id'];
        exit;
       }
                                                  
        if(!empty($site_permission_count)) {
            $permitted = 1;
        }
        return $permitted;
    }
}