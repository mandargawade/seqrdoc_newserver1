<?php

//Author :: Aakashi Modi
//Date : 18-11-2019
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\InstituteMaster;
use DB,Event;
use App\Events\InstituteEvent;
use App\Http\Requests\InstituteRequest;
use Auth;
class InstituteMasterController extends Controller
{
    //displaying list of institute
    public function index(Request $request){
      /*  $permitted = 0;
        $routeName="adminmaster.index";
        $user_id = auth('admin')->user()->id;
        $user_role_count = auth('admin')->user()->with(['user_permissions'=>function($query) use ($routeName){
                                $query->where('user_permissionss.route_name',$routeName);
                            }])
                            ->where('id',$user_id)
                            ->first()->toArray();
        print_r($user_role_count);
        if(head($user_role_count['user_permissions'])) {
           echo $permitted = 1;
        }
        exit;*/
        //return $permitted;
    	if($request->ajax()){
            $where_str    = "1 = ?";;
            $where_str    .= " AND publish = '1'";
            $where_params = array(1); 

            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( created_at like \"%{$search}%\""
                . " or institute_username like \"%{$search}%\""
                . " or username like \"%{$search}%\""
                . ")";
            }     

            //get status and check
            $status=$request->get('status');

            if($status==1)
            {
                $where_str.= " and (institute_table.status =$status)";
            }
            else if($status==0)
            {
                $where_str.=" and (institute_table.status= $status)";
            }                             
            
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            DB::statement(DB::raw('set @rownum=0'));

            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'id','created_at','institute_username','username','status','password'];
            
            $institute_count = InstituteMaster::select($columns)
            ->whereRaw($where_str, $where_params)
            ->where('site_id',$auth_site_id)
            ->count();

            //get list
            $institute_list = InstituteMaster::select($columns)
            ->whereRaw($where_str, $where_params)
            ->where('site_id',$auth_site_id);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $institute_list = $institute_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            //sorting the data column wise
            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $institute_list = $institute_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $institute_list = $institute_list->get();

            $response['iTotalDisplayRecords'] = $institute_count;
            $response['iTotalRecords'] = $institute_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $institute_list->toArray();

            return $response;
        }
    	return view('admin.institutemaster.index');
    } 

    //store institute data and InstituteRequest is the validation files
    public function store(InstituteRequest $request){
        //get all institute data from form
        $institute_data = $request->all();
        //pass institute data to event to store the record in db 
        Event::dispatch(new InstituteEvent($institute_data)); 
    }
    //update institute data and InstituteRequest is the validation files
    public function update(InstituteRequest $request,$id){
        //get edit data
        $institute_data = $request->all();
        //get institute id
        $institute_data['id'] = $id;
        //allevent for update data
        Event::dispatch(new InstituteEvent($institute_data));
    } 

    //delete the institute data
    public function delete(Request $request){
        //get institute id
        $institute_id = $request->id;
        //update institute id to 0 for delting institute data
        InstituteMaster::where('id',$institute_id)->update(['publish'=>0]);
        $message = Array('type'=>'success','message'=>'Deleted Successfully');
        echo json_encode($message);
        exit();
    }
}
