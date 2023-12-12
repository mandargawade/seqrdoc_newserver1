<?php

namespace App\Jobs;

use App\models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Hash;
class AdminManagementJob 
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $admin_data;
    public function __construct($admin_data)
    {
        $this->admin_data=$admin_data;
       
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $admin_data=$this->admin_data;
        
        
       
        $id=null;
        if(!empty($admin_data['user_id']))
        {
            $id=$admin_data['user_id'];
        }
        if(!empty($admin_data['passwords']))
        {
            
            $admin_password=Hash::make($admin_data['passwords']);
        }

         // save Admin data on Database
         $user_obj=Admin::firstOrNew(['id'=>$id]);
         $user_obj->fill($admin_data);
         $user_obj->role_id=$admin_data['role'];
         if(!empty($admin_password))
          {

            $user_obj->password=$admin_password;
          }  
          if(!isset($admin_data['mobile_no'])){
            $user_obj->mobile_no='';
          }
         $user_obj->save();
    }
}
