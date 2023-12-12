<?php

namespace App\Http\Controllers\api\threeApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Utility\ApiSecurityLayer;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use App\models\SessionManager;
use App\models\TemplateMaster;
use App\models\StudentTable;

class AdminLoginController extends Controller
{
	use AuthenticatesUsers;
    public function login(Request $request){
    	
    	$data = $request->all();

    	if(ApiSecurityLayer::checkAuthorization()){

    		
    		$rules = [
                'username' => 'required',
                'password' => 'required'
            ];

            $messages = [
                'username.required' => 'User name required',
                'password.required' => 'Password required',
            ];

            $validator = \Validator::make($request->all(),$rules,$messages);

            if ($validator->fails()) {
                return response()->json(['success'=>false,'message'=>'Validation Fails, Login Fail',$validator->errors()],400);
            }

            $credential=
            [
                "username"=>$request->username,
                "password"=>$request->password,
            ]; 
            
            if(Auth::guard('admin')->attempt($credential))
            {  
            	$result = Auth::guard('admin')->user(); 
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

            	return response()->json(['success'=>true,'msg'=>'Login Successfully','AccessToken'=>$session_id]);   
            	
            }else
            {
                return response()->json(['success'=>false,'msg'=>'These Credentials do not match our records.']);  
            
            }
    	}
    }
    public function getAllTemplate(){

    	$user_id = ApiSecurityLayer::fetchUserId();
    	
    	if (ApiSecurityLayer::checkAuthorization()){

    		if (!empty($user_id) && ApiSecurityLayer::checkAccessTokenAdmin($user_id)) 
            {
            	$templateData = TemplateMaster::select('template_name','id','status')->get()->toArray();
            	
            	
            	
            	foreach ($templateData as $key => $value) {
            		
            		$last_updated = StudentTable::select('updated_at')->where('template_id',$value['id'])->orderBy('updated_at','desc')->first();
            		
            		$student_table = StudentTable::where(['template_id'=>$value['id'],'status'=>1])->count();

            		$templateData[$key]['active_count'] = $student_table;
            		$templateData[$key]['Last Used Date'] = $last_updated['updated_at'];
            		
            	}
            	$message = array('success' => true, 'data' => $templateData);
            		
            }else
            {
                $message = array('success' => false, 'message' => 'User id is missing or You dont have access to this api.');
            }
    	}else
        {
            $message = array('success' => false, 'message' => 'Access forbidden.');
        }
    	
    	return $message;
    	
    }
    public function generateSeqrocs(Request $request){

        
        $user_id = ApiSecurityLayer::fetchUserId();
        
        if (ApiSecurityLayer::checkAuthorization()){

            if (!empty($user_id) && ApiSecurityLayer::checkAccessTokenAdmin($user_id)){

                dd($user_id);
            
            }else{

                $message = array('success' => false, 'message' => 'User id is missing or You dont have access to this api.');
            }
        }else
        {
            $message = array('success' => false, 'message' => 'Access forbidden.');
        }   
    }
    public function logout(Request $request){

        
        $user_id = ApiSecurityLayer::fetchUserId();
        dd($user_id);
      
    }
}
