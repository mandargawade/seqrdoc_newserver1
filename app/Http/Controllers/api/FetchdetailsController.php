<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SuperApiTracker;
use App\Models\SuperSessionManager;
use App\Models\StudentTable;
use App\Models\PrintingDetail;
use App\Models\SbPrintingDetail;
use App\Models\SystemConfig;
use App\Models\SbStudentTable;
use App\Utility\SuperApiSecurityLayer;

use DB,Event;

class FetchdetailsController extends Controller
{
	function sanitizeVar($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return $data;
	}

    public function fetchdetail(Request $request)
    {
    	

		$data = $request->post();
		

        if (SuperApiSecurityLayer::checkAuthorization())
        {   
            // to fetch user id
            
            $type = $this->sanitizeVar($request->type);
            if(!empty($type))
            {
	            $user_id = $this->sanitizeVar($request->user_id);
	            $key = $this->sanitizeVar($request->key);

	            if (!empty($key)) {
		            if (!empty($user_id) && SuperApiSecurityLayer::checkAccessToken($user_id))
		            {
		                switch($type) 
		            	{
		        	        case 'qr_code':
		        	        		$message = $this->qr_code($data);
		        	            	
		        	            break;

		        	        case 'one_d_barcode':
		        	            	$message = $this->one_d_barcode($data);
		        	            break;   

		        	        default:
		        	        	$message = ['status'=>404,'message'=>'Request not found.']; 
		               	}
		            }else{
		            	$message = array('status'=>403, 'message' => 'User id is missing or You dont have access to this api.');
		            }
		        }else{
		        	$message = array('status'=>422, 'message' => 'Required parameters not found.');
		        }
	        }else{

	        	$message = array('status'=>405, 'message' => 'Request method not accepted.');
	        }
        }
        else
        {
            $message = array('status'=>403, 'message' => 'Access forbidden.');
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

       $api_tracker_id = SuperApiSecurityLayer::insertTracker($requestUrl,$request->type,$requestMethod,$requestParameter,$message,$status);
  
       $response_time = microtime(true) - LARAVEL_START;
        SuperApiTracker::where('id',$api_tracker_id)->update(['response_time'=>$response_time]);
        return $message;
    }

    

	function qr_code($data){
		if (SuperApiSecurityLayer::checkAuthorization()) {
			$instance_domain = '';
			$user_id = $data['user_id'];
			$key = $data['key'];
			$instance = $data['instance'];

			$config_data = SystemConfig::first()->toArray();
			
			if (!empty($user_id) && SuperApiSecurityLayer::checkAccessToken($user_id)) {
				if (!empty($key)) {
						$domain = \Request::getHost();
        				$subdomain = explode('.', $domain);
        				$HTTP_HOST = $_SERVER['HTTP_HOST'];
						$PHP_SELF = $_SERVER['PHP_SELF'];
        				if(array_key_exists('HTTPS', $_SERVER) && $_SERVER["HTTPS"] == "on"){
							$server_type = 'https';
						} else {
							$server_type = 'http';
						}

						if(!empty($instance)){
							$instance_domain = strtoupper($instance);
						}else{
							$instance_domain = strtoupper($subdomain[0]);
						}

						switch ($instance_domain) {
							case 'IITJMU':
									if($config_data['sandboxing'])
									{
										$result = 0;
									}else{
										$result = StudentTable::where('key',$key)->where('publish','1')->orderBy('id','DESC')->get()->toArray();
									}
								break;
							case 'IIM':
									$result=DB::select(DB::raw('SELECT DocNo FROM `seqr_d_apponly`.`indira_iim` WHERE QRText ="'.$key.'"'));
									
								break;
							case 'ISBS':
									$result = DB::select(DB::raw('SELECT DocNo FROM `seqr_d_apponly`.`indira_isbs` WHERE QRText ="'.$key.'"'));
								break;	
							default:
									if($config_data['sandboxing'])
									{
										$result = SbStudentTable::where('key',$key)->where('publish','1')->orderBy('id','DESC')->get()->toArray();
									}else{
										$result = StudentTable::where('key',$key)->where('publish','1')->orderBy('id','DESC')->get()->toArray();
									}
								break;
						}

						if (!empty($result)) {
							$final_certificate_path = '';
							switch ($instance_domain) {
								case 'IITJMU':
										$certificateFilename = json_decode($this->CallAPI('POST','https://eg.iitjammu.ac.in/do_open.php',$post_data = array("dtype" => "gettranscriptpath",  "key" => $key)),'true');
										$final_certificate_path = $certificateFilename['Path'];
										$certificateData['serialNo'] = $result[0]['serial_no'];
										$certificateData['fileUrl'] = $final_certificate_path;
										$certificateData['status'] = $result[0]['status'];
										$certificateData['key'] = $key;
									break;
								case 'IIM':
										$DocNo=$result[0]->DocNo;
	            	 					$final_certificate_path = 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/pdf_file/IIMP/'.$DocNo.'.pdf';
	            	 					$certificateData['serialNo'] = '';
	            	 					$certificateData['fileUrl'] = $final_certificate_path;
	            	 					$certificateData['status'] = '';
	            	 					$certificateData['key'] = $key;
									break;
								case 'ISBS':
										$DocNo=$result[0]->DocNo;
	            	 					$final_certificate_path = 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/pdf_file/ISBS/'.$DocNo.'.pdf';
	            	 					$certificateData['serialNo'] = '';
	            	 					$certificateData['fileUrl'] = $final_certificate_path;
	            	 					$certificateData['status'] = '';
	            	 					$certificateData['key'] = $key;
										break;
								default:
										if($config_data['sandboxing'])
										{
											$pdf_url = $server_type."://".$HTTP_HOST.str_replace('api/fetchdetail', '', dirname($PHP_SELF)) .$subdomain[0]."/backend/pdf_file/sandbox/";
										}else{
											$pdf_url = $server_type."://".$HTTP_HOST.str_replace('api/fetchdetail', '', dirname($PHP_SELF)) .$subdomain[0]."/backend/pdf_file/";
										}

										$final_certificate_path = $pdf_url . $result[0]['certificate_filename'];
										$certificateData['serialNo'] = $result[0]['serial_no'];
										$certificateData['fileUrl'] = $final_certificate_path;
										$certificateData['status'] = $result[0]['status'];
										$certificateData['key'] = $key;
									break;
							}
							
							$message = array('status' => 200, 'message' => "success", "certificateData" => $certificateData);
						} else {
							$message = array('status' => 500, 'message' => "Certificate not found.");
						}
				} else {
					$message = array('status' => 422, 'message' => 'Required parameters not found.');
				}

			} else {
				$message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');

			}

		} else {
			$message = array('status' => 403, 'message' => 'Access forbidden.');

		}

		return $message;
    }

    function one_d_barcode($data){
    	if (SuperApiSecurityLayer::checkAuthorization()) {
			$user_id = $data['user_id'];
			$key = $data['key'];
			$config_data = SystemConfig::first()->toArray();
			if (!empty($user_id) && SuperApiSecurityLayer::checkAccessToken($user_id)) {
				if (!empty($key)) {
					if($config_data['sandboxing'])
					{
						$result = SbPrintingDetail::where('print_serial_no',$key)->where('publish','1')->get()->toArray();
					}else{
						$result = PrintingDetail::where('print_serial_no',$key)->where('publish','1')->get()->toArray();
					}
						if (!empty($result)) {
							$certificateData['serialNo'] = $result[0]['sr_no'];
							$certificateData['userPrinted'] = $result[0]['username'];
							$certificateData['printingDateTime'] = $result[0]['print_datetime'];
							$certificateData['printerUsed'] = $result[0]['printer_name'];
							$certificateData['printCount'] = $result[0]['print_count'];
							$certificateData['status'] = $result[0]['status'];
							$certificateData['key'] = $key;
							
							$message = array('status' => 200, 'message' => "success", "certificateData" => $certificateData);
						} else {
							$message = array('status' => 500, 'message' => "Certificate not found.");
						}
				} else {
					$message = array('status' => 422, 'message' => 'Required parameters not found.');
				}

			} else {
				$message = array('status' => 403, 'message' => 'User id is missing or You dont have access to this api.');

			}

		} else {
			$message = array('status' => 403, 'message' => 'Access forbidden.');

		}
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


}
