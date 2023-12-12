<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use App\models\ApiTracker;

class SecureDocumentController extends Controller
{
    public function printLogin(Request $request)
    {
    	$data = $request->post();
        

        if (ApiSecurityLayer::checkAuthorization()) 
        {   
            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)) 
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

                $credential=[

                "username"=>$request->username,
                "password"=>$request->password
              ]; 

                if(Auth::guard('admin')->attempt($credential))
                {   
                    $message = array('success' => true,'status'=>200, 'message' => 'Login Successfully Done.');
                }
                else
                {
                    $message = array('success' => false,'status'=>400, 'message' => 'These credentials do not match our records.');
                }
            }
            else
            {
                $message = array('success' => false,'status'=>400, 'message' => 'User id is missing or You dont have access to this api.');
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

    public function printUpdatePrinting(Request $request)
    {
    	$data = $request->post();

    	$rules = [
            'username' => 'required',
            'printer_name' => 'required'
        ];

        $validator = \Validator::make($request->post(),$rules);

        
        if ($validator->fails()) {
            return response()->json([false,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
        }

        $file_path = save_file();
        $print_serials = read_and_format_data($file_path);
		$count=0;

		if (!empty($print_serials)) {
			foreach ($print_serials as $value) {
				$printData = PrintingDetail::where('print_serial_no',$value)
                                                ->update(['print_datetime' => Now(),'printer_name'=> $printer_name,'username' => $username]);
			}
		}

		$updated_count_array = update_printing_count($conn,$count);
		return_message('true','Updated Printing Records of: '.$count.' document(s).',array_merge(array('documents'=>$print_serials), $updated_count_array));	
    }

    function save_file()
    {
    	if($request->hasfile('upfile'))
            {
                $path = public_path()."/upload/tmp_name";
                $file = $request->file('upfile');
                $filename = $file->getClientOriginalName();
                $filename = $data['upfile'].'_'.$filename;
                $file->move($path,$filename);
                $backgroundTemplateMaster->image_path = $filename;
            }
    }

    function read_and_format_data($file_path)
    {

        $myfile = fopen($file_path,'r');
        $print_serial_no = fread($myfile,filesize($file_path));
        fclose($myfile);
        $print_serials = explode(",",$print_serial_no);
        return $print_serials;
    }
    function update_printing_count($conn,$count){

        $stmt = SuperAdmin::where('property','max_certificate_generation')
                                 ->update(['current_value' => 'current_value'+$count]);
        $result = SuperAdmin::where('property','max_certificate_generation');
        return $result;
       
    }


}