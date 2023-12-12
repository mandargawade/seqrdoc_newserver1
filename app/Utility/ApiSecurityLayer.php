<?php

namespace App\Utility;
use Illuminate\Support\Str;
use App\models\SessionManager;
use App\models\ApiTracker;
use App\models\User;
define('apiKey', 'GSka~2nu@D,knVOfz{+/RL1WMF{bka');
// used for Api security 

class ApiSecurityLayer
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

			$result = SessionManager::Join('user_table','session_manager.user_id','user_table.id')
											->where('session_manager.user_id',$user_id)
											->where('session_id',$accessToken)
											->where('session_manager.is_logged',1)
											->where('session_manager.device_type','mobile')
											->where('user_table.publish',1)
											->first();

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
											->where('session_manager.device_type','mobile')
											->where('admin_table.publish',1)
											->first();

			if (!empty($result)) {
				$status = $result['session_id'];
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
											->first();

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
											->where('session_manager.is_logged',1)
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



		$result = new ApiTracker;
		$result->client_ip = $client_ip;
		$result->request_url = $requestUrl;
		$result->request_method = $requestMethod;
		$result->status = $status;
		$result->header_parameters = $headerParameters;
		$result->request_parameters = $requestParameters;
		$result->response_parameters = $response;
		$result->created = $dateTime;
		$result->save();

		return $result->id;

		
	}
	public static function fetchUserId()
	{	
		$user_id = 0;
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

	// getUserNameForDeletedUser
	public static function getUserNameForDeletedUser() 
	{
		$flag = 0;

		$deletedData=User::where('username', 'like', '%delete%')->orderBy('updated_at', 'DESC')->get()->toArray();
		//print_r($deletedData);
    	if($deletedData){
    		$username=$deletedData[0]['username'];

    	}else{
    		$username='delete1';
    	}
		do {


			$isUsed = User::where('username', $username)->get()->toArray(); 
			if (!$isUsed) 
			{
				$flag = 1;
			}else{
			$strarr = str_split($username,6);
			$username='delete'.($strarr[1]+1);
			}
		} while ($flag == 0);

		return $username;
	}

}