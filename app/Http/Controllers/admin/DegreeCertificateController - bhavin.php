<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use Session,TCPDF,TCPDF_FONTS,Auth,DB;
use App\models\StudentTable;
use App\models\SuperAdmin;
use App\Http\Requests\ExcelValidationRequest;
use App\models\BackgroundTemplateMaster;
use App\models\SystemConfig;
use App\Jobs\DegreeCertificatePdfJob;
use App\models\Degree;
use App\models\Config;
use App\models\PrintingDetail;
use App\models\ExcelUploadHistory;

class DegreeCertificateController extends Controller
{
    public function index(Request $request)
    {
        //remove session that is assigned during preview pdf
        Session::remove('template_data');
        //if we are in index page then make create_refresh field inside template 0
        TemplateMaster::where('create_refresh',1)->update(['create_refresh'=>0]);

        if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and (actual_template_name like \"%{$search}%\""
                . ")";
            }

            $status=$request->get('status');

            if($status==1)
            {
                $status=1;
                $where_str.= " and (template_master.status =$status)";
            }
            else if($status==0)
            {
                $status=0;
                $where_str.=" and (template_master.status= $status)";
            }                                    
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            DB::statement(DB::raw('set @rownum=0'));

            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'actual_template_name','id','status'];
            
            $degree_columns_count = TemplateMaster::select($columns)
            ->whereRaw($where_str, $where_params)
             ->where('site_id',$auth_site_id)
             ->where('id',1)
            ->count();

            $degree_list = TemplateMaster::select($columns)
            ->where('site_id',$auth_site_id)
             ->where('id',1)
            ->whereRaw($where_str, $where_params);
            

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $degree_list = $degree_list->take($request->input('iDisplayLength'))
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
                    $degree_list = $degree_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $degree_list = $degree_list->get();

            $response['iTotalDisplayRecords'] = $degree_columns_count;
            $response['iTotalRecords'] = $degree_columns_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $degree_list->toArray();

            return $response;
        }
        return view('admin.degreecertificate.index');
    }


    public function checkmaxcertificate(Request $request){
        $user_id = $request->id;

        $site_id=Auth::guard('admin')->user()->site_id;

        $studentTableCounts = StudentTable::select('id')->where('site_id',$site_id)->count();

        $superAdminUpdate = SuperAdmin::where('property','print_limit')
                            ->update(['current_value'=>$studentTableCounts]);
        //get value,current value from super admin
        $template_value = SuperAdmin::select('value','current_value')->where('property','print_limit')->first();
        if($template_value['value'] == null || $template_value['value'] == 0 || (int)$template_value['current_value'] < (int)$template_value['value']){ 
            return response()->json(['success']);
        }
        else{
            return response()->json(['limit exceed']);
        }   
    }

    public function excelvalidation(ExcelValidationRequest $request){
        //only for excel validation of extension and size
        if($request['field_file'] != 'undefined'){
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            
            if($ext != 'xls' && $ext != 'xlsx'){    
                return response()->json(['success'=>false,'message'=>'Please enter valid excel sheet']);
            }
        }
        else{
            return response()->json(['success'=>false,'message'=>'Please upload file with .xls or .xlsx extension!','type'=>'toaster']);
        }
        return response()->json(['success'=>true]);
    }

    public function excelcheck(Request $request){
        //excel process

        if($request->hasFile('field_file')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            $excelfile =  date("YmdHis") . "_" . $file_name;
            
            $target_path = public_path().'\backend\canvas\dummy_images\/'.$request['id'];

            $fullpath = $target_path.'\/'.$excelfile;

            if($request['field_file']->move($target_path,$excelfile)){

                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                $objPHPExcel = $objReader->load($fullpath);
                $sheet = $objPHPExcel->getSheet(0);
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestDataRow();

                $rowData = $sheet->rangeToArray('A1:' . $highestColumn . 1, NULL, TRUE, FALSE);
                $auth_site_id=Auth::guard('admin')->user()->site_id;
                $TemplateUniqueColumn = TemplateMaster::select('unique_serial_no')->where('id',$request['id'])->first();

                $unique_serial_no = $TemplateUniqueColumn['unique_serial_no'];
                
                $getKey = '';
                
                foreach ($rowData[0] as $key => $value) {
                    if($unique_serial_no == $value){

                        $getKey = $key;
                    }
                }
                $rowData1 = $sheet->rangeToArray('A2:' . $highestColumn . $highestRow, NULL, TRUE, FALSE);
                $old_rows = 0;
                $new_rows = 0;
                
                foreach ($rowData1 as $key1 => $value1) {
                    
                    if(!empty($getKey)){
                    $studentTableCounts = StudentTable::where('serial_no',$value1[$getKey])->where('site_id',$auth_site_id)->count();
                    if($studentTableCounts > 0){
                        $old_rows += 1;
                    }else{
                        $new_rows += 1;
                    } 
                    }else{
                        $new_rows += 1;
                    }  
                }
            }
        }
        
        if (file_exists($fullpath)) {
            unlink($fullpath);
        }
        $msg = array('type'=> 'duplicate', 'message' => 'excel history founded','old_rows'=>$old_rows,'new_rows'=>$new_rows);
        return json_encode($msg);
    }

    public function uploadfile(Request $request){

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $template_id = $request->id;
        //get template data from id
        $template_data = TemplateMaster::select('id','unique_serial_no','template_name','bg_template_id','width','height','background_template_status')->where('id',$template_id)->first();
        //get background template data
        if($template_data['bg_template_id'] != 0){
            $bg_template_data = BackgroundTemplateMaster::select('width','height')->where('id',$template_data['bg_template_id'])->first();
        }

        //store all data in one variable
        $FID = array();
        $FID['template_id']  = $template_data['id'];
        $FID['bg_template_id']  = $template_data['bg_template_id'];
        $FID['template_width']  = $template_data['width'];
        $FID['template_height'] = $template_data['height'];
        $FID['template_name']  =  $template_data['template_name'];
        $FID['background_template_status']  =  $template_data['background_template_status'];
        $FID['printing_type'] = 'pdfGenerate';
        //get field data

        $check_mapped = array();
            $FID['mapped_excel_col'][] = '';
            $FID['data'][] = '';

        if($request->hasFile('field_file')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            
            $target_path = public_path().'\backend\canvas\dummy_images\/'.$template_data['template_name'];
            $fullpath = $target_path.'\/'.$excelfile;

            
            if($request['field_file']->move($target_path,$excelfile)){
               
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                $auth_site_id=Auth::guard('admin')->user()->site_id;

                $aws_excel = \File::copy($fullpath,public_path().'\\'.$subdomain[0].'\backend\templates\/'.$template_data['id'].'\/'.$excelfile);

                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel = $objReader->load($fullpath);
                $sheet = $objPHPExcel->getSheet(0);
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestDataRow();

                $rowData = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE);
            

                $rowData[0] = array_filter($rowData[0]);
                
                $ab = array_count_values($rowData[0]);

                $duplicate_columns = '';
                foreach ($ab as $key => $value) {
                    
                    if($value > 1){

                        if($duplicate_columns != ''){

                            $duplicate_columns .= ", ".$key;
                        }else{
                            
                            $duplicate_columns .= $key;
                        }
                    }
                }

                if($duplicate_columns != ''){
                    // Excel has more than 1 column having same name. i.e. <column name>
                    $message = Array('type' => 'fieldNotMatch', 'message' => '<table border="1"><tr><td style="padding:10px;">Excel has more than 1 column having same name. i.e. : <br><b>'.$duplicate_columns.'</b></td></tr></table>');
                    return json_encode($message);
                }
                
                $fields = array();
                $mapped_excel_col_unique_serial_no = '';

                //hardcoded mapping columns
                foreach ($rowData[0] as $key => $f) {
                    if($f != '') {
                        $fields[] = $f;
                        if($mapped_excel_col_unique_serial_no == '') {
                            if($template_data['unique_serial_no'] == $f)
                                $mapped_excel_col_unique_serial_no = $key;

                        }
                       
                    }
                }
                //get diff if mapped name not match with excel
                $diff = array_diff($check_mapped, $fields);

                if(count($diff) > 0)
                {
                    if (file_exists($fullpath)) {
                        unlink($fullpath);
                    }
                    $diff_name = implode(", ", $diff);
                    $message = Array('type' => 'fieldNotMatch', 'message' => '<table border="1"><tr><td style="padding:10px;">Following mapping columns are missing : <br><b>'.$diff_name.'</b></td></tr></table>');
                    return json_encode($message);
                }
            }
        }
        else{
            dd(2);
        }


        $date = date('Y_m_d_his');

        // Get Background Template image
        $bg_template_img = '';
        
        if(isset($FID['bg_template_id']) && $FID['bg_template_id'] != '') {
            if($FID['bg_template_id'] == 0) {
                $bg_template_width = $FID['template_width'];
                $bg_template_height = $FID['template_height'];
            } 
            else {
                if($FID['background_template_status'] == 0){
                    $bg_template_width = $FID['template_width'];
                    $bg_template_height = $FID['template_height'];
                }
                else
                {
                    $get_bg_template_data = BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();
                  
                    $bg_template_img = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\degree_certificate_bg.jpg';
                    $bg_template_width = $get_bg_template_data['width'];
                    $bg_template_height = $get_bg_template_data['height'];
                }
                
            }
        } 
        else 
        {
            $bg_template_img = '';
            $bg_template_width = 210;
            $bg_template_height = 297;
            if($FID['bg_template_id'] == 0) {
                $bg_template_width = $FID['template_width'];
                $bg_template_height = $FID['template_height'];
            } 
        }

        $template_Width = $bg_template_width;
        $template_height = $bg_template_height;
      
        $Orientation = 'P';
        if($bg_template_width > $bg_template_height)
            $Orientation = 'L';
        $path = public_path().'backend/canvas/dummy_images/new/App Audit-Final.pdf';
        // This PDF is for download (printing) and Preview
        $pdf = new TCPDF($Orientation, 'mm', array($template_Width, $template_height), true, 'UTF-8', false);
        $page_width = $bg_template_width;

        $pdf->SetCreator('TCPDF');
        $pdf->SetAuthor('TCPDF');
        $pdf->SetTitle('Certificate');
        $pdf->SetSubject('');

        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);

        // code for check map excel or database
        $sheet = $objPHPExcel->getSheet(0);
            

        //get system config data
        $get_system_config_data = SystemConfig::get();
        
        $printer_name = '';
        $timezone = '';
        
        if(!empty($get_system_config_data[0])){
            $printer_name = $get_system_config_data[0]['printer_name'];
            $timezone = $get_system_config_data[0]['timezone'];
        }
        // QR Code
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
        // code for check map excel or database amd count total array match

            $highestColumn = $sheet->getHighestColumn();
            $highestRow = $sheet->getHighestDataRow();
       

        $FID['map_type'] = '';
        
        if(!isset($request->flag)) {

            $pdf->SetProtection(array('modify', 'copy','annot-forms','fill-forms','extract','assemble'),'',null,0,null);

            //check if value come from formula or not

                $formula = [];
                foreach ($sheet->getCellCollection() as $cellId) {
                    foreach ($cellId as $key1 => $value1) {
                        $checkFormula = $sheet->getCell($cellId)->isFormula();
                        if($checkFormula == 1){
                            $formula[] = $cellId;       
                        }   
                    }  
                };

                //if formula is included then delete excel file
                if(!empty($formula)){

                    $tmp_name = public_path().'\backend\canvas\dummy_images\/'.$FID['template_name'].'\/'.$excelfile;
                    if (file_exists($tmp_name)) {
                        unlink($tmp_name);
                    }
                    $message = array('type'=> 'formula', 'message' => 'Please remove formula from column','cell'=>$formula);
                    return response()->json(['success'=>false,'message'=>$message,'flag'=>0]);
                }
            
        }


        $get_bg_template_data =  BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();

        if(isset($FID['bg_template_id']) && $FID['bg_template_id'] != '') {
            if($FID['bg_template_id'] == 0) {
                $bg_template_width_generate = $FID['template_width'];
                $bg_template_height_generate = $FID['template_height'];
            } 
            else {
                if($FID['background_template_status'] == 0){
                    $bg_template_width_generate = $FID['template_width'];
                    $bg_template_height_generate = $FID['template_height'];
                }
                else
                {

                    $bg_template_img_generate = 'C:\xampp\htdocs\seqr\public\\'.$subdomain[0].'\backend\canvas\bg_images\degree_certificate_bg.jpg';
                    $bg_template_width_generate = $get_bg_template_data['width'];
                    $bg_template_height_generate = $get_bg_template_data['height'];
                }
            }
        }
        else 
        {
            $bg_template_img = '';
            $bg_template_width = 210;
            $bg_template_height = 297;
            if($FID['bg_template_id'] == 0) {
                $bg_template_width = $FID['template_width'];
                $bg_template_height = $FID['template_height'];
            } 
        }
        

        //store ghost image
        $tmpDir = $this->createTemp(public_path().'\backend\canvas\ghost_images\temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();

        //pdf generation process
        $excel_row_num = 2;
        $pdf_flag = 0;
        if(isset($request->is_progress)){
            $excel_row_num = $request->excel_row;
            $pdf_flag = 1;
        }
        
        $pdf_data = ['highestRow'=>$highestRow,'highestColumn'=>$highestColumn,'FID'=>$FID,'Orientation'=>$Orientation,'template_Width'=>$template_Width,'template_height'=>$template_height,'bg_template_img_generate'=>$bg_template_img_generate,'bg_template_width_generate'=>$bg_template_width_generate,'bg_template_height_generate'=>$bg_template_height_generate,'bg_template_img'=>$bg_template_img,'bg_template_width'=>$bg_template_width,'bg_template_height'=>$bg_template_height,'mapped_excel_col_unique_serial_no'=>$mapped_excel_col_unique_serial_no,'ext'=>$ext,'fullpath'=>$fullpath,'tmpDir'=>$tmpDir,'excelfile'=>$excelfile,'timezone'=>$timezone,'printer_name'=>$printer_name,'style2D'=>$style2D,'style1D'=>$style1D,'style1Da'=>$style1Da,'admin_id'=>$admin_id,'excel_row_num'=>$excel_row_num,'pdf_flag'=>$pdf_flag];

        //pdf generation process
        $response = $this->dispatch(new DegreeCertificatePdfJob($pdf_data));

        return response()->json(['success'=>true,'is_progress'=>$response['is_progress'],'excel_row'=>$response['excel_row'],'highestRow'=>$response['highestRow'],'msg'=>$response['msg']]);
    }


    public function databaseGenerate(){

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();



        $ghostImgArr = array();
        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);


       $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) AS prog_spec FROM gu_dc_2 WHERE CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E))="MASTER OF LIBRARY AND INFORMATION SCIENCE";') );
       

      
        foreach ($fetch_degree_data_prog_spec as $value) {
           $progSpec =$value->prog_spec;


            $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM gu_dc_2 WHERE CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) ="'.$value->prog_spec.'"'));
          
            $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();
          
          
            $fetch_degree_array=array();
        foreach ($fetch_degree_data as $key => $value) {
            $fetch_degree_array[$key] = array_values($fetch_degree_data[$key]);
        }

         $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

            $pdf->SetCreator('TCPDF');
            $pdf->SetAuthor('TCPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');

            // remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);

            $pdf->SetOverprint(true, true, 0);
        
            $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text 
        for($excel_row = 0; $excel_row < count($fetch_degree_array); $excel_row++)
        {

              //profile photo
                $extension = '';
                if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpg.jpg')){
                    $extension = '.jpg.jpg';
                  
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpeg.jpg')){
                    $extension = '.jpeg.jpg';

                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.png.jpg')){
                    $extension = '.png.jpg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpg')){
                    $extension = '.jpg';
                }
                else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpeg')){
                    $extension = '.jpeg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.png')){
                    $extension = '.png';
                }
        
                if(!empty($extension)){
                   $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].$extension;
                }else{
                     if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpg.jpg')){
                        $extension = '.jpg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpeg.jpg')){
                        $extension = '.jpeg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.png.jpg')){
                        $extension = '.png.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpg')){
                        $extension = '.jpg';
                    }
                    else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpeg')){
                        $extension = '.jpeg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.png')){
                        $extension = '.png';
                    }

                    if(!empty($extension)){
                    $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].$extension;
                    }else{
                     $profile_path ='';  
                    }
                }

           

            if(!empty($profile_path)){


            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');
            

            $pdf->AddPage();
            
            if($fetch_degree_array[$excel_row][10] == 'DB'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DB_Light Background.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'DIP'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DIP_Background_lite.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'INT'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/INT_lite background.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'DO'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DO_Background Lite.jpg';   
            } else if($fetch_degree_array[$excel_row][10] == 'NU'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/GU Nursing background_lite.jpg';   
            }

            
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
           
            


            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            
            $serial_no = trim($fetch_degree_array[$excel_row][0]);

                //set enrollment no
                $enrollment_font_size = '8';
                $enrollmentx= 26.7;
                
                $enrollmenty = 10.8;
                $enrollmentstr = trim($fetch_degree_array[$excel_row][1]);

                $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
                $pdf->SetXY($enrollmentx, $enrollmenty);
                $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');


                //set serial No
                $serial_no_split = (string)trim($fetch_degree_array[$excel_row][3]);
                $serialx = 186.4;
                $serialx = 181;
                $serialy = 10.9;
                for($i=0;$i<strlen($serial_no_split);$i++)
                { 
                    $get_last_four_digits = strlen($serial_no_split) - 4;
                    
                    $serial_font_size = 8;
                    if($i == 0){
                        $serialx = $serialx;
                    }
                    else{
                        if($i <= $get_last_four_digits){
                            if($serial_no_split[$i-1] == '/'){
                                $serialx = $serialx + (0.9);
                            }
                            else{
                                $serialx = $serialx + (1.7);
                            }
                        }
                        else{
                            $serialx = $serialx + (2.1);
                        }
                    }
                    if($i >= $get_last_four_digits){
                        
                        $serial_font_size = $serial_font_size + ($i - $get_last_four_digits) + 1;
                        $serialy = $serialy - 0.3;
                   
                    }
                    $serialstr = $serial_no_split[$i];

                    $pdf->SetFont($arial, '', $serial_font_size, '', false);
                    $pdf->SetXY($serialx, $serialy);
                    $pdf->Cell(0, 0, $serialstr, 0, false, 'L');
                }


                if($fetch_degree_array[$excel_row][10] == 'NU'){
                     $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$fetch_degree_array[$excel_row][17]."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                          $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                    }
                    else if($fetch_degree_array[$excel_row][10] == 'DO'){
                        $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                    }

                }

                $codePath = strtoupper(md5(rand()));
                $qr_code_path = public_path().'/'.$subdomain[0].'/temp_qr/'.$codePath.'.png';
                $qrCodex = 5.3;
                $qrCodey = 17.9;
                $qrCodeWidth =26.3;
                $qrCodeHeight = 25.3;
         
                \QrCode::size(75.6)
                    ->format('png')
                    ->generate($codeContents, $qr_code_path);
                $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

                $profilex = 181;
                $profiley = 19.8;
                $profileWidth = 22.2;
                
                $profileHeight = 26.6;
                $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);


                 //invisible data
                $invisible_font_size = '10';

                $invisible_degreex = 7.3;
                $invisible_degreey = 48.3;
                $invisible_degreestr = trim($fetch_degree_array[$excel_row][11]);
               
                $pdf->SetTextSpotColor('Spot Red', 100);
                $pdf->SetFont($arial, 'B', $invisible_font_size, '', false);
                $pdf->SetXY($invisible_degreex, $invisible_degreey);
                $pdf->Cell(0, 0, $invisible_degreestr, 0, false, 'L');
               

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $invisible1y = 51.9;  
                    
                    $invisible1str = trim($fetch_degree_array[$excel_row][13]);
                    
                    $pdf->SetTextSpotColor('Spot Red', 100);
                    $pdf->SetFont($arial, 'B', $invisible_font_size, '', false);
                    $pdf->SetXY($invisible_degreex, $invisible1y);
                    $pdf->Cell(0, 0, $invisible1str, 0, false, 'L');
                }

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    
                    $invisible2y = 55.1;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $invisible2y = 51.9;   
                }else if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $invisible2y = 56.1;
                }

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                   $invisible2str = $fetch_degree_array[$excel_row][17];
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                   $invisible2str = 'CGPA '.$cgpaFormat;  
                }
                
                
                $pdf->SetTextSpotColor('Spot Red', 100);
                $pdf->SetFont($arial, 'B', $invisible_font_size, '', false);
                $pdf->SetXY($invisible_degreex, $invisible2y);
                $pdf->Cell(0, 0, $invisible2str, 0, false, 'L');


                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    
                    $invisible3y = 58.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $invisible3y = 55.1;
                }else if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $invisible3y = 59.7;
                }
                $invisible3str = trim($fetch_degree_array[$excel_row][8]); 
                
                $pdf->SetTextSpotColor('Spot Red', 100);
                $pdf->SetFont($arial, 'B', $invisible_font_size, '', false);
                $pdf->SetXY($invisible_degreex, $invisible3y);
                $pdf->Cell(0, 0, $invisible3str, 0, false, 'L');

                //invisible data profile name
                $invisible_profile_font_size = '10';
                $invisible_profile_name1x = 175.9;
                
                $invisible_profile_name1y = 47.6;
                $invisible_profile_name1str = strtoupper(trim($fetch_degree_array[$excel_row][4]));
                
                $pdf->SetTextSpotColor('Spot Red', 100);
                $pdf->SetFont($arial, 'B', $invisible_profile_font_size, '', false);
                $pdf->SetXY($invisible_profile_name1x, $invisible_profile_name1y);
                $pdf->Cell(28.8, 0, $invisible_profile_name1str, 0, false, 'R');

                $invisible_profile_name2x = 186.6;
                $invisible_profile_name2y = 50.8;
                $invisible_profile_name2str = trim($fetch_degree_array[$excel_row][1]);
                $pdf->SetTextSpotColor('Spot Red', 100);
                $pdf->SetFont($arial, 'B', $invisible_profile_font_size, '', false);
                $pdf->SetXY($invisible_profile_name2x, $invisible_profile_name2y);
                $pdf->Cell(18, 0, $invisible_profile_name2str, 0, false, 'R');

                //enrollment no inside round
                $enrollment_no_font_size = '7';
                
                $enrollment_nox = 184.8;
                $enrollment_noy = 66;
                
                $enrollment_nostr = trim($fetch_degree_array[$excel_row][1]);

                $pdf->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
                $pdf->SetTextColor(0,0,0,8,false,'');
                $pdf->SetXY(186, $enrollment_noy);
                $pdf->Cell(12, 0, $enrollment_nostr, 0, false, 'C');


                //profile name
                $profile_name_font_size = '20';
                $profile_namex = 71.7;
                
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $profile_namey = 83.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $profile_namey = 85;
                }
                $profile_namestr = strtoupper(trim($fetch_degree_array[$excel_row][4]));

                $pdf->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_namey);
                $pdf->Cell(190, 0, $profile_namestr, 0, false, 'C');


                //degree name
                $degree_name_font_size = '20';
                $degree_namex = 55;
                
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $degree_namey = 99.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'INT'){
                    $degree_name_font_size = '14';
                    $degree_namey = 103.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $degree_namey = 104.5;
                }
                // $degree_namestr = 'Integrated(BACHELOR OF TECHNOLOGY in Electronics & Communication Engineering)-';
                if($fetch_degree_array[$excel_row][10] != 'DIP'){
                $degree_namestr = trim($fetch_degree_array[$excel_row][11]);

                $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_namey);
                $pdf->Cell(190, 0, $degree_namestr, 0, false, 'C');
                }

                //branch name
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'NU'){
                        $branch_name_font_size = '18';
                        $branch_namey = 114.2;
                    }
                    else if($fetch_degree_array[$excel_row][10] == 'INT'){
                        $branch_name_font_size = '14';
                        $branch_namey = 111.5;
                    }else if ( $fetch_degree_array[$excel_row][10] == 'DIP') {
                        $branch_name_font_size = '20';
                        $branch_namey = 99.5;
                    }
                   
                    $branch_namex = 80;
                    $branch_namestr = trim($fetch_degree_array[$excel_row][13]);

                    $pdf->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_namey);
                    $pdf->Cell(190, 0, $branch_namestr, 0, false, 'C');
                }

                //grade
                $grade_font_size = '17';

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $gradey = 137.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    
                    $gradey = 133.3;
                }elseif ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $gradey = 132.3;
                }
                $divisionStr= '';

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    if(strpos($fetch_degree_array[$excel_row][17], 'With') !== false){

                         $gradestr = $fetch_degree_array[$excel_row][17].' ';
                    }else{
                        $divisionStr= ' division ';
                        $gradestr = $fetch_degree_array[$excel_row][17]; 
                    }
                   
                }else{
                if(is_float($fetch_degree_array[$excel_row][6])){
                $gradestr = 'CGPA '. number_format(trim($fetch_degree_array[$excel_row][6]),2).' ';
                }else{
                $gradestr = 'CGPA '. trim($fetch_degree_array[$excel_row][6]).' ';    
                }
                }

                $instr = $divisionStr.'in ';
                $datestr = trim($fetch_degree_array[$excel_row][8]);


                $grade_str_result = $this->GetStringPositions(
                    array(
                        array($gradestr, $timesNewRomanBI, '', $grade_font_size), 
                        array($instr, $timesNewRomanI, '', $grade_font_size),
                        array($datestr, $timesNewRomanBI, '', $grade_font_size)
                    ),$pdf
                );
                

                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[0], $gradey);
                $pdf->Cell(0, 0, $gradestr, 0, false, 'L');


                $pdf->SetFont($timesNewRomanI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[1], $gradey);
                $pdf->Cell(0, 0, $instr, 0, false, 'L');


                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[2], $gradey);
                $pdf->Cell(0, 0, $datestr, 0, false, 'L');


                //micro line name
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $microlinestr = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][4].$fetch_degree_array[$excel_row][11].$fetch_degree_array[$excel_row][13]));
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $microlinestr = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][4].$fetch_degree_array[$excel_row][11]));
                }
                $textArray = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlinestr);
                $strWidth = ($textArray[2] - $textArray[0]);
                $strHeight = $textArray[6] - $textArray[1] / 1.4;
                
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $latestWidth = 557;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $latestWidth = 564;
                }
                $wd = '';
                $last_width = 0;
                $message = array();
                for($i=1;$i<=1000;$i++){

                    if($i * $strWidth > $latestWidth){
                        $wd = $i * $strWidth;
                        $last_width =$wd - $strWidth;
                        $extraWidth = $latestWidth - $last_width;
                        $stringLength = strlen($microlinestr);
                        $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                        $message[$i]  = mb_substr($microlinestr, 0,$extraCharacter);
                        break;
                    }
                    $message[$i] = $microlinestr.'';
                }

                $horizontal_line = array();
                foreach ($message as $key => $value) {
                    $horizontal_line[] = $value;
                }
                

                $string = implode(',', $horizontal_line);
                $array = str_replace(',', '', $string);
                //bhavin rgb to cmyk & B
                $pdf->SetFont($arialb, 'B', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0, 100);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' ||  $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $pdf->SetXY(36.8, 146.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.8, 145.5);
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $pdf->SetXY(36.8, 143.5);
                }
                $pdf->Cell(0, 0, $array, 0, false, 'L');

                $pdf->StopTransform();



                if($fetch_degree_array[$excel_row][10] == 'NU'){
                   $microlineEnrollment = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][1].strtoupper($fetch_degree_array[$excel_row][17]).$fetch_degree_array[$excel_row][8])); 
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                  $microlineEnrollment = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][1].'CGPA'.$cgpaFormat.$fetch_degree_array[$excel_row][8]));  
                }
                


                $textArrayEnrollment = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlineEnrollment);
                $strWidthEnrollment = ($textArrayEnrollment[2] - $textArrayEnrollment[0]);
                $strHeightEnrollment = $textArrayEnrollment[6] - $textArrayEnrollment[1] / 1.4;
                
                $latestWidthEnrollment = 627;
                $wdEnrollment = '';
                $last_widthEnrollment = 0;
                $messageEnrollment = array();
                for($i=1;$i<=1000;$i++){

                    if($i * $strWidthEnrollment > $latestWidthEnrollment){
                        $wdEnrollment = $i * $strWidthEnrollment;
                        $last_widthEnrollment =$wdEnrollment - $strWidthEnrollment;
                        $extraWidth = $latestWidthEnrollment - $last_widthEnrollment;
                        $stringLength = strlen($microlineEnrollment);
                        $extraCharacter = intval($stringLength * $extraWidth / $strWidthEnrollment);
                        $messageEnrollment[$i]  = mb_substr($microlineEnrollment, 0,$extraCharacter);
                        break;
                    }
                    $messageEnrollment[$i] = $microlineEnrollment.'';
                }

                $horizontal_lineEnrollment = array();
                foreach ($messageEnrollment as $key => $value) {
                    $horizontal_lineEnrollment[] = $value;
                }
                
                $stringEnrollment = implode(',', $horizontal_lineEnrollment);
                $arrayEnrollment = str_replace(',', '', $stringEnrollment);
                // Bhavin CMYK & B
                $pdf->SetFont($arialb, 'B', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0, 100);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $pdf->SetXY(36.4, 216.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.4, 216);
                }
                
                $pdf->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdf->StopTransform();

                //profile name in hindi
                $profile_name_hindi_font_size = '25';
                $profile_name_hidix = 85.1;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' ||  $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $profile_name_hidiy = 156.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $profile_name_hidiy = 159;
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                   $profile_name_hidiy = 161;
                }
                $profile_name_hindistr = trim($fetch_degree_array[$excel_row][5]);

                $pdf->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_name_hidiy);
                $pdf->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');

                //date in hindi (make whole string)
                $date_font_size =  '20';
                if($fetch_degree_array[$excel_row][10] == 'DIP'){
                $str = 'rhu o"khZ; fMIyksek ikBîØe ';
                $hindiword_str = ' ' ; 
                }else{
                $str = 'dks bl mikf/k dh çkfIr gsrq fofue; fofgr vis{kkvksa dks ';
                $hindiword_str = 'esa' ; 
                }
                $date_hindistr = trim($fetch_degree_array[$excel_row][9]).' ';
                

                $strx = 20;
                $date_hindix = 159;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $date_hindiy = 168.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $date_hindiy = 170.6;
                }else if($fetch_degree_array[$excel_row][10] == 'DIP'){
                    $date_hindiy = 180.4;
                }

                $result = $this->GetStringPositions(
                    array(
                        array($str, $krutidev100, '', $date_font_size), 
                        array($date_hindistr, $krutidev101, '', $date_font_size),
                        array($hindiword_str, $krutidev100, '', $date_font_size)
                    ),$pdf
                );

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[0], $date_hindiy);
                $pdf->Cell(0, 0, $str, 0, false, 'L');

                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[1], $date_hindiy);
                $pdf->Cell(0, 0, $date_hindistr, 0, false, 'L');

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[2], $date_hindiy);
                $pdf->Cell(0, 0, $hindiword_str, 0, false, 'L');



                //grade in hindi
                $grade_hindix = 37.5;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' ){
                    
                    $grade_hindiy = 177.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $grade_hindiy = 181.7;
                }elseif ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $grade_hindix = 40.5;
                    $grade_hindiy = 188.5;
                }
                elseif ($fetch_degree_array[$excel_row][10] == 'NU') {

                    if(strpos($fetch_degree_array[$excel_row][17], 'With') !== false){
                       
                        $grade_hindiy = 178.8;
                        $grade_hindix = 31.5; 
                    }else if($fetch_degree_array[$excel_row][17]=="First"){
                       $grade_hindiy = 178.8;
                        $grade_hindix = 49.5; 
                    }else{
                       $grade_hindiy = 178.8;
                        $grade_hindix = 46.5; 
                    }
                    
                }



                if ($fetch_degree_array[$excel_row][10] == 'DIP') {

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_hindix-6, $grade_hindiy);
                $pdf->Cell(0, 0, 'esa', 0, false, 'L'); 
                 
                }

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                  $grade_hindistr = trim($fetch_degree_array[$excel_row][18]);
                }
                else{
                  $grade_hindistr = 'lh-th-ih-,- '.trim($fetch_degree_array[$excel_row][7]);  
                }
                
                
                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_hindix, $grade_hindiy);
                $pdf->Cell(0, 0, $grade_hindistr, 0, false, 'L');
                

                //degree name in hindi
                $degree_hindi_font_size = '25';
                if($fetch_degree_array[$excel_row][10] == 'INT'){
                    $degree_hindi_font_size = '18';
                    $degree_hindiy = 188.8;
                }
                $degree_hindix = 66;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    $degree_hindiy = 185.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $degree_hindiy = 192;
                }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {
                    $degree_hindiy = 188.2;
                }
                $degree_hindistr = trim($fetch_degree_array[$excel_row][12]);
                if($fetch_degree_array[$excel_row][10] != 'DIP'){
                $pdf->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_hindiy);
                $pdf->Cell(190, 0, $degree_hindistr, 0, false, 'C');
                }
                //branch name in hindi
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    if($fetch_degree_array[$excel_row][10] == 'DIP'){
                        $branch_hindi_font_size = '25';
                    }else {
                        $branch_hindi_font_size = '20';
                    }
                    
                    $branch_hindiy = 196.5;
                    if($fetch_degree_array[$excel_row][10] == 'INT'){
                        $branch_hindi_font_size = '15';
                        $branch_hindiy = 196.8;
                    }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {
                        $branch_hindiy = 199.8;
                    }
                    $branch_hindix = 75.2;
                    $branch_hindistr = trim($fetch_degree_array[$excel_row][14]);

                    $pdf->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_hindiy);
                    $pdf->Cell(190, 0, $branch_hindistr, 0, false, 'C');
                }

                //today date
                $today_date_font_size = '12';
                
                $today_datex = 95;
                $today_datey = 273.8;
                $todaystr = 'September, 2020';

                $pdf->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(84, $today_datey);
                $pdf->Cell(47, 0, $todaystr, 0, false, 'C');

                //1D Barcode
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
                
                $barcodex = 84;
                $barcodey = 278;
                $barcodeWidth = 46;
                $barodeHeight = 12;

                $pdf->SetAlpha(1);
                // Barcode CODE_39 - Roll no 
                $pdf->write1DBarcode(trim($fetch_degree_array[$excel_row][1]), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

                $footer_name_font_size = '12';
                $footer_namex = 84.9;
                $footer_namey = 290.9;
                $footer_namestr = strtoupper(trim($fetch_degree_array[$excel_row][4]));

                $pdf->SetFont($arial, 'B', $footer_name_font_size, '', false);
                
                $pdf->SetTextSpotColor('Spot Dark Green', 100);
                $pdf->SetXY(10, $footer_namey);
                $pdf->Cell(190, 0, $footer_namestr, 0, false, 'C');
                

                //repeat line
                $repeat_font_size = '9.5';
                $repeatx= 0;

                    //name repeat line
                    
                    $name_repeaty = 242.9;
                    if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $cgpaFormat = strtoupper($fetch_degree_array[$excel_row][17]);
                    }else{
                        if(is_float($fetch_degree_array[$excel_row][6])){
                            $cgpaFormat='CGPA '.number_format(trim($fetch_degree_array[$excel_row][6]),2);
                        }else{
                            $cgpaFormat='CGPA '.trim($fetch_degree_array[$excel_row][6]);
                        }
                    }
                    $name_repeatstr = strtoupper(trim($fetch_degree_array[$excel_row][4])).' '.$cgpaFormat.' '.strtoupper(trim($fetch_degree_array[$excel_row][8])).' '; 
                    $name_repeatstr .= $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr;

                    //degree repeat line
 
                    $degree_repeaty = 247;
                    $degree_repeatstr = strtoupper(trim($fetch_degree_array[$excel_row][11])).' '.strtoupper(trim($fetch_degree_array[$excel_row][13])).' '; 
                    $degree_repeatstr .= $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr;

                    //grade repeat line
                    $grade_repeaty = 251.1;
                    $grade_repeatstr = 'GALGOTIAS UNIVERSITY '; 
                    $grade_repeatstr .= $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr;

                    //date repeat line
                    $date_repeaty = 255.2;
                    $date_repeatstr = 'GALGOTIAS UNIVERSITY '; 
                    $date_repeatstr .= $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr;

                        $pdf->SetTextColor(0,0,0,8,false,'');
                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $name_repeaty);
                        $pdf->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();

                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $degree_repeaty);
                        $pdf->Cell(0, 0, $degree_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();
                       

                unlink(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

                //vc sign visible
                $vc_sign_visible_path = public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png';
                
                $vc_sign_visibllex = 168.5;
                $vc_sign_visiblley = 243.7;
                $vc_sign_visiblleWidth = 21;
                $vc_sign_visiblleHeight = 16;
                $pdf->image($vc_sign_visible_path,$vc_sign_visibllex,$vc_sign_visiblley,$vc_sign_visiblleWidth,$vc_sign_visiblleHeight,"",'','L',true,3600);

                unlink(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');
                

                // Ghost image
                $ghost_font_size = '13';
                $ghostImagex = 10;
                $ghostImagey = 278.8;
                $ghostImageWidth = 68;
                $ghostImageHeight = 9.8;
                $name = substr(str_replace(' ','',strtoupper($fetch_degree_array[$excel_row][4])), 0, 6);
                

                $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp_galgotias');
                
                $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');

                $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

             Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'1']);
            }else{
             
              Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'0']);
            }
        }
            $certName = str_replace("/", "_","2019 ".$fetch_degree_array[0][11].' '.$fetch_degree_array[0][13]).'.pdf';
            
            $myPath = public_path().'/'.$subdomain[0].'/galgotias_pdf';
            $dt = date("_ymdHis");
            
           $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

           
        }
           


    }


    public function pdfGenerate(){


        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $ghostImgArr = array();
        $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdf->SetCreator('TCPDF');
        $pdf->SetAuthor('TCPDF');
        $pdf->SetTitle('Certificate');
        $pdf->SetSubject('');

        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);


        // add spot colors
        $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo

        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);



        $pdf->AddPage();

        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\INT_lite background.jpg';
        
        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        
        //set enrollment no
        $enrollment_font_size = '8';
        $enrollmentx= 26.7;
        $enrollmenty = 10.8;
        $enrollmentstr = '12345678901';
        $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdf->SetXY($enrollmentx, $enrollmenty);
        $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');
         

        //set serial No

        $serial_no_split = (string)('D19/12345678');
        
        $serialx = 181;
        $serialy = 10.9;
        for($i=0;$i<strlen($serial_no_split);$i++)
        { 
            $get_last_four_digits = strlen($serial_no_split) - 4;
            
            $serial_font_size = 8;
            if($i == 0){
                $serialx = $serialx;
            }
            else{
                if($i <= $get_last_four_digits){
                    if($serial_no_split[$i-1] == '/'){
                        $serialx = $serialx + (0.9);
                    }
                    else{
                        $serialx = $serialx + (1.7);
                    }
                }
                else{
                    $serialx = $serialx + (2.1);
                }
            }
            if($i >= $get_last_four_digits){
                
                $serial_font_size = $serial_font_size + ($i - $get_last_four_digits) + 1;
                $serialy = $serialy - 0.3;
            
            }
           
            $serialstr = $serial_no_split[$i];

            $pdf->SetFont($arial, '', $serial_font_size, '', false);
            $pdf->SetXY($serialx, $serialy);
            $pdf->Cell(0, 0, $serialstr, 0, false, 'L');
            
        }

        //qr code    
        $codeContents = strtoupper(md5(rand()));
        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$codeContents.'.png';
        $qrCodex = 5.3;
        $qrCodey = 17.9;
        $qrCodeWidth =26.3;
        $qrCodeHeight = 25.3;
                
        \QrCode::size(75.6)
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

        //profile photo
        $profile_path = public_path().'\\'.$subdomain[0].'\backend\templates\444\degree_certi.jpg';
        $profilex = 181;
        $profiley = 19.8;
        $profileWidth = 22.2;
        $profileHeight = 26.6;
        $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

        //invisible data
        
        $invisible_font_size = '8';

        $invisible_degreex = 7.3;
        $invisible_degreey = 48.3;
        $invisible_degreestr = 'BACHELOR OF TECNOLOGY';
        $pdf->SetOverprint(true, true, 0);
        $pdf->SetTextSpotColor('Spot Red', 100);
        $pdf->SetFont($arial, '', $invisible_font_size, '', false);
        $pdf->SetXY($invisible_degreex, $invisible_degreey);
        $pdf->Cell(0, 0, $invisible_degreestr, 0, false, 'L');



        $invisible1y = 51.9;
        $invisible1str = 'Chemical Engineering';
        $pdf->SetTextSpotColor('Spot Red', 100);
        $pdf->SetFont($arial, '', $invisible_font_size, '', false);
        $pdf->SetXY($invisible_degreex, $invisible1y);
        $pdf->Cell(0, 0, $invisible1str, 0, false, 'L');

        $invisible2y = 55.1;
        $invisible2str = 'CGPA 4.06'; 
        $pdf->SetTextSpotColor('Spot Red', 100);
        $pdf->SetFont($arial, '', $invisible_font_size, '', false);
        $pdf->SetXY($invisible_degreex, $invisible2y);
        $pdf->Cell(0, 0, $invisible2str, 0, false, 'L');

        $invisible3y = 58.2;
        $invisible3str = 'December , 2018'; 
        $pdf->SetTextSpotColor('Spot Red', 100);
        $pdf->SetFont($arial, '', $invisible_font_size, '', false);
        $pdf->SetXY($invisible_degreex, $invisible3y);
        $pdf->Cell(0, 0, $invisible3str, 0, false, 'L');

        //invisible data profile name
        $invisible_profile_font_size = '8';
        $invisible_profile_name1x = 175.9;
        $invisible_profile_name1y = 47.6;
        $invisible_profile_name1str = 'ANITA KUMARI CHOWDHARY';
        $pdf->SetTextSpotColor('Spot Red', 100);
        $pdf->SetFont($arial, '', $invisible_profile_font_size, '', false);
        $pdf->SetXY($invisible_profile_name1x, $invisible_profile_name1y);
        $pdf->Cell(29, 0, $invisible_profile_name1str, 0, false, 'R');

        $invisible_profile_name2x = 186.6;
        $invisible_profile_name2y = 50.9;
        $invisible_profile_name2str = '16051010030';
        $pdf->SetTextSpotColor('Spot Red', 100);
        $pdf->SetFont($arial, '', $invisible_profile_font_size, '', false);
        $pdf->SetXY($invisible_profile_name2x, $invisible_profile_name2y);
        $pdf->Cell(18, 0, $invisible_profile_name2str, 0, false, 'R');
        $pdf->SetOverprint(false, false, 0);

        //enrollment no inside round
        $enrollment_no_font_size = '7';
        $enrollment_nox = 184.8;
        $enrollment_noy = 66;
        $enrollment_nostr = '14211010021';
        $pdf->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
        $pdf->SetTextColor(0,0,0,8,false,'');
        $pdf->SetXY(186, $enrollment_noy);
        $pdf->Cell(12, 0, $enrollment_nostr, 0, false, 'C');

        //profile name
        $profile_name_font_size = '20';
        $profile_namex = 71.7;
        $profile_namey = 83.4;
        $profile_namestr = 'ANITA KUMARI CHOWDHARY';
        $pdf->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $profile_namey);
        $pdf->Cell(190, 0, $profile_namestr, 0, false, 'C');


        //degree name
        $degree_name_font_size = '14';
        $degree_namex = 55;
        $degree_namey = 103.5;
        $degree_namestr = 'Integrated(BACHELOR OF TECHNOLOGY in Electronics & Communication Engineering)-';
        
        $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $degree_namey);
        $pdf->Cell(190, 0, $degree_namestr, 0, false, 'C');

        //branch name
        $branch_name_font_size = '14';
        $branch_namex = 80;
        $branch_namey = 111.5;
        $branch_namestr = 'Applied Psychology';
        $pdf->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $branch_namey);
        $pdf->Cell(190, 0, $branch_namestr, 0, false, 'C');

        //grade
        $grade_font_size = '17';
        $gradex = 62.6;
        $gradey = 137.2;
        $gradestr = 'CGPA 59.60';
        $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($gradex, $gradey);
        $pdf->Cell(0, 0, $gradestr, 0, false, 'L');

        //date
        $datex = 109.2;
        $datey = 133.3;
        $datestr = 'December, 2018.';
        $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($datex, $datey);
        $pdf->Cell(0, 0, $datestr, 0, false, 'L');

        //micro line name
        $microlinestr = strtoupper(str_replace(' ','','SHANTANU TOMARBCHELOR OF TECHNOLOGY'));
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $microlinestr);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        $latestWidth = 564;
        $wd = '';
        $last_width = 0;
        $message = array();
        for($i=1;$i<=1000;$i++){

            if($i * $strWidth > $latestWidth){
                $wd = $i * $strWidth;
                $last_width =$wd - $strWidth;
                $extraWidth = $latestWidth - $last_width;
                $stringLength = strlen($microlinestr);
                $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                $message[$i]  = mb_substr($microlinestr, 0,$extraCharacter);
                break;
            }
            $message[$i] = $microlinestr.'';
        }

        $horizontal_line = array();
        foreach ($message as $key => $value) {
            $horizontal_line[] = $value;
        }
        
        $string = implode(',', $horizontal_line);
        $array = str_replace(',', '', $string);
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        
        $pdf->SetXY(36.8, 145.5);
        
        $pdf->Cell(0, 0, $array, 0, false, 'L');

        $pdf->StopTransform();

        $microlineEnrollment = strtoupper(str_replace(' ','','ABC\12\1234CGPA4.06May, 2018 '));
        $textArrayEnrollment = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $microlineEnrollment);
        $strWidthEnrollment = ($textArrayEnrollment[2] - $textArrayEnrollment[0]);
        $strHeightEnrollment = $textArrayEnrollment[6] - $textArrayEnrollment[1] / 1.4;
        
        $latestWidthEnrollment = 627;
        $wdEnrollment = '';
        $last_widthEnrollment = 0;
        $messageEnrollment = array();
        for($i=1;$i<=1000;$i++){

            if($i * $strWidthEnrollment > $latestWidthEnrollment){
                $wdEnrollment = $i * $strWidthEnrollment;
                $last_widthEnrollment =$wdEnrollment - $strWidthEnrollment;
                $extraWidth = $latestWidthEnrollment - $last_widthEnrollment;
                $stringLength = strlen($microlineEnrollment);
                $extraCharacter = intval($stringLength * $extraWidth / $strWidthEnrollment);
                $messageEnrollment[$i]  = mb_substr($microlineEnrollment, 0,$extraCharacter);
                break;
            }
            $messageEnrollment[$i] = $microlineEnrollment.'';
        }

        $horizontal_lineEnrollment = array();
        foreach ($messageEnrollment as $key => $value) {
            $horizontal_lineEnrollment[] = $value;
        }
        
        $stringEnrollment = implode(',', $horizontal_lineEnrollment);
        $arrayEnrollment = str_replace(',', '', $stringEnrollment);
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(36.4, 216);
        
        $pdf->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

        $pdf->StopTransform();

        //profile name in hindi
        $profile_name_hindi_font_size = '25';
        $profile_name_hidix = 85.1;
        $profile_name_hidiy = 156.5;
        $profile_name_hindistr = "vuhrk dqekjh pkSèkjh";
        $pdf->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $profile_name_hidiy);
        $pdf->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');


        $date_font_size =  '20';
        $str = 'dks bl mikf/k dh çkfIr gsrq fofue; fofgr vis{kkvksa dks';
        $date_hindistr = 'eÃ]„åƒ‹';
        $hindiword_str = 'esa'; 
        $stry = 168.4;

        $result = $this->GetStringPositions(
            array(
                array($str, $krutidev100, '', $date_font_size), 
                array($date_hindistr, $krutidev101, '', $date_font_size),
                array($hindiword_str, $krutidev100, '', $date_font_size)
            ),$pdf
        );


        $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
        $pdf->SetXY($result[0], $stry);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
        $pdf->SetXY($result[1], $stry);
        $pdf->Cell(0, 0, $date_hindistr, 0, false, 'L');

        $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
        $pdf->SetXY($result[2], $stry);
        $pdf->Cell(0, 0, $hindiword_str, 0, false, 'L');


        //grade in hindi
        $grade_hindix = 39.7;
        $grade_hindiy = 181.7;
        $grade_hindistr = 'lh-th-ih-,- †-åˆ';
        $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($grade_hindix, $grade_hindiy);
        $pdf->Cell(0, 0, $grade_hindistr, 0, false, 'L');

        //degree name in hindi
        $degree_hindi_font_size = '18';
        $degree_hindix = 66;
        $degree_hindiy = 188.8;
        $degree_hindistr = 'cSpyj vkWQ vkV~Zl ¼vkWulZ½';
        $pdf->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $degree_hindiy);
        $pdf->Cell(190, 0, $degree_hindistr, 0, false, 'C');

        //branch name in hindi
        $branch_hindi_font_size = '15';
        $branch_hindix = 75.2;
        $branch_hindiy = 196.8;
        $branch_hindistr = 'vIykbM lkbdkykWth';
        $pdf->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $branch_hindiy);
        $pdf->Cell(190, 0, $branch_hindistr, 0, false, 'C');

        //today date
        $today_date_font_size = '12';
        $today_datex = 95;
        $today_datey = 273.8;
        $todaystr = 'September, 2020';
        $pdf->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(84, $today_datey);
        $pdf->Cell(47, 0, $todaystr, 0, false, 'C');

        //1D Barcode
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
        $barcodex = 84;
        $barcodey = 278;
        $barcodeWidth = 46;
        $barodeHeight = 12;
        $pdf->SetAlpha(1);
        // Barcode CODE_39 - Roll no 
        $pdf->write1DBarcode($enrollment_nostr, 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

        $footer_name_font_size = '12';
        $footer_namex = 84.9;
        $footer_namey = 290.9;
        $footer_namestr = 'SHANTANU TOMAR';
        $pdf->SetOverprint(true, true, 0);
        $pdf->SetFont($arial, '', $footer_name_font_size, '', false);
        $pdf->SetTextSpotColor('Spot Dark Green', 100);
        $pdf->SetXY($footer_namex, $footer_namey);
        $pdf->Cell(0, 0, $footer_namestr, 0, false, 'L');
        $pdf->SetOverprint(false, false, 0);
        //repeat line
        $repeat_font_size = '9.5';
        $repeatx= 0;

            //name repeat line
            $name_repeaty = 242.9;
            $name_repeatstr = 'SHANTANU TOMAR'; 
            $security_line = '';

            //degree repeat line
            $degree_repeaty = 247;
            $degree_repeatstr = 'MASTER OF BUSINESS ADMINITRATION'; 
            $degree_security_line = '';

            //grade repeat line
            $grade_repeaty = 251.1;
            $grade_repeatstr = 'CGPA 4.06'; 
            $grade_security_line = '';

            //date repeat line
            $date_repeaty = 255.2;
            $date_repeatstr = 'DECEMBER, 2018.'; 
            $date_security_line = '';


            for($d = 0; $d < 15; $d++){
                //name repeat line
                $security_line .= $name_repeatstr . ' ';
                $pdf->SetOverprint(true, true, 0);
                $pdf->SetTextColor(0,0,0,4,false,'');
                $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdf->StartTransform();
                $pdf->SetXY($repeatx, $name_repeaty);
                $pdf->Cell(0, 0, $security_line, 0, false, 'L');
                $pdf->StopTransform();

                //degree repeat line
                $degree_security_line .= $degree_repeatstr . ' ';
                $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdf->StartTransform();
                $pdf->SetXY($repeatx, $degree_repeaty);
                $pdf->Cell(0, 0, $degree_security_line, 0, false, 'L');
                $pdf->StopTransform();

                //grade repeat line
                $grade_security_line .= $grade_repeatstr . ' ';
                $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdf->StartTransform();
                $pdf->SetXY($repeatx, $grade_repeaty);
                $pdf->Cell(0, 0, $grade_security_line, 0, false, 'L');
                $pdf->StopTransform();

                //date repeat line
                $date_security_line .= $date_repeatstr . ' ';
                $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                $pdf->StartTransform();
                $pdf->SetXY($repeatx, $date_repeaty);
                $pdf->Cell(0, 0, $date_security_line, 0, false, 'L');
                $pdf->StopTransform();
                $pdf->SetOverprint(false, false, 0);
            }

        //uv sign invisible
        $uv_sign_invisible_path = public_path().'\\'.$subdomain[0].'\backend\templates\444\uv_visible_sign.png';
        $uv_sign_invisiblex = 128;
        $uv_sign_invisibley = 257.7;
        $uv_sign_invisibleWidth = 21;
        $uv_sign_invisibleHeight = 16;
        $pdf->image($uv_sign_invisible_path,$uv_sign_invisiblex,$uv_sign_invisibley,$uv_sign_invisibleWidth,$uv_sign_invisibleHeight,"",'','L',true,3600);

        //uv sign visible
        $uv_sign_visiblle_path = public_path().'\\'.$subdomain[0].'\backend\templates\444\uv_invisible_sign.png';
        $uv_sign_visibllex = 96;
        $uv_sign_visiblley = 243.7;
        $uv_sign_visiblleWidth = 21;
        $uv_sign_visiblleHeight = 16;
        $pdf->image($uv_sign_visiblle_path,$uv_sign_visibllex,$uv_sign_visiblley,$uv_sign_visiblleWidth,$uv_sign_visiblleHeight,"",'','L',true,3600);


        //vc sign visible
        $uv_sign_visiblle_path = public_path().'\\'.$subdomain[0].'\backend\templates\444\VC_Sign.png';
        $uv_sign_visibllex = 168.5;
        $uv_sign_visiblley = 243.7;
        $uv_sign_visiblleWidth = 21;
        $uv_sign_visiblleHeight = 16;
        $pdf->image($uv_sign_visiblle_path,$uv_sign_visibllex,$uv_sign_visiblley,$uv_sign_visiblleWidth,$uv_sign_visiblleHeight,"",'','L',true,3600);

        //ce sign visible
        $uv_sign_visiblle_path = public_path().'\\'.$subdomain[0].'\backend\templates\444\CE_Sign.png';
        $uv_sign_visibllex = 26;
        $uv_sign_visiblley = 243.7;
        $uv_sign_visiblleWidth = 35;
        $uv_sign_visiblleHeight = 16;
        $pdf->image($uv_sign_visiblle_path,$uv_sign_visibllex,$uv_sign_visiblley,$uv_sign_visiblleWidth,$uv_sign_visiblleHeight,"",'','L',true,3600);

        // Ghost image
        $ghost_font_size = '13';
        $ghostImagex = 10;
        $ghostImagey = 278.8;
        $ghostImageWidth = 68;
        $ghostImageHeight = 9.8;
        $name = str_replace(' ','',substr('SHANTANU TOMAR', 0, 6));

        $tmpDir = $this->createTemp(public_path().'\backend\canvas\ghost_images\temp');
        if(!array_key_exists($name, $ghostImgArr))
        {
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
            $ghostImgArr[$name] = $w;   
        }
        else{
            $w = $ghostImgArr[$name];
        }

        $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

        $pdf->Output('sample.pdf', 'I');  
    }

    public function dbUploadfile(){
        $template_data = TemplateMaster::select('id','template_name')->where('id',1)->first()->toArray();
       
        $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) AS prog_spec, COUNT(guid) FROM gu_dc_2 WHERE STATUS="0" GROUP BY prog_spec ORDER BY COUNT(guid) ASC') );
        
        foreach ($fetch_degree_data_prog_spec as $value) {
           $progSpec =$value->prog_spec;

             $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM gu_dc_2 WHERE CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) ="'.$value->prog_spec.'" AND status="0"'));
            $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();

            $fetch_degree_array=array();
        $admin_id = \Auth::guard('admin')->user()->toArray();
        
        foreach ($fetch_degree_data as $key => $value) {
            $fetch_degree_array[$key] = array_values($fetch_degree_data[$key]);
        }
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        
        $printer_name = $systemConfig['printer_name'];

        $ghostImgArr = array();
        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);

        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);

        $log_serial_no = 1;


        for($excel_row = 0; $excel_row < count($fetch_degree_array); $excel_row++)
        {   

            //profile photo
                $extension = '';
                if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpg.jpg')){
                    $extension = '.jpg.jpg';
                  
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpeg.jpg')){
                    $extension = '.jpeg.jpg';

                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.png.jpg')){
                    $extension = '.png.jpg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpg')){
                    $extension = '.jpg';
                }
                else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpeg')){
                    $extension = '.jpeg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.png')){
                    $extension = '.png';
                }
        
                if(!empty($extension)){
                   $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].$extension;
                }else{
                     if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpg.jpg')){
                        $extension = '.jpg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpeg.jpg')){
                        $extension = '.jpeg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.png.jpg')){
                        $extension = '.png.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpg')){
                        $extension = '.jpg';
                    }
                    else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpeg')){
                        $extension = '.jpeg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.png')){
                        $extension = '.png';
                    }

                    if(!empty($extension)){
                    $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].$extension;
                    }else{
                     $profile_path ='';  
                    }
                }
            if(!empty($profile_path)){

            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');
            
            $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

            $pdf->SetCreator('TCPDF');
            $pdf->SetAuthor('TCPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');

            // remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45);


            $pdfBig->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            $pdfBig->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo

            $pdf->AddPage();
            if($fetch_degree_array[$excel_row][10] == 'DB'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DB_Light Background.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'DIP'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DIP_Background_lite.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'INT'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/INT_lite background.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'DO'){
                 $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DO_Background Lite.jpg';   
            } else if($fetch_degree_array[$excel_row][10] == 'NU'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/GU Nursing background_lite.jpg';   
            }
        
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
           
            $pdfBig->AddPage();


            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            $serial_no = trim($fetch_degree_array[$excel_row][0]);

            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

                //set enrollment no
                $enrollment_font_size = '8';
                $enrollmentx= 26.7;
                
                $enrollmenty = 10.8;
                $enrollmentstr = trim($fetch_degree_array[$excel_row][1]);

                $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
                $pdf->SetXY($enrollmentx, $enrollmenty);
                $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');


                $pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
                $pdfBig->SetXY($enrollmentx, $enrollmenty);
                $pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');


                //set serial No
                $serial_no_split = (string)trim($fetch_degree_array[$excel_row][3]);
                $serialx = 186.4;
                $serialx = 181;
                $serialy = 10.9;
                for($i=0;$i<strlen($serial_no_split);$i++)
                { 
                    $get_last_four_digits = strlen($serial_no_split) - 4;
                    
                    $serial_font_size = 8;
                    if($i == 0){
                        $serialx = $serialx;
                    }
                    else{
                        if($i <= $get_last_four_digits){
                            if($serial_no_split[$i-1] == '/'){
                                $serialx = $serialx + (0.9);
                            }
                            else{
                                $serialx = $serialx + (1.7);
                            }
                        }
                        else{
                            $serialx = $serialx + (2.1);
                        }
                    }
                    if($i >= $get_last_four_digits){
                        
                        $serial_font_size = $serial_font_size + ($i - $get_last_four_digits) + 1;
                        $serialy = $serialy - 0.3;
                   
                    }
                    $serialstr = $serial_no_split[$i];

                    $pdf->SetFont($arial, '', $serial_font_size, '', false);
                    $pdf->SetXY($serialx, $serialy);
                    $pdf->Cell(0, 0, $serialstr, 0, false, 'L');

                    $pdfBig->SetFont($arial, '', $serial_font_size, '', false);
                    $pdfBig->SetXY($serialx, $serialy);
                    $pdfBig->Cell(0, 0, $serialstr, 0, false, 'L');
                }



                //qr code    
                //name // enrollment // degree // branch // cgpa // guid
                if($fetch_degree_array[$excel_row][10] == 'NU'){
                     $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$fetch_degree_array[$excel_row][17]."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                        $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                    }
                    else if($fetch_degree_array[$excel_row][10] == 'DO'){
                        $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                    }

                }
                $codePath = strtoupper(md5(rand()));
                $qr_code_path = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$codePath.'.png';
                $qrCodex = 5.3;
                $qrCodey = 17.9;
                $qrCodeWidth =26.3;
                $qrCodeHeight = 25.3;
                        
                \QrCode::size(75.6)
                    ->format('png')
                    ->generate($codeContents, $qr_code_path);
                $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);


                $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);                

               
                $profilex = 181;
                $profiley = 19.8;
                $profileWidth = 22.2;
                $profileHeight = 26.6;
                $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                //invisible data
                $invisible_font_size = '10';

                $invisible_degreex = 7.3;
                $invisible_degreey = 48.3;
                $invisible_degreestr = trim($fetch_degree_array[$excel_row][11]);
                $pdfBig->SetOverprint(true, true, 0);
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible_degreey);
                $pdfBig->Cell(0, 0, $invisible_degreestr, 0, false, 'L');

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $invisible1y = 51.9;
                    $invisible1str = trim($fetch_degree_array[$excel_row][13]);
                    $pdfBig->SetTextSpotColor('Spot Red', 100);
                    $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                    $pdfBig->SetXY($invisible_degreex, $invisible1y);
                    $pdfBig->Cell(0, 0, $invisible1str, 0, false, 'L');

                }

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    $invisible2y = 55.1;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $invisible2y = 51.9;   
                }else if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $invisible2y = 56.1;
                }

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                   $invisible2str = $fetch_degree_array[$excel_row][17];
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                   $invisible2str = 'CGPA '.$cgpaFormat;  
                }

                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible2y);
                $pdfBig->Cell(0, 0, $invisible2str, 0, false, 'L');

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    
                    $invisible3y = 58.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $invisible3y = 55.1;
                }else if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $invisible3y = 59.7;
                }
                $invisible3str = trim($fetch_degree_array[$excel_row][8]); 
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible3y);
                $pdfBig->Cell(0, 0, $invisible3str, 0, false, 'L');

                //invisible data profile name
                $invisible_profile_font_size = '10';
                $invisible_profile_name1x = 175.9;
                $invisible_profile_name1y = 47.6;
                $invisible_profile_name1str = strtoupper(trim($fetch_degree_array[$excel_row][4]));
                
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_profile_font_size, '', false);
                $pdfBig->SetXY($invisible_profile_name1x, $invisible_profile_name1y);
                $pdfBig->Cell(28.8, 0, $invisible_profile_name1str, 0, false, 'R');

                
                $invisible_profile_name2x = 186.6;
                $invisible_profile_name2y = 50.8;
                $invisible_profile_name2str = trim($fetch_degree_array[$excel_row][1]);
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_profile_font_size, '', false);
                $pdfBig->SetXY($invisible_profile_name2x, $invisible_profile_name2y);
                $pdfBig->Cell(18, 0, $invisible_profile_name2str, 0, false, 'R');
            
                //enrollment no inside round
                $enrollment_no_font_size = '7';
                
                $enrollment_nox = 184.8;
                $enrollment_noy = 66;
                
                $enrollment_nostr = trim($fetch_degree_array[$excel_row][1]);

                $pdf->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
                $pdf->SetTextColor(0,0,0,8,false,'');
                $pdf->SetXY(186, $enrollment_noy);
                $pdf->Cell(12, 0, $enrollment_nostr, 0, false, 'C');

                $pdfBig->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,8,false,'');
                $pdfBig->SetXY(186, $enrollment_noy);
                $pdfBig->Cell(12, 0, $enrollment_nostr, 0, false, 'C');

                //profile name
                $profile_name_font_size = '20';
                $profile_namex = 71.7;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $profile_namey = 83.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $profile_namey = 85;
                }
                $profile_namestr = strtoupper(trim($fetch_degree_array[$excel_row][4]));

                $pdf->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_namey);
                $pdf->Cell(190, 0, $profile_namestr, 0, false, 'C');

                $pdfBig->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $profile_namey);
                $pdfBig->Cell(190, 0, $profile_namestr, 0, false, 'C');


                //degree name
                $degree_name_font_size = '20';
                $degree_namex = 55;
                
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $degree_namey = 99.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'INT'){
                    $degree_name_font_size = '14';
                    $degree_namey = 103.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $degree_namey = 104.5;
                }
                
                $degree_namestr = trim($fetch_degree_array[$excel_row][11]);

                if($fetch_degree_array[$excel_row][10] != 'DIP'){

                $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_namey);
                $pdf->Cell(190, 0, $degree_namestr, 0, false, 'C');


                $pdfBig->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $degree_namey);
                $pdfBig->Cell(190, 0, $degree_namestr, 0, false, 'C');
                }

                //branch name
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'NU'){
                        $branch_name_font_size = '18';
                        $branch_namey = 114.2;
                    }
                    else if($fetch_degree_array[$excel_row][10] == 'INT'){
                        $branch_name_font_size = '14';
                        $branch_namey = 111.5;
                    }else if ( $fetch_degree_array[$excel_row][10] == 'DIP') {
                        $branch_name_font_size = '20';
                        $branch_namey = 99.5;
                    }
                   
                    $branch_namex = 80;
                    $branch_namestr = trim($fetch_degree_array[$excel_row][13]);

                    $pdf->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_namey);
                    $pdf->Cell(190, 0, $branch_namestr, 0, false, 'C');


                    $pdfBig->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
                    $pdfBig->SetTextColor(0,92,88,7,false,'');
                    $pdfBig->SetXY(10, $branch_namey);
                    $pdfBig->Cell(190, 0, $branch_namestr, 0, false, 'C');
                }

                //grade
                $grade_font_size = '17';

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){

                    $gradey = 137.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $gradey = 133.3;
                }elseif ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $gradey = 132.3;
                }
                $divisionStr= '';

                if($fetch_degree_array[$excel_row][10] == 'NU'){

                    if(strpos($fetch_degree_array[$excel_row][17], 'With') !== false){

                         $gradestr = $fetch_degree_array[$excel_row][17].' ';
                    }else{
                        $divisionStr= ' division ';
                        $gradestr = $fetch_degree_array[$excel_row][17]; 
                    }
                   
                }else{
                if(is_float($fetch_degree_array[$excel_row][6])){
                $gradestr = 'CGPA '. number_format(trim($fetch_degree_array[$excel_row][6]),2).' ';
                }else{
                $gradestr = 'CGPA '. trim($fetch_degree_array[$excel_row][6]).' ';    
                }
                }
                $instr = $divisionStr.'in ';
                $datestr = trim($fetch_degree_array[$excel_row][8]);


                $grade_str_result = $this->GetStringPositions(
                    array(
                        array($gradestr, $timesNewRomanBI, '', $grade_font_size), 
                        array($instr, $timesNewRomanI, '', $grade_font_size),
                        array($datestr, $timesNewRomanBI, '', $grade_font_size)
                    ),$pdf
                );
                

                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[0], $gradey);
                $pdf->Cell(0, 0, $gradestr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[0], $gradey);
                $pdfBig->Cell(0, 0, $gradestr, 0, false, 'L');


                $pdf->SetFont($timesNewRomanI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[1], $gradey);
                $pdf->Cell(0, 0, $instr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[1], $gradey);
                $pdfBig->Cell(0, 0, $instr, 0, false, 'L');


                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[2], $gradey);
                $pdf->Cell(0, 0, $datestr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[2], $gradey);
                $pdfBig->Cell(0, 0, $datestr, 0, false, 'L');


                //micro line name
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $microlinestr = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][4].$fetch_degree_array[$excel_row][11].$fetch_degree_array[$excel_row][13]));
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $microlinestr = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][4].$fetch_degree_array[$excel_row][11]));
                }
                $textArray = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlinestr);
                $strWidth = ($textArray[2] - $textArray[0]);
                $strHeight = $textArray[6] - $textArray[1] / 1.4;
                
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $latestWidth = 557;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $latestWidth = 564;
                }
                $wd = '';
                $last_width = 0;
                $message = array();
                for($i=1;$i<=1000;$i++){

                    if($i * $strWidth > $latestWidth){
                        $wd = $i * $strWidth;
                        $last_width =$wd - $strWidth;
                        $extraWidth = $latestWidth - $last_width;
                        $stringLength = strlen($microlinestr);
                        $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                        $message[$i]  = mb_substr($microlinestr, 0,$extraCharacter);
                        break;
                    }
                    $message[$i] = $microlinestr.'';
                }

                $horizontal_line = array();
                foreach ($message as $key => $value) {
                    $horizontal_line[] = $value;
                }
                

                $string = implode(',', $horizontal_line);
                $array = str_replace(',', '', $string);
                $pdf->SetFont($arialb, '', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $pdf->SetXY(36.8, 146.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.8, 145.5);
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $pdf->SetXY(36.8, 143.5);
                }
                $pdf->Cell(0, 0, $array, 0, false, 'L');

                $pdf->StopTransform();


                $pdfBig->SetFont($arialb, '', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0);
                $pdfBig->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $pdfBig->SetXY(36.8, 146.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdfBig->SetXY(36.8, 145.5);
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $pdfBig->SetXY(36.8, 143.5);
                }
                $pdfBig->Cell(0, 0, $array, 0, false, 'L');

                $pdfBig->StopTransform();

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                   $microlineEnrollment = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][1].strtoupper($fetch_degree_array[$excel_row][17]).$fetch_degree_array[$excel_row][8])); 
                }else{
                  if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                  $microlineEnrollment = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][1].'CGPA'.$cgpaFormat.$fetch_degree_array[$excel_row][8]));  
                }

                $textArrayEnrollment = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlineEnrollment);
                $strWidthEnrollment = ($textArrayEnrollment[2] - $textArrayEnrollment[0]);
                $strHeightEnrollment = $textArrayEnrollment[6] - $textArrayEnrollment[1] / 1.4;
                
                $latestWidthEnrollment = 627;
                $wdEnrollment = '';
                $last_widthEnrollment = 0;
                $messageEnrollment = array();
                for($i=1;$i<=1000;$i++){

                    if($i * $strWidthEnrollment > $latestWidthEnrollment){
                        $wdEnrollment = $i * $strWidthEnrollment;
                        $last_widthEnrollment =$wdEnrollment - $strWidthEnrollment;
                        $extraWidth = $latestWidthEnrollment - $last_widthEnrollment;
                        $stringLength = strlen($microlineEnrollment);
                        $extraCharacter = intval($stringLength * $extraWidth / $strWidthEnrollment);
                        $messageEnrollment[$i]  = mb_substr($microlineEnrollment, 0,$extraCharacter);
                        break;
                    }
                    $messageEnrollment[$i] = $microlineEnrollment.'';
                }

                $horizontal_lineEnrollment = array();
                foreach ($messageEnrollment as $key => $value) {
                    $horizontal_lineEnrollment[] = $value;
                }
                
                $stringEnrollment = implode(',', $horizontal_lineEnrollment);
                $arrayEnrollment = str_replace(',', '', $stringEnrollment);

                $pdf->SetFont($arialb, '', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $pdf->SetXY(36.4, 216.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.4, 216);
                }
                
                $pdf->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdf->StopTransform();


                $pdfBig->SetFont($arialb, '', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0);
                $pdfBig->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $pdfBig->SetXY(36.4, 216.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdfBig->SetXY(36.4, 216);
                }
                
                $pdfBig->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdfBig->StopTransform();

                $profile_name_hindi_font_size = '25';
                $profile_name_hidix = 85.1;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $profile_name_hidiy = 155.8;
                    $profile_name_hidiy = 156.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $profile_name_hidiy = 159;
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                   $profile_name_hidiy = 161;
                }
                $profile_name_hindistr = trim($fetch_degree_array[$excel_row][5]);

                $pdf->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_name_hidiy);
                $pdf->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');

                $pdfBig->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $profile_name_hidiy);
                $pdfBig->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');

                //date in hindi (make whole string)
                $date_font_size =  '20';
                if($fetch_degree_array[$excel_row][10] == 'DIP'){
                $str = 'rhu o"khZ; fMIyksek ikBîØe ';
                $hindiword_str = ' ' ; 
                }else{
                $str = 'dks bl mikf/k dh çkfIr gsrq fofue; fofgr vis{kkvksa dks ';
                $hindiword_str = 'esa' ; 
                }
                $date_hindistr = trim($fetch_degree_array[$excel_row][9]).' ';
               

                $strx = 20;
                $date_hindix = 159;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $date_hindiy = 168.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $date_hindiy = 170.6;
                }else if($fetch_degree_array[$excel_row][10] == 'DIP'){
                    $date_hindiy = 180.4;
                }

                $result = $this->GetStringPositions(
                    array(
                        array($str, $krutidev100, '', $date_font_size), 
                        array($date_hindistr, $krutidev101, '', $date_font_size),
                        array($hindiword_str, $krutidev100, '', $date_font_size)
                    ),$pdf
                );

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[0], $date_hindiy);
                $pdf->Cell(0, 0, $str, 0, false, 'L');

                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[0], $date_hindiy);
                $pdfBig->Cell(0, 0, $str, 0, false, 'L');

                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[1], $date_hindiy);
                $pdf->Cell(0, 0, $date_hindistr, 0, false, 'L');

                $pdfBig->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[1], $date_hindiy);
                $pdfBig->Cell(0, 0, $date_hindistr, 0, false, 'L');

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[2], $date_hindiy);
                $pdf->Cell(0, 0, $hindiword_str, 0, false, 'L');


                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[2], $date_hindiy);
                $pdfBig->Cell(0, 0, $hindiword_str, 0, false, 'L');



                //grade in hindi
                $grade_hindix = 37.5;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT'){
                    $grade_hindiy = 177.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $grade_hindiy = 181.7;
                }elseif ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $grade_hindix = 40.5;
                    $grade_hindiy = 188.5;
                }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {

                    if(strpos($fetch_degree_array[$excel_row][17], 'With') !== false){
                       
                        $grade_hindiy = 178.8;
                        $grade_hindix = 31.5; 
                    }else if($fetch_degree_array[$excel_row][17]=="First"){
                       $grade_hindiy = 178.8;
                        $grade_hindix = 49.5; 
                    }else{
                       $grade_hindiy = 178.8;
                        $grade_hindix = 46.5; 
                    }
                    
                }
                if ($fetch_degree_array[$excel_row][10] == 'DIP') {

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_hindix-6, $grade_hindiy);
                $pdf->Cell(0, 0, 'esa', 0, false, 'L'); 
                
                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_hindix-6, $grade_hindiy);
                $pdfBig->Cell(0, 0, 'esa', 0, false, 'L'); 
                }


                if($fetch_degree_array[$excel_row][10] == 'NU'){
                  $grade_hindistr = trim($fetch_degree_array[$excel_row][18]);
                }else{
                  $grade_hindistr = 'lh-th-ih-,- '.trim($fetch_degree_array[$excel_row][7]);  
                }

                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_hindix, $grade_hindiy);
                $pdf->Cell(0, 0, $grade_hindistr, 0, false, 'L');

                $pdfBig->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_hindix, $grade_hindiy);
                $pdfBig->Cell(0, 0, $grade_hindistr, 0, false, 'L');


                //degree name in hindi
                $degree_hindi_font_size = '25';
                if($fetch_degree_array[$excel_row][10] == 'INT'){
                    $degree_hindi_font_size = '18';
                    $degree_hindiy = 188.8;
                }
                $degree_hindix = 66;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    $degree_hindiy = 185.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $degree_hindiy = 192;
                }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {
                    $degree_hindiy = 188.2;
                }

                if($fetch_degree_array[$excel_row][10] != 'DIP'){
                $degree_hindistr = trim($fetch_degree_array[$excel_row][12]);

                $pdf->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_hindiy);
                $pdf->Cell(190, 0, $degree_hindistr, 0, false, 'C');

                $pdfBig->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $degree_hindiy);
                $pdfBig->Cell(190, 0, $degree_hindistr, 0, false, 'C');
                }
                //branch name in hindi
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    if($fetch_degree_array[$excel_row][10] == 'DIP'){
                        $branch_hindi_font_size = '25';
                    }else {
                        $branch_hindi_font_size = '20';
                    }
                    $branch_hindiy = 196.5;
                    if($fetch_degree_array[$excel_row][10] == 'INT'){
                        $branch_hindi_font_size = '15';
                        $branch_hindiy = 196.8;
                    }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {
                        $branch_hindiy = 199.8;
                    }
                    $branch_hindix = 75.2;
                    $branch_hindistr = trim($fetch_degree_array[$excel_row][14]);

                    $pdf->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_hindiy);
                    $pdf->Cell(190, 0, $branch_hindistr, 0, false, 'C');

                    $pdfBig->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
                    $pdfBig->SetTextColor(0,92,88,7,false,'');
                    $pdfBig->SetXY(10, $branch_hindiy);
                    $pdfBig->Cell(190, 0, $branch_hindistr, 0, false, 'C');
                }

                //today date
                $today_date_font_size = '12';
                
                $today_datex = 95;
                $today_datey = 273.8;
                $todaystr = 'September, 2020';

                $pdf->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(84, $today_datey);
                $pdf->Cell(47, 0, $todaystr, 0, false, 'C');

                $pdfBig->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(84, $today_datey);
                $pdfBig->Cell(47, 0, $todaystr, 0, false, 'C');

                //1D Barcode
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
                $barcodex = 84;
                $barcodey = 278;
                $barcodeWidth = 46;
                $barodeHeight = 12;

                $pdf->SetAlpha(1);
                // Barcode CODE_39 - Roll no 
                $pdf->write1DBarcode(trim($fetch_degree_array[$excel_row][1]), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

                $pdfBig->SetAlpha(1);
                // Barcode CODE_39 - Roll no 
                $pdfBig->write1DBarcode(trim($fetch_degree_array[$excel_row][1]), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

                 $footer_name_font_size = '12';
                $footer_namex = 84.9;
                $footer_namey = 290.9;
                $footer_namestr = strtoupper(trim($fetch_degree_array[$excel_row][4]));



                $pdfBig->SetOverprint(true, true, 0);
                $pdfBig->SetFont($arial, '', $footer_name_font_size, '', false);
                $pdfBig->SetTextSpotColor('Spot Dark Green', 100);
                $pdfBig->SetXY(10, $footer_namey);
                $pdfBig->Cell(190, 0, $footer_namestr, 0, false, 'C');
                $pdfBig->SetOverprint(false, false, 0);
                //repeat line
                $repeat_font_size = '9.5';
                $repeatx= 0;

                    //name repeat line

                    $name_repeaty = 242.9;
                     if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $cgpaFormat = strtoupper($fetch_degree_array[$excel_row][17]);
                    }else{
                        if(is_float($fetch_degree_array[$excel_row][6])){
                            $cgpaFormat='CGPA '.number_format(trim($fetch_degree_array[$excel_row][6]),2);
                        }else{
                            $cgpaFormat='CGPA '.trim($fetch_degree_array[$excel_row][6]);
                        }
                    }
                    $name_repeatstr = strtoupper(trim($fetch_degree_array[$excel_row][4])).' '.$cgpaFormat.' '.strtoupper(trim($fetch_degree_array[$excel_row][8])).' '; 
                    $name_repeatstr .= $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr;

                    //degree repeat line
                    $degree_repeaty = 247;
                    $degree_repeatstr = strtoupper(trim($fetch_degree_array[$excel_row][11])).' '.strtoupper(trim($fetch_degree_array[$excel_row][13])).' '; 
                    $degree_repeatstr .= $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr;

                    //grade repeat line
                    $grade_repeaty = 251.1;
                    $grade_repeatstr = 'GALGOTIAS UNIVERSITY '; 
                    $grade_repeatstr .= $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr;

                    //date repeat line
                    $date_repeaty = 255.2;
                    $date_repeatstr = 'GALGOTIAS UNIVERSITY '; 
                    $date_repeatstr .= $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr;

                        $pdf->SetTextColor(0,0,0,5,false,'');
                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $name_repeaty);
                        $pdf->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();


                        $pdfBig->SetTextColor(0,0,0,5,false,'');
                        $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdfBig->StartTransform();
                        $pdfBig->SetXY($repeatx, $name_repeaty);
                        $pdfBig->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                        $pdfBig->StopTransform();

                        //degree repeat line
                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $degree_repeaty);
                        $pdf->Cell(0, 0, $degree_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();

                        $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdfBig->StartTransform();
                        $pdfBig->SetXY($repeatx, $degree_repeaty);
                        $pdfBig->Cell(0, 0, $degree_repeatstr, 0, false, 'L');
                        $pdfBig->StopTransform();
                

                unlink(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

                //vc sign visible
                $vc_sign_visible_path = public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png';
                
                $vc_sign_visibllex = 168.5;
                $vc_sign_visiblley = 243.7;
                $vc_sign_visiblleWidth = 21;
                $vc_sign_visiblleHeight = 16;
                $pdf->image($vc_sign_visible_path,$vc_sign_visibllex,$vc_sign_visiblley,$vc_sign_visiblleWidth,$vc_sign_visiblleHeight,"",'','L',true,3600);

                $pdfBig->image($vc_sign_visible_path,$vc_sign_visibllex,$vc_sign_visiblley,$vc_sign_visiblleWidth,$vc_sign_visiblleHeight,"",'','L',true,3600);

                unlink(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');
                

                // Ghost image
                $ghost_font_size = '13';
                $ghostImagex = 10;
                $ghostImagey = 278.8;
                $ghostImageWidth = 68;
                $ghostImageHeight = 9.8;
                $name = substr(str_replace(' ','',strtoupper($fetch_degree_array[$excel_row][4])), 0, 6);


                $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
                
                    $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
           

                $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

                $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

            $certName = str_replace("/", "_", $serial_no) .".pdf";
            $myPath = public_path().'/backend/temp_pdf_file';
            $dt = date("_ymdHis");
            
            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

            $this->addCertificate($serial_no, $certName, $dt,$template_data['id'],$admin_id);

            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            $print_datetime = date("Y-m-d H:i:s");
            

            $print_count = $this->getPrintCount($serial_no);
            $printer_name = /*'HP 1020';*/$printer_name;
            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_data['template_name'],$admin_id);
            
             Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'1']);
            }else{
             Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'0']);;
              
            }

        }


        $msg = '';
        
        $file_name =  str_replace("/", "_",'2019 '.$fetch_degree_array[0][11].' '.$fetch_degree_array[0][13].' '.$fetch_degree_array[0][10]).'.pdf';
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        $pdfBig->output($filename,'F');

        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
        @unlink($filename);
    }
            

    }

    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id)
    {

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        
        copy($file1, $file2);
        
        $aws_qr = \File::copy($file2,public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName);
                
            
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
        $key = strtoupper(md5($serial_no.$dt)); 
        $codeContents = $key;
        $fileName = $key.'.png'; 
        
        $urlRelativeFilePath = 'qr/'.$fileName; 

        
        $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        
    }

    public function getPrintCount($serial_no)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    }

    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id)
    {
        
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
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
        unlink($tmpname);
        mkdir($tmpname);
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
