<?php

namespace App\Jobs;

use App\models\PaymentGateway;
use App\models\PaymentGatewayConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Auth;
class PaymentGatewayJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $pg_data;
    public function __construct($pg_data)
    {
       $this->pg_data=$pg_data;  
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $pg_data=$this->pg_data;
        $id=null;
        // check id empty or not
        if(!empty($pg_data['pg_id']))
        {
            $id=$pg_data['pg_id'];
        }
        
        // get Admin id
        $auth_id=Auth::guard('admin')->user()->id;
        $auth_site_id=Auth::guard('admin')->user()->site_id;    
        // save PaymentGateway data on database
        $pg_obj=PaymentGateway::firstOrNew(['id'=>$id]);
        $pg_obj->fill($pg_data);
        $pg_obj->updated_by=$auth_id;
        $pg_obj->site_id=$auth_site_id;
        $pg_obj->status=$pg_data['opt_status'];
        $pg_obj->save();
         
        $pg_obj=$pg_obj->toArray(); 
        $pg_config=PaymentGatewayConfig::firstOrNew(['id'=>$id]);
        $pg_config->pg_id=$pg_obj['id'];
        $pg_config->updated_by=$auth_id;
        $pg_config->pg_status=$pg_obj['status'];
        $pg_config->save();
    }
}
