<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\Transactions;
use App\models\User;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use App\models\ApiTracker;

class TransactionController extends Controller
{
    
    public function transaction(Request $request)
    {
    	$data = $request->post();

    	
        if (ApiSecurityLayer::checkAuthorization()) 
        {   
            // to fetch user id
            $user_id = ApiSecurityLayer::fetchUserId();
            if (!empty($user_id) && ApiSecurityLayer::checkAccessToken($user_id))
            {
                $rules = [
                    'action' => 'required',
                ];

                $validator = \Validator::make($data,$rules);

                
                if ($validator->fails()) {
                    return response()->json(['success' => false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
                }

            	switch ($request->get('action')) 
            	{
        	        case 'create':
        	        		$message = insertTransaction($data);
        	            	
        	            break;

        	        case 'read':
        	            	$message = getTransaction($data);
        	            break;

        			case 'update':
        	            	$message = updateTransaction($data);
        	            break;

        	        case 'delete':
        	            	$message = deleteTransaction($data);
        	            break; 

        	        case 'check':
        	            	$message = checkTransaction($data);
        	            break;   

        	        default:
        	        	$message = ['success' => false,'service'=>'Transaction','message'=>'Action param invalid','status'=>400]; 
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

    function insertTransaction($data)
    {    	
    	$rules = [
            'trans_id_ref' => 'required',
            'trans_id_gateway' => 'required',
            'payment_mode' => 'required',
            'amount' => 'required',
            'additional' => 'required',
            'user_id' => 'required',
            'student_key' => 'required',
            'trans_status' => 'required',
            'transaction_date' => 'required',
        ];

        $validator = \Validator::make($data,$rules);

        
        if ($validator->fails()) {
            return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
        }

    	$arr = explode('_', $data['trans_id_ref']);
				
		if($arr[1] == 'PT')
		{
			$pgid = 1;
		}
		if($arr[1] == 'PU')
		{
			$pgid = 2;
		}

		$transaction = new Transactions();
		
		$transaction['trans_id_ref'] = $data['trans_id_ref'];
		$transaction['trans_id_gateway'] = $data['trans_id_gateway'];
		$transaction['payment_mode'] = $data['payment_mode'];
		$transaction['amount'] = $data['amount'];
		$transaction['user_id'] = $data['user_id'];
		$transaction['student_key'] = $data['student_key'];
		$transaction['transaction_date'] = $data['transaction_date'];
		$transaction['trans_status'] = $data['trans_status'];
		$transaction->save();

		if ($transaction['trans_status'] == '1') 
		{
			$userData = User::where('id',$data['user_id']);

			$array = explode('_',$data['trans_id_ref']);
				
				if($array[1] == 'PU'){
					$gate = 'PAYU MONEY';
				}else{
					$gate = 'PAYTM';
				}
				
				if($array[1] == 'IAP'){
					$gate = 'Apple In-app purchase';
				}
		}

        $message = array('success' => true,'status'=>200, 'message' => 'Transaction inserted successfully','trans_status' =>$data['trans_status']);
        
        return $message;

    }

    function getTransaction($data)
    {
    	$rules = [
            'user_id' => 'required',
        ];

        $validator = \Validator::make($data,$rules);

        
        if ($validator->fails()) {
            return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
        }

    	
    	if ($data['user_id']) {
    		$transaction = Transactions::where('user_id',$data['user_id'])->get()->toArray();
            if (!empty($transaction)) {
                $message = array('success' => true,'status'=>200, 'message' => 'Success','data' =>$transaction);
            }
    		else
            {
                $message = array('success' => false,'status'=>404, 'message' => 'No Data found');
            }
    	}
        return $message;
    }

    function updateTransaction($data)
    {
    	$rules = [
            'trans_id_ref' => 'required',
        ];

        $validator = \Validator::make($data,$rules);

        
        if ($validator->fails()) {
            return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
        }

    	if ($data['trans_id_ref']) {
    		$transaction = Transactions::where('trans_id_ref',$data['trans_id_ref'])
                                                ->update(['publish' => '0']);
            $message = array('success' => true,'status'=>200, 'message' => 'Transaction Updated successfully');
            return $message;
		
    	}
    }

   	function checkTransaction($data)
    {
    	$rules = [
            'student_key' => 'required',
            'user_id' => 'required'
        ];

        $validator = \Validator::make($data,$rules);

        
        if ($validator->fails()) {
            return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
        }

    	if($data['student_key'] && $data['user_id'])
    	{
    		$transaction = Transactions::where('trans_status','1')
    										->where('student_key',$data['student_key'])
    										->where('user_id',$data['user_id'])
    										->where('publish','1')
    										->get()->toArray();

    		if (count($transaction)>=1) {
    			$payment_status = true;
			}
			else
			{
					$payment_status = false;
			}

            $message = array('success' => true,'status'=>200, 'message' => 'Transaction Done','status' =>$payment_status);
			
    	}
        else
        {
            $message = array('success' => false,'status'=>404, 'message' => 'No Data Found');
        }
        return $message;
    }

