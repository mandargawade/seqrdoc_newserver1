<?php

namespace App\Utility;
use Illuminate\Support\Str;
use App\models\SuperSessionManager;
use App\models\SuperApiTracker;
	
define('apiKey', '~PLJXP]T2~r8>4-SX!ZL');
// used for Api security 

class SuperApiSecurityLayer
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

		if (!empty($accessToken)&&!empty($user_id)) 
		{	

			$result = SuperSessionManager::Join('user_table','session_manager.user_id','user_table.id')
											->where('session_manager.user_id',$user_id)
											->where('session_id',$accessToken)
											->where('session_manager.is_logged',1)
											->where('user_table.publish',1)
											->first();
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
			$isUsed = SuperSessionManager::where('session_id', $access_token)->get()->toArray(); 
			if (!$isUsed) 
			{
				$flag = 1;
			}

		} while ($flag == 0);

		return $access_token;
	}

	public static function insertTracker($requestUrl, $function_name, $requestMethod, $requestParameters,$response,$status) 
	{
		//fetching headers
		$headers = apache_request_headers();
		
		$headerParameters = array();
		if (isset($headers['apikey'])) {
			$headerParameters['apikey'] = $headers['apikey'];
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

		$result = new SuperApiTracker();
		$result->client_ip = $client_ip;
		$result->request_url = $requestUrl;
		$result->function_name = $function_name;
		$result->request_method = $requestMethod;
		$result->status = $status;
		$result->header_parameters = $headerParameters;
		$result->request_parameters = $requestParameters;
		$result->response_parameters = $response;
		$result->created = $dateTime;
		$result->updated = $dateTime;
		$result->save(); 
		return $result->id;

	}
	
}