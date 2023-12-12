<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 24/12/2019
 *   Use   : listing of User & create new user and update
 *
**/
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Jobs\UserPermissionJob;
use App\Models\SitePermission;
use App\models\AclPermission;
use App\models\Admin;
use App\models\Role;
use App\models\RolePermission;
use App\models\Site;
use App\models\UserPermission;
use Auth;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
class AdminManagementController extends Controller
{
    /**
     * Display a listing of the Users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);  
        $data = session()->all();  
        //print_r($data['login_admin_59ba36addc2b2f9401580f014c7f58ea4e30989d']);
        if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( username like \"%{$search}%\""
                . " or fullname like \"%{$search}%\""
                . " or email like \"%{$search}%\""
                . " or mobile_no like \"%{$search}%\""
                . ")";
            }  
           $status=$request->get('status');
            if($status==1)
            {
                $status='1';
                $where_str.= " and (admin_table.status =$status)";
            }
            else if($status==0)
            {
                $status='0';
                $where_str.=" and (admin_table.status= $status)";
            } 
            
            $auth_site_id=auth::guard('admin')->user()->site_id;            
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart)); 
            //DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'username','fullname','email','mobile_no','status','id','updated_at','role_id'];

            $font_master_count = Admin::select($columns)
                ->whereRaw($where_str, $where_params)
                ->where('publish',1)
                ->where('site_id',$auth_site_id)
                ->count();
  
            $fontMaster_list = Admin::select($columns)
                 ->where('publish',1)
                 ->where('site_id',$auth_site_id)
                 ->whereRaw($where_str, $where_params);
             
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $fontMaster_list = $fontMaster_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $fontMaster_list = $fontMaster_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $fontMaster_list = $fontMaster_list->get();
             
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list->toArray();
            
            return $response;
        }
        return view('admin.adminManagement.index');
    }

    /**
     * Show the form for creating a new role.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {   
        $site_id=Auth::guard('admin')->user()->site_id;
        $roles = Role::where('site_id',$site_id)->pluck('name', 'id');
        
                $auth_site_id=Auth::guard('admin')->user()->site_id;

            $dbName = 'seqr_demo';
            $new_connection = 'new';
            $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
                'driver'   => 'mysql',
             //  'host'     => 'localhost',
                'host'     => 'seqrdoc.com',
                'database' => $dbName,
                'username' => 'seqrdoc_multi',
                'password' => 'SimAYam4G2',
               /* 'username' => 'developer',
                'password' => 'developer',*/
                "unix_socket" => "",
                "charset" => "utf8mb4",
                "collation" => "utf8mb4_unicode_ci",
                "prefix" => "",
                "prefix_indexes" => true,
                "strict" => true,
                "engine" => null,
                "options" => []
            ]);

            // DB::connection($new_connection)->table("sites")->update(['start_date'=>$start_date,'end_date'=>$end_date,'license_key'=>$license_key,'status'=>$status]);
             $user_permission = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on')
                                ->join('site_permissions as s', 's.route_name', '=', 'acl.main_route')
                               ->where('s.site_id',$auth_site_id) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0')
                               ->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc')
                               ->get()->toArray();
                      
        return view('admin.adminManagement.create',compact('roles','user_permission'));
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(UserRequest $request)
    {   
        
        $user_details = $request->all();
        $generate_password = \Hash::make($user_details['password']);     
        
        $site_id=Auth::guard('admin')->user()->site_id;

        $user_details_save = new Admin();
        $user_details_save->fill($user_details);
        $user_details_save->password = $generate_password;
        $user_details_save->role_id = $user_details['role'];
        $user_details_save->username = $user_details['username'];
        $user_details_save->fullname = $user_details['fullname'];
        $user_details_save->mobile_no = $user_details['mobile_no'];
        $user_details_save->status = $user_details['status'];
        $user_details_save->site_id = $site_id;
        $user_details_save->save();

        $user_id = $user_details_save->id;
        $created_by = auth('admin')->user()->id;
        // $user_delete_id = UserPermission::where('user_id',$user_id)->delete();
        //$user_permission = $user_details['permission'];
        $role = $user_details['role'];
        $role_permission_group = $request->get('permissions');

        /*$user_permission = DB::table("acl_permissions_group")
                               ->select('main_route','sub_route','route_name','group_name','visibility_on')
                               ->where('visibility_on','1')->where('is_system','0')->where('is_super_admin','0')
                               ->orderBy('step_id','asc')->orderBy('sub_step_id','asc')
                               ->get()->toArray();*/
         $auth_site_id=Auth::guard('admin')->user()->site_id;

            $dbName = 'seqr_demo';
            $new_connection = 'new';
            $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
                'driver'   => 'mysql',
              //  'host'     => 'localhost',
                'host'     => 'seqrdoc.com',
                'database' => $dbName,
                'username' => 'seqrdoc_multi',
                'password' => 'SimAYam4G2',
               /* 'username' => 'developer',
                'password' => 'developer',*/
                "unix_socket" => "",
                "charset" => "utf8mb4",
                "collation" => "utf8mb4_unicode_ci",
                "prefix" => "",
                "prefix_indexes" => true,
                "strict" => true,
                "engine" => null,
                "options" => []
            ]);
        $user_permission = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on')
                                ->join('site_permissions as s', 's.route_name', '=', 'acl.main_route')
                               ->where('s.site_id',$auth_site_id) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0')
                               ->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc')
                               ->get()->toArray();
       // print_r($role_permission_group);

        $main_route_permission = array_column($user_permission, 'main_route');
        //print_r($main_route_permission);

        
        $role_permission_group = array_values(array_intersect($role_permission_group, $main_route_permission));
       /* print_r($role_permissions_post);
        exit;*/
        $permissionIds=array();
        foreach ($role_permission_group as $readPermission) {

//echo $readPermission;
            $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readPermission)->get()->toArray();
             if(count($acl_permission)>0){
           // print_r($acl_permission);
                if(!in_array($acl_permission[0]->id, $permissionIds)){
                    array_push($permissionIds, $acl_permission[0]->id);
                }

                $acl_permission_rel = DB::connection($new_connection)->table("acl_permissions_group")->select('sub_route')->where('main_route',$readPermission)->where('sub_route','!=',$readPermission)->where('is_super_admin','0')->where('is_system','0')->get()->toArray();

               if(count($acl_permission_rel)>0){
                    foreach ($acl_permission_rel as $readSubPermission) {
                       // print_r($readSubPermission);

                         $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readSubPermission->sub_route)->get()->toArray();
                            if(!in_array($acl_permission[0]->id, $permissionIds)){
                        array_push($permissionIds, $acl_permission[0]->id);
                    }
                    }
               }
            }
        }

        $acl_permission_sys = DB::connection($new_connection)->table("acl_permissions_group")->select('sub_route')->where('is_super_admin',0)->where('is_system',1)->get()->toArray();
           if(count($acl_permission_sys)>0){
                foreach ($acl_permission_sys as $readSubPermission) {
                     $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readSubPermission->sub_route)->get()->toArray();
                       if(!in_array($acl_permission[0]->id, $permissionIds)){
                    array_push($permissionIds, $acl_permission[0]->id);
                    }
                }
           }
        /*print_r($permissionIds);
        exit;*/
        $user_permission =$permissionIds;
        $user_permission_data = ['user_id'=>$user_id,'role_id'=>$role,'user_permission'=>$user_permission,'created_by'=>$created_by];

        $this->dispatch(new UserPermissionJob($user_permission_data));
        return response()->json(['success'=>true,'action'=>'created'],200);

       }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
  

    /**
     * Show the form for editing the specified User.
     * 
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = Admin::findOrFail($id);
        //$user_permission = SitePermission::getPermission();
        $auth_site_id=Auth::guard('admin')->user()->site_id;

            $dbName = 'seqr_demo';
            $new_connection = 'new';
            $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
                'driver'   => 'mysql',
               //  'host'     => 'localhost',
                'host'     => 'seqrdoc.com',
                'database' => $dbName,
                'username' => 'seqrdoc_multi',
                'password' => 'SimAYam4G2',
               /* 'username' => 'developer',
                'password' => 'developer',*/
                "unix_socket" => "",
                "charset" => "utf8mb4",
                "collation" => "utf8mb4_unicode_ci",
                "prefix" => "",
                "prefix_indexes" => true,
                "strict" => true,
                "engine" => null,
                "options" => []
            ]);

            // DB::connection($new_connection)->table("sites")->update(['start_date'=>$start_date,'end_date'=>$end_date,'license_key'=>$license_key,'status'=>$status]);
             $user_permission = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on')
                                ->join('site_permissions as s', 's.route_name', '=', 'acl.main_route')
                               ->where('s.site_id',$auth_site_id) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0')
                               ->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc')
                               ->get()->toArray();
             $main_route_permission = array_column($user_permission, 'main_route');
        $roles = Role::pluck('name','id');
        //$role_current_permissions = UserPermission::where('user_id',$id)->pluck('permission_id')->toArray();
        $role_current_permissions_all = UserPermission::where('user_id',$id)->pluck('route_name')->toArray();
        $role_current_permissions = array_intersect($main_route_permission, $role_current_permissions_all);

        return view('admin.adminManagement.edit',compact('user','roles','user_permission','role_current_permissions','id'));
    }

    /**
     * Update the specified User in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UserRequest $request, $id)
    {
        $user_details = $request->all();
        $user_details_save = Admin::firstOrNew(['id' => $id]);
        $user_details_save->fill($user_details);
        $user_details_save->role_id = $user_details['role'];
        $user_details_save->username = $user_details['username'];
        $user_details_save->fullname = $user_details['fullname'];
        $user_details_save->mobile_no = $user_details['mobile_no'];
        $user_details_save->status = $user_details['status'];
        $user_details_save->save();

        $user_id = $user_details_save->id;
        // $user_delete_id = UserPermission::where('user_id',$user_id)->delete();
        $created_by = auth('admin')->user()->id;
        //$user_permission = $user_details['permission'];
        $role = $user_details['role'];

        $role_permission_group = $request->get('permissions');

        /*$user_permission = DB::table("acl_permissions_group")
                               ->select('main_route','sub_route','route_name','group_name','visibility_on')
                               ->where('visibility_on','1')->where('is_system','0')->where('is_super_admin','0')
                               ->orderBy('step_id','asc')->orderBy('sub_step_id','asc')
                               ->get()->toArray();*/
         $auth_site_id=Auth::guard('admin')->user()->site_id;

            $dbName = 'seqr_demo';
            $new_connection = 'new';
            $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
                'driver'   => 'mysql',
               //  'host'     => 'localhost',
                'host'     => 'seqrdoc.com',
                'database' => $dbName,
                'username' => 'seqrdoc_multi',
                'password' => 'SimAYam4G2',
               /* 'username' => 'developer',
                'password' => 'developer',*/
                "unix_socket" => "",
                "charset" => "utf8mb4",
                "collation" => "utf8mb4_unicode_ci",
                "prefix" => "",
                "prefix_indexes" => true,
                "strict" => true,
                "engine" => null,
                "options" => []
            ]);
        $user_permission = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on')
                                ->join('site_permissions as s', 's.route_name', '=', 'acl.main_route')
                               ->where('s.site_id',$auth_site_id) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0')
                               ->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc')
                               ->get()->toArray();
       // print_r($role_permission_group);

        $main_route_permission = array_column($user_permission, 'main_route');
        //print_r($main_route_permission);

        
        $role_permission_group = array_values(array_intersect($role_permission_group, $main_route_permission));
       /* print_r($role_permissions_post);
        exit;*/
        $permissionIds=array();
        foreach ($role_permission_group as $readPermission) {

//echo $readPermission;
            $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readPermission)->get()->toArray();
             if(count($acl_permission)>0){
           // print_r($acl_permission);
                if(!in_array($acl_permission[0]->id, $permissionIds)){
                    array_push($permissionIds, $acl_permission[0]->id);
                }

                $acl_permission_rel = DB::connection($new_connection)->table("acl_permissions_group")->select('sub_route')->where('main_route',$readPermission)->where('sub_route','!=',$readPermission)->where('is_super_admin','0')->where('is_system','0')->get()->toArray();

               if(count($acl_permission_rel)>0){
                    foreach ($acl_permission_rel as $readSubPermission) {
                       // print_r($readSubPermission);

                         $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readSubPermission->sub_route)->get()->toArray();
                            if(!in_array($acl_permission[0]->id, $permissionIds)){
                        array_push($permissionIds, $acl_permission[0]->id);
                    }
                    }
               }
            }
        }

        $acl_permission_sys = DB::connection($new_connection)->table("acl_permissions_group")->select('sub_route')->where('is_super_admin',0)->where('is_system',1)->get()->toArray();
           if(count($acl_permission_sys)>0){
                foreach ($acl_permission_sys as $readSubPermission) {
                     $acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readSubPermission->sub_route)->get()->toArray();
                       if(!in_array($acl_permission[0]->id, $permissionIds)){
                    array_push($permissionIds, $acl_permission[0]->id);
                    }
                }
           }
        /*print_r($permissionIds);
        exit;*/
        $user_permission =$permissionIds;



        $user_permission_data = ['user_id'=>$user_id,'role_id'=>$role,'user_permission'=>$user_permission,'created_by'=>$created_by];

        $this->dispatch(new UserPermissionJob($user_permission_data));
        return response()->json(['success'=>true,'action'=>'updated'],200);

    }

    /**
     * Remove the specified User from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if(!empty($id))
        {
          $status=UserPermission::where('user_id',$id)->delete();

          $admin_table=Admin::where('id',$id)->delete();

          return response()->json(['success'=>true]);
        }
    }
    /**
     * acl_permission in insert routes on database.
     *
     */ 
    public function RouteList(){
        
         $dbName = 'seqr_demo';
            $new_connection = 'new';
            $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
                'driver'   => 'mysql',
              // 'host'     => 'localhost',
                'host'     => 'seqrdoc.com',
                'database' => $dbName,
                'username' => 'seqrdoc_multi',
                'password' => 'SimAYam4G2',
                /*'username' => 'developer',
                'password' => 'developer',*/
                "unix_socket" => "",
                "charset" => "utf8mb4",
                "collation" => "utf8mb4_unicode_ci",
                "prefix" => "",
                "prefix_indexes" => true,
                "strict" => true,
                "engine" => null,
                "options" => []
            ]);
        $routes = Route::getRoutes();
        
        $route_array = [];
        foreach ($routes as $value) {
            $route = $value->getName();
            $log_viewer = explode('::', $route);
            
            $route_method = $value->methods[0]; //method
            $route_action = $value->getActionName(); //method
            $route_path = $value->uri();  //url
            $route_inner = explode('.', $route);
            $last_index = last($route_inner);
            // dd($route_inner);
            if ($route != '') {
                $route_title = $route_inner[0];
                $route_save = AclPermission::firstOrNew(['route_name'=>$route]);
                $route_save->route_name = $route;
                if(head($route_inner) == 'semester' || head($route_inner) == 'branch' || head($route_inner) == 'sessionsmaster' || head($route_inner) == 'degreemaster')
                {
                    $route_save->main_module = 'Raisoni Master';
                    $route_save->sub_module = 'Raisoni Master';
                }
                elseif(head($route_inner) == 'stationarystock' || head($route_inner) == 'damagedstock' || head($route_inner) == 'consumptionreport' || head($route_inner) == 'consumptionreportexport'){
                    $route_save->main_module = 'Raisoni Stock Master';
                    $route_save->sub_module = 'Raisoni Stock Master';
                }
                elseif(head($route_inner) == 'degree-certifiate'){
                    $route_save->main_module = 'Galgotias Degree-Certificate';
                    $route_save->sub_module = 'Galgotias Degree-Certificate';
                }
                else{
                    $route_save->main_module = head($route_inner);
                    $route_save->sub_module = $route_title;
                }
                $route_save->method_name = $route_method;
                $route_save->module = $last_index;
                $route_save->action_name = $route_action;
                $route_save->route_method = $last_index;

                if ($last_index == 'index') {
                    $route_save->description = 'Listing of '.$route_title;
                }elseif($last_index == 'create'){
                    $route_save->description = 'Create page of '.$route_title;
                }elseif($last_index == 'edit'){
                    $route_save->description = 'Edit page of '.$route_title;
                }elseif($last_index == 'delete'){
                    $route_save->description = 'Delete '.$route_title;
                }else{
                    $route_save->description =  ucfirst($last_index).' of '.$route_title;
                }

                $route_save->description_alias =$route_save->description;
                if($log_viewer[0] != 'log-viewer'){
                    $route_save->save();
                }
                $acl_permission = DB::connection($new_connection)->table("acl_permissions_group")->select('sub_route')->where('sub_route',$route_save->route_name)->get()->toArray();
                if(count($acl_permission)<=0){
                         $aclGroup=array(); 
                        $aclGroup['main_route'] = $route_save->route_name;
                        $aclGroup['sub_route'] = $route_save->route_name;
                        $aclGroup['route_name'] = $route_save->module;
                        $aclGroup['group_name'] = $route_save->main_module;
                        $aclGroup['step_id'] = 1000;
                        $aclGroup['sub_step_id'] = 0;
                        $aclGroup['visibility_on'] = 1;
                        DB::connection($new_connection)->table("acl_permissions_group")->insert($aclGroup);

                }
            }
            $route_array[] = [
                'route' => $route,
                'route_method' => $route_method,
                'route_action' => $route_action,
                'route_path' => $route_path
            ];
        }

        echo "<pre>";
        echo count($route_array);
        print_r($route_array);
        exit();
    }
     /**
     * get the specified Role from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getRoleName(Request $request){
      
        $id = $request->id;

        //$role_current_permissions = RolePermission::where('role_id',$id)->pluck('permission_id')->toArray();
        //$user_permission = SitePermission::getPermission();

       // $get_html = view('admin.adminManagement.roles', compact('role_current_permissions','user_permission'))->render();
        // dd($get_html);
        //return response()->json(array('html'=>$get_html,'success' => true), 200);

                $auth_site_id=Auth::guard('admin')->user()->site_id;

            $dbName = 'seqr_demo';
            $new_connection = 'new';
            $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
                'driver'   => 'mysql',
               //  'host'     => 'localhost',
                'host'     => 'seqrdoc.com',
                'database' => $dbName,
                'username' => 'seqrdoc_multi',
                'password' => 'SimAYam4G2',
               /* 'username' => 'developer',
                'password' => 'developer',*/
                "unix_socket" => "",
                "charset" => "utf8mb4",
                "collation" => "utf8mb4_unicode_ci",
                "prefix" => "",
                "prefix_indexes" => true,
                "strict" => true,
                "engine" => null,
                "options" => []
            ]);

            // DB::connection($new_connection)->table("sites")->update(['start_date'=>$start_date,'end_date'=>$end_date,'license_key'=>$license_key,'status'=>$status]);
             $user_permission = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on')
                                ->join('site_permissions as s', 's.route_name', '=', 'acl.main_route')
                               ->where('s.site_id',$auth_site_id) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0')
                               ->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc')
                               ->get()->toArray();
              
       // $user_permission = SitePermission::getPermission();
               /* print_r($user_permission);
                exit;*/
                $main_route_permission = array_column($user_permission, 'main_route');
        $role_details = Role::findOrFail($id);
        

        //$role_current_permissions = RolePermission::where('role_id',$id)->pluck('permission_id')->toArray();


         $role_current_permissions_all = RolePermission::select('acl.route_name')
                                    ->join('acl_permissions as acl','acl.id','role_permissions.permission_id')
                                    ->where('role_permissions.role_id',$id)
                                    ->get()->toArray();
        $role_current_permissions_all = array_column($role_current_permissions_all, 'route_name');
                                    
         $role_current_permissions = array_intersect($main_route_permission, $role_current_permissions_all);
         return response()->json(array('data'=>$role_current_permissions,'success' => true), 200);

    }

    public function AssignLab(){ 
        $id = \Request::segment(4);   
        $LA = DB::table('admin_table')
                ->select('assigned_labs','fullname')
                ->where('id', '=', $id)
                ->get();
        $assigned_labs=$LA[0]->assigned_labs;
        $fullname=$LA[0]->fullname;    
        $LabData=DB::select(DB::raw('SELECT id,lab_title FROM `lab_table` WHERE publish=1 order by lab_title'));         
        //$LabDataAssigned=DB::select(DB::raw('SELECT id,lab_title FROM `lab_table` WHERE id in('.$assigned_labs.')')); 
        return view('admin.adminManagement.assignlab',compact('id','fullname','LabData','assigned_labs'));
    }
    public function AssignLabSave(Request $request){         
        $id=$request->id;
        $lab_id=$request->sbTwo;
        DB::select(DB::raw("UPDATE `admin_table` SET assigned_labs='".$lab_id."' WHERE id='".$id."'"));
        /*if (is_array($request->sbTwo)){
            $LabCount=count($request->sbTwo); 
            $List = implode(', ', $request->sbTwo);
            DB::select(DB::raw("UPDATE `admin_table` SET assigned_labs='".$lab_id."' WHERE id='".$id."'"));
        }else{
            DB::select(DB::raw("UPDATE `admin_table` SET assigned_labs='0' WHERE id='".$id."'"));
        }*/
        return redirect('admin/adminmaster/adminlab-assign/'.$id)->with('success','Record is saved successfully.');;
    }    
    
}
