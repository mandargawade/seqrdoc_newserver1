<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use Session,TCPDF,TCPDF_FONTS,Auth,DB;
use App\models\BackgroundTemplateMaster;
use App\models\SystemConfig;
use App\models\FieldMaster;
use App\models\FontMaster;
use QrCode;
use App\models\Config;
use App\models\PrintingDetail;
use App\models\StudentTable;
use App\models\SbStudentTable;
class PassingCertificateController extends Controller
{
    //load template
    public function uploadPage(){


        $templates = TemplateMaster::where('id','<',3)->pluck('template_name',"id");
        
    	return view('admin.passing-certificate.index',compact('templates'));
    }

    //validation of upload excel
    public function validateExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        
        $template_id=$request['template_id'];


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
                $rowData1=array_values($rowData1);
                
                $blobArr=array();
           

                $sandboxCheck = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                $old_rows = 0;
                $new_rows = 0;
            }

            
            if (file_exists($fullpath)) {
                unlink($fullpath);
            }
            
        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);

        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }


    }

    //upload excel for genrte certificate
    public function uploadfile(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        
        $template_id=$request['template_id'];
        $template_data = TemplateMaster::select('id','unique_serial_no','template_name','bg_template_id','width','height','background_template_status')->where('id',$template_id)->first();

        if($template_data['bg_template_id'] != 0){
            $bg_template_data = BackgroundTemplateMaster::select('width','height')->where('id',$template_data['bg_template_id'])->first();
        }
        $FID = array();
        $FID['template_id']  = $template_data['id'];
        $FID['bg_template_id']  = $template_data['bg_template_id'];
        $FID['template_width']  = $template_data['width'];
        $FID['template_height'] = $template_data['height'];
        $FID['template_name']  =  $template_data['template_name'];
        $FID['background_template_status']  =  $template_data['background_template_status'];
        $FID['printing_type'] = 'pdfGenerate';

        $field_data = FieldMaster::where('template_id',$template_id)->orderBy('field_position')->get()->toArray();
        foreach ($field_data as $field_key => $field_value) {
            // start code for static image hide from map
            if($field_value['security_type'] != 'Copy' && $field_value['security_type'] != 'ID Barcode' && $field_value['security_type'] != 'Static Image' && $field_value['security_type'] != 'Static Text' && $field_value['security_type'] != 'Anti-Copy' && $field_value['security_type'] != '1D Barcode' && $field_value['security_type'] != 'Static Microtext Border') {
                if($field_value['mapped_name'] == '') {
                    $message = 'Can\'t generate pdf from unmapped template!';
                    return response()->json(['success'=>false,'message'=>$message]);
                }
                if ($field_value['security_type'] == 'Static Image' || $field_value['security_type'] == 'Static Text' || $field_value['security_type'] == 'Anti-Copy' || $field_value['security_type'] == '1D Barcode' || $field_value['security_type'] == 'Static Microtext Border') {
                }
                else
                {
                    $check_mapped[] = $field_value['mapped_name'];
                }
            }


            $FID['id'][] = $field_value['id'];
            $FID['mapped_name'][] = $field_value['mapped_name'];
            $FID['mapped_excel_col'][] = '';
            $FID['data'][] = '';
            $FID['security_type'][] = $field_value['security_type'];
            $FID['field_position'][] = $field_value['field_position'];
            $FID['text_justification'][] = $field_value['text_justification'];
            $FID['x_pos'][] = $field_value['x_pos'];
            $FID['y_pos'][] = $field_value['y_pos'];
            $FID['width'][] = $field_value['width'];
            $FID['height'][] = $field_value['height'];
            $FID['font_style'][] = $field_value['font_style'];
            $FID['font_id'][] = $field_value['font_id'];
            $FID['font_size'][] = $field_value['font_size'];
            $FID['font_color'][] = $field_value['font_color'];
            $FID['font_color_extra'][] = $field_value['font_color_extra'];
            $FID['text_opacity'][] = $field_value['text_opicity'];
            $FID['visible'][] = $field_value['visible'];
            $FID['visible_varification'][] = $field_value['visible_varification'];
            
            // start get data from db and store in array 
            $FID['sample_image'][] = $field_value['sample_image'];
            $FID['angle'][] = $field_value['angle'];
            $FID['sample_text'][] = $field_value['sample_text'];
            $FID['line_gap'][] = $field_value['line_gap'];
            $FID['length'][] = $field_value['length'];
            $FID['uv_percentage'][] = $field_value['uv_percentage'];
            $FID['print_type'] = $request->print_type;
            $FID['is_repeat'][] = $field_value['is_repeat'];
            // end get data from db and store in array
            $FID['is_mapped'][] = $field_value['is_mapped'];
            $FID['infinite_height'][] = $field_value['infinite_height'];
            $FID['include_image'][] = $field_value['include_image'];
            $FID['grey_scale'][] = $field_value['grey_scale'];
            $FID['is_uv_image'][] = $field_value['is_uv_image'];
            $FID['is_transparent_image'][] = $field_value['is_transparent_image'];
            $FID['is_font_case'][] = $field_value['is_font_case'];
        }
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

                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                        
                    }else{
                        
                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$excelfile, file_get_contents($fullpath), 'public');
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
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_data['id'].'/'.$excelfile);
                        
                    }else{
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_data['id'].'/'.$excelfile);
                        
                    }
                }
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel1 = $objReader->load($fullpath);
                $sheet1 = $objPHPExcel1->getSheet(0);
                $highestColumn1 = $sheet1->getHighestColumn();
                $highestRow1 = $sheet1->getHighestDataRow();
                $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);

                $fields = array();
                $mapped_excel_col_unique_serial_no = '';
                foreach ($rowData1[0] as $key => $f) {
                    if($f != '') {
                        $fields[] = $f;
                        if($mapped_excel_col_unique_serial_no == '') {
                            if($template_data['unique_serial_no'] == $f)
                                $mapped_excel_col_unique_serial_no = $key;

                        }
                        //check mapped name with excel is in db or not
                        foreach ($FID['mapped_name'] as $i => $value) {
                            if($value == $f) {
                                $FID['mapped_excel_col'][$i] = $key;
                            }
                        }
                    }
                }
            }        
        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }     
        
        $rowData1=array_values($rowData1);
        $fonts_array = array();
        $font_name = '';
      
        foreach ($FID['font_id'] as $font_key => $font_value) {
            if(($font_value != '' && $font_value != 'null' && !empty($font_value)) || ($font_value == '0'))
            {
                $s3=\Storage::disk('s3');
                try {

                    //get font data from id inside FID array
                    if($font_value != '0')
                    {
                        $get_font_data = FontMaster::select('font_name','font_filename','font_filename_N','font_filename_B','font_filename_I','font_filename_BI')->where('id',$font_value)->first();
                    }
                    else{
                        $get_font_data = FontMaster::select('font_name','font_filename','font_filename_N','font_filename_B','font_filename_I','font_filename_BI')->first();
                    }
                    

                    if($FID['font_style'][$font_key] == '') {
                        if($get_file_aws_local_flag->file_aws_local == '1'){ 
                            $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_N'];

                            $filename = $subdomain[0].'/backend/canvas/fonts/'.$get_font_data['font_filename_N'];

                            $font_name[$font_key] = $get_font_data['font_filename'];
                            if(!Storage::disk('s3')->has($filename))
                            {
                                $tmp_name = public_path().'/backend/canvas/dummy_images/'.$FID['template_name'].'/'.$excelfile;
                                if (file_exists($tmp_name)) {
                                    unlink($tmp_name);
                                }

                                $message = 'font file ' . $font_filename . 'not found';
                                return response()->json(['success'=>false,'message'=>$message]);
                            }
                        }
                        else{
                            $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/'.$get_font_data['font_filename_N'];
                            $font_name[$font_key] = $get_font_data['font_filename'];
                            if(!file_exists($font_filename))
                            {
                                $tmp_name = public_path().'/backend/canvas/dummy_images/'.$FID['template_name'].'/'.$excelfile;
                                if (file_exists($tmp_name)) {
                                    unlink($tmp_name);
                                }

                                $message = 'font file ' . $font_filename . 'not found';
                                return response()->json(['success'=>false,'message'=>$message]);
                            }
                        }

                        

                    }
                    else if ($FID['font_style'][$font_key] == 'B'){
                        if($get_file_aws_local_flag->file_aws_local == '1'){ 
                            $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_B'];
                        }
                        else{
                            $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_B'];
                        }
                    }
                    else if($FID['font_style'][$font_key] == 'I'){
                        if($get_file_aws_local_flag->file_aws_local == '1'){ 
                            $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_I'];
                        }
                        else{
                            $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_I'];  
                        }        
                    }
                    else if($FID['font_style'][$font_key] == 'BI'){
                        if($get_file_aws_local_flag->file_aws_local == '1'){ 
                            $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_BI'];
                        }
                        else{
                            $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_BI'];    
                        }      
                    }
                    
                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
                        if(!Storage::disk('s3')->has($filename) || empty($name[1]))
                        {
                            // if other styles are not present then load normal file
                            $font_filename = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_N'];
                            $filename = $subdomain[0].'/backend/canvas/fonts/'.$get_font_data['font_filename_N'];
                            
                            $fonts_array[$font_key] = \TCPDF_FONTS::addTTFfont($font_filename, 'TrueTypeUnicode', '', false);
                            
                            if(!Storage::disk('s3')->has($filename))
                            {
                                if(isset($FID['template_name'])){
                                    $tmp_name = public_path().'/backend/canvas/dummy_images/'.$FID['template_name'].'/'.$excelfile;
                                    if (file_exists($tmp_name)) {
                                        unlink($tmp_name);
                                    }
                                }
                                $message = 'font file "' . $font_filename . '" not found';
                                return response()->json(['success'=>false,'message'=>$message]);
                            }
                        }
                    }
                    else{
                        if(!file_exists($font_filename))
                        {
                            // if other styles are not present then load normal file
                            $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_N'];
                            if(!file_exists($font_filename))
                            {
                                $tmp_name = public_path().'/backend/canvas/dummy_images/'.$FID['template_name'].'/'.$excelfile;
                                if (file_exists($tmp_name)) {
                                    unlink($tmp_name);
                                }
                                $message = 'font file "' . $font_filename . '" not found';
                                return response()->json(['success'=>false,'message'=>$message,'flag'=>0]);
                            }
                        }
                    }
                    $name = explode('fonts/',$font_filename);
                    if(!empty($name[1])){
                        $fonts_array[$font_key] = \TCPDF_FONTS::addTTFfont($font_filename, 'TrueTypeUnicode', '', false);
                    }
                    
                } catch (PDOExcelption $e) {

                    $tmp_name = public_path().'/backend/canvas/dummy_images/'.$FID['template_name'].'/'.$excelfile;
                    if (file_exists($tmp_name)) {
                        unlink($tmp_name);
                    }
                    $message = 'font file "' . $font_filename . '" not found';
                    return response()->json(['success'=>false,'message'=>$message,'flag'=>0]);       
                }
            }
        }
        //store ghost image
        //$tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();
        
        
        $link=$this->certificateGenerate($rowData1,$template_id,$previewPdf,$FID,$highestRow1,$checkUploadedFileOnAwsOrLocal,$fonts_array,$mapped_excel_col_unique_serial_no);
        return response()->json(['success'=>true,'message'=>'Certificates generated successfully.','link'=>$link]);
    }

    //genrate certificate
    public function certificateGenerate($rowData,$template_id,$previewPdf,$FID,$highestRow1,$checkUploadedFileOnAwsOrLocal,$fonts_array,$mapped_excel_col_unique_serial_no){

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];
        $admin_id = \Auth::guard('admin')->user()->toArray();
        $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp/temp');
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        
        $printer_name = $systemConfig['printer_name'];

       
        $pdfBig = new TCPDF('P', 'mm', array($FID['template_width'], $FID['template_height']), true, 'UTF-8', false);
        $pdfBig->SetCreator("TCPDF");
        $pdfBig->SetAuthor('TCPDF');
        $pdfBig->SetTitle('Certificate');
        $pdfBig->SetSubject('');

        // remove default header/footer
        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);

        $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewroman_N_N.ttf', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold_B_B.ttf', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromanbi_BI.ttf', 'TrueTypeUnicode', '', 96);
        $oldenglish = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\oldenglishfive_N.ttf', 'TrueTypeUnicode', '', 32);
        $log_serial_no = 1;
        for($excel_row = 2; $excel_row <= $highestRow1; $excel_row++)
        {
            
            
            $pdfBig->AddPage();
            $bg_template_img_generate = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/MIT_BG_BG.jpg' ;
            
            if($previewPdf==1){
                if($previewWithoutBg!=1){

                     $pdfBig->Image($bg_template_img_generate, 0, 0, $FID['template_width'], $FID['template_height'], "JPG", '', 'R', true);
                }
            }   
             $pdfBig->setPageMark();
           
            if ($FID['is_mapped'][0] == 'excel') {
                $serial_no = $rowData[0][$mapped_excel_col_unique_serial_no];
              
            }
            else
            {
                $serial_no = 1;
            }
            $count =  count($FID['mapped_name']);
            
            $pdf = new TCPDF('P', 'mm', array($FID['template_width'], $FID['template_height']), true, 'UTF-8', false);
            $pdf->SetCreator("TCPDF");
            $pdf->SetAuthor('TCPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');

            // remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);

            $pdf->AddPage();

             if($previewPdf!=1){
                $pdf->Image($bg_template_img_generate, 0, 0,  $FID['template_width'], $FID['template_height'], "JPG", '', 'R', true);
            }
             $pdf->setPageMark();
    
            for($extra_fields = 0; $extra_fields < $count; $extra_fields++)
            {
                
                if($FID['security_type'][$extra_fields] == "Static Microtext Border" || $FID['security_type'][$extra_fields] == "Static Text")
                {
                    $str = $FID['sample_text'][$extra_fields];
                }
                else{
                    //code for check map excel or database 
                    // get string from excel columns or database selected columns value
                    if ($FID['is_mapped'][0] == 'excel') {

                        if(isset($rowData[$excel_row - 1][$FID['mapped_excel_col'][$extra_fields]])){
                            $str = $rowData[$excel_row - 1][$FID['mapped_excel_col'][$extra_fields]];
                        }
                        else{
                            $str = '';
                        }
                    }else{
                        if(isset($rowData[$FID['mapped_name'][$extra_fields]]))
                            $str = $rowData[$FID['mapped_name'][$extra_fields]];
                        else
                            $str = 'Not mapped';
                    }
                }
                
                $bold = '';
            
                if($FID['font_style'][$extra_fields] != ''){
                    $bold = $FID['font_style'][$extra_fields];
                }

                $angle = $FID['angle'][$extra_fields];
                if(isset($FID['font_id'][$extra_fields]))
                {

                    $font = $FID['font_id'][$extra_fields];
                }
                if(isset($FID['sample_image'][$extra_fields])){
                    $sample_image = $FID['sample_image'][$extra_fields];
                }
                

                if(isset($FID['line_gap'][$extra_fields])){

                    $line_gap =  $FID['line_gap'][$extra_fields];
                }
                $style = $FID['font_style'][$extra_fields];

                if(isset($FID['length'][$extra_fields])){

                    $length =  $FID['length'][$extra_fields];
                }
                    
                $font_colorHex = $FID['font_color'][$extra_fields];
                
                if(isset($font_colorHex[1])){
                    list($r,$g,$b)  = array($font_colorHex[0].$font_colorHex[1],
                                        $font_colorHex[2].$font_colorHex[3],
                                        $font_colorHex[4].$font_colorHex[5],
                                );

                    $r = hexdec($r);
                    $g = hexdec($g);
                    $b = hexdec($b);
                }

                $security_type = $FID['security_type'][$extra_fields];
                
                $config= SystemConfig::select('print_color')->get();
                
                $print_color = '';
                if(!empty($config[0])){
                    $print_color = $config[0]['print_color'];
                }

                
                
                
                    //Generating new print no
                $print_serial_no = $this->nextPrintSerial();
               // $print_serial_no='test001';
                $text_align = $FID['text_justification'][$extra_fields];
                $width = $FID['width'][$extra_fields];

                if($text_align == 'C' && $width == 0)
                {
                    $text_align = 'L';
                    $w = $pdfBig->GetStringWidth($str, $font, '', $font_size, false);
                    $x = $page_width/2 - $w/2;
                } 
                else if($text_align == 'R') 
                {
                    $text_align = 'R';
                }
                $singleQuotePosition = strpos($str, "'");

                if ($singleQuotePosition === 0){
                
                        $str = str_replace("'", "", $str);          
         
                 
                }else{

                    $str = $str;
                    
                }

                $x = $FID['x_pos'][$extra_fields];
                $y = $FID['y_pos'][$extra_fields];
                $height = $FID['height'][$extra_fields];
                $font_size = $FID['font_size'][$extra_fields];
                if(empty($font_size)){
                    $font_size = 0;
                }
                if(isset($FID['font_color_extra'][$extra_fields])){

                    $font_color_extra = $FID['font_color_extra'][$extra_fields];
                }
    

              
                if($str !="(Enrollment No."&&$str!=")"){

                switch  ($security_type) {

                    case 'Normal':
            


                        if($FID['template_id'] == 1 && $extra_fields == 20 && $str != ""){
                            $str = "(".trim($str).")";
                            if(strlen($rowData[$excel_row-1][6])>45){
                                $y=$y-1.5;
                            }

                        }
                        if($FID['template_id'] == 2 && $extra_fields == 13){
                            $str = "(".trim($str).")";
                        }
                        if($FID['template_id'] == 2 && $extra_fields == 21){
                            $str = "Mother's Name: ".trim($str);
                        }
                        if($FID['template_id'] == 1 && $extra_fields == 11){
                            $str = "Mother's Name: ".trim($str);
                        }
                        if($FID['template_id'] == 1 && $extra_fields == 21){
                            $str = "The said degree under the ".trim($str);
                        }
                       
                        if($fonts_array[$extra_fields] == "timesnewromanbi_bi"){

                            $font = "times";
                            $bold = "BI";
                        }else if($fonts_array[$extra_fields] == "timesnewroman_n_n"){

                            $font = "times";
                            $bold = "";
                        }else if($fonts_array[$extra_fields] == "timesnewromanb_b_b"){

                            $font = "times";
                            $bold = "B";
                        }else if($fonts_array[$extra_fields] == "oldenglishfive_n"){

                            $font = $oldenglish;
                        }else{
                            $font = $timesNewRoman;
                        }

                        if( strpos( $str, "MITU" ) !== false) {
                        $pdfBig->SetTextColor($r,$g,$b);
                        $pdfBig->SetFont($font,$bold, $font_size, '', false);
                        $pdfBig->SetXY(10, $y);
                        $pdfBig->Cell(0, 0, "(Enrollment No. ".trim($str).")", 0, false, 'C');

                       

                        $pdf->SetTextColor($r,$g,$b);
                        $pdf->SetFont($font,$bold, $font_size, '', false);
                        $pdf->SetXY(10, $y);
                        $pdf->Cell(0, 0, "(Enrollment No. ".trim($str).")", 0, false, 'C');
                        }else{

                        if($font=="oldenglishfive_n"&&strlen($str)>44){
                           
                            $font_size=16;

                        }
                      
                        if($str=="Pothiwal Tejvinder Singh Kirpal Singh Surender Kaur"){
                            $font_size=$font_size-2;
                        }
                        $pdfBig->SetTextColor($r,$g,$b);
                        $pdfBig->SetFont($font,$bold, $font_size, '', false);
                        $pdfBig->SetXY($x, $y);
                        $pdfBig->Cell($width, $height, trim($str), 0, false, $text_align);

                       

                        $pdf->SetTextColor($r,$g,$b);
                        $pdf->SetFont($font,$bold, $font_size, '', false);
                        $pdf->SetXY($x, $y);
                        $pdf->Cell($width, $height, trim($str), 0, false, $text_align);


                        }

                    break;
                    case 'Micro line':

                        $pdf->SetFont("arial", "B", 1.5, '', false);
                        $pdf->SetTextColor(0, 0, 0);
                        $pdf->SetXY($x, $y);
                        $pdf->Cell($width, $height, $str, 0, false, $text_align);
                        
                        $pdfBig->SetFont("arial", "B", 1.5, '', false);
                        $pdfBig->SetTextColor(0, 0, 0);
                        $pdfBig->SetXY($x, $y);
                        $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                        
                    break;
                    case 'QR Code':
                           


                            $dt = date("_ymdHis");
                            $codeContentsData = "";
                            if($FID['template_id'] == 1){
                         
                                $codeContentsData .= $rowData[$excel_row - 1][$FID['mapped_excel_col'][7]];
                                $codeContentsData .= "\r\n".$rowData[$excel_row - 1][$FID['mapped_excel_col'][0]];
                                $codeContentsData .= "\r\n".$rowData[$excel_row - 1][$FID['mapped_excel_col'][19]];
                                if(!empty($rowData[$excel_row - 1][$FID['mapped_excel_col'][20]])){
                                    $codeContentsData .= "\r\n".$rowData[$excel_row - 1][$FID['mapped_excel_col'][20]];
                                }
                                
                                

                            }else if($FID['template_id'] == 2){

                                $codeContentsData .= $rowData[$excel_row - 1][$FID['mapped_excel_col'][7]];
                                $codeContentsData .= "\r\n".$rowData[$excel_row - 1][$FID['mapped_excel_col'][0]];
                                $codeContentsData .= "\r\n".$rowData[$excel_row - 1][$FID['mapped_excel_col'][12]];
                                $codeContentsData .= "\r\n".$rowData[$excel_row - 1][$FID['mapped_excel_col'][13]];
                            }
                            $codeContentsData .= "\r\n\r\n".strtoupper(md5($str . $dt));

                            $codeData = $codeContentsData;
                             
                            $codeContents = strtoupper(md5($str . $dt));
                            $pngAbsoluteFilePath = "$tmpDir/$codeContents.png";
                            
                                
                            if($FID['include_image'][$extra_fields] == 1){
                                $logopath = $FID['sample_image'][$extra_fields];
                                
                                
                            }else{
                                
                                
                                $logopath = public_path()."/backend/canvas/dummy_images/QR.png";
                            }
                            
                            QrCode::size(200)->format('png')->generate($codeData,$pngAbsoluteFilePath);
                            
                            $pdf->Image($pngAbsoluteFilePath, $x, $y, 23,  23, '', '', '', false, 600);
                          $pdfBig->Image($pngAbsoluteFilePath, $x, $y, 23,  23, '', '', '', false, 600);
                            
                    break;
                    
                    case 'Static Text':

                        if($fonts_array[$extra_fields] == "timesnewromanbi_bi"){
                        
                            $font = "times";
                            $bold = "BI";
                        }else if($fonts_array[$extra_fields] == "timesnewroman_n_n"){

                            $font = "times";
                            $bold = "";
                        }else if($fonts_array[$extra_fields] == "times-new-roman-bold_b_b.ttf"){
                            $font = "times";
                            $bold = "B";
                            $font = $timesNewRomanB;
                        }else{

                            $font = $timesNewRoman;
                        }

                        $pdf->SetAlpha(1);
                        $pdf->SetTextColor($r,$g,$b);
                        
                        $pdf->SetFont($font, $bold, $font_size, '', false);
                        $pdf->SetXY($x, $y);
                        $pdf->Cell($width, $height, $str, 0, false, $text_align);
                    
                        $pdfBig->SetAlpha(1);
                        $pdfBig->SetTextColor($r,$g,$b);
                        $pdfBig->SetFont($font, $bold, $font_size, '', false);
                        $pdfBig->SetXY($x, $y);
                        $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                          
                    break;

                    case 'Dynamic Image':
                        /* we can display image from 2 path if without save preview then check template name is avalable or not
                            if available then image display from template name folder otherwise custom image folder 
                        */
                        

                            $strWithExtension = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/'.$str;
                            if(file_exists($strWithExtension.'.jpg')){
                                $extension = ".jpg";
                            }else if(file_exists($strWithExtension.'.png')){

                                $extension = ".png";
                            }else if(file_exists($strWithExtension.'.jpeg')){

                                $extension = ".jpeg";
                            }else if(file_exists($strWithExtension.'.JPG')){

                                $extension = ".JPG";
                            }else if(file_exists($strWithExtension.'.PNG')){

                                $extension = ".PNG";
                            }else if(file_exists($strWithExtension.'.JPEG')){
                                $extension = ".JPEG";

                            }else{
                                $extension = "no";
                            }
                            if($extension != "no"){


                                    $upload_image = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/'.$str.$extension;

                                    $pdf->image($upload_image, $x, $y,23,30, "", '', 'L', true, 3600);
                                
                                    
                                        
                                    
                                    if($FID['visible'][$extra_fields] == 0){
                                    
                                        $pdfBig->image($upload_image, $x, $y,23,30, "", '', 'L', true, 3600);
                                    }
                            }
                        break;

                        default:
                        break;
                }

                $log_serial_no++;
            }
            }
            
            if($previewPdf!=1){
                $serial_no=$rowData[$excel_row-1][0];
                
                $template_name  = $FID['template_name'];
                $certName = str_replace("/", "_", $serial_no) .".pdf";
                $myPath = public_path().'/backend/temp_pdf_file';
                $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;
                $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');
                $student_table_id = $this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id);

                $username = $admin_id['username'];
                date_default_timezone_set('Asia/Kolkata');

                $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
                $date = date('Y-m-d H:i:s').PHP_EOL;
                $print_datetime = date("Y-m-d H:i:s");
                

                $print_count = $this->getPrintCount($serial_no);
                $printer_name = /*'HP 1020';*/$printer_name;
                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_name,$admin_id,$student_table_id);

            }
        }
        
        $file_name =  str_replace("/", "_",'MIT'.date("Ymdhms")).'.pdf';
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        //delete temp dir 26-04-2022 
        CoreHelper::rrmdir($tmpDir);
        
        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
        @unlink($filename);
         
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';

        $path = $protocol.'://mitadt.seqrdoc.com/';
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";

        return $msg;
    
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

    //store student info.
    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id)
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

        //Sore file on azure server
       

        $sts = '1';
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

        if($systemConfig['sandboxing'] == 1){
        $resultu = SbStudentTable::where('serial_no','T-'.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = SbStudentTable::create(['serial_no'=>'T-'.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }else{
        $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }
        return $result['id'];
    }

    //get print count
    public function getPrintCount($serial_no)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    }

    //store printdetails
    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$student_table_id)
    {
        
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>'T-'.$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }else{
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1,'student_table_id'=>$student_table_id]);    
        }
    }

    //printing_details table of next serial number
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

    //get financialyear
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
}
