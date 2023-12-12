<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\SitePermission;
use App\models\AclPermission;
use App\models\SuperAdminLogin;
use App\models\SuperAdminRolePermission;
use App\models\SuperAdminUserPermission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class SuperAdminRolePermissionJob 
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $role_permission_data;
    public function __construct($role_permission_data)
    {
        $this->role_permission_data = $role_permission_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Request $request)
    {
         
        $role_permission_data = $this->role_permission_data;

        $role_permission = $role_permission_data['role_permission'];
        
        if(!empty($role_permission_data['role_id']))
        {
           $current_permission_delete = SitePermission::where('site_id',$role_permission_data['role_id'])->delete();
        }   
       
        if(isset($role_permission)){
            foreach ($role_permission as $key => $single_permission) {
                $route_name = AclPermission::select('route_name','main_module','sub_module','description')->where('id',$single_permission)->get()->toArray();
                 $route_name = head($route_name);
                // print_r($route_name);
                 $role_permission_data['role_id'];
                $role_permission_save = new SitePermission();
                $role_permission_save->site_id = $role_permission_data['role_id'];
                $role_permission_save->permission_id = $single_permission;
                $role_permission_save->route_name = $route_name['route_name'];
                $role_permission_save->main_module = $route_name['main_module'];
                $role_permission_save->sub_module = $route_name['sub_module'];
                $role_permission_save->description = $route_name['description'];
                $role_permission_save->save();
            }
        }
       
    }
}
