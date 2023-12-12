<?php

namespace App\Http\Controllers\api\verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\User;
use App\models\SessionManager;
use App\models\Site;
use App\models\SystemConfig;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Hash,DB;
use Illuminate\Support\Str;
//use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use Auth;


class LoginController extends Controller
{
    /*use AuthenticatesUsers;*/
    // use ApiSecurityLayer;

    public function login(Request $request)
    {
        $data = $request->post();

        switch ($data['type']) {
            case 'login':
               
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
                        $message = array(['success'=>false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                    }

                    $username=$request->username;
                    $userData = User::where(function ($query) {
                                    $query->where('publish', '=', 1);
                                })->where(function ($query) use ($username) {
                                    $query->where('username','=',$username)
                                          ->orWhere('email_id', '=', $username);
                                })->first();  
                    if($userData){
                       $request->username=$userData['username'];
                    }  
                    $credential=
                    [
                        "username"=>$request->username,
                        "password"=>$request->password
                    ];

                    /*try {
                        \DB::connection()->getPDO();
                        echo \DB::connection()->getDatabaseName();
                        } catch (\Exception $e) {
                        echo 'None';
                    }*/

                    if(Auth::guard('webuser')->attempt($credential))
                    {
                        $result = Auth::guard('webuser')->user(); 

                        if(!empty($result)){
                            if ($result['is_verified'] == 1) {
                                if ($result['status'] == 1) {
                                    //$hostUrl = "127.0.0.1"; old code
                                    $hostUrl = \Request::getHost();

                                    $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                                    $site_id = $site['site_id'];
                                    $SystemConfig = SystemConfig::select('sandboxing')->where('site_id',$site_id)->first();
                                    $sandboxing = $SystemConfig['sandboxing'];
                                    
                                    $session_id = ApiSecurityLayer::generateAccessToken();
                                    $login_time = date('Y-m-d H:i:s');
                                    $ip = $_SERVER['REMOTE_ADDR'];
                                    $id = $result['id'];

                                    //storing session details in db
                                    $sessionData = new SessionManager;
                                    $sessionData->user_id = $id;
                                    $sessionData->session_id = $session_id;
                                    $sessionData->login_time = $login_time;
                                    $sessionData->is_logged = 1;
                                    $sessionData->device_type = 'mobile';
                                    $sessionData->ip = $ip;
                                    $sessionData->save();
                                
                                    
                                    $result['access_token'] = $session_id;
                                    header("accesstoken:" . $session_id);
                                   
                                   $result = $result->toArray(); 

                                   
                                    if($sandboxing == 1){

                                        $message = array('success' => true,'status'=>200,'message' => 'Under Sandboxing Environment', 'data' => $result);
                                    }else{
                                        $message = array('success' => true,'status'=>200,'message' => 'success', 'data' => $result);
                                    }
                                }
                                else
                                {
                                    $message = array('success' => false,'status'=>400,'message' => 'Your account has been deactivated! Please contact to system administrator.');
                                }
                            }
                            else
                            {
                                $message = array('success' => false,'status'=>402,'message' => 'Your verification is pending! Please contact to system administrator.');
                            }
                        }
                        else
                        {
                            $message = array('success' => false,'status'=>400,'message' => 'Please enter correct username & password!');
                        }
                    }
                    else
                    {
                        $message = array('success'=>false,'status'=>400,'message'=>'Error While Login');
                    
                    }    
                }
                else
                {
                    $message = array('success' => false,'status'=>403,'message' => 'Access forbidden.');
                } 
            break;
            case 'logout':
                $user_id = $data['user_id'];
                $session_id = ApiSecurityLayer::checkAccessToken($user_id);

                if ($session_id) {
                    $session_id = ApiSecurityLayer::fetchSessionId($user_id);

                    $logout_time = (string)date('Y-m-d H:i:s');
                
                    $query=SessionManager::where(['user_id'=>$user_id,'id'=>$session_id])->update(['logout_time'=>$logout_time,'is_logged'=>0]);  
                }
                
                 $message = array('success' => true,'status'=>200,'message' => 'Logged Out Successfully');
              

            default:
                # code...
                break;
        }
        
        echo json_encode($message);

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
         

        ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

        
    }
}
