<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\UserPermissionJob;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use App\models\SuperAdmin;
use Session,TCPDF,TCPDF_FONTS,Auth,DB;
use App\Http\Requests\ExcelValidationRequest;
use App\Http\Requests\MappingDatabaseRequest;
use App\Http\Requests\TemplateMapRequest;
use App\Http\Requests\TemplateMasterRequest;
use App\Imports\TemplateMapImport
;use App\Imports\TemplateMasterImport;
use App\Jobs\PDFGenerateJob;
use App\models\BackgroundTemplateMaster;
use App\Events\BarcodeImageEvent;
use App\Events\TemplateEvent;
use App\models\FontMaster;
use App\models\FieldMaster;
use App\models\User;
use App\models\StudentTable;
use App\models\SbStudentTable;
use Maatwebsite\Excel\Facades\Excel;
use App\models\SystemConfig;
use App\Jobs\PreviewPDFGenerateJob;
use App\Exports\TemplateMasterExport;
use Storage;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\Config;
use App\models\PrintingDetail;
use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;
use App\models\sgrsa_agent;
use App\models\sgrsa_supplier;
use App\models\sgrsa_governor;
use App\models\sgrsa_unitserial;
use App\models\sgrsaAdmin;

use App\Helpers\CoreHelper;
use Helper;

class SgrsaAgentController extends Controller
{
    public function index(Request $request)
    {
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$agent_id = Auth::guard('admin')->user()->agent_id;  
		$supplier_id = Auth::guard('admin')->user()->supplier_id;  
		$admin_id=Auth::guard('admin')->user()->id;
        if($request->ajax()){
            $where_str    = "1 = ?";
            $where_str    .= " AND publish=1";
            $where_str    .= " AND admin_id=".$admin_id;
            $where_params = array(1);
            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( created_date like \"%{$search}%\""
                . " or name like \"%{$search}%\""
                . " or location like \"%{$search}%\""
                . " or contact_no like \"%{$search}%\""
                . ")";
            }             
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));

            //column that we wants to display in datatable
            //$columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id',DB::raw('IFNULL(`generated_at`,"") AS generated_at')];
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id', 'name', 'location', 'contact_no', 'publish', 'admin_id', 'supplier_id', 'created_date'];
            
            $ffl_count = sgrsa_agent::select($columns)
            ->whereRaw($where_str, $where_params)
            //->where('site_id',$auth_site_id)
            ->count();

            //get list
            $get_list = sgrsa_agent::select($columns)
            ->whereRaw($where_str, $where_params);
            //->where('site_id',$auth_site_id);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $get_list = $get_list->take($request->input('iDisplayLength'))
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
                    $get_list = $get_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $get_list = $get_list->get();

            $response['iTotalDisplayRecords'] = $ffl_count;
            $response['iTotalRecords'] = $ffl_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $get_list->toArray();

            return $response;
        }
    }
	
    public function addForm(){
        return view('admin.sgrsa.agentadd');
    }

    public function agentData(Request $request){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$auth_site_id=Auth::guard('admin')->user()->site_id;
		$admin_id=Auth::guard('admin')->user()->id;
		$LA = DB::table('admin_table')
			->select('supplier_id','fullname')
			->where('id', '=', $admin_id)
			->get();
		$supplier_id=$LA[0]->supplier_id;		
		$datetime  = date("Y-m-d H:i:s");	
		
		$input = $request->all(); 
		$name=trim($input['name']);
		$location=trim($input['location']);
		$contact_no=trim($input['contact_no']);
		$email=trim($input['email']);
		$username=trim($input['username']);
		$password=trim($input['password']);		
		
		$input_log=array();
        $input_log['name'] = $name;
		$input_log['location']=$location;
		$input_log['contact_no']=$contact_no;
		$input_log['email']=$email;
		$input_log['admin_id']=$admin_id;		
		$input_log['supplier_id']=$supplier_id;		
		$input_log['created_date']=$datetime;		
		
        /*if(sgrsa_agent::where('name',$name)->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'Name already existed.']); 
			exit();
		}*/
		if(sgrsaAdmin::where('username',$username)->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'User Name already existed.']); 
			exit();
		}
		$results = DB::select(DB::raw('SELECT AUTO_INCREMENT AS NEXT_ID FROM `information_schema`.`tables` WHERE  TABLE_NAME = "agents" AND table_schema = "seqr_d_sgrsa"'));
	    $agent_id=trim($results[0]->NEXT_ID);
		
		DB::beginTransaction();
		try {
			sgrsa_agent::create($input_log);
			$generate_password = \Hash::make($password);
			$input_admin=array();
			$input_admin['fullname'] = $name;
			$input_admin['username'] = $username;
			$input_admin['email'] = $email;
			$input_admin['mobile_no'] = $contact_no;
			$input_admin['password'] = $generate_password;
			$input_admin['status'] = 1;
			$input_admin['role_id'] = 2;
			$input_admin['publish'] = 1;
			$input_admin['supplier_id'] = $supplier_id;
			$input_admin['agent_id'] = $agent_id;
			$input_admin['site_id'] = $auth_site_id;
			$create=sgrsaAdmin::create($input_admin);
			
			/****** Start permissions ******/
			$user_id=$create->id; //lastInsertID	
			$created_by = auth('admin')->user()->id;
			$role = 2; //Sub Agent
			$role_permission_group = Array('sgrsa-certificate.addform');
			
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
			
			$main_route_permission = array_column($user_permission, 'main_route');					
			$role_permission_group = array_values(array_intersect($role_permission_group, $main_route_permission)); 
			$permissionIds=array();
			foreach ($role_permission_group as $readPermission) {
				$acl_permission = DB::table("acl_permissions")->select('id','route_name')->where('route_name',$readPermission)->get()->toArray();
				 if(count($acl_permission)>0){
					if(!in_array($acl_permission[0]->id, $permissionIds)){
						array_push($permissionIds, $acl_permission[0]->id);
					}

					$acl_permission_rel = DB::connection($new_connection)->table("acl_permissions_group")->select('sub_route')->where('main_route',$readPermission)->where('sub_route','!=',$readPermission)->where('is_super_admin','0')->where('is_system','0')->get()->toArray();

					if(count($acl_permission_rel)>0){
						foreach ($acl_permission_rel as $readSubPermission) {
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
			$user_permission =$permissionIds;
			$user_permission_data = ['user_id'=>$user_id,'role_id'=>$role,'user_permission'=>$user_permission,'created_by'=>$created_by];

			$this->dispatch(new UserPermissionJob($user_permission_data));			
			/****** End permissions ******/			
			
			DB::commit();
			return response()->json(['success'=>true, 'message'=>'Record added successfully.']); 
		} catch (Exception $e) {
			print "Something went wrong.<br />";
			echo 'Exception Message: '. $e->getMessage();
			DB::rollback();
			return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
			exit;
		}		
	}
	public function EditRecord(Request $request)
    {
        $id=$request->id;
		$records = DB::table('agents')->where(['id' => $id, 'publish' => 1])->first();
		$agents = DB::table('admin_table')->where(['agent_id' => $id, 'publish' => 1])->first();
		$username=$agents->username;
		return response()->json(['success'=>true, 'res'=>$records, 'agent_id' => $id, 'username'=>$username, 'message'=>'Record Found']);	
	}

    public function editData(Request $request){
        $datetime  = date("Y-m-d H:i:s");	
		
		$input = $request->all(); 
		$name=trim($input['name_e']);
		$agent_id=trim($input['agent_id']);
		$location=trim($input['location_e']);
		$contact_no=trim($input['contact_no_e']);
		$email=trim($input['email_e']);		
		$password=trim($input['password_e']);		
		
		$input_log=array();
        $input_log['name'] = $name;
		$input_log['location']=$location;
		$input_log['contact_no']=$contact_no;
		$input_log['email']=$email;		
		
		DB::beginTransaction();
		try {
			$data = sgrsa_agent::find($agent_id);
            $data->update($input_log);			
			
			$admin_table = DB::table('admin_table')->where(['agent_id' => $agent_id, 'publish' => 1])->first();
			$admin_tbl_id=$admin_table->id;			
			$input_admin=array();
			$input_admin['fullname'] = $name;
			$input_admin['email'] = $email;
			$input_admin['mobile_no'] = $contact_no;
			if($password<>''){
				$generate_password = \Hash::make($password);
				$input_admin['password'] = $generate_password;				
			}
			$data_admin = sgrsaAdmin::find($admin_tbl_id);
            $data_admin->update($input_admin);	
			
			DB::commit();
			return response()->json(['success'=>true, 'message'=>'Record edited successfully.']); 
		} catch (Exception $e) {
			print "Something went wrong.<br />";
			echo 'Exception Message: '. $e->getMessage();
			DB::rollback();
			return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
			exit;
		}		
	}	

}
