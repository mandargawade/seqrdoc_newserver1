<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use App\models\sgrsa_recall_informations;
use App\models\sgrsa_governor;
use App\models\sgrsa_unitserial;
use App\models\sgrsa_supplier;
use App\models\sgrsa_agent;
use App\models\sgrsa_allotment;
use App\models\sgrsa_hc_nos;
use App\models\sgrsa_supplier_allotment;
use App\models\sgrsa_damaged_hc;

use App\Helpers\CoreHelper;
use Helper;

class SgrsaSupplierAllotmentController extends Controller
{
    public function index(Request $request)
    {
        $admin_id=Auth::guard('admin')->user()->id;
        $LA = DB::table('admin_table')
			->select('supplier_id','role_id','fullname')
			->where('id', '=', $admin_id)
			->get();
		$supplier_id=$LA[0]->supplier_id;		
		$role_id=$LA[0]->role_id;
		if($request->ajax()){

            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            $where_str    = "1 = ?";			
            $where_str    .= " AND publish=1";
			$where_str    .= " AND supplier_id=".$supplier_id;
			/*if ($role_id==2){
				$where_str    .= " AND admin_id=".$admin_id;
			}elseif ($role_id==3){
				$where_str    .= " AND supplier_id=".$supplier_id;
			}*/
            $where_params = array(1);
            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( created_date like \"%{$search}%\""
                . " or issue_date like \"%{$search}%\""
                . " or hc_from like \"%{$search}%\""
                . " or hc_to like \"%{$search}%\""
                . " or quantity like \"%{$search}%\""
                . ")";
            }             			
            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id', 'issue_date', 'supplier_id', 'hc_from', 'hc_to', 'quantity', 'publish', 'admin_id', 'created_date', 'supplier_reply', 'supplier_reply_date'];
            $ffl_count = sgrsa_allotment::select($columns)
            ->whereRaw($where_str, $where_params)
            ->count();            
            $get_list = sgrsa_allotment::select($columns)
            ->whereRaw($where_str, $where_params);
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
			
			/*
			$where_str    .= " AND recall_informations.publish=1";
			$where_str    .= " AND recall_informations.supplier_id=".$supplier_id;
			$columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'recall_informations.id', 'agents.name', 'recall_informations.certificate_no', 'recall_informations.vehicle_reg_no', 'recall_informations.chassis_no', 'recall_informations.type_of_governor', 'recall_informations.unit_sr_no', 'recall_informations.supplier', 'recall_informations.date_of_installation', 'recall_informations.date_of_expiry', 'recall_informations.admin_id', 'recall_informations.file_name', 'recall_informations.created_date'];
			$ffl_count = sgrsa_recall_informations::select($columns)
            ->leftJoin('agents', 'recall_informations.admin_id', '=', 'agents.id')
			->whereRaw($where_str, $where_params)
            ->count();            
            $get_list = sgrsa_recall_informations::select($columns)
			->leftJoin('agents', 'recall_informations.admin_id', '=', 'agents.id')
            ->whereRaw($where_str, $where_params);
			*/
			


            return $response;
        }
    	//return view('admin.sgrsa.recall');
    }
	
	public function pdfGenerate(){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain); 
    }
	
	public function srnoGenerate(){
        //return view('admin.sgrsa.serialnotest');
    }
	
    public function addForm(){
        $admin_id=Auth::guard('admin')->user()->id;	
		$LA = DB::table('admin_table')
			->select('role_id','supplier_id')
			->where('id', '=', $admin_id)
			->get();
		$supplier_id=$LA[0]->supplier_id;	
		$role_id=$LA[0]->role_id;	
		//$agentSelect[0] = "Select";        
		$agents = DB::table('agents')->where(['supplier_id' => $supplier_id])->orderBy('name')->pluck('name','id')->all();   
		//$agents=array_merge($agentSelect,$supplier);	
		return view('admin.sgrsa.supplierallotadd',compact(['agents', 'role_id']));
    }
	
    public function allotData(Request $request){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$admin_id=Auth::guard('admin')->user()->id;		
		$record_unique_id = date('dmYHis').'_'.uniqid();
		$LA = DB::table('admin_table')
			->select('role_id','supplier_id')
			->where('id', '=', $admin_id)
			->get();
		$supplier_id=$LA[0]->supplier_id;	
		$role_id=$LA[0]->role_id;
		$input = $request->all();
		$record_id=trim($input['record_id']); //sgrsa_allotments_id
		$issue_date=trim($input['issue_date']);
		$issue_date_save=date('Y-m-d', strtotime($issue_date));
		$agent_id=trim($input['agent']);
		$hc_from=trim($input['hc_from']);
		$hc_to=trim($input['hc_to']);		
		$quantity=trim($input['quantity']);		
		$current_date=now();
		$record_unique_id = date('dmYHis').'_'.uniqid();
		
		$input_log=array();
        $input_log['issue_date'] = $issue_date_save;
		$input_log['agent_id']=$agent_id;
		$input_log['hc_from']=$hc_from;
		$input_log['hc_to']=$hc_to;		
		$input_log['quantity']=$quantity;		
		$input_log['supplier_id']=$supplier_id;		
		$input_log['admin_id']=$admin_id;		
		$input_log['sgrsa_allotments_id']=$record_id;
		$input_log['record_unique_id']=$record_unique_id;
		$input_log['created_date']=$current_date;
		
        $result = \DB::select("SELECT COUNT(*) AS num_exist FROM supplier_allotments WHERE $hc_from BETWEEN `hc_from` AND `hc_to`");
        $count = $result[0]->num_exist;
		if($count>0){
			return response()->json(['success'=>false, 'message'=>'HC Number already alloted.']);
			exit;	
		}		
		
		DB::beginTransaction();
		try {
			sgrsa_supplier_allotment::create($input_log);
			$records = sgrsa_supplier_allotment::where('record_unique_id',$record_unique_id)->first();
			$supplier_allotments_id=$records->id;
			for($i=$hc_from;$i<=$hc_to;$i++){
				DB::select(DB::raw('UPDATE `alloted_hc_numbers` SET agent_id='.$agent_id.', supplier_allotments_id='.$supplier_allotments_id.' WHERE supplier_id='.$supplier_id.' and sgrsa_allotments_id='.$record_id.' and hc_number="'.$i.'"'));	
			}
			DB::commit();
			return response()->json(['success'=>true, 'message'=>'HC numbers '.$hc_from.' To '.$hc_to.' alloted successfully.']);
		}catch (Exception $e){
			print "Something went wrong.<br />";
			echo 'Exception Message: '. $e->getMessage();
			DB::rollback();
			return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
			exit;
		}		
		
	}
    
	public function ApproveRejectRecord(Request $request)
    {
        $id=$_POST['id'];
        $reply=$_POST['reply'];
		$current_date=now();
        if($id != '' && $reply=="Approve"){            
            $records = sgrsa_allotment::where('id',$id)->first();
			$supplier_id=$records->supplier_id;
			$hc_from=$records->hc_from;
			$hc_to=$records->hc_to;
			$quantity=$records->quantity;
			for($i=$hc_from;$i<=$hc_to;$i++){
				$input_log=array();
				$input_log['sgrsa_allotments_id'] = $id;
				$input_log['supplier_id']=$supplier_id;
				$input_log['hc_number']=$i;
				sgrsa_hc_nos::create($input_log);
			}
			DB::select(DB::raw('UPDATE `sgrsa_allotments` SET supplier_reply="'.$reply.'", supplier_reply_date="'.$current_date.'" WHERE id="'.$id.'"'));
            $result = json_encode(array('rstatus'=>'Success','message'=>'This allotment is '.$reply.'ed.'));
        }else{
			DB::select(DB::raw('UPDATE `sgrsa_allotments` SET supplier_reply="'.$reply.'", supplier_reply_date="'.$current_date.'" WHERE id="'.$id.'"'));
			$result = json_encode(array('rstatus'=>'Success','message'=>'This allotment is '.$reply.'ed.'));
		}
		echo $result;
    }
	
    public function getRecordid(Request $request)
    {
		DB::beginTransaction();
		try {        
			//DB::select(DB::raw('UPDATE `sgrsa_allotments` SET publish=0 WHERE id='.$request->id));
			DB::commit();
			return response()->json(['success'=>true, 'message'=>'Record Deleted']);
		} catch (Exception $e) {
			//print "Something went wrong.<br />";
			echo 'Exception Message: '. $e->getMessage();
			DB::rollback();
			return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
			exit;
		}		
    }
	public function ViewAllotments(Request $request)
    {
		$id=$request['id'];
		$data = sgrsa_supplier_allotment::where('sgrsa_allotments_id',$id)->orderBy('hc_from', 'desc')->get();
        $i=1;
		$qty=0;
		$html_data="
		<table class='table table-hover table-bordered'>
		<tr>
		<th>#</th>
		<th>Issue Date</th>
		<th>Agent</th>		
		<th>HC From</th>		
		<th>HC To</th>		
		<th>Quantity</th>		
		</tr>
		";
		foreach ($data as $sheet_one) {
            $issue_date=$sheet_one->issue_date;
			$issue_date_show=date('d-m-Y', strtotime($issue_date));
            $agent_id=$sheet_one->agent_id;
            $agent_info = sgrsa_agent::select('id','name')->where('id',$agent_id)->first();
			$agent_name=$agent_info->name;
			$hc_from=$sheet_one->hc_from;
			$hc_to=$sheet_one->hc_to;
			$quantity=$sheet_one->quantity;
			$html_data.="
			<tr>
			<td>$i</td>
			<td>$issue_date_show</td>
			<td>$agent_name</td>			
			<td>$hc_from</td>			
			<td>$hc_to</td>			
			<td>$quantity</td>			
			</tr>
			";
            $qty +=$quantity;
			$i++;
        }
		$html_data.="
		<tr>
		<th colspan='5' style='text-align:right;'>TOTAL</th>
		<th>$qty</th>
		</tr>
		</table>
		";	
		echo $html_data;	
    }
	public function AgentAllotments(Request $request)
    {
        $admin_id=Auth::guard('admin')->user()->id;
        $LA = DB::table('admin_table')
			->select('supplier_id','agent_id','role_id','fullname')
			->where('id', '=', $admin_id)
			->get();
		$supplier_id=$LA[0]->supplier_id;		
		$agent_id=$LA[0]->agent_id;		
		$role_id=$LA[0]->role_id;
		
		if($request->ajax()){
			$auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            $where_str    = "1 = ?";			
            $where_str    .= " AND alloted_hc_numbers.supplier_id=".$supplier_id;
			
			/*if ($role_id==2){
				$where_str    .= " AND admin_id=".$admin_id;
			}elseif ($role_id==3){
				$where_str    .= " AND supplier_id=".$supplier_id;
			}*/
            $where_params = array(1);
            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
				{
					$search     = $request->input('sSearch');
					$where_str .= " and ( alloted_hc_numbers.hc_number like \"%{$search}%\""
					. " or alloted_hc_numbers.status like \"%{$search}%\""
					. " or agents.name like \"%{$search}%\""
					. ")";
				}             			
				//column that we wants to display in datatable
				$columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'alloted_hc_numbers.id', 'alloted_hc_numbers.hc_number', 'alloted_hc_numbers.status', 'agents.name'];
				
				$ffl_count = sgrsa_hc_nos::select($columns)
				->leftJoin('agents', function($join) {
				  $join->on('alloted_hc_numbers.agent_id', '=', 'agents.id');
				})
				->whereRaw($where_str, $where_params)
				->count();
				$get_list = sgrsa_hc_nos::select($columns)
				->leftJoin('agents', function($join) {
				  $join->on('alloted_hc_numbers.agent_id', '=', 'agents.id');
				})
				->whereRaw($where_str, $where_params);
				
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
    	//return view('');		
	}
	public function editform(Request $request)
    {
        $admin_id=Auth::guard('admin')->user()->id;	
		$LA = DB::table('admin_table')
			->select('role_id','supplier_id')
			->where('id', '=', $admin_id)
			->get();
		$supplier_id=$LA[0]->supplier_id;	
		$role_id=$LA[0]->role_id;	
		return view('admin.sgrsa.damageallot',compact(['role_id']));		
	}
    public function editData(Request $request){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$admin_id=Auth::guard('admin')->user()->id;		
		$record_unique_id = date('dmYHis').'_'.uniqid();
		$LA = DB::table('admin_table')
			->select('role_id','supplier_id')
			->where('id', '=', $admin_id)
			->get();
		$supplier_id=$LA[0]->supplier_id;	
		$role_id=$LA[0]->role_id;
		$input = $request->all();
		$record_id=trim($input['record_id']); //sgrsa_allotments_id
		$condition=trim($input['condition']);		
		$hc_no_enter=trim($input['hc_no_enter']);
		$hc_no_original=trim($input['hc_no_original']);			
		$current_date=now();
		$record_unique_id = date('dmYHis').'_'.uniqid();
		
		$input_log=array();
        $input_log['condition']=$condition;
		$input_log['hc_no_enter']=$hc_no_enter;
		$input_log['hc_no_original']=$hc_no_original;
		$input_log['supplier_id']=$supplier_id;		
		$input_log['admin_id']=$admin_id;		
		$input_log['alloted_hc_numbers_id']=$record_id;
		$input_log['created_date']=$current_date;
		
        $result = \DB::select("SELECT COUNT(*) AS num_exist FROM supplier_allotments WHERE ($hc_no_enter BETWEEN `hc_from` AND `hc_to`) AND supplier_id=$supplier_id");
        $count = $result[0]->num_exist;
		if($count==0){
			return response()->json(['success'=>false, 'message'=>'HC Number not alloted.']);
			exit;	
		}		
		
		DB::beginTransaction();
		try {
			sgrsa_damaged_hc::create($input_log);
			if($condition=="Replaced"){
				DB::select(DB::raw('UPDATE `alloted_hc_numbers` SET status="Assigned" WHERE hc_number='.$hc_no_enter));	
				DB::select(DB::raw('UPDATE `alloted_hc_numbers` SET status="Not Assigned" WHERE hc_number='.$hc_no_original));
			}else{				
				DB::select(DB::raw('UPDATE `alloted_hc_numbers` SET status="Assigned" WHERE hc_number='.$hc_no_enter));	
				DB::select(DB::raw('UPDATE `alloted_hc_numbers` SET status="Damaged" WHERE hc_number='.$hc_no_original));
			}
			DB::commit();
			return response()->json(['success'=>true, 'message'=>'Record saved successfully.']);
		}catch (Exception $e){
			print "Something went wrong.<br />";
			echo 'Exception Message: '. $e->getMessage();
			DB::rollback();
			return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
			exit;
		}		
		
	}	
	
	
}
