<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\models\Transactions;
use Session;
use Helper;
use App\models\SystemConfig;
use App\models\SbTransactions;

class TransactionsJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $payment_params;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($payment_params)
    {
        $this->payment_params=$payment_params;   
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Request $equest)
    {

        $payment_params   = $this->payment_params;
        $trans_id_ref     = $this->sanitizeVar($payment_params['trans_id_ref']);
        $trans_id_gateway = $this->sanitizeVar($payment_params['trans_id_gateway']);
        $payment_mode     = $this->sanitizeVar($payment_params['payment_mode']);
        $amount           = $this->sanitizeVar($payment_params['amount']);
        $additional       = $this->sanitizeVar($payment_params['additional']);
        $user_id          = $this->sanitizeVar($payment_params['user_id']);
        $student_key      = $this->sanitizeVar($payment_params['student_key']);
        $trans_status     = $this->sanitizeVar($payment_params['trans_status']);
        $transaction_date = date('Y-m-d H:i:s');

        $exp_transaction_id = explode('_', $trans_id_ref);
        
        if($exp_transaction_id[1] == 'PT')
        {
         $pgid = 1;
        }
        if($exp_transaction_id[1] == 'PU')
        {
         $pgid = 2;
        }
        if($exp_transaction_id[1] == 'IM')
        {
         $pgid = 10;
        }

        $auth_site_id=\Auth::guard('webuser')->user()->site_id;
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        try {
            $site_id=null;
            $site_id=Helper::GetSiteId($_SERVER['SERVER_NAME']);
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $transactions = new SbTransactions;
            }
            else{
                $transactions = new Transactions;
            }
            $transactions->pay_gateway_id = $pgid;    
            $transactions->trans_id_ref = $trans_id_ref;    
            $transactions->trans_id_gateway = $trans_id_gateway;    
            $transactions->payment_mode = $payment_mode;    
            $transactions->amount = $amount;    
            $transactions->additional = $additional;    
            $transactions->user_id = $user_id;    
            $transactions->student_key = $student_key;   
            $transactions->trans_status = $trans_status;
            $transactions->site_id = $site_id;    
            $transactions->publish = 1;    
            $transactions->save();

            Session::forget('payment_key');
            $message = array('service' => 'Transaction', 'message' => 'Transaction inserted successfully', 'status' => true, 'trans_status' => $trans_status);

        } catch (Exception $e) {

            $message = array('service' => 'Transaction', 'message' => $e->getMessage(), 'status' => false);
        }
        
        
        return $message;

    }
    public function sanitizeVar($sanitizeVar){

        $sanitizeVar = trim($sanitizeVar);
        $sanitizeVar = stripslashes($sanitizeVar);
        $sanitizeVar = htmlspecialchars($sanitizeVar);

        return $sanitizeVar;
    }
}
