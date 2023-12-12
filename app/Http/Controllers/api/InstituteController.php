<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\InstituteRequest;
use App\models\InstituteMaster;
use App\models\SessionManager;
use App\models\Site;
use App\models\SystemConfig;
use Hash;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use App\models\ApiTracker;

class InstituteController extends Controller
{
    public function get_login(Request $request)
    {   
    	
        
        $data = $request->post();
       
        
        
        if (ApiSecurityLayer::checkAuthorization()) 
        {   

                $rules = [
                    'institute_username' => 'required',
                    'password' => 'required'
                ];

                $messages = [
                    'institute_username.required' => 'Institute user name required',
                    'password.required' => 'Password required',
                ];

                $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                    return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                }

            	$username = $data['institute_username'];
            	$password = $data['password'];
                

              
                $institute_username = InstituteMaster::where('institute_username',$username)->first();
                                
                if (empty($institute_username)) {
                    return response()-> json(array('success' => false,'status'=>400, 'message' => 'Login Fail, please check Username'));
                }

                

                if (empty(Hash::check($password,$institute_username['password']))) {
                    
                    if(md5($password) != $institute_username['password']){
                        
                        return response()-> json(array('success' => false,'status'=>400, 'message' => 'Login Fail, please check Password'));
                    }
                }

                $hostUrl = \Request::getHttpHost();

                $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                $site_id = $site['site_id'];

                $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$site_id)->first();
                $result= array("id" =>$institute_username['id'],"institute_username" =>$institute_username['institute_username'],"device_type" =>"mobile-institute");
                if($systemConfig['sandboxing'] == 1){

                    $message = array('success' => true,'status'=>200, 'message' => 'Success! Under Sandboxing Environment', 'data' => $result);
                }

                 $session_id = ApiSecurityLayer::generateAccessToken();
                            $login_time = date('Y-m-d H:i:s');
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $id = $institute_username['id'];
                            //storing session details in db
                            $sessionData = new SessionManager();
                            $sessionData->user_id = $id;
                            $sessionData->session_id = $session_id;
                            $sessionData->login_time = $login_time;
                            $sessionData->is_logged = 1;
                            $sessionData->device_type = 'mobile-institute';
                            $sessionData->ip = $ip;
                            $sessionData->save();
                        
                           
                            header("accesstoken:" . $session_id);

                $message = array('success' => true,'status'=>200, 'message' => 'Success', 'data' => $result,'accesstoken'=>$session_id);

        }
        else
        {
            $message = array('success' => false,'status'=>403, 'message' => 'Access forbidden.');
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