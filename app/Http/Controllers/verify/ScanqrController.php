<?php

namespace App\Http\Controllers\verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\StudentTable;
use App\models\Transactions;
use App\models\TemplateMaster;
use App\models\ScannedHistory;
use App\models\raisoni\ScanningRequests;
use App\models\PaymentGateway;
use App\models\raisoni\ScannedDocuments;
use App\models\raisoni\DocumentRateMaster;
use App\models\SystemConfig;
use App\models\SiteDocuments;
use Illuminate\Support\Facades\DB;
use Auth;


class ScanqrController extends Controller
{
    //
    public function index(){

    	
    	
    	return view('verify.home');
    }

    //view dashboard
    public function dashboard(){
    	$domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        if($subdomain[0]=="monad"){
        	return view('verify.dashboard_monad');
        }else{
        	return view('verify.dashboard');
        }
    	
    }

    //scan qr
    public function scandata(Request $request){

    	$site_id=Auth::guard('webuser')->user()->site_id;
    	$user_id=Auth::guard('webuser')->user()->id;


        $get_file_aws_local_flag = SystemConfig::select('file_aws_local')->where('site_id',$site_id)->first();
        
        $systemConfig = SystemConfig::where('site_id',$site_id)->first()->toArray();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

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
                $path = \Config::get('constant.local_base_path').$subdomain[0].'/backend/pdf_file';
            }
        }
    	
    	$scan_action = $request->action;
    	$update_scan = $request->update_scan;
		

    	
						
		if($subdomain[0] == "monad"){
			$rates = DocumentRateMaster::get()->toArray();
			$degree_price = $rates[0]['amount_per_document'];
			$marksheet_price = $rates[1]['amount_per_document'];
		}

    	if($scan_action == 'scanData'){

    		$flag = 0;
    	
    		if($request->action != null){

    			$user_id = 38;
    			$key = $request->key;
    			

    			$gotData = $student = StudentTable::where(['key'=>$key,'publish'=>1])
                ->orderBy('id', 'DESC')
                ->first();
               
                try{

                	if(!empty($gotData)){

                		if($gotData['status']==	'0'){

                			$flag = 0;
							$html = '';
							
							$html .= '<div class="alert alert-danger"><strong>Unho!</strong> You scanned a correct QR code <i class="fa fa-qrcode fa-fw theme"></i>, but the document is not active & valid any longer. Please <a href="'.route('verify.home').'">click here</a> to scan again.</div>';
							$message = $html;
                		}else{

                			$result_T = Transactions::where(['student_key'=>$key,
	                        'user_id'=>$user_id,
	                        'publish'=>1,
	                        'trans_status'=>1
	                        ])->count();
	                        
	                        if($result_T >= 1){

	                        	$html = '';
								$html .= '<div class="panel panel-info"><div class="panel-heading"><b>Student information</b></div><div class="panel-body">';
								$html .= '<div class="col-xs-6">
											  	<div class="row">
													<div class="col-xs-5"><label for="info1">Serial No.</label></div>
													<div class="col-xs-1"><label for="info1">:</label></div>
													<div class="col-xs-6">' . $gotData['serial_no'] . '</div>
												</div>
												<div class="row">
													<div class="col-xs-5"><label for="info2">Student Name</label></div>
													<div class="col-xs-1"><label for="info2">:</label></div>
													<div class="col-xs-6">' . $gotData['student_name'] . '</div>
												</div>
												<div class="row">
													<div class="col-xs-5"><label for="info3">Certificate Filename</label></div>
													<div class="col-xs-1"><label for="info3">:</label></div>
													<div class="col-xs-6"><<a target="_blank" href="'.$path.'/'.$gotData["certificate_filename   "].'">'.$gotData['certificate_filename'].'</a></li></div>
												</div><hr>
												<div class="row">
													<div class="col-xs-12">
														<iframe src="'.$path.'/'.$gotData["certificate_filename"].'" width="810" height="780">
                                            			</iframe>
													</div>
												</div>
											</div>';
								$html .= '</div></div>';
								$message = $html;
	                        }else{

	                        	$flag = 1;
	                        	$selectColumns = ['payment_gateway.pg_name','payment_gateway_config.pg_id','payment_gateway_config.pg_status','payment_gateway_config.amount'];

		                        $res = PaymentGateway::select($selectColumns)
		                            ->leftjoin('payment_gateway_config','payment_gateway_config.pg_id','payment_gateway.id')
		                            ->where(['payment_gateway_config.pg_status'=>1,'payment_gateway.status'=>1,'payment_gateway.publish'=>1,'site_id'=>$site_id])
		                            ->get()
		                            ->toArray(); 

		                        $html = '';
								$html .= '<div class="panel panel-info"><div class="panel-heading"><b>Student information</b></div><div class="panel-body">';

								foreach($res as $key_r => $value_r){

									$pg_id = $value_r['pg_id'];

									if($value_r['pg_status'] ==1 ){

										$result_p = Transactions::where(['student_key'=>$key,'user_id'=>$user_id,'publish'=>1,'trans_status'=>1])->count();

										if($result_p > 1){

											$payment_status = "true";
										}else{

											$payment_status = "false";
										}

									}

									if($payment_status == "true"){

										$html .= '<div class="col-xs-6">
										  	<div class="row">
												<div class="col-xs-5"><label for="info1">Serial No.</label></div>
												<div class="col-xs-1"><label for="info1">:</label></div>
												<div class="col-xs-6">' . $gotData['serial_no'] . '</div>
											</div>
											<div class="row">
												<div class="col-xs-5"><label for="info2">Student Name</label></div>
												<div class="col-xs-1"><label for="info2">:</label></div>
												<div class="col-xs-6">' . $gotData['student_name'] . '</div>
											</div>
											<div class="row">
												<div class="col-xs-5"><label for="info3">Certificate Filename</label></div>
												<div class="col-xs-1"><label for="info3">:</label></div>
												<div class="col-xs-6"><<a target="_blank" href="'.$path.'/'.$gotData["certificate_filename   "].'">'.$gotData['certificate_filename'].'</a></li></div>
											</div><hr>
											<div class="row">
												<div class="col-xs-12">
													<!--iframe src="http://docs.google.com/gview?url=http://scube.net.in/seqr_doc/ku/pdf_file/' . $gotData['certificate_filename'] . '&embedded=true" width="1000" height="780"></iframe-->
													<iframe src="'.$path.'/'.$gotData["certificate_filename"].'" width="810" height="780">
                                            		</iframe>
												</div>
											</div>
										</div>';

									}else{

										$amount = $value_r['amount'];
										$pg_name = strtolower($value_r['pg_name']);

										if ($pg_name == "paytm") {
											$png = "Paytm_btn.png";
											$url = url('verify/paytm/'.$key.'/'.$amount.'/'.$gotData['student_name']);
										}

										if ($pg_name == "payumoney") {
											$png = "PayU_btn.png";
										}

										if ($pg_name == "solutechpay") {
											$png = "solutechpay_btn.png";
										}

										if ($pg_name == "payubiz") {
											$png = "payubiz_new.png";
										}

										$html .= '<div class="row"><div class="col-xs-12 text-center"><span class="alert alert-success" style="border-left: 4px solid; border-right: 4px solid;">Make payment of <b>' . $amount . ' <i class="fa fa-rupee"></i></b> to view hidden data.</span>
											<a href="'.$url.'" class="payment-url">
											<img src="'.\Config::get("constant.payment_image").'/'.$png.'" style="display:inline"></a></div></div><hr>';
									}
								}

								$html .= '</div></div>';
	                        }
	                        $message = $html;
	                        if($request->update_scan == 0){
		                        $result_u = StudentTable::where('key', '=', $key)
		                                    ->update(array('scan_count' => `scan_count`+1));
		                    }
                		}
                	}else{

		                $flag = 2;
						$html = '';
						$html .= '<div class="panel panel-info"><div class="panel-heading"><b>Invalid QR</b></div><div class="panel-body">';
						$html .= '<div class="alert alert-danger">The QR code you scanned is not a Secured QR generated by this system. Kindly scan one of our Secured QR only.<a href="'.route('verify.home').'"> Click here Scan Again</a></div></div></div>';
						$message = $html;
                	}

                }catch (Exception $e){

                	$message = $e->getMessage;
					$flag = 1;
                }

                $datetime = date("Y-m-d H:i:s");
	            $device_type = 'Verify';
	            $scanned_by = $user_id;
	            $scan_result = $flag;

	            if($update_scan == 0){

	                $scan_history = new ScannedHistory();
	                $scan_history->date_time = $datetime;
	                $scan_history->device_type = $device_type;
	                $scan_history->scanned_data = $key;
	                $scan_history->scan_by = $scanned_by;
	                $scan_history->scan_result = $scan_result;
	                
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


	                $scan_count = ScannedHistory::select('id')->where('site_id',$site_id)->count();
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
        }elseif($scan_action == 'scanDataMultiple'){

        	$flag = 0;

        	if($request->key != null && !empty($request->key)){
        		
        		$key = $request->key;


        		$gotData = StudentTable::where(['key'=>$key,'publish'=>1])
                ->orderBy('id', 'DESC')
                ->first();

                try{

                	if(!empty($gotData)){

                		if($gotData['status'] == '0'){

                			return response()->json(['status' => false,'message' => 'Unho! You scanned a correct QR code, but the document is not active & valid any longer.','code' => 404]);
							
							$flag = 0;
                		}else{
                			
                			$flag = 1;

                			$result_T = Transactions::where(['student_key'=>$key,
	                        'user_id'=>$user_id,
	                        'publish'=>1,
	                        'trans_status'=>1
	                        ])->count();

                			$result_T2 = DB::table('scanned_documents')
	            							   ->select('*')
	            							   ->join('scanning_requests','scanning_requests.id','=','scanned_documents.request_id')
	            							   ->where('scanned_documents.document_key',$key)
	            							   ->where('scanning_requests.user_id',$user_id)
	            							   ->where('scanning_requests.payment_status','Paid')->count();
	                       

	                        try{

	                        	if($result_T >= 1 || $result_T2 >=1 ){

	                        		$already_paid = 1;		
	                        		
	                        	}else{

	                        		$gotDataTemplate = TemplateMaster::where(['id'=>$gotData->template_id,'status'=>1])->first();
	                        		
	                        		try{

	                        			if(!empty($gotData) && $subdomain[0]=="monad"){
	                        				if($gotData->certificate_type=="Marksheet"){
	                        					$templateFee=$marksheet_price;
	                        				}else{
	                        					$templateFee=$degree_price;
	                        				}
	                        				return response()->json(['status' => true,'message' => 'You scanned correct QR code.', "alreadyPaid" => 0, "key" => $key, "amount" => $templateFee]);
	                        			}else{
		                        			if(!empty($gotData) && !empty($gotDataTemplate['scanning_fee'])){
		                        				$already_paid = 0;

		                        				return response()->json(['status' => true,'message' => 'You scanned correct QR code.', "alreadyPaid" => 0, "key" => $key, "amount" => $gotDataTemplate['scanning_fee']]);
		                        				
											}else{

												return response()->json(['status'=>false,'message' => 'Something went wrong check template.']);

												
											}
										}

	                        		}catch(Exception $e){

	                        			return response()->json(['status'=>false,'message'=>$e->getMessage]);
	                        			
										$flag = 1;
                					}
	                        	}

	                        }catch(Exception $e){

	                        	return response()->json(['status'=>false,'message'=>$e->getMessage]);
								$flag = 1;
                			}

                		}
                	}else{

                		$flag = 2;
                		return response()->json(['status'=>false,'message'=>'You scanned wrong QR code.']);
						
                	}

                	$datetime = date("Y-m-d H:i:s");
		            $device_type = 'Verify';
		            $scanned_by = $user_id;
		            $scan_result = $flag;

		            $scan_history = new ScannedHistory();
	                $scan_history->date_time = $datetime;
	                $scan_history->device_type = $device_type;
	                $scan_history->scanned_data = $key;
	                $scan_history->scan_by = $scanned_by;
	                $scan_history->scan_result = $scan_result;
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


	                $scan_count = ScannedHistory::select('id')->where('site_id',$site_id)->count();
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
	                if($already_paid == 1){

	                	return response()->json(['status'=> true,'message' => 'You scanned correct QR code which is already paid.', 'alreadyPaid' => 1, 'key' => $key]);
	                }else{

	                	return response()->json(['status' => true,'message' => 'You scanned correct QR code.', "alreadyPaid" => 0, "key" => $key, "amount" => $gotDataTemplate['scanning_fee']]);

	                }
	                

                }catch(Exception $e){
                	return response()->json(['status'=>false,'message'=> $e->getMessage]);
                	
					$flag = 1;
                }
        	}else{
        		return response()->json(['status'=>false,'message'=> 'Required fields not found.']);
        		
        	}
        	
        }elseif($scan_action == 'saveRequest'){

        	$total_files_count = $request->total_files_count;
        	$totalAmount = $request->totalAmount;
        	$qrCodes = explode(',',$request->qrCodes); 	

        	if (!empty($total_files_count) && !empty($qrCodes)){

	        	$row = ScanningRequests::select('request_number')
	        			->orderBy('id','desc')->first();
	        	
	        	if(!empty($row['request_number'])){
	        		
	        		$theRest = substr($row['request_number'], 8);
					$request_number = "NQRCV-R-" . ($theRest + 1);
	        	}else{

	        		$request_number = "NQRCV-R-1";
	        	}

	        	$created_date_time = date('Y-m-d H:i:s');
				$totalAmountBackend = 0;
				$totalFilesBackend = 0;				

				$scan_request = new ScanningRequests();
	            $scan_request->request_number = $request_number;
	            $scan_request->user_id = $user_id;
	            $scan_request->no_of_documents = $total_files_count;
	            $scan_request->total_amount = $totalAmount;
	            $scan_request->device_type = 'web';
	            $scan_request->created_date_time = $created_date_time;
	            $scan_request->save();

	            try{

	            	$last_id = $scan_request->id;
	            	if(!empty($last_id)){

	            		$error = false;
	            		foreach ($qrCodes as $key) {

	            			$amount = 0;
	            			$gotData = StudentTable::where(['key'=>$key,'publish'=>1])
			                ->orderBy('id', 'DESC')
			                ->first();

			                try{

			                	if(!empty($gotData)){
				                	if($gotData['status'] == '0'){

				                		$errorMsg = 'Unho! You scanned a correct QR code, but the document is not active & valid any longer.';
										$error = true;
				                	}else{
				                		
				                		$result_T = Transactions::where(['student_key'=>$key,
				                        'user_id'=>$user_id,
				                        'publish'=>1,
				                        'trans_status'=>1
				                        ])->count();

				                        $result_T2 = DB::table('scanned_documents')
	            							   ->select('*')
	            							   ->join('scanning_requests','scanning_requests.id','=','scanned_documents.request_id')
	            							   ->where('scanned_documents.document_key',$key)
	            							   ->where('scanning_requests.user_id',$user_id)
	            							   ->where('scanning_requests.payment_status','Paid')->count();
	            							   
				                        try{

				                        	if($result_T >= 1 || $result_T2 >=1){

												$already_paid = 1;
												$amount = 0;
				                        	}else{

				                        		$already_paid = 0;
				                        		$gotDataTemplate = TemplateMaster::where(['id'=>$gotData->template_id,'status'=>1])->first();
				                        		
				                        		try{
				                        			if(!empty($gotData) && $subdomain[0]=="monad"){
				                        				if($gotData->certificate_type=="Marksheet"){
				                        					$amount =$marksheet_price;
				                        				}else{
				                        					$amount =$degree_price;
				                        				}
				                        				
				                        			}else{
					                        			if(!empty($gotData) && !empty($gotDataTemplate['scanning_fee'])){

					                        				$amount = $gotDataTemplate['scanning_fee'];
					                        		
					                        				
					                        			}else{

					                        				$errorMsg = 'Something went wrong check template.';
															$error = true;
					                        			}
				                        			}
				                        		}catch(Exception $e){

				                        			$errorMsg = $e->getMessage;
													$error = true;
				                        		}
				                        	}
				                        }catch(Exception $e){

				                        	$errorMsg = $e->getMessage;
											$error = true;
				                        }
				                	}
				                }else{

				                	$error = true;
									$errorMsg = 'You scanned wrong QR code.';
				                }
			                }catch(Exception $e){

			                	$errorMsg = $e->getMessage;
								$error = true;
			                }

			                if ($error) {

			                	break;
			                }else{


			                	try{

			                		$result = new ScannedDocuments();
			                		$result->request_id = $last_id;
			                		$result->document_key = $key;
			                		$result->amount = $amount;
			                		$result->already_paid = $already_paid;
			                		$result->save();
			                	}catch(Exception $e){

			                		$errorMsg = $e->getMessage();
									$error = true;
			                	}
			                }
			                $totalAmountBackend = $totalAmountBackend + $amount;
							$totalFilesBackend++;
							
	            		}
	            		
	            		if ($totalAmountBackend == $totalAmount && $total_files_count == $totalFilesBackend) {

	            			if ($totalAmount == 0) {

	            				$showPdf = true;
	            				$dataPdf = DB::table('student_table')
	            							   ->select('certificate_filename')
	            							   ->join('scanned_documents','scanned_documents.document_key','=','student_table.key')
	            							   ->where('scanned_documents.request_id',$last_id)->get();
	            			
	            			}else {	
								$showPdf = false;
								$dataPdf = array();
							}
							return response()->json(['status' => true, 'message' => 'Request submitted successfully.', "request_number" => $request_number, "amount" => $totalAmount, 'showPdf' => $showPdf, 'dataPdf' => $dataPdf]);
	            		}else{

	            			return response()->json(['status' => false, 'message' => 'Something went wrong.']);
	            		}
	            	}else{
	            		return response()->json(['status' => false, 'message' => 'Something went wrong.']);
	            	}

	            }catch(Exception $e){

	            	$errorMsg = $e->getMessage();
					
					return response()->json(['status'=>false,'message'=>$errorMsg]);
	            }
	        }else{

	        	return response()->json(['status'=>false,'message'=>'Required fields not found.']);
	        	
	        }
            
	       

   		}else if($scan_action == 'saveRequestMonad'){


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
	        
   			$total_files_count = $request->total_files_count;
        	$totalAmount = $request->totalAmount;
        	$verification_type = $request->verification_type;
        	$data_type = $request->data_type;
        	$qrCodes = explode(',',$request->qrCodes); 	

        	if (!empty($total_files_count) && !empty($qrCodes)){

	        	$row = ScanningRequests::select('request_number')
	        			->orderBy('id','desc')->first();
	        	
	        	if(!empty($row['request_number'])){
	        		
	        		$theRest = substr($row['request_number'], 8);
					$request_number = "NQRCV-R-" . ($theRest + 1);
	        	}else{

	        		$request_number = "NQRCV-R-1";
	        	}

	        	$created_date_time = date('Y-m-d H:i:s');
				$totalAmountBackend = 0;
				$totalFilesBackend = 0;				

				$scan_request = new ScanningRequests();
	            $scan_request->request_number = $request_number;
	            $scan_request->user_id = $user_id;
	            $scan_request->no_of_documents = $total_files_count;
	            $scan_request->total_amount = $totalAmount;
	            $scan_request->device_type = 'web';
	            $scan_request->created_date_time = $created_date_time;
	            $scan_request->verification_type = $verification_type;
	            $scan_request->data_type = $data_type;
	            $scan_request->save();

	            try{

	            	$last_id = $scan_request->id;
	            	if(!empty($last_id)){

	            		 if($verification_type=="marksheet"){
                            $amount=$marksheet_price;
                        }else{
                            $amount=$degree_price;
                        }
	            		foreach ($qrCodes as $key) {

	            			
	            			if($data_type=="qr"){
                                        $result = StudentTable::where('key',$key)->where('publish',1)->where('status',1)->where('certificate_type',$verification_type)->orderBy('id','DESC')->first();
                                        if (!empty($result)) {
                                           
                                            $isValid=1;
                                            $respMsg = 'Valid document.';
                                        }else{
                                            
                                            $isValid=0;
                                            $respMsg = 'Invalid document.';
                                        }
                                    }else{
                                        $result = StudentTable::where('serial_no',$key)->where('publish',1)->where('status',1)->where('certificate_type',$verification_type)->orderBy('id','DESC')->first();    
                                        if (!empty($result)) {
                                            
                                            $isValid=1;
                                            $respMsg = 'Valid document.';
                                        }else{
                                        	
                                            $isValid=0;
                                            $respMsg = 'Invalid document.';
                                        }
                                    }
                                    

                                    $scanned_documents = new ScannedDocuments;
                                    $scanned_documents->request_id = $last_id;
                                    $scanned_documents->document_key = $key;
                                    $scanned_documents->amount = $amount;
                                    $scanned_documents->already_paid = 0;
                                    $scanned_documents->is_valid = $isValid;
                                    $scanned_documents->save();  

			                $totalAmountBackend = $totalAmountBackend + $amount;
							$totalFilesBackend++;
							
	            		}
	            		
	            		if ($totalAmountBackend == $totalAmount && $total_files_count == $totalFilesBackend) {

	            			
								$showPdf = false;
								$dataPdf = array();
							
							return response()->json(['status' => true, 'message' => 'Request submitted successfully.', "request_number" => $request_number, "amount" => $totalAmount, 'showPdf' => $showPdf, 'dataPdf' => $dataPdf]);
	            		}else{

	            			return response()->json(['status' => false, 'message' => 'Something went wrong.']);
	            		}
	            	}else{
	            		return response()->json(['status' => false, 'message' => 'Something went wrong.']);
	            	}

	            }catch(Exception $e){

	            	$errorMsg = $e->getMessage();
					
					return response()->json(['status'=>false,'message'=>$errorMsg]);
	            }
	        }else{

	        	return response()->json(['status'=>false,'message'=>'Required fields not found.']);
	        	
	        }
            
	       

   		}
    
	}

	
}
