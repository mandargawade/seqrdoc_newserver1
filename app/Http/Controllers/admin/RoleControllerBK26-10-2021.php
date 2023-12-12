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
        $user_permission = SitePermission::getPermission();

        //print_r( $user_permission);
        //exit;

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
        $user_permission = SitePermission::getPermission();
       //  print_r($user_permission);
         //exit;       
        $role_details = Role::findOrFail($id);
        $role_current_permissions = RolePermission::where('role_id',$id)->pluck('permission_id')->toArray();
        
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
        $role_id = $request->id;

        $user_id = auth('admin')->user()->id;
        $role_edit_save = Role::findOrFail($role_id);
            
        $role_edit_save->name = $role_name;
        $role_edit_save->description = $request->get('description');
        $role_edit_save->updated_by = $user_id;
        $role_edit_save->status = $request->get('status');
        $role_edit_save->save();

       
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
