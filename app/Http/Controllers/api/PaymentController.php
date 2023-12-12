<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\Site;
use App\models\Transactions;
use App\models\SbTransactions;
use App\models\PrintingDetail;
use App\models\SbPrintingDetail;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayer;
use Illuminate\Support\Facades\DB;
use App\models\ApiTracker;

class PaymentController extends Controller
{

    function sanitizeVar($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
	
	public function instamojoPayment(Request $request){
  		
  		
 	  $data = $request->post();  
      if (ApiSecurityLayer::checkAuthorization())
        {
            $rules = [
                'key' => 'required',
                'device_type' => 'required',
                'user_id' => 'required',
                'scan_id' => 'required'
            ];

            $messages = [
                'key.required' => 'Key is required',
                'device_type.required' => 'Device type is required',
                'user_id.required' => 'User id is required',
                'scan_id.required' => 'scan id is required'
            ];

            $validator = \Validator::make($request->post(),$rules,$messages);
                
            if ($validator->fails()) {
                return response()->json([false,'status'=>400,'message'=>ApiSecurityLayer::getMessage($validator->errors()),$validator->errors()],400);
            }
  
            $user_id = ApiSecurityLayer::fetchUserId();

            if (!empty($data['key'])) {
                if (!empty($user_id) /*&& ApiSecurityLayer::checkAccessToken($user_id)*/ && $user_id == $data['user_id'])
                {
                    $HTTP_HOST = $_SERVER['HTTP_HOST'];
                    $PHP_SELF = $_SERVER['PHP_SELF'];

                    if(array_key_exists('HTTPS', $_SERVER) && $_SERVER["HTTPS"] == "on"){
                        $server_type = 'https';
                    } else {
                        $server_type = 'http';
                    }

                    $domain = \Request::getHost();
                    $subdomain = explode('.', $domain);

                    $payment = DB::table('payment_gateway')
                                ->select('*')
                                ->join('payment_gateway_config', 'payment_gateway.id', '=', 'payment_gateway_config.pg_id')
                                ->where('payment_gateway.pg_name', '=', 'instaMojo')
                                ->get();

                    $user = DB::table('user_table')
                                ->select('*')
                                ->where('user_table.id', '=', $data['user_id'])
                                ->get();            

                    $amount = $payment[0]->amount;
                    $fullname = $user[0]->fullname;
                    $email_id = $user[0]->email_id;
                    $mobile_no = $user[0]->mobile_no;

                    if($payment[0]->crendential)
                    {
                        $X_Api_Key = $payment[0]->merchant_key;
                        $X_Auth_Token = $payment[0]->salt;
                        $endpoint = 'https://www.instamojo.com/api/1.1/';
                    }else{
                        $X_Api_Key = $payment[0]->test_merchant_key;
                        $X_Auth_Token = $payment[0]->test_salt;
                        $endpoint = 'https://test.instamojo.com/api/1.1/payment-requests/';
                    }           

                    $ch = curl_init();

                    curl_setopt($ch, CURLOPT_URL, $endpoint);
                    curl_setopt($ch, CURLOPT_HEADER, FALSE);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                    curl_setopt($ch, CURLOPT_HTTPHEADER,
                                array("X-Api-Key:".$X_Api_Key,
                                      "X-Auth-Token:".$X_Auth_Token));

                    $payload = Array(
                        'purpose' => 'For Verification of CEDP Document',
                        'amount' => $amount,
                        'phone' => $mobile_no,
                        'buyer_name' => $fullname,
                        'redirect_url' => $server_type.'://'.$HTTP_HOST.'/api/instamojoResponse?key='.$data['key']."&id=".$data['user_id']."&scan_id=".$data['scan_id'],
                        'email' => $email_id,
                        'allow_repeated_payments' => false
                    );
                    //echo $endpoint;
                    

                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                    $response = curl_exec($ch);
                    curl_close($ch); 
                    $response = json_decode($response);
                    
                    $message = array('status' => 200, 'message' => "success", "URL" => $response->payment_request->longurl);
                }else{
                    $message = array('status'=>403, 'message' => 'User id is missing or You dont have access to this api.');
                }
            }else{
                $message = array('status'=>422, 'message' => 'Required parameters not found.');
            }
        }else{
            $message = array('status'=>403, 'message' => 'Access forbidden.');
        }


        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $data;


        if ($message['status']==200) {
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

    public function instamojoResponse(Request $request){

        $hostUrl = \Request::getHttpHost();
        $subdomain = explode('.', $hostUrl);

        $site = Site::select('site_id')->where('site_url',$hostUrl)->first();
        $site_id = $site['site_id'];


        $payment = DB::table('payment_gateway')
                    ->select('*')
                    ->join('payment_gateway_config', 'payment_gateway.id', '=', 'payment_gateway_config.pg_id')
                    ->where('payment_gateway.pg_name', '=', 'instaMojo')
                    ->get();

        if($payment[0]->crendential)
        {
            $X_Api_Key = $payment[0]->merchant_key;
            $X_Auth_Token = $payment[0]->salt;
            $endpoint = 'https://www.instamojo.com/api/1.1/';
        }else{
            $X_Api_Key = $payment[0]->test_merchant_key;
            $X_Auth_Token = $payment[0]->test_salt;
            $endpoint = 'https://test.instamojo.com/api/1.1/payment-requests/';
        } 

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://test.instamojo.com/api/1.1/payments/'.$request->get('payment_id'));
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array("X-Api-Key:".$X_Api_Key,
                          "X-Auth-Token:".$X_Auth_Token));

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch); 

        if ($err) {
            echo "Payment Failed!..";
        } else {
            $data = json_decode($response,true);
        }

        if($data['success']) {
            if($data['payment']['status'] == 'Credit') {
                
                $ORDER_ID = 'SeQR_IM_'.strtotime("now");
                $trans_id_gateway = $data['payment']['payment_id'];
                $payment_mode = 'INMJ';
                $amount = $data['payment']['amount'];
                $additional = 0;
                $user_id = $request->get('id');
                $email = $data['payment']['buyer_email'];
                $phone = $data['payment']['buyer_phone'];
                $student_key = $request->get('key');


                $transaction = new Transactions();
                $transaction['pay_gateway_id'] = 10; //Instamojo id from master table
                $transaction['trans_id_ref'] = $ORDER_ID;
                $transaction['trans_id_gateway'] = $trans_id_gateway;
                $transaction['payment_mode'] = $payment_mode;
                $transaction['amount'] = $amount;
                $transaction['user_id'] = $user_id;
                $transaction['student_key'] = $student_key;
                $transaction['trans_status'] = 1;
                $transaction['publish'] = 1;
                $transaction['created_at'] = date('Y-m-d H:i:s');
                $transaction['site_id'] = $site_id;
                $transaction['scan_id'] = $request->get('scan_id');
                $transaction->save();


            echo '<!DOCTYPE html>
                        <html>
                        <head>
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                        <style>
                            table {
                              border-collapse: collapse;
                              border-spacing: 0;
                              width: 100%;
                              border: 1px solid #ddd;
                            }

                            th, td {
                              text-align: left;
                              padding: 8px;
                              border: 1px solid #ddd;
                            }

                            #parent{    
                                text-align: center;
                            }

                            #child {
                                margin: 0 auto;
                                display: inline-block;
                            }
                            .button {
                              background-color: #4CAF50; /* Green */
                              border: none;
                              color: white;
                              padding: 15px 32px;
                              text-align: center;
                              text-decoration: none;
                              display: inline-block;
                              font-size: 16px;
                              margin: 4px 2px;
                              cursor: pointer;
                              border-radius: 9px;
                            }
                            .button2 {background-color: #008CBA;} /* Blue */
                        </style>
                        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
                        <script src="extensions/mobile/bootstrap-table-mobile.js"></script>
                        </head>
                        <body>

                        <div class="container" id="parent">
                            <div class="row">
                                <div class="col-lg-12" style="color:green"><h1>Transaction Successful</h1></div>
                                <div class="row" style="text-align:center;">
                                    <div class="col-md-4 col-md-offset-4"  id="child"><h3s>Your transaction has completed successfully and details are provided below.</h3></div>
                                </div>
                            </div>
                        </div>
                        <br>
                        <div style="overflow-x:auto;" > 
                          <table>
                            <tr>
                                <td>Transaction ID</td>
                                <td class="text-left">'.$ORDER_ID.'</td>
                            </tr>
                            <tr>
                                <td>Gateway ID</td>
                                <td class="text-left">'.$trans_id_gateway.'</td>
                            </tr>
                            <tr>
                                <td>Date</td>
                                <td class="text-left">'.date('d-m-Y').'</td>
                            </tr>
                            <tr>
                                <td>Mode</td>
                                <td class="text-left">'.$payment_mode.'</td>
                            </tr>
                            <tr>
                                <td>Email</td>
                                <td class="text-left">'.$email.'</td>
                            </tr>
                            <tr>
                                <td>Phone</td>
                                <td class="text-left">'.$phone.'</td>
                            </tr>
                            <tr>
                                <td>Amount</td>
                                <td class="text-left">'.$amount.'<i class="fa fa-rupee"></i></td>
                            </tr>
                          </table>
                          <br>
                          <div style="text-align:center;">
                                <p>Click the below close button to view the certificate.</p>
                                <p><button onclick="doSomthing();" class="button button2">Close</button></p>
                          </div>
                        </div>
                        </body>
                        </html>
                        <script>
                            function doSomthing() {
                                window.ReactNativeWebView.postMessage("'.$data['payment']['status'].'");
                              }
                        </script>';       

            } else {
                $ORDER_ID = 'SeQR_IM_'.strtotime("now");
                $trans_id_gateway = $data['payment']['payment_id'];
                $payment_mode = 'INMJ';
                $amount = $data['payment']['amount'];
                $additional = 0;
                $user_id = $request->get('id');
                $email = $data['payment']['buyer_email'];
                $phone = $data['payment']['buyer_phone'];
                $student_key = $request->get('key');


                $transaction = new Transactions();
                $transaction['pay_gateway_id'] = 10; //Instamojo id from master table
                $transaction['trans_id_ref'] = $ORDER_ID;
                $transaction['trans_id_gateway'] = $trans_id_gateway;
                $transaction['payment_mode'] = $payment_mode;
                $transaction['amount'] = $amount;
                $transaction['user_id'] = $user_id;
                $transaction['student_key'] = $student_key;
                $transaction['trans_status'] = 0;
                $transaction['publish'] = 1;
                $transaction['scan_id'] = $request->get('scan_id');
                $transaction['site_id'] = $site_id;
                $transaction['created_at'] = date('Y-m-d H:i:s');
                $transaction->save();

                

                  echo '<!DOCTYPE html>
						<html>
						<head>
						<meta name="viewport" content="width=device-width, initial-scale=1">
						<style>
							table {
							  border-collapse: collapse;
							  border-spacing: 0;
							  width: 100%;
							  border: 1px solid #ddd;
							}

							th, td {
							  text-align: left;
							  padding: 8px;
							  border: 1px solid #ddd;
							}

							#parent{    
							    text-align: center;
							}

							#child {
							    margin: 0 auto;
							    display: inline-block;
							}

							.button {
							  background-color: #4CAF50; /* Green */
							  border: none;
							  color: white;
							  padding: 15px 32px;
							  text-align: center;
							  text-decoration: none;
							  display: inline-block;
							  font-size: 16px;
							  margin: 4px 2px;
							  cursor: pointer;
							  border-radius: 9px;
							}
							.button2 {background-color: #008CBA;} /* Blue */
						</style>
						
						<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
						<script src="extensions/mobile/bootstrap-table-mobile.js"></script>
						</head>
						<body>

						<div class="container" id="parent">
						    <div class="row">
						        <div class="col-lg-12" style="color:red"><h1>Transaction Failed</h1></div>
						        <div class="row" style="text-align:center;">
						            <div class="col-md-4 col-md-offset-4"  id="child"><h3s>Your transaction has failed and details are provided below.</h3></div>
						        </div>
						    </div>
						</div>
						<br>
						<div style="overflow-x:auto;" >	
						  <table>
						    <tr>
						        <td>Transaction ID</td>
						        <td class="text-left">'.$ORDER_ID.'</td>
						    </tr>
						    <tr>
						        <td>Date</td>
						        <td class="text-left">'.date('d-m-Y').'</td>
						    </tr>
						    <tr>
						        <td>Email</td>
						        <td class="text-left">'.$email.'</td>
						    </tr>
						    <tr>
						        <td>Phone</td>
						        <td class="text-left">'.$phone.'</td>
						    </tr>
						    <tr>
						        <td>Amount</td>
						        <td class="text-left">'.$amount.'<i class="fa fa-rupee"></i></td>
						    </tr>
						  </table>
						  <br>
						  <div style="text-align:center;">
						  		<p>Click the below close button to return to previous page."</p>
						        <p><button onclick="doSomthing();" class="button button2">Close</button></p>
						  </div>
						</div>
						</body>
						</html>
						<script>
							function doSomthing() {
						        window.ReactNativeWebView.postMessage("failed");
						      }
						</script>';
            }
        }else{
            echo '<!DOCTYPE html>
                        <html>
                        <head>
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                        <style>
                            table {
                              border-collapse: collapse;
                              border-spacing: 0;
                              width: 100%;
                              border: 1px solid #ddd;
                            }

                            th, td {
                              text-align: left;
                              padding: 8px;
                              border: 1px solid #ddd;
                            }

                            #parent{    
                                text-align: center;
                            }

                            #child {
                                margin: 0 auto;
                                display: inline-block;
                            }
                            .button {
                              background-color: #4CAF50; /* Green */
                              border: none;
                              color: white;
                              padding: 15px 32px;
                              text-align: center;
                              text-decoration: none;
                              display: inline-block;
                              font-size: 16px;
                              margin: 4px 2px;
                              cursor: pointer;
                              border-radius: 9px;
                            }
                            .button2 {background-color: #008CBA;} /* Blue */
                        </style>
                        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
                        <script src="extensions/mobile/bootstrap-table-mobile.js"></script>
                        </head>
                        <body>

                        <div class="container" id="parent">
                            <div class="row">
                                <div class="col-lg-12" style="color:red"><h1>OOPS Something Went Wrong!!!</h1></div>
                                <div class="row" style="text-align:center;">
                                    <div class="col-md-4 col-md-offset-4"  id="child"><h3s>Your transaction has failed.</h3></div>
                                </div>
                            </div>
                        </div>
                        <br>
                        <div style="overflow-x:auto;" >
                          <br>
                          <div style="text-align:center;">
                                <p>Click the below close button to view the certificate.</p>
                                <p><button onclick="doSomthing();" class="button button2">Close</button></p>
                          </div>
                        </div>
                        </body>
                        </html>
                        <script>
                            function doSomthing() {
                                window.ReactNativeWebView.postMessage("something went wrong");
                              }
                        </script>'; 
        }

    }

    public function demo1()
    {
    	echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
					  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
					  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>';

        echo '<div class="row">
						<div class="col-xs-12 col-md-6 col-md-offset-3 text-center">
							<h2>Transaction Failed</h2>
							<h5>Your transaction has failed and details are provided below.</h5>

							<table class="table table-bordered table-hover">
								<tr>
									<th>Transaction ID</th>
									<td class="text-left">$ORDER_ID</td>
								</tr>
								<tr>
									<th>Gateway ID</th>
									<td class="text-left">$trans_id_gateway</td>
								</tr>
								<tr>
									<th>Date</th>
									<td class="text-left">'.date('d-m-Y').'</td>
								</tr>
								<tr>
									<th>Mode</th>
									<td class="text-left">$payment_mode</td>
								</tr>
								<tr>
									<th>Email</th>
									<td class="text-left">$email</td>
								</tr>
								<tr>
									<th>Phone</th>
									<td class="text-left">$phone</td>
								</tr>
								<tr>
									<th>Amount</th>
									<td class="text-left">$amount<i class="fa fa-rupee"></i></td>
								</tr>
							</table>
							
							<p>You can close this tab and return to previous page. </p>
							<p><a href="#" onclick="window.close()" class="btn btn-theme" style="color:blue"> Close</a></p>
						</div>
				</div>';
    }

    public function demo(Request $request){
    	$data['url'] = 'https://cedp.seqrdoc.com/api/demo1';
    	$message = array('status'=>200, 'message' => 'success', 'URL' => $data );
    	return $message;
    }

}
