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

class UserLoginController extends Controller
{
    use AuthenticatesUsers;

	public function login(Request $request)
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
                return response()->json(['success'=>false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
            }

            $credential=
            [
                "username"=>$request->username,
                "password"=>$request->password,
               
            ]; 
        
            if(Auth::guard('webuser')->attempt($credential))
            {

                $result = Auth::guard('webuser')->user(); 


                if(!empty($result)){
                    if ($result['is_verified'] == 1) {
                        if ($result['status'] == 1) {

                            $hostUrl = $_SERVER['HTTP_HOST'];

                            
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

    public function webAppLogin(Request $request)
    {
        $data = $request->post();
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
                return response()->json(['success'=>false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
            }

            $credential=
            [
                "username"=>$request->username,
                "password"=>$request->password,
              
            ]; 

            if(Auth::guard('webuser')->attempt($credential))
            {   
                $result = Auth::guard('webuser')->user(); 
                if(!empty($result)){
                    if ($result['is_verified'] == 1) {
                        if ($result['status'] == 1) {

                            $session_id = ApiSecurityLayer::generateAccessToken();
                            $login_time = date('Y-m-d H:i:s');
                            $ip = $_SERVER['REMOTE_ADDR'];
                            $id = $result['id'];
                            //storing session details in db
                            $sessionData = new SessionManager();
                            $sessionData->user_id = $id;
                            $sessionData->session_id = $session_id;
                            $sessionData->login_time = $login_time;
                            $sessionData->is_logged = 1;
                            $sessionData->device_type = 'mobile';
                            $sessionData->ip = $ip;
                            $sessionData->save();
                        
                            header('accesstoken:',$session_id);
                           
                            $message = array('status' => 200, 'message' => 'success', 'data' => $result);
                        }
                        else
                        {
                            $message = array('status' => 500, 'message' => 'Your account has been deactivated! Please contact to system administrator.');
                        }
                    }
                    else
                    {
                        $message = array('status' => 402, 'message' => 'Your verification is pending! Please contact to system administrator.');
                    }
                }
                else
                {
                    $message = array('status' => 422, 'message' => 'Please enter correct username & password!');
                }
            }
            else
            {
            return response()->json(['success'=>false,'status'=>422,'message' =>'Error While Login.']);  
            }    
        }
        else
        {
            $message = array('status' => 403, 'message' => 'Access forbidden.');
        } 

        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $data;

        if ($message['status']==200) {
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

    public function loginVerify(Request $request)
    {
        $id = $request['id'];
        
        if (ApiSecurityLayer::checkAuthorization()) 
        {   
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)) 
            {  
                $rules = [
                    'id' => 'required',
                ];

                $messages = [
                    'id.required' => 'User id required',
                ];

                $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                    return response()->json(['success'=>false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                }

                $result = User::where('id',$id)
                                ->where('publish',1)
                                ->get()->toArray();

                if (!empty($result)) 
                {
                    $message = array('success'=>true,'status'=>200,'message'=>'success','data'=>$result);
                }
                else
                {
                    $message = array('success'=>false,'status'=>404,'message'=>'No data found');
                }
            }
            else
            {
                $message = array('success' => false,'status'=>422, 'message' => 'User id is missing or You dont have access to this api.');
            }
        }
        else
        {
            $message = array('success' => false,'status'=>403, 'message' => 'Access forbidden.');
        }

        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $id;

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

    public function logout(Request $request)
    {
        

         $request->id;
        $message = '';
     
        if (ApiSecurityLayer::checkAuthorization()) 
        {
          
           $data = $user_id = ApiSecurityLayer::fetchUserId();

            if (!empty($user_id)) {
                $session_id = ApiSecurityLayer::fetchSessionId($user_id);

                                
                if ($session_id) 
                {
                    $logout_time = date('Y-m-d H:i:s');
                    $query =SessionManager::where('id',$session_id)
                                            ->where('user_id',$data)
                                                    ->update(['logout_time' => $logout_time,'user_id'=>$data]);
                    $message = array('success' => true, 'status'=>200, 'message' => 'Successfully Logout');
                } 
                else 
                {
                    $message = array('success' => false,'status'=>403, 'message' => 'Access forbidden.');
                }
            } 
            else 
            {
                $message = array('success' => false,'status'=>403, 'message' => 'Access forbidden.');
            }
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

 public function deleteUser(Request $request)
    {
        

          $id=$request->id;
        $message = '';
   
        if (ApiSecurityLayer::checkAuthorization()) 
        {

           DB::delete( DB::raw('DELETE FROM user_table WHERE id ="'.$id.'"'));
        
         $message = array('success' => true,'status'=>200, 'message' => 'success');
        } 
        else 
        {
            $message = array('success' => false,'status'=>403, 'message' => 'Access forbidden.');
        }
    
        return $message;
    }

}
