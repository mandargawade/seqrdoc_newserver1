<?php

namespace App\Http\Controllers\webapp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\TransactionsEvent;
use Event;

class PaymentTransactionController extends Controller
{
    public function addTransaction(Request $request){
    	$payment_params = $request->all();

         
    	if(isset($payment_params['trans_id_ref']) && isset($payment_params['trans_id_gateway']) && isset($payment_params['payment_mode']) && isset($payment_params['amount']) && isset($payment_params['additional']) && isset($payment_params['user_id']) && isset($payment_params['student_key'])){

            
    		$event = Event::dispatch(new TransactionsEvent($payment_params));
    		 dd($event);
       		return response()->json(['data'=>$event]);	
    	
    	}else{
    		$message = array('service' => 'Transaction', 'message' => 'Data params missing', 'status' => false);
    	}
    	return $message;
	}

	
}

