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

use App\Helpers\CoreHelper;
use Helper;

class SgrsaAgentAllotmentController extends Controller
{
    public function index(Request $request)
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
            $where_str    .= " AND supplier_allotments.publish=1";
			$where_str    .= " AND supplier_allotments.agent_id=".$agent_id;
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
					$where_str .= " and ( supplier_allotments.created_date like \"%{$search}%\""
					. " or supplier_allotments.issue_date like \"%{$search}%\""
					. " or supplier_allotments.hc_from like \"%{$search}%\""
					. " or supplier_allotments.hc_to like \"%{$search}%\""
					. " or supplier_allotments.quantity like \"%{$search}%\""
					. " or agents.name like \"%{$search}%\""
					. ")";
				}             			
				//column that we wants to display in datatable
				$columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'supplier_allotments.id', 'supplier_allotments.issue_date', 'supplier_allotments.supplier_id', 'supplier_allotments.hc_from', 'supplier_allotments.hc_to', 'supplier_allotments.quantity', 'supplier_allotments.publish', 'supplier_allotments.admin_id', 'supplier_allotments.created_date', 'agents.name'];
				
				$ffl_count = sgrsa_supplier_allotment::select($columns)
				->leftJoin('agents', function($join) {
				  $join->on('supplier_allotments.agent_id', '=', 'agents.id');
				})
				->whereRaw($where_str, $where_params)
				->count();
				$get_list = sgrsa_supplier_allotment::select($columns)
				->leftJoin('agents', function($join) {
				  $join->on('supplier_allotments.agent_id', '=', 'agents.id');
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
    	return view('admin.sgrsa.agentallot');
    }
	
	public function pdfGenerate(){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain); 
    }
	
	public function srnoGenerate(){
        //return view('admin.sgrsa.serialnotest');
    }
	
    public function addForm(){
        /*$admin_id=Auth::guard('admin')->user()->id;	
		$LA = DB::table('admin_table')
			->select('role_id','supplier_id')
			->where('id', '=', $admin_id)
			->get();
		$supplier_id=$LA[0]->supplier_id;	
		$role_id=$LA[0]->role_id;	
		//$agentSelect[0] = "Select";        
		$agents = DB::table('agents')->where(['supplier_id' => $supplier_id])->orderBy('name')->pluck('name','id')->all();   
		//$agents=array_merge($agentSelect,$supplier);	
		return view('admin.sgrsa.supplierallotadd',compact(['agents', 'role_id']));*/
    }
	
    public function allotData(Request $request){
        		
		
	}
    
	public function ApproveRejectRecord(Request $request)
    {
       
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

}
