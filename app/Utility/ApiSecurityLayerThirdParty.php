<?php

namespace App\Utility;
use Illuminate\Support\Str;
use App\models\SessionManager;
use App\models\ApiTracker;
use App\models\ApiAuthIpAddresses;
use Illuminate\Http\Request;

define('apiKey', 'I:n0GP5I&ZuNzBc~Lrco;%jI+>cl!X');
// used for Api security 

class ApiSecurityLayerThirdParty
{
	// cheking authorization
	public static function checkAuthorization() 
	{
		$status = false;
		
		$headers = apache_request_headers();
		
		if (isset($headers['apikey'])) {
			if (strcasecmp($headers['apikey'], apiKey) == 0) {
				$status = true;
			}

		}
		if (isset($headers['Apikey'])) {

			if (strcasecmp($headers['Apikey'], apiKey) == 0) {
				$status = true;
			}
		}


		 $client_ip = $_SERVER['REMOTE_ADDR'];
		 $result = ApiAuthIpAddresses::where('ip_address',$_SERVER['REMOTE_ADDR'])
		 								->where('publish',1)
                                        ->first(); 
        if ((!empty($result)&&$status)||($client_ip=='180.149.245.161'&&$status)) {                                    
        	$status = true;
    	}else{
    		$status = false;
    	}
		
		return $status;

	}
	// check Access Token
	public static function checkAccessToken($user_id) 
	{
		$status = false;
		$accessToken = '';
		$headers = apache_request_headers();
		
		if (isset($headers['accesstoken'])) {
			if (!empty($headers['accesstoken'])) {
				$accessToken = $headers['accesstoken'];
			}
		}

		if (isset($headers['Accesstoken']) && empty($accessToken)) {
			if (!empty($headers['Accesstoken'])) {
				$accessToken = $headers['Accesstoken'];

			}
		}

		if (!empty($accessToken)) 
		{
			$result = SessionManager::Join('user_table','session_manager.user_id','user_table.id')
											->where('session_manager.user_id',$user_id)
											->where('session_id',$accessToken)
											->where('session_manager.is_logged',1)
											->where('session_manager.device_type','mobile')
											->where('user_table.publish',1)
											->first()->toArray();
											echo "adss";
			
			if (!empty($result)) {
				$status = $result['id'];
			}
		}
		return $status;
	}
	public static function checkAccessTokenAdmin($user_id) 
	{
		$status = false;
		$accessToken = '';
		$headers = apache_request_headers();
		
		if (isset($headers['accesstoken'])) {
			if (!empty($headers['accesstoken'])) {
				$accessToken = $headers['accesstoken'];
			}
		}

		if (isset($headers['Accesstoken']) && empty($accessToken)) {
			if (!empty($headers['Accesstoken'])) {
				$accessToken = $headers['Accesstoken'];

			}
		}

		if (!empty($accessToken)) 
		{
			$result = SessionManager::Join('admin_table','session_manager.user_id','admin_table.id')
											->where('session_manager.user_id',$user_id)
											->where('session_id',$accessToken)
											->where('session_manager.is_logged',1)
											->where('session_manager.device_type','Third Party Web')
											->where('admin_table.publish',1)
											->first();

			
			if (!empty($result)) {
				$status = array('id'=> $result['id'],'session_id'=> $result['session_id'],'site_id'=> $result['site_id']);
			}
		}
		return $status;
	}

	public static function checkAccessTokenInstitute($user_id) 
	{
		$status = false;
		$accessToken = '';
		$headers = apache_request_headers();
		
		if (isset($headers['accesstoken'])) {
			if (!empty($headers['accesstoken'])) {
				$accessToken = $headers['accesstoken'];
			}
		}

		if (isset($headers['Accesstoken']) && empty($accessToken)) {
			if (!empty($headers['Accesstoken'])) {
				$accessToken = $headers['Accesstoken'];

			}
		}

		if (!empty($accessToken)) 
		{
			$result = SessionManager::Join('institute_table','session_manager.user_id','institute_table.id')
											->where('session_manager.user_id',$user_id)
											->where('session_id',$accessToken)
											->where('session_manager.is_logged',1)
											->where('session_manager.device_type','mobile-institute')
											->where('institute_table.publish',1)
											->first()->toArray();

			if (!empty($result)) {
				$status = $result['session_id'];
			}
		}
		return $status;
	}

	// Fetch Session Id
	public static function fetchSessionId($user_id) 
	{
		$status = false;
		$accessToken = '';
		$headers = apache_request_headers();
		
		if (isset($headers['accesstoken'])) {
			if (!empty($headers['accesstoken'])) {
				$accessToken = $headers['accesstoken'];
			}
		}

		if (isset($headers['Accesstoken']) && empty($accessToken)) {
			if (!empty($headers['Accesstoken'])) {
				$accessToken = $headers['Accesstoken'];

			}
		}

		if (!empty($accessToken)) 
		{
			
			$result = SessionManager::where('session_manager.user_id',$user_id)
											->where('session_id',$accessToken)
											->where('device_type','Third Party Web')
											->where('session_manager.is_logged',1)
											->first()->toArray();
			if (!empty($result)) {
				$status = $result['id'];
			}
		}
		return $status;
	}
	// generate Access token
	public static function generateAccessToken() 
	{
		$flag = 0;
		do {
			$access_token = Str::random(40);
			$isUsed = SessionManager::where('session_id', $access_token)->get()->toArray(); 
			if (!$isUsed) 
			{
				$flag = 1;
			}

		} while ($flag == 0);

		return $access_token;
	}

	public static function insertTracker($requestUrl, $requestMethod, $requestParameters,$response,$status) 
	{
		//fetching headers
		$headers = apache_request_headers();
		
		$headerParameters = array();
		if (isset($headers['apikey'])) {
			$headerParameters['apikey'] = $headers['apikey'];
		}
		if (isset($headers['Apikey'])) {
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
		//$executionTime = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
		$result = new ApiTracker();
		$result->client_ip = $client_ip;
		$result->request_url = $requestUrl;
		$result->request_method = $requestMethod;
		$result->status = $status;
		$result->header_parameters = $headerParameters;
		$result->request_parameters = $requestParameters;
		$result->response_parameters = $response;
		$result->created = $dateTime;
		$result->save(); 
		return $result['id'];
	}
	public static function fetchUserId()
	{	
		$user_id = false;
		$accesstoken = '';
		// get accesstoken from header
        $headers = apache_request_headers();
       	if (isset($headers['accesstoken'])) {
			if (!empty($headers['accesstoken'])) {
				$accesstoken = $headers['accesstoken'];
			}
		}

		if (isset($headers['Accesstoken']) && empty($accessToken)) {
			if (!empty($headers['Accesstoken'])) {
				$accesstoken = $headers['Accesstoken'];

			}
		}
        // to fetch user_id 
        $result = SessionManager::where('session_id',$accesstoken)
                                            ->first();
        if (!empty($result)) {                                    
        $user_id = $result['user_id'];
    	}

        return $user_id;
	}

	public static function getMessage($data)
	{

		$array =(array)$data;
        $first_key=key($array);
        $array_2=$array[$first_key];
        $first_key_2=key($array_2);
        $array_3=$array_2[$first_key_2];
        return $array_3[0];

	}

	public static function apiResponse($message,$header,$requestUrl,$requestMethod,$requestParameter){
		
        if ($message['status']==200) {
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }

        $id = Self::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);
        $response_time = microtime(true) - LARAVEL_START;
        ApiTracker::where('id',$id)->update(['response_time'=>$response_time]);
        if($header){
             $response= response($message);
             foreach ($header as $key => $value) {
             	$response->header($key,$value);
             }
             return $response;
        }else{
            return response($message);
        }
	}

	public static function encrypt_adv($plaintext, $key) {
	$ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
	$iv = openssl_random_pseudo_bytes($ivlen);
	$ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
	$ciphertext = base64_encode($iv . /*$hmac.*/$ciphertext_raw);
	$ciphertext = str_replace('/', '[slash]', $ciphertext);
	$ciphertext = str_replace('+', '[plus]', $ciphertext);
	return $ciphertext;
}

	public static function decrypt_adv($ciphertext, $key) {
	$ciphertext = str_replace('[slash]', '/', $ciphertext);
	$ciphertext = str_replace('[plus]', '+', $ciphertext);
	$c = base64_decode($ciphertext);
	$ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
	$iv = substr($c, 0, $ivlen);
	$ciphertext_raw = substr($c, $ivlen);

	$original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
	return $original_plaintext;
}

}