<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 28/12/2019
 *   Use   : Check specific User route permission
 *
**/
namespace App\Helpers;
use Log,Auth,DB;

use App\Models\StudentTable;
/*use App\Models\SuperAdmin;*/
use App\models\Demo\SuperAdmin as DemoSuperAdmin;
use App\models\Demo\Site as DemoSite;
use App\models\TemplateMaster;
use Storage;
use App\Utility\BlockChain;
use App\models\BlockChainMintData;

class CoreHelper
{
	
    public static function genRandomStr($length) {
        $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $string = '';
        for ($p = 0; $p < $length; $p++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $string;
    }

    public static function sendSMS($mobile_no, $text) {
        $apiKey = "A32ba2b0a6770c225411fd95ff86401c4";
        $message = urlencode($text);
        $sender_id = "scubes";

        $url = "https://alerts.solutionsinfini.com/api/v4/?api_key=" . $apiKey . "&method=sms&message=" . $message . "&to=" . $mobile_no . "&sender=" . $sender_id . "";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        curl_close($ch);

        $data = (json_decode($result, true));

        if ($data['status'] == "OK") {
            return 1;
        } else {
            return 0;
        }
    }

    public static function generateOTP($digits) {
        //$digits = 6;
        $OTP = rand(pow(10, $digits - 1), pow(10, $digits) - 1); //6 Digit OTP
        return $OTP;
    }

    public static function uploadSingleFile($fieldname, $maxsize, $uploadpath, $extensions = false, $ref_name = false) {
    $upload_field_name = $_FILES[$fieldname]['name'];
    if (empty($upload_field_name) || $upload_field_name == 'NULL') {
        return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Please upload a file');
    }
    
    $file_extension = strtolower(pathinfo($upload_field_name, PATHINFO_EXTENSION));

    if ($extensions !== false && is_array($extensions)) {
        if (!in_array($file_extension, $extensions)) {
            return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Please upload valid file ');
        }
    }
    $file_size = @filesize($_FILES[$fieldname]["tmp_name"]);
    if ($file_size > $maxsize) {
        return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'File Exceeds maximum limit');
    }
    if (isset($upload_field_name)) {
        if ($_FILES[$fieldname]["error"] > 0) {
            return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Error: ' . $_FILES[$fieldname]['error']);
        }
    }
    if ($ref_name == false) {
        

        $file_name_without_ext = self::FileNameWithoutExt($upload_field_name);
        $file_name = time() . '_' . self::RenameUploadFile($file_name_without_ext) . "." . $file_extension;
    } else {
        $file_name = str_replace(" ", "_", $ref_name) . "." . $file_extension;
    }
    if (!is_dir($uploadpath)) {
        mkdir($uploadpath, 0777, true);
    }
    if (move_uploaded_file($_FILES[$fieldname]["tmp_name"], $uploadpath . $file_name)) {
        return array('file' => $_FILES[$fieldname]["name"], 'status' => true, 'msg' => 'File Uploaded Successfully!', 'filename' => $file_name);
    } else {
        return array('file' => $_FILES[$fieldname]["name"], 'status' => false, 'msg' => 'Sorry unable to upload your file, Please try after some time.');
    }
}

public static function FileNameWithoutExt($filename) {
    return substr($filename, 0, (strlen($filename)) - (strlen(strrchr($filename, '.'))));
}

public static function RenameUploadFile($data) {
    $search = array("'", " ", "(", ")", ".", "&", "-", "\"", "\\", "?", ":", "/");
    $replace = array("", "_", "", "", "", "", "", "", "", "", "", "");
    $new_data = str_replace($search, $replace, $data);
    return strtolower($new_data);
}

    public static function deleteDirectory($dirPath) {
        if (is_dir($dirPath)) {
            $objects = scandir($dirPath);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
                        self::deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dirPath . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dirPath);
        }
    }

    public static function uploadBlob($filetoUpload,$blobName){
    $accesskey=env('AZURE_STORAGE_KEY'); 
    $storageAccount =env('AZURE_STORAGE_NAME'); 
    $containerName = env('AZURE_STORAGE_CONTAINER');  
    $destinationURL = "https://$storageAccount.blob.core.windows.net/$containerName/$blobName";
    //exit;
    $currentDate = gmdate("D, d M Y H:i:s T", time());
    $handle = fopen($filetoUpload, "r");
    $fileLen = filesize($filetoUpload);

    $headerResource = "x-ms-blob-cache-control:max-age=3600\nx-ms-blob-type:BlockBlob\nx-ms-date:$currentDate\nx-ms-version:2015-12-11";
    $urlResource = "/$storageAccount/$containerName/$blobName";

    $arraysign = array();
    $arraysign[] = 'PUT';               /*HTTP Verb*/  
    $arraysign[] = '';                  /*Content-Encoding*/  
    $arraysign[] = '';                  /*Content-Language*/  
    $arraysign[] = $fileLen;            /*Content-Length (include value when zero)*/  
    $arraysign[] = '';                  /*Content-MD5*/  
    $arraysign[] = 'application/pdf';         /*Content-Type*/  
    $arraysign[] = '';                  /*Date*/  
    $arraysign[] = '';                  /*If-Modified-Since */  
    $arraysign[] = '';                  /*If-Match*/  
    $arraysign[] = '';                  /*If-None-Match*/  
    $arraysign[] = '';                  /*If-Unmodified-Since*/  
    $arraysign[] = '';                  /*Range*/  
    $arraysign[] = $headerResource;     /*CanonicalizedHeaders*/
    $arraysign[] = $urlResource;        /*CanonicalizedResource*/

    $str2sign = implode("\n", $arraysign);

    $sig = base64_encode(hash_hmac('sha256', urldecode(utf8_encode($str2sign)), base64_decode($accesskey), true));  
    $authHeader = "SharedKey $storageAccount:$sig";

    $headers = [
        'Authorization: ' . $authHeader,
        'x-ms-blob-cache-control: max-age=3600',
        'x-ms-blob-type: BlockBlob',
        'x-ms-date: ' . $currentDate,
        'x-ms-version: 2015-12-11',
        'Content-Type: application/pdf',
        'Content-Length: ' . $fileLen
    ];

    $ch = curl_init($destinationURL);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_INFILE, $handle); 
    curl_setopt($ch, CURLOPT_INFILESIZE, $fileLen); 
    curl_setopt($ch, CURLOPT_UPLOAD, true); 
    $result = curl_exec($ch);

   
    $error= curl_error($ch);
    curl_close($ch);
    
    if(!empty($error)){
        print_r($error);
        exit;
    }
    return $result;

    }

    public static function checkMaxCertificateLimit($recordToGenerate){
          $site_id=Auth::guard('admin')->user()->site_id;

        $studentTableCounts = StudentTable::select('id')->where('site_id',$site_id)->count();

        $superAdminUpdate = DemoSuperAdmin::where('property','print_limit')->where('site_id',$site_id)
                            ->update(['current_value'=>$studentTableCounts]);
        //get value,current value from super admin
        $template_value = DemoSuperAdmin::select('value','current_value')->where('property','print_limit')->where('site_id',$site_id)->first();


        // print_r((int)$template_value['current_value']);
        // print_r($template_value);
        // exit();
       // DB::connection('superdb')->table("super_admin")->where('property','print_limit')->where('site_id',$site_id)->update(['current_value'=>$studentTableCounts]);
        
        $currentValue=(int)$template_value['current_value'];
        $printLimit=(int)$template_value['value'];
        if($template_value['value'] == null || $template_value['value'] == 0 || $currentValue < $printLimit){
            
            $totalRecordsCount=$currentValue+$recordToGenerate;
            $noOfCertificateCanGenerate=$printLimit-$currentValue;
            if($totalRecordsCount<=$printLimit){
                $arrResp=array('status'=>true,'type' => 'success',"message"=>"success");
            }else{
                $arrResp=array('status'=>false,'type' => 'error',"message"=>"Your are limit to geneate certificate is ".$noOfCertificateCanGenerate);
            }
        }
        else{
             $arrResp=array('status'=>false,'type' => 'error',"message"=>"Limit exceed");
            
        } 
        return $arrResp;
    }

    public static function fetchStorageDetails(){
       // $site_id=Auth::guard('admin')->user()->site_id;
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        //$site = Site::select('site_id')->where('site_url',$domain)->first();
       
        $siteData = DemoSite::select('pdf_storage_path','site_id')->where('site_url',$domain)->first();

        //print_r($siteData);
        if($siteData){
             $site_id= $siteData['site_id'];
            if(!empty($siteData['pdf_storage_path'])){

             $pdf_storage_path=$siteData['pdf_storage_path'];
            }else{
                $pdf_storage_path=false;
            }

            /*if(!empty($siteData->pdf_base_url)){
                $pdf_base_url=$siteData->pdf_base_url;
            }else{
                 $pdf_base_url=false;
            }*/

            $arrResp=array('status'=>true,'type' => 'success',"message"=>"success","pdf_storage_path"=>$pdf_storage_path,"site_id"=>$site_id);

        }else{
             $arrResp=array('status'=>false,'type' => 'error',"message"=>"Site data not found");

        }
        return $arrResp;
    }

    public static function checkMonadFtpStatus(){
        // FTP server details
        $ftpHost = \Config::get('constant.monad_ftp_host');
        $ftpPort = \Config::get('constant.monad_ftp_port');
        $ftpUsername = \Config::get('constant.monad_ftp_username');
        $ftpPassword = \Config::get('constant.monad_ftp_pass');        
        // open an FTP connection
        $connId = ftp_connect($ftpHost,$ftpPort); //or die("Couldn't connect to $ftpHost");   
        /*if($connId){
            $ftp_flag=is_array(ftp_nlist($connId, ".")) ? 'Connected' : 'Not Connected';            
        }else{
            $ftp_flag='Not Connected';
        }*/
        if (@ftp_login($connId, $ftpUsername, $ftpPassword))
        {
            //echo "Connection established.";
            $ftp_flag='Connected';
        }
        else
        {
            //echo "Couldn't establish a connection.";
            $ftp_flag='Not Connected';
        } 
        
        if($ftp_flag=='Connected'){
           $arrResp=array('status'=>true,'type' => 'success',"message"=>"Server connected!","ftpHost"=>$ftpHost,"ftp_flag"=>$ftp_flag);
        }else{
           $arrResp=array('status'=>false,'type' => 'error',"message"=>"Failed to connect to Monad University Server. Please try again after sometime.","ftpHost"=>$ftpHost,"ftp_flag"=>$ftp_flag); 
        }

        return $arrResp;
    }

    public static function checkAnuFtpStatus(){
        // FTP server details
        $ftpHost = \Config::get('constant.anu_ftp_host');
        $ftpPort = \Config::get('constant.anu_ftp_port');
        $ftpUsername = \Config::get('constant.anu_ftp_username');
        $ftpPassword = \Config::get('constant.anu_ftp_pass');        
        // open an FTP connection
        $connId = ftp_connect($ftpHost,$ftpPort); //or die("Couldn't connect to $ftpHost");   
        /*if($connId){
            $ftp_flag=is_array(ftp_nlist($connId, ".")) ? 'Connected' : 'Not Connected';            
        }else{
            $ftp_flag='Not Connected';
        }*/
        if (@ftp_login($connId, $ftpUsername, $ftpPassword))
        {
            //echo "Connection established.";
            $ftp_flag='Connected';
        }
        else
        {
            //echo "Couldn't establish a connection.";
            $ftp_flag='Not Connected';
        }
        
        if($ftp_flag=='Connected'){
           $arrResp=array('status'=>true,'type' => 'success',"message"=>"Server connected!","ftpHost"=>$ftpHost,"ftp_flag"=>$ftp_flag);
        }else{
           $arrResp=array('status'=>false,'type' => 'error',"message"=>"Failed to connect to Anant National University Server. Please try again after sometime.","ftpHost"=>$ftpHost,"ftp_flag"=>$ftp_flag); 
        }

        return $arrResp;
    }	
	
    public static function thousandsCurrencyFormat($num) {

      if($num>9999) {

            $x = round($num);
            $x_number_format = number_format($x);
            $x_array = explode(',', $x_number_format);
            $x_parts = array('k', 'm', 'b', 't');
            $x_count_parts = count($x_array) - 1;
            $x_display = $x;
            $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
            $x_display .= $x_parts[$x_count_parts - 1];

            return $x_display;

      }

      return $num;
    }

    public static function createLoaderJson($jsonData,$flag=1){

         $domain = \Request::getHost();
         $subdomain = explode('.', $domain);

        
            $fileName = $jsonData['token']. '_loader.json';
            $loaderDir=public_path().'/'.$subdomain[0].'/backend/loader/';
           
            if($flag!=1&&!empty($jsonData['token'])){
                $jsonNew=$jsonData;
                $jsonData = json_decode(file_get_contents($loaderDir.$fileName), true); 

                if(isset($jsonNew['generatedCertificates'])){


                    $jsonData['pendingCertificates']=$jsonData['recordsToGenerate']-$jsonNew['generatedCertificates'];
                    if($jsonData['pendingCertificates']<0){

                        $jsonData['pendingCertificates']=($jsonData['recordsToGenerate']*-1)-$jsonNew['generatedCertificates'];

                    }
                    $jsonData['generatedCertificates'] =$jsonNew['generatedCertificates'];

                    if($jsonData['pendingCertificates']==0){
                        $jsonData['isGenerationCompleted'] =1; 
                        $predictedCompletion=round($jsonData['recordsToGenerate']/100);
                        if($predictedCompletion==0){
                            $predictedCompletion=1;
                        }

                        $predictedCompletion=$predictedCompletion*3;
                        $jsonData['totalTimeForGeneration'] = gmdate("H:i:s", $jsonData['totalSecondsForGeneration']+$predictedCompletion);

                        $timeArr=explode(':', $jsonData['totalTimeForGeneration']);
                        $timeStr='';
                        if($timeArr[0]!='00'){
                            $timeStr .=$timeArr[0].' Hours ';
                        }
                        if($timeArr[1]!='00'){
                            $timeStr .=$timeArr[1].' Minutes ';
                        }
                        if($timeArr[2]!='00'){
                            $timeStr .=$timeArr[2].' Seconds';
                        }
                        if(!empty($timeStr)){
                        $jsonData['totalTimeForGeneration'] =$timeStr;
                        }else{
                        $jsonData['totalTimeForGeneration'] ='Less than 1 second.'; 
                        }
                    }
                }
                if(isset($jsonNew['timePerCertificate'])){
                    $jsonData['timePerCertificate'] =$jsonNew['timePerCertificate'];

                    $totalSeconds =$jsonData['pendingCertificates']*$jsonData['timePerCertificate'];
                    $jsonData['predictedTime'] = date("h:i:s A", strtotime("+$totalSeconds sec"));
                    $jsonData['totalSecondsForGeneration'] = $jsonData['totalSecondsForGeneration']+$jsonNew['timePerCertificate'];
                    
                }
                
            }


            //if($subdomain[0] == 'rrmu'){

                //dd($jsonData);
            //}

            //dd($jsonData);
            if(!empty($jsonData['recordsToGenerate'])){
            $jsonData['percentageCompleted']=round(($jsonData['generatedCertificates']/$jsonData['recordsToGenerate'])*100);
             
            }else{
             $jsonData['percentageCompleted']=round($jsonData['percentageCompleted']);    
            }
            
            if(!is_dir($loaderDir)){
    
                        mkdir($loaderDir, 0777);
            }
            \File::put($loaderDir.$fileName,json_encode($jsonData));
            $protocol = (isset($_SERVER["HTTPS"])&& $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $prefix = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/'.$subdomain[0].'/backend/loader/';
            //$prefix = 'http://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/loader/';
            return array('loader_token'=>$jsonData['token'],'fileName'=> $prefix.$fileName);

    }

    public static function rrmdir($dir) {
        if (is_dir($dir)) {
          $objects = scandir($dir);
          foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
              if (filetype($dir."/".$object) == "dir") 
                 rrmdir($dir."/".$object); 
              else unlink   ($dir."/".$object);
            }
          }
          reset($objects);
          rmdir($dir);
        }
    }

    public static function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824)
        {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        }
        elseif ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        }
        elseif ($bytes >= 1024)
        {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        }
        elseif ($bytes > 1)
        {
            $bytes = $bytes . ' bytes';
        }
        elseif ($bytes == 1)
        {
            $bytes = $bytes . ' byte';
        }
        else
        {
            $bytes = '0 bytes';
        }

        return $bytes;
    }


    public static function getFilesAndSize($directory,$file_size_pdf_file,$file_count_pdf_file){
        // if(!empty($extensions)){
        //     $files = glob($directory . "*.{$extensions}",GLOB_BRACE);
        // }else{
        $files = glob($directory . "*");    

        //print_r($files);
       // exit;
        //}
        // $file_size_pdf_file=0;
        // $file_count_pdf_file=0;
        if ($files){
         //$file_count_pdf_file = count($files);
            foreach($files as $path){

                if(is_file($path)){
                    $file_count_pdf_file++;
                    $file_size_pdf_file += filesize($path);
                }

                if(is_dir($path)){  
                    $resp=self::getFilesAndSize($path,$file_size_pdf_file,$file_count_pdf_file);
                    //$file_size_pdf_file=$resp['file_size_pdf_file']+$file_size_pdf_file;

                    //$file_count_pdf_file=$resp['file_count_pdf_file']+$file_count_pdf_file;
                }
                //is_file($path) && $file_size_pdf_file += filesize($path);
                //is_dir($path)  && $size += get_dir_size($path);
            }
        }

        return array("file_size_pdf_file"=>$file_size_pdf_file,"file_count_pdf_file"=>$file_count_pdf_file);
    }

    public static function getDirContents($dir,&$file_count_pdf_file=0,&$file_size_pdf_file=0, &$results = array()) {
        $files = scandir($dir);

        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                $results[] = $path;
                $file_count_pdf_file++;
                $file_size_pdf_file += filesize($path);
            } else if ($value != "." && $value != "..") {
                $resp=self::getDirContents($path,$file_count_pdf_file,$file_size_pdf_file, $results);
                $results[] = $path;
                //$file_size_pdf_file=$resp['file_size_pdf_file']+$file_size_pdf_file;
                //$file_count_pdf_file=$resp['file_count_pdf_file']+$file_count_pdf_file;
            }
        }

        //return $results;
        return array("file_size_pdf_file"=>$file_size_pdf_file,"file_count_pdf_file"=>$file_count_pdf_file);
    }

    public static function compressPdfFile($inputFile,$outputFile,$pdfSetting=''){
		if($pdfSetting == ''){
			$pdfSetting='ebook';
		}else{
			$pdfSetting=$pdfSetting;
		}
        $output = exec('"C:/Program Files/gs/gs9.56.1/bin/gswin64c.exe" -r120 -dSAFER -dQUIET -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dPDFSETTINGS=/'.$pdfSetting.' -dCompatibilityLevel=1.4 -sOutputFile='.$outputFile.' '.$inputFile);
        return $output;
    }

  	
  	public static function geneatePayUHash($data){



// Merchant key here as provided by Payu
//$MERCHANT_KEY = "hDkYGPQe";

    //    $MERCHANT_KEY="S8u5hD";// scube live
		$MERCHANT_KEY = "ICFgMpPe";//monad live
     //   $MERCHANT_KEY = "rjQUPktU"; //scube test

		// Merchant Salt as provided by Payu
		//$SALT = "yIEkykqEH3";

        //$SALT = "HCZltttx"; // scube live
        $SALT = "j73HrLwwlT";//monad live
      //  $SALT = "e5iIg1jwi8";//scube test

		// End point - change to https://secure.payu.in for LIVE mode
        $PAYU_BASE_URL = "https://secure.payu.in";// live
    //    $PAYU_BASE_URL = "https://test.payu.in"; //test

		$action = '';

		$posted = array();
		/*if(!empty($_POST)) {
		    //print_r($_POST);
		  foreach($_POST as $key => $value) {    
		    $posted[$key] = $value; 
			
		  }
		}*/

		$posted['key']=$MERCHANT_KEY;
		$posted['txnid']=$data['txnid'];
		//$txnid =substr(hash('sha256', mt_rand() . microtime()), 0, 20);
		$posted['amount']=$data['amount'];
		$posted['firstname']=$data['name'];
		$posted['email']=$data['email'];
		$posted['phone']=$data['mobile_number'];
		$posted['productinfo']=$data['product_info'];
		$posted['surl']= $data['success_url'];//URL::route('payumoney-success');
		$posted['furl']=$data['failure_url']; //URL::route('payumoney-failure');
		$posted['service_provider']="payu_paisa";
		$posted['udf1']=$data['user'];
        $posted['udf2']=$data['platform'];
		
		$formError = 0;
		//print_r($posted);
		//exit;
		/*if(empty($posted['txnid'])) {
		  // Generate random transaction id
		  $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
		} else {
		  $txnid = $posted['txnid'];
		}*/
		$hash = '';
		// Hash Sequence
		$hashSequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10";
		if(empty($posted['hash']) && sizeof($posted) > 0) {
		  if(
		          empty($posted['key'])
		          || empty($posted['txnid'])
		          || empty($posted['amount'])
		          || empty($posted['firstname'])
		          || empty($posted['email'])
		          || empty($posted['phone'])
		          || empty($posted['productinfo'])
		          || empty($posted['surl'])
		          || empty($posted['furl'])
				  || empty($posted['service_provider'])
				  || empty($posted['udf1'])
		  ) {
		    $formError = 1;

		  } else {
		    //$posted['productinfo'] = json_encode(json_decode('[{"name":"tutionfee","description":"","value":"500","isRequired":"false"},{"name":"developmentfee","description":"monthly tution fee","value":"1500","isRequired":"false"}]'));
			$hashVarsSeq = explode('|', $hashSequence);
		    $hash_string = '';	
			foreach($hashVarsSeq as $hash_var) {
		      $hash_string .= isset($posted[$hash_var]) ? $posted[$hash_var] : '';
		      $hash_string .= '|';
		    }

		    $hash_string .= $SALT;


		    $hash = strtolower(hash('sha512', $hash_string));
		    $action = $PAYU_BASE_URL . '/_payment';
		  }
		} elseif(!empty($posted['hash'])) {
		  $hash = $posted['hash'];
		  $action = $PAYU_BASE_URL . '/_payment';
		}
		$result['posted']=$posted;
		$result['hash']=$hash;
		$result['MERCHANT_KEY']=$MERCHANT_KEY;
		$result['txnid']=$data['txnid'];
		$result['action']=$action;
		$result['formError']=$formError;

		return $result;
  	}



    /******************************BlockChain Start*********************************************/

    public static function checkContactAddress($template_id,$templateType='NORMALTEMPLATE'){//NORMALTEMPLATE, PDF2PDFTEMPLATE
        
        if($templateType=='NORMALTEMPLATE'){
         $checkContact = TemplateMaster::select('bc_contract_address')->where('id',$template_id)->first();

         if($checkContact&&empty($checkContact['bc_contract_address'])){
                if($template_id==698){
                $mode=1;//1:live 0:testnet
                }else{
                $mode=0;    
                }
                $response=BlockChain::deployContract($mode);
                if($response&&$response['status']==200&&isset($response['contractAddress'])&&!empty($response['contractAddress'])){

                     TemplateMaster::where('id',$template_id)->update(['bc_contract_address'=>$response['contractAddress']]);

                }
         }
        }
     }


    public static function mintPDF($data){//NORMALTEMPLATE, PDF2PDFTEMPLATE
        
        $domain = \Request::getHost();
        //$subdomain = explode('.', $domain);


        $response=["status"=>0];
        $mintData=[];

        $siteData = DemoSite::select('bc_wallet_address')->where('site_url',$domain)->first();

        if(!empty($siteData&&!empty($siteData['bc_wallet_address'])&&$data['pdf_file'])&&!empty($data['uniqueHash'])&&!empty($data['template_id'])){

            $checkContact = TemplateMaster::select('bc_contract_address')->where('id',$data['template_id'])->first();

             $checkContact['bc_contract_address'];

            
            if($checkContact&&!empty($checkContact['bc_contract_address'])){

                $mintData['pdf_file']=$data['pdf_file'];
                
                //Fetch from Demo sites table
                $mintData['walletID']=$siteData['bc_wallet_address'];

                //Fetch from Template Master
                $mintData['smartContractAddress']=$checkContact['bc_contract_address'];

                $mintData['documentType']=(!empty($data['documentType']))?$data['documentType']:"";;
                $mintData['description']=(!empty($data['description']))?$data['description']:"";
                $mintData['uniqueHash']=$data['uniqueHash'];



                for($i=1;$i<=5;$i++){
                
                    
                    if(isset($data['metadata'.$i])){
                        $mintData['metadata'.$i]=$data['metadata'.$i];    
                    }

                }
                
                if($data['template_id']==698){
                $mode=1;//1:live 0:testnet
                $mintData['walletID']="0xB509AF6532Af95eE59286A8235f2A290c26b5730";
                }else{
                $mode=0;    
                }
               
                $response=BlockChain::mintData($mode,$mintData);
                if($response&&$response['status']==200&&isset($response['txnHash'])&&!empty($response['txnHash'])){
                    
                    $response=["status"=>$response['status'],"txnHash"=>$response['txnHash']];

                     $datetime  = date("Y-m-d H:i:s");
                    BlockChainMintData::create(['txn_hash'=>$response['txnHash'],'gas_fees'=>$response['gasPrice'],'token_id'=>$response['tokenID'],'key'=>$mintData['uniqueHash'],'created_at'=>$datetime]);

                }
            }
        }
        return $response;
    }

    public static function getBCVerificationUrl($encryptedData){
        
        $domain = \Request::getHost();
        //$subdomain = explode('.', $domain);

        return "https://".$domain."/bverify/".$encryptedData;
    }

    public static function retreiveDetails($data){
      
         if(!empty($data['template_id'])&&!empty($data['walletID'])&&!empty($data['uniqueHash'])){
                if($data['template_id']==698){
                    $data['walletID']="0xB509AF6532Af95eE59286A8235f2A290c26b5730";
                    $mode=1;//1:live 0:testnet
                }else{
                    $mode=0;    
                }
                /*print_r($data);
                exit;*/
                $response=BlockChain::retreiveDetails($mode,$data);
                return $response;
         }
        
     }
    /******************************BlockChain End*********************************************/

      /****************Encryption*****************************************/
    public static function encrypt_adv($plaintext, $key) {
    $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
    $ciphertext = base64_encode($iv . /*$hmac.*/$ciphertext_raw);
    //$ciphertext = str_replace('/', '[slash]', $ciphertext);
    //$ciphertext = str_replace('+', '[plus]', $ciphertext);
    return $ciphertext;
    }

    public static function decrypt_adv($ciphertext, $key) {
    //$ciphertext = str_replace('[slash]', '/', $ciphertext);
    //$ciphertext = str_replace('[plus]', '+', $ciphertext);
    $c = base64_decode($ciphertext);
    $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
    $iv = substr($c, 0, $ivlen);
    $ciphertext_raw = substr($c, $ivlen);

    $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
    return $original_plaintext;
    }
    /************************************************************************/

    /****************  Rohit changes 18/05/2023 *****************/
    public static function awsUpload($output,$outputFile,$serial_no,$certName) {
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        
        $s3 = \Storage::disk('s3');
        // // File Exist in folder
        if($s3->exists($outputFile)) {
            $student = StudentTable::where('status',1)->where('serial_no',$serial_no)->value('id');
            $awsInactivePDFFolder = 'public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF';
            // check folder 
            if($s3->exists($awsInactivePDFFolder)) {
                $newFileNamePdf = 'public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF/'.$student.'_'.$certName;

               // echo $newFileNamePdf;
                $s3->move($outputFile, $newFileNamePdf);
            } else {
                // folder create
                $s3->makeDirectory($awsInactivePDFFolder, 0777);
                $newFileNamePdf = 'public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF/'.$student.'_'.$certName;
                $s3->move($outputFile, $newFileNamePdf);
            }
        }
        if(!$s3->exists($outputFile)) {
            $s3->put($outputFile, file_get_contents($output));
        }
        
        @unlink($output);
    }
    /**************** Rohit changes 18/05/2023 *****************/

    
}