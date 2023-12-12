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

use App\Helpers\CoreHelper;
use Helper;

class SgrsaAllotmentController extends Controller
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
		if ($role_id==1){
			if($request->ajax()){

				$auth_site_id=Auth::guard('admin')->user()->site_id;
				//for serial number
				$iDisplayStart=$request->input('iDisplayStart'); 
				DB::statement(DB::raw('set @rownum='.$iDisplayStart));
				$where_str    = "1 = ?";
				
				$where_str    .= " AND sgrsa_allotments.publish=1";
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
					$where_str .= " and ( sgrsa_allotments.created_date like \"%{$search}%\""
					. " or sgrsa_allotments.issue_date like \"%{$search}%\""
					. " or sgrsa_allotments.hc_from like \"%{$search}%\""
					. " or sgrsa_allotments.hc_to like \"%{$search}%\""
					. " or sgrsa_allotments.quantity like \"%{$search}%\""
					. " or sgrsa_allotments.supplier_reply like \"%{$search}%\""
					. " or suppliers.company_name like \"%{$search}%\""
					. ")";
				}             			
				//column that we wants to display in datatable
				$columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'sgrsa_allotments.id', 'sgrsa_allotments.issue_date', 'sgrsa_allotments.supplier_id', 'sgrsa_allotments.hc_from', 'sgrsa_allotments.hc_to', 'sgrsa_allotments.quantity', 'sgrsa_allotments.publish', 'sgrsa_allotments.admin_id', 'sgrsa_allotments.created_date', 'sgrsa_allotments.supplier_reply', 'sgrsa_allotments.supplier_reply_date', 'suppliers.company_name'];
				
				$ffl_count = sgrsa_allotment::select($columns)
				->leftJoin('suppliers', function($join) {
				  $join->on('sgrsa_allotments.supplier_id', '=', 'suppliers.id');
				})
				->whereRaw($where_str, $where_params)
				->count();
				$get_list = sgrsa_allotment::select($columns)
				->leftJoin('suppliers', function($join) {
				  $join->on('sgrsa_allotments.supplier_id', '=', 'suppliers.id');
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
			->select('role_id')
			->where('id', '=', $admin_id)
			->get();
		$role_id=$LA[0]->role_id;	
		//$supplierSelect[0] = "Select";        
		$suppliers = DB::table('suppliers')->orderBy('company_name')->pluck('company_name','id')->all();   
		//$suppliers=array_merge($supplierSelect,$supplier);	
		return view('admin.sgrsa.sgrsaallotadd',compact(['suppliers', 'role_id']));
    }
	
    public function allotData(Request $request){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$admin_id=Auth::guard('admin')->user()->id;		
		$record_unique_id = date('dmYHis').'_'.uniqid();
		$input = $request->all(); 
		
		$issue_date=trim($input['issue_date']);
		$issue_date_save=date('Y-m-d', strtotime($issue_date));
		$supplier_id=trim($input['supplier']);
		$hc_from=trim($input['hc_from']);
		$hc_to=trim($input['hc_to']);		
		$quantity=trim($input['quantity']);		
		$current_date=now();
		
		$input_log=array();
        $input_log['issue_date'] = $issue_date_save;
		$input_log['supplier_id']=$supplier_id;
		$input_log['hc_from']=$hc_from;
		$input_log['hc_to']=$hc_to;		
		$input_log['quantity']=$quantity;		
		$input_log['admin_id']=$admin_id;		
		$input_log['created_date']=$current_date;
		
        $result = \DB::select("SELECT COUNT(*) AS num_exist FROM sgrsa_allotments WHERE $hc_from BETWEEN `hc_from` AND `hc_to` and supplier_reply<>'Reject'");
        $count = $result[0]->num_exist;
		if($count>0){
			return response()->json(['success'=>false, 'message'=>'HC Number already alloted.']);
			exit;	
		}		
		//return response()->json(['success'=>true, 'message'=>'Record added successfully.']);
		//exit;
		DB::beginTransaction();
		try {
			sgrsa_allotment::create($input_log);
			DB::commit();
			return response()->json(['success'=>true, 'message'=>'Record added successfully.']);
		}catch (Exception $e){
			print "Something went wrong.<br />";
			echo 'Exception Message: '. $e->getMessage();
			DB::rollback();
			return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
			exit;
		}
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


}
