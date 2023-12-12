<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\PaymentGatewayConfig;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use App\models\ApiTracker;

class PaymentGatewayConfigController extends Controller
{
    public function get_paymentgateway(Request $request)
    {
    	$data = $request->post();
    	    
        if (ApiSecurityLayer::checkAuthorization()) 
        {   
            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id)) 
            {
                $rules = [
                    'pg_id' => 'required',
                ];

                $messages = [
                    'pg_id.required' => 'Payment Gateway id required',
                ];

                $validator = \Validator::make($request->post(),$rules,$messages);

                if ($validator->fails()) {
                    return response()->json(['success'=>false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                }
                
                $pg_config_data = PaymentGatewayConfig::where('pg_id',$data['pg_id'])
                	        								->get()
                	        								->toArray();
                if(!empty($pg_config_data))
                {
                    $message = array('success' => true,'status'=>200, 'message' => 'Success','data' => $pg_config_data);
                }

                else
                {
                    $message = array('success' => false,'status'=>404, 'message' => 'No Data Found','data');
                }
            }
            else
            {
                $message = array('success' => false,'status'=>400, 'message' => 'User id is missing or You dont have access to this api.');
            }
        }
        else
        {
            $message = array('success' => false,'status'=>403, 'message' => 'Access forbidden.');
        }

        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $data;

        if ($message['success']==true) {
            $status = 'success';
        }
        else
        {
            $status = 'failed';
        }

        $api_tracker_id = ApiSecurityLayer::insertTracker($requestUrl,$requestMethod,$requestParameter,$message,$status);
        $response_time = microtime(true) - LARAVEL_START;
        ApiTracker::where('id',$api_tracker_id)->update(['response_time'=>$response_time]);
        return $message;
    }
}
