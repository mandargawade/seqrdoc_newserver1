<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; 
use App\models\StudentTable;
use App\models\StudentMaster;
use App\models\SessionManager;
use App\Utility\ApiSecurityLayer;
use App\models\ApiTracker;

class StudentController extends Controller
{
 	public function login(Request $request)
    {
        $data = $request->post();
        

        if (ApiSecurityLayer::checkAuthorization()) 
        {   
            // to fetch user id
           
            $rules = [
                'enrollment_no' => 'required',
                'password' => 'required | date'
            ];

            $messages = [
                'enrollment_no.required' => 'Enrollment no. required',
                'password.required' => 'Password Requiredred',
                'password.date' => 'Password is in date format',
            ];

            $validator = \Validator::make($request->post(),$rules,$messages);

            if ($validator->fails()) {
                /*return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);*/
                $message = array('success' => false,'status'=>400, 'message' => ApiSecurityLayer::getMessage($validator->errors()));
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

                        $api_tracker_id_otp = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

                        $response_time = microtime(true) - LARAVEL_START;
                        ApiTracker::where('id',$api_tracker_id_otp)->update(['response_time'=>$response_time]);

                        return $message; 
            }

            $enrollment_no = $data['enrollment_no'];

            $password = date_format(date_create($data['password']),'Y-m-d');

                $studentMaster = StudentMaster::where('enrollment_no',$enrollment_no)
                                            ->where('date_of_birth',$password)
                                            ->first();
                
                if (!empty($studentMaster)) {

                    $session_id = ApiSecurityLayer::generateAccessToken();
                    $studentMaster['access_token'] = $session_id;
                    header("accesstoken:" . $session_id);
                    $login_time = date('Y-m-d H:i:s');
                    $ip = $_SERVER['REMOTE_ADDR'];

                    $sessionData = new SessionManager;
                    $sessionData->user_id = $studentMaster['student_id'];
                    $sessionData->session_id = $session_id;
                    $sessionData->login_time = $login_time;
                    $sessionData->is_logged = 1;
                    $sessionData->device_type = 'mobile';
                    $sessionData->ip = $ip;
                    $sessionData->save();


                    $message = array('success' => true,'status'=>200, 'message' => 'Student login Successfully','data',$studentMaster);
                }
                else
                {
                    $message = array('success' => false,'status'=>400, 'message' => 'Please Check Credential');
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

    public function get_all_student(Request $request)
 	{
        
        
        if (ApiSecurityLayer::checkAuthorization()) 
        {
     		// to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)) 
            {
                $all_student = StudentTable::where('status','1')
     										->where('publish','1')
     										->get();

         		foreach ($all_student as $key => $value) {
         			if ($value['status'] == 0) {
                        $all_student[$key]['status'] = 'Inactive';
                    }

                    else
                    {
                        $all_student[$key]['status'] = 'Active';
                    }
         		}
     		    $message = array('success' => true,'status'=>200, 'message' => 'Success','data'=>$all_student);
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
        $requestParameter = 'no data';

        if ($message['success']==true) {
            $status = 'success';
            $message = array('success' => true,'status'=>200, 'message' => 'Success');
        }
        else
        {
            $status = 'failed';
        }

        $api_tracker_id = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);
        
        $response_time = microtime(true) - LARAVEL_START;
        ApiTracker::where('id',$api_tracker_id)->update(['response_time'=>$response_time]);

        if ($message['success']==true) {
           $message = array('success' => true,'status'=>200, 'message' => 'Success','data'=>$all_student);
        }
        return $message;
 	}  

 	public function get_data(Request $request)
 	{
        
        $key = $request->key;
        

        
        if (ApiSecurityLayer::checkAuthorization()) 
        {
            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)) 
            {
                $rules = [
                    'key' => 'required',
                ];

                $messages = [
                    'key.required' => 'Key Required',
                ];

                $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                    /*return response()->json([false,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);*/

                      $message = array('success' => false,'status'=>400, 'message' => ApiSecurityLayer::getMessage($validator->errors()));
               // return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
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

                        $api_tracker_id_otp = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

                        $response_time = microtime(true) - LARAVEL_START;
                        ApiTracker::where('id',$api_tracker_id_otp)->update(['response_time'=>$response_time]);

                        return $message; 
                }

         		$student_data = StudentTable::where('key',$key)
         								->where('publish','1')
         								->get()->toarray();
               
                if (!empty($student_data)) 
                {
                    foreach ($student_data as $key => $value) {
                        if ($value['status'] == 0) {
                            $student_data[$key]['status'] = 'Inactive';
                        }

                            else
                        {
                            $student_data[$key]['status'] = 'Active';
                        }
                    }

                    $message = array('success' => true,'status'=>200, 'message' => 'Success','data'=>$student_data);
                }
                else
                {   
                    $message = array('success' => false,'status'=>400, 'message' => 'No Data Found.');
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
        $requestParameter = $key;

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

    public function studentCertificate(Request $request)
    {
    
        
        $data = $request->post();
        if (ApiSecurityLayer::checkAuthorization()) 
        {   
            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)) 
            {
                $rules = [
                    'enrollment_no' => 'required'
                ];

                $messages = [
                    'enrollment_no.required' => 'Enrollment No. Required'
                ];

                $validator = \Validator::make($request->post(),$rules,$messages);

                
                if ($validator->fails()) {
                    /*return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);*/
                      $message = array('success' => false,'status'=>400, 'message' => ApiSecurityLayer::getMessage($validator->errors()));
               // return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
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

                        $api_tracker_id_otp = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

                        $response_time = microtime(true) - LARAVEL_START;
                        ApiTracker::where('id',$api_tracker_id_otp)->update(['response_time'=>$response_time]);

                        return $message; 
                }

                $enrollment_no = $request->enrollment_no;

                $student_data = StudentMaster::Join('student_documents','student_master.student_id','student_documents.student_id')
                                        ->where('enrollment_no',$enrollment_no)
                                        ->get()->toArray();
                $finaldoc = [];
                if(!empty($student_data))
                {
                    foreach ($student_data as $key => $value) {
                        $finaldoc['url'] = public_path().'/pdf_file/'.$value['doc_id'].'.pdf';
                        $finaldoc['document_no'] = $value['doc_id'];

                    }
                    $message = array('success' => true,'status'=>200, 'message' => 'Success','data'=>$finaldoc);
                }
                else
                {
                    $message = array('success' => false,'status'=>400, 'message' => 'No Document Found.');
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
    public function studentCertificateCedp(Request $request)
    {
    
        
        $data = $request->post();
        if (ApiSecurityLayer::checkAuthorization()) 
        {   
            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            
            if (!empty($user_id)) 
            {
                $rules = [
                    'enrollment_no' => 'required'
                ];

                $messages = [
                    'enrollment_no.required' => 'Enrollment No. Required'
                ];

                $validator = \Validator::make($request->post(),$rules,$messages);

                
                if ($validator->fails()) {
                    /*return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);*/
                      $message = array('success' => false,'status'=>400, 'message' => ApiSecurityLayer::getMessage($validator->errors()));
               // return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
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

                        $api_tracker_id_otp = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

                        $response_time = microtime(true) - LARAVEL_START;
                        ApiTracker::where('id',$api_tracker_id_otp)->update(['response_time'=>$response_time]);

                        return $message; 
                }

                $enrollment_no = $request->enrollment_no;

                $student_data = StudentMaster::Join('student_documents','student_master.student_id','student_documents.student_id')
                                        ->where('enrollment_no',$enrollment_no)
                                        ->get()->toArray();
                $finaldoc = [];
                if(!empty($student_data))
                {
                    foreach ($student_data as $key => $value) {
                        $finaldoc['url'] = 'https://cedp.seqrdoc.com/cedp/backend/pdf_file/'.$value['doc_id'].'.pdf';
                        $finaldoc['document_no'] = $value['doc_id'];

                    }
                    $message = array('success' => true,'status'=>200, 'message' => 'Success','data'=>$finaldoc);
                }
                else
                {
                    $message = array('success' => false,'status'=>400, 'message' => 'No Document Found.');
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
    public function cedpStudentLogout(Request $request){

        
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
}
