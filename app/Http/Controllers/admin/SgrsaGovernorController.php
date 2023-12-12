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
use App\models\sgrsa_governor;
use App\models\sgrsa_unitserial;

use App\Helpers\CoreHelper;
use Helper;
/*use App\Jobs\ValidateExcelBestiuJob;
use App\Jobs\PdfGenerateBestiuJob;
use App\Jobs\PdfGenerateBestiuJob2;
use App\Jobs\PdfGenerateBestiuJob3;
use App\Jobs\PdfGenerateBestiuJob4;*/
class SgrsaGovernorController extends Controller
{
    public function index(Request $request)
    {
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
                . " or type_name like \"%{$search}%\""
                . ")";
            }             
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));

            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id', 'type_name', 'admin_id', 'created_date'];
            
            $ffl_count = sgrsa_governor::select($columns)
            ->whereRaw($where_str, $where_params)
            //->where('site_id',$auth_site_id)
            ->count();

            //get list
            $get_list = sgrsa_governor::select($columns)
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
    	//return view('admin.sgrsa.recall');
    }
	
	public function pdfGenerate(){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);         
    }
	
    public function addForm(){
        return view('admin.sgrsa.governoradd');
    }
    public function saveData(Request $request){
		/*$validate=$request->validate([
			'type_name' => 'required'
		]);*/    
		
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
		$type_name=trim($input['type_name']);
				
		$input_log=array();
        $input_log['type_name'] = $type_name;
		$input_log['admin_id']=$admin_id;
        $input_log['supplier_id']=$supplier_id;			
		
		if($type_name==''){ 
			return response()->json(['success'=>false, 'message'=>'Please enter governor type.']); 
			exit();
		}
		
        if(sgrsa_governor::where('type_name',$type_name)->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'Governor Type is already existed.']); 
			exit();
		}
		DB::beginTransaction();
		try { 
			sgrsa_governor::create($input_log); 
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

    public function addUForm(){
        $admin_id=Auth::guard('admin')->user()->id;
		//$governorSelect[''] = "Select";        
		//$governors = DB::table('type_of_governor')->where('publish',1)->orderBy('type_name')->pluck('type_name','id')->all();   
		$governors = DB::table('type_of_governor')->where(['publish' => 1,'admin_id' => $admin_id])->orderBy('type_name')->pluck('type_name','id')->all();   
		//$governors=array_merge($governorSelect,$governor);        
		return view('admin.sgrsa.unitserialadd',compact(['governors']));
    }
    public function Unitindex(Request $request)
    {
        $admin_id=Auth::guard('admin')->user()->id;
		if($request->ajax()){
            $where_str    = "1 = ?";
            $where_str    .= " AND unit_sr_numbers.publish=1";
			$where_str    .= " AND unit_sr_numbers.admin_id=".$admin_id;
            $where_params = array(1);
            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( unit_sr_numbers.created_date like \"%{$search}%\""
                . " or unit_sr_numbers.reg_number like \"%{$search}%\""
                . " or unit_sr_numbers.used like \"%{$search}%\""
                . " or type_of_governor.type_name like \"%{$search}%\""
                . ")";
            }             
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));

            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'unit_sr_numbers.id', 'type_of_governor.type_name', 'unit_sr_numbers.reg_number', 'unit_sr_numbers.admin_id', 'unit_sr_numbers.used', 'unit_sr_numbers.created_date'];
            
            $ffl_count = sgrsa_unitserial::select($columns)
			->leftJoin('type_of_governor', 'unit_sr_numbers.governor_type_id', '=', 'type_of_governor.id')
            ->whereRaw($where_str, $where_params)
            //->where('site_id',$auth_site_id)
            ->count();

            //get list
            $get_list = sgrsa_unitserial::select($columns)
			->leftJoin('type_of_governor', 'unit_sr_numbers.governor_type_id', '=', 'type_of_governor.id')
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

    public function saveUData(Request $request){
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
		$governor_type_id=trim($input['governor_type_id']);
		$reg_number=trim($input['reg_number']);
				
		$input_log=array();
        $input_log['governor_type_id'] = $governor_type_id;
        $input_log['reg_number'] = $reg_number;
		$input_log['admin_id']=$admin_id;		
		$input_log['supplier_id']=$supplier_id;		
		
		if($governor_type_id==0){ 
			return response()->json(['success'=>false, 'message'=>'Please select governor type.']); 
			exit();
		}
		if($reg_number==''){ 
			return response()->json(['success'=>false, 'message'=>'Please enter unit serial number.']); 
			exit();
		}
        if(sgrsa_unitserial::where(['governor_type_id' => $governor_type_id,'reg_number' => $reg_number])->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'Unit serial number is already assigned.']); 
			exit();
		}
		DB::beginTransaction();
		try { 
			sgrsa_unitserial::create($input_log); 
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

	
}
