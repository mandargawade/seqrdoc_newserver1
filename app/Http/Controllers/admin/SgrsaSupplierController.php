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
use App\models\sgrsa_supplier;
use App\models\sgrsa_governor;
use App\models\sgrsa_unitserial;
use App\models\sgrsaAdmin;

use App\Helpers\CoreHelper;
use Helper;

class SgrsaSupplierController extends Controller
{	
    public function index(Request $request)
    {
        if($request->ajax()){
            $where_str    = "1 = ?";
            $where_str    .= " AND publish=1";
            $where_params = array(1);
            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( created_date like \"%{$search}%\""
                . " or company_name like \"%{$search}%\""
                . " or registration_no like \"%{$search}%\""
                . " or pin_no like \"%{$search}%\""
                . " or vat_no like \"%{$search}%\""
                . " or po_box like \"%{$search}%\""
                . " or code like \"%{$search}%\""
                . " or town like \"%{$search}%\""
                . " or tel_no like \"%{$search}%\""
                . " or email like \"%{$search}%\""
                . ")";
            }             
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));

            //column that we wants to display in datatable
            //$columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id',DB::raw('IFNULL(`generated_at`,"") AS generated_at')];
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id', 'company_name', 'registration_no', 'pin_no', 'vat_no', 'po_box', 'code', 'town', 'tel_no', 'email', 'publish', 'admin_id', 'created_date'];
            
            $ffl_count = sgrsa_supplier::select($columns)
            ->whereRaw($where_str, $where_params)
            //->where('site_id',$auth_site_id)
            ->count();

            //get list
            $get_list = sgrsa_supplier::select($columns)
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
        return view('admin.sgrsa.supplieradd');
    }
	
	public function editForm(){
        $supplier_id=Auth::guard('admin')->user()->supplier_id;
		return view('admin.sgrsa.supplieredit',compact(['supplier_id']));
    }

    public function supplierData(Request $request){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$auth_site_id=Auth::guard('admin')->user()->site_id;
		$admin_id=Auth::guard('admin')->user()->id;
		$datetime  = date("Y-m-d H:i:s");	
		
		$input = $request->all(); 
		$company_name=trim($input['company_name']);
		$registration_no=trim($input['registration_no']);
		$pin_no=trim($input['pin_no']);
		$vat_no=trim($input['vat_no']);
		$po_box=trim($input['po_box']);
		$code=trim($input['code']);
		$town=trim($input['town']);
		$tel_no=trim($input['tel_no']);
		$tel_no_two=trim($input['tel_no_two']);
		$email=trim($input['email']);
		$initials=trim($input['initials']);
		$username=trim($input['username']);
		$password=trim($input['password']);		

		if($request->hasFile('company_logo')) {
			//filename to store
			$filename = preg_replace('/\s+/', '', $registration_no);
			$filename = str_replace('/', '-', $filename);
			$extension = $request->file('company_logo')->getClientOriginalExtension();
			$filenametostore = $filename.'_'.uniqid().'.'.$extension; 	
			$logo_folder = public_path().'\\'.$subdomain[0].'\logos';			
			$request->file('company_logo')->move($logo_folder, $filenametostore);
		}else{
			$filenametostore = ''; 
		}
		
		$input_log=array();
        $input_log['company_name'] = $company_name;
		$input_log['registration_no']=$registration_no;
		$input_log['pin_no']=$pin_no;
		$input_log['vat_no']=$vat_no;
		$input_log['po_box']=$po_box;
		$input_log['code']=$code;
		$input_log['town']=$town;
		$input_log['tel_no']=$tel_no;
		$input_log['tel_no_two']=$tel_no_two;
		$input_log['email']=$email;
		$input_log['company_logo']=$filenametostore;
		$input_log['initials']=$initials;
		$input_log['admin_id']=$admin_id;		
		$input_log['created_date']=$datetime;		
				
        if(sgrsa_supplier::where('company_name',$company_name)->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'Business Name already existed.']); 
			exit();
		}
		if(sgrsaAdmin::where('username',$username)->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'User Name already existed.']); 
			exit();
		}
		$results = DB::select(DB::raw('SELECT AUTO_INCREMENT AS NEXT_ID FROM `information_schema`.`tables` WHERE  TABLE_NAME = "suppliers" AND table_schema = "seqr_d_sgrsa"'));
	    $supplier_id=trim($results[0]->NEXT_ID);
		
		DB::beginTransaction();
		try {
			sgrsa_supplier::create($input_log);							
			$generate_password = \Hash::make($password);
			$input_admin=array();
			$input_admin['fullname'] = $company_name;
			$input_admin['username'] = $username;
			$input_admin['email'] = $email;
			$input_admin['mobile_no'] = $tel_no;
			$input_admin['password'] = $generate_password;
			$input_admin['status'] = 1;
			$input_admin['role_id'] = 3;
			$input_admin['publish'] = 1;
			$input_admin['supplier_id'] = $supplier_id;
			$input_admin['site_id'] = $auth_site_id;
			$create=sgrsaAdmin::create($input_admin);
			
			/****** Start permissions ******/
			$user_id=$create->id; //lastInsertID	
			$created_by = auth('admin')->user()->id;
			$role = 3; //Supplier
			$role_permission_group = Array
			(
				'sgrsa-governor.addform',
				'sgrsa-governor.adduform',
				'sgrsa-agent.addform',
			);
			
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
		$records = DB::table('suppliers')->where(['id' => $id, 'publish' => 1])->first();
		$suppliers = DB::table('admin_table')->where(['supplier_id' => $id, 'agent_id' => 0, 'publish' => 1])->first();
		$username=$suppliers->username;
		return response()->json(['success'=>true, 'res'=>$records, 'supplier_id' => $id, 'username'=>$username, 'message'=>'Record Found']);	
	}

    public function editData(Request $request){
        $auth_site_id=Auth::guard('admin')->user()->site_id;
		$admin_id=Auth::guard('admin')->user()->id;
		$datetime  = date("Y-m-d H:i:s");	
		
		$input = $request->all(); 
		$company_name=trim($input['company_name_e']);
		$company_name_chk=trim($input['company_name_chk']);		
		$supplier_id=trim($input['supplier_id']);
		$registration_no=trim($input['registration_no_e']);
		$pin_no=trim($input['pin_no_e']);
		$vat_no=trim($input['vat_no_e']);
		$po_box=trim($input['po_box_e']);
		$code=trim($input['code_e']);
		$town=trim($input['town_e']);
		$tel_no=trim($input['tel_no_e']);
		$tel_no_two=trim($input['tel_no_two_e']);
		$email=trim($input['email_e']);	
		$initials=trim($input['initials_e']);	
		$company_logo_chk=trim($input['company_logo_chk']);	
		$password=trim($input['password_e']);
		
		$input_log=array();
		if($request->hasFile('company_logo_e')) {
			//filename to store
			$filename = preg_replace('/\s+/', '', $registration_no);
			$filename = str_replace('/', '-', $filename);
			$extension = $request->file('company_logo_e')->getClientOriginalExtension();
			$filenametostore = $filename.'_'.uniqid().'.'.$extension; 	
			$logo_folder = public_path().'\\'.$subdomain[0].'\logos';			
			$request->file('company_logo_e')->move($logo_folder, $filenametostore);
			$input_log['company_logo']=$filenametostore;
		}	
        $input_log['company_name'] = $company_name;
		$input_log['registration_no']=$registration_no;
		$input_log['pin_no']=$pin_no;
		$input_log['vat_no']=$vat_no;
		$input_log['po_box']=$po_box;
		$input_log['code']=$code;
		$input_log['town']=$town;
		$input_log['tel_no']=$tel_no;
		$input_log['tel_no_two']=$tel_no_two;
		$input_log['email']=$email;
		$input_log['initials']=$initials;
		//$input_log['company_logo']=$filenametostore;
		//$input_log['admin_id']=$admin_id;		
		//$input_log['created_date']=$datetime;		
				
        if(strcmp($company_name, $company_name_chk) <> 0){
			if(sgrsa_supplier::where('company_name',$company_name)->count() > 0){ 
				return response()->json(['success'=>false, 'message'=>'Business Name already existed.']); 
				exit();
			}
		}
		DB::beginTransaction();
		try {
			$data = sgrsa_supplier::find($supplier_id);
            $data->update($input_log);			
			
			$admin_table = DB::table('admin_table')->where(['supplier_id' => $supplier_id, 'agent_id' => 0, 'publish' => 1])->first();
			$admin_tbl_id=$admin_table->id;			
			$input_admin=array();
			$input_admin['fullname'] = $company_name;
			$input_admin['email'] = $email;
			$input_admin['mobile_no'] = $tel_no;
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
