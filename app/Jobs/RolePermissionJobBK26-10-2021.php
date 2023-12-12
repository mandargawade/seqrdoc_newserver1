<?php

namespace App\Jobs;

use App\Models\SitePermission;
use App\models\AclPermission;
use App\models\Admin;
use App\models\RolePermission;
use App\models\UserPermission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RolePermissionJob 
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

        //print_r($role_permission_data);
        $current_permission_delete = RolePermission::where('role_id',$role_permission_data['role_id'])->delete();
        
        if(isset($role_permission)){
            foreach ($role_permission as $key => $single_permission) {
                
                $role_permission_save = new RolePermission();
                $role_permission_save->role_id = $role_permission_data['role_id'];
                $role_permission_save->permission_id = $single_permission;
                $role_permission_save->created_by = $role_permission_data['created_by'];
                $role_permission_save->updated_by = $role_permission_data['updated_by'];
                $role_permission_save->save();
            }
        }
      

      // all user permission edit & get user list
        $user_permission = Admin::select('id')->where('role_id',$role_permission_data['role_id'])->get()->toArray();
        //print_r($user_permission);
        if(is_array($user_permission)){
            foreach ($user_permission as $user_key => $user_permission_value) {
                $user_permission_id = $user_permission_value['id'];
                $user_permission_delete = UserPermission::where('user_id',$user_permission_id)->delete();
                
               // print_r($role_permission);
                foreach ($role_permission as $key => $single_permission) {

                    $user_permission_save = new UserPermission();
                    $route_name = SitePermission::select('route_name')->where('permission_id',$single_permission)->get()->toArray();
                    $route_name = head($route_name);
                    $user_permission_save->user_id = $user_permission_id;
                    $user_permission_save->role_id = $role_permission_data['role_id'];
                    $user_permission_save->route_name = $route_name['route_name'];
                    $user_permission_save->permission_id = $single_permission;
                    $user_permission_save->created_by = $role_permission_data['created_by'];
                    $user_permission_save->updated_by = $role_permission_data['updated_by'];
                    
                    $user_permission_save->save();
                }
            }
        }
    }
}
