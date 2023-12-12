<?php

namespace App\Http\Middleware;

use Closure,Response,Auth,Redirect;
use Illuminate\Contracts\Auth\Guard;
use App\models\UserPermission;
use App\models\AclPermission;
class Permission
{
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $permitted = 0;
        $current_user = auth('admin')->user();
        // dd($current_user);

        //show.routes is out of acl.permitted middleware so not needed to add in array
        $without_permission_accessible_routes_array = ['horizon','passport','demo','demo1','canvasmaker','excel','preview','templateMaster','idcards_validation','pgconfig_fetch_dropdown_value','user-master','admin-master','scanningHistory','sand-box','template-map','raisoniMaster','degreeCertificate','manual'];


        $mapping_route = $request->route()->getName();
        $explode_route = explode('.',$mapping_route);
       // dd($explode_route);
        /*print_r($explode_route);
        exit;*/
        if(isset($explode_route[0])){
            $route = $explode_route[0];
        }
        else{
            $route = $explode_route;
        }

       /* $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        if($subdomain[0] == 'raisoni')
        {
        print_r($mapping_route);
        exit;
        }*/
        //print_r($route);
        //exit;
        if(in_array($route, $without_permission_accessible_routes_array))
        {
            return $next($request);
        }
        else{

          //  echo $mapping_route;


            $user_route = UserPermission::select('route_name')
                                    ->where('user_id',$current_user->id)
                                    ->where('route_name',$mapping_route)
                                    ->get()->toArray();

            // print_r($user_route);                       
            // dd($user_route);
            if(!empty($user_route)){
                return $next($request);
            }
           
        // $route_arr = explode('.', $mapping_route);

        // $route_arr_last = $route_arr[sizeof($route_arr) - 1];
    
        // if($route_arr_last == 'update')
        // {
        //     $route_arr[sizeof($route_arr) - 1] = 'edit';
        //     $mapping_route = implode('.', $route_arr);
        // }
        // if($route_arr_last == 'store')
        // {
        //     $route_arr[sizeof($route_arr) - 1] = 'create';
        //     $mapping_route = implode('.', $route_arr);
        // }
        // if($route_arr_last == 'delete')
        // {
        //     $route_arr[sizeof($route_arr) - 1] = 'index';
        //     $mapping_route = implode('.', $route_arr);
        // }
        // if(($route_arr_last == 'getstate') or ($route_arr_last == 'getcity'))
        // {
        //     $route_arr[sizeof($route_arr) - 1] = 'index';
        //     $mapping_route = implode('.', $route_arr);
        // }
        // $current_user->load('role_list.permissions');
        // if($current_user->role_list != null){
        //     foreach ($current_user->role_list->permissions as $key => $single_permissions) {
        //         if ($single_permissions->route_name == $mapping_route) {
        //             return $next($request);
        //         }
        //     }
        // }
            return redirect()->to('/access-denied');
        }
    }
}
