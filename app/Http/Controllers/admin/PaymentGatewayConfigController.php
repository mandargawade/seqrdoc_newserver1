<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 23/11/2019
 *   Use   : show and update and get information of PaymentGatewayConfig    
 *
**/
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentGatewayConfigRequest;
use App\models\PaymentGateway;
use App\models\PaymentGatewayConfig;
use Illuminate\Http\Request;
use Auth;
class PaymentGatewayConfigController extends Controller
{
     /**
     * Display a listing of the PaymentGatewayConfig Detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
     //show payment table
    public function index(Request $request)
    {
        if($request->ajax())
        {
           $site_id=Auth::guard('admin')->user()->site_id;
           $pg_data= PaymentGateway::select('pg_name','id')->where('site_id',$site_id)->where('status',1)->get()->toArray();
           return $pg_data;
        }
    	return view('admin.paymentGatewayConfig.index');
    }

     /**
     * Display the specified PaymentGatewayConfig Detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
    	$request_data=$request->all();
    	
        if(!empty($request_data['pg_id']))
        {
        	$pg_config_data=paymentGatewayConfig::select('pg_status','amount','crendential')
        	        ->where('pg_id',$request_data['pg_id'])
        	        ->get()
        	        ->toArray();

        	 $pg_config_data=head($pg_config_data);
        	 
        	 return $pg_config_data;     
        }
    }
    
    /**
     * Update the specified PaymentGatewayConfig in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    //update payement details in paymentgetwayconfig 
    public function update(PaymentGatewayConfigRequest $request)
    {
        $pg_status=$request['opt_pg'];
        $amount=$request['amt_charge'];
        $crendential=$request['opt_crenden'];
        
        $pg_update=PaymentGatewayConfig::where('pg_id',$request['opt_wl'])
                  ->update(['pg_status'=>$pg_status,'amount'=>$amount,
                  	'crendential'=>$crendential]);

        $pg_data=PaymentGateway::where('id',$request['opt_wl'])
                        ->update(['status'=>$pg_status]);

        //check is paytem request  
        $nameExit=PaymentGateway::select('pg_name')->where('id',$request['opt_wl'])->first();
       
       // PaymentGateway name available or not
       if(!empty($nameExit['pg_name']))
       { 
            $nameExit=strtolower($nameExit['pg_name']);

        // request is paytem    
        if($nameExit==="paytm")
        {
         
            $data=PaymentGateway::select('payment_gateway.pg_name','payment_gateway_config.crendential')
                             ->join('payment_gateway_config','payment_gateway.id','payment_gateway_config.pg_id')
                             ->where('payment_gateway.id',$request['opt_wl'])
                             ->where('payment_gateway.status',1)
                             ->where('payment_gateway.publish',1)
                             ->first(); 

           // PaymentGateway production crendential
           if($data['crendential']==1)
           {
                  $credential=[
                     'YOUR_MERCHANT_ID'=>'YOUR_MERCHANT_ID',
                     'YOUR_MERCHANT_ID_value'=>'ketan1',
                     'YOUR_MERCHANT_KEY'=>'YOUR_MERCHANT_KEY',
                     'YOUR_MERCHANT_KEY_value'=>'ketan12',
                     'YOUR_WEBSITE'=>'YOUR_WEBSITE',
                     'YOUR_WEBSITE_value'=>'ketan123',
                     'env'=>'env',
                     'env_value'=>'production',
                   ];
               // .env file set production crendential   
               $this->PgCredentialSet($credential); 
                               
           }
           // PaymentGateway local crendential 
           else if($data['crendential']==0){
               
                  $credential=[
                     'YOUR_MERCHANT_ID'=>'YOUR_MERCHANT_ID',
                     'YOUR_MERCHANT_ID_value'=>'DIY12386817555501617',
                     'YOUR_MERCHANT_KEY'=>'YOUR_MERCHANT_KEY',
                     'YOUR_MERCHANT_KEY_value'=>'bKMfNxPPf_QdZppa',
                     'YOUR_WEBSITE'=>'YOUR_WEBSITE',
                     'YOUR_WEBSITE_value'=>'DIYtestingweb',
                     'env'=>'env',
                     'env_value'=>'local',
                   ];
                // .env file set local crendential    
                $this->PgCredentialSet($credential);   
           }             

        }
      }
        // return response success
        return response()->json(['success'=>true]);      	      
    }

    /**
     * .env file set credential local or production 
     *
     * @param  array $credential
     * @return \Illuminate\Http\Response
     */

    //set paymentgetway credential
    public function PgCredentialSet(array $credential)
    {    
         // not empty check 
         if(!empty($credential))
         {
             $YOUR_MERCHANT_ID=$credential['YOUR_MERCHANT_ID'];
             $YOUR_MERCHANT_ID_value=$credential['YOUR_MERCHANT_ID_value'];
             $YOUR_MERCHANT_KEY=$credential['YOUR_MERCHANT_KEY'];
             $YOUR_MERCHANT_KEY_value=$credential['YOUR_MERCHANT_KEY_value'];
             $YOUR_WEBSITE=$credential['YOUR_WEBSITE'];
             $YOUR_WEBSITE_value=$credential['YOUR_WEBSITE_value'];
             $env=$credential['env'];
             $env_value=$credential['env_value'];     

           // path .env file
           $path = base_path('.env');
            
           // check .env exit or not
           if(file_exists($path)) {
             
              //replace credential on .env file
              file_put_contents($path, str_replace(
                  $env . '=' . env($env), $env . '=' . $env_value, file_get_contents($path)
                ));
              file_put_contents($path, str_replace(
                  $YOUR_MERCHANT_ID . '=' . env($YOUR_MERCHANT_ID), $YOUR_MERCHANT_ID . '=' . $YOUR_MERCHANT_ID_value, file_get_contents($path)
               ));
              file_put_contents($path, str_replace(
               $YOUR_MERCHANT_KEY . '=' . env($YOUR_MERCHANT_KEY), $YOUR_MERCHANT_KEY . '=' . $YOUR_MERCHANT_KEY_value, file_get_contents($path)
             ));
             file_put_contents($path, str_replace(
              $YOUR_WEBSITE . '=' . env($YOUR_WEBSITE), $YOUR_WEBSITE . '=' . $YOUR_WEBSITE_value, file_get_contents($path)
            ));
           
         }
       }
     }
        
  
}
