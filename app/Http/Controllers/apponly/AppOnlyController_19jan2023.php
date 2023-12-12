<?php

namespace App\Http\Controllers\apponly;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use DB,Event;

//use Illuminate\Encryption\Encrypter;
class AppOnlyController extends Controller
{
   
   
    public function getIIMCertificates(Request $request){

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
      
        $apiKeyStatic="|M_Fu#t)Q}rH0)r/U<4t3FR3.OrtL&";
        $checkAuthorization = false;
		
		$headers = apache_request_headers();
		
		if (isset($headers['apikey'])) {
			if (strcasecmp($headers['apikey'], $apiKeyStatic) == 0) {
				$checkAuthorization = true;
			}

		}
		if (isset($headers['Apikey'])) {

			if (strcasecmp($headers['Apikey'], $apiKeyStatic) == 0) {
				$checkAuthorization = true;
			}
		}

        if ($checkAuthorization){
            

            $rules = [
                'qr_text' => 'required'
            ];

            $messages = [
                'qr_text.required' => 'Please enter qr text.',
            ];

            $validator = \Validator::make($request->all(),$rules,$messages);

            if ($validator->fails()) {
               
                  $message = array('status' => 400, 'message' => ApiSecurityLayerThirdParty::getMessage($validator->errors()));
                  return response($message);
            }else{
            
            $qr_text = $request['qr_text'];
           
            if (!empty($qr_text)) 
            {
                //get pdf path `seqr_d_apponly`.
                $certificateData=DB::select(DB::raw('SELECT DocNo FROM `seqr_d_apponly`.`indira_iim` WHERE QRText ="'.$qr_text.'"'));
                if($certificateData){

            	$DocNo=$certificateData[0]->DocNo;

            	 $pdfPath = 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/pdf_file/IIMP/'.$DocNo.'.pdf';
                 $message = array('status' => 200, 'message' => 'success','documentId'=>$DocNo,'pdfPath'=>$pdfPath);
                }else{
                $message = array('status' => 400, 'message' => 'Certificate not found in our system.');	
                }
                 return response($message);  
            }else
            {
                $message = array('status' => 400, 'message' => 'QR text is empty.');
                return response($message);
            }
        }
        }else
        {
            $message = array('status' => 403, 'message' => 'Access forbidden.');
            return response($message);
        }
       
    }


    
    public function getISBSCertificates(Request $request){

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);


        $apiKeyStatic="|M_Fu#t)Q}rH0)r/U<4t3FR3.OrtL&";
        $checkAuthorization = false;
		
		$headers = apache_request_headers();
		
		if (isset($headers['apikey'])) {
			if (strcasecmp($headers['apikey'], $apiKeyStatic) == 0) {
				$checkAuthorization = true;
			}

		}
		if (isset($headers['Apikey'])) {

			if (strcasecmp($headers['Apikey'], $apiKeyStatic) == 0) {
				$checkAuthorization = true;
			}
		}

        if ($checkAuthorization){
            

            $rules = [
                'qr_text' => 'required'
            ];

            $messages = [
                'qr_text.required' => 'Please enter qr text.',
            ];

            $validator = \Validator::make($request->all(),$rules,$messages);

            if ($validator->fails()) {
               
                  $message = array('status' => 400, 'message' => ApiSecurityLayerThirdParty::getMessage($validator->errors()));
                  return response($message);
            }else{
            
            $qr_text = $request['qr_text'];
           
            if (!empty($qr_text)) 
            {
                //get pdf path `seqr_d_apponly`.
                $certificateData=DB::select(DB::raw('SELECT DocNo FROM `seqr_d_apponly`.`indira_isbs` WHERE QRText ="'.$qr_text.'"'));
                if($certificateData){

            	$DocNo=$certificateData[0]->DocNo;

            	 $pdfPath = 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/pdf_file/ISBS/'.$DocNo.'.pdf';
                 $message = array('status' => 200, 'message' => 'success','documentId'=>$DocNo,'pdfPath'=>$pdfPath);
                }else{
                $message = array('status' => 400, 'message' => 'Certificate not found in our system.');	
                }
                 return response($message);  
            }else
            {
                $message = array('status' => 400, 'message' => 'QR text is empty.');
                return response($message);
            }
        }
        }else
        {
            $message = array('status' => 403, 'message' => 'Access forbidden.');
            return response($message);
        }
       
    }

    public function getMolwaDocuments(Request $request){

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);


        $apiKeyStatic="|M_Fu#t)Q}rH0)r/U<4t3FR3.OrtL&";
        $checkAuthorization = false;
        
        $headers = apache_request_headers();
        
        if (isset($headers['apikey'])) {
            if (strcasecmp($headers['apikey'], $apiKeyStatic) == 0) {
                $checkAuthorization = true;
            }

        }
        if (isset($headers['Apikey'])) {

            if (strcasecmp($headers['Apikey'], $apiKeyStatic) == 0) {
                $checkAuthorization = true;
            }
        }

        if ($checkAuthorization){
            

            $rules = [
                'qr_text' => 'required'
            ];

            $messages = [
                'qr_text.required' => 'Please enter qr text.',
            ];

            $validator = \Validator::make($request->all(),$rules,$messages);

            if ($validator->fails()) {
               
                  $message = array('status' => 400, 'message' => ApiSecurityLayerThirdParty::getMessage($validator->errors()));
                  return response($message);
            }else{
            
            $qr_text = $request['qr_text'];
           
            if (!empty($qr_text)) 
            {
                
                if( $qr_text=='zVjbVPFeo2o'){
                    $message = array('status' => 200, 'message' => 'success','videoUrl'=>"http://103.48.19.157/video/qr2.mp4");
                }else{
                //get pdf path `seqr_d_apponly`.
                $certificateData=DB::select(DB::raw('SELECT serial_no as DocNo FROM `seqr_d_secura`.`student_table` WHERE `key` ="'.$qr_text.'"'));
                if($certificateData){

                $DocNo=$certificateData[0]->DocNo;

                 $pdfPath = 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/pdf_file/'.$DocNo.'.pdf';
                 $message = array('status' => 200, 'message' => 'success','documentId'=>$DocNo,'pdfPath'=>$pdfPath);
                }else{
                $message = array('status' => 400, 'message' => 'Certificate not found in our system.'); 
                }

                }
                 return response($message);  
            }else
            {
                $message = array('status' => 400, 'message' => 'QR text is empty.');
                return response($message);
            }
        }
        }else
        {
            $message = array('status' => 403, 'message' => 'Access forbidden.');
            return response($message);
        }
       
    }

}