<?php
// Author : Rushik Joshi
// Date : 21/12/2019
// use for generate pdf of id cards and update status of id cards
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\IdCardStatus;
use App\models\TemplateMaster;
use App\models\BackgroundTemplateMaster;
use App\models\FieldMaster;
use App\models\FontMaster;
use App\models\SystemConfig;
use App\models\Config;
use App\models\StudentTable;
use App\models\PrintingDetail;
use App\models\SbStudentTable;
use App\models\SbPrintingDetail;
use DB;
use Mail;
use TCPDF;
use QrCode;
use Auth;
use File;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

use App\Helpers\CoreHelper;
class IdCardStatusController extends Controller
{
    // listing of id card status with their status
    public function index(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
    	
        

    	if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and (id_card_status.template_name like \"%{$search}%\""
                . " or excel_sheet like \"%{$search}%\""
                 . " or request_number like \"%{$search}%\""
                 . " or id_card_status.rows like \"%{$search}%\""
                 . " or id_card_status.status like \"%{$search}%\""
                 . " or updated_on like \"%{$search}%\""
                 . " or admin_table.username like \"%{$search}%\""
                . ") ";
            }

            $status=$request->get('status');
            
            if($status==1 || $status == '1')
            {
                $status=1;
                $where_str.= " and (id_card_status.status != 'Acknowledged')";
            }
            else if($status==0 || $status=='0')
            {
                $status=0;
                $where_str.=" and (id_card_status.status= 'Acknowledged')";
            } 
            
            $auth_site_id=Auth::guard('admin')->user()->site_id;                               
            //for serial number
            DB::statement(DB::raw('set @rownum=0'));
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'request_number','id_card_status.template_name','excel_sheet','rows','id_card_status.status','admin_table.username','updated_on','created_on','template_master.id'];
            

            $id_card_status_count = IdCardStatus::select($columns)
                ->leftjoin('template_master','template_master.template_name','id_card_status.template_name')
                ->leftjoin('admin_table','admin_table.id','id_card_status.uploaded_by')
             ->whereRaw($where_str, $where_params)
             ->count();

            $id_card_status_list = IdCardStatus::select($columns)
                ->leftjoin('template_master','template_master.template_name','id_card_status.template_name')
                ->leftjoin('admin_table','admin_table.id','id_card_status.uploaded_by')
             ->whereRaw($where_str, $where_params);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $id_card_status_list = $id_card_status_list->take($request->input('iDisplayLength'))
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
                    $id_card_status_list = $id_card_status_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $id_card_status_list = $id_card_status_list->get();

            $response['iTotalDisplayRecords'] = $id_card_status_count;
            $response['iTotalRecords'] = $id_card_status_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $id_card_status_list->toArray();
           
            return $response;
        }
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $file_aws_local = $get_file_aws_local_flag['file_aws_local'];

        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
        $config = $systemConfig['sandboxing'];
    	return view('admin.idcardStatus.index',compact('file_aws_local','config'));
    }
    public function revokeRequest(Request $request){

        $request_number = $request['req_number'];
        $updated_on = date('Y-m-d H:i:s');

        $IdCardStatus = IdCardStatus::select('rows','template_name')
                        ->where('request_number',$request_number)
                        ->first();
        
        $rows = $IdCardStatus['rows'];
        $template_name = $IdCardStatus['template_name'];
        $mail_data = [
            'req_no'=>$request_number,
            'updated_on'=>$updated_on,
            'template_name'=>$template_name,
            'rows'=>$rows
        ];
        $user_email = ['dtp@scube.net.in','ankit@scube.net.in','asdawson@tatapower.com'];
        $cc_email = ['dev12@scube.net.in'];
        $mail_subject = "Test - TPSDI ".$request_number." Revoked request for ".$rows." ".$template_name.".";
        $mail_view = 'mail.revokeMail';

        Mail::send($mail_view, ['mail_data'=>$mail_data], function ($message) use ($user_email,$mail_subject,$cc_email) {
            $message->to($user_email);
            $message->cc($cc_email);
            $message->subject($mail_subject);
            $message->from('info@seqrdoc.com');
        });
        $res = IdCardStatus::where('request_number',$request_number)->delete();
        
        if($res){

            $message = Array('type' => 'success', 'message' => $request_number.' revoked successfully');
            echo json_encode($message);
            exit;
        }
    }
    public function updateStatusToAcknowledge(Request $request){

        $request_number = $request['req_number'];
        
        $changeStatus = IdCardStatus::where('request_number',$request_number)->update(['status'=>'Acknowledged']);

        if($changeStatus){

            $message = Array('type' => 'success', 'message' => $request_number.' Acknowledged successfully');
            echo json_encode($message);
            exit;
        }
    }
    public function updateStatusToComplete(Request $request){


        $request_number = $request['req_number'];
        $updated_on = date('Y-m-d H:i:s');

        $IdCardStatus = IdCardStatus::select('rows','template_name')
                        ->where('request_number',$request_number)
                        ->first();

        $rows = $IdCardStatus['rows'];
        $template_name = $IdCardStatus['template_name'];
        $mail_data = [

            'req_no'=>$request_number,
            'updated_on'=>$updated_on,
            'template_name'=>$template_name,
            'rows'=>$rows
        ];
        $user_email = ['dtp@scube.net.in','ankit@scube.net.in','asdawson@tatapower.com'];
        $cc_email = ['dev12@scube.net.in'];
        
        $mail_subject = "Test - TPSDI ".$request_number." Dispatch of ".$rows." ".$template_name." is initiated.";
        $mail_view = 'mail.updateId';

        Mail::send($mail_view, ['mail_data'=>$mail_data], function ($message) use ($user_email,$mail_subject,$cc_email) {
            $message->to($user_email);
            $message->cc($cc_email);
            $message->subject($mail_subject);
            $message->from('info@seqrdoc.com');
        });
        
        $changeStatus = IdCardStatus::where('request_number',$request_number)->update(['status'=>'Complete']);

        $message = Array('type' => 'success', 'message' => 'Status updated successfully');
        echo json_encode($message);
        exit;
    }
    public function processPdf(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $systemConfig = SystemConfig::first();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $request_number = $request['req_number'];
        $IdCardStatus  = IdCardStatus::where('request_number',$request_number)
                        ->first();
        //$IdCardStatus['template_name']="Training participation 1 sided";
        $template_name = $IdCardStatus['template_name'];
        $excelfile = $IdCardStatus['excel_sheet'];
        $highestRow = $IdCardStatus['rows'] + 1;
        $status = $IdCardStatus['status'];

      

        $templateMaster = TemplateMaster::select('id','unique_serial_no','template_name','bg_template_id','width','background_template_status')->where('template_name',
            $template_name)->first();


        $template_id = $templateMaster['id'];
        $unique_serial_no = $templateMaster['unique_serial_no'];
        $template_name = $templateMaster['template_name'];
        $background_template_id = $templateMaster['bg_template_id'];
        $template_width = $templateMaster['width'];
        $template_height = $templateMaster['height'];
        $backgound_template_status = $templateMaster['background_template_status'];

        if($background_template_id != 0) {

            $backgroundMaster = BackgroundTemplateMaster::select('width','height')
                            ->where('id',$background_template_id)
                            ->first();

            $template_width = $backgroundMaster['width'];
            $template_height = $backgroundMaster['height'];
        }


        $FID = [];
        $FID['template_id']  = $template_id;
        $FID['bg_template_id']  = $background_template_id;
        $FID['template_width']  = $template_width;
        $FID['template_height'] = $template_height;
        $FID['template_name']  =  $template_name;
        $FID['background_template_status']  =  $backgound_template_status;
        $FID['printing_type'] = 'pdfGenerate';

       

        $fields_master = FieldMaster::where('template_id',$template_id)
                        ->orderBy('field_position','asc')
                        ->get();
                        
        $fields =  collect($fields_master);
       // print_r($fields);
        $check_mapped = array();
        foreach ($fields as $key => $value) {
            
            $FID['mapped_name'][] = $value['mapped_name'];
            $FID['mapped_excel_col'][] = '';
            $FID['data'][] = '';
            $FID['security_type'][] = $value['security_type'];
            $FID['field_position'][] = $value['field_position'];
            $FID['text_justification'][] = $value['text_justification'];
            $FID['x_pos'][] = $value['x_pos'];
            $FID['y_pos'][] = $value['y_pos'];
            $FID['width'][] = $value['width'];
            $FID['height'][] = $value['height'];
            $FID['font_style'][] = $value['font_style'];
            $FID['font_id'][] = $value['font_id'];
            $FID['font_size'][] = $value['font_size'];
            $FID['font_color'][] = $value['font_color'];
            $FID['font_color_extra'][] = $value['font_color_extra'];
            
            // created by Rushik 
            // start get data from db and store in array 
            $FID['sample_image'][] = $value['sample_image'];
            $FID['angle'][] = $value['angle'];
            $FID['sample_text'][] = $value['sample_text'];
            $FID['line_gap'][] = $value['line_gap'];
            $FID['length'][] = $value['length'];
            $FID['uv_percentage'][] = $value['uv_percentage'];
            $FID['print_type'] = 'pdf';
            $FID['is_repeat'][] = $value['is_repeat'];
            $FID['field_sample_text_width'][] = $value['field_sample_text_width'];
            $FID['field_sample_text_vertical_width'][] = $value['field_sample_text_vertical_width'];
            $FID['field_sample_text_horizontal_width'][] = $value['field_sample_text_horizontal_width'];
            // end get data from db and store in array
            $FID['is_mapped'][] = $value['is_mapped'];
            $FID['infinite_height'][] = $value['infinite_height'];
            $FID['include_image'][] = $value['include_image'];
            $FID['grey_scale'][] = $value['grey_scale'];
        }


         if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){

            if($get_file_aws_local_flag->file_aws_local == '1'){
                $target_path = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile;
                $copy_path =public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile;
                $s3_file = \Storage::disk('s3')->get($target_path);
                $s3 = \Storage::disk('public_new');
                $s3->put($target_path,$s3_file);
                $target_path = $copy_path;
            }
            else{
                $target_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile;
            }
        }
        else{
            if($get_file_aws_local_flag->file_aws_local == '1'){
                $target_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile;
                $copy_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile;
                $s3_file = \Storage::disk('s3')->get($target_path);
                $s3 = \Storage::disk('public_new');
                $s3->put($target_path,$s3_file);
                $target_path = $copy_path;
            }
            else{
                 $target_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile;
            }
        }
       // echo $excelfile;
       
        $extension = pathinfo($excelfile, PATHINFO_EXTENSION);

        $inputType = 'Xls';
        if($extension == 'xlsx' || $extension == 'XLSX'){

         $inputType = 'Xlsx';   
        }

        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputType);
        $objPHPExcel = $objReader->load($target_path);
        $sheet = $objPHPExcel->getSheet(0);

        $fonts_array = array('' => '');


        foreach ($FID['font_id'] as $key => $font) {
                

            if(($font != '' && $font != 'null' && !empty($font)) || ($font == '0')){
            
                if($font != '0')
                {
                    $fontMaster = FontMaster::select('font_name','font_filename','font_filename_N','font_filename_B','font_filename_I','font_filename_BI')
                    ->where('id',$font)
                    ->first();
                }
                else{
                    $fontMaster = FontMaster::select('font_name','font_filename','font_filename_N','font_filename_B','font_filename_I','font_filename_BI')
                    ->first();
                }   

                if($FID['font_style'][$key] == ''){
                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
                            $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_N'];

                            $filename = $subdomain[0].'/backend/canvas/fonts/'.$fontMaster['font_filename_N'];

                            $font_name[$key] = $fontMaster['font_filename'];
                            if(!\Storage::disk('s3')->has($filename))
                            {
                                if (!file_exists($font_filename)) {
                                

                                    $message = Array('type' => 'error', 'message' => $font['font_filename_N'].' font not found');
                                    echo json_encode($message);
                                    exit;
                                }
                            }
                        }
                        else{
                            $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/'.$fontMaster['font_filename_N'];
                            $font_name[$key] = $fontMaster['font_filename'];

                            if (!file_exists($font_filename)) {
                                

                                $message = Array('type' => 'error', 'message' => $font['font_filename_N'].' font not found');
                                echo json_encode($message);
                                exit;
                            }
                        }
                }else if($FID['font_style'][$key] == 'B'){
                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
                        $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/'.$fontMaster['font_filename_B'];
                    }
                    else{
                        $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/'.$fontMaster['font_filename_B'];
                    }
                    $exp = explode('.', $font['font_filename_B']);
                    $font_name[$key] = $exp[0];
                }else if($FID['font_style'][$key] == 'I'){
                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
                        $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_I'];
                    }
                    else{
                        $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_I'];  
                    }   
                }else if($FID['font_style'][$key] == 'BI'){
                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
                        $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_BI'];
                    }
                    else{
                        $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_BI'];    
                    }  
                }
                if($get_file_aws_local_flag->file_aws_local == '1'){ 
                    if(!\Storage::disk('s3')->has($filename))
                    {
                        // if other styles are not present then load normal file
                        $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_N'];
                        $filename = $subdomain[0].'/backend/canvas/fonts/'.$get_font_data['font_filename_N'];
                       
                        
                        if(!\Storage::disk('s3')->has($filename))
                        {
                            $message = Array('type' => 'error', 'message' => $font['font_filename_N'].' font not found');
                            echo json_encode($message);
                            exit;
                        }
                    }
                }
                else{

                    if(!file_exists($font_filename)){

                        $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $fontMaster['font_filename_N'];

                        if (!file_exists($font_filename)) {
                            

                            $message = Array('type' => 'error', 'message' => $font['font_filename_N'].' font not found');
                            echo json_encode($message);
                            exit;
                        }
                    }
                }
                $fonts_array[$key] = \TCPDF_FONTS::addTTFfont($font_filename, 'TrueTypeUnicode', '', false);
            }
        }
        
        
        $printer_name = $systemConfig['printer_name'];
        $timezone = $systemConfig['timezone'];

        $style2D = array(
            'border' => false,
            'vpadding' => 0,
            'hpadding' => 0,
            'fgcolor' => array(0,0,0),
            'bgcolor' => false, //array(255,255,255)
            'module_width' => 1, // width of a single module in points
            'module_height' => 1 // height of a single module in points
        );
        $style1Da = array(
            'position' => '',
            'align' => 'C',
            'stretch' => false,
            'fitwidth' => true,
            'cellfitalign' => '',
            'border' => false,
            'hpadding' => 'auto',
            'vpadding' => 'auto',
            'fgcolor' => array(0,0,0),
            'bgcolor' => false, //array(255,255,255),
            'text' => true,
            'font' => 'helvetica',
            'fontsize' => 8,
            'stretchtext' => 3
        );
        $style1D = array(
            'position' => '',
            'align' => 'C',
            'stretch' => false,
            'fitwidth' => true,
            'cellfitalign' => '',
            'border' => false,
            'hpadding' => 'auto',
            'vpadding' => 'auto',
            'fgcolor' => array(0,0,0),
            'bgcolor' => false, //array(255,255,255),
            'text' => false,
            'font' => 'helvetica',
            'fontsize' => 8,
            'stretchtext' => 4
        );

        $ghostImgArr = array();

        $highestColumn = $sheet->getHighestColumn();
        

        $formula = [];
        foreach ($sheet->getCellCollection() as $cellId) {
            foreach ($cellId as $key1 => $value1) {
                $checkFormula = $sheet->getCell($cellId)->isFormula();
                if($checkFormula == 1){
                    $formula[] = $cellId;       
                }   
            }  
        };

        if(!empty($formula)){
            
            $message = array('type'=> 'error', 'message' => 'Please remove formula from column','cell'=>$formula);
            echo json_encode($message);
            exit;
        }

        if(isset($FID['bg_template_id']) && $FID['bg_template_id'] != '') {

            if($FID['bg_template_id'] == 0) {
                $bg_template_img_generate = '';
                $bg_template_width_generate = $FID['template_width'];
                $bg_template_height_generate = $FID['template_height'];
            } else {
                $get_bg_template_data =  BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();
                if($get_file_aws_local_flag->file_aws_local == '1'){ 
                    $bg_template_img_generate = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/'. $get_bg_template_data['image_path'];
                }
                else{
                    $bg_template_img_generate = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/'. $get_bg_template_data['image_path'];
                }
                
                $bg_template_width_generate = $get_bg_template_data['width'];
                $bg_template_height_generate = $get_bg_template_data['height'];
            }
        } else {
            $bg_template_img_generate = '';
            $bg_template_width_generate = 210;
            $bg_template_height_generate = 297;
        }
        $tmp_path = public_path().'/backend/images/ghosttemp';
        
        $tmpDir = app('App\Http\Controllers\admin\TemplateMasterController')->createTemp($tmp_path);

        $log_serial_no = 1;
        
        if($template_id == 7){

           //$sheet->setCellValue('L1','SeQR code');
            $sheet->setCellValue('M1','SeQR code');
        }else{
            //$sheet->setCellValue('J1','SeQR code');
             $sheet->setCellValue('K1','SeQR code');
        }
        $enrollImage = [];

        for ($excel_row=2; $excel_row <= $highestRow; $excel_row++) { 
            
            $rowData = $sheet->rangeToArray('A'.$excel_row.':'.$highestColumn.$excel_row,NULL,TRUE,FALSE);

            $pdf = new TCPDF('P', 'mm', array($bg_template_width_generate, $bg_template_height_generate), true, 'UTF-8', false);

            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('TCPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');

            // remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetCreator('SetCreator');

            $pdf->AddPage();

            if(isset($FID['bg_template_id']) && $FID['bg_template_id'] != ''){
                $pdf->Image($bg_template_img_generate, 0, 0, $bg_template_width_generate, $bg_template_height_generate, "JPG", '', 'R', true);
            }
            $serial_no = $rowData[0][2];
            if($get_file_aws_local_flag->file_aws_local == '1'){
                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                    $file_pointer_jpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

                    $file_pointer_png =\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
                }
                else{
                    $file_pointer_jpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

                    $file_pointer_png = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';
                }
            }
            else{
                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                    $file_pointer_jpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

                    $file_pointer_png =\Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
                }
                else{
                    $file_pointer_jpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

                    $file_pointer_png = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';
                }
            }
          

            $count = count($FID['mapped_name']);

            for ($extra_fields=0; $extra_fields < $count; $extra_fields++) { 
                

                if(isset($FID['security_type'][0]) || isset($FID['security_type'][1])){

                    array_push($FID['security_type'], $FID['security_type'][0]);
                    array_push($FID['security_type'], $FID['security_type'][1]);
                    unset($FID['security_type'][0]);
                    unset($FID['security_type'][1]);
                    
                }
                $security_type = $FID['security_type'][$extra_fields+2];
                
                if(isset($FID['x_pos'][0]) || isset($FID['x_pos'][1])){

                    array_push($FID['x_pos'], $FID['x_pos'][0]);
                    array_push($FID['x_pos'], $FID['x_pos'][1]);
                    unset($FID['x_pos'][0]);
                    unset($FID['x_pos'][1]);
                    
                }
                $x = $FID['x_pos'][$extra_fields + 2];

                if(isset($FID['y_pos'][0]) || isset($FID['y_pos'][1])){

                    array_push($FID['y_pos'], $FID['y_pos'][0]);
                    array_push($FID['y_pos'], $FID['y_pos'][1]);
                    unset($FID['y_pos'][0]);
                    unset($FID['y_pos'][1]);
                    
                }
                $y = $FID['y_pos'][$extra_fields + 2];


                $print_serial_no = $this->nextPrintSerial();

                if(isset($FID['field_position'][0]) || isset($FID['field_position'][1])){

                    array_push($FID['field_position'], $FID['field_position'][0]);
                    array_push($FID['field_position'], $FID['field_position'][1]);
                    unset($FID['field_position'][0]);
                    unset($FID['field_position'][1]);
                    
                }
                //print_r($FID['field_position']);
                $field_position = $FID['field_position'][$extra_fields + 2];

                
                if(isset($FID['font_color'][0]) || isset($FID['font_color'][1])){

                    array_push($FID['font_color'], $FID['font_color'][0]);
                    array_push($FID['font_color'], $FID['font_color'][1]);
                    unset($FID['font_color'][0]);
                    unset($FID['font_color'][1]);
                    
                }                
                $font_color_hex = $FID['font_color'][$extra_fields + 2];

                
                if($font_color_hex != ''){

                    
                    if($font_color_hex == "0"){

                        $r = 0;
                        $g = 0;
                        $b = 0;

                    }else{

                        list($r,$g,$b)  = array($font_color_hex[0].$font_color_hex[1],
                                        $font_color_hex[2].$font_color_hex[3],
                                        $font_color_hex[4].$font_color_hex[5],
                                );
                        $r = hexdec($r);
                        $g = hexdec($g);
                        $b = hexdec($b);    
                    }
                };
                

                if(isset($fonts_array[$extra_fields + 2])){

                    $font = $fonts_array[$extra_fields + 2];
                }

                if(isset($FID['font_size'][0]) || isset($FID['font_size'][1])){

                    array_push($FID['font_size'], $FID['font_size'][0]);
                    array_push($FID['font_size'], $FID['font_size'][1]);
                    unset($FID['font_size'][0]);
                    unset($FID['font_size'][1]);
                    
                }                
                $font_size = $FID['font_size'][$extra_fields + 2];


                if(isset($FID['font_style'][0]) || isset($FID['font_style'][1])){

                    array_push($FID['font_style'], $FID['font_style'][0]);
                    array_push($FID['font_style'], $FID['font_style'][1]);
                    unset($FID['font_style'][0]);
                    unset($FID['font_style'][1]);
                    
                }                
                $font_style = $FID['font_style'][$extra_fields + 2];



                if(isset($FID['width'][0]) || isset($FID['width'][1])){

                    array_push($FID['width'], $FID['width'][0]);
                    array_push($FID['width'], $FID['width'][1]);
                    unset($FID['width'][0]);
                    unset($FID['width'][1]);
                    
                }                
                $width = $FID['width'][$extra_fields + 2];


                if(isset($FID['height'][0]) || isset($FID['height'][1])){

                    array_push($FID['height'], $FID['height'][0]);
                    array_push($FID['height'], $FID['height'][1]);
                    unset($FID['height'][0]);
                    unset($FID['height'][1]);
                    
                }                
                $height = $FID['height'][$extra_fields + 2];



                $str = '';
                if(isset($rowData[0][$extra_fields]))
                    $str = $rowData[0][$extra_fields];
                

                if(isset($FID['text_justification'][0]) || isset($FID['text_justification'][1])){

                    array_push($FID['text_justification'], $FID['text_justification'][0]);
                    array_push($FID['text_justification'], $FID['text_justification'][1]);
                    unset($FID['text_justification'][0]);
                    unset($FID['text_justification'][1]);
                    
                }                
                $text_align = $FID['text_justification'][$extra_fields + 2];



                if($field_position == 3){
                    $str = $rowData[0][1];
                }else if($field_position == 4){
                    
                    $str = $rowData[0][2];
                }else if($field_position == 5){
                    
                    $str = $rowData[0][3];
                }else if($field_position == 6){
                    
                    $str = $rowData[0][4];
                }else if($field_position == 7){
                    $str = $rowData[0][5];
                }else if($field_position == 8){
                    
                    $str = $rowData[0][6];
                }else if($field_position == 9){
                    
                    $str = $rowData[0][7];
                }else if($field_position == 10){
                    $str = $rowData[0][8];

                }else if($field_position == 12){
                    $str = $rowData[0][11];

                }

                //print_r($rowData[0]);
                /*echo '<br>';
                echo $str.'_____'.$field_position;
                echo '<br>';*/
                switch ($security_type) {
                    case 'QR Code':
                       /* echo "----";
                        print_r($rowData[0]);
                        echo "----";
                        echo $field_position;
                        echo "----";
*/
                        $dt = date("_ymdHis");
                        /*$excl_column_row = $sheet->getCellByColumnAndRow(0,$excel_row);
                        echo $str = $excl_column_row->getValue();*/
                        $str=$rowData[0][0]; //updated by mandar
                        /* echo "----";
                       print_r($excel_row);

                        echo "----";
                        echo $str;
                        echo "----";
                        echo '<br>';*/
                         $codeContents = strtoupper(md5($str.$dt));

                        if(!empty($str)){

                            if($template_id == 7){
                                //echo "a";
                                //$sheet->setCellValue('L'.$excel_row,$codeContents);
                                $sheet->setCellValue('M'.$excel_row,$codeContents);
                            }else{
                                //echo "b";
                               // $sheet->setCellValue('J'.$excel_row,$codeContents);
                                 $sheet->setCellValue('K'.$excel_row,$codeContents);
                            }
                        }
                        
                        $pngAbsoluteFilePath = "$tmpDir/$codeContents.png";

                        QrCode::format('png')->size(200)->generate($codeContents,$pngAbsoluteFilePath);
                        $QR = imagecreatefrompng($pngAbsoluteFilePath);

                        $QR_width = imagesx($QR);
                        $QR_height = imagesy($QR);

                        $logo_qr_width = $QR_width/3;

                        imagepng($QR,$pngAbsoluteFilePath);
                        
                        $pdf->SetAlpha(1);
                        $pdf->Image($pngAbsoluteFilePath,$x,$y,19,19,"PNG",'','R',true);
                        break;

                    case 'ID Barcode':
                        break;
                    case 'Normal':

                        
                        if($FID['template_id'] == 6){

                            if($field_position == 8){
                                $cell = $sheet->getCellByColumnAndRow(8,$excel_row);
                               
                                $str = $cell->getValue();
                                if (is_numeric($str) && \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($str)){

                                    $val = $cell->getValue();
                                    $xls_date = $val;
         
                                    $unix_date = ($xls_date - 25569) * 86400;
                                     
                                    $xls_date = 25569 + ($unix_date / 86400);
                                    $unix_date = ($xls_date - 25569) * 86400;
                                    $str =  date("d-m-Y", $unix_date);
                                }
                            }

                           if($field_position == 10){
                                $cell = $sheet->getCellByColumnAndRow(10,$excel_row);
                               
                                $str = $cell->getValue();
                            }
                        }else if($FID['template_id'] == 1||$FID['template_id'] == 2||$FID['template_id'] == 3||$FID['template_id'] == 4||$FID['template_id'] == 5){

                            if($field_position == 10){  

                                $cell = $sheet->getCellByColumnAndRow(9,$excel_row);

                                $str = $cell->getValue();
                                if (is_numeric($str) && \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($str)){

                                    $val = $cell->getValue();
                                    $xls_date = $val;
         
                                    $unix_date = ($xls_date - 25569) * 86400;
                                     
                                    $xls_date = 25569 + ($unix_date / 86400);
                                    $unix_date = ($xls_date - 25569) * 86400;
                                    $str =  date("d-m-Y", $unix_date);
                                }
                            }

                             if($field_position == 12){
                                $cell = $sheet->getCellByColumnAndRow(10,$excel_row);
                               
                                $str = $cell->getValue();
                            }
                        }else{

                            if($field_position == 10){  

                                $cell = $sheet->getCellByColumnAndRow(9,$excel_row);

                                $str = $cell->getValue();
                                if (is_numeric($str) && \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($str)){

                                    $val = $cell->getValue();
                                    $xls_date = $val;
         
                                    $unix_date = ($xls_date - 25569) * 86400;
                                     
                                    $xls_date = 25569 + ($unix_date / 86400);
                                    $unix_date = ($xls_date - 25569) * 86400;
                                    $str =  date("d-m-Y", $unix_date);
                                }
                            }


                        }
                       
                        
                        $pdf->SetAlpha(1);
                        $pdf->SetTextColor($r,$g,$b);
                        $pdf->SetFont($font,$font_style,$font_size,'',false);
                        $pdf->SetXY($x,$y);
                        $pdf->Cell($width,$height,$str,0,false,$text_align);
                        break;
                    
                    case 'Dynamic Image':

                       
                        $pdf->SetAlpha(1);
                        $excel_column_row = $sheet->getCellByColumnAndRow(2,$excel_row);
                        $enrollValue = $excel_column_row->getValue();

                        $serial_no = trim($serial_no);
                        
                        if($get_file_aws_local_flag->file_aws_local == '1'){
                            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                                $image_jpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

                                $image_png =\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
                            }
                            else{
                                $image_jpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

                                $image_png = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';
                            }
                        }
                        else{
                            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                                $image_jpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

                                $image_png =\Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
                            }
                            else{
                                $image_jpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

                                $image_png = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';
                            }
                        }


                        

                        $exists = $this->check_file_exist($image_jpg);
                        
                        if($exists){
                            $pdf->image($image_jpg,$x,$y,$width / 3,$height / 3,"","",'L',true,3600);
                        } else {


                            $exists = $this->check_file_exist($image_png);
                            if($exists){
                               $pdf->image($image_png,$x,$y,$width / 3,$height / 3,"","",'L',true,3600);
                            } 
                        }

                        break;
                    default:
                        # code...
                        break;
                }
            }

           // exit; 

            $serial_no = $rowData[0][0];//Overwrite enrolment serial no to unique no
            $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $excelfile);
            $admin_id = \Auth::guard('admin')->user()->toArray();
            $template_name  = $FID['template_name'];
            $certName = str_replace("/", "_", $serial_no) .".pdf";
            $myPath = public_path().'/backend/temp_pdf_file';
            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');
            $student_table_id = $this->addCertificate($serial_no, $certName, $dt,$FID['template_id'],$admin_id);
            
  
            
            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            

            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $file_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id;
            }
            else{
                $file_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id;
            }
            $fp = fopen($file_path.'/'.$withoutExt.".txt","a");
            fwrite($fp,$content);
            fwrite($fp,$date);

            $print_datetime = date("Y-m-d H:i:s");
            $print_count = $this->getPrintCount($serial_no);
            $printer_name = /*'HP 1020';*/$printer_name;
            
            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_name,$admin_id,$student_table_id);
            $log_serial_no++;

            $excel_column_row = $sheet->getCellByColumnAndRow(3, $excel_row);
            $enroll_value = $excel_column_row->getValue();

            if($get_file_aws_local_flag->file_aws_local == '1'){
                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                    $imageNameJpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$enroll_value.'.jpg';

                    $imageNamePng =\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$enroll_value.'.png';
                }
                else{
                    $imageNameJpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.jpg';

                    $imageNamePng = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.png';
                }
            }
            else{
                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                    $imageNameJpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$enroll_value.'.jpg';

                    $imageNamePng =\Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$enroll_value.'.png';
                }
                else{
                    $imageNameJpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.jpg';

                    $imageNamePng = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.png';
                }
            }

            //echo $imageNamePng;
            $imageName = $enroll_value.'.jpg';
            
            /*if(file_exists($imageNamePng)){

                $imageName = $enroll_value.'.png';
            }*/


            if($this->check_file_exist($imageNamePng)){

                  $imageName = $enroll_value.'.png';  
            }
            array_push($enrollImage, $imageName);



        }

       // if($subdomain[0]=='test'){
            CoreHelper::rrmdir($tmpDir);
        //}
        
        //print_r($enrollImage);
        //exit;
            if($subdomain[0]=="demo"){
                /*$allDataInSheet = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
                $totalRecords = sizeof($allDataInSheet);
                $wrsheet = $objPHPExcel->getSheet(0);

                $format = 'dd/mm/yyyy';
                for ($i=1; $i <=$totalRecords ; $i++) { 
                     $wrsheet->getStyleByColumnAndRow(8, $i)->getNumberFormat()->setFormatCode($format);
                }*/

                $format = 'dd/mm/yyyy';
               // $objPHPExcel->getActiveSheet()->getColumnDimension('I')
                    //    ->setAutoSize(true);
                $objPHPExcel->getActiveSheet()->getNumberFormat()->setFormatCode($format);
            }
        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objPHPExcel, "Xlsx");
        $pathinfo = pathinfo($excelfile);

        $excel_filename = $pathinfo['filename'];
        $excel_extension = $pathinfo['extension'];

        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            $save_path = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id;
        }
        else{
            $save_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id;
        }

        $objWriter->save(public_path().$save_path.'/'.$template_name.'_'.$excel_filename.'_processed.'.$excel_extension);

        if($get_file_aws_local_flag->file_aws_local == '1'){
            \Storage::disk('s3')->put($save_path.'/'.$excel_filename.'_processed.'.$excel_extension, file_get_contents(public_path().$save_path.'/'.$excel_filename.'_processed.'.$excel_extension), 'public');
        }

        $excel_request_no = explode('/', $request_number);
        $zip_file_name = $excel_request_no[0].'_'.$excel_request_no[1].'_'.$excel_request_no[2].'.zip';
    
        
                
        $zip = new \ZipArchive;
        
        if ($zip->open(public_path().$save_path.'/'.$zip_file_name,\ZipArchive::CREATE) === TRUE) {
            
            $zip->addFile(public_path().$save_path.'/'.$template_name.'_'.$excel_filename.'_processed.'.$excel_extension,$template_name.'_'.$excel_filename.'_processed.'.$excel_extension);
            
            foreach ($enrollImage as $key => $value) {
                $zip->addfile(public_path().$save_path.'/'.$value,$value);
            }
            $zip->close();
        }
        if($get_file_aws_local_flag->file_aws_local == '1'){
            \Storage::disk('s3')->put($save_path.'/'.$zip_file_name, file_get_contents(public_path().$save_path.'/'.$zip_file_name), 'public');
            \Storage::disk('s3')->put($save_path.'/'.$withoutExt.".txt", file_get_contents($file_path.'/'.$withoutExt.".txt"), 'public');
        }
        $created_on = date('Y-m-d H:i:s');
        $highestRecord = $highestRow - 1;
       
       $changeStatus = IdCardStatus::where('request_number',$request_number)->update(['status'=>'Inprogress']);
        $mail_data = [

            'req_no'=>$request_number,
            'updated_on'=>$created_on,
            'template_name'=>$template_name,
            'quantity'=>$highestRecord
        ];
        
        $user_email = ['dtp@scube.net.in'];
        $cc_email = ['dev12@scube.net.in'];
        $mail_subject = "TPSDI ".$request_number." Request for ".$highestRecord." ".$template_name." is ready.";
        $mail_view = 'mail.processPdf';

        Mail::send($mail_view, ['mail_data'=>$mail_data], function ($message) use ($user_email,$mail_subject,$cc_email) {
            $message->to($user_email);
            $message->cc($cc_email);
            $message->subject($mail_subject);
            $message->from('info@seqrdoc.com');
        });

        if($get_file_aws_local_flag->file_aws_local == '1'){
            unlink(public_path().$save_path.'/'.$excel_filename.'_processed.'.$excel_extension);
            unlink(public_path().$save_path.'/'.$zip_file_name);
            unset($fp);
            unlink(public_path().$save_path.'/'.$excel_filename.".txt");
            unlink($target_path);
            \File::deleteDirectory(public_path().$save_path);
        }
        

        $message = Array('type' => 'success', 'message' => 'Status updated successfully');
        echo json_encode($message);
        exit;
        

    }

     public function checkImage(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $request_number = $request['req_number'];
        $IdCardStatus  = IdCardStatus::where('request_number',$request_number)
                        ->first();

        $excel = $IdCardStatus['excel_sheet'];
        $template_name = $IdCardStatus['template_name'];
        $template_id = TemplateMaster::where('template_name',
            $template_name)->value('id');
        $filename = $newflname =public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excel;
        $field_data = FieldMaster::where('template_id',$template_id)->get()->toArray();
        $FID = [];
        foreach ($field_data as $frow) {
            $FID['mapped_name'][] = $frow['mapped_name'];
        }
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id;
        }
        else{
            $path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id;
        }
        // dd($template_name);
        if($get_file_aws_local_flag->file_aws_local == '1'){
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $aws_path = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id;
            }
            else{
                $aws_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id;
            }
        }


        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filename);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($filename);
                $sheet = $spreadsheet->getSheet(0);
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();
                
                $rowData = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE);
        if(file_exists(public_path().'/'.$subdomain[0].'/test.txt')){
                                unlink(public_path().'/'.$subdomain[0].'/test.txt');
            }
        $file_not_exists = [];
        for($excel_row = 2; $excel_row <= $highestRow; $excel_row++)
        {
            $rowData1 = $sheet->rangeToArray('A'. $excel_row . ':' . $highestColumn . $excel_row, NULL, TRUE, FALSE);
            
            $count =  count($FID['mapped_name']);
            $serial_no = $rowData1[0][2];
            
            if($get_file_aws_local_flag->file_aws_local == '1'){
                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                    $file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

                    $file_location_png ='/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
                }
                else{
                    $file_location_jpg = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

                    $file_location_png = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';
                }
            }
            else{
                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                    $file_location_jpg = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.jpg';

                    $file_location_png = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$serial_no.'.png';
                }
                else{
                    /*$file_location_jpg = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.jpg';

                    $file_location_png = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$serial_no.'.png';*/

                    $file_location_jpg= 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$serial_no.'.jpg';
                    $file_location_png = 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$serial_no.'.png';
                }
            }

            

            $target_path = $path.'/'.$newflname;

            // dd($file_location_png);
            if($get_file_aws_local_flag->file_aws_local == '1'){
                if (!Storage::disk('s3')->exists($file_location_png) || !Storage::disk('s3')->exists($file_location_jpg)) {
                    if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                        if (Storage::disk('s3')->exists('/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$newflname)) {
                            Storage::disk('s3')->delete($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$newflname);
                        }
                        return response()->json(['success'=>false,'message'=>'Please add images in folder of your template name','type'=>'toster']);
                    }
                    else{
                        if (Storage::disk('s3')->exists('/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$newflname)) {
                            Storage::disk('s3')->delete($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$newflname);
                        }
                        return response()->json(['success'=>false,'message'=>'Please add images in folder of your template name','type'=>'toster']);
                    }
                
                }
            }
            else{


                
                if(!$this->check_file_exist($file_location_jpg)){

                    if(!$this->check_file_exist($file_location_png)){

                        $file = public_path().'/'.$subdomain[0].'/test.txt';
                        
                        file::append($file,$serial_no.PHP_EOL);
                        if($serial_no != ''){
                          
                                array_push($file_not_exists, $serial_no);
                            }

                    }
                }

               /* echo $file_location_jpg;
                exit;*/
                /*echo $serial_no;
                exit;*/
               /* if(!file_exists($file_location_jpg)){
                    if(!file_exists($file_location_png)){
                
                        array_push($file_not_exists, $serial_no);
                    }
                }*/
                
            }
        }

        if(count($file_not_exists) > 0){
            $path = 'https://'.$subdomain[0].'.seqrdoc.com/';
            $msg = "<b>Click <a href='".$path.$subdomain[0].'/test.txt'."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";

            return response()->json(['success'=>false,'message'=>'Please add images in folder of your template name','type'=>'toster','msg'=>$msg]);
        }else{
            return response()->json(['success'=>true,'message'=>'All files exists','type'=>'toster']);
        }

    }
     
    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        
        $file2 =public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;

        if($subdomain[0]=='tpsdi'){
            $source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;//$file1
            $output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName;
            CoreHelper::compressPdfFile($source,$output);
        }else{
            copy($file1, $file2);
        }

        @unlink($file1);

        $sts = 1;
        $datetime  = date("Y-m-d H:i:s");
        $ses_id  = $admin_id["id"];
        $certName = str_replace("/", "_", $certName);

        $get_config_data = Config::select('configuration')->first();
     
        $c = explode(", ", $get_config_data['configuration']);
        $key = "";


        $tempDir = public_path().'/backend/qr';
        $key = strtoupper(md5($serial_no.$dt)); 
        $codeContents = $key;
        $fileName = $key.'.png'; 
        
        $urlRelativeFilePath = 'qr/'.$fileName; 
        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
        // Mark all previous records of same serial no to inactive if any
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            $resultu = SbStudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
            // Insert the new record

            $result = SbStudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }
        else{
            $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
            // Insert the new record

            $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }

        return $result['id'];
    }
    public function getPrintCount($serial_no)
    {
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        return $numCount + 1;
    }
    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$student_table_id)
    {
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];
        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            // Insert the new record
            $result = SbPrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }
        else{
            // Insert the new record
            $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1,'student_table_id'=>$student_table_id]);
        }
    }
    public function nextPrintSerial()
    {
        $current_year = 'PN/' . $this->getFinancialyear() . '/';
        // find max
        $maxNum = 0;

        $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 10), UNSIGNED)), 0) AS next_num "
            . "FROM printing_details WHERE SUBSTR(print_serial_no, 1, 9) = '$current_year'");
        
        //get next num
        $maxNum = $result[0]->next_num + 1;

        return $current_year . $maxNum;
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
    public function check_file_exist($url){
        $handle = @fopen($url, 'r');
        if(!$handle){
            return false;
        }else{
            return true;
        }
    }


    public function TestR(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $excelfile="BronzeCertification-SHE_TPSDI_Job_B146_20211217122041_processed.xlsx";
        $target_path=public_path().'/'.$subdomain[0].'/backend/'.$excelfile;
       // $target_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile;
            
       // echo $excelfile;
       
        $extension = pathinfo($excelfile, PATHINFO_EXTENSION);

        $inputType = 'Xls';
        if($extension == 'xlsx' || $extension == 'XLSX'){

         $inputType = 'Xlsx';   
        }



                

        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputType);
        $objPHPExcel = $objReader->load($target_path);
        $sheet = $objPHPExcel->getSheet(0);
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();
                
       
        $template_id=7;

        $file_not_exists = [];
        $enrollImage=[];
        $i=1;
        for($excel_row = 2; $excel_row <= $highestRow; $excel_row++)
        {


        /* $rowData = $sheet->rangeToArray('A'.$excel_row.':' . $highestColumn .$excel_row, NULL, TRUE, FALSE);
        
        print_r($rowData);
        exit;*/
        $excel_column_row = $sheet->getCellByColumnAndRow(3, $excel_row);
        
            $enroll_value = $excel_column_row->getValue();
          //  print_r($enroll_value);
        //exit;

            /*if($get_file_aws_local_flag->file_aws_local == '1'){
                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                    $imageNameJpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$enroll_value.'.jpg';

                    $imageNamePng =\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$enroll_value.'.png';
                }
                else{
                    $imageNameJpg = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.jpg';

                    $imageNamePng = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.png';
                }
            }
            else{
                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                    $imageNameJpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$enroll_value.'.jpg';

                    $imageNamePng =\Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$enroll_value.'.png';
                }
                else{
                    $imageNameJpg = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.jpg';

                    $imageNamePng = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.png';
                }
            }
*/

            $imageName = $enroll_value.'.jpg';


             /*$file_location_jpg = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.jpg';

                 $file_location_png = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.png';
                //exit;
                if(file_exists($file_location_jpg)){

                    array_push($enrollImage, $imageName);
                    
                }elseif(file_exists($file_location_png)){

                   $imageName = $enroll_value.'.png';
                array_push($enrollImage, $imageName);
                }else{
                     array_push($file_not_exists, $imageName);
                }*/
            $imageNameJpg = public_path().'/'.$subdomain[0].'/backend/R_21_414/'.$enroll_value.'.jpg';
            $imageNamePng = public_path().'/'.$subdomain[0].'/backend/R_21_414/'.$enroll_value.'.png';
             echo $imageNamePng = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.png';
          echo'<br>';   
           if(file_exists($imageNamePng)){

                $imageName = $enroll_value.'.png';
               
            }

             array_push($enrollImage, $imageName);
            //$imageNameJpg= 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/R_21_414/'.$enroll_value.'.jpg';
            //$imageNamePng = 'https://'.$subdomain[0].'.seqrdoc.com/'.$subdomain[0].'/backend/R_21_414/'.$enroll_value.'.png';
            /*if(file_exists($imageNameJpg)){
                array_push($enrollImage, $imageName);
                //echo "Jpg-".$imageName;
                //exit;
                 //echo $i."-".$imageName."-<img src='".$imageNameJpg."' height='100px' width='100px' /> <br>";
            }elseif(file_exists($imageNamePng)){

                $imageName = $enroll_value.'.png';
                array_push($enrollImage, $imageName);
                //echo "Png-".$imageName;
                //exit;
                 //echo $i."-".$imageName."-<img src='".$imageNamePng."' height='100px' width='100px' /> <br>";
            }else{

            */    //echo $imageName.'</br>';
                //exit;
                // echo $i."-".$imageName."-<img src='".$imageNameJpg."' height='100px' width='100px' /> <br>";
               // array_push($file_not_exists, $imageName);

                 /*$file_location_jpg = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.jpg';

                 $file_location_png = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$enroll_value.'.png';
                //exit;
                if(file_exists($file_location_jpg)){
                    
                }elseif(file_exists($file_location_png)){

                   
                }else{

                    //echo "Template7 - ".$imageName.'</br>';
                    //exit;
                    // echo $i."-".$imageName."-<img src='".$imageNameJpg."' height='100px' width='100px' /> <br>";
                    //array_push($file_not_exists, $imageName);


                }*/
            //}




            $i++;
            
        }


        /*$save_path = '/'.$subdomain[0].'/backend';
        $zip_file_name='test.zip';
        $zipPath= public_path().$save_path.'/'.$zip_file_name;
      // exit;
        $zip = new \ZipArchive;
        
        if ($zip->open($zipPath,\ZipArchive::CREATE) === TRUE) {
            
            $zip->addFile(public_path().$save_path.'/BronzeCertification-SHE_TPSDI_Job_B146_20211217122041_processed.xlsx',"BronzeCertification-SHE_TPSDI_Job_B146_20211217122041_processed.xlsx");
            
            foreach ($enrollImage as $key => $value) {

                $file_location = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$value;
                $zip->addfile($file_location,$value);
            }
            $zip->close();
        }*/

        print_r($file_not_exists);

    }
}
