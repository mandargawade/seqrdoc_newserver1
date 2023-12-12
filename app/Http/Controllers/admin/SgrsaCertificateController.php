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

use App\Helpers\CoreHelper;
use Helper;
/*use App\Jobs\ValidateExcelBestiuJob;
use App\Jobs\PdfGenerateBestiuJob;
use App\Jobs\PdfGenerateBestiuJob2;
use App\Jobs\PdfGenerateBestiuJob3;
use App\Jobs\PdfGenerateBestiuJob4;*/
class SgrsaCertificateController extends Controller
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
                . " or type_of_governor like \"%{$search}%\""
                . " or unit_sr_no like \"%{$search}%\""
                . " or supplier like \"%{$search}%\""
                . " or date_of_installation like \"%{$search}%\""
                . " or date_of_expiry like \"%{$search}%\""
                . ")";
            }             			
            //column that we wants to display in datatable
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'), 'id', 'certificate_no', 'vehicle_reg_no', 'chassis_no', 'type_of_governor', 'unit_sr_no', 'supplier', 'date_of_installation', 'date_of_expiry', 'admin_id', 'file_name', 'created_date'];
            $ffl_count = sgrsa_recall_informations::select($columns)
            ->whereRaw($where_str, $where_params)
            ->count();            
            $get_list = sgrsa_recall_informations::select($columns)
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
        return view('admin.sgrsa.serialnotest');
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
		return view('admin.sgrsa.recalladd',compact(['suppliers', 'supplier_id', 'supplier_name', 'governors','role_id']));
    }
	
    public function recallData(Request $request){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$admin_id=Auth::guard('admin')->user()->id;		
		$agent_id=Auth::guard('admin')->user()->agent_id;		
		$agent_name=Auth::guard('admin')->user()->fullname;
		$record_unique_id = date('dmYHis').'_'.uniqid();		

		$input = $request->all(); 
		$certificate_nor=trim($input['certificate_no']);
		$vehicle_reg_no=trim($input['vehicle_reg_no']);
		$chassis_no=trim($input['chassis_no']);
		$type_of_governor=trim($input['type_of_governor']);
		$gtype=sgrsa_governor::where('id',$type_of_governor)->first();
		$type_name=$gtype->type_name;
		$unit_sr_no=trim($input['unit_sr_no']);
		$supplier_id=trim($input['supplier']);
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
		
        if(sgrsa_recall_informations::where(['vehicle_reg_no' => $vehicle_reg_no,'publish' => 1])->count() > 0){ 
			return response()->json(['success'=>false, 'message'=>'Vehicle Registration Number already existed.']); 
			exit();
		}
		if(sgrsa_unitserial::where(['governor_type_id'=>$type_of_governor, 'reg_number' => $unit_sr_no,'supplier_id'=>$supplier_id,'publish' => 1,'used' => 'No'])->count() == 0){ 
			return response()->json(['success'=>false, 'message'=>'Invalid Unit Serial Number.']); 
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

    public function getUnitsrno(Request $request)
    {
        $records = DB::table('unit_sr_numbers')
            ->where(['governor_type_id' => $request->type_id, 'used' => 'No', 'publish' => 1])
			->orderBy('reg_number')
            ->get();
        
        if (count($records) > 0) {
            return response()->json($records);
        }
    }

    public function getRecordid(Request $request)
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
	
	public function RenewForm(Request $request){
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
		return view('admin.sgrsa.recallrenew',compact(['supplier_name', 'role_id', 'record_id']));		
	}
	
	public function RenewRecall(Request $request)
    {
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   		
		$id=$request->record_id;
		$renew_hc_no=$request->renew_hc_no;
		$doe=trim($request->renew_date_of_expiry); //date_of_expiry
		$date_of_expiry=date('Y-m-d', strtotime($doe));		
		

		
		//$new_doe = date('Y-m-d',strtotime(date("Y-m-d", time()) . " + 365 day"));
		$record_unique_id = date('dmYHis').'_'.uniqid();
		
		$records = DB::table('recall_informations')->where(['id' => $id, 'publish' => 1])->first();
		
		$certificate_no=$records->certificate_no;        
		$vehicle_reg_no=$records->vehicle_reg_no;
		$chassis_no=$records->chassis_no;
		$type_name=$records->type_of_governor;
		$type_of_governor=$records->type_of_governor;
		$unit_sr_no=$records->unit_sr_no;
		//$supplier_name=$records->supplier;
		$supplier_id=$records->supplier_id;
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
		
		$admin_id=$records->admin_id;			
		$agent_data = DB::table('admin_table')->where(['id' => $admin_id])->first();
		$agent_name=$agent_data->fullname;
		$agent_id=$agent_data->agent_id;
		$agent=sgrsa_agent::where('id',$agent_data->agent_id)->first();
		$dealer=$agent->name;
		
		$make=$records->make;
		$model=$records->model;
		//$hc_no=$records->hc_no;		
		$hc_no=$renew_hc_no;		
		$doi=$records->date_of_installation;
		$old_encryptedString=$records->encryptedString;
		$current_date=now();
		        
		if(sgrsa_hc_nos::where(['hc_number' => $hc_no,'agent_id' => $agent_id])->count() == 0){ 
			return response()->json(['success'=>false, 'message'=>'Invalid HC Number.'.$agent_id]); 
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
		
		$input_log=array();
		$input_log['certificate_no']=$certificate_no;        
		$input_log['vehicle_reg_no']=$vehicle_reg_no;
		$input_log['chassis_no']=$chassis_no;
		$input_log['type_of_governor']=$type_of_governor;
		$input_log['unit_sr_no']=$unit_sr_no;
		$input_log['supplier']=$supplier_name;
		$input_log['supplier_id']=$supplier_id;
		$input_log['make']=$make;
		$input_log['model']=$model;
		$input_log['hc_no']=$hc_no;
		$input_log['date_of_installation']=$doi;
		$input_log['date_of_expiry']=$date_of_expiry;
		$input_log['record_unique_id']=$record_unique_id;
		$input_log['admin_id']=$admin_id;	
		
		$low_res_bg="sgrsa_bg.jpg";
		//qr code info   
		$GUID=preg_replace('/\s+/', '', $vehicle_reg_no);
		$dt = date("_ymdHis");
		$str=$GUID.$dt;
		$encryptedString = strtoupper(md5($str));
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
		//File
		$file_name = str_replace("/", "_",'SGRSA_'.$vehicle_reg_no.'_'.date("Ymdhms")).'.pdf';
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
			$auth_site_id=Auth::guard('admin')->user()->site_id;
			$sts = '1';
			$datetime  = date("Y-m-d H:i:s");
			$ses_id  = $admin_id;	
			$urlRelativeFilePath = 'qr/'.$encryptedString.'.png';	
			StudentTable::create(['serial_no'=>$GUID,'certificate_filename'=>$verify_file_name,'template_id'=>0,'key'=>$encryptedString,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>0]);
			DB::select(DB::raw("UPDATE `student_table` SET publish='0' WHERE `key`='".$old_encryptedString."'"));
			DB::select(DB::raw("UPDATE `recall_informations` SET publish=0 WHERE id=".$id));
			DB::select(DB::raw("UPDATE `alloted_hc_numbers` SET status='Assigned', status_date='".$current_date."' WHERE hc_number='".$hc_no."'"));
			
			$flag=1;
			//return response()->json(['success'=>true, 'message'=>'Record has renewed successfully.']); 
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
			$pdfBig->Cell(61, 0, $doe, 'B', false, 'C');			
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
			
			//return response()->json(['success'=>true, 'message'=>'Record renewed successfully.<br /><a href="'.$pdfDownloadLink.'" target="_blank">PDF to Print</a>']); 
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

		
    }
	
    public function validateExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $template_id=100;
        $dropdown_template_id = $request['template_id'];
        /* 1=Basic, 2=B.Ed. 1st Year, 3=B.Ed. Final, 4=Grade Final, 5=Pharma */
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
       
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
         //check file is uploaded or not
        if($request->hasFile('field_file')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'/backend/canvas/dummy_images/'.$template_id;
            $fullpath = $target_path.'/'.$excelfile;

            if(!is_dir($target_path)){
                
                            mkdir($target_path, 0777);
                        }

            if($request['field_file']->move($target_path,$excelfile)){
                //get excel file data
                
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                $auth_site_id=Auth::guard('admin')->user()->site_id;

                $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    if($systemConfig['sandboxing'] == 1){
                        $sandbox_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                        //if directory not exist make directory
                        if(!is_dir($sandbox_directory)){
                
                            mkdir($sandbox_directory, 0777);
                        }

                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                        
                    }else{
                        
                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                    }
                }
                else{

                      $sandbox_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                    //if directory not exist make directory
                    if(!is_dir($sandbox_directory)){
            
                        mkdir($sandbox_directory, 0777);
                    }

                    if($systemConfig['sandboxing'] == 1){
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile);
                        
                    }else{
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile);
                        
                    }
                }
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel1 = $objReader->load($fullpath);
                $sheet1 = $objPHPExcel1->getSheet(0);
                $highestColumn1 = $sheet1->getHighestColumn();
                $highestRow1 = $sheet1->getHighestDataRow();
                $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                 //For checking certificate limit updated by Mandar
                $recordToGenerate=$highestRow1-1;
                $checkStatus = CoreHelper::checkMaxCertificateLimit($recordToGenerate);
                if(!$checkStatus['status']){
                  return response()->json($checkStatus);
                }
                 $excelData=array('rowData1'=>$rowData1,'auth_site_id'=>$auth_site_id,'dropdown_template_id'=>$dropdown_template_id);
                $response = $this->dispatch(new ValidateExcelBestiuJob($excelData));
                //print_r($response);
                $responseData =$response->getData();
               
                if($responseData->success){
                    $old_rows=$responseData->old_rows;
                    $new_rows=$responseData->new_rows;
                }else{
                   return $response;
                }
            }
            
            if (file_exists($fullpath)) {
                unlink($fullpath);
            }
            //For custom Loader
			$randstr=CoreHelper::genRandomStr(5);
            $jsonArr=array();
            $jsonArr['token'] = $randstr.'_'.time();
            $jsonArr['status'] ='200';
            $jsonArr['message'] ='Pdf generation started...';
            $jsonArr['recordsToGenerate'] =$recordToGenerate;
            $jsonArr['generatedCertificates'] =0;
            $jsonArr['pendingCertificates'] =$recordToGenerate;
            $jsonArr['timePerCertificate'] =0;
            $jsonArr['isGenerationCompleted'] =0;
            $jsonArr['totalSecondsForGeneration'] =0;
            $loaderData=CoreHelper::createLoaderJson($jsonArr,1);            
        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows,'loaderFile'=>$loaderData['fileName'],'loader_token'=>$loaderData['loader_token']]);

        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }


    }

    public function uploadfile(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        //For custom loader
        $loader_token=$request['loader_token'];        
        $template_id = 100;
        $dropdown_template_id = $request['template_id'];
        /* 1=Basic */
        $previewPdf = array($request['previewPdf'],$request['previewWithoutBg']);

        //check file is uploaded or not
        if($request->hasFile('field_file')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'/backend/canvas/dummy_images/'.$template_id;
            $fullpath = $target_path.'/'.$excelfile;

            if(!is_dir($target_path)){
                
                            mkdir($target_path, 0777);
                        }

            if($request['field_file']->move($target_path,$excelfile)){
                //get excel file data
                
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                $auth_site_id=Auth::guard('admin')->user()->site_id;

                $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    if($systemConfig['sandboxing'] == 1){
                        $sandbox_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                        //if directory not exist make directory
                        if(!is_dir($sandbox_directory)){
                
                            mkdir($sandbox_directory, 0777);
                        }

                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                        
                    }else{
                        
                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                    }
                }
                else{

                    $sandbox_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                    //if directory not exist make directory
                    if(!is_dir($sandbox_directory)){
                        mkdir($sandbox_directory, 0777);
                    }

                    if($systemConfig['sandboxing'] == 1){
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile);                     
                    }else{
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile);
                        
                    }
                }
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel1 = $objReader->load($fullpath);
                $sheet1 = $objPHPExcel1->getSheet(0);
                $highestColumn1 = $sheet1->getHighestColumn();
                $highestRow1 = $sheet1->getHighestDataRow();
				if($dropdown_template_id==1 || $dropdown_template_id==2 || $dropdown_template_id==3){
					$rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
					unset($rowData1[0]);
				}
				if($dropdown_template_id==4){
					$worksheet = $objPHPExcel1->getSheet(0);
					$highestRow = $worksheet->getHighestRow(); 
					$highestColumn = $worksheet->getHighestColumn(); 
					$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); 					
					$course=array(); 
					$cols_name=array(); 
					for ($row = 1; $row <= 1; $row++) {
						for ($col = 1; $col <= $highestColumnIndex; ++$col) {
							$value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
							if($value){ 
								$cols_name[]=trim($value);
								if (str_contains($value, 'COURSE CODE')) {
									$course[]=trim($value);
								}							
							}
						}                   
					} 
					
					$total_cols = count($cols_name);
					$course_counts = count($course);
					//$highestColumnSet=($course_counts+1)*5;
					$last_col=$total_cols;
					$records=array();
					$r=1;
					for ($row = 2; $row <= $highestRow; $row++) {
						$value=array();
						$c=1;
						for ($col = 1; $col <= $highestColumnIndex; ++$col) {
							$value[$c] = trim($worksheet->getCellByColumnAndRow($col, $row)->getValue());
							$c++;
						}
						$records[$r]=array_values($value);
						$r++;	
					}
					$rowData1=array_values($records); 
					$note_key=$last_col-1;
					$total_credit_points_key=$last_col-2;
					$total_credit_hours_key=$last_col-3;					
					$cgpa_key=$last_col-4;
					$sgpa_key=$last_col-5;
					$subj_col = 5;
					$subj_start = 7;        
					$subj_end = $subj_col*$course_counts;
				}
            }                                   
        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        } 
        
        //store ghost image 
        //$tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();
		if($dropdown_template_id==4){
			$pdfData=array('studentDataOrg'=>$rowData1, 'note_key'=>$note_key, 'total_credit_hours_key'=>$total_credit_hours_key, 'total_credit_points_key'=>$total_credit_points_key, 'cgpa_key'=>$cgpa_key, 'sgpa_key'=>$sgpa_key, 'subj_col'=>$subj_col, 'subj_start'=>$subj_start, 'subj_end'=>$subj_end, 'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token); //For Custom Loader
		}else{
			$pdfData=array('studentDataOrg'=>$rowData1, 'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token); //For Custom Loader
        }
        if($dropdown_template_id==1){
            $link = $this->dispatch(new PdfGenerateBestiuJob($pdfData));
        }
        elseif($dropdown_template_id==2){
            $link = $this->dispatch(new PdfGenerateBestiuJob2($pdfData));
        }
        elseif($dropdown_template_id==3){
            $link = $this->dispatch(new PdfGenerateBestiuJob3($pdfData));
        }
        elseif($dropdown_template_id==4){
            $link = $this->dispatch(new PdfGenerateBestiuJob4($pdfData));
        }     
        elseif($dropdown_template_id==5){
            //$link = $this->dispatch(new PdfGenerateBestiuJob5($pdfData));
        }      
        return response()->json(['success'=>true,'message'=>'Certificates generated successfully.','link'=>$link]);
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

    public function CreateMessage($tmpDir, $name = "",$font_size,$print_color)
    {
        if($name == "")
            return;
        $name = strtoupper($name);
        // Create character image
        if($font_size == 15 || $font_size == "15"){


            $AlphaPosArray = array(
                "A" => array(0, 825),
                "B" => array(825, 840),
                "C" => array(1665, 824),
                "D" => array(2489, 856),
                "E" => array(3345, 872),
                "F" => array(4217, 760),
                "G" => array(4977, 848),
                "H" => array(5825, 896),
                "I" => array(6721, 728),
                "J" => array(7449, 864),
                "K" => array(8313, 840),
                "L" => array(9153, 817),
                "M" => array(9970, 920),
                "N" => array(10890, 728),
                "O" => array(11618, 944),
                "P" => array(12562, 736),
                "Q" => array(13298, 920),
                "R" => array(14218, 840),
                "S" => array(15058, 824),
                "T" => array(15882, 816),
                "U" => array(16698, 800),
                "V" => array(17498, 841),
                "W" => array(18339, 864),
                "X" => array(19203, 800),
                "Y" => array(20003, 824),
                "Z" => array(20827, 876)
            );

            $filename = public_path()."/backend/canvas/ghost_images/F15_H14_W504.png";

            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((14 * $currentX)/ $size[1]);

        }else if($font_size == 12){

            $AlphaPosArray = array(
                "A" => array(0, 849),
                "B" => array(849, 864),
                "C" => array(1713, 840),
                "D" => array(2553, 792),
                "E" => array(3345, 872),
                "F" => array(4217, 776),
                "G" => array(4993, 832),
                "H" => array(5825, 880),
                "I" => array(6705, 744),
                "J" => array(7449, 804),
                "K" => array(8273, 928),
                "L" => array(9201, 776),
                "M" => array(9977, 920),
                "N" => array(10897, 744),
                "O" => array(11641, 864),
                "P" => array(12505, 808),
                "Q" => array(13313, 804),
                "R" => array(14117, 904),
                "S" => array(15021, 832),
                "T" => array(15853, 816),
                "U" => array(16669, 824),
                "V" => array(17493, 800),
                "W" => array(18293, 909),
                "X" => array(19202, 800),
                "Y" => array(20002, 840),
                "Z" => array(20842, 792)
            
            );
                
                $filename = public_path()."/backend/canvas/ghost_images/F12_H8_W288.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((8 * $currentX)/ $size[1]);

        }else if($font_size == "10" || $font_size == 10){
            $AlphaPosArray = array(
                "A" => array(0, 700),
                "B" => array(700, 757),
                "C" => array(1457, 704),
                "D" => array(2161, 712),
                "E" => array(2873, 672),
                "F" => array(3545, 664),
                "G" => array(4209, 752),
                "H" => array(4961, 744),
                "I" => array(5705, 616),
                "J" => array(6321, 736),
                "K" => array(7057, 784),
                "L" => array(7841, 673),
                "M" => array(8514, 752),
                "N" => array(9266, 640),
                "O" => array(9906, 760),
                "P" => array(10666, 664),
                "Q" => array(11330, 736),
                "R" => array(12066, 712),
                "S" => array(12778, 664),
                "T" => array(13442, 723),
                "U" => array(14165, 696),
                "V" => array(14861, 696),
                "W" => array(15557, 745),
                "X" => array(16302, 680),
                "Y" => array(16982, 728),
                "Z" => array(17710, 680)
                
            );
            
            $filename = public_path()."/backend/canvas/ghost_images/F10_H5_W180.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }
            
            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
           
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((5 * $currentX)/ $size[1]);

        }else if($font_size == 11){

            $AlphaPosArray = array(
                "A" => array(0, 833),
                "B" => array(833, 872),
                "C" => array(1705, 800),
                "D" => array(2505, 888),
                "E" => array(3393, 856),
                "F" => array(4249, 760),
                "G" => array(5009, 856),
                "H" => array(5865, 896),
                "I" => array(6761, 744),
                "J" => array(7505, 832),
                "K" => array(8337, 887),
                "L" => array(9224, 760),
                "M" => array(9984, 920),
                "N" => array(10904, 789),
                "O" => array(11693, 896),
                "P" => array(12589, 776),
                "Q" => array(13365, 904),
                "R" => array(14269, 784),
                "S" => array(15053, 872),
                "T" => array(15925, 776),
                "U" => array(16701, 832),
                "V" => array(17533, 824),
                "W" => array(18357, 872),
                "X" => array(19229, 806),
                "Y" => array(20035, 832),
                "Z" => array(20867, 848)
            
            );
                
                $filename = public_path()."/backend/canvas/ghost_images/F11_H7_W250.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            

            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((7 * $currentX)/ $size[1]);

        }else if($font_size == "13" || $font_size == 13){

            $AlphaPosArray = array(
                "A" => array(0, 865),
                "B" => array(865, 792),
                "C" => array(1657, 856),
                "D" => array(2513, 888),
                "E" => array(3401, 768),
                "F" => array(4169, 864),
                "G" => array(5033, 824),
                "H" => array(5857, 896),
                "I" => array(6753, 784),
                "J" => array(7537, 808),
                "K" => array(8345, 877),
                "L" => array(9222, 664),
                "M" => array(9886, 976),
                "N" => array(10862, 832),
                "O" => array(11694, 856),
                "P" => array(12550, 776),
                "Q" => array(13326, 896),
                "R" => array(14222, 816),
                "S" => array(15038, 784),
                "T" => array(15822, 816),
                "U" => array(16638, 840),
                "V" => array(17478, 794),
                "W" => array(18272, 920),
                "X" => array(19192, 808),
                "Y" => array(20000, 880),
                "Z" => array(20880, 800)
            
            );

                
            $filename = public_path()."/backend/canvas/ghost_images/F13_H10_W360.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((10 * $currentX)/ $size[1]);

        }else if($font_size == "14" || $font_size == 14){

            $AlphaPosArray = array(
                "A" => array(0, 833),
                "B" => array(833, 872),
                "C" => array(1705, 856),
                "D" => array(2561, 832),
                "E" => array(3393, 832),
                "F" => array(4225, 736),
                "G" => array(4961, 892),
                "H" => array(5853, 940),
                "I" => array(6793, 736),
                "J" => array(7529, 792),
                "K" => array(8321, 848),
                "L" => array(9169, 746),
                "M" => array(9915, 1024),
                "N" => array(10939, 744),
                "O" => array(11683, 864),
                "P" => array(12547, 792),
                "Q" => array(13339, 848),
                "R" => array(14187, 872),
                "S" => array(15059, 808),
                "T" => array(15867, 824),
                "U" => array(16691, 872),
                "V" => array(17563, 736),
                "W" => array(18299, 897),
                "X" => array(19196, 808),
                "Y" => array(20004, 880),
                "Z" => array(80884, 808)
            
            );
                
                $filename = public_path()."/backend/canvas/ghost_images/F14_H12_W432.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((12 * $currentX)/ $size[1]);

        }else{
            $AlphaPosArray = array(
                "A" => array(0, 944),
                "B" => array(943, 944),
                "C" => array(1980, 944),
                "D" => array(2923, 944),
                "E" => array(3897, 944),
                "F" => array(4840, 753),
                "G" => array(5657, 943),
                "H" => array(6694, 881),
                "I" => array(7668, 504),
                "J" => array(8265, 692),
                "K" => array(9020, 881),
                "L" => array(9899, 944),
                "M" => array(10842, 944),
                "N" => array(11974, 724),
                "O" => array(12916, 850),
                "P" => array(13859, 850),
                "Q" => array(14802, 880),
                "R" => array(15776, 944),
                "S" => array(16719, 880),
                "T" => array(17599, 880),
                "U" => array(18479, 880),
                "V" => array(19485, 880),
                "W" => array(20396, 1038),
                "X" => array(21465, 944),
                "Y" => array(22407, 880),
                "Z" => array(23287, 880)
            );  

            $filename = public_path()."/backend/canvas/ghost_images/ALPHA_GHOST.png";
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);

            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $bgImage = imagecreatefrompng($filename);
            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
                imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
            $im = imagecrop($bgImage, $rect);
            
            imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((10 * $currentX)/ $size[1]);
        }
    }

    function GetStringPositions($strings,$pdf)
    {
        $len = count($strings);
        $w = array();
        $sum = 0;
        foreach ($strings as $key => $str) {
            $width = $pdf->GetStringWidth($str[0], $str[1], $str[2], $str[3], false);
            $w[] = $width;
            $sum += intval($width);
            
        }
        
        $ret = array();
        $ret[0] = (205 - $sum)/2;
        for($i = 1; $i < $len; $i++)
        {
            $ret[$i] = $ret[$i - 1] + $w[$i - 1] ;
            
        }
        
        return $ret;
    }

    function sanitizeQrString($content){
         $find = array('â€œ', 'â€™', 'â€¦', 'â€”', 'â€“', 'â€˜', 'Ã©', 'Â', 'â€¢', 'Ëœ', 'â€'); // en dash
         $replace = array('“', '’', '…', '—', '–', '‘', 'é', '', '•', '˜', '”');
        return $content = str_replace($find, $replace, $content);
    }
}
