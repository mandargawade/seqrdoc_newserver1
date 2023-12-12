<?php

namespace App\Jobs;

use App\models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\models\SiteDocuments;
use Hash;
use Auth;
use App\models\SessionManager;

class UserManagmentJob 
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $user_data;
    public function __construct($user_data)
    {
        $this->user_data=$user_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $user_data=$this->user_data;
       
       
        // is null
        $id=null;
        if(!empty($user_data['user_id']))
        {
            $id=$user_data['user_id'];
        }
        if(!empty($user_data['user_password']))
        {
            $user_password=Hash::make($user_data['user_password']);
        }
        
        //print_r($user_data);
        $auth_site_id=Auth::guard('admin')->user()->site_id; 
         // save User data on Database
        $user_obj=User::firstOrNew(['id'=>$id]);
        $user_obj->fill($user_data);
        $user_obj->role_id=$user_data['role'];
        $user_obj->site_id=$auth_site_id;
       /*
        if(!isset($user_data['mobile_no'])){
         
            $user_obj->mobile_no='';
        }*/
        
        //exit;
       if(isset($user_data['registration_type'])){
            $user_obj->user_type=$user_data['registration_type'];
        }

        if(isset($user_data['working_sector'])){
            $user_obj->working_sector=$user_data['working_sector'];
        }

        if(isset($user_data['address'])){
            $user_obj->address=$user_data['address'];
        }

        if(isset($user_data['reg_no'])){
            if($user_data['registration_type']!=0){
                $user_obj->registration_no=$user_data['reg_no'];
            }else{
                $user_obj->registration_no=$user_data['student_reg_no'];
            }
            
        }

        if(isset($user_data['student_degree'])){
            $user_obj->degree=$user_data['student_degree'];
        }

        if(isset($user_data['student_institute'])){
            $user_obj->institute=$user_data['student_institute'];
        }

        if(isset($user_data['student_branch'])){
            $user_obj->branch=$user_data['student_branch'];
        }

        if(isset($user_data['passout_year'])){
            $user_obj->passout_year=$user_data['passout_year'];
        }

        if(isset($user_data['student_branch'])){
            $user_obj->branch=$user_data['student_branch'];
        }


        if(!empty($user_password))
        {
            $user_obj->password=$user_password;
        }  
        $user_obj->save();

        if($user_data['status']== "0"){
            
            $session_manager = SessionManager::where('user_id',$id)->update(['is_logged'=>0]);
        }
        $dbName = 'seqr_demo';
        
        \DB::disconnect('mysql'); 
        
        \Config::set("database.connections.mysql", [
            'driver'   => 'mysql',
            'host'     => \Config::get('constant.SDB_HOST'),
            'port' => \Config::get('constant.SDB_PORT'),
            'database' => \Config::get('constant.SDB_NAME'),
            'username' => \Config::get('constant.SDB_UN'),
            'password' => \Config::get('constant.SDB_PW'),
            "unix_socket" => "",
            "charset" => "utf8mb4",
            "collation" => "utf8mb4_unicode_ci",
            "prefix" => "",
            "prefix_indexes" => true,
            "strict" => true,
            "engine" => null,
            "options" => []
        ]);
        \DB::reconnect();
        $user_count = User::select('id')->where(["site_id"=>$auth_site_id])->count();
        SiteDocuments::where('site_id',$auth_site_id)->update(['total_verifier'=>$user_count]);
        if($subdomain[0] == 'demo')
        {
            $dbName = 'seqr_'.$subdomain[0];
        }else{

            $dbName = 'seqr_d_'.$subdomain[0];
        }

        \DB::disconnect('mysql');     
        \Config::set("database.connections.mysql", [
            'driver'   => 'mysql',
            'host'     => \Config::get('constant.DB_HOST'),
            "port" => \Config::get('constant.DB_PORT'),
            'database' => $dbName,
            'username' => \Config::get('constant.DB_UN'),
            'password' => \Config::get('constant.DB_PW'),
            "unix_socket" => "",
            "charset" => "utf8mb4",
            "collation" => "utf8mb4_unicode_ci",
            "prefix" => "",
            "prefix_indexes" => true,
            "strict" => true,
            "engine" => null,
            "options" => []
        ]);
        \DB::reconnect();
    }
}
