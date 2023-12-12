<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\AnswerBookletData;
use App\models\ScannedHistoryAnswerBooklet;
use App\models\SystemConfig;
use App\models\Site;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use Illuminate\Support\Facades\DB;
use App\models\ApiTracker;
use App\Helpers\CoreHelper;
class AnswerBookletController extends Controller
{
    
    public function scan(Request $request)
    {  
     
        $data = $request->post();
        $hostUrl = \Request::getHttpHost();
        $subdomain = explode('.', $hostUrl);
       // $awsS3Instances = \Config::get('constant.awsS3Instances');

        if (ApiSecurityLayer::checkAuthorization()) 
        {   


            

            $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                    'user_id' => 'required',
                    'user_type' => 'required',
                ];

            $messages = [
                'key.required' => 'Key is required',
                'device_type.required' => 'Device type is required',
                'user_id.required' => 'User id is required',
                'user_type.required' => 'User type is required',
            ];

            $validator = \Validator::make($request->post(),$rules,$messages);
            
                
            if ($validator->fails()) {

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

                // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();

            if (!empty($user_id) && $user_id==$data['user_id']) 
            {
                
             /*   $hostUrl = \Request::getHttpHost();
                $subdomain = explode('.', $hostUrl);*/

                $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                $site_id = $site['site_id'];
                
                $key = $data['key'];
                $scan_data = [];
               
                $studentData = AnswerBookletData::where('key',$key)
                                                ->where('publish',1)
                                                ->orderBy('id','DESC')
                                                ->first();
                
                if (!empty($studentData)) {
                    if($studentData['status']=='1'){
                        $scan_result = $studentData['status'];
                        $gotData = [];
                        $gotData['status'] = $studentData['status'];
                        $gotData['qr_data'] = $studentData['qr_data'];
                        $gotData['metadata1'] = $studentData['metadata1'];
                        $gotData['message'] ="Scanned QR is Active.";
                        $scan_data = $gotData;
                    }else{
                        $scan_result = $studentData['status'];
                        $gotData = [];
                        $gotData['status'] = $studentData['status'];
                        $gotData['qr_data'] = $studentData['qr_data'];
                        $gotData['metadata1'] = $studentData['metadata1'];
                        $gotData['message'] ="Scanned QR is not Active.";
                        $scan_data = $gotData;
                    }
                }
                else
                {
                    
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['qr_data'] = "";
                    $gotData['metadata1'] = "";
                    $gotData['message'] ="Scanned QR not found in our system!.";
                    $scan_data = $gotData;
                    
                }

                $date = date('Y-m-d H:i:s');
                
               // $students = AnswerBookletData::where(['key'=>$key])->first();

                $document_id = $studentData['serial_no'];
                $document_status = $studentData['status'];
                
                $scanndHistory = new ScannedHistoryAnswerBooklet();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;
                $scanndHistory['user_type'] = $data['user_type'];
                $scanndHistory['document_id'] = $document_id;
                $scanndHistory['document_status'] = $document_status;
                $scanndHistory->save();
                
                
                $updateStudentData = AnswerBookletData::where('key',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);

               
                if(!empty($studentData)){
                    $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $scan_data);
                }
                else
                {
                    $message = array('success' => false,'status'=>400, 'message' => 'Unsuccess','data' => $scan_data);
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
    
}
