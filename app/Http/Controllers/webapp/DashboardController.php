<?php

namespace App\Http\Controllers\webapp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\StudentTable;
use App\models\Site;
use App\models\SbStudentTable;
use App\models\StudentHistory;
use App\models\Transactions;
use App\models\SbTransactions;
use App\models\PaymentGateway;
use App\models\ScannedHistory;
use PaytmWallet;
use Tzsk\Payu\Facade\Payment;
use Session;
use App\models\SystemConfig;
use App\models\SiteDocuments;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // show scan UI
    public function dashboard(Request $request){

    	return view('webapp.dashboard.show');

    }

  

    public function store(Request $request)
    {
        
        $auth_site_id=\Auth::guard('webuser')->user()->site_id;

        $get_file_aws_local_flag = SystemConfig::select('file_aws_local')->where('site_id',$auth_site_id)->first();
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first()->toArray();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $awsS3Instances = \Config::get('constant.awsS3Instances');

        if($get_file_aws_local_flag->file_aws_local == '1'){
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $path = \Config::get('constant.amazone_path').$subdomain[0].'/backend/pdf_file/sandbox';
            }
            else{
                $path = \Config::get('constant.amazone_path').$subdomain[0].'/backend/pdf_file';
            }
        }
        else{
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $path = \Config::get('constant.local_base_path').$subdomain[0].'/backend/pdf_file/sandbox';
            }
            else{
                if($subdomain[0]=="test"||$subdomain[0]=="demo"){
                   // $path = \Config::get('constant.s3bucket_base_url').$subdomain[0].'/backend/pdf_file';
                    $path = 'https://'.$subdomain[0].'.seqrdoc.com/api/pdf/replace_serial_no/1/1';
                }else{
                    if(in_array($subdomain[0], $awsS3Instances)){ 
                        $path = \Config::get('constant.s3bucket_base_url').$subdomain[0]."/backend/pdf_file"; 
                    }else{
                        $path = \Config::get('constant.local_base_path').$subdomain[0].'/backend/pdf_file'; 
                    }
                       
                }
                
            }
        }
        
        if(isset($_POST['qrcode'])){
            
            $key = $_POST['qrcode'];
            $user_id = \Auth::guard('webuser')->user()->id;
            $site_id = \Auth::guard('webuser')->user()->site_id;
            $update_scan = $_POST['update_scan'];
            
            if(strstr($key, "\n")){

                $lines = explode("\n", $key);
                for($i=0;$i<sizeof($lines)-2;$i++){

                    $data[] =  $lines[$i];
                }
                $data_info = implode('<br>',$data);
                $key = (end($lines));
                $flag1 = 1;
                  
            }else{

                $key = $_POST['qrcode'];
                $flag1 = 0;
            }
            //echo $key;
            $flag = 1;
            $student_count = StudentTable::where(['key'=>$key])->count();

            /*print_r($key);
            print_r($student_count);*/
            $sb_student_count = SbStudentTable::where(['key'=>$key])->count();


            
            
            if($student_count > 0){
                
                $student = StudentTable::where(['key'=>$key,'publish'=>1])
                ->orderBy('id', 'DESC')
                ->first();
               
                if($student['status'] == '0'){

                    $flag = 0;
                    $html = '';
                    $html .= '<div class="alert alert-danger">';
                    $html .= '<strong>Error</strong>';
                    $html .= 'You scanned a correct QR code';
                    $html .= '<i class="fa fa-qrcode fa-fw theme"></i>';
                    $html .= 'but the document is not active & valid any longer.';
                    $html .= 'Please <a href="'.route('webapp.dashboard').'">click here</a>';
                    $html .= 'to scan again.';
                    $html .= '</div>';

                    $message = $html;
                }else{

                    $transaction = Transactions::where(['student_key'=>$key,
                        'user_id'=>$user_id,
                        'publish'=>1,
                        'trans_status'=>1
                        ])->count();
                    
                    $domain = \Request::getHost();
                    $siteData =Site::where(['site_url'=>$domain])->first();
                    $noPaymentGateway=0;
                    if($domain=="wilson.seqrdoc.com"){
                        
                        if($siteData['start_date']>="2023-07-24"){
                            $noPaymentGateway=1;
                        }
                    }


                    if($transaction > 0  || $subdomain[0]=="test" || $subdomain[0]=="vesasc" || $subdomain[0]=="lnctbhopal"|| $subdomain[0]=="surana"|| $subdomain[0]=="srit"||$subdomain[0]=="mitwpu"|| $noPaymentGateway==1){
                    	if($subdomain[0]=="test"||$subdomain[0]=="demo"){
                        	$pathPDF=str_replace("replace_serial_no",$student['serial_no'],$path);
                    	}else{
                    		$pathPDF=$path.'/'.$student["certificate_filename"];
                    	}	
                        if($flag1 == 1){

                            $html = '';
                            $html .= '<div class="panel panel-info">';
                            $html .= '<div class="panel-heading"><b>Document Information</b></div>';
                            $html .= '<div class="panel-body">';
                            $html .= '<div class="col-xs-6">
                                        <div claass="row">
                                            <div class="col-xs-5"><label for="info1">Document ID</label></div>
                                            <div class="col-xs-1"><label for="info1">:</label></div>
                                            <div class="col-xs-6">'.$student['serial_no'].'</div>
                                        </div><br><br>
                                        <div claass="row">
                                            <div class="col-xs-5"><label for="info3">Data</label></div>
                                            <div class="col-xs-1"><label for="info3">:</label></div>
                                            <div class="col-xs-6">'.$data_info.'</div>
                                        </div><br><br>
                                   
                                        <div claass="row">
                                            <div class="col-xs-12">
                                                <iframe src="'.$pathPDF.'#toolbar=0" width="810" height="780">
                                                </iframe>
                                            </div>
                                        </div>
                                    </div>';
                            $html .=  '</div></div>'; 

                        }else{

                            $html = '';
                            $html .= '<div class="panel panel-info">';
                            $html .= '<div class="panel-heading"><b>Document Information</b></div>';
                            $html .= '<div class="panel-body">';
                            $html .= '<div class="col-xs-6">
                                        <div claass="row">
                                            <div class="col-xs-5"><label for="info1">Document ID</label></div>
                                            <div class="col-xs-1"><label for="info1">:</label></div>
                                            <div class="col-xs-6">'.$student['serial_no'].'</div>
                                        </div><br><br>
                                        
                                        <div claass="row">
                                            <div class="col-xs-6">
                                                <iframe src="'.$pathPDF.'#toolbar=0" width="810" height="780">
                                                </iframe>
                                            </div>
                                        </div>
                                    </div>';
                            $html .=  '</div></div>'; 
                        }            

                    }else{


                        $flag = 1;
                        $selectColumns = ['payment_gateway.pg_name','payment_gateway_config.pg_id','payment_gateway_config.pg_status','payment_gateway_config.amount'];

                        $paymentList = PaymentGateway::select($selectColumns)
                            ->leftjoin('payment_gateway_config','payment_gateway_config.pg_id','payment_gateway.id')
                            ->where(['payment_gateway_config.pg_status'=>1,'payment_gateway.status'=>1,'payment_gateway.publish'=>1,'site_id'=>$site_id])
                            ->get()
                            ->toArray();
                        $student_compact = $student->toArray();
                        $html = '';
                        $html .= '<div class="panel panel-info">';
                        $html .= '<div class="panel-heading"><b>Student Information</b></div>';
                        $html .= '<div class="panel-body">';
                       // print_r($paymentList);
                        if($paymentList){
                        foreach ($paymentList as $payment_key => $value) {
                            
                            $pg_id = $value['pg_id'];
                            
                            if($value['pg_status'] == 1){

                                $transaction_count = Transactions::where(['student_key'=>$key,'user_id'=>$user_id,'publish'=>1,'trans_status'=>1])->count();
                               
                                $payment_status = 'false';
                                
                                if($transaction_count > 0){
                                    $payment_status = 'true';
                                }
                            }

                            if($payment_status == 'true'){
                                    
                                	if($subdomain[0]=="test"||$subdomain[0]=="demo"){
			                        	$pathPDF=str_replace("replace_serial_no",$student['serial_no'],$path);
			                    	}else{
			                    		$pathPDF=$path.'/'.$student["certificate_filename"];
			                    	}
                                    if($flag1 == 1){

                                        $html = '';
                                        $html .= '<div class="panel panel-info">';
                                        $html .= '<div class="panel-heading"><b>Document Information</b></div>';
                                        $html .= '<div class="panel-body">';
                                        $certificate_filename=$student['certificate_filename'];
                                        $html .= '<div class="col-xs-6">
                                                    <div claass="row">
                                                        <div class="col-xs-5"><label for="info1">Document ID</label></div>
                                                        <div class="col-xs-1"><label for="info1">:</label></div>
                                                        <div class="col-xs-6">'.$student['serial_no'].'</div>
                                                    </div><br><br>
                                                    
                                                    <div claass="row">
                                                        <div class="col-xs-5"><label for="info3">Data</label></div>
                                                        <div class="col-xs-1"><label for="info3">:</label></div>
                                                        <div class="col-xs-6">'.$data_info.'</div>
                                                    </div><br><br>
                                               
                                                    <div claass="row">
                                                        <div class="col-xs-12">
                                                            <iframe src="'.$pathPDF.'#toolbar=0" width="810" height="780">
                                                            </iframe>
                                                        </div>
                                                    </div>
                                                </div>';
                                        $html .=  '</div></div>'; 

                                    }else{

                                        $html = '';
                                        $html .= '<div class="panel panel-info">';
                                        $html .= '<div class="panel-heading"><b>Document Information</b></div>';
                                        $html .= '<div class="panel-body">';
                                        $html .= '<div class="col-xs-6">
                                                    <div claass="row">
                                                        <div class="col-xs-5"><label for="info1">Document ID</label></div>
                                                        <div class="col-xs-1"><label for="info1">:</label></div>
                                                        <div class="col-xs-6">'.$student['serial_no'].'</div>
                                                    </div><br><br>
                                                    
                                                    
                                                    <div claass="row">
                                                        <div class="col-xs-6">
                                                            <iframe src="'.$pathPDF.'#toolbar=0" width="810" height="780">
                                                            </iframe>
                                                        </div>
                                                    </div>
                                                </div>';
                                        $html .=  '</div></div>'; 
                                    }         
                                        
                            }else{

                                $amount = $value['amount'];
                                $pg_name = strtolower($value['pg_name']);
                                
                                if(!isset($student['student_name']) || empty($student['student_name']) || $student['student_name'] == null){
                                    $student['student_name'] = 'Test Student';
                                }

                                if($pg_name == "paytm"){
                                    $png = "paytm_btn.png";
                                    $url = url('webapp/paytm/'.$key.'/'.$amount.'/'.$student['student_name']);
                                }
                                if($pg_name == "PayuBiz"){
                                    $png = "payu_btn.png";
                                    $url = url('webapp/payuGiz/'.$key.'/'.$amount.'/'.$student['student_name']);
                                }
                                if($pg_name == "PayUmoney"){

                                    $png = "PayU_btn.png";
                                    $url = url('webapp/payuGiz/'.$key.'/'.$amount.'/'.$student['student_name']);
                                }
                                if($pg_name == "instamojo"){

                                    $png = "instamojologo_btn.png";
                                    $url = url('webapp/instamojo/'.$key.'/'.$amount.'/'.$student['student_name']);
                                }

                                if($pg_name == "easypay"){

                                    $png = "easypay_icici_btn.png";
                                    $url = url('webapp/easypay/'.$key.'/'.$amount.'/'.$student['student_name']);
                                }

                                

                                $html .= '<div class="row"><div class="col-xs-12 text-center"><span class="alert alert-success" style="border-left:4px solid;border-right:4px solid;">Make Payment of <b><i class="fa fa-rupee">'.$amount.'</i></b> to view hidden data.</span><a href="'.$url.'" class="payment-url"><img src="'.\Config::get("constant.payment_image").'/'.$png.'" style="display:inline"></a></div></div><hr>';        
                            }
                        }

                         }else{
                            $certificate_filename=$student['certificate_filename'];
                            //$html .= '<h5>Data not found!</h5>';

                            if($subdomain[0]=="test"||$subdomain[0]=="demo"){
	                        	$pathPDF=str_replace("replace_serial_no",$student['serial_no'],$path);
	                    	}else{
	                    		$pathPDF=$path.'/'.$student["certificate_filename"];
	                    	}	
                             if($flag1 == 1){

                                        $html = '';
                                        $html .= '<div class="panel panel-info">';
                                        $html .= '<div class="panel-heading"><b>Document Information</b></div>';
                                        $html .= '<div class="panel-body">';
                                        $html .= '<div class="col-xs-6">
                                                    <div claass="row">
                                                        <div class="col-xs-5"><label for="info1">Document ID</label></div>
                                                        <div class="col-xs-1"><label for="info1">:</label></div>
                                                        <div class="col-xs-6">'.$student['serial_no'].'</div>
                                                    </div><br><br>
                                                    
                                                
                                                    <div claass="row">
                                                        <div class="col-xs-5"><label for="info3">Data</label></div>
                                                        <div class="col-xs-1"><label for="info3">:</label></div>
                                                        <div class="col-xs-6">'.$data_info.'</div>
                                                    </div><br><br>
                                               
                                                    <div claass="row">
                                                        <div class="col-xs-12">
                                                            <iframe src="'.$pathPDF.'#toolbar=0" width="810" height="780">
                                                            </iframe>
                                                        </div>
                                                    </div>
                                                </div>';
                                        $html .=  '</div></div>'; 

                                    }else{

                                        $html = '';
                                        $html .= '<div class="panel panel-info">';
                                        $html .= '<div class="panel-heading"><b>Document Information</b></div>';
                                        $html .= '<div class="panel-body">';
                                        $html .= '<div class="col-xs-6">
                                                    <div claass="row">
                                                        <div class="col-xs-5"><label for="info1">Document ID</label></div>
                                                        <div class="col-xs-1"><label for="info1">:</label></div>
                                                        <div class="col-xs-6">'.$student['serial_no'].'</div>
                                                    </div><br><br>
                                                    
                                                    <div claass="row">
                                                        <div class="col-xs-6">
                                                            <iframe src="'.$pathPDF.'#toolbar=0" width="810" height="780">
                                                            </iframe>
                                                        </div>
                                                    </div>
                                                </div>';
                                        $html .=  '</div></div>'; 
                                    }   
                        }
                        $html .= '</div></div>';
                               
                    }
                    

                    $message = $html;
                    if($update_scan == 0){
                        StudentTable::where('key', '=', $key)
                                    ->update(array('scan_count' => `scan_count`+1));
                    }
                }

            }else if($sb_student_count > 0){
                
                $student = SbStudentTable::where(['key'=>$key])
                ->orderBy('id', 'DESC')
                ->first();
                
                if($student['status'] == '0'){

                    $flag = 0;
                    $html = '';
                    $html .= '<div class="alert alert-danger">';
                    $html .= '<strong>Error</strong>';
                    $html .= 'You scanned a correct QR code';
                    $html .= '<i class="fa fa-qrcode fa-fw theme"></i>';
                    $html .= 'but the document is not active & valid any longer.';
                    $html .= 'Please <a href="'.route('webapp.dashboard').'">click here</a>';
                    $html .= 'to scan again.';
                    $html .= '</div>';

                    $message = $html;
                }else{


                    $transaction = SbTransactions::where(['student_key'=>$key,
                        'user_id'=>$user_id,
                        'publish'=>1,
                        'trans_status'=>1
                        ])->count();
                    if($transaction > 0){
                        $html = '';
                        $html .= '<div class="panel panel-info">';
                        $html .= '<div class="panel-heading"><b>Student Information</b></div>';
                        $html .= '<div class="panel-body">';
                        $html .= '<div class="col-xs-6">
                                    <div claass="row">
                                        <div class="col-xs-5"><label for="info1">Serial No.</label></div>
                                        <div class="col-xs-1"><label for="info1">:</label></div>
                                        <div class="col-xs-6">'.$student['serial_no'].'</div>
                                    </div>
                                    <div claass="row">
                                        <div class="col-xs-6">
                                            <iframe src="'.$path.'/'.$student["certificate_filename"].'#toolbar=0" width="810" height="780">
                                            </iframe>
                                        </div>
                                    </div>
                                </div>';
                        $html .=  '</div></div>';          
                                    

                    }else{


                        $flag = 1;
                        $selectColumns = ['payment_gateway.pg_name','payment_gateway_config.pg_id','payment_gateway_config.pg_status','payment_gateway_config.amount'];

                        $paymentList = PaymentGateway::select($selectColumns)
                            ->leftjoin('payment_gateway_config','payment_gateway_config.pg_id','payment_gateway.id')
                            ->where(['payment_gateway_config.pg_status'=>1,'payment_gateway.status'=>1,'payment_gateway.publish'=>1,'site_id'=>$site_id])
                            ->get()
                            ->toArray();
                        $student_compact = $student->toArray();
                        $html = '';
                        $html .= '<div class="panel panel-info">';
                        $html .= '<div class="panel-heading"><b>Student Information</b></div>';
                        $html .= '<div class="panel-body">';

                        foreach ($paymentList as $payment_key => $value) {
                            
                            $pg_id = $value['pg_id'];
                            
                            if($value['pg_status'] == 1){

                                $transaction_count = SbTransactions::where(['student_key'=>$key,'user_id'=>$user_id,'publish'=>1,'trans_status'=>1])->count();
                               
                                $payment_status = 'false';
                                
                                if($transaction_count > 0){
                                    $payment_status = 'true';
                                }
                            }

                            if($payment_status == 'true'){

                                $html .= '<div class="col-xs-6">
                                            <div class="row">
                                                <div class="col-xs-5"><label for="info1">Serial No.</label></div>
                                                <div class="col-xs-1"><label for="info1">:</label></div>
                                                <div class="col-xs-6">'.$student['serial_no'].'</div>
                                            </div>
                                            <div class="row">
                                                <div class="col-xs-5"><label for="info2">Student Name</label></div>
                                                <div class="col-xs-1"><label for="info2">:</label></div>
                                                <div class="col-xs-6">'.$student['student_name'].'</div>
                                            </div><hr>
                                            <div class="row">
                                                <div class="col-xs-12">
                                                    <iframe src="'.$path.'/'.$student['certificate_filaname'].'#toolbar=0" width="810" height="780">
                                                    </iframe>
                                                </div>    
                                            </div>
                                        </div>';         
                                        
                            }else{

                                $amount = $value['amount'];
                                $pg_name = strtolower($value['pg_name']);
                                if($student['student_name'] == null || $student['student_name'] == 'null'){
                                    $student['student_name'] = 'Test Student';
                                }
                                if($pg_name == "paytm"){
                                    $png = "paytm_btn.png";
                                    $url = url('webapp/paytm/'.$key.'/'.$amount.'/'.$student['student_name']);
                                }
                                if($pg_name == "payugiz"){
                                    $png = "payu_btn.png";
                                    $url = url('webapp/payuGiz/'.$key.'/'.$amount.'/'.$student['student_name']);
                                }
                                if($pg_name == "payumoney"){

                                    $png = "PayU_btn.png";
                                    $url = "payment-gateway/payuGiz";
                                }
                                if($pg_name == "instamojo"){

                                    $png = "instaMojoLogo.png";
                                    $url = url('webapp/instamojo/'.$key.'/'.$amount.'/'.$student['student_name']);
                                }

                                $html .= '<div class="row"><div class="col-xs-12 text-center"><span class="alert alert-success" style="border-left:4px solid;border-right:4px solid;">Make Payment of <b>'.$amount.'<i class="fa fa-rupee"></i></b> to view hidden data.</span><a href="'.$url.'" class="payment-url"><img src="'.\Config::get("constant.payment_image").'/'.$png.'" style="display:inline"></a></div></div><hr>';        
                            }
                        }

                        $html .= '</div></div>';
                             
                    }
                    $message = $html;
                    if($update_scan == 0){
                        SbStudentTable::where('key', '=', $key)
                                    ->update(array('scan_count' => `scan_count`+1));
                    }
                }
            }else{

                $flag = 2;
                $html = '';
                $html .= '<div class="panel panel-info"><div class="panel-heading"><b>Invalid QR</b></div><div class="panel-body">';
                $html .= '<div class="alert alert-danger">The QR code you scanned is not a Secured QR generated by this system. Kindly scan one of our Secured QR only.<a href="/webapp/dashboard"> Click here Scan Again</a></div></div></div>';
                $message = $html;
            }
            $datetime = date("Y-m-d H:i:s");
            $device_type = 'WebApp';
            $scanned_by = $user_id;
            $scan_result = $flag;

            if($update_scan == 0){

                $student_data = StudentTable::where(['key'=>$key])->first();
                
                $document_id = $student_data['serial_no'];
                $document_status = $student_data['status'];

                $scan_history = new ScannedHistory();
                $scan_history->date_time = $datetime;
                $scan_history->device_type = $device_type;
                $scan_history->scanned_data = $key;
                $scan_history->scan_by = $scanned_by;
                $scan_history->scan_result = $scan_result;
                $scan_history->document_id = $document_id;
                $scan_history->document_status = $document_status;
                $scan_history->site_id = $site_id;
                $scan_history->save();

                $dbName = 'seqr_demo';
        
                \DB::disconnect('mysql'); 
                
                \Config::set("database.connections.mysql", [
                    'driver'   => 'mysql',
                    'host'     => \Config::get('constant.SDB_HOST'),
                    'port' => \Config::get('constant.SDB_PORT'),
                    'database' => \Config::get('constant.SDB_NAME'),
                    'username' => \Config::get('constant.SDB_UN'),
                    'password' => \Config::get('constant.SDB_PW'),
                    "unix_socket" => "",
                    "charset" => "utf8mb4",
                    "collation" => "utf8mb4_unicode_ci",
                    "prefix" => "",
                    "prefix_indexes" => true,
                    "strict" => true,
                    "engine" => null,
                    "options" => []
                ]);
                \DB::reconnect();


                $scan_count = ScannedHistory::select('id')->where('site_id',$site_id)->get()->count();
                SiteDocuments::where('site_id',$site_id)->update(['total_scanned'=>$scan_count]);

                if($subdomain[0] == 'demo')
                {
                    $dbName = 'seqr_'.$subdomain[0];
                }else{

                    $dbName = 'seqr_d_'.$subdomain[0];
                }

                \DB::disconnect('mysql');     
                \Config::set("database.connections.mysql", [
                    'driver'   => 'mysql',
                    'host'     => \Config::get('constant.DB_HOST'),
                    "port" => \Config::get('constant.DB_PORT'),
                    'database' => $dbName,
                    'username' => \Config::get('constant.DB_UN'),
                    'password' => \Config::get('constant.DB_PW'),
                    "unix_socket" => "",
                    "charset" => "utf8mb4",
                    "collation" => "utf8mb4_unicode_ci",
                    "prefix" => "",
                    "prefix_indexes" => true,
                    "strict" => true,
                    "engine" => null,
                    "options" => []
                ]);
                \DB::reconnect();
                
            }

        }else{
            $message = 'Data params missing';
        }
        
        return $message;
    }
    public function paytm(Request $request){
        
        $key = $request->segment(3);
        $amount = $request->segment(4);
        $student_name = $request->segment(5);

        
        return view('webapp.payment.payTmPost',compact('key','amount','student_name'));
        
    }
    public function paymentPayTm(Request $request){
        
        $requestData  = $request->all();

        $amount = $requestData['amount'];
        $key = $requestData['key'];
        $payment = PaytmWallet::with('receive');

        $ORDER_ID = 'SeQR_PT_'.strtotime("now");
        $mobile_number = \Auth::guard('webuser')->user()->mobile_no;
        $user_id = \Auth::guard('webuser')->user()->id;

        $payment_key = Session::put('payment_key',$key);
        $payment->prepare([
          'order' => $ORDER_ID,
          'user' => $user_id,
          'mobile_number' => $mobile_number,
          'email' => 'rushik9994@gmail.com',
          'amount' => $amount,
          'callback_url' => url('webapp/payment-gateway/paytm/response')
        ]);
        
        return $payment->receive();
    }
    public function PayTmResponse(Request $request){
        
        $user_id = \Auth::guard('webuser')->user()->id;
        $transaction = PaytmWallet::with('receive');
        $session_key = Session::get('payment_key');
        $inputInfo = $transaction->response();
        return view('webapp.payment.payTmStatus',compact('inputInfo','session_key','user_id'));
    }
    public function PayUGiz(Request $request){
        

        $key = $request->segment(3);
        $amount = $request->segment(4);
        $student_name = $request->segment(5);
        
        return view('webapp.payment.payUgizPost',compact('key','amount','student_name'));
    }
    public function paymentPayUGiz(Request $request){

        $requestData = $request->all();
        $fullname = \Auth::guard('webuser')->user()->fullname;
        $email_id = \Auth::guard('webuser')->user()->email_id;
        $mobile_number = \Auth::guard('webuser')->user()->mobile_no;

        $payment_data = [
            'txnid' => 'SeQR_PU_'.strtotime("now"), # Transaction ID.
            'amount' => $requestData['amount'], # Amount to be charged.
            'firstname' => $fullname, # Payee Name.
            'email' => $email_id, # Payee Email Address.
            'phone' => $mobile_number, # Payee Phone Number.
            'productinfo' => $requestData['key'], 
            'surl' => url('webapp/payment-gateway/paytm/response'),
            'furl' => url('webapp/payment-gateway/paytm/response'), 
        ];
        return Payment::make($payment_data, function($then) {
            $then->redirectRoute('payubiz.paymentResponse');
        });
        
    }
    public function PayUGizResponse(Request $request){
        $payment = Payment::capture();
        $inputInfo =  $payment->getData();
        $user_id = \Auth::guard('webuser')->user()->id;
        
        return view('webapp.payment.payUbizStatus',compact('inputInfo','user_id'));
    }

    public function instaMojo(Request $request){
        
        $key = $request->segment(3);
        $amount = $request->segment(4);
        $student_name = $request->segment(5);
        
        return view('webapp.payment.instaMojoPost',compact('key','amount','student_name'));
    }

    public function easypay(Request $request){

        $key = $request->segment(3);
        $amount = $request->segment(4);
        $student_name = $request->segment(5);
        $user_id = \Auth::guard('webuser')->user()->id;

     
       

        $scan_data = ScannedHistory::where(['scanned_data'=>$key,'device_type'=>'WebApp'])
                ->orderBy('id', 'DESC')
                ->first();
        if($scan_data['id'] > 0){
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,"https://iitjmupg.seqrdoc.com/easypaywebapp.php");//http://seqronline.com/iitj/easypaywebapp.php
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,"key=".$key."&device_type='WebApp'&user_id=".$user_id."&scan_id=".$scan_data['id']);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $server_output = curl_exec($ch);

            curl_close ($ch);

            $data = json_decode($server_output, true);

            return redirect($data['URL']);
        }      
        
    }

    public function paymentinstaMojo(Request $request){
        
        $requestData  = $request->all();

        $amount = $requestData['amount'];
        $key = $requestData['key'];
        

        $ORDER_ID = 'SeQR_IM_'.strtotime("now");
        $mobile_number = \Auth::guard('webuser')->user()->mobile_no;
        $user_id = \Auth::guard('webuser')->user()->id;

        $payment_key = Session::put('payment_key',$key);


        $HTTP_HOST = $_SERVER['HTTP_HOST'];
        $PHP_SELF = $_SERVER['PHP_SELF'];

        if(array_key_exists('HTTPS', $_SERVER) && $_SERVER["HTTPS"] == "on"){
            $server_type = 'https';
        } else {
            $server_type = 'http';
        }

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $payment = DB::table('payment_gateway')
                    ->select('*')
                    ->join('payment_gateway_config', 'payment_gateway.id', '=', 'payment_gateway_config.pg_id')
                    ->where('payment_gateway.pg_name', '=', 'instaMojo')
                    ->get();

        $user = DB::table('user_table')
                    ->select('*')
                    ->where('user_table.id', '=', $user_id)
                    ->get();            

        $amount = $payment[0]->amount;
        $fullname = $user[0]->fullname;
        $email_id = $user[0]->email_id;
        $mobile_no = $user[0]->mobile_no;

        if($payment[0]->crendential)
        {
            $X_Api_Key = $payment[0]->merchant_key;
            $X_Auth_Token = $payment[0]->salt;
            $endpoint = 'https://www.instamojo.com/api/1.1/';
        }else{
            $X_Api_Key = $payment[0]->test_merchant_key;
            $X_Auth_Token = $payment[0]->test_salt;
            $endpoint = 'https://test.instamojo.com/api/1.1/payment-requests/';
        }           

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
                    array("X-Api-Key:".$X_Api_Key,
                          "X-Auth-Token:".$X_Auth_Token));

        $payload = Array(
            'purpose' => 'For Verification of CEDP Document',
            'amount' => $amount,
            'phone' => $mobile_no,
            'buyer_name' => $fullname,
            'redirect_url' => url('webapp/payment-gateway/instamojo/response'),
            'email' => $email_id,
            'allow_repeated_payments' => false
        );
        

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $response = curl_exec($ch);
        curl_close($ch); 
        $response = json_decode($response);

        return redirect($response->payment_request->longurl);
    }
    
    public function instaMojoResponse(Request $request){
        
        $user_id = \Auth::guard('webuser')->user()->id;
        
        $session_key = Session::get('payment_key');
        
        $payment = DB::table('payment_gateway')
                    ->select('*')
                    ->join('payment_gateway_config', 'payment_gateway.id', '=', 'payment_gateway_config.pg_id')
                    ->where('payment_gateway.pg_name', '=', 'instaMojo')
                    ->get();

        if($payment[0]->crendential)
        {
            $X_Api_Key = $payment[0]->merchant_key;
            $X_Auth_Token = $payment[0]->salt;
            $endpoint = 'https://www.instamojo.com/api/1.1/';
        }else{
            $X_Api_Key = $payment[0]->test_merchant_key;
            $X_Auth_Token = $payment[0]->test_salt;
            $endpoint = 'https://test.instamojo.com/api/1.1/payment-requests/';
        } 

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://test.instamojo.com/api/1.1/payments/'.$request->get('payment_id'));
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array("X-Api-Key:".$X_Api_Key,
                          "X-Auth-Token:".$X_Auth_Token));

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $response['status'] = 'failed';

            $ORDER_ID = 'SeQR_IM_'.strtotime("now");
            $payment_mode = 'INMJ';

            $response['orderid'] = $ORDER_ID;
            $response['payment_mode'] = $payment_mode;

        } else {
            $inputInfo = json_decode($response,true);
            $response = $inputInfo['payment'];

            $ORDER_ID = 'SeQR_IM_'.strtotime("now");
            $payment_mode = 'INMJ';

            $response['orderid'] = $ORDER_ID;
            $response['payment_mode'] = $payment_mode;

        }

        return view('webapp.payment.instaMojoStatus',compact('response','session_key','user_id'));
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

}


