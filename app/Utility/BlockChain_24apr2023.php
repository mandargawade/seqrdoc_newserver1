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
	public static function createWallet($mode='0') 
	{	

		
		$responseArr=[];
		$response=[];
		try {
			
			if($mode==0){
			//Testnet Url
			$requestUrl='https://veraciousapis.herokuapp.com/v1/createAccount';
			}else{
			//Live Url
			$requestUrl = 'https://mainnet-apis.herokuapp.com/v1/mainnet/createAccount';
			}
				
			$requestMethod='GET';
			$requestParameters=[];
			
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
	public static function deployContract($mode='0') 
	{	


		$responseArr=[];
		$response=[];
		try {
			if($mode==0){
			//Testnet Url
			$requestUrl='https://veraciousapis.herokuapp.com/v1/deployContract';
			}else{
			//Live Url
			$requestUrl='https://mainnet-apis.herokuapp.com/v1/mainnet/deployContract';
			}
			$requestMethod='GET';
			$requestParameters=[];
			
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
	public static function mintData($mode='0',$data) 
	{	

		$responseArr=[];
		$response=[];
		$requestParameters=[];
		try {
			
			$filePath=$data['pdf_file'];
			$name = basename($filePath); 
			$fileName =pathinfo($filePath, PATHINFO_FILENAME);

			if($mode==0){
			//Test URL
			$requestUrl='https://veraciousapis.herokuapp.com/v1/mint';
			}else{
			//Live URL
			$requestUrl='https://mainnet-apis.herokuapp.com/v1/mainnet/mint';
			}
			$requestMethod='POST';
			
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
			    		$respArr['gasPrice']=$response['gasPrice'];
			    		$respArr['tokenID']=$response['tokenID'];
			    	
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



	public static function retreiveDetails($mode='0',$data) 
	{	

		$responseArr=[];
		$response=[];
		try {
			
			if(isset($data['uniqueHash'])&&isset($data['walletID'])&&!empty($data['uniqueHash'])&&!empty($data['walletID'])){
					
					if($mode==0){
					//Test URL
					$requestUrl='https://veraciousapis.herokuapp.com/v1/retreiveDetails?uniqueHash='.$data['uniqueHash'].'&walletID='.$data['walletID'];
					}else{
					//Live URL
					$requestUrl='https://mainnet-apis.herokuapp.com/v1/mainnet/retreiveDetails?uniqueHash='.$data['uniqueHash'].'&walletID='.$data['walletID'];
					}
					$requestMethod='GET';
					$requestParameters['uniqueHash']=$data['uniqueHash'];
					$requestParameters['walletID']=$data['walletID'];
					//echo $requestUrl;
					//print_r($requestParameters);
					//exit;
					/*$client = new Client();

					$res = $client->request($requestMethod, $requestUrl, [
					    'form_params' => $requestParameters
					]);*/

         
			        $ch = curl_init();
			        curl_setopt($ch, CURLOPT_URL, $requestUrl);
			        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			         
			        $msg = curl_exec($ch);
			        $info = curl_getinfo($ch);
			      //  print_r($msg);
			      //  print_r($info);
			        
			        $response = json_decode($msg,1);
				//	print_r($response);
				//	exit;
					//if($info['http_code'] == 200){
					    
					   // $response_data = $res->getBody()->getContents();

					    //$response=(array)json_decode($response_data);
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

					/*}else{
						$respArr['status']=400;
					    $respArr['message']="Something went wrong.";
					    
					}*/	
					curl_close($ch);
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