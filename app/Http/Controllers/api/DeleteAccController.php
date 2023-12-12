<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\User;
use App\models\SessionManager;
use App\models\Site;
use App\models\SystemConfig;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Hash,DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use App\models\ApiTracker;

class DeleteAccController extends Controller
{
    use AuthenticatesUsers;

	public function deleteUserAccount(Request $request)
	{
        $data = $request->post();
        $start_time = date('Y-m-d H:i:s');
        
	   if(ApiSecurityLayer::checkAuthorization())
        {
            
            $rules = [
                'username' => 'required',
                'password' => 'required'
            ];

            $messages = [
                'username.required' => 'User name required',
                'password.required' => 'Password required',
            ];

            $validator = \Validator::make($request->post(),$rules,$messages);

            if ($validator->fails()) {
                   $message = array('success' => false,'status'=>400, 'message' => ApiSecurityLayer::getMessage($validator->errors()));
             
            }else{

            $credential=
            [
                "username"=>$request->username,
                "password"=>$request->password,
               
            ]; 
        
            if(Auth::guard('webuser')->attempt($credential))
            {

                $result = Auth::guard('webuser')->user(); 


                if(!empty($result)){

                    $logout_time = date('Y-m-d H:i:s');
                    $queryLogout =SessionManager::where('device_type','mobile')
                                            ->where('user_id',$result['id'])
                                                    ->update(['logout_time' => $logout_time]);
                    $queryLogout =SessionManager::where('device_type','webuser')
                                            ->where('user_id',$result['id'])
                                                    ->update(['logout_time' => $logout_time]);

                    $username = ApiSecurityLayer::getUserNameForDeletedUser();

                    $result2 = User::where('id',$result['id'])->get()->toArray();
                   // print_r();
                     if(isset($result2)&&isset($result2[0])&&isset($result2[0]['registration_no'])){
                        
                        User::where('id',$result['id'])->update(['username'=>$username,'fullname'=>$username,'l_name'=>$username,'email_id'=>$username.'@seqrdoc.com','mobile_no'=>$username,'registration_no'=>$username,'address'=>$username,'institute'=>$username,'updated_at' => $logout_time]);
                     }else{
                        User::where('id',$result['id'])->update(['username'=>$username,'fullname'=>$username,'email_id'=>$username.'@seqrdoc.com','mobile_no'=>$username,'updated_at' => $logout_time]);
                     }           

                    

                     $message = array('success' => true, 'status'=>200, 'message' => 'Account deleted Successfully.');
                }
                else
                {
                    $message = array('success' => false,'status'=>400,'message' => 'Please enter correct username & password!');
                }
            }
            else
            {
                $message = array('success' => false,'status'=>400,'message' => 'Please enter correct username & password!');
            
            }    
        }
        
        }else
        {
            $message = array('success' => false,'status'=>403,'message' => 'Access forbidden.');
        } 

        $requestUrl = \Request::Url();

        $requestMethod = \Request::method();
        $requestParameter = $data;

        if ($message['success']==true) {
            
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }
        
        
        $api_tracker_id = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);
       
        $response_time = microtime(true) - LARAVEL_START;
   
        ApiTracker::where('id',$api_tracker_id)->update(['response_time'=>$response_time]);
        return $message;   
	}

    
   


}
