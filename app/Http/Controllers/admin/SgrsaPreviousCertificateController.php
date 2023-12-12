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
use App\models\sgrsa_hc_nos;
use App\models\sgrsa_previous_certificates;

use App\Helpers\CoreHelper;
use Helper;

class SgrsaPreviousCertificateController extends Controller
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
			$where_str    .= " AND publish=1";
            if ($role_id==2){
				$where_str    .= " AND admin_id=".$admin_id;
			}elseif ($role_id==3){
				$where_str    .= " AND supplier_id=".$supplier_id;
			}
            $where_params = array(1);
            //for seraching the keyword in datatable
            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( created_date like \"%{$search}%\""
                . " or certificate_no like \"%{$search}%\""
                . " or vehicle_reg_no like \"%{$search}%\""
                . " or chassis_no like \"%{$search}%\""
                . " or unit_sr_no like \"%{$search}%\""
                . " or supplier like \"%{$search}%\""
                . " or date_of_installation like \"%{$search}%\""
                . " or date_of_expiry like \"%{$search}%\""
                . ")";
            }             			
            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id', 'certificate_no', 'vehicle_reg_no', 'chassis_no', 'unit_sr_no', 'date_of_installation', 'date_of_expiry', 'admin_id', 'created_date'];
            $ffl_count = sgrsa_previous_certificates::select($columns)
            ->whereRaw($where_str, $where_params)
            ->count();            
            $get_list = sgrsa_previous_certificates::select($columns)
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
    	//return view('admin.sgrsa.recall');
    }
	
    public function uploadExcelForm(){
        $admin_id=Auth::guard('admin')->user()->id;
		$LA = DB::table('admin_table')
			->select('supplier_id','role_id','fullname')
			->where('id', '=', $admin_id)
			->get();
		$role_id=$LA[0]->role_id;
		$supplier_id=$LA[0]->supplier_id;
		$supplier_row = sgrsa_supplier::where('id',$supplier_id)->first();
		$supplier_name=$supplier_row->company_name;	
		return view('admin.sgrsa.uploadolddata',compact(['supplier_id', 'role_id']));
    }
	
	public function saveExcelData(Request $request){
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        //For custom loader
        $loader_token=$request['loader_token'];
        
		$admin_id=Auth::guard('admin')->user()->id;
		$LA = DB::table('admin_table')
			->select('supplier_id','role_id','fullname')
			->where('id', '=', $admin_id)
			->get();
		$role_id=$LA[0]->role_id;
		$supplier_id=$LA[0]->supplier_id;
		
		//check file is uploaded or not
        if($request->hasFile('file_name')){
            //check extension
            $file_name = $request['file_name']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'\\'.$subdomain[0].'/backend/previous_excel';			
            $fullpath = $target_path.'/'.$excelfile;
            if(!is_dir($target_path)){                
                //mkdir($target_path, 0777);
            }
            if($request['file_name']->move($target_path,$excelfile)){
                //get excel file data                
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                $auth_site_id=Auth::guard('admin')->user()->site_id;
                
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel1 = $objReader->load($fullpath);
                $sheet1 = $objPHPExcel1->getSheet(0);
                $highestColumn1 = $sheet1->getHighestColumn();
                $highestRow1 = $sheet1->getHighestDataRow();
				$rowData = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
				unset($rowData[0]);
				
				foreach ($rowData as $sheet_one) {
					$vehicle_reg_no=$sheet_one[0];
					$chassis_no=$sheet_one[1];
					$date_of_inspection=$sheet_one[2];
					$date_of_inspection_timestamp =  \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($date_of_inspection);
					$date_of_inspection = gmdate( 'Y-m-d', $date_of_inspection_timestamp); 					
					$date_of_expiry=$sheet_one[3];
					$date_of_expiry_timestamp =  \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($date_of_expiry);
					$date_of_expiry = gmdate( 'Y-m-d', $date_of_expiry_timestamp); 					
					$certificate_no=$sheet_one[4];
					$town=$sheet_one[5];
					$unit_sr_no=$sheet_one[6];
					$date_of_installation=$sheet_one[7];
					$date_of_installation_timestamp =  \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($date_of_installation);
					$date_of_installation = gmdate( 'Y-m-d', $date_of_installation_timestamp);
					$agent_name=$sheet_one[8];
					$tele_no=$sheet_one[9];
					$date_of_issue=$sheet_one[10];
					$date_of_issue_timestamp =  \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($date_of_issue);
					$date_of_issue = gmdate( 'Y-m-d', $date_of_issue_timestamp); 
					$model=$sheet_one[11];
					$make=$sheet_one[12];
					$vehicle_owner=$sheet_one[13];
					$business_reg=$sheet_one[14];
					$pin_no=$sheet_one[15];
					$vat_no=$sheet_one[16];
					$company_address=$sheet_one[17];
					$certify_by=$sheet_one[18];
					$engine_no=$sheet_one[19];
					$po_box=$sheet_one[20];
					$code=$sheet_one[21];
					$email=$sheet_one[22];
					$current_date=now();
					$input_log=array();
					$input_log['certificate_no']=$certificate_no;
					$input_log['vehicle_reg_no']=$vehicle_reg_no;
					$input_log['chassis_no']=$chassis_no; 
					$input_log['unit_sr_no']=$unit_sr_no;
					$input_log['town']=$town;
					$input_log['agent_name']=$agent_name;
					$input_log['tele_no']=$tele_no;
					$input_log['make']=$make;
					$input_log['model']=$model;
					$input_log['vehicle_owner']=$vehicle_owner;
					$input_log['business_reg']=$business_reg;
					$input_log['pin_no']=$pin_no;
					$input_log['vat_no']=$vat_no;
					$input_log['company_address']=$company_address;
					$input_log['certify_by']=$certify_by;
					$input_log['engine_no']=$engine_no;
					$input_log['po_box']=$po_box;
					$input_log['code']=$code;
					$input_log['email']=$email;
					$input_log['date_of_inspection']=$date_of_inspection;
					$input_log['date_of_installation']=$date_of_installation;
					$input_log['date_of_expiry']=$date_of_expiry;
					$input_log['date_of_issue']=$date_of_expiry;
					$input_log['status']='Pending';
					$input_log['supplier_id']=$supplier_id;
					$input_log['admin_id']=$admin_id;
					$input_log['created_date']=$current_date;
					sgrsa_previous_certificates::create($input_log);
				}
				return response()->json(['success'=>true, 'message'=>'Record added successfully.']);
            }                                   
        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        } 	
	
	
	}	
	
	public function pdfGenerate(){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain); 
    }	
	
    public function addForm(){
        $admin_id=Auth::guard('admin')->user()->id;
		$LA = DB::table('admin_table')
			->select('supplier_id','role_id','fullname')
			->where('id', '=', $admin_id)
			->get();
		$role_id=$LA[0]->role_id;
		$supplier_id=$LA[0]->supplier_id;
		$supplier_row = sgrsa_supplier::where('id',$supplier_id)->first();
		$supplier_name=$supplier_row->company_name;	
		//$supplierSelect[0] = "Select";        
		$suppliers = DB::table('suppliers')->orderBy('company_name')->pluck('company_name','id')->all();   
		//$suppliers=array_merge($supplierSelect,$supplier);	
		//$governorSelect[] = "Select";
		$governors = DB::table('type_of_governor')->where('supplier_id',$supplier_id)->orderBy('type_name')->pluck('type_name','id')->all(); 
		//$governors=array_merge($governorSelect,$governor);
		return view('admin.sgrsa.oldrecord',compact(['suppliers', 'supplier_id', 'supplier_name', 'governors','role_id']));
    }
	
	public function viewRecord(Request $request){
        $record_id=$request->id;
		$admin_id=Auth::guard('admin')->user()->id;
		$LA = DB::table('admin_table')
			->select('supplier_id','role_id','fullname')
			->where('id', '=', $admin_id)
			->get();
		$role_id=$LA[0]->role_id;
		$supplier_id=$LA[0]->supplier_id;
		$supplier_row = sgrsa_supplier::where('id',$supplier_id)->first();
		$supplier_name=$supplier_row->company_name;	
		$agents = DB::table('agents')->where(['supplier_id' => $supplier_id])->orderBy('name')->pluck('name','id')->all(); 
		$record = DB::table('previous_certificates')->where(['id' => $record_id])->first(); 
		$governors = DB::table('type_of_governor')->where('supplier_id',$supplier_id)->orderBy('type_name')->pluck('type_name','id')->all();
		return view('admin.sgrsa.viewarchiverecord',compact(['record', 'governors', 'supplier_name', 'agents', 'role_id', 'record_id']));		
	}	
	
    public function recallData(Request $request){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$admin_id=Auth::guard('admin')->user()->id;		
		$agent_id=Auth::guard('admin')->user()->agent_id;		
		$agent_name=Auth::guard('admin')->user()->fullname;
		$record_unique_id = date('dmYHis').'_'.uniqid();		

		$input = $request->all(); 
		$record_id=trim($input['record_id']);
		$certificate_nor=trim($input['certificate_no']);
		$vehicle_reg_no=trim($input['vehicle_reg_no']);
		$chassis_no=trim($input['chassis_no']);
		$type_of_governor=trim($input['type_of_governor']);
		$gtype=sgrsa_governor::where('id',$type_of_governor)->first();
		$type_name=$gtype->type_name;
		$unit_sr_no=trim($input['unit_sr_no']);
		$supplier_id=trim($input['supplier']);
		$LA = DB::table('admin_table')
			->select('id')
			->where(['supplier_id' => $supplier_id,'agent_id' => 0])
			->get();
		$supplier_admin_id=$LA[0]->id; 
		$supplier=sgrsa_supplier::where('id',$supplier_id)->first();
		$supplier_name=$supplier->company_name;
		$registration_no=$supplier->registration_no;
		$pin_no=$supplier->pin_no;
		$vat_no=$supplier->vat_no;
		$po_box=$supplier->po_box;
		$code=$supplier->code;
		$town=$supplier->town;
		$tel_no=$supplier->tel_no;
		$tel_no_two=$supplier->tel_no_two;
		$email=$supplier->email;
		$company_logo=$supplier->company_logo;
		$initials=$supplier->initials;
		$location=$town.", ".$code.", PO Box ".$po_box;
		$agent=sgrsa_agent::where('id',$agent_id)->first();
		$dealer=$agent->name;
		
		$make=trim($input['make']);
		$model=trim($input['model']);
		$hc_no=trim($input['hc_no']);
		$doi=trim($input['date_of_installation']);
		$date_of_installation=date('Y-m-d', strtotime($doi));
		$doe=trim($input['date_of_expiry']);
		$date_of_expiry=date('Y-m-d', strtotime($doe));
		$current_date=now();
		
		$input_log=array();
        $input_log['certificate_no'] = $certificate_nor;
		$input_log['vehicle_reg_no']=$vehicle_reg_no;
		$input_log['chassis_no']=$chassis_no;
		$input_log['type_of_governor']=$type_name;
		$input_log['unit_sr_no']=$unit_sr_no;
		$input_log['supplier']=$supplier_name;
		$input_log['supplier_id']=$supplier_id;
		$input_log['make']=$make;
		$input_log['model']=$model;
		$input_log['hc_no']=$hc_no;
		$input_log['date_of_installation']=$date_of_installation;
		$input_log['date_of_expiry']=$date_of_expiry;
		$input_log['record_unique_id']=$record_unique_id;
		$input_log['admin_id']=$admin_id;
		
		$record = DB::table('previous_certificates')->where(['id' => $record_id])->first();
		$notification=$record->notification;
		
		if(sgrsa_recall_informations::where(['vehicle_reg_no' => $vehicle_reg_no,'publish' => 1])->count() > 0){ 
			if (is_null($notification)){
				$inputNotification=array();
				$inputNotification['notification'] = 'Yes';
				sgrsa_previous_certificates::where('id',$record_id)->update($inputNotification);
			}	
			return response()->json(['success'=>false, 'message'=>'Vehicle Registration Number already existed.']); 
			exit();
		}		
		if(sgrsa_hc_nos::where(['hc_number' => $hc_no,'agent_id' => $agent_id])->count() == 0){ 
			return response()->json(['success'=>false, 'message'=>'Invalid HC Number.']); 
			exit();
		}	
		if(sgrsa_hc_nos::where(['hc_number' => $hc_no,'agent_id' => $agent_id,'status' =>'Assigned'])->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'HC Number already assigned.']); 
			exit();
		}
		if(sgrsa_hc_nos::where(['hc_number' => $hc_no,'agent_id' => $agent_id,'status' =>'Damaged'])->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'HC Number damaged.']); 
			exit();
		}
		if(sgrsa_unitserial::where(['governor_type_id' =>$type_of_governor,'reg_number'=>$unit_sr_no,'supplier_id'=>$supplier_id,'used' => 'Yes','publish' => 1])->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'Unit Serial Number already used.']); 
			exit();
		} 
		if(sgrsa_unitserial::where(['governor_type_id'=>$type_of_governor,'reg_number'=>$unit_sr_no,'supplier_id'=>$supplier_id,'used' => 'No','publish' => 1])->count() == 0){ 
			$input_unitserial=array();
			$input_unitserial['governor_type_id'] = $type_of_governor;
			$input_unitserial['reg_number'] = $unit_sr_no;
			$input_unitserial['admin_id']=$supplier_admin_id;		
			$input_unitserial['supplier_id']=$supplier_id;	
			sgrsa_unitserial::create($input_unitserial); 	
		}
		//exit;
		/***** set fonts *****/
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrow = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arialn.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $times = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.ttf', 'TrueTypeUnicode', '', 96);
        $timesb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.ttf', 'TrueTypeUnicode', '', 96);
        $britannicb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Britannic Bold Regular.ttf', 'TrueTypeUnicode', '', 96);
        $certificate = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Certificate.ttf', 'TrueTypeUnicode', '', 96);
		$ralewayr = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Raleway-Regular.ttf', 'TrueTypeUnicode', '', 96);	
		$ralewayb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Raleway-Bold.ttf', 'TrueTypeUnicode', '', 96);	
		$ralewaysbi = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Raleway-SemiBoldItalic.ttf', 'TrueTypeUnicode', '', 96);	
		$verdana = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\\backend\canvas\fonts\verdana.TTF', 'TrueTypeUnicode', '', 96);
		$verdanab = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\\backend\canvas\fonts\verdanab.TTF', 'TrueTypeUnicode', '', 96);
		$calibri = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\\backend\canvas\fonts\Calibri.TTF', 'TrueTypeUnicode', '', 96);
		$calibrib = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\\backend\canvas\fonts\Calibri Bold.TTF', 'TrueTypeUnicode', '', 96);
		$swis721bth = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\\backend\canvas\fonts\Swis721_Hv_BT_Heavy.TTF', 'TrueTypeUnicode', '', 96);
        
		$low_res_bg="sgrsa_blank_a4bg.jpg"; //sgrsa_blank_a4bg.jpg sgrsa_cc_bg.jpg
		//qr code info   
		$GUID=preg_replace('/\s+/', '', $input['vehicle_reg_no']);
		$dt = date("_ymdHis");
		$str=$GUID.$dt;
		$encryptedString = strtoupper(md5($str));
		//File
		$file_name = str_replace("/", "_",'PreSGRSA_'.$vehicle_reg_no.'_'.date("Ymdhms")).'.pdf';
        $filename = public_path().'\\'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name; 
		$pdfDownloadLink='/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name;
		$verify_file_name = $GUID.'.pdf';
		$verify_filename_path = public_path().'\\'.$subdomain[0].'/backend/pdf_file/'.$verify_file_name; 
		$verifyDownloadLink='/'.$subdomain[0].'/backend/pdf_file/'.$verify_file_name;		
		
		$input_log['file_name']=$file_name;
		$input_log['encryptedString']=$encryptedString;
		$input_log['created_date']=$current_date;
		$flag=0;
		DB::beginTransaction();
		try {
			sgrsa_recall_informations::create($input_log);
			DB::commit();
			DB::select(DB::raw("UPDATE `unit_sr_numbers` SET used='Yes' WHERE reg_number='".$unit_sr_no."' and governor_type_id=".$type_of_governor));
			DB::select(DB::raw("UPDATE `alloted_hc_numbers` SET status='Assigned', status_date='".$current_date."' WHERE hc_number='".$hc_no."'"));
			DB::select(DB::raw("UPDATE `previous_certificates` SET status='Closed' WHERE id=".$record_id));
			$auth_site_id=Auth::guard('admin')->user()->site_id;
			$sts = '1';
			$datetime  = date("Y-m-d H:i:s");
			$ses_id  = $admin_id;	
			$urlRelativeFilePath = 'qr/'.$encryptedString.'.png';	
			StudentTable::create(['serial_no'=>$GUID,'certificate_filename'=>$verify_file_name,'template_id'=>0,'key'=>$encryptedString,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>0]);
			$flag=1;
		} catch (Exception $e) {
			print "Something went wrong.<br />";
			echo 'Exception Message: '. $e->getMessage();
			DB::rollback();
			return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
			exit;
		}		
		if($flag==1){
		
		$TwoDigitRandomNumber = mt_rand(1,99);
		$auto_digits=sprintf("%02d", $TwoDigitRandomNumber);
		$length=2;
		$auto_chars=substr(str_shuffle(str_repeat($x='ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
		$alphanumeric_str=$initials.$auto_digits.$auto_chars;	
				
		$recall_data = sgrsa_recall_informations::where('record_unique_id',$record_unique_id)->first();
		$recordid=$recall_data->id;
		$certificate_no=$alphanumeric_str.sprintf("%06d", $recordid);
		
		$inputFresh=array();
		$inputFresh['certificate_no'] = $certificate_no;
		sgrsa_recall_informations::where('id',$recordid)->update($inputFresh);		
		
		
		$codeContents = "$vehicle_reg_no\n$supplier_name\n$doe\n\n$encryptedString";
		$qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';	
		$qrCodex = 11; 
		$qrCodey = 29;
		$qrCodeWidth =14;
		$qrCodeHeight = 14;
		$ecc = 'L';
		$pixel_Size = 1;
		$frame_Size = 1;
		$qrCodex2 = 276; 
		$qrCodey2 = 137;	
		//set background image
		$template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;

		
		/***** Start PDF Generation *****/
			/** Start pdfBig **/
			$pdfBig = new TCPDF('L', 'mm', array('297', '210'), true, 'UTF-8', false);
			$pdfBig->SetCreator(PDF_CREATOR);
			$pdfBig->SetAuthor('TCPDF');
			$pdfBig->SetTitle('Compliance Certificate');
			$pdfBig->SetSubject('');
			/***** remove default header/footer *****/
			$pdfBig->setPrintHeader(false);
			$pdfBig->setPrintFooter(false);
			$pdfBig->SetAutoPageBreak(false, 0);
			$pdfBig->AddPage(); 
			//$pdfBig->Image($template_img_generate, 0, 0, '297', '210', "JPG", '', 'R', true);		
			$pdfBig->setPageMark();			
			//qr code
			\PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
			$pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
			$pdfBig->setPageMark(); 
			$pdfBig->Image($qr_code_path, $qrCodex2, $qrCodey2, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 	
			$pdfBig->setPageMark(); 
			//Compliance Certificate Start
			$pdfBig->setFontStretching(100);
            $pdfBig->setFontSpacing(0.000);
			
			if($company_logo!=''){
				$logo_file = public_path().'\\'.$subdomain[0].'\logos\\'.$company_logo;
				//$logo_file = public_path().'\\'.$subdomain[0].'\logos\logo_test.png';
				$pdfBig->Image($logo_file, 142, 3, 20,  '', 'png', '', true, false); 
			}
			$pdfBig->SetTextColor(0, 0, 0); 
			$pdfBig->SetFont($verdana, '', 9, '', false); 
			$pdfBig->SetXY(125, 20); 
			$pdfBig->Cell(0, 0, $location, 0, false, 'L');
			$pdfBig->SetFont($verdana, '', 9, '', false); 
			$pdfBig->SetXY(125, 25);
			$pdfBig->Cell(0, 0, 'Contact:', 0, false, 'L');
			$pdfBig->SetFont($verdana, '', 9, '', false); 
			$pdfBig->SetXY(140, 25);
			$pdfBig->Cell(0, 0, $tel_no, 0, false, 'L');
			$pdfBig->SetXY(140, 30);
			$pdfBig->Cell(0, 0, $tel_no_two, 0, false, 'L');
			
			$pdfBig->setFontSpacing(0.254);
			$pdfBig->SetFont($verdanab, '', 16, '', false); 
			$pdfBig->SetTextColor(0, 0, 0);    
			$pdfBig->SetXY(64.5, 27.5);
			$pdfBig->Cell(0, 0, 'Speed Governor', 0, false, 'L');  
			
			$pdfBig->SetXY(48, 33);
			$pdfBig->SetFont($certificate, '', 28, '', false); 
			$pdfBig->Cell(0, 0, 'Compliance  Certificate', 0, false, 'L');
			
			$pdfBig->SetXY(126, 51.5);
			$pdfBig->SetFont($ralewayb, '', 11, '', false); 
			$pdfBig->Cell(0, 0, 'TIMS No.', 0, false, 'L');
			$pdfBig->SetFont($calibri, '', 11, '', false);
			$pdfBig->SetXY(146, 52);
			$pdfBig->Cell(0, 0, $certificate_no, 0, false, 'L');
			
			$pdfBig->SetXY(11, 53.5);
			$pdfBig->SetFont($calibrib, '', 10, '', false); 
			$pdfBig->Cell(0, 0, 'THIS IS TO CERTIFY THAT', 0, false, 'L');
			
			
			$pdfBig->SetFont($calibri, '', 9, '', false);
			$pdfBig->SetXY(11, 62.5);
			$pdfBig->Cell(22, 0, 'Vehicle Reg. No:', 0, false, 'L'); 
			$pdfBig->SetXY(37.5, 61);
			$pdfBig->Cell(50, 0, $vehicle_reg_no, 'B', false, 'C');
			$pdfBig->SetXY(88.3, 62.5);
			$pdfBig->Cell(20, 0, 'Chassis No.', 0, false, 'L'); 
			$pdfBig->SetXY(107, 61);
			$pdfBig->Cell(72, 0, $chassis_no, 'B', false, 'C');
			
			$pdfBig->SetXY(11, 69.5);
			$pdfBig->Cell(10, 0, 'Make', 0, false, 'L'); 
			$pdfBig->SetXY(21, 68);
			$pdfBig->Cell(76, 0, $make, 'B', false, 'C');
			$pdfBig->SetXY(97, 69.5);
			$pdfBig->Cell(10, 0, 'Model', 0, false, 'L'); 
			$pdfBig->SetXY(108, 68);
			$pdfBig->Cell(71, 0, $model, 'B', false, 'C');
			
			$pdfBig->SetXY(11, 76.5);			
			$pdfBig->Cell(80, 0, 'Is fitted with an approved Speed Governor of type:', 0, false, 'L');
			$pdfBig->SetXY(92, 75.5);
			$pdfBig->Cell(87, 0, $type_name, 'B', false, 'C');
			
			$pdfBig->SetXY(11, 83.5);
			$pdfBig->Cell(50, 0, 'Speed Governor Serial Number', 0, false, 'L'); 
			$pdfBig->SetXY(61, 82.5);
			$pdfBig->Cell(36, 0, $unit_sr_no, 'B', false, 'C');
			$pdfBig->SetXY(97, 83.5);
			$pdfBig->Cell(10, 0, 'Date', 0, false, 'L'); 
			$pdfBig->SetXY(106, 82.5);
			$pdfBig->Cell(73, 0, $doi, 'B', false, 'C');
			
			$pdfBig->SetXY(11, 90.5);
			$pdfBig->Cell(0, 0, 'The Governor is set and sealed not to exceed 80 KPH', 0, false, 'L');
			//$pdfBig->MultiCell(0, 0, 'The Governor is set and sealed not to exceed <span style="font-size:15;"><b>80 KPH</b></span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetFont($swis721bth, '', 9, '', false);
			$pdfBig->SetXY(36, 106);
			$pdfBig->Cell(0, 0, 'This Certificate is Valid For 12 months from date of issuance', 0, false, 'L');
			$pdfBig->SetXY(9, 112.5);
			$pdfBig->Cell(0, 0, 'It is an offence to tamper with the governor and it will nullify all warranty and conditions', 0, false, 'L');
			
			$pdfBig->SetFont($calibrib, '', 9, '', false);
			$pdfBig->SetXY(11, 126);
			$pdfBig->Cell(0, 0, 'Signed with stamp by Supplier', 0, false, 'L');
			$pdfBig->SetXY(61, 125.5);
			$pdfBig->Cell(49, 0, '', 'B', false, 'C');
			$pdfBig->SetXY(111, 126);
			$pdfBig->Cell(10, 0, 'Sign:', 0, false, 'L');
			$pdfBig->SetXY(121, 125.5);
			$pdfBig->Cell(58, 0, '', 'B', false, 'C');
			
			//Start Box
			$pdfBig->SetXY(9.5, 132);
			$pdfBig->Cell(169.5, 34, '', 1, false, 'C');			
			$pdfBig->SetFont($calibrib, '', 9, '', false);
			$pdfBig->SetXY(11, 134);
			$pdfBig->Cell(41, 0, 'FITTING CENTER DETAILS:', 0, false, 'L');
			$pdfBig->SetXY(12, 133.2);
			$pdfBig->Cell(39, 1, '', 'B', false, 'L');
			
			$pdfBig->SetFont($calibri, '', 9, '', false);
			$pdfBig->SetXY(11, 139);
			$pdfBig->Cell(35, 0, 'Business Reg. No.', 0, false, 'L');
			$pdfBig->SetXY(39.5, 137.5);
			$pdfBig->Cell(52, 0, $registration_no, 'B', false, 'C');
			$pdfBig->SetXY(91, 139);
			$pdfBig->Cell(10, 0, 'Pin No.', 0, false, 'L'); 
			$pdfBig->SetXY(104, 137.5);
			$pdfBig->Cell(32, 0, $pin_no, 'B', false, 'C');			
			$pdfBig->SetXY(137, 139);
			$pdfBig->Cell(15, 0, 'VAT No.', 0, false, 'L'); 
			$pdfBig->SetXY(151, 137.5);
			$pdfBig->Cell(26, 0, $vat_no, 'B', false, 'C');
			
			$pdfBig->SetXY(11, 146);
			$pdfBig->Cell(27, 0, 'Company Name', 0, false, 'L');
			$pdfBig->SetXY(37.5, 144.5);
			$pdfBig->Cell(56, 0, $supplier_name, 'B', false, 'C');
			$pdfBig->SetXY(95.5, 146);
			$pdfBig->Cell(27, 0, 'Dealer', 0, false, 'L');
			$pdfBig->SetXY(107, 144.5);
			$pdfBig->Cell(70, 0, $dealer, 'B', false, 'C');
			
			$pdfBig->SetXY(11, 153);
			$pdfBig->Cell(0, 0, 'Date of Installation/Inspection', 0, false, 'L');
			$pdfBig->SetXY(60, 151.5);
			$pdfBig->Cell(33, 0, $doi, 'B', false, 'C');
			$pdfBig->SetXY(93, 153);
			$pdfBig->Cell(0, 0, 'Certified by', 0, false, 'L');
			$pdfBig->SetXY(113, 151.5);
			$pdfBig->Cell(64, 0, $agent_name, 'B', false, 'C');
			
			$pdfBig->SetXY(11, 160);
			$pdfBig->Cell(0, 0, 'Sign (Technician)', 0, false, 'L');
			$pdfBig->SetXY(38.5, 159);
			$pdfBig->Cell(55, 0, '', 'B', false, 'C');
			$pdfBig->SetXY(95, 160);
			$pdfBig->Cell(0, 0, 'EXPIRY DATE', 0, false, 'L');
			$pdfBig->SetXY(116, 159);
			$pdfBig->Cell(61, 0, $date_of_expiry, 'B', false, 'C');			
			//End Box
			$pdfBig->SetFont($ralewayr, '', 11, '', false); 
			$pdfBig->SetXY(8.5, 171.5);
			$pdfBig->Cell(0, 0, 'Contact Number 1:', 0, false, 'L');
			$pdfBig->SetXY(49, 170);
			$pdfBig->Cell(32, 0, $tel_no, 'B', false, 'L');			
			$pdfBig->SetXY(8.5, 179);
			$pdfBig->Cell(0, 0, 'Contact Number 2:', 0, false, 'L');
			$pdfBig->SetXY(49, 178);
			$pdfBig->Cell(32, 0, $tel_no_two, 'B', false, 'L');			
			$pdfBig->SetXY(106, 179);
			$pdfBig->Cell(0, 0, 'Email', 0, false, 'L');
			$pdfBig->SetXY(120, 178);
			$pdfBig->Cell(59, 0, $email, 'B', false, 'L');
			
			$pdfBig->SetFont($ralewayb, '', 11, '', false);
			$pdfBig->SetXY(10, 195);
			$pdfBig->Cell(0, 0, 'H.C. No:', 0, false, 'L'); 
			//Compliance Certificate End
			
			$pdfBig->setFontSpacing(0.000);
			
			//WARRANTY CARD Start			
			$pdfBig->SetTextColor(255,255,255);
			$pdfBig->SetXY(194, 3); 
			$pdfBig->SetFont($ralewayb, '', 12, '', false);
			$pdfBig->Cell(39, 5, 'WARRANTY CARD', 0, false, 'L', 1);
			
			
			$pdfBig->SetTextColor(0,0,0);
			$pdfBig->SetXY(254.5, 5);
			$pdfBig->SetFont($ralewayb, '', 10, '', false); 
			$pdfBig->Cell(0, 0, 'TIMS No.', 0, false, 'L');
			$pdfBig->SetFont($calibri, '', 10, '', false);
			$pdfBig->SetXY(270, 5);
			$pdfBig->Cell(0, 0, $certificate_no, 0, false, 'L');
			
			$pdfBig->SetFont($ralewaysbi, '', 8, '', false);
			$pdfBig->SetXY(192.5, 10.5);
			$pdfBig->Cell(0, 0, 'WARRANTY TERMS AND CONDITIONS', 0, false, 'L'); 
			
			$pdfBig->SetFont($ralewayr, '', 7, '', false);
			$pdfBig->SetXY(190.5, 14);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 16.7);
			$pdfBig->MultiCell(0, 0, 'Warrant is valid for one year from the date of installation', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(190.5, 19.5);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 22);
			$pdfBig->MultiCell(100, 0, 'This is not a security device but a SPEED GOVERNOR device transmitting data online', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(190.5, 24.8);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 27.3);
			$pdfBig->MultiCell(100, 0, 'Vehicle short circuits, high voltages are not covered in the warranty.', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(190.5, 30.1);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 32.6);
			$pdfBig->MultiCell(100, 0, 'Theft of the device and its parts (printer/cable) is not covered in the warranty.', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(190.5, 35.4);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 37.9);
			$pdfBig->MultiCell(100, 0, 'Water/moisture leading to the unit failure is not covered in the warranty.', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdfBig->SetXY(190.5, 41.4);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 43.5);
			$pdfBig->MultiCell(105, 5, '<span style="line-height:1.7;">Welding of the vehicle is done at the owner’s risk as this may result in the unit failure and the unit being burnt.</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdfBig->SetXY(190.5, 51);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 53.5);
			$pdfBig->MultiCell(100, 0, 'Any complains of the unit malfunction should be raised 72 hours in the event.', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdfBig->SetXY(190.5, 56);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 58);
			$pdfBig->MultiCell(102, 8, '<span style="line-height:1.7;">In an event of breakdown, the customer maybe required to facilitate a personnel to the vehicle location and or required to avail the vehicle at our nearest yard/dealer/<br>fitting center.</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdfBig->SetXY(190.5, 69.5);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 72);
			$pdfBig->MultiCell(100, 0, 'The warrant terms and conditions are subjected to the Kenyan Laws.', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdfBig->SetXY(190.5, 76);			
			$pdfBig->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(193, 78);
			$pdfBig->MultiCell(105, 5, '<span style="line-height:1.7;">It’s the owner’s jurisdiction that the speed governor functions as required and report any malfunctionof the dealer/vendor/company.</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdfBig->SetFont($ralewaysbi, '', 7, '', false);
			$pdfBig->SetXY(193, 93);
			$pdfBig->Cell(0, 0, 'Customer’s Name:', 0, false, 'L'); 
			$pdfBig->SetXY(215, 92);
			$pdfBig->Cell(78.5, 0, '', 'B', false, 'L'); 
			$pdfBig->SetXY(193, 101.5);
			$pdfBig->Cell(0, 0, 'Signature:', 0, false, 'L');
			$pdfBig->SetXY(207, 100.5);
			$pdfBig->Cell(86, 0, '', 'B', false, 'L');

			$pdfBig->SetFont($ralewayb, '', 11, '', false);
			$pdfBig->SetXY(193, 108.5);
			$pdfBig->Cell(0, 0, 'H.C. No:', 0, false, 'L'); 	
			
			$pdfBig->SetXY(258, 131);
			$pdfBig->SetFont($britannicb, '', 10, '', false); 
			$pdfBig->Cell(0, 0, 'TIMS No.', 0, false, 'L');
			$pdfBig->SetFont($calibri, '', 9, '', false);
			$pdfBig->SetXY(272, 131.5);
			$pdfBig->Cell(0, 0, $certificate_no, 0, false, 'L');
			
			$pdfBig->setFontSpacing(0.254);
			
			$pdfBig->SetFont($britannicb, '', 8.5, '', false);
			$pdfBig->SetXY(193.5, 136.7);			 
			$pdfBig->Cell(0, 0, 'Certificate No.', 0, false, 'L');
			$pdfBig->SetXY(217.5, 135.5);
			$pdfBig->Cell(53, 0, $certificate_no, 'B', false, 'L'); 
			$pdfBig->SetXY(193.5, 143);			 
			$pdfBig->Cell(0, 0, 'Vehicle Reg No.', 0, false, 'L');
			$pdfBig->SetXY(219.5, 142);
			$pdfBig->Cell(51, 0, $vehicle_reg_no, 'B', false, 'L'); 
			$pdfBig->SetXY(193.5, 149.5);			 
			$pdfBig->Cell(0, 0, 'Chassis No.', 0, false, 'L');
			$pdfBig->SetXY(213, 148.5);
			$pdfBig->Cell(57, 0, $chassis_no, 'B', false, 'L');
			$pdfBig->SetXY(193.5, 156.5);			 
			$pdfBig->Cell(0, 0, 'Type of Governor', 0, false, 'L');
			$pdfBig->SetXY(221, 155.5);
			$pdfBig->Cell(50, 0, $type_name, 'B', false, 'L'); 
			$pdfBig->SetXY(193.5, 163);			 
			$pdfBig->Cell(0, 0, 'Supplier', 0, false, 'L');
			$pdfBig->SetXY(208, 161.5);
			$pdfBig->Cell(62.5, 0, $supplier_name, 'B', false, 'L'); 
			
			$pdfBig->SetXY(193.5, 169);			 
			$pdfBig->Cell(0, 0, 'Unit S/No.', 0, false, 'L');
			$pdfBig->SetXY(211, 168.5);
			$pdfBig->Cell(60, 0, $unit_sr_no, 'B', false, 'L'); 
			
			$pdfBig->SetXY(193.5, 175.5);			 
			$pdfBig->Cell(0, 0, 'Date of Inspection', 0, false, 'L');
			$pdfBig->SetXY(224, 174.5);
			$pdfBig->Cell(46.5, 0, $doi, 'B', false, 'L'); 
			
			$pdfBig->SetXY(193.5, 182);			 
			$pdfBig->Cell(0, 0, 'Expiry Date', 0, false, 'L');
			$pdfBig->SetXY(213, 181);
			$pdfBig->Cell(57.5, 0, $doe, 'B', false, 'L'); 
			
			$pdfBig->SetXY(193.5, 188);			 
			$pdfBig->MultiCell(18, 0, 'Director’s Signature', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(210, 195);
			$pdfBig->Cell(60.5, 0, '', 'T', false, 'L'); 
			
			//WARRANTY CARD End						
				
				   
			$pdfBig->output($filename,'F');	
			/** End pdfBig **/
			
			//return response()->json(['success'=>true, 'message'=>'Record added successfully.<br /><a href="'.$pdfDownloadLink.'" target="_blank">PDF to Print</a>']); 
			//exit;			
			
			/** Start pdf **/
			$pdf = new TCPDF('L', 'mm', array('297', '210'), true, 'UTF-8', false);
			$pdf->SetCreator(PDF_CREATOR);
			$pdf->SetAuthor('TCPDF');
			$pdf->SetTitle('Compliance Certificate');
			$pdf->SetSubject('');
			/***** remove default header/footer *****/
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
			$pdf->SetAutoPageBreak(false, 0);
			$pdf->AddPage(); 
			$pdf->Image($template_img_generate, 0, 0, '297', '210', "JPG", '', 'R', true);		
			$pdf->setPageMark();			
			//qr code
			\PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
			$pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
			$pdf->setPageMark(); 
			$pdf->Image($qr_code_path, $qrCodex2, $qrCodey2, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 	
			$pdf->setPageMark(); 
			//Compliance Certificate Start
			$pdf->setFontStretching(100);
            $pdf->setFontSpacing(0.000);
			
			if($company_logo!=''){
				$logo_file = public_path().'\\'.$subdomain[0].'\logos\\'.$company_logo;
				//$logo_file = public_path().'\\'.$subdomain[0].'\logos\logo_test.png';
				$pdf->Image($logo_file, 142, 3, 20,  '', 'png', '', true, false); 
			}
			$pdf->SetTextColor(0, 0, 0); 
			$pdf->SetFont($verdana, '', 9, '', false); 
			$pdf->SetXY(125, 20); 
			$pdf->Cell(0, 0, $location, 0, false, 'L');
			$pdf->SetFont($verdana, '', 9, '', false); 
			$pdf->SetXY(125, 25);
			$pdf->Cell(0, 0, 'Contact:', 0, false, 'L');
			$pdf->SetFont($verdana, '', 9, '', false); 
			$pdf->SetXY(140, 25);
			$pdf->Cell(0, 0, $tel_no, 0, false, 'L');
			$pdf->SetXY(140, 30);
			$pdf->Cell(0, 0, $tel_no_two, 0, false, 'L');
			
			$pdf->setFontSpacing(0.254);
			$pdf->SetFont($verdanab, '', 16, '', false); 
			$pdf->SetTextColor(0, 0, 0);    
			$pdf->SetXY(64.5, 27.5);
			$pdf->Cell(0, 0, 'Speed Governor', 0, false, 'L');  
			
			$pdf->SetXY(48, 33);
			$pdf->SetFont($certificate, '', 28, '', false); 
			$pdf->Cell(0, 0, 'Compliance  Certificate', 0, false, 'L');
			
			$pdf->SetXY(126, 51.5);
			$pdf->SetFont($ralewayb, '', 11, '', false); 
			$pdf->Cell(0, 0, 'TIMS No.', 0, false, 'L');
			$pdf->SetFont($calibri, '', 11, '', false);
			$pdf->SetXY(146, 52);
			$pdf->Cell(0, 0, $certificate_no, 0, false, 'L');
			
			$pdf->SetXY(11, 53.5);
			$pdf->SetFont($calibrib, '', 10, '', false); 
			$pdf->Cell(0, 0, 'THIS IS TO CERTIFY THAT', 0, false, 'L');
			
			
			$pdf->SetFont($calibri, '', 9, '', false);
			$pdf->SetXY(11, 62.5);
			$pdf->Cell(22, 0, 'Vehicle Reg. No:', 0, false, 'L'); 
			$pdf->SetXY(37.5, 61);
			$pdf->Cell(50, 0, $vehicle_reg_no, 'B', false, 'C');
			$pdf->SetXY(88.3, 62.5);
			$pdf->Cell(20, 0, 'Chassis No.', 0, false, 'L'); 
			$pdf->SetXY(107, 61);
			$pdf->Cell(72, 0, $chassis_no, 'B', false, 'C');
			
			$pdf->SetXY(11, 69.5);
			$pdf->Cell(10, 0, 'Make', 0, false, 'L'); 
			$pdf->SetXY(21, 68);
			$pdf->Cell(76, 0, $make, 'B', false, 'C');
			$pdf->SetXY(97, 69.5);
			$pdf->Cell(10, 0, 'Model', 0, false, 'L'); 
			$pdf->SetXY(108, 68);
			$pdf->Cell(71, 0, $model, 'B', false, 'C');
			
			$pdf->SetXY(11, 76.5);			
			$pdf->Cell(80, 0, 'Is fitted with an approved Speed Governor of type:', 0, false, 'L');
			$pdf->SetXY(92, 75.5);
			$pdf->Cell(87, 0, $type_name, 'B', false, 'C');
			
			$pdf->SetXY(11, 83.5);
			$pdf->Cell(50, 0, 'Speed Governor Serial Number', 0, false, 'L'); 
			$pdf->SetXY(61, 82.5);
			$pdf->Cell(36, 0, $unit_sr_no, 'B', false, 'C');
			$pdf->SetXY(97, 83.5);
			$pdf->Cell(10, 0, 'Date', 0, false, 'L'); 
			$pdf->SetXY(106, 82.5);
			$pdf->Cell(73, 0, $doi, 'B', false, 'C');
			
			$pdf->SetXY(11, 90.5);
			$pdf->Cell(0, 0, 'The Governor is set and sealed not to exceed 80 KPH', 0, false, 'L');
			//$pdf->MultiCell(0, 0, 'The Governor is set and sealed not to exceed <span style="font-size:15;"><b>80 KPH</b></span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetFont($swis721bth, '', 9, '', false);
			$pdf->SetXY(36, 106);
			$pdf->Cell(0, 0, 'This Certificate is Valid For 12 months from date of issuance', 0, false, 'L');
			$pdf->SetXY(9, 112.5);
			$pdf->Cell(0, 0, 'It is an offence to tamper with the governor and it will nullify all warranty and conditions', 0, false, 'L');
			
			$pdf->SetFont($calibrib, '', 9, '', false);
			$pdf->SetXY(11, 126);
			$pdf->Cell(0, 0, 'Signed with stamp by Supplier', 0, false, 'L');
			$pdf->SetXY(61, 125.5);
			$pdf->Cell(49, 0, '', 'B', false, 'C');
			$pdf->SetXY(111, 126);
			$pdf->Cell(10, 0, 'Sign:', 0, false, 'L');
			$pdf->SetXY(121, 125.5);
			$pdf->Cell(58, 0, '', 'B', false, 'C');
			
			//Start Box
			$pdf->SetXY(9.5, 132);
			$pdf->Cell(169.5, 34, '', 1, false, 'C');			
			$pdf->SetFont($calibrib, '', 9, '', false);
			$pdf->SetXY(11, 134);
			$pdf->Cell(41, 0, 'FITTING CENTER DETAILS:', 0, false, 'L');
			$pdf->SetXY(12, 133.2);
			$pdf->Cell(39, 1, '', 'B', false, 'L');
			
			$pdf->SetFont($calibri, '', 9, '', false);
			$pdf->SetXY(11, 139);
			$pdf->Cell(35, 0, 'Business Reg. No.', 0, false, 'L');
			$pdf->SetXY(39.5, 137.5);
			$pdf->Cell(52, 0, $registration_no, 'B', false, 'C');
			$pdf->SetXY(91, 139);
			$pdf->Cell(10, 0, 'Pin No.', 0, false, 'L'); 
			$pdf->SetXY(104, 137.5);
			$pdf->Cell(32, 0, $pin_no, 'B', false, 'C');			
			$pdf->SetXY(137, 139);
			$pdf->Cell(15, 0, 'VAT No.', 0, false, 'L'); 
			$pdf->SetXY(151, 137.5);
			$pdf->Cell(26, 0, $vat_no, 'B', false, 'C');
			
			$pdf->SetXY(11, 146);
			$pdf->Cell(27, 0, 'Company Name', 0, false, 'L');
			$pdf->SetXY(37.5, 144.5);
			$pdf->Cell(56, 0, $supplier_name, 'B', false, 'C');
			$pdf->SetXY(95.5, 146);
			$pdf->Cell(27, 0, 'Dealer', 0, false, 'L');
			$pdf->SetXY(107, 144.5);
			$pdf->Cell(70, 0, $dealer, 'B', false, 'C');
			
			$pdf->SetXY(11, 153);
			$pdf->Cell(0, 0, 'Date of Installation/Inspection', 0, false, 'L');
			$pdf->SetXY(60, 151.5);
			$pdf->Cell(33, 0, $doi, 'B', false, 'C');
			$pdf->SetXY(93, 153);
			$pdf->Cell(0, 0, 'Certified by', 0, false, 'L');
			$pdf->SetXY(113, 151.5);
			$pdf->Cell(64, 0, $agent_name, 'B', false, 'C');
			
			$pdf->SetXY(11, 160);
			$pdf->Cell(0, 0, 'Sign (Technician)', 0, false, 'L');
			$pdf->SetXY(38.5, 159);
			$pdf->Cell(55, 0, '', 'B', false, 'C');
			$pdf->SetXY(95, 160);
			$pdf->Cell(0, 0, 'EXPIRY DATE', 0, false, 'L');
			$pdf->SetXY(116, 159);
			$pdf->Cell(61, 0, $date_of_expiry, 'B', false, 'C');			
			//End Box
			$pdf->SetFont($ralewayr, '', 11, '', false); 
			$pdf->SetXY(8.5, 171.5);
			$pdf->Cell(0, 0, 'Contact Number 1:', 0, false, 'L');
			$pdf->SetXY(49, 170);
			$pdf->Cell(32, 0, $tel_no, 'B', false, 'L');			
			$pdf->SetXY(8.5, 179);
			$pdf->Cell(0, 0, 'Contact Number 2:', 0, false, 'L');
			$pdf->SetXY(49, 178);
			$pdf->Cell(32, 0, $tel_no_two, 'B', false, 'L');			
			$pdf->SetXY(106, 179);
			$pdf->Cell(0, 0, 'Email', 0, false, 'L');
			$pdf->SetXY(120, 178);
			$pdf->Cell(59, 0, $email, 'B', false, 'L');
			
			$pdf->SetFont($ralewayb, '', 11, '', false);
			$pdf->SetXY(10, 195);
			$pdf->Cell(0, 0, 'H.C. No:', 0, false, 'L'); 
			//Compliance Certificate End
			
			$pdf->setFontSpacing(0.000);
			
			//WARRANTY CARD Start			
			$pdf->SetTextColor(255,255,255);
			$pdf->SetXY(194, 3); 
			$pdf->SetFont($ralewayb, '', 12, '', false);
			$pdf->Cell(39, 5, 'WARRANTY CARD', 0, false, 'L', 1);
			
			
			$pdf->SetTextColor(0,0,0);
			$pdf->SetXY(254.5, 5);
			$pdf->SetFont($ralewayb, '', 10, '', false); 
			$pdf->Cell(0, 0, 'TIMS No.', 0, false, 'L');
			$pdf->SetFont($calibri, '', 10, '', false);
			$pdf->SetXY(270, 5);
			$pdf->Cell(0, 0, $certificate_no, 0, false, 'L');
			
			$pdf->SetFont($ralewaysbi, '', 8, '', false);
			$pdf->SetXY(192.5, 10.5);
			$pdf->Cell(0, 0, 'WARRANTY TERMS AND CONDITIONS', 0, false, 'L'); 
			
			$pdf->SetFont($ralewayr, '', 7, '', false);
			$pdf->SetXY(190.5, 14);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 16.7);
			$pdf->MultiCell(0, 0, 'Warrant is valid for one year from the date of installation', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(190.5, 19.5);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 22);
			$pdf->MultiCell(100, 0, 'This is not a security device but a SPEED GOVERNOR device transmitting data online', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(190.5, 24.8);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 27.3);
			$pdf->MultiCell(100, 0, 'Vehicle short circuits, high voltages are not covered in the warranty.', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(190.5, 30.1);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 32.6);
			$pdf->MultiCell(100, 0, 'Theft of the device and its parts (printer/cable) is not covered in the warranty.', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(190.5, 35.4);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 37.9);
			$pdf->MultiCell(100, 0, 'Water/moisture leading to the unit failure is not covered in the warranty.', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdf->SetXY(190.5, 41.4);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 43.5);
			$pdf->MultiCell(105, 5, '<span style="line-height:1.7;">Welding of the vehicle is done at the owner’s risk as this may result in the unit failure and the unit being burnt.</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdf->SetXY(190.5, 51);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 53.5);
			$pdf->MultiCell(100, 0, 'Any complains of the unit malfunction should be raised 72 hours in the event.', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdf->SetXY(190.5, 56);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 58);
			$pdf->MultiCell(102, 8, '<span style="line-height:1.7;">In an event of breakdown, the customer maybe required to facilitate a personnel to the vehicle location and or required to avail the vehicle at our nearest yard/dealer/<br>fitting center.</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdf->SetXY(190.5, 69.5);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 72);
			$pdf->MultiCell(100, 0, 'The warrant terms and conditions are subjected to the Kenyan Laws.', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdf->SetXY(190.5, 76);			
			$pdf->MultiCell(0, 0, '<span style="font-size:18;">•</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(193, 78);
			$pdf->MultiCell(105, 5, '<span style="line-height:1.7;">It’s the owner’s jurisdiction that the speed governor functions as required and report any malfunctionof the dealer/vendor/company.</span>', 0, 'L', 0, 0, '', '', true, 0, true);
			
			$pdf->SetFont($ralewaysbi, '', 7, '', false);
			$pdf->SetXY(193, 93);
			$pdf->Cell(0, 0, 'Customer’s Name:', 0, false, 'L'); 
			$pdf->SetXY(215, 92);
			$pdf->Cell(78.5, 0, '', 'B', false, 'L'); 
			$pdf->SetXY(193, 101.5);
			$pdf->Cell(0, 0, 'Signature:', 0, false, 'L');
			$pdf->SetXY(207, 100.5);
			$pdf->Cell(86, 0, '', 'B', false, 'L');

			$pdf->SetFont($ralewayb, '', 11, '', false);
			$pdf->SetXY(193, 108.5);
			$pdf->Cell(0, 0, 'H.C. No:', 0, false, 'L'); 	
			
			$pdf->SetXY(263, 131);
			$pdf->SetFont($britannicb, '', 10, '', false); 
			$pdf->Cell(0, 0, 'TIMS No.', 0, false, 'L');
			$pdf->SetFont($calibri, '', 9, '', false);
			$pdf->SetXY(277, 131.5);
			$pdf->Cell(0, 0, $certificate_no, 0, false, 'L');
			
			$pdf->setFontSpacing(0.254);
			
			$pdf->SetFont($britannicb, '', 8.5, '', false);
			$pdf->SetXY(193.5, 136.7);			 
			$pdf->Cell(0, 0, 'Certificate No.', 0, false, 'L');
			$pdf->SetXY(217.5, 135.5);
			$pdf->Cell(53, 0, $certificate_no, 'B', false, 'L'); 
			$pdf->SetXY(193.5, 143);			 
			$pdf->Cell(0, 0, 'Vehicle Reg No.', 0, false, 'L');
			$pdf->SetXY(219.5, 142);
			$pdf->Cell(51, 0, $vehicle_reg_no, 'B', false, 'L'); 
			$pdf->SetXY(193.5, 149.5);			 
			$pdf->Cell(0, 0, 'Chassis No.', 0, false, 'L');
			$pdf->SetXY(213, 148.5);
			$pdf->Cell(57, 0, $chassis_no, 'B', false, 'L');
			$pdf->SetXY(193.5, 156.5);			 
			$pdf->Cell(0, 0, 'Type of Governor', 0, false, 'L');
			$pdf->SetXY(221, 155.5);
			$pdf->Cell(50, 0, $type_name, 'B', false, 'L'); 
			$pdf->SetXY(193.5, 163);			 
			$pdf->Cell(0, 0, 'Supplier', 0, false, 'L');
			$pdf->SetXY(208, 161.5);
			$pdf->Cell(62.5, 0, $supplier_name, 'B', false, 'L'); 
			
			$pdf->SetXY(193.5, 169);			 
			$pdf->Cell(0, 0, 'Unit S/No.', 0, false, 'L');
			$pdf->SetXY(211, 168.5);
			$pdf->Cell(60, 0, $unit_sr_no, 'B', false, 'L'); 
			
			$pdf->SetXY(193.5, 175.5);			 
			$pdf->Cell(0, 0, 'Date of Inspection', 0, false, 'L');
			$pdf->SetXY(224, 174.5);
			$pdf->Cell(46.5, 0, $doi, 'B', false, 'L'); 
			
			$pdf->SetXY(193.5, 182);			 
			$pdf->Cell(0, 0, 'Expiry Date', 0, false, 'L');
			$pdf->SetXY(213, 181);
			$pdf->Cell(57.5, 0, $doe, 'B', false, 'L'); 
			
			$pdf->SetXY(193.5, 188);			 
			$pdf->MultiCell(18, 0, 'Director’s Signature', 0, 'L', 0, 0, '', '', true, 0, true);
			$pdf->SetXY(210, 195);
			$pdf->Cell(60.5, 0, '', 'T', false, 'L'); 
			
			//WARRANTY CARD End
				   
			$pdf->output($verify_filename_path,'F'); 
			/** End pdf **/			
		
		//exit;
		/***** End PDF Generation *****/
		
			return response()->json(['success'=>true, 'message'=>'Record added successfully.<br /><a href="'.$pdfDownloadLink.'" target="_blank">PDF to Print</a>']); 
		}
		/*$input_log['file_name']=$file_name;
		$input_log['encryptedString']=$encryptedString;
		$input_log['created_date']=$current_date;
		DB::beginTransaction();
		try {
			sgrsa_recall_informations::create($input_log);
			DB::commit();
			DB::select(DB::raw("UPDATE `unit_sr_numbers` SET used='Yes' WHERE reg_number='".$unit_sr_no."' and governor_type_id=".$type_of_governor));
			$auth_site_id=Auth::guard('admin')->user()->site_id;
			$sts = '1';
			$datetime  = date("Y-m-d H:i:s");
			$ses_id  = $admin_id;	
			$urlRelativeFilePath = 'qr/'.$encryptedString.'.png';	
			StudentTable::create(['serial_no'=>$GUID,'certificate_filename'=>$verify_file_name,'template_id'=>0,'key'=>$encryptedString,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>0]);
			//return response()->json(['success'=>true, 'message'=>'Record added successfully.<br /><a href="'.$pdfDownloadLink.'" target="_blank">PDF to Print</a><br /><a href="'.$verifyDownloadLink.'" target="_blank">Verification PDF</a>']); 
			return response()->json(['success'=>true, 'message'=>'Record added successfully.<br /><a href="'.$pdfDownloadLink.'" target="_blank">PDF to Print</a>']); 
		} catch (Exception $e) {
			print "Something went wrong.<br />";
			echo 'Exception Message: '. $e->getMessage();
			DB::rollback();
			return response()->json(['success'=>false, 'message'=>$e->getMessage()]);
			exit;
		}	*/	
	}

    public function searchVehicleNo(Request $request)
    {
        $vno=$request->vno;
        $supplier_id=$request->supplier_id;
		$records = DB::table('previous_certificates')
            ->where(['vehicle_reg_no' => $vno, 'supplier_id' => $supplier_id,'status' => 'Pending', 'publish' => 1])
			->first();
        return response()->json($records);
    }

    public function _getRecordid(Request $request)
    {
		DB::beginTransaction();
		try {        
			DB::select(DB::raw('UPDATE `recall_informations` SET publish=0 WHERE id='.$request->id));
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
	
    
 
    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,$blob)
    {

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        $pdfActualPath=public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;

        
        copy($file1, $file2);
        
        $aws_qr = \File::copy($file2,$pdfActualPath);
                
          
        @unlink($file2);

        @unlink($file1);

       

        $sts = '1';
        $datetime  = date("Y-m-d H:i:s");
        $ses_id  = $admin_id["id"];
        $certName = str_replace("/", "_", $certName);

        $get_config_data = Config::select('configuration')->first();
     
        $c = explode(", ", $get_config_data['configuration']);
        $key = "";


        $tempDir = public_path().'/backend/qr';
        $key = strtoupper(md5($serial_no)); 
        $codeContents = $key;
        $fileName = $key.'.png'; 
        
        $urlRelativeFilePath = 'qr/'.$fileName; 

        if($systemConfig['sandboxing'] == 1){
        $resultu = SbStudentTable::where('serial_no','T-'.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = SbStudentTable::create(['serial_no'=>'T-'.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }else{
        $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }
        
    }

    public function getPrintCount($serial_no)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    }

    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$card_serial_no)
    {
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>'T-'.$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }else{
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);    
        }
    }

    public function nextPrintSerial()
    {
        $current_year = 'PN/' . $this->getFinancialyear() . '/';
        // find max
        $maxNum = 0;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 10), UNSIGNED)), 0) AS next_num "
                . "FROM printing_details WHERE SUBSTR(print_serial_no, 1, 9) = '$current_year'");
        //get next num
        $maxNum = $result[0]->next_num + 1;
        
        return $current_year . $maxNum;
    }

    public function getNextCardNo($template_name)
    { 
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result = \DB::select("SELECT * FROM sb_card_serial_numbers WHERE template_name = '$template_name'");
        }else{
        $result = \DB::select("SELECT * FROM card_serial_numbers WHERE template_name = '$template_name'");
        }
          
        return $result[0];
    }

    public function updateCardNo($template_name,$count,$next_serial_no)
    { 
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        if($systemConfig['sandboxing'] == 1){
        $result = \DB::select("UPDATE sb_card_serial_numbers SET card_count='$count',next_serial_no='$next_serial_no' WHERE template_name = '$template_name'");
        }else{
        $result = \DB::select("UPDATE card_serial_numbers SET card_count='$count',next_serial_no='$next_serial_no' WHERE template_name = '$template_name'");
        }
        
        return $result;
    }


    public function getFinancialyear()
    {
        $yy = date('y');
        $mm = date('m');
        $fy = str_pad($yy, 2, "0", STR_PAD_LEFT);
        if($mm > 3)
            $fy = $fy . "-" . ($yy + 1);
        else
            $fy = str_pad($yy - 1, 2, "0", STR_PAD_LEFT) . "-" . $fy;
        return $fy;
    }

    public function createTemp($path){
        //create ghost image folder
        $tmp = date("ymdHis");
        
        $tmpname = tempnam($path, $tmp);
        //unlink($tmpname);
        //mkdir($tmpname);
         if (file_exists($tmpname)) {
         unlink($tmpname);
        }
        mkdir($tmpname, 0777);
        return $tmpname;
    }

    public function indexPC(Request $request)
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
				$where_str    .= " AND previous_certificates.publish=1";
				$where_str    .= " AND previous_certificates.notification='Yes'";
				$where_params = array(1);
				//for seraching the keyword in datatable
				if (!empty($request->input('sSearch')))
				{
					$search     = $request->input('sSearch');
					$where_str .= " and (previous_certificates.created_date like \"%{$search}%\""
					. " or previous_certificates.certificate_no like \"%{$search}%\""
					. " or previous_certificates.vehicle_reg_no like \"%{$search}%\""
					. " or previous_certificates.chassis_no like \"%{$search}%\""
					. " or previous_certificates.unit_sr_no like \"%{$search}%\""
					. " or previous_certificates.date_of_installation like \"%{$search}%\""
					. " or previous_certificates.date_of_expiry like \"%{$search}%\""
					. " or suppliers.company_name like \"%{$search}%\""
					. ")";
				}             			
				//column that we wants to display in datatable
				
				$columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'previous_certificates.id', 'previous_certificates.certificate_no', 'previous_certificates.vehicle_reg_no', 'previous_certificates.chassis_no', 'previous_certificates.unit_sr_no', 'previous_certificates.date_of_installation', 'previous_certificates.date_of_expiry', 'previous_certificates.admin_id', 'previous_certificates.created_date', 'suppliers.company_name'];
				
				$ffl_count = sgrsa_previous_certificates::select($columns)
				->leftJoin('suppliers', function($join) {
				  $join->on('previous_certificates.supplier_id', '=', 'suppliers.id');
				})
				->whereRaw($where_str, $where_params)
				->count();
				$get_list = sgrsa_previous_certificates::select($columns)
				->leftJoin('suppliers', function($join) {
				  $join->on('previous_certificates.supplier_id', '=', 'suppliers.id');
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
    	return view('admin.sgrsa.adminpreviousdataview');
    }	
    
}
