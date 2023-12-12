<?php

namespace App\Utility;
use Illuminate\Support\Str;
use App\models\SessionManager;
use App\models\BlockChainApiTracker;
use App\models\User; 
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;

class BlockChain
{
	
	
	private static function insertTracker($requestUrl, $requestMethod, $requestParameters,$response,$status,$responseTime,$apiName) 
	{
		

		//fetching headers
		// $headers = apache_request_headers();
		$headers = apache_request_headers();
		
		$headerParameters = array();
		if (isset($headers['apikey'])) {
			$headerParameters['apikey'] = $headers['apikey'];
		}else if(isset($headers['Apikey'])){

			$headerParameters['apikey'] = $headers['Apikey'];

		}
		if (isset($headers['accesstoken'])) {
			$headerParameters['accesstoken'] = $headers['accesstoken'];
		}
		if (isset($headers['Accesstoken'])) {
			$headerParameters['accesstoken'] = $headers['Accesstoken'];
		}
		if (isset($headers['Content-Type'])) {
			$headerParameters['Content-Type'] = $headers['Content-Type'];
		}

		

		$client_ip = $_SERVER['REMOTE_ADDR'];
		$headerParameters = json_encode($headerParameters);
		$requestParameters = json_encode($requestParameters);
		$response = json_encode($response);
		$dateTime = date('Y-m-d H:i:s');



		$result = new BlockChainApiTracker;
		$result->client_ip = $client_ip;
		$result->request_url = $requestUrl;
		$result->request_method = $requestMethod;
		$result->status = $status;
		$result->header_parameters = $headerParameters;
		$result->request_parameters = $requestParameters;
		$result->response = $response;
		$result->created_at = $dateTime;
		$result->client_ip = $client_ip;
		$result->api_name = $apiName;
		$result->response_time = $responseTime;

		$result->save();

		return $result->id;

		
	}



	/************************** Create Wallet For Site*********************************************/
	public static function createWallet() 
	{	

		/*{
		    "success": 200,
		    "message": "ERC20 Account created succesfully",
		    "address": "0x7e457bAE2F4b09D4F966670A8367A2E388e45128",
		    "privateKey": "0x23bbef371dd23c2fea93de78e37607c2646b1f79351d0e82830f374a7ecff8b6"
		}*/

		$responseArr=[];
		$response=[];
		try {
			
			$requestUrl='https://veraciousapis.herokuapp.com/v1/createAccount';
			$requestMethod='GET';
			$requestParameters=[];
			/*$requestUrl='https://test.seqrdoc.com/api/user-login';
			$requestMethod='POST';
			$requestParameters['username']="abc";
			$requestParameters['password']="abc";
*/
			$client = new Client();

			$res = $client->request($requestMethod, $requestUrl, [
			    'form_params' => $requestParameters
			]);

			if ($res->getStatusCode() == 200) { // 200 OK
			    $response_data = $res->getBody()->getContents();

			    $response=(array)json_decode($response_data);

			    if(isset($response['success'])){

			    	if($response['success']==200){

			    		$walletAddress=$response['address'];
			    		$privateKey=$response['privateKey'];

			    		$respArr['status']=200;
			    		$respArr['message']="Wallet id generated successfully.";
			    	
			    	}else{
			    		$respArr['status']=$response['success'];
			    		if(isset($response['message'])){
			    			$respArr['message']=$response['message'];
			    		}else{
			    			$respArr['message']="Something went wrong.";
			    		}
			    	}

			    }else{
			    	$respArr['status']=400;
			    	$respArr['message']="Something went wrong.";
			    }

			}else{
				$respArr['status']=400;
			    $respArr['message']="Something went wrong.";
			    
			}	
		
		} catch (Exception $e) {

			$respArr['message'] =$e->getMessage();
			$respArr['status']=400;
		}
		
		if ($respArr['status']==200) {
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }


        if(empty($response)){
        	$response=$res;

        }
        $responseTime = microtime(true) - LARAVEL_START;
        $api_tracker_id = Self::insertTracker($requestUrl,$requestMethod,$requestParameters,$response,$status,$responseTime,"createWallet");
         

		return $respArr;

	}
	

	/************************** Deploy Contract Accoring To Template Name*********************************************/
	public static function deployContract() 
	{	

		/*{
		    "success": 200,
		    "message": "Contract Succesfully deployed",
		    "contractAddress": "0x19e9CA259E59925C057e640CbC716CC9c8d1f5f7"
		}*/

		$responseArr=[];
		$response=[];
		try {
			
			$requestUrl='https://veraciousapis.herokuapp.com/v1/deployContract';
			$requestMethod='GET';
			$requestParameters=[];
			/*$requestUrl='https://test.seqrdoc.com/api/user-login';
			$requestMethod='POST';
			$requestParameters['username']="abc";
			$requestParameters['password']="abc";
*/
			$client = new Client();

			$res = $client->request($requestMethod, $requestUrl, [
			    'form_params' => $requestParameters
			]);

			if ($res->getStatusCode() == 200) { // 200 OK
			    $response_data = $res->getBody()->getContents();

			    $response=(array)json_decode($response_data);

			    if(isset($response['success'])){

			    	if($response['success']==200){

			    		$contractAddress=$response['contractAddress'];

			    		$respArr['status']=200;
			    		$respArr['message']="Contract Succesfully deployed.";
			    		$respArr['contractAddress']=$contractAddress;
			    	}else{
			    		$respArr['status']=$response['success'];
			    		if(isset($response['message'])){
			    			$respArr['message']=$response['message'];
			    		}else{
			    			$respArr['message']="Something went wrong.";
			    		}
			    	}

			    }else{
			    	$respArr['status']=400;
			    	$respArr['message']="Something went wrong.";
			    }

			}else{
				$respArr['status']=400;
			    $respArr['message']="Something went wrong.";
			    
			}	
		
		} catch (Exception $e) {

			$respArr['message'] =$e->getMessage();
			$respArr['status']=400;
		}
		
		if ($respArr['status']==200) {
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }

        if(empty($response)){
        	$response=$res;

        }

        $responseTime = microtime(true) - LARAVEL_START;
        $api_tracker_id = Self::insertTracker($requestUrl,$requestMethod,$requestParameters,$response,$status,$responseTime,"deployContract");
         

		return $respArr;

	}



	
	/************************** MINT Data*********************************************/
	public static function mintData($data) 
	{	


		/*{
		    "success": 200,
		    "message": "document registered on the blockchain successfully",
		    "txnHash": "0xad2014f1130898fa5c0c3051d4160296902c84bc72583272cc48b88d90462b0b",
		    "tokenID": 25,
		    "gasPrice": 0.00027489300183262
		}*/

		$responseArr=[];
		$response=[];
		$requestParameters=[];
		try {
			
			 $filePath=$data['pdf_file'];
			$name = basename($filePath); 
			$fileName =pathinfo($filePath, PATHINFO_FILENAME);

			//$requestUrl='https://test.seqrdoc.com/api/verify/uploadTest';
			$requestUrl='https://veraciousapis.herokuapp.com/v1/mint';
			$requestMethod='POST';
			/*$requestUrl='https://test.seqrdoc.com/api/user-login';
			$requestMethod='POST';
			$requestParameters['username']="abc";
			$requestParameters['password']="abc";*/

			/*$requestParameters['walletID']="0x7e457bAE2F4b09D4F966670A8367A2E388e45128";
			$requestParameters['smartContractAddress']="0x19e9CA259E59925C057e640CbC716CC9c8d1f5f7";
			$requestParameters['documentType']="Passing Degree Certificate";
			$requestParameters['description']="Scube University, Maharashtra";
			$requestParameters['uniqueHash']=$fileName;*/

			$requestParameters['walletID']=$data['walletID'];//"0x7e457bAE2F4b09D4F966670A8367A2E388e45128";
			$requestParameters['smartContractAddress']=$data['smartContractAddress'];//"0x19e9CA259E59925C057e640CbC716CC9c8d1f5f7";
			$requestParameters['documentType']=$data['documentType'];
			$requestParameters['description']=$data['description'];
			$requestParameters['uniqueHash']=$data['uniqueHash'];//$fileName;

			for($i=1;$i<=5;$i++){
				
				
				if(isset($data['metadata'.$i])){
					$requestParameters['metadata'.$i]=json_encode($data['metadata'.$i]);	
				}else{
					$requestParameters['metadata'.$i]=json_encode(["label"=> "", "value"=> ""]);
				}

			}

			/*$requestParameters['metadata1']=json_encode(["label"=> "Name", "value"=> "Mandar Gawade"]);
			$requestParameters['metadata2']=json_encode(["label"=> "Course", "value"=> "SeQR Doc" ]);
			$requestParameters['metadata3']=json_encode(["label"=> "Specialization", "value"=> "Block Chain"]);
			$requestParameters['metadata4']=json_encode(["label"=> "College", "value"=> "Scube" ]);
			$requestParameters['metadata5']=json_encode(["label"=> "CGPA", "value"=> "9.2" ]);
*/
			/*print_r($requestParameters);
			exit;*/
			$requestParametersWithFile=$requestParameters;
			$requestParametersWithFile['document']=curl_file_create($filePath);
			

			$ch = curl_init ($requestUrl);
			curl_setopt ($ch, CURLOPT_POST, 1);
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $requestParametersWithFile);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			$msg=curl_exec ($ch);
			$info = curl_getinfo($ch);

			if($info['http_code'] === 200){
			    $response = json_decode($msg,1);

			    if(isset($response['success'])){

			    	if($response['success']==200){


			    		$respArr['status']=200;
			    		$respArr['message']="Data mint Succesfully deployed.";
			    		$respArr['txnHash']=$response['txnHash'];
			    	
			    	}else{
			    		$respArr['status']=$response['success'];
			    		if(isset($response['message'])){
			    			$respArr['message']=$response['message'];
			    		}else{
			    			$respArr['message']="Something went wrong.";
			    		}
			    	}

			    }else{
			    	$respArr['status']=400;
			    	$respArr['message']="Something went wrong.";
			    }
			} else {
			    //$returnMessage['file_type'] = 'error';
			    $respArr['status']=400;
			    $respArr['message']="Something went wrong.";
			}
			curl_close($ch);
			//var_dump($info,$returnMessage);
			//exit;



		} catch (Exception $e) {

			$respArr['message'] =$e->getMessage();
			$respArr['status']=400;
		}
		
		if ($respArr['status']==200) {
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }


        if(empty($response)){
        	 $response=$res;
        	//exit;
        }
        $responseTime = microtime(true) - LARAVEL_START;
        $api_tracker_id = Self::insertTracker($requestUrl,$requestMethod,$requestParameters,$response,$status,$responseTime,"mintData");
         

		return $respArr;

	}



	public static function retreiveDetails($data) 
	{	

		$responseArr=[];
		$response=[];
		try {
			
			if(isset($data['uniqueHash'])&&isset($data['walletID'])&&!empty($data['uniqueHash'])&&!empty($data['walletID'])){
					//$requestUrl='https://test.seqrdoc.com/api/user-login';
					$requestUrl='https://veraciousapis.herokuapp.com/v1/retreiveDetails?uniqueHash='.$data['uniqueHash'].'&walletID='.$data['walletID'];
					$requestMethod='GET';
					$requestParameters['uniqueHash']=$data['uniqueHash'];
					$requestParameters['walletID']=$data['walletID'];

					$client = new Client();

					$res = $client->request($requestMethod, $requestUrl, [
					    'form_params' => $requestParameters
					]);

					/*print_r($res);
					exit;*/
					if ($res->getStatusCode() == 200) { // 200 OK
					    $response_data = $res->getBody()->getContents();

					    $response=(array)json_decode($response_data);
					    //print_r($response);

					    if(isset($response['success'])){

					    	if($response['success']==200){

					    		//$contractAddress=$response['contractAddress'];

					    		$respArr['status']=200;
					    		$respArr['data']=$response;
					    		$respArr['message']="Details fetched succesfully.";
					    	
					    	}else{
					    		$respArr['status']=$response['success'];
					    		if(isset($response['message'])){
					    			$respArr['message']=$response['message'];
					    		}else{
					    			$respArr['message']="Something went wrong.";
					    		}
					    	}

					    }else{
					    	$respArr['status']=400;
					    	$respArr['message']="Something went wrong.";
					    }

					}else{
						$respArr['status']=400;
					    $respArr['message']="Something went wrong.";
					    
					}	
				}else{
					$respArr['status']=400;
					$respArr['message']="Required fields not found.";
				}	
		} catch (Exception $e) {

			$respArr['message'] =$e->getMessage();
			$respArr['status']=400;
		}

			
		
		if ($respArr['status']==200) {
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }

        if(empty($response)){
        	$response=$res;

        }

        $responseTime = microtime(true) - LARAVEL_START;
        $api_tracker_id = Self::insertTracker($requestUrl,$requestMethod,$requestParameters,$response,$status,$responseTime,"retreiveDetails");
         

		return $respArr;

	}

}