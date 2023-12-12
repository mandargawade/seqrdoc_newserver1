<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\SbStudentTable;
use App\models\StudentTable;
use App\models\TemplateMaster;
use App\models\SbPrintingDetail;
use App\models\SbExceUploadHistory;
use App\models\SbScannedHistory;
use App\models\SbTransactions;
use App\models\SystemConfig;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;

use Auth;
use QrCode;
use DB;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

//Display a listing of the sb_student Detail.
class SandBoxingController extends Controller
{
    public function certificate(Request $request)
    {
    	if($request->ajax())
        {
            $data = $request->all();
            $where_str    = "1 = ?";
            $where_params = array(1); 


            if($request->has('sSearch'))
            {
                $search = $request->get('sSearch');
                $where_str .= " and ( certificate_filename like \"%{$search}%\""
                			. " or serial_no like \"%{$search}%\""
                           . ")";
            }

            $status=$request->get('status');

            if($status==1)
            {
                $status=1;
                    
                $where_str.= " and (sb_student_table.status = '$status')";
            }
            else if($status==0 && $status!=null)
            {
                $status=0;
                    
                $where_str.= " and (sb_student_table.status = '$status')";
                
            }  

            
            $auth_site_id=Auth::guard('admin')->user()->site_id;

                
            $certificate = SbStudentTable::select('sb_student_table.id','serial_no','certificate_filename','template_name','sb_student_table.status','sb_student_table.created_at')
                                ->leftjoin('template_master','template_master.id','sb_student_table.template_id')
                                ->where('sb_student_table.site_id',$auth_site_id)
                                ->whereRaw($where_str, $where_params);

            $certificate_count = SbStudentTable::select('id')
                                        ->leftjoin('template_master','template_master.id','sb_student_table.template_id')
                                        ->whereRaw($where_str, $where_params)
                                        ->where('sb_student_table.site_id',$auth_site_id)
                                        ->count();
            $columns = ['serial_no','certificate_filename','template_name','status','sb_student_table.created_at','id'];
        

            if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                $certificate = $certificate->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
            }
            
            if($request->has('iSortCol_0')){
                for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->get('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }

                    $certificate = $certificate->orderBy($column,$request->get('sSortDir_'.$i));
                }
            }

            $certificate = $certificate->get()->toArray();
            
            foreach ($certificate as $key => $value) {
            	if ($value['status']==1 || $value['status']=='1') {
            		$certificate[$key]['status'] = 'Active';
            	}
            	else
            	{
            		$certificate[$key]['status'] = 'Inactive';
            	}
            }

            $response['iTotalDisplayRecords'] = $certificate_count;
            $response['iTotalRecords'] = $certificate_count;

            $response['sEcho'] = intval($request->get('sEcho'));

            $response['aaData'] = $certificate;

            return $response;
        }
    	return view('admin.sandboxing.certificate');
    }
    public function generateQRCode(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        

         //check upload to aws/local (var(get_file_aws_local_flag) coming through provider)
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $data = $request->all();
        
        $studentData = SbStudentTable::select('key')->where('id',$data['id'])->get()->first();
    
        $key = $studentData['key'];
        $temp_path = public_path().'/backend/qr/'.$key.'.png';

        QrCode::format('png')
                ->size(200)
                ->generate($key,$temp_path);

        if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_qr_path = '/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';

            $aws_qr = \Storage::disk('s3')->put($aws_qr_path, file_get_contents($temp_path), 'public');
            $get_qr_image = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';
        }
        else{
            $local_server_qr_path = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';

            $aws_qr = \File::copy($temp_path,$local_server_qr_path);

            $get_qr_image = 'http://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/canvas/images/qr/'.$key.'.png';
        }

        @unlink($temp_path);

       
        $this->args['key'] = $key;
        $this->args['get_qr_image'] = $get_qr_image;
        
        return response(['success'=>true,'data'=>['key'=>$key,'path'=>$get_qr_image]]);
    }
    public function rePrint(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        
        $requestData = $request['data'];
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        foreach ($requestData as $key => $value) {
            
            $result = SbStudentTable::where('id',$value)->first();
            $eData[$key] = $result;
        }
        $pdf = PDFMerger::init();
        foreach ($eData as $key => $value) {
            if($get_file_aws_local_flag->file_aws_local == '1'){
                $s3Path = 'https://sequredoc.s3.amazonaws.com/'.$subdomain[0].'/backend/pdf_file/';
            }
            else{
                $s3Path = 'http://demo.seqrdoc.com/'.$subdomain[0].'/backend/pdf_file/';
            }
            $pdf_file_path = $s3Path.'sandbox/'.$value['certificate_filename'];

            $s3_file = \Storage::disk('s3')->get('/'.$subdomain[0].'/backend/pdf_file/sandbox/'.$value['certificate_filename']);
            
            
            $s3 = \Storage::disk('public_reprint');
            $s3->put("pdf_file/" . $value['certificate_filename'], $s3_file);



            $pdf->addPDF(public_path().'/backend/pdf_file/'.$value['certificate_filename'],[1]);
            
        }
        
        $pdf->merge();
        $pdf->save(public_path().'/backend/pdf_file/TEST2.pdf');
        if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_pdf = \Storage::disk('s3')->put('/'.$subdomain[0].'/backend/pdf_file/sandbox/TEST2.pdf', file_get_contents(public_path().'/backend/pdf_file/TEST2.pdf'), 'public');
            $filename = \Storage::disk('s3')->url('TEST2.pdf');

            $pdfPath = 'https://sequredoc.s3.amazonaws.com/'.$subdomain[0].'/backend/pdf_file/sandbox/TEST2.pdf';

        }
        else{
            $pdfPath = public_path().'/backend/pdf_file/TEST2.pdf';
        }


        

        return response()->json(['type'=>'success','message'=>'merged ','link'=>'Click <a href="'.$pdfPath.'" class="downloadSinglePdf" download>Here</a> to download file";','filePath'=>$pdfPath,'eData'=>$eData]);
    }

    public function unlinkData(Request $request){

        $data = $request['data'];
        $dataPrint = $request['dataPrint'];
        if (file_exists(public_path().'/backend/pdf_file/TEST2.pdf')) {
            
            @unlink(public_path().'/backend/pdf_file/TEST2.pdf');
        }
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


    
        $username=Auth::guard('admin')->user()->username;
        $printer_name = $systemConfig['printer_name'];
        $timezone = $systemConfig['timezone'];
        date_default_timezone_set($timezone);
        $print_datetime = date("Y-m-d H:i:s");
        $ses_id   = Auth::guard('admin')->user()->id;

        foreach ($dataPrint as $key => $value) {
            
            $printing_details=SbPrintingDetail::where(['sr_no'=>$value['serial_no'],'status'=>1])
                ->update(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'updated_at'=>$print_datetime,'updated_by'=>$ses_id,'reprint'=>1]);
            if (file_exists(public_path().'/backend/pdf_file/'.$value['certificate_filename'])) {
                
                @unlink(public_path().'/backend/pdf_file/'.$value['certificate_filename']);
            }
        }
        
        return response()->json(['type'=>'success','message'=>'file deleted']);

    }
    public function CertificateUpdate(Request $request){

        $data = $request->all();

        

        if ($data['status'] == "Active") {
            $data['status'] = '1';
            $status = '0';
        }
        else
        {
            $data['status'] = '0';
            $status = '1';
        }

        $status_update = SbStudentTable::where('id',$data['id'])
                                ->where('status',$data['status'])
                                ->update(['status'=>$status]);

        return response(['success'=>true]);
    }

    //printing details for sb_student certificate
    public function PrintingDetails(Request $request){
        

         if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
               
                $search     = $request->input('sSearch');
                $where_str .= " and (print_serial_no like \"%{$search}%\""
                ." or sr_no like \"%{$search}%\""
                ." or printer_name like \"%{$search}%\""
                ." or print_datetime like \"%{$search}%\""
                ." or username like \"%{$search}%\""
                .")";
            }  
           $status=$request->get('status');
           
            if($status==1)
            {
                $status=1;
                $where_str.= " and (sb_printing_details.status =$status)";
            }
            else if($status==0)
            {
                $status=0;
                $where_str.=" and (sb_printing_details.status= $status)";
            }  

            $auth_site_id=Auth::guard('admin')->user()->site_id;                                             
             //for serial number
            DB::statement(DB::raw('set @rownum=0')); 
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'print_serial_no','sr_no','print_count','printer_name','print_datetime','username','id','updated_at'];

            $font_master_count = SbPrintingDetail::select($columns)
               ->whereRaw($where_str, $where_params)
               ->where('publish',1)
               ->where('site_id',$auth_site_id)
               ->count();

            $fontMaster_list = SbPrintingDetail::select($columns)
                 ->where('publish',1)
                 ->where('site_id',$auth_site_id)
                 ->whereRaw($where_str, $where_params);

           
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $fontMaster_list = $fontMaster_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          
            
            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $fontMaster_list = $fontMaster_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $fontMaster_list = $fontMaster_list->get();
             
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list->toArray();
            
            return $response;
        }
        return view("admin.sandboxing.printingDetails");
    }

    //list of printing details
    public function getDetail(Request $request){

        if(!empty($request['print_id']))
        {
              $columns = ['print_serial_no','sr_no','print_count','printer_name','print_datetime','username','reprint','status','created_at'];
              $print_data=SbPrintingDetail::select($columns)
                           ->where('id',$request['print_id'])
                           ->get()
                           ->toArray();

              $print_data=head($print_data);
              return  $print_data;       
        }
    }

    //get template data for sb_student
    public function TemplateData(Request $request){
         
        $template_data_list = TemplateMaster::select('id','template_name','status')
                                            ->orderBy('updated_at','desc')
                                            ->get()
                                            ->toArray();


                        
        
        $activeData = [];
        $inActiveData = [];
        $updated_at = [];
        $gotData = [];
        foreach ($template_data_list as $key => $value) {
            
            $template_id = $value['id'];
            $last_updated = SbStudentTable::select('updated_at')->where('template_id',$template_id)->orderBy('updated_at','desc')->get()->toArray();
     
            $resultActive = SbStudentTable::where(['status'=>'1','template_id'=>$template_id])->get()->count();

            array_push($template_data_list[$key],$resultActive);
            $resultInActive = SbStudentTable::where(['status'=>'0','template_id'=>$template_id])->get()->count();
            array_push($template_data_list[$key], $resultInActive);
            if(isset($last_updated[0]['updated_at'])){

                array_push($template_data_list[$key], $last_updated[0]['updated_at']);
            }else{
                
                array_push($template_data_list[$key], '');
            }
        }
        
        $i = 0;
        $new_arr = [];
        $template_data = [];
        foreach ($template_data_list as $key => $value) {

            $active_scanned = 0;        
            $deActive_scanned = 0;
            $active_verifier_count = 0; 
            $deactive_verifier_count = 0;   

            $value = json_decode(json_encode($value),true);
            $template_name = $value['id'];
            // get scanned data based on template name and scan_result=1(active)
            $scanned_data = DB::select("SELECT DISTINCT `key`,`sb_scanned_history`.`scan_by` FROM sb_student_table LEFT JOIN sb_scanned_history ON `sb_student_table`.`key`= `sb_scanned_history`.`scanned_data` WHERE `sb_student_table`.`template_id`='$template_name' AND `sb_scanned_history`.`scan_result` = 1");
            $active_scanned_data = json_decode(json_encode($scanned_data),true);
            if(count($active_scanned_data) > 0){
                foreach ($active_scanned_data as $a_key => $a_value) {
                    $scan_by = $active_scanned_data[$a_key]['scan_by'];
                    $active_verifier_data =  DB::select("SELECT count(`id`) as c_id from user_table where username='$scan_by'");
                   
                    $active_ver_scanned_data = json_decode(json_encode($active_verifier_data),true);

                    if($active_ver_scanned_data[0]['c_id'] > 0){
                        $active_verifier_count+=1;
                    }
                }
                $active_scanned=count($active_scanned_data);
                    
            }
            // get scanned data based on template name and scan_result=0(deactive)

            $de_scanned_data = DB::select("SELECT DISTINCT `key`,`sb_scanned_history`.`scan_by` FROM sb_student_table LEFT JOIN sb_scanned_history ON `sb_student_table`.`key`= `sb_scanned_history`.`scanned_data` WHERE `sb_student_table`.`template_id`='$template_name' AND `sb_scanned_history`.`scan_result` = 0");
            $deActive_scanned_data =  json_decode(json_encode($de_scanned_data),true);

            if(count($deActive_scanned_data) > 0){
                foreach ($deActive_scanned_data as $d_key => $d_value) {
                    $de_scan_by = $deActive_scanned_data[$d_key]['scan_by'];
                    $deactive_verifier_data =  DB::select("SELECT count(`id`) as count_cid from user_table where username='$de_scan_by'");
                    
                    $deactive_ver_scanned_data = json_decode(json_encode($deactive_verifier_data),true);
                    if($deactive_ver_scanned_data[0]['count_cid'] > 0){
                        $deactive_verifier_count+=1;
                    }

                }
                $deActive_scanned=count($deActive_scanned_data);
            }
           
            if($value['status'] == 1 || $value['status'] == '1'){
                $value['status'] = 'Active';
            }else{
                
                $value['status'] = 'Inactive';
            }
            
            // put the value in array with key
            $template_data[$i]['id'] = $i+1;
            $template_data[$i]['template_name'] = $value['template_name'];
            $template_data[$i]['status'] = $value['status'];
            $template_data[$i]['active_count'] = $value[0];
            $template_data[$i]['deactive_count'] = $value[1];
            $template_data[$i]['updated_on'] = $value[2];
            $template_data[$i]['active'] = $active_scanned.'('.$active_verifier_count.')';
            $template_data[$i]['deactive'] = $deActive_scanned.'('.$deactive_verifier_count.')';
            ++$i;
        } 
    
        $array['data']= $template_data;
        return view('admin.sandboxing.templateData',compact('template_data'));
    }

    //list of printingdetails
    public function PrintingReport(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $file_aws_local = $get_file_aws_local_flag['file_aws_local'];

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $config = $systemConfig['sandboxing'];
        if($request->ajax())
        {
            $data = $request->all();
            
            $where_str = '1 = ?';
            $where_params = [1];
            

            if($request->has('sSearch'))
            {
                $search = $request->get('sSearch');
                $where_str .= " and ( sb_excelupload_history.template_name like \"%{$search}%\""
                            . " or sb_excelupload_history.user like \"%{$search}%\""
                            . " or sb_excelupload_history.excel_sheet_name like \"%{$search}%\""
                            . " or sb_excelupload_history.created_at like \"%{$search}%\""
                            . " or sb_excelupload_history.no_of_records like \"%{$search}%\""
                           . ")";
            }
 
            $auth_site_id=Auth::guard('admin')->user()->site_id;
      
            $printingReport = SbExceUploadHistory::select('sb_excelupload_history.id','sb_excelupload_history.template_name','sb_excelupload_history.excel_sheet_name','sb_excelupload_history.user','sb_excelupload_history.no_of_records','sb_excelupload_history.created_at','template_master.id as template_id')
                                ->leftjoin('template_master','template_master.template_name','sb_excelupload_history.template_name')
                                ->where('sb_excelupload_history.site_id','=',$auth_site_id)
                                ->whereRaw($where_str, $where_params);
                          

            $printingReport_count = SbExceUploadHistory::select('id')
                                        ->where('site_id','=',$auth_site_id)
                                        ->whereRaw($where_str, $where_params)
                                        ->count();
           
            $columns = ['id','template_name','excel_sheet_name','user','no_of_records','created_at'];

            if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                $printingReport = $printingReport->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
            }

            if($request->has('iSortCol_0')){
                for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->get('iSortCol_' . $i)];
                    if (false !== ($index = strpos($column, ' as '))) {
                        $column = substr($column, 0, $index);
                    }
                    $printingReport = $printingReport->orderBy($column,$request->get('sSortDir_'.$i));
                }
            }

            $printingReport = $printingReport->get();
            foreach ($printingReport as $key => $value) {
               $value['created_on'] =  date("d M Y h:i A", strtotime($value['created_at']));
            }

             $response['iTotalDisplayRecords'] = $printingReport_count;
             $response['iTotalRecords'] = $printingReport_count;

            $response['sEcho'] = intval($request->get('sEcho'));

            $response['aaData'] = $printingReport;
            
            return $response;
        }
        return view('admin.sandboxing.printingReport',compact('file_aws_local','config'));
    }

    //to get scanhistory of sb_student
    public function ScanHistory(Request $request){

        if($request->ajax()){
            
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
               
                $search     = $request->input('sSearch');
                $where_str .= " and (sb_scanned_history.scan_by like \"%{$search}%\""
                 . " or sb_scanned_history.scanned_data like \"%{$search}%\""
                 . " or sb_scanned_history.date_time like \"%{$search}%\""
                . ")";
            }
           
           $device_type=$request->get('device_type');
           
            if($device_type=="webapp")
            {
                $where_str.= " and (sb_scanned_history.device_type ='webapp')";
            }
            else if($device_type=="ios")
            {
                $where_str.=" and (sb_scanned_history.device_type= 'ios')";
            }
            else if($device_type=="android")
            {
                $where_str.=" and (sb_scanned_history.device_type= 'android')";
            } 

            $auth_site_id=Auth::guard('admin')->user()->site_id;                                                 
             //for serial number
            DB::statement(DB::raw('set @rownum=0')); 
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'sb_scanned_history.date_time','sb_scanned_history.device_type','sb_scanned_history.scanned_data','sb_scanned_history.scan_by','sb_scanned_history.scan_result','institute_table.institute_username','sb_scanned_history.updated_at'];
            
            $font_master_count = SbScannedHistory::select($columns)
            ->join('institute_table','sb_scanned_history.scan_by','institute_table.institute_username')
            ->whereRaw($where_str, $where_params)
            ->where('sb_scanned_history.site_id',$auth_site_id) 
            ->count();

            $fontMaster_list = SbScannedHistory::select($columns)
            ->join('institute_table','sb_scanned_history.scan_by','institute_table.institute_username')
            ->where('sb_scanned_history.site_id',$auth_site_id)
            ->whereRaw($where_str, $where_params);

           
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $fontMaster_list = $fontMaster_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $fontMaster_list = $fontMaster_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $fontMaster_list = $fontMaster_list->get();
            
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list->toArray();
            
            return $response;
        }
        return view('admin.sandboxing.scanHistory');
    }

    //list of transcation for sb_student
    public function PaymentTransaction(Request $request){

        if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( username like \"%{$search}%\""
                . " or sb_transactions.trans_id_gateway like \"%{$search}%\""
                . " or sb_transactions.trans_id_ref like \"%{$search}%\""
                . " or sb_transactions.payment_mode like \"%{$search}%\""
                . " or sb_transactions.amount like \"%{$search}%\""
                . " or sb_transactions.additional like \"%{$search}%\""
                . " or user_table.username like \"%{$search}%\""
                . " or sb_student_table.serial_no like \"%{$search}%\""
                . " or sb_student_table.serial_no like \"%{$search}%\""
                . " or sb_transactions.created_at like \"%{$search}%\""
                . ")";
            }  
           $status=$request->get('status');
            if($status==1)
            {
                $status='1';
                $where_str.= " and (sb_transactions.trans_status =$status)";
            }
            else if($status==0)
            {
                $status='0';
                $where_str.=" and (sb_transactions.trans_status= $status)";
            }                                               
             
            $auth_site_id=Auth::guard('admin')->user()->site_id; 
            //for serial number
            DB::statement(DB::raw('set @rownum=0')); 
             $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'sb_transactions.trans_id_ref','sb_transactions.trans_id_gateway','sb_transactions.payment_mode','sb_transactions.amount','sb_transactions.additional','user_table.username','sb_student_table.serial_no','sb_transactions.created_at','sb_transactions.updated_at','sb_transactions.trans_status'];

            $font_master_count = SbTransactions::select($columns)
            ->leftjoin('user_table','sb_transactions.user_id','user_table.id')
            ->leftjoin('sb_student_table','sb_transactions.student_key','sb_student_table.key')
            ->whereRaw($where_str, $where_params)
            ->where('sb_transactions.site_id',$auth_site_id)
            ->count();
  
            $fontMaster_list = SbTransactions::select($columns)
            ->leftjoin('user_table','sb_transactions.user_id','user_table.id')
            ->leftjoin('sb_student_table','sb_transactions.student_key','sb_student_table.key')
            ->where('sb_transactions.site_id',$auth_site_id)
            ->whereRaw($where_str, $where_params);
      
            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $fontMaster_list = $fontMaster_list->take($request->input('iDisplayLength'))
                ->skip($request->input('iDisplayStart'));
            }          

            if($request->input('iSortCol_0')){
                $sql_order='';
                for ( $i = 0; $i < $request->input('iSortingCols'); $i++ )
                {
                    $column = $columns[$request->input('iSortCol_' . $i)];
                    if(false !== ($index = strpos($column, ' as '))){
                        $column = substr($column, 0, $index);
                    }
                    $fontMaster_list = $fontMaster_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            } 
            $fontMaster_list = $fontMaster_list->get();
             
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list->toArray();
            
            return $response;
        }
        return view('admin.sandboxing.paymentTransaction');
    }

    //to get scanhistory data for sb_student
    public function ScanHistoryGetData(Request $request){
      
      if(!empty($request['key']))
      {
           $student_data=SbStudentTable::where('key',$request['key'])->get()->first();
           
           return $student_data ? $student_data : "null" ;
      }
    }
}
