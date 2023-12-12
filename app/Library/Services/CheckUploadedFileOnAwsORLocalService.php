<?php
namespace App\Library\Services;
use Auth;
use App\models\SystemConfig;
  
class CheckUploadedFileOnAwsORLocalService
{
    public function checkUploadedFileOnAwsORLocal()
    {
    	
    	if(Auth::guard('admin')->user()){
    		$auth_site_id=Auth::guard('admin')->user()->site_id;
        $get_file_aws_local_flag = SystemConfig::select('file_aws_local')->where('site_id',$auth_site_id)->first();
    	}else{
    		$get_file_aws_local_flag=(object)array('file_aws_local'=>0);
    	}	
      return $get_file_aws_local_flag;
    }
}