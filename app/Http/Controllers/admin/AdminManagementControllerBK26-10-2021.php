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
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'username','fullname','email','mobile_no','status','id','updated_at'];

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
        
        return view('admin.adminManagement.create',compact('roles'));
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
        $user_permission = $user_details['permission'];
        $role = $user_details['role'];

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
    	$site_id=Auth::guard('admin')->user()->site_id;
        $user = Admin::findOrFail($id);
        $user_permission = SitePermission::getPermission();
    
        $roles = Role::where('site_id',$site_id)->pluck('name','id');
        $role_current_permissions = UserPermission::where('user_id',$id)->pluck('permission_id')->toArray();

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
        if(isset($user_details['mobile_no'])){
            $user_details_save->mobile_no = $user_details['mobile_no'];
        }else{
            $user_details_save->mobile_no = '';
        }
        
        $user_details_save->status = $user_details['status'];
        $user_details_save->save();

        $user_id = $user_details_save->id;
        $created_by = auth('admin')->user()->id;
        $user_permission = $user_details['permission'];
        $role = $user_details['role'];

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
                if($log_viewer[0] != 'log-viewer'){
                    $route_save->save();
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

        $role_current_permissions = RolePermission::where('role_id',$id)->pluck('permission_id')->toArray();
        $user_permission = SitePermission::getPermission();

        $get_html = view('admin.adminManagement.roles', compact('role_current_permissions','user_permission'))->render();
        
        return response()->json(array('html'=>$get_html,'success' => true), 200);
    }
}
