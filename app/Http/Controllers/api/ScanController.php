<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\StudentTable;
use App\models\SbStudentTable;
use App\models\ScannedHistory;
use App\models\SbScannedHistory;
use App\models\SystemConfig;
use App\models\Site;
use App\models\Transactions;
use App\models\SbTransactions;
use App\models\PrintingDetail;
use App\models\SbPrintingDetail;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use Illuminate\Support\Facades\DB;
use App\models\ApiTracker;
use App\Helpers\CoreHelper;
class ScanController extends Controller
{
    public function NidanScanViewCertificate(Request $request){

        
        
        $data = $request->post();
        $hostUrl = \Request::getHttpHost();
        $subdomain = explode('.', $hostUrl);
        $awsS3Instances = \Config::get('constant.awsS3Instances');

        if (ApiSecurityLayer::checkAuthorization()) 
        {   


            if($subdomain[0]=="monad"){
             $response=CoreHelper::checkMonadFtpStatus();
            
        
           if(!$response['status']){
            $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] =$response['message'];
                    $scan_data = $gotData;
            $message = array('success' => false,'status'=>400, 'message' =>$response['message'],"data"=>$scan_data);
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

       }

            $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                    'user_id' => 'required',
                ];

            $messages = [
                'key.required' => 'Key is required',
                'device_type.required' => 'Device type is required',
                'user_id.required' => 'User id is required',
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

            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)&& $user_id==$data['user_id']) 
            {
                
                $hostUrl = \Request::getHttpHost();
                $subdomain = explode('.', $hostUrl);

                $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                $site_id = $site['site_id'];
               

                $systemConfig = SystemConfig::select('varification_sandboxing')->where('site_id',$site_id)->first();
                 
                if($systemConfig['varification_sandboxing'] == 1){
                 
                    $sandbox =  $this->scanSandboxing($request,$site_id);
                    
                        $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $sandbox['data']);

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

                $key = $data['key'];
                $scan_data = [];

                if($subdomain[0]=="mpkv"){
                $studentData = StudentTable::where('key',$key)
                                                ->where('status',1)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();
                 
                 }else{
                 $studentData = StudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();   
                 }
                if (!empty($studentData)) {
                    if($studentData['status']=='1'){
                        $scan_result = $studentData['status'];
                         $path = 'https://'.$subdomain[0].'.seqrdoc.com/';

                        if($subdomain[0]=='kmtc'&&$studentData['template_id']=='13'){
                            $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/Original.pdf";
                        }elseif($subdomain[0]=='imt' && $studentData['template_id']=='101'){
                           $certificateFilename = \Config::get('constant.lrmis_base_url').$studentData['certificate_filename'];
                        }elseif($subdomain[0]=='monad'){
                           $certificateFilename = \Config::get('constant.monad_base_url')."pdf_file/".$studentData['certificate_filename'];
                        }else{
                            
                            if(in_array($subdomain[0], $awsS3Instances)){ 
                                $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename']; 
                            }else if($subdomain[0]=='test'||$subdomain[0]=='demo'){
                                $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$studentData['serial_no'].'/1/1';
                               // $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename']; 
                            }else{
                            $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                            }
                        }
                        $studentData['fileUrl'] = $certificateFilename;
                        $studentData['scan_result'] = $scan_result;
                        $scan_data = $studentData;

                        $transaction = Transactions::where('student_key',$key)
                                                        ->where('user_id',$data['user_id'])
                                                        ->where('trans_status','1')
                                                        ->where('publish','1')
                                                        ->where('site_id',$site_id)
                                                        ->get()->toArray();
                        if (count($transaction)>=1) {
                            $payment_status = true;
                        }
                        else
                        {
                            $payment_status = false;
                        }
                        $studentData['payment_status'] = $payment_status;
                    }else{
                        $scan_result = $studentData['status'];
                        $gotData = [];
                        $gotData['status'] = $studentData['status'];
                        $gotData['message'] ="The document scanned in not Active.";
                        $scan_data = $gotData;
                    }
                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] ="Certificate not found!.";
                    $scan_data = $gotData;
                    
                }
                $date = date('Y-m-d H:i:s');
                
                $students = StudentTable::where(['key'=>$key])->first();

                $document_id = $students['serial_no'];
                $document_status = $students['status'];

                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;
                $scanndHistory['document_id'] = $document_id;
                $scanndHistory['document_status'] = $document_status;
               
                $scanndHistory->save();
            
                if($subdomain[0]=="mpkv"){
                $updateStudentData = StudentTable::where('key',$key)->where('status',1)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                }else{

                $updateStudentData = StudentTable::where('key',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                }

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

        $api_tracker_id_otp = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

        $response_time = microtime(true) - LARAVEL_START;
        ApiTracker::where('id',$api_tracker_id_otp)->update(['response_time'=>$response_time]);

        return $message;
    }
    public function scanData(Request $request){
        dd($request->all());
    }
    public function scan(Request $request)
    {  
     
        $data = $request->post();
        $hostUrl = \Request::getHttpHost();
        $subdomain = explode('.', $hostUrl);
        $awsS3Instances = \Config::get('constant.awsS3Instances');

        if (ApiSecurityLayer::checkAuthorization()) 
        {   


            if($subdomain[0]=="monad"){
             $response=CoreHelper::checkMonadFtpStatus();
            
        
           if(!$response['status']){
             $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] =$response['message'];
                    $scan_data = $gotData;
            $message = array('success' => false,'status'=>400, 'message' =>$response['message'],"data"=>$gotData);
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

       }

            $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                    'user_id' => 'required',
                ];

            $messages = [
                'key.required' => 'Key is required',
                'device_type.required' => 'Device type is required',
                'user_id.required' => 'User id is required',
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

            if (!empty($user_id) /*&& ApiSecurityLayer::checkAccessToken($user_id)*/&& $user_id==$data['user_id']) 
            {
                
             /*   $hostUrl = \Request::getHttpHost();
                $subdomain = explode('.', $hostUrl);*/

                $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                $site_id = $site['site_id'];
               

                $systemConfig = SystemConfig::select('varification_sandboxing')->where('site_id',$site_id)->first();
                
                if($systemConfig['varification_sandboxing'] == 1){
                 
                    $sandbox =  $this->scanSandboxing($request,$site_id);
                    
                        $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $sandbox['data']);
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
                
                $key = $data['key'];
                $scan_data = [];
                if($subdomain[0]=="mpkv"){
                    $studentData = StudentTable::where('key',$key)
                                                ->where('status',1)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();
                
                }else{
                $studentData = StudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();
                }
                if (!empty($studentData)) {
                    if($studentData['status']=='1'){
                        $scan_result = $studentData['status'];
                         $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                         if($subdomain[0]=='kmtc'&&$studentData['template_id']=='13'){
                        $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/Original.pdf";
                        }elseif($subdomain[0]=='imt' && $studentData['template_id']=='101'){
                           $certificateFilename = \Config::get('constant.lrmis_base_url').$studentData['certificate_filename'];
                        }elseif($subdomain[0]=='monad'){
                           $certificateFilename = \Config::get('constant.monad_base_url')."pdf_file/".$studentData['certificate_filename'];
                        }else{
                            if(in_array($subdomain[0], $awsS3Instances)){ 
                                $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename']; 
                            }else if($subdomain[0]=='test'||($subdomain[0]=='demo')){
                                $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$studentData['serial_no'].'/1/1';
                               //echo $filename1 = \Storage::disk('s3')->url($studentData['serial_no'].'.png');
                               /* echo $image_path = \Storage::disk('s3')->temporaryUrl(
                                                                                    'public/'.$subdomain[0].'/backend/pdf_file/'.$studentData['serial_no'].'.pdf',
                                                                                    Carbon::now()->addMinutes(5)
                                                                                );*/
                               // $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                            }else{

                                
                                    $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                                
                                
                            }
                        }
                        $studentData['fileUrl'] = $certificateFilename;
                        $studentData['scan_result'] = $scan_result;
                        $scan_data = $studentData;

                        $transaction = Transactions::where('student_key',$key)
                                                        ->where('user_id',$data['user_id'])
                                                        ->where('trans_status','1')
                                                        ->where('publish','1')
                                                        ->where('site_id',$site_id)
                                                        ->get()->toArray();
                        if (count($transaction)>=1) {
                            $payment_status = true;
                        }
                        else
                        {
                            $payment_status = false;
                        }
                        $studentData['payment_status'] = $payment_status;
                    }else{
                        $scan_result = $studentData['status'];
                        $gotData = [];
                        $gotData['status'] = $studentData['status'];
                        $gotData['message'] ="The document scanned in not Active.";
                        $scan_data = $gotData;
                    }
                }
                else
                {
                    
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] ="Certificate not found!.";
                    $scan_data = $gotData;
                    
                }

                $date = date('Y-m-d H:i:s');
                
                $students = StudentTable::where(['key'=>$key])->first();

                $document_id = $students['serial_no'];
                $document_status = $students['status'];
                
                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;
                $scanndHistory['document_id'] = $document_id;
                $scanndHistory['document_status'] = $document_status;
                $scanndHistory->save();
                
                if($subdomain[0]=="mpkv"){
                $updateStudentData = StudentTable::where('key',$key)->where('status',1)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                }else{
                $updateStudentData = StudentTable::where('key',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);

                }
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
    public function scanSandboxing($request,$site_id){

        // to fetch user id
        $data = $request->post();

        
        $hostUrl = \Request::getHttpHost();
        $subdomain = explode('.', $hostUrl);
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        if (ApiSecurityLayer::checkAuthorization()) 
        {  

             $user_id = ApiSecurityLayer::fetchUserId();

           
                $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                  
                    'user_id' => 'required',
                ];
                $messages = [
                'key.required' => 'Key is required',
                'device_type.required' => 'Device type is required',
                'user_id.required' => 'User id is required',
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

                $key = $data['key'];
                $scan_data = [];

                $sbStudentData = SbStudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();

                if(!empty($sbStudentData)){

                    if($sbStudentData['status']=='1'){
                    $scan_result = $sbStudentData['status'];

                    $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                    
                    if($subdomain[0]=='test'||$subdomain[0]=='demo'){
                                $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$sbStudentData['serial_no'].'/1/2'; 

                    }else{
                    $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/sandbox/".$sbStudentData['certificate_filename'];
                 
                    if(!file_exists($certificateFilename)){
                        $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$sbStudentData['certificate_filename'];
                       
                    }
                    }
                    $sbStudentData['fileUrl'] = $certificateFilename;
                    $sbStudentData['scan_result'] = $scan_result;
                    $scan_data = $sbStudentData;

                    $transaction = SbTransactions::where('student_key',$key)
                                                    ->where('user_id',$data['user_id'])
                                                    ->where('trans_status','1')
                                                    ->where('publish','1')
                                                    ->where('site_id',$site_id)
                                                    ->get()->toArray();

                    if(count($transaction < 1)){

                        $transaction = Transactions::where('student_key',$key)
                                                    ->where('user_id',$data['user_id'])
                                                    ->where('trans_status','1')
                                                    ->where('publish','1')
                                                    ->where('site_id',$site_id)
                                                    ->get()->toArray();

                    


                    }
                    if (count($transaction)>=1) {
                        $payment_status = true;
                    }
                    else
                    {
                        $payment_status = false;
                    }
                    $sbStudentData['payment_status'] = $payment_status;
                    }else{
                    $scan_result = $sbStudentData['status'];
                    $gotData = [];
                    $gotData['status'] = $sbStudentData['status'];
                    $gotData['message'] ="The document scanned in not Active.";
                    $scan_data = $gotData;
                    }


                }else{

                    
                    $studentData = StudentTable::where('key',$key)
                                                    ->where('publish',1)
                                                    ->where('site_id',$site_id)
                                                    ->orderBy('id','DESC')
                                                    ->first();
                    
                    if (!empty($studentData)) {


                        if($studentData['status']=='1'){
                        
                        $scan_result = $studentData['status'];

                        $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                        
                        if($subdomain[0]=='test'||$subdomain[0]=='demo'){
                                $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$studentData['serial_no'].'/1/2'; 
                            }else{
                        $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/sandbox/".$studentData['certificate_filename'];
                      
                        if(!file_exists($certificateFilename)){
                            $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                           
                        }
                        }

                        $studentData['fileUrl'] = $certificateFilename;
                        $studentData['scan_result'] = $scan_result;
                        $scan_data = $studentData;
                        
                        $transaction = SbTransactions::where('student_key',$key)
                                                        ->where('user_id',$data['user_id'])
                                                        ->where('trans_status','1')
                                                        ->where('publish','1')
                                                        ->where('site_id',$site_id)
                                                        ->get()->toArray();
                        if(count($transaction) < 1){

                            $transaction = Transactions::where('student_key',$key)
                                                        ->where('user_id',$data['user_id'])
                                                        ->where('trans_status','1')
                                                        ->where('publish','1')
                                                        ->where('site_id',$site_id)
                                                        ->get()->toArray();

                        }
                        if (count($transaction)>=1) {
                            $payment_status = true;
                        }
                        else
                        {
                            $payment_status = false;
                        }
                        $studentData['payment_status'] = $payment_status;

                        }else{
                    $scan_result = $studentData['status'];
                    $gotData = [];
                    $gotData['status'] = $studentData['status'];
                    $gotData['message'] ="The document scanned in not Active.";
                    $scan_data = $gotData;
                    }
                    }
                    else
                    {
                        $scan_result = 2;
                        $gotData = [];
                        $gotData['status'] = 2;
                        $gotData['message'] ="Certificate not found!.";
                        $scan_data = $gotData;
                        
                    }
                    $date = date('Y-m-d H:i:s');
                    
                    $scanndHistory = new SbScannedHistory();
                    $scanndHistory['date_time'] = $date;
                    $scanndHistory['device_type'] = $data['device_type'];
                    $scanndHistory['scanned_data'] = $key;
                    $scanndHistory['scan_by'] = $data['user_id'];
                    $scanndHistory['scan_result'] = $scan_result;
                    $scanndHistory['site_id'] = $site_id;
                    $scanndHistory->save();
                    
                    $countStudent = StudentTable::where('key',$key)->get()->count();
                    

                    if($countStudent > 0){

                        $updateStudentData = StudentTable::where('key',$key)
                                            ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                    }else{
                           
                        $updateStudentData = SbStudentTable::where('key',$key)
                                            ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                    }
                }
                

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
    public function scanViewCertificate(Request $request )
    {
       
        $data = $request->post();

       $hostUrl = \Request::getHttpHost();
                $subdomain = explode('.', $hostUrl);
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        if (ApiSecurityLayer::checkAuthorization()) 
        {   

             if($subdomain[0]=="monad"){
             $response=CoreHelper::checkMonadFtpStatus();
            
        
           if(!$response['status']){
            $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] =$response['message'];
                    $scan_data = $gotData;
            $message = array('success' => false,'status'=>400, 'message' =>$response['message'],"data"=>$scan_data);
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

       }


            $rules = [
                'key' => 'required',
                'device_type' => 'required',
               
                'user_id' => 'required'
            ];
            $messages = [
                'key.required' => 'Key is required',
                'device_type.required' => 'Device type is required',
                'user_id.required' => 'User id is required',
            ];

            $validator = \Validator::make($request->post(),$rules,$messages);

            if ($validator->fails()) {
                return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
            }

            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            
            if (!empty($user_id) /*&& ApiSecurityLayer::checkAccessTokenInstitute($user_id)*/&& $user_id==$data['user_id']) 
            {
     
                
                $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                $site_id = $site['site_id'];

                $systemConfig = SystemConfig::select('varification_sandboxing')->where('site_id',$site_id)->first();

                $get_file_aws_local_flag = SystemConfig::select('file_aws_local')->where('site_id',$site_id)->first();

                if($systemConfig['varification_sandboxing'] == 1){
                    $sandbox =  $this->scanViewCertificateSandboxing($request,$site_id);

                    if(isset($sandbox['data'])){

                        $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $sandbox['data']);
                        return $message;  
                    }else{
                        
                        return response()->json(['success' => false,'status'=>400,'message' => $sandbox['message']]); 
                    }
                }
               

                $key = $data['key'];
                $message = [];
                $studentData = StudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();

                if (!empty($studentData)) {
                    
                 
                    $scan_result = $studentData['status'];
                    $array['serialNo'] = $studentData['serial_no'];
                    $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                    if($subdomain[0]=='kmtc'&&$studentData['template_id']=='13'){
                        $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/Original.pdf";
                    }elseif($subdomain[0]=='imt' && $studentData['template_id']=='101'){
                           $certificateFilename = \Config::get('constant.lrmis_base_url').$studentData['certificate_filename'];
                    }elseif($subdomain[0]=='monad'){
                           $certificateFilename = \Config::get('constant.monad_base_url')."pdf_file/".$studentData['certificate_filename'];
                    }else{
                        if(in_array($subdomain[0], $awsS3Instances)){ 
                                $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename']; 
                        }else if($subdomain[0]=='test'||$subdomain[0]=='demo'){
                                $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$studentData['serial_no'].'/1/1'; 
                                //$certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                        }else{
                    $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                    }
                  
                    }
                    $array['fileUrl'] = $certificateFilename;
                    $array['scan_result'] = $scan_result;
                    $array['key'] = $key;
                    $message = $array;

                    $updateStudentData = StudentTable::where('serial_no',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                                           

                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['scan_result'] = 2;
                    $message = $gotData;
                }
                
                $date = date('Y-m-d H:i:s');
                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;

                //if($subdomain[0]=="test"|| $subdomain[0]=="sgrsa" || $subdomain[0]=="vesasc" || $subdomain[0]=="lnctbhopal"|| $subdomain[0]=="lnctindore"|| $subdomain[0]=="surana"|| $subdomain[0]=="srit"){//Code updated by Mandar on 06-06-2023 at 07:00 PM


                $scanndHistory['user_type'] =1; // institute user

                //}
                
                $scanndHistory->save();
               
                if(!empty($studentData)){
                    return response()->json(['success'=>true,'status'=>200,'data' => $message]);   
                }
                else
                {
                    return response()->json(['success' => false,'status'=>404,'message' => "No Data Found"]);    
                }
            }
            else
            {
                $message = array('success' => false, 'status'=>400,'message' => 'User id is missing or You dont have access to this api.');
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

    public function scanViewCertificateSandboxing($request,$site_id)
    {
        $get_file_aws_local_flag = SystemConfig::select('file_aws_local')->where('site_id',$site_id)->first();
       
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        $data = $request->post();

        
        
        if (ApiSecurityLayer::checkAuthorization()) 
        {   
            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            
                $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                    
                    'user_id' => 'required'

                ];
                 $messages = [
                    'key.required' => 'Key is required',
                    'device_type.required' => 'Device type is required',
                    'user_id.required' => 'User id is required',
                ];

            $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                    return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                }
                $hostUrl = \Request::getHttpHost();
                $subdomain = explode('.', $hostUrl);
                $key = $data['key'];
                $message = [];
                $studentData = StudentTable::where('key',$key)
                                                ->where('publish','1')
                                                ->where('site_id',$site_id)
                                                ->get()
                                                ->first();

                $sbStudentData = SbStudentTable::where('key',$key)
                                                ->where('publish','1')
                                                ->where('site_id',$site_id)
                                                ->get()
                                                ->first();
                
                                                

                if (!empty($studentData)) {

                  
                    $scan_result = $studentData['status'];
                    $array['serialNo'] = $studentData['serial_no'];
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $certificateFilename = \Config::get('constant.S3_pdf_file').$studentData['certificate_filename'];
                    }
                    else{
                        if(in_array($subdomain[0], $awsS3Instances)){ 
                                $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename']; 
                        }else if($subdomain[0]=='test'||$subdomain[0]=='demo'){
                                $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$studentData['serial_no'].'/1/1'; 
                                //$certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                            }else{
                        $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                    
                    $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                    }
                    }
                    
                    
                    $array['fileUrl'] = $certificateFilename;
                    $array['scan_result'] = $scan_result;
                    $array['key'] = $key;
                    $message = $array;

                    $updateStudentData = StudentTable::where('serial_no',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);

                   
                }else if(!empty($sbStudentData)){

                    $scan_result = $sbStudentData['status'];
                    $array['serialNo'] = $sbStudentData['serial_no'];

             
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $certificateFilename = \Config::get('constant.S3_pdf_file').'sandbox/'.$sbStudentData['certificate_filename'];
                    }
                    else{
                        $certificateFilename = \Config::get('constant.server_pdf_file').'sandbox/'.$sbStudentData['certificate_filename'];
                    }
                    $array['fileUrl'] = $certificateFilename;
                    $array['scan_result'] = $scan_result;
                    $array['key'] = $key;
                    $message = $array;

                    $updateStudentData = SbStudentTable::where('serial_no',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['scan_result'] = 2;
                    $message = $gotData;
                }
                
                
                $date = date('Y-m-d H:i:s');
                $scanndHistory = new SbScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;

                $scanndHistory->save();
               
                if(!empty($studentData)){

                    $message = array('success'=>true,'status'=>200,'data' => $message);   
                
                }else if(!empty($sbStudentData)){
                    
                    $message = array('success'=>true,'status'=>200,'data' => $message);   
                       
                }
                else
                {
                    $message = array('success' => false,'status'=>404,'message' => "No Data Found");   
                       
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

        ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);
        
        
        return $message;
    }

    public function scanViewAuditTrail(Request $request)
    {
        $data = $request->post();

        $hostUrl = \Request::getHttpHost();
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
        $site_id = $site['site_id'];

        $systemConfig = SystemConfig::select('varification_sandboxing')->where('site_id',$site_id)->first();
        
        
        if (ApiSecurityLayer::checkAuthorization()) 
        {      
             $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                  
                    'user_id' => 'required'
                ];
                $messages = [
                    'key.required' => 'Key is required',
                    'device_type.required' => 'Device type is required',
                    'user_id.required' => 'User id is required',
                ];

                $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                    return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                }
            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && $user_id==$data['user_id']) 
            {   
               

                $key = $data['key'];
                $message = [];
                $printingDetails = PrintingDetail::where('print_serial_no',$key)
                                                ->where('publish',1)
                                                ->first();

                if (!empty($printingDetails)) {

                    $scan_result = $printingDetails['status'];
                    $array['serialNo'] = $printingDetails['sr_no'];
                    $array['userPrinted'] = $printingDetails['username'];
                    $array['printingDateTime'] = $printingDetails['print_datetime'];
                    $array['printerUsed'] = $printingDetails['printer_name'];
                    $array['printCount'] = $printingDetails['print_count'];
                    $array['scan_result'] = $scan_result;
                    $array['key'] = $key;
                    $message = $array;

                    $updatePrintingDetails = PrintingDetail::where('print_serial_no',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] ="Certificate not found!.";
                    $message = $gotData;
                }
                
                $date = date('Y-m-d H:i:s');
                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;

                $scanndHistory->save();
               
                if(!empty($printingDetails)){
                    return response()->json(['success'=>true,'status'=>200,'data' => $message]);   
                }
                else
                {
                    return response()->json(['success' => false,'status'=>400,'message' => "No Data Found"]);    
                }
            }
            else
            {
                $message = array('success' => false,'status'=>400,'message' => 'User id is missing or You dont have access to this api.');
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
    public function scanViewAuditTrailSandbox($request,$site_id)
    {
        $data = $request->post();
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        if (ApiSecurityLayer::checkAuthorization()) 
        {   
             $hostUrl = \Request::getHttpHost();

        $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
        $site_id = $site['site_id'];
             $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                    
                    'user_id' => 'required'
                ];
                $messages = [
                    'key.required' => 'Key is required',
                    'device_type.required' => 'Device type is required',
                    'user_id.required' => 'User id is required',
                ];

                $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                    return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                }
            // to fetch user id
             $user_id = ApiSecurityLayer::fetchUserId();

            if (!empty($user_id) && ApiSecurityLayer::checkAccessTokenInstitute($user_id)&& $user_id==$data['user_id']) 
            {   
               

                $key = $data['key'];
                $message = [];
                $printingDetails = PrintingDetail::where('print_serial_no',$key)
                                                ->where('publish',1)
                                                
                                                ->first();
                
                $sbPrintingDetails = SbPrintingDetail::where('print_serial_no',$key)
                                                ->where('publish',1)
                                                
                                                ->first();

                
                if (!empty($printingDetails)) {

                    $scan_result = $printingDetails['status'];
                    $array['serialNo'] = $printingDetails['sr_no'];
                    $array['userPrinted'] = $printingDetails['username'];
                    $array['printingDateTime'] = $printingDetails['print_datetime'];
                    $array['printerUsed'] = $printingDetails['printer_name'];
                    $array['printCount'] = $printingDetails['print_count'];
                    $array['scan_result'] = $scan_result;
                    $array['key'] = $key;
                    $message = $array;
                    

                    $updatePrintingDetails = PrintingDetail::where('print_serial_no',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                }else if(!empty($sbPrintingDetails)){


                    $scan_result = $printingDetails['status'];
                    $array['serialNo'] = $printingDetails['sr_no'];
                    $array['userPrinted'] = $printingDetails['username'];
                    $array['printingDateTime'] = $printingDetails['print_datetime'];
                    $array['printerUsed'] = $printingDetails['printer_name'];
                    $array['printCount'] = $printingDetails['print_count'];
                    $array['scan_result'] = $scan_result;
                    $array['key'] = $key;
                    $message = $array;
                    

                    $updatePrintingDetails = SbPrintingDetail::where('print_serial_no',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] ="Certificate not found!.";
                    $message = $gotData;
                }
                
                $date = date('Y-m-d H:i:s');
                $scanndHistory = new SbScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;
       
                $scanndHistory->save();
            
                if(!empty($printingDetails)){
                
                    $message = array('success'=>true,'status'=>200,'data' => $message);   
                }else if(!empty($sbPrintingDetails)){
                    
                    $message = array('success'=>true,'status'=>200,'data' => $message);   
                      
                }
                else
                {
                    $message = array('success'=>true,'status'=>200,'message' => "No Data Found");   
                       
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

        ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);
        return $message;   
    }
    public function scaniitjammuverifier(Request $request )
    {
        $data = $request->post();
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        if (ApiSecurityLayer::checkAuthorization()) 
        {   

            $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                    'user_id' => 'required',
                ];

            $messages = [
                'key.required' => 'Key is required',
                'device_type.required' => 'Device type is required',
                'user_id.required' => 'User id is required',
            ];

            $validator = \Validator::make($request->post(),$rules,$messages);

                
            if ($validator->fails()) {
                return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
            }

                // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)&& $user_id==$data['user_id']) 
            {
                
                $hostUrl = \Request::getHttpHost();
                $subdomain = explode('.', $hostUrl);

                $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                $site_id = $site['site_id'];
               

                $systemConfig = SystemConfig::select('varification_sandboxing')->where('site_id',$site_id)->first();
                 
                if($systemConfig['varification_sandboxing'] == 1){
                 
                    $sandbox =  $this->scanSandboxing($request,$site_id);
                    
                        $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $sandbox['data']);
                        return $message; 
                }

                $key = $data['key'];
                $scan_data = [];

                $studentData = StudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();
                 
                if (!empty($studentData)) {
                    if($studentData['status']=='1'){

                        $payment = DB::table('payment_gateway')
                                    ->select('*')
                                    ->join('payment_gateway_config', 'payment_gateway.id', '=', 'payment_gateway_config.pg_id')
                                    ->where('status','=','1')
                                    ->get();

                        if($payment->isEmpty()){

                            $scan_result = $studentData['status'];
                            $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                            $certificateFilename = json_decode($this->CallAPI('POST','https://eg.iitjammu.ac.in/do_open.php',$post_data = array("dtype" => "gettranscriptpath",  "key" => $key)),'true');
                            
                            $studentData['fileUrl'] = $certificateFilename;
                            $studentData['scan_result'] = $scan_result;
                            $studentData['payment'] = 'Disabled';
                            $scan_data = $studentData;

                        }else{
                            $transaction = Transactions::where('student_key',$key)
                                                        ->where('user_id',$data['user_id'])
                                                        ->where('trans_status','1')
                                                        ->where('publish','1')
                                                        ->where('site_id',$site_id)
                                                        ->get()->toArray();
                            if (count($transaction)>=1) {
                                $payment_status = true;
                            }
                            else
                            {
                                $payment_status = false;
                            }

                            if($payment_status){
                                $scan_result = $studentData['status'];
                                $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                                $certificateFilename = json_decode($this->CallAPI('POST','https://eg.iitjammu.ac.in/do_open.php',$post_data = array("dtype" => "gettranscriptpath",  "key" => $key)),'true');
                                
                                $studentData['fileUrl'] = $certificateFilename;
                                $studentData['scan_result'] = $scan_result;
                                $studentData['payment'] = true;
                                $scan_data = $studentData;
                            }else{
                                $scan_result = $studentData['status'];
                                $gotData = [];
                                $gotData['status'] = $studentData['status'];
                                $gotData['message'] ="The document scanned is present.";
                                $gotData['payment'] = false;
                                $scan_data = $gotData; 
                            }
                        }                   

                    }else{
                        $scan_result = $studentData['status'];
                        $gotData = [];
                        $gotData['status'] = $studentData['status'];
                        $gotData['message'] ="The document scanned is not Active.";
                        $scan_data = $gotData;
                    }
                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] ="Certificate not found!.";
                    $scan_data = $gotData;
                    
                }
                $date = date('Y-m-d H:i:s');
                $students = StudentTable::where(['key'=>$key])->first();

                $document_id = $students['serial_no'];
                $document_status = $students['status'];

                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;
                $scanndHistory['document_id'] = $document_id;
                $scanndHistory['document_status'] = $document_status;
                $scanndHistory->save();

                $scan_data['scan_id'] = $scanndHistory->id;
            
                $updateStudentData = StudentTable::where('key',$key)
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

      $api_tracker_id  = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);
     
        $response_time = microtime(true) - LARAVEL_START;
        ApiTracker::where('id',$api_tracker_id)->update(['response_time'=>$response_time]);
        return $message;
    }

    public function scanViewCertificateiitjammuinstitute(Request $request )
    {
        
       $data = $request->post();
       $awsS3Instances = \Config::get('constant.awsS3Instances');
       if (ApiSecurityLayer::checkAuthorization()) 
        {   
                $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                   
                    'user_id' => 'required'
                ];
                $messages = [
                    'key.required' => 'Key is required',
                    'device_type.required' => 'Device type is required',
                    'user_id.required' => 'User id is required',
                ];

            $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                    return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                }

            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();

            if (!empty($user_id) && ApiSecurityLayer::checkAccessTokenInstitute($user_id)&& $user_id==$data['user_id']) 
            {
               
                 $hostUrl = \Request::getHttpHost();
                $subdomain = explode('.', $hostUrl);
                $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                $site_id = $site['site_id'];

                $systemConfig = SystemConfig::select('varification_sandboxing')->where('site_id',$site_id)->first();

                $get_file_aws_local_flag = SystemConfig::select('file_aws_local')->where('site_id',$site_id)->first();

                if($systemConfig['varification_sandboxing'] == 1){
                    
                    $sandbox =  $this->scanViewCertificateSandboxing($request,$site_id);

                    
                    if(isset($sandbox['data'])){

                        $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $sandbox['data']);
                        return $message;  
                    }else{
                        
                        return response()->json(['success' => false,'status'=>400,'message' => $sandbox['message']]); 
                    }
                }
               

                $key = $data['key'];
                $message = [];
                $studentData = StudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();

                if(!empty($studentData)) {
                    if($studentData['status']=='1'){
                    $scan_result = $studentData['status'];
                    $array['serialNo'] = $studentData['serial_no'];
                    $certificateFilename = json_decode($this->CallAPI('POST','https://eg.iitjammu.ac.in/do_open.php',$post_data = array("dtype" => "gettranscriptpath",  "key" => $key)),'true');

                    $array['fileUrl'] = $certificateFilename['Path'];
                    $array['scan_result'] = $scan_result;
                    $array['key'] = $key;
                    $message = $array;

                    $updateStudentData = StudentTable::where('serial_no',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);

                    }
                    else
                    {
                        $scan_result =$studentData['status'];
                        $gotData = [];
                        $gotData['scan_result'] = $studentData['status'];
                        $message = $gotData;
                    } 
                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['scan_result'] = 2;
                    $message = $gotData;
                }

                
                $date = date('Y-m-d H:i:s');

                $students = StudentTable::where(['key'=>$key])->first();

                $document_id = $students['serial_no'];
                $document_status = $students['status'];

                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;
                $scanndHistory['document_id'] = $document_id;
                $scanndHistory['document_status'] = $document_status;
                $scanndHistory['created_at'] = date('Y-m-d H:i:s');

                $scanndHistory->save();
                $scanndHistory->id;

                $message['scan_id'] = $scanndHistory->id;
               
                if(!empty($studentData)){
                    return response()->json(['success'=>true,'status'=>200,'data' => $message]);   
                }
                else
                {
                    return response()->json(['success' => false,'status'=>404,'message' => "No Data Found"]);    
                }
            }
            else
            {
                $message = array('success' => false, 'status'=>400,'message' => 'User id is missing or You dont have access to this api.');
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

    function CallAPI($method,$url,$data)
    {

        $curl = curl_init();
        
        switch ($method)
        {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }

    public function scanCEDP(Request $request)
    {   
        $data = $request->post();
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        if (ApiSecurityLayer::checkAuthorization()) 
        {   

            $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                    'user_id' => 'required',
                ];

            $messages = [
                'key.required' => 'Key is required',
                'device_type.required' => 'Device type is required',
                'user_id.required' => 'User id is required',
            ];

            $validator = \Validator::make($request->post(),$rules,$messages);

                
            if ($validator->fails()) {
                return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
            }

                // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)&& $user_id==$data['user_id']) 
            {
                
                $hostUrl = \Request::getHttpHost();
                $subdomain = explode('.', $hostUrl);

                $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                $site_id = $site['site_id'];
               

                $systemConfig = SystemConfig::select('varification_sandboxing')->where('site_id',$site_id)->first();
                 
                if($systemConfig['varification_sandboxing'] == 1){
                 
                    $sandbox =  $this->scanSandboxing($request,$site_id);
                    
                        $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $sandbox['data']);
                        return $message; 
                }

                $key = $data['key'];
                $scan_data = [];
                $studentData = StudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();
                 
                if (!empty($studentData)) {
                    if($studentData['status']=='1'){

                        $payment = DB::table('payment_gateway')
                                    ->select('*')
                                    ->join('payment_gateway_config', 'payment_gateway.id', '=', 'payment_gateway_config.pg_id')
                                    ->where('status','=','1')
                                    ->get();

                        if($payment->isEmpty()){

                            $scan_result = $studentData['status'];

                            if(in_array($subdomain[0], $awsS3Instances)){ 
                                $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename']; 
                            }else if($subdomain[0]=='test'||$subdomain[0]=='demo'){
                                $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$studentData['serial_no'].'/1/1'; 
                               // $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                            }else{
                            $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                                $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                            }
                            $studentData['fileUrl'] = $certificateFilename;
                            $studentData['scan_result'] = $scan_result;
                            $studentData['payment'] = 'Disabled';
                            $scan_data = $studentData;

                        }else{
                            $transaction = Transactions::where('student_key',$key)
                                                        ->where('user_id',$data['user_id'])
                                                        ->where('trans_status','1')
                                                        ->where('publish','1')
                                                        ->where('site_id',$site_id)
                                                        ->get()->toArray();
                            if (count($transaction)>=1) {
                                $payment_status = true;
                            }
                            else
                            {
                                $payment_status = false;
                            }

                            if($payment_status){
                                $scan_result = $studentData['status'];
                                if(in_array($subdomain[0], $awsS3Instances)){ 
                                $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename']; 
                                }else if($subdomain[0]=='test'||$subdomain[0]=='demo'){
                                $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$studentData['serial_no'].'/1/1'; 
                               // $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                            }else{
                                $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                                    $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                                }
                                
                                $studentData['fileUrl'] = $certificateFilename;
                                $studentData['scan_result'] = $scan_result;
                                $studentData['payment'] = true;
                                $scan_data = $studentData;
                            }else{
                                $scan_result = $studentData['status'];
                                $gotData = [];
                                $gotData['status'] = $studentData['status'];
                                $gotData['message'] ="The document scanned is present.";
                                $gotData['payment'] = false;
                                $scan_data = $gotData; 
                            }
                        }                   

                    }else{
                        $scan_result = $studentData['status'];
                        $gotData = [];
                        $gotData['status'] = $studentData['status'];
                        $gotData['message'] ="The document scanned is not Active.";
                        $scan_data = $gotData;
                    }
                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] ="Certificate not found!.";
                    $scan_data = $gotData;
                    
                }
                $date = date('Y-m-d H:i:s');
                
                $students = StudentTable::where(['key'=>$key])->first();

                $document_id = $students['serial_no'];
                $document_status = $students['status'];

                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;
                $scanndHistory['document_id'] = $document_id;
                $scanndHistory['document_status'] = $document_status;
                $scanndHistory->save();

                $scanndHistory->id;

                $scan_data['scan_id'] = $scanndHistory->id;
            
                $updateStudentData = StudentTable::where('key',$key)
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


    public function scanHistory(Request $request)
    {   
        $data = $request->post();
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        if (ApiSecurityLayer::checkAuthorization()) 
        {   

            $rules = [
                    'device_type' => 'required',
                    'user_id' => 'required',
                ];

            $messages = [
                'device_type.required' => 'Device type is required',
                'user_id.required' => 'User id is required',
            ];

            $validator = \Validator::make($request->post(),$rules,$messages);

                
            if ($validator->fails()) {
                return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
            }

                // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)&& $user_id==$data['user_id']) 
            {
                
                $scan_data = DB::table('scanned_history')
                                    ->select('*')
                                    ->where('scan_by','=',$data['user_id'])
                                    ->get();
                
                 $hostUrl = \Request::getHttpHost();
                $subdomain = explode('.', $hostUrl);
                $scan_output_data = [];

                foreach ($scan_data as $key => $value) {
                    
                    
                    $student_data = StudentTable::where('key',$value->scanned_data)->get()->first();
                    
                    $scan_output_data[$key]['id'] = $value->id;
                    $scan_output_data[$key]['date_time'] = $value->date_time;
                    $scan_output_data[$key]['device_type'] = $value->device_type;
                    $scan_output_data[$key]['scanned_data'] = $value->scanned_data;
                    $scan_output_data[$key]['scan_by'] = $value->scan_by;
                    $scan_output_data[$key]['scan_result'] = $value->scan_result;
                    $scan_output_data[$key]['site_id'] = $value->site_id;
                    $scan_output_data[$key]['document_id'] = $value->document_id;
                    $scan_output_data[$key]['document_status'] = $value->document_status;
                    $scan_output_data[$key]['created_at'] = $value->created_at;

                    if(in_array($subdomain[0], $awsS3Instances)){ 
                                $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename']; 
                    }else if($subdomain[0]=='test'||$subdomain[0]=='demo'){
                        $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$student_data['serial_no'].'/1/1'; 
                       // $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                    }else{
                    $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                    $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$student_data['certificate_filename'];
                    }
                    $scan_output_data[$key]['pdf_url'] = $certificateFilename;
                   

                }
                    
                if(!empty($scan_data)){
                    $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $scan_output_data);
                }
                else
                {
                    $message = array('success' => false,'status'=>400, 'message' => 'Unsuccess');
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
    public function nidanScanCertificate(Request $request)
    {
        
        $data = $request->post();

        $awsS3Instances = \Config::get('constant.awsS3Instances');
        
        if (ApiSecurityLayer::checkAuthorization()) 
        {   


             $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                   
                    'user_id' => 'required'
                ];
                $messages = [
                    'key.required' => 'Key is required',
                    'device_type.required' => 'Device type is required',
                    'user_id.required' => 'User id is required',
                ];

            $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                   // return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
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
              
                 $hostUrl = \Request::getHttpHost();
                 
                $subdomain = explode('.', $hostUrl);
                
                $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
                $site_id = $site['site_id'];

                $systemConfig = SystemConfig::select('varification_sandboxing')->where('site_id',$site_id)->first();

                $get_file_aws_local_flag = SystemConfig::select('file_aws_local')->where('site_id',$site_id)->first();
               

                $key = $data['key'];
                $message = [];

                if($subdomain[0]=="mpkv"){
                $studentData = StudentTable::where('key',$key)
                                                ->where('status',1)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();
                }else{
                $studentData = StudentTable::where('key',$key)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();
                }
                if (!empty($studentData)) {
                    

                    $printing_details = PrintingDetail::where('sr_no',$studentData['serial_no'])->where('status',1)->orderBy('id','DESC')->first();
                   
                    if(!empty($printing_details)){

                        $print_datetime = explode(" ", $printing_details['print_datetime']);

                        $array['isPrintDetailsAvailable'] = 0;
                        $array['barcode'] = $printing_details['print_serial_no'];
                        $array['userPrinted'] = $printing_details['username'];
                        $array['printedDate'] = $print_datetime[0];
                        $array['printedTime'] = $print_datetime[1];
                        $array['printCount'] = $printing_details['print_count'];
                        $array['status'] = $printing_details['status'];
                        $array['printerUsed'] = $printing_details['printer_name'];
                    }else{

                        $array['isPrintDetailsAvailable'] = 1;
                    }
                 
                    $scan_result = $studentData['status'];
                    $array['serialNo'] = $studentData['serial_no'];
                    $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
                    
                        if($subdomain[0]=='kmtc'&&$studentData['template_id']=='13'){
                            $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/Original.pdf";
                        }elseif($subdomain[0]=='monad'){
                           $certificateFilename = \Config::get('constant.monad_base_url')."pdf_file/".$studentData['certificate_filename'];
                        }else{
                            if(in_array($subdomain[0], $awsS3Instances)){ 
                                $certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename']; 
                            }else if($subdomain[0]=='test'||$subdomain[0]=='demo'){
                                $certificateFilename = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$studentData['serial_no'].'/1/1'; 
                                //$certificateFilename = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                            }else{
                    $certificateFilename = $path.$subdomain[0]."/backend/pdf_file/".$studentData['certificate_filename'];
                }
                  
                }
                    $array['fileUrl'] = $certificateFilename;
                    $array['scan_result'] = $scan_result;
                    $array['key'] = $key;
                    $message = $array;

                    $updateStudentData = StudentTable::where('serial_no',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                                                 

                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['scan_result'] = 2;
                    $message = $gotData;
                }
                
                $date = date('Y-m-d H:i:s');
                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;

                //if($subdomain[0]=="test"|| $subdomain[0]=="sgrsa" || $subdomain[0]=="vesasc"|| $subdomain[0]=="lnctbhopal"|| $subdomain[0]=="lnctindore"|| $subdomain[0]=="surana"|| $subdomain[0]=="srit"){//Code updated by Mandar on 06-06-2023 at 07:00 PM


                $scanndHistory['user_type'] =1; // institute user

                //}
                
                $scanndHistory->save();
               
                if(!empty($studentData)){
                    //return response()->json(['success'=>true,'status'=>200,'data' => $message]); 
                    $message = array('success' => true, 'status'=>200,'data' => $message);  
                }
                else
                {   
                    $message = array('success' => false,'status'=>404,'message' => "No Data Found");
                    //return response()->json(['success' => false,'status'=>404,'message' => "No Data Found"]);    
                }
            }
            else
            {
                $message = array('success' => false, 'status'=>400,'message' => 'User id is missing or You dont have access to this api.');
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

    public function nidanScanViewAuditTrail()
    {
        $data = $request->post();

        $hostUrl = \Request::getHttpHost();
        $awsS3Instances = \Config::get('constant.awsS3Instances');

        $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
        $site_id = $site['site_id'];

        $systemConfig = SystemConfig::select('varification_sandboxing')->where('site_id',$site_id)->first();
        if($systemConfig['varification_sandboxing'] == 1){

            $sandbox =  $this->scanViewAuditTrailSandbox($request,$site_id);

            if(isset($sandbox['data'])){

                $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $sandbox['data']);
                return $message;  
            }else{
                
                return response()->json(['success' => false,'status'=>400,'message' => $sandbox['message']]); 
            }
        }

        
        if (ApiSecurityLayer::checkAuthorization()) 
        {      
             $rules = [
                    'key' => 'required',
                    'device_type' => 'required',
                  
                    'user_id' => 'required'
                ];
                $messages = [
                    'key.required' => 'Key is required',
                    'device_type.required' => 'Device type is required',
                    'user_id.required' => 'User id is required',
                ];

                $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                    return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                }
            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessTokenInstitute($user_id)&& $user_id==$data['user_id']) 
            {   
               

                $key = $data['key'];
                $message = [];
                $printingDetails = PrintingDetail::where('print_serial_no',$key)
                                                ->where('publish',1)
                                                ->first();

                if (!empty($printingDetails)) {

                    $scan_result = $printingDetails['status'];
                    $array['serialNo'] = $printingDetails['sr_no'];
                    $array['userPrinted'] = $printingDetails['username'];
                    $array['printingDateTime'] = $printingDetails['print_datetime'];
                    $array['printerUsed'] = $printingDetails['printer_name'];
                    $array['printCount'] = $printingDetails['print_count'];
                    $array['scan_result'] = $scan_result;
                    $array['key'] = $key;
                    $message = $array;

                    $updatePrintingDetails = PrintingDetail::where('print_serial_no',$key)
                                                        ->update(['scan_count' => \DB::raw('scan_count + 1')]);
                }
                else
                {
                    $scan_result = 2;
                    $gotData = [];
                    $gotData['status'] = 2;
                    $gotData['message'] ="Certificate not found!.";
                    $message = $gotData;
                }
                
                $date = date('Y-m-d H:i:s');
                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] = $key;
                $scanndHistory['scan_by'] = $data['user_id'];
                $scanndHistory['scan_result'] = $scan_result;

                $scanndHistory->save();
               
                if(!empty($printingDetails)){
                    return response()->json(['success'=>true,'status'=>200,'data' => $message]);   
                }
                else
                {
                    return response()->json(['success' => false,'status'=>400,'message' => "No Data Found"]);    
                }
            }
            else
            {
                $message = array('success' => false,'status'=>400,'message' => 'User id is missing or You dont have access to this api.');
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

        ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);
        return $message;   
    }

    public function estampScan(Request $request)
    {  
     
        $data = $request->post();
        $hostUrl = \Request::getHttpHost();
        $subdomain = explode('.', $hostUrl);
        
        $awsS3Instances = \Config::get('constant.awsS3Instances');
            $rules = [
                    'certificateNo' => 'required',
                    'device_type' => 'required',
                ];

            $messages = [
                'certificateNo.required' => 'Certificate number is required',
                'device_type.required' => 'Device type is required',
            ];

            $validator = \Validator::make($request->post(),$rules,$messages);
            
                
            if ($validator->fails()) {

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

            $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
            $site_id = $site['site_id'];

             $certificateNo = $data['certificateNo'];
             $certificateData = StudentTable::where('serial_no',$certificateNo)
                                                ->where('publish',1)
                                                ->where('site_id',$site_id)
                                                ->orderBy('id','DESC')
                                                ->first();
            //print_r($certificateData);
            if($certificateData){
                $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => (array)$certificateData['json_data']);
                $scan_result=1;
            }else{
                $scan_result=0;
                $message = array('success' => false,'status'=>400, 'message' => 'No data found.');
            }
            

                $date = date('Y-m-d H:i:s');
               
                $document_id = $certificateData['serial_no'];
                $document_status = $certificateData['status'];
                
                $scanndHistory = new ScannedHistory();
                $scanndHistory['date_time'] = $date;
                $scanndHistory['device_type'] = $data['device_type'];
                $scanndHistory['scanned_data'] =  $certificateData['key'];
                $scanndHistory['scan_by'] = 0;
                $scanndHistory['scan_result'] = $scan_result;
                $scanndHistory['site_id'] = $site_id;
                $scanndHistory['document_id'] = $document_id;
                $scanndHistory['document_status'] = $document_status;
                $scanndHistory->save();
                
                
                $updateStudentData = StudentTable::where('serial_no',$serial_no)
                                                   ->where('publish',1)
                                                   ->where('site_id',$site_id)
                                                   ->update(['scan_count' => \DB::raw('scan_count + 1')]);

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
