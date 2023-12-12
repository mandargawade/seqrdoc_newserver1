<?php
/**
 *
 *  Author : Ketan valand 
 *   Date  : 27/12/2019
 *   Use   : listing of Role & create new Role and update
 *
**/
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Jobs\RolePermissionJob;
use App\Models\SitePermission;
use App\models\AclPermission;
use App\models\Role;
use App\models\RolePermission;
use Auth;
use DB;
use Illuminate\Http\Request;
class RoleController extends Controller
{
    /**
     * Display a listing of the Roles.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        if ($request->ajax()) {
            $where_str = "1 = ?";
            $where_params = array(1);

            if (!empty($request->input('sSearch'))) {
                $search = $request->input('sSearch');
                $where_str .= " and ( name like \"%{$search}%\""
                . ")";
            }
             //for serial number
            DB::statement(DB::raw('set @rownum=0')); 
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'id','updated_at','created_at','name','status'];

             $status=$request->get('status');
            if($status=='1')
            {
                $status='1';
                $where_str.= " and (roles.status ='1')";
            }
            else if($status=='0')
            {
                $status='0';
                $where_str.=" and (roles.status= '0')";
            } 
            
            $auth_site_id=auth::guard('admin')->user()->site_id;
            
            $role = Role::select($columns)       
                        ->whereRaw($where_str, $where_params)
                        ->where('site_id',$auth_site_id);

            $role_count = Role::select('id')
                        ->whereRaw($where_str, $where_params)
                         ->where('site_id',$auth_site_id)
                        ->count();

            if ($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != '') {
                $role = $role->take($request->input('iDisplayLength'))
                    ->skip($request->input('iDisplayStart'));
            }
            if ($request->input('iSortCol_0')) {
                $sql_order = '';
                for ($i = 0; $i < $request->input('iSortingCols'); $i++) {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }
                    $role = $role->orderBy($column, $request->input('sSortDir_' . $i));
                }
            }
            $role = $role->get();

            $response['iTotalDisplayRecords'] = $role_count;
            $response['iTotalRecords'] = $role_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $role->toArray();

            return $response;
        }
        return view('admin.role.index');
    }

    /**
     * Show the form for creating a new Role.
     *
     * @return view response
     */
    public function create()
    {
       // $user_permission = SitePermission::getPermission();
         $auth_site_id=Auth::guard('admin')->user()->site_id;

            $dbName = 'seqr_demo';
            $new_connection = 'new';
            $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
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

            // DB::connection($new_connection)->table("sites")->update(['start_date'=>$start_date,'end_date'=>$end_date,'license_key'=>$license_key,'status'=>$status]);
             /*$user_permission = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on')
                                ->join('site_permissions as s', 's.route_name', '=', 'acl.main_route')
                               ->where('s.site_id',$auth_site_id) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0')
                               ->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc')
                               ->get()->toArray();*/
                                $mainMenu = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on','acl.step_id','acl.sub_step_id','acl.main_menu_no') 
                               ->where('acl.sub_route',-1) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0');
         
            $user_permission = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on','acl.step_id','acl.sub_step_id','acl.main_menu_no')
                                ->join('site_permissions as s', 's.route_name', '=', 'acl.main_route')
                               ->where('s.site_id',$auth_site_id) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0');
                               //->orderBy('acl.main_menu_no','asc')->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc')
                              // ->union($mainMenu)
                              // ->get()->toArray();


            $union_query = $mainMenu->union($user_permission);
            $user_permission = DB::connection($new_connection)->query()
                ->fromSub($union_query, 'union_query')
                ->select('main_route','sub_route','route_name','group_name','visibility_on','step_id','sub_step_id','main_menu_no')
               // ->groupBy('id')
                ->orderBy('main_menu_no','asc')->orderBy('step_id','asc')->orderBy('sub_step_id','asc')
                ->get()->toArray();

        return view('admin.role.create',compact('user_permission'));
    }

    /**
     * Store a newly created Role in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(RoleRequest $request)
    {
        $role_name = $request->get('name');
        $role_permission = $request->get('permission');
        
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
                    
                     /*  print_r($readSubPermission->sub_route);
                         print_r($acl_permission);*/
                         if($acl_permission){
                       if(!in_array($acl_permission[0]->id, $permissionIds)){
                    array_push($permissionIds, $acl_permission[0]->id);
                    }
                    }
                }
           }
        /*print_r($permissionIds);
        exit;*/
        $role_permission =$permissionIds;
        

        $id = auth('admin')->user()->id;
        $site_id = auth('admin')->user()->site_id;
        //create the role 
        $role_save = new Role();
        $role_save->name = $role_name;
        $role_save->description = $request->get('description');
        $role_save->status = $request->get('status');
        $role_save->created_by = $id;
        $role_save->updated_by = $id;
        $role_save->site_id = $site_id;
        $role_save->save();
        
        $role_id  = $role_save->id;
        $role_permission_data = ['role_id'=>$role_id,'role_permission'=>$role_permission,'created_by'=>$id,'updated_by'=>$id];

        $this->dispatch(new RolePermissionJob($role_permission_data));

        return response()->json(array('success' => true,'action'=>'added'),200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified Role.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {


        $auth_site_id=Auth::guard('admin')->user()->site_id;

            $dbName = 'seqr_demo';
            $new_connection = 'new';
            $nc = \Illuminate\Support\Facades\Config::set('database.connections.' . $new_connection, [
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

            // DB::connection($new_connection)->table("sites")->update(['start_date'=>$start_date,'end_date'=>$end_date,'license_key'=>$license_key,'status'=>$status]);
             /*$user_permission = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on')
                                ->join('site_permissions as s', 's.route_name', '=', 'acl.main_route')
                               ->where('s.site_id',$auth_site_id) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0')
                               ->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc')
                               ->get()->toArray();*/
             $mainMenu = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on','acl.step_id','acl.sub_step_id','acl.main_menu_no') 
                               ->where('acl.sub_route',-1) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0')
                               ->orderBy('acl.main_menu_no','asc')->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc');
         
             $user_permission = DB::connection($new_connection)->table("acl_permissions_group as acl")
                               ->select('acl.main_route','acl.sub_route','acl.route_name','acl.group_name','acl.visibility_on','acl.step_id','acl.sub_step_id','acl.main_menu_no')
                                ->join('site_permissions as s', 's.route_name', '=', 'acl.main_route')
                               ->where('s.site_id',$auth_site_id) 
                               ->where('acl.visibility_on','1')->where('acl.is_system','0')->where('acl.is_super_admin','0')
                               ->orderBy('acl.main_menu_no','asc')->orderBy('acl.step_id','asc')->orderBy('acl.sub_step_id','asc');
                              // ->union($mainMenu)
                              // ->get()->toArray();


          $union_query = $mainMenu->union($user_permission);
         // if(){
          
        //}
$user_permission = DB::connection($new_connection)->query()
                ->fromSub($union_query, 'union_query')
                ->select('main_route','sub_route','route_name','group_name','visibility_on','step_id','sub_step_id','main_menu_no')
               // ->groupBy('id')
                ->orderBy('main_menu_no','asc')->orderBy('step_id','asc')->orderBy('sub_step_id','asc')->orderBy('route_name','asc')
                ->get()->toArray();
              
       // $user_permission = SitePermission::getPermission();
              /*  print_r($user_permission);
                exit;
              */  $main_route_permission = array_column($user_permission, 'main_route');
        $role_details = Role::findOrFail($id);
        

        //$role_current_permissions = RolePermission::where('role_id',$id)->pluck('permission_id')->toArray();


         $role_current_permissions_all = RolePermission::select('acl.route_name')
                                    ->join('acl_permissions as acl','acl.id','role_permissions.permission_id')
                                    ->where('role_permissions.role_id',$id)
                                    ->orderBy('acl.main_module','asc')->orderBy('acl.sub_module','asc')->orderBy('acl.module','asc')
                                    ->get()->toArray();
        $role_current_permissions_all = array_column($role_current_permissions_all, 'route_name');
                                    
         $role_current_permissions = array_intersect($main_route_permission, $role_current_permissions_all);
     
         //print_r($role_current_permissions);
                //exit;

        // dd($user_permission);

        //  dd($role_current_permissions_all);
        return view('admin.role.edit',compact('user_permission','role_details','role_current_permissions','id'));
    }

    /**
     * Update the specified Role in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(RoleRequest $request)
    {

        $id = $request->id;
        $role_name = $request->get('name');
        $role_permission = $request->get('permission');
        
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
        $role_permission =$permissionIds;


        $role_id = $request->id;

        $user_id = auth('admin')->user()->id;
        $role_edit_save = Role::findOrFail($role_id);
            
        $role_edit_save->name = $role_name;
        $role_edit_save->description = $request->get('description');
        $role_edit_save->updated_by = $user_id;
        $role_edit_save->status = $request->get('status');
        $role_edit_save->save();

        // $current_permission_delete = RolePermission::where('role_id',$role_id)->delete();
        $role_permission_data = ['role_id'=>$role_id,'role_permission'=>$role_permission,'created_by'=>$user_id,'updated_by'=>$user_id];
        
        $this->dispatch(new RolePermissionJob($role_permission_data));

        return response()->json(array('success' => true,'action'=>'updated'),200);
    }

    /**
     * Remove the specified Role from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if(!empty($id))
        {
            Role::where('id',$id)->delete();

            RolePermission::where('role_id',$id)->delete();

            return response()->json(['success'=>true]);
        }
        
    }
}
