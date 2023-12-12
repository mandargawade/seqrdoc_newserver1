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

class ScanModelMonadController extends Controller
{
    //
    public function ScanModel(Request $request)
    {
        $data = $request->post();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        if($subdomain[0] == "monad"){
            $rates = DocumentRateMaster::get()->toArray();
            $degree_price = $rates[0]['amount_per_document'];
            $marksheet_price = $rates[1]['amount_per_document'];
        }else{
            $rates = DocumentRateMaster::get()->toArray();
//print_r($rates);
            $degree_price = $rates[1]['amount_per_document'];
            $marksheet_price = $rates[3]['amount_per_document'];
        }

        switch ($request->type) {
            

            case 'saveRequest':

                if(ApiSecurityLayer::checkAuthorization())
                {
                    $user_id = $request->user_id;
                    $total_files_count = $request->total_files_count;
                    $totalAmount = $request->totalAmount;
                    $qrCodes = $request->qrCodes;
                    $verificationType = $request->verification_type;
                    $dataType = $request->data_type;
                   //print_r($qrCodes);
                    if(!empty($user_id))
                    {
                        if (!empty($total_files_count) && !empty($qrCodes)&& ($verificationType=="marksheet" || ($verificationType=="degree"&&$total_files_count==1)) && ($dataType=="qr" || $dataType=="serialno"))
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
                            $scanning_request->verification_type= $verificationType;
                            $scanning_request->data_type= $dataType;
                            $scanning_request->save();
                            $id = $scanning_request->id;

                            if (!empty($id)) {
                               	//$amount = 0;
                                    
                                if($verificationType=="marksheet"){
                                    $amount=$marksheet_price;
                                }else{
                                    $amount=$degree_price;
                                }
                                foreach ($qrCodes as $key) {
                                    
                                    if($dataType=="qr"){
                                        $result = StudentTable::where('key',$key)->where('publish',1)->where('status',1)->where('certificate_type',$verificationType)->orderBy('id','DESC')->first();
                                        if (!empty($result)) {
                                           
                                            $isValid=1;
                                            $respMsg = 'Valid document.';
                                        }else{
                                            
                                            $isValid=0;
                                            $respMsg = 'Invalid document.';
                                        }
                                    }else{
                                        $result = StudentTable::where('serial_no',$key)->where('publish',1)->where('status',1)->where('certificate_type',$verificationType)->orderBy('id','DESC')->first();    
                                        if (!empty($result)) {
                                            
                                            $isValid=1;
                                            $respMsg = 'Valid document.';
                                        }else{
                                        	
                                            $isValid=0;
                                            $respMsg = 'Invalid document.';
                                        }
                                    }
                                    

                                    $scanned_documents = new ScannedDocuments;
                                    $scanned_documents->request_id = $id;
                                    $scanned_documents->document_key = $key;
                                    $scanned_documents->amount = $amount;
                                    $scanned_documents->already_paid = 0;
                                    $scanned_documents->is_valid = $isValid;
                                    $scanned_documents->save();   
                                   
                                    $totalAmountBackend = $totalAmountBackend + $amount;
                                    $totalFilesBackend = $totalFilesBackend + 1;
                                }
                                $totalAmountBackend = explode('.', $totalAmountBackend)[0];
                                

                                if ($totalAmountBackend == $totalAmount && $total_files_count == $totalFilesBackend) {

                                    /*if ($totalAmount == 0) {
                                        $showPdf = 1;
                                        $domain = \Request::getHost();
                                        $subdomain = explode('.', $domain);
                                        $subdomain = $subdomain[0];
                                        $config = Config::get('constant.show_pdf');
                                        if($dataType=="qr"){
                                            $sql = DB::select(DB::raw("SELECT  CONCAT('".$config."/', '' , st.certificate_filename) as pdf_path,sd.is_valid FROM scanned_documents as sd LEFT JOIN student_table as st ON sd.document_key=st.key where sd.request_id='" . $id . "'"));
                                        }else{
                                            $sql = DB::select(DB::raw("SELECT  CONCAT('".$config."/', '' , st.certificate_filename) as pdf_path,sd.is_valid FROM scanned_documents as sd LEFT JOIN student_table as st ON sd.document_key=st.serial_no where sd.request_id='" . $id . "'"));
                                        }
                                        $dataPdf = json_decode(json_encode($sql),true);
                                    }else{*/

                                        $showPdf = 0;
                                        $dataPdf = array();
                                    //}
                                    $message = array('status' => 200,'success' => true,'message' => 'Request submitted successfully.', "request_number" => $request_number, "request_id" => $id, "amount" => $totalAmount, 'showPdf' => $showPdf, 'dataPdf' => $dataPdf ,'url'=>$_SERVER['HTTP_HOST'].'/verify/payment/paytm');
                                }else{
                                
                                    $message = array('status' => 500, 'message' => 'Something went wrong.',"error"=>'Something went wrong.',"amountB"=>$totalAmountBackend,"amountA"=>$totalAmount,"countB"=>$totalFilesBackend,"countA"=>$total_files_count);
                                }
                            }else{
                                
                                $message = array('status' => 500, 'message' => 'Something went wrong.');
                            }
                        } else {
                           
                            $message = array('status' => 422, 'message' => 'Required parameters not found.1');
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
                            \DB::statement("SET SQL_MODE=''");
                            $users = DB::select(DB::raw("SELECT sr.*, DATE_FORMAT(sr.created_date_time, '%d %b %Y %h:%i %p') as created_date_time,ut.fullname,ut.l_name,tr.trans_id_ref as payment_transaction_id,tr.trans_id_gateway as payment_gateway_id, tr.payment_mode,tr.amount,DATE_FORMAT(tr.created_at, '%d %b %Y %h:%i %p') as payment_date_time FROM scanning_requests as sr
                                        INNER JOIN user_table as ut ON sr.user_id=ut.id
                                        INNER JOIN scanned_documents as sd ON sd.request_id=sr.id
                                        LEFT JOIN student_table as st ON (sd.document_key = st.key OR (sd.document_key = st.serial_no AND st.status=1)) 
                                        LEFT JOIN transactions as tr ON sr.request_number=tr.student_key AND tr.trans_status=1
                                     WHERE sr.payment_status='Paid' AND  sr.user_id='" . $user_id . "' AND sr.verification_type='".$verification_type."' GROUP BY sr.id ORDER BY sr.created_date_time DESC"));
                            $res = json_decode(json_encode($users),true);
                            $message = array('status' => 200,'success' => true,'message' => 'success', "data" => $res); 
                        
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
                    $verificationType = $request->verification_type;
                   // $dataType = $request->data_type;
                    if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id))
                    {
                        if (!empty($request_id)&&!empty($verificationType)) {
                            $config = Config::get('constant.show_pdf');
                            $result_show = Config::get('constant.result_show');
                            \DB::statement("SET SQL_MODE=''");
                            $sql = DB::select(DB::raw("SELECT sd.*,sr.payment_status, CONCAT('".$config."/', '' , st.certificate_filename) as pdf_path, CONCAT('".$result_show."/', '' , st.path) as qr_code_path FROM scanned_documents as sd
                                                  LEFT JOIN student_table as st ON (sd.document_key = st.key OR (sd.document_key = st.serial_no AND st.status=1))
                                                  INNER JOIN scanning_requests as sr ON sr.id = sd.request_id
                                                  WHERE sd.request_id='" . $request_id . "' AND sr.payment_status='Paid' GROUP BY sd.id"));

                          
                            $res = json_decode(json_encode($sql),true);
                          	$validCount=0;
                          	$inValidCount=0;
                            foreach($res as $key => $readData){
                                if($res[$key]['is_valid']==0){
                                	$res[$key]['pdf_path']="";
                                	$inValidCount++;
                                }else{
                                	$validCount++;
                                }

                                $res[$key]['data_pdf']=array();
                                /*if($readData['already_paid']!=1&&$readData['payment_status']!="Paid"){
                                    $res[$key]['pdf_path']="";
                                    $res[$key]['qr_code_path']="";
                                    $res[$key]['data_pdf']=array();
                                }else{


                                    $sql = DB::select(DB::raw("SELECT  CONCAT('".$config."/', '' , st.certificate_filename) as pdf_path,is_valid FROM scanned_documents as sd INNER JOIN student_table as st ON (sd.document_key=st.key OR (sd.document_key = st.serial_no AND st.status=1)) where sd.request_id='" . $request_id . "' GROUP BY sd.id"));
                                    $dataPdf = json_decode(json_encode($sql),true);
                                    //$dataPdf=array(array("pdf_path"=>$res[$key]['pdf_path']));
                                    //$dataPdf = json_decode(json_encode($dataPdf),true);
                                    $res[$key]['data_pdf']=$dataPdf;
                                }      */                      
                            }

                            // print_r($res);
                           
                            $totalCount=$validCount+$inValidCount;
                            if($totalCount==1&&$inValidCount==1){
                                $message = array('status' => 404,'success' => false,'message' => 'Certificate not found!');
                            }else{
                            $message = array('status' => 200,'success' => true,'message' => 'success', "data" => $res,"validCount"=>$validCount,"inValidCount"=>$inValidCount);
                            }
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
