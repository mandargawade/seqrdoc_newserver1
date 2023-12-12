<?php

namespace App\Jobs;

use App\Models\SitePermission;
use App\models\AclPermission;
use App\models\UserPermission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UserPermissionJob 
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $user_permission_data;
    public function __construct($user_permission_data)
    {
        $this->user_permission_data = $user_permission_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    
    public function handle(Request $request)
    {
        $user_permission_data = $this->user_permission_data;
        $user_permission = $user_permission_data['user_permission'];

        $user_delete_id = UserPermission::where('user_id',$user_permission_data['user_id'])->delete();
       
        foreach($user_permission as $key => $single_permission) {
                
            $route_name = SitePermission::select('route_name')->where('permission_id',$single_permission)->get()->toArray();
            
            $role_permission_save = new UserPermission();
            $route_name = head($route_name);

            
            $role_permission_save->user_id = $user_permission_data['user_id'];
            $role_permission_save->permission_id = $single_permission;
            $role_permission_save->role_id = $user_permission_data['role_id'];
            $role_permission_save->route_name = $route_name['route_name'];
            $role_permission_save->created_by = $user_permission_data['created_by'];
            $role_permission_save->updated_by = $user_permission_data['created_by'];
            $role_permission_save->save();
        }
        return;
    }
}
