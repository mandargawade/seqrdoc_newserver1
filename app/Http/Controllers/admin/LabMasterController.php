<?php
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\LabMaster;
use DB,Event;
use App\Events\LabEvent;
use App\Http\Requests\LabRequest;
use App\Jobs\LabJob;
use Auth;
class LabMasterController extends Controller
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
                . " or lab_title like \"%{$search}%\""
                . ")";
            }     

            //get status and check
            $status=$request->get('status');

            if($status==1)
            {
                $where_str.= " and (lab_table.status =$status)";
            }
            else if($status==0)
            {
                $where_str.=" and (lab_table.status= $status)";
            }                             
            
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));

            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'id','created_at','lab_title','status'];
            
            $lab_count = LabMaster::select($columns)
            ->whereRaw($where_str, $where_params)
            ->where('site_id',$auth_site_id)
            ->count();

            //get list
            $lab_list = LabMaster::select($columns)
            ->whereRaw($where_str, $where_params)
            ->where('site_id',$auth_site_id);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $lab_list = $lab_list->take($request->input('iDisplayLength'))
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
                    $lab_list = $lab_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $lab_list = $lab_list->get();

            $response['iTotalDisplayRecords'] = $lab_count;
            $response['iTotalRecords'] = $lab_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $lab_list->toArray();

            return $response;
        }
    	return view('admin.labmaster.index');
    } 

    //store lab data and LabRequest is the validation files
    public function store(LabRequest $request){
        //get all lab data from form
        $lab_data = $request->all();
        //pass lab data to event to store the record in db 
        //Event::dispatch(new LabEvent($lab_data)); 
        $id = $request->id;        
        if(isset($lab_data['id'])){
            $id = $lab_data['id'];
        }
        //check id is exist in db or not if exist then update else create
        $save_data = LabMaster::firstorNew(['id'=>$id]);
        //fill data in variable
        $save_data->fill($lab_data);        
        //get login user id
        $admin_id = \Auth::guard('admin')->user()->toArray();
        $site_id = \Auth::guard('admin')->user()->site_id;
        $save_data->created_by = $admin_id['id'];
        $save_data->updated_by = $admin_id['id'];
        $save_data->site_id =$site_id;
        $save_data->publish = 1;
        $save_data->save();  
        $message = Array('type'=>'success','message'=>'Added Successfully');
        echo json_encode($message);
    }
    //update lab data and LabRequest is the validation files
    public function update(LabRequest $request,$id){
        //get edit data
        $lab_data = $request->all();
        //get lab id
        $lab_data['id'] = $id;
        //allevent for update data
        //Event::dispatch(new LabEvent($lab_data));
        $id = $request->id;        
        if(isset($lab_data['id'])){
            $id = $lab_data['id'];
        }
        //check id is exist in db or not if exist then update else create
        $save_data = LabMaster::firstorNew(['id'=>$id]);
        //fill data in variable
        $save_data->fill($lab_data);        
        //get login user id
        $admin_id = \Auth::guard('admin')->user()->toArray();
        $site_id = \Auth::guard('admin')->user()->site_id;
        //$save_data->created_by = $admin_id['id'];
        $save_data->updated_by = $admin_id['id'];
        $save_data->site_id =$site_id;
        $save_data->publish = 1;
        $save_data->save();  
        $message = Array('type'=>'success','message'=>'Edited Successfully');
        echo json_encode($message);
    } 

    //delete the lab data
    public function delete(Request $request){
        //get lab id
        $lab_id = $request->id;
        //update lab id to 0 for delting lab data
        LabMaster::where('id',$lab_id)->update(['publish'=>0]);
        $message = Array('type'=>'success','message'=>'Deleted Successfully');
        echo json_encode($message);
        exit();
    }
}
