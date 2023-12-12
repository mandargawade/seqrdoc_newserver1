<?php

namespace App\Http\Controllers\api\verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\User;
use App\models\StudentTable;
use App\models\ScannedHistory;
use App\models\Transactions;
use App\models\TemplateMaster;
use App\models\raisoni\ScannedDocuments;
use App\models\raisoni\DocumentRateMaster;
use Illuminate\Support\Facades\Hash;
use App\Utility\ApiSecurityLayer;
use DB,Config;
use App\models\raisoni\ScanningRequests;

class ScanModelController extends Controller
{
    //
    public function ScanModel(Request $request)
    {
        $data = $request->post();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
         $awsS3Instances = \Config::get('constant.awsS3Instances');
        if($subdomain[0] == "monad"){
            $rates = DocumentRateMaster::get()->toArray();
            $degree_price = $rates[0]['amount_per_document'];
            $marksheet_price = $rates[1]['amount_per_document'];
        }

        switch ($request->type) {
            
            case 'validateDocument':
                if(ApiSecurityLayer::checkAuthorization())
                {
                    $user_id = $request->user_id;
                    $key = $request->key;
                    if (!empty($user_id) /*&& ApiSecurityLayer::checkAccessToken($user_id)*/) {
                        $flag = 0; //0 Inactive, 1 Active, 2 Regular
                        if (!empty($key)) {
                            $result = StudentTable::where('key',$key)->where('publish',1)->orderBy('id', 'DESC')->first();
                            if(isset($request->verification_type)&&($request->verification_type!=strtolower($result['certificate_type']))){
                                $flag = 2;
                                $message = array('status' => 500, 'message' => 'You scanned wrong QR code.');
                            }else{
                            if (!empty($result))
                            {
                                if ($result['status'] == '0') {

                                    $errorMsg = 'Unho! You scanned a correct QR code, but the document is not active & valid any longer.';
                                    $error = true;
                                    $message = array('status' => 500, 'message' => $errorMsg);
                                } else {
                                    $result_T = Transactions::where('student_key',$key)->where('user_id',$user_id)->where('publish',1)->where('trans_status',1)->count();

                                    $result_T2 = ScannedDocuments::join('scanning_requests','scanned_documents.request_id','scanning_requests.id')->where('document_key',$key)->where('scanning_requests.user_id',$user_id)->where('scanning_requests.payment_status','Paid')->count();
                                    
                                    if ($result_T > 0 || $result_T2 > 0){
                                        $message = array('status' => 200, 'message' => 'You scanned correct QR code which is already paid.', "alreadyPaid" => 1, "key" => $key);
                                    } else{
                                        $already_paid = 0;
                                        if(!empty($result) && ($subdomain[0]=="monad")){
                                            if(strtolower($result['certificate_type'])==$request->verification_type){
                                                if($result['certificate_type']=="Marksheet"){
                                                    $templateFee=$marksheet_price;
                                                }else{
                                                    $templateFee=$degree_price;
                                                }
                                              //  return response()->json(['status' => 200,'message' => 'You scanned correct QR code.', "alreadyPaid" => 0, "key" => $key, "amount" => $templateFee]);

                                                $message = array('status' => 200,'message' => 'You scanned correct QR code.', "alreadyPaid" => 0, "key" => $key, "amount" => $templateFee);
                                            }else{

                                               // return response()->json(['status' => 500,'message' => 'You scanned wrong QR code.']);
                                                $message = array('status' => 500, 'message' => 'You scanned wrong QR code.');
                                            }
                                        }else{

                                            $template_data = TemplateMaster::where('id',$result['template_id'])->where('status',1)->first();
                                            if (!empty($result) && !empty($template_data['scanning_fee'])) {
                                                $message = array('status' => 200,'success' => true,'message' => 'You scanned correct QR code.', "alreadyPaid" => 0, "key" => $key, "amount" => $template_data['scanning_fee'] );
                                            } else {
                                                $message = array('status' => 500, 'message' => 'Something went wrong check template.');
                                            }
                                        }
                                    }
                                } 
                            } else {
                                $flag = 2;
                                $message = array('status' => 500, 'message' => 'You scanned wrong QR code.');
                            }
                            }
                            $result = User::select('id')->where('id',$user_id)->first();
                            $datetime = date("Y-m-d H:i:s");
                            $device_type = 'Android';
                            $scanned_by = $result['id'];
                            $scan_result = $flag;
                           
                            $scanned_history = new ScannedHistory;
                            $scanned_history->date_time = $datetime;
                            $scanned_history->device_type= $device_type;
                            $scanned_history->scanned_data = $key;
                            $scanned_history->scan_by= $scanned_by;
                            $scanned_history->scan_result= $scan_result;
                            $scanned_history->save();
                        } else{
                            $message = array('status' => 422, 'message' => 'Required parameters not found.');
                        }
                    } else {
                        $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');

                    }
                } else {
                    $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
                break;

            case 'saveRequest':

                if(ApiSecurityLayer::checkAuthorization())
                {
                    $user_id = $request->user_id;
                    $total_files_count = $request->total_files_count;
                    $totalAmount = $request->totalAmount;
                    $qrCodes = $request->qrCodes;

                    if(!empty($user_id))
                    {
                        if (!empty($total_files_count) && !empty($qrCodes))
                        {
                            if (empty($totalAmount)) {
                                $totalAmount = 0;
                            }
                            $result = ScanningRequests::select('request_number')->orderBy('id', 'DESC')->first();
                            $result = json_decode($result,true);
                            if (!empty($result['request_number'])) {
                                $theRest = substr($result['request_number'], 8);
                                $request_number = "NQRCV-R-" . ($theRest + 1);
                            } else {
                                $request_number = "NQRCV-R-1";
                            }

                            $created_date_time = date('Y-m-d H:i:s');
                            $totalAmountBackend = 0;
                            $totalFilesBackend = 0;
                            $user_id = $user_id;
                            $device_type = 'Android';

                            $scanning_request = new ScanningRequests;
                            $scanning_request->request_number = $request_number;
                            $scanning_request->user_id= $user_id;
                            $scanning_request->no_of_documents= $total_files_count;
                            $scanning_request->total_amount= $totalAmount;
                            $scanning_request->created_date_time= $created_date_time;
                            $scanning_request->device_type= $device_type;
                            $scanning_request->save();
                            $id = $scanning_request->id;

                            if (!empty($id)) {
                                $error = false;
                                
                                foreach ($qrCodes as $key) {
                                    $amount = 0;
                                    $result = StudentTable::where('key',$key)->where('publish',1)->orderBy('id','DESC')->first();
                                    if(isset($request->verification_type)&&($request->verification_type!=strtolower($result['certificate_type']))){
                                        $error = true;
                                        $errorMsg = 'You scanned wrong QR code.';
                                    }else{
                                        if (!empty($result)) {
                                            if ($result['status'] == '0') {

                                                $errorMsg = 'Unho! You scanned a correct QR code, but the document is not active & valid any longer.';
                                                $error = true;
                                            } else {
                                                
                                                $result_T = Transactions::where('student_key',$key)->where('user_id',$user_id)->where('publish',1)->where('trans_status',1)->count();

                                                $result_T2 = ScannedDocuments::join('scanning_requests','scanned_documents.request_id','scanning_requests.id')->where('document_key',$key)->where('scanning_requests.user_id',$user_id)->where('scanning_requests.payment_status','Paid')->count();

                                                if ($result_T > 0 || $result_T2 > 0){
                                                    $message = 'You scanned correct QR code which is already paid.';

                                                        $already_paid = 1;
                                                        $amount = 0;
                                                } else{

                                                    $already_paid = 0;

                                                    if(!empty($result) && ($subdomain[0]=="monad")){
                                                        if(strtolower($result['certificate_type'])==$request->verification_type){

                                                            if($result['certificate_type']=="Marksheet"){
                                                                $templateFee=$marksheet_price;
                                                            }else{
                                                                $templateFee=$degree_price;
                                                            }
                                                            $amount = $templateFee;
                                                            $message = 'You scanned correct QR code.';
                                                        }else{
                                                             $errorMsg = 'You scanned wrong QR code.';
                                                            $error = true;
                                                        }
                                                    }else{
                                                        $template_data = TemplateMaster::where('id',$result['template_id'])->where('status',1)->first();
                                                        if (!empty($result) && !empty($template_data['scanning_fee'])) {
                                                            $amount = $template_data['scanning_fee'];
                                                            $message = 'You scanned correct QR code.';
                                                        } else {
                                                            $errorMsg = 'Something went wrong check template.';
                                                            $error = true;
                                                        }
                                                    }
                                                }
                                            }
                                        } else {
                                            $error = true;
                                            $errorMsg = 'You scanned wrong QR code.';
                                        }
                                    }

                                    if ($error) {
                                        
                                        break;
                                    } else {
                                        $scanned_documents = new ScannedDocuments;
                                        $scanned_documents->request_id = $id;
                                        $scanned_documents->document_key = $key;
                                        $scanned_documents->amount = $amount;
                                        $scanned_documents->already_paid = $already_paid;
                                        $scanned_documents->save();   
                                    }
                                    $totalAmountBackend = $totalAmountBackend + $amount;
                                    $totalFilesBackend = $totalFilesBackend + 1;
                                }
                                $totalAmountBackend = explode('.', $totalAmountBackend)[0];
                                

                                if (!$error && $totalAmountBackend == $totalAmount && $total_files_count == $totalFilesBackend) {

                                    if ($totalAmount == 0) {
                                        $showPdf = 1;
                                        $domain = \Request::getHost();
                                        $subdomain = explode('.', $domain);
                                        $subdomain = $subdomain[0];
                                        if(in_array($subdomain, $awsS3Instances)){ 
                                            $config = \Config::get('constant.s3bucket_base_url').$subdomain."/backend/pdf_file";
                                        }else{
                                            $config = Config::get('constant.show_pdf');
                                        }
                                        
                                        $sql = DB::select(DB::raw("SELECT  CONCAT('".$config."/', '' , st.certificate_filename) as pdf_path FROM scanned_documents as sd INNER JOIN student_table as st ON sd.document_key=st.key where sd.request_id='" . $id . "'"));
                                        $dataPdf = json_decode(json_encode($sql),true);
                                    }else{

                                        $showPdf = 0;
                                        $dataPdf = array();
                                    }
                                    $message = array('status' => 200,'success' => true,'message' => 'Request submitted successfully.', "request_number" => $request_number, "request_id" => $id, "amount" => $totalAmount, 'showPdf' => $showPdf, 'dataPdf' => $dataPdf ,'url'=>$_SERVER['HTTP_HOST'].'/verify/payment/paytm');
                                }else{
                                
                                    $message = array('status' => 500, 'message' => 'Something went wrong.',"error"=>$errorMsg,"amountB"=>$totalAmountBackend,"amountA"=>$totalAmount,"countB"=>$totalFilesBackend,"countA"=>$total_files_count);
                                }
                            }else{
                                
                                $message = array('status' => 500, 'message' => 'Something went wrong.');
                            }
                        } else {
                           
                            $message = array('status' => 422, 'message' => 'Required parameters not found.');
                        }
                    } else {
                            
                       $message = array('status' => 422, 'message' => 'Required parameters not found.'); 
                    }
                }else{
                    $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
                break;
            case 'getRequestList':
                if(ApiSecurityLayer::checkAuthorization())
                {
                    $user_id = $request->user_id;
                    $verification_type = $request->verification_type;
                    if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id))
                    {   
                        if($subdomain[0]=="monad"){
                            \DB::statement("SET SQL_MODE=''");
                            $users = DB::select(DB::raw("SELECT sr.*, DATE_FORMAT(sr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time FROM scanning_requests as sr
                                        INNER JOIN user_table as ut ON sr.user_id=ut.id
                                        INNER JOIN scanned_documents as sd ON sd.request_id=sr.id
                                        INNER JOIN student_table as st ON sd.document_key = st.key AND st.certificate_type='".$verification_type."'
                                        LEFT JOIN transactions as tr ON sr.request_number=tr.student_key AND tr.trans_status=1
                                     WHERE sr.payment_status='Paid' AND  sr.user_id='" . $user_id . "' GROUP BY sr.id ORDER BY sr.created_date_time DESC"));
                            $res = json_decode(json_encode($users),true);
                            $message = array('status' => 200,'success' => true,'message' => 'success', "data" => $res); 
                        }else{
                        $users = DB::select(DB::raw("SELECT sr.*, DATE_FORMAT(sr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time FROM scanning_requests as sr
                                        INNER JOIN user_table as ut ON sr.user_id=ut.id
                                        LEFT JOIN transactions as tr ON sr.request_number=tr.student_key AND tr.trans_status=1
                                     WHERE sr.payment_status='Paid' AND  sr.user_id='" . $user_id . "' ORDER BY sr.created_date_time DESC"));
                        $res = json_decode(json_encode($users),true);
                        $message = array('status' => 200,'success' => true,'message' => 'success', "data" => $res);  
                        }
                    } else {
                        $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');

                    }
                } else {
                    $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
                break;
            case 'getRequestDetails':
             if(ApiSecurityLayer::checkAuthorization())
                {
                    $user_id = $request->user_id;
                    $request_id = $request->request_id;
                    if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id))
                    {
                        if (!empty($request_id)) {
                            if(in_array($subdomain[0], $awsS3Instances)){ 
                                $config = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file";
                            }else{
                                $config = Config::get('constant.show_pdf');
                            }
                            $result_show = Config::get('constant.result_show');
                            
                            $sql = DB::select(DB::raw("SELECT sd.*,sr.payment_status, CONCAT('".$config."/', '' , st.certificate_filename) as pdf_path, CONCAT('".$result_show."/', '' , st.path) as qr_code_path FROM scanned_documents as sd
                                                  INNER JOIN student_table as st ON sd.document_key = st.key
                                                  INNER JOIN scanning_requests as sr ON sr.id = sd.request_id
                                                  WHERE sd.request_id='" . $request_id . "'"));


                            $res = json_decode(json_encode($sql),true);

                            foreach($res as $key => $readData){
                                if($readData['already_paid']!=1&&$readData['payment_status']!="Paid"){
                                    $res[$key]['pdf_path']="";
                                    $res[$key]['qr_code_path']="";
                                    $res[$key]['data_pdf']=array();
                                }else{

                                    $sql = DB::select(DB::raw("SELECT  CONCAT('".$config."/', '' , st.certificate_filename) as pdf_path FROM scanned_documents as sd INNER JOIN student_table as st ON sd.document_key=st.key where sd.request_id='" . $request_id . "'"));
                                    $dataPdf = json_decode(json_encode($sql),true);
                                    //$dataPdf=array(array("pdf_path"=>$res[$key]['pdf_path']));
                                    //$dataPdf = json_decode(json_encode($dataPdf),true);
                                    $res[$key]['data_pdf']=$dataPdf;
                                }                            
                            }

                            // print_r($res);
                           

                            
                            $message = array('status' => 200,'success' => true,'message' => 'success', "data" => $res);
                        } else {
                            $message = array('status' => 422, 'message' => 'Required fields not found.');
                        }
                    } else {
                        $message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');
                    }
                } else {
                    $message = array('status' => 403, 'message' => 'Access forbidden.');
                }
                break;
            default:
                $message = array('status' => 404, 'message' => 'Request not found.');
                break;
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

        ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);

        echo json_encode($message);

        
    }
}
