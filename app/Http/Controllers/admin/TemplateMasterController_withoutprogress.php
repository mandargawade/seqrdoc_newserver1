<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use App\models\SuperAdmin;
use DB,Event;
use App\Http\Requests\ExcelValidationRequest;
use App\Http\Requests\MappingDatabaseRequest;
use App\Http\Requests\TemplateMapRequest;
use App\Http\Requests\TemplateMasterRequest;
use App\Imports\TemplateMapImport;
use App\Imports\TemplateMasterImport;
use App\Jobs\PDFGenerateJob;
use App\models\BackgroundTemplateMaster;
use App\Events\BarcodeImageEvent;
use App\Events\TemplateEvent;
use App\models\FontMaster;
use App\models\FieldMaster;
use App\models\User;
use App\models\StudentTable;
use Maatwebsite\Excel\Facades\Excel;
use TCPDF,Session;
use App\models\SystemConfig;
use App\Jobs\PreviewPDFGenerateJob;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

class TemplateMasterController extends Controller
{
    //list of templates
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

            //for serial number
            DB::statement(DB::raw('set @rownum=0'));

            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'actual_template_name','id','status'];
            
            $categorie_columns_count = TemplateMaster::select($columns)
            ->whereRaw($where_str, $where_params)
            ->count();

            $categorie_list = TemplateMaster::select($columns)
            ->whereRaw($where_str, $where_params);

            if($request->get('iDisplayStart') != '' && $request->get('iDisplayLength') != ''){
                $categorie_list = $categorie_list->take($request->input('iDisplayLength'))
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
                    $categorie_list = $categorie_list->orderBy($column,$request->input('sSortDir_'.$i));   
                }
            }
            $categorie_list = $categorie_list->get();

            $response['iTotalDisplayRecords'] = $categorie_columns_count;
            $response['iTotalRecords'] = $categorie_columns_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $categorie_list->toArray();

            return $response;
        }
        return view('admin.templateMaster.index');
    }
    //check template print limit
    public function checkLimit(Request $request){
        $templateMasterCounts = TemplateMaster::select('id')->count();
        
        $superAdminUpdate = SuperAdmin::where('property','max_templates')
                                ->update(['current_value'=>$templateMasterCounts]);
        $superAdminSelect = SuperAdmin::select(['value','current_value'])
                                ->where('property','max_templates')
                                ->first();
        $maxValue = $superAdminSelect['value'];
        $currentValue = $superAdminSelect['current_value'];
        if(isset($maxValue) || $maxValue == null || $maxValue == 0 || $currentValue < $maxValue){

            echo json_encode(['type'=>'success']);
        }else{

            echo json_encode(['type'=>'error','message'=>'Limit Exceed']);
        }                  
    }
    //create template on canvas
    public function create(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        //during create fields are not added so field count comes 0
        $field_count = 0;
        
        //check if create page refresh after save
        $get_template_id = TemplateMaster::select('id')->where('create_refresh',1)->first();
        
        $TEMPLATE = [];
        $FIELDS = [];
        
        if(!empty($get_template_id)){
            $id = $get_template_id['id'];
            //get template master data
            $TEMPLATE = TemplateMaster::where('id',$id)->first();
            //get field master data
            $FIELDS = FieldMaster::where('template_id',$id)->orderBy('field_position')->get();
            $field_count = 0;
            if(!empty($FIELDS)){
                $field_count = count($FIELDS);
            }
        }
        
        //get backgroubd template master from db
        $BGTEMPLATE = BackgroundTemplateMaster::select('id','background_name')->get();
        //get fonts from font master
        $FONTS = FontMaster::where('status','1')->where('publish','1')->get();
        $font_style = '';
        foreach ($FONTS as $key => $value) {
            $font_style .= '<option value="' . $value['id'] . '">' . $value['font_name'] . '</option>';
        }

        //set font size
        $font_size = '';
        for($i = 1; $i <= 100; $i++) {
            $font_size .= "<option value=\'$i\'>$i</option>";
        }
        
        //define default image
        $default_image = \Config::get('constant.default_image');
        //canvas path
        if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_canvas_upload_path = \Config::get('constant.aws_canvas_upload_path');
        }
        else{
            $aws_canvas_upload_path = \Config::get('constant.server_canvas_upload_path');
        }
        //security line opacity value
        $text_opacity = '';
        $opacity = ['0.1' => '10%','0.2' => '20%','0.3' => '30%','0.4' => '40%','0.5' => '50%','0.6' => '60%','0.7' => '70%','0.8' => '80%','0.9' => '90%','1' => '100%'
        ];
        foreach($opacity as $opacity_key=>$opacity_value) {
            $text_opacity .= "<option value=\'$opacity_key\'>$opacity_value</option>";
        }

        //percentage for uv repeat line
        $uv_percentage_value = '';
        $uv_percentage = ['05' => '05%','15' => '15%','25' => '25%','35' => '35%','45' => '45%','55' => '55%','65' => '65%','75' => '75%','85' => '85%','95' => '95%'
        ];

        foreach($uv_percentage as $uv_per_key=>$uv_per_value) {
            $uv_percentage_value .= "<option value=\'$uv_per_key\'>$uv_per_value</option>";
        }

        return view('admin.addtemplates.canvas',compact('TEMPLATE','FIELDS','BGTEMPLATE','field_count','font_style','font_size','FONTS','default_image','text_opacity','uv_percentage_value','aws_canvas_upload_path'));
    }

    //store template details
    public function store(TemplateMasterRequest $request){
        $get_template_data = $request->all();
        if(isset($get_bgtemplate_data['id']) && !empty($get_bgtemplate_data['id'])){
            $get_bgtemplate_data['template_id'] = $get_bgtemplate_data['id'];
        }
        
        //pass template data to job 
        $template_data = Event::dispatch(new TemplateEvent($get_template_data));
        //return response to blade file

        return response()->json(['data'=>$template_data]);
    }

    //get template details edit time on canvas
    public function edit($id,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        //get backgroubd template master from db
        $BGTEMPLATE = BackgroundTemplateMaster::select('id','background_name')->get();
        //get template master data
        $TEMPLATE = TemplateMaster::where('id',$id)->first();
        //get field master data
        $FIELDS = FieldMaster::where('template_id',$id)->orderBy('field_position')->get();
        $field_count = count($FIELDS);

        //define default image
        $default_image = \Config::get('constant.default_image');

        //canvas path
        if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_canvas_upload_path = \Config::get('constant.aws_canvas_upload_path');
        }
        else{
            $aws_canvas_upload_path = \Config::get('constant.server_canvas_upload_path');
        }
        //get fonts from font master
        $FONTS = FontMaster::where('status','1')->where('publish','1')->get();
        $font_style = '';
        foreach ($FONTS as $key => $value) {
            $font_style .= '<option value="' . $value['id'] . '">' . $value['font_name'] . '</option>';
        }
        //set font size
        $font_size = '';
        for($i = 1; $i <= 100; $i++) {
            $font_size .= "<option value=\'$i\'>$i</option>";
        }
        //security line opacity value
        $text_opacity = '';
        $opacity = ['0.1' => '10%','0.2' => '20%','0.3' => '30%','0.4' => '40%','0.5' => '50%','0.6' => '60%','0.7' => '70%','0.8' => '80%','0.9' => '90%','1' => '100%'
        ];
        foreach($opacity as $opacity_key=>$opacity_value) {
            $text_opacity .= "<option value=\'$opacity_key\'>$opacity_value</option>";
        }
        //percentage for uv repeat line
        $uv_percentage_value = '';
        $uv_percentage = ['05' => '05%','15' => '15%','25' => '25%','35' => '35%','45' => '45%','55' => '55%','65' => '65%','75' => '75%','85' => '85%','95' => '95%'
        ];

        foreach($uv_percentage as $uv_per_key=>$uv_per_value) {
            $uv_percentage_value .= "<option value=\'$uv_per_key\'>$uv_per_value</option>";
        }
        
        return view('admin.addtemplates.canvas',compact('TEMPLATE','FIELDS','field_count','default_image','font_style','font_size','FONTS','text_opacity','uv_percentage_value','BGTEMPLATE','aws_canvas_upload_path'));
    }

    //load canvas
    public function canvas(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        //get fonts from font master
        $FONTS = FontMaster::where('status','1')->where('publish','1')->get();
        //get backgroubd template master from db
        $BGTEMPLATE = BackgroundTemplateMaster::select('id','background_name','image_path')->get();
        if($get_file_aws_local_flag->file_aws_local == '1'){
            $config = \Config::get('constant.aws_canvas_upload_path');
            $bg_upload_path = \Config::get('constant.aws_background_image_path');
        }
        else{
            $config = \Config::get('constant.server_canvas_image_path');
            $bg_upload_path = \Config::get('constant.server_background_image_path');
        }
        return view('admin.addtemplates.canvasmaker',compact('config','FONTS','BGTEMPLATE','bg_upload_path'));
    }

    //onbarcode image change upload image
    public function barcodeimagechange(Request $request){
        $getBarcodeImageData = $request->all(); 
        //pass image data to job 
        $image_data = Event::dispatch(new BarcodeImageEvent($getBarcodeImageData));
        return response()->json(['data'=>$image_data]);
    }

     //onbackground image change background image
    public function bgtemplate(Request $request){
        $get_bgtemplate_id = $request->id;

        $get_bgtemplate_data = BackgroundTemplateMaster::select('width','height')->where('id',$get_bgtemplate_id)->first();
        return json_encode($get_bgtemplate_data);
    }

    //get fonts from font master
    public function font(Request $request){
        $FONTS = FontMaster::select('font_filename','font_filename_B','font_filename_I','font_filename_BI')->where(['status'=>1,'publish'=>1,'id'=>$request->id])->first();
        return $FONTS;
    }
    //check max certificate during pdf generate
    public function checkmaxcertificate(Request $request){
        $user_id = $request->id;
        //get value,current value from super admin
        $template_value = SuperAdmin::select('value','current_value')->where('property','max_templates')->first();
        if($template_value['value'] == null || $template_value['value'] == 0 || (int)$template_value['current_value'] < (int)$template_value['value']){
            
            return response()->json(['success']);
        }
        else{
            return response()->json(['limit exceed']);
        }   
    }
    //check template is mapped or not during pdf generate
    public function checktemplatemapped(Request $request){
        $template_id = $request->template_id;

        $get_template_fields = FieldMaster::select('is_mapped')->where('template_id',$template_id)->get()->toArray();
        if ($get_template_fields[0]['is_mapped'] == 'excel' || $get_template_fields[0]['is_mapped'] == 'database'  ) {
            return json_encode($get_template_fields[0]);
        }
        else
        {
            $get_template_fields = array('type'=> 'error', 'message' => 'Cant generate pdf from unmapped template!');
            return json_encode($get_template_fields);
        }
    }
    //only for excel validation of extension and size
    public function excelvalidation(ExcelValidationRequest $request){
        
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
    //excel process
    public function excelcheck(Request $request){
        $template_id = $request->id;
        //get template name from template master from template id
        $template_name = TemplateMaster::select('template_name')->where('id',$template_id)->first();
        //get template name count from student table
        $counts = StudentTable::select('id')->where('template_id',$template_id)->count();

        if($counts < 1){
            $msg = array('type'=> 'counts', 'message' => 'no excel history found');
            return json_encode($msg);
        }else{
            $msg = array('type'=> 'duplicate', 'message' => 'excel history founded','old_rows'=>$counts);
                return json_encode($msg);
        }
    }

    //upload excel for genrate certificate
    public function uploadfile(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

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
        $field_data = FieldMaster::where('template_id',$template_id)->orderBy('field_position')->get()->toArray();

        $check_mapped = array();

        $isPreview = false;

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
        }
        //check file is uploaded or not
        if($request->hasFile('field_file')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'/backend/canvas/dummy_images/'.$template_data['template_name'];
            $fullpath = $target_path.'/'.$excelfile;

            if($request['field_file']->move($target_path,$excelfile)){
                //get excel file data
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel = $objReader->load($fullpath);
                $sheet = $objPHPExcel->getSheet(0);
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestDataRow();

                $rowData = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE);
                
                $fields = array();
                $mapped_excel_col_unique_serial_no = '';

                foreach ($rowData[0] as $key => $f) {
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
                if($FID['printing_type'] == 'pdfGenerate'){
                    if($FID['background_template_status'] == 0){
                        $bg_template_width = $FID['template_width'];
                        $bg_template_height = $FID['template_height'];
                    }
                    else
                    {
                        $get_bg_template_data = BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();
                        if($get_file_aws_local_flag->file_aws_local == '1'){
                            $bg_template_img = \Config::get('constant.aws_path').'/backend/bg_images/' . $get_bg_template_data['image_path'];
                        }
                        else{
                            $bg_template_img = \Config::get('constant.server_path').'/backend/bg_images/' . $get_bg_template_data['image_path'];
                        }
                        $bg_template_width = $get_bg_template_data['width'];
                        $bg_template_height = $get_bg_template_data['height'];
                    }
                }
                else
                {
                    $get_bg_template_data = BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $bg_template_img = \Config::get('constant.aws_path').'/backend/bg_images/' . $get_bg_template_data['image_path'];
                    }
                    else{
                       $bg_template_img = \Config::get('constant.server_path').'/backend/bg_images/' . $get_bg_template_data['image_path'];
                    }
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
        $pdfBig = new TCPDF($Orientation, 'mm', array($template_Width, $template_height), true, 'UTF-8', false);
        $page_width = $bg_template_width;

        $pdfBig->SetCreator('TCPDF');
        $pdfBig->SetAuthor('TCPDF');
        $pdfBig->SetTitle('Certificate');
        $pdfBig->SetSubject('');

        // remove default header/footer
        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);

        // code for check map excel or database
        if(!$isPreview)
            if ($FID['is_mapped'][0] == 'excel') {
                $sheet = $objPHPExcel->getSheet(0);
            }

        // Load fonts 
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

                            $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_N'];
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
                            $font_filename = public_path().'/backend/canvas/fonts/' . $get_font_data['font_filename_I'];
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
                            $font_filename = public_path().'/backend/canvas/fonts/' . $get_font_data['font_filename_N'];
                            if(!file_exists($font_filename))
                            {
                                $tmp_name = public_path().'/'.$subdomain[0].'/backend/canvas/dummy_images/'.$FID['template_name'].'/'.$excelfile;
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
        if(!$isPreview) {
            if ($FID['is_mapped'][0] == 'excel') {
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestDataRow();
            }else
            {
                $highestRow = count($match_array)+1;
            }
        }
        else
        {
            $highestRow = 2;
        }

        $FID['map_type'] = '';
        
        if(!$isPreview && !isset($request->flag)) {

            $pdfBig->SetProtection(array('modify', 'copy','annot-forms','fill-forms','extract','assemble'),'',null,0,null);

            //check if value come from formula or not
            if($FID['map_type'] != 'database'){ 
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
                    $tmp_name = public_path().'backend/canvas/dummy_images/'.$FID['template_name'].'/'.$excelfile;
                    if (file_exists($tmp_name)) {
                        unlink($tmp_name);
                    }
                    $message = array('type'=> 'formula', 'message' => 'Please remove formula from column','cell'=>$formula);
                    return response()->json(['success'=>false,'message'=>$message,'flag'=>0]);
                }
            }
        }

        
        $bg_template_img_generate = '';

        if(isset($FID['bg_template_id']) && $FID['bg_template_id'] != '') {
            if($FID['bg_template_id'] == 0) {
                $bg_template_img_generate = '';
                $bg_template_width_generate = $FID['template_width'];
                $bg_template_height_generate = $FID['template_height'];
            } else {
                $get_bg_template_data =  BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    $bg_template_img_generate = \Config::get('constant.aws_path').'/backend/bg_images/' . $get_bg_template_data['image_path'];
                }
                else{
                    $bg_template_img_generate = \Config::get('constant.server_path').'/backend/bg_images/' . $get_bg_template_data['image_path'];
                }
                $bg_template_width_generate = $get_bg_template_data['width'];
                $bg_template_height_generate = $get_bg_template_data['height'];
            }
        } else {
            $bg_template_img_generate = '';
            $bg_template_width_generate = 210;
            $bg_template_height_generate = 297;
        }


        //store ghost image
        $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();
        $pdf_data = ['isPreview'=>$isPreview,'highestRow'=>$highestRow,'highestColumn'=>$highestColumn,'FID'=>$FID,'Orientation'=>$Orientation,'template_Width'=>$template_Width,'template_height'=>$template_height,'bg_template_img_generate'=>$bg_template_img_generate,'bg_template_width_generate'=>$bg_template_width_generate,'bg_template_height_generate'=>$bg_template_height_generate,'bg_template_img'=>$bg_template_img,'bg_template_width'=>$bg_template_width,'bg_template_height'=>$bg_template_height,'mapped_excel_col_unique_serial_no'=>$mapped_excel_col_unique_serial_no,'ext'=>$ext,'fullpath'=>$fullpath,'tmpDir'=>$tmpDir,'excelfile'=>$excelfile,'timezone'=>$timezone,'printer_name'=>$printer_name,'style2D'=>$style2D,'style1D'=>$style1D,'style1Da'=>$style1Da,'fonts_array'=>$fonts_array,'font_name'=>$font_name,'admin_id'=>$admin_id];


        //pdf generation process
        $response = $this->dispatch(new PDFGenerateJob($pdf_data));

        return response()->json(['success'=>$response['success'],'msg'=>$response['msg'],'status'=>$response['status']]);
    }

    //create ghost image folder
    public function createTemp($path){
        $tmp = date("ymdHis");
        $tmpname = tempnam($path, $tmp);
        unlink($tmpname);
        mkdir($tmpname);
        return $tmpname;
    }

    //store template data in variable
    public function saveforpreview(Request $request){
        $template_data = $request->all();
        $FID = array();

        // Background template
        if(isset($template_data['bg_template_id']) && $template_data['bg_template_id'] != '')
            $FID['bg_template_id'] = $template_data['bg_template_id'];
        if(isset($template_data['template_width']) && $template_data['template_width'] != '')
            $FID['template_width'] = $template_data['template_width'];
        if(isset($template_data['template_height']) && $template_data['template_height'] != '')
            $FID['template_height'] = $template_data['template_height'];
        if(isset($template_data['template_name']) && $template_data['template_name'] != '')
            $FID['template_name'] = $template_data['template_name'];
        // QR Code
        if(isset($template_data['field_qr_sample_text']) && $template_data['field_qr_sample_text'] != '')
            $FID['data'][] = $template_data['field_qr_sample_text'];
        else
            $FID['data'][] = 'Hello text';

        $FID['template_width']  = $template_data['width'];
        $FID['template_height'] = $template_data['height'];

        $FID['id'][] = '';
        $FID['template_id'][] = $template_data['template_id'];
        $FID['mapped_name'][] = '';
        $FID['mapped_excel_col'][] = '';
        $FID['security_type'][] = 'QR Code';
        $FID['field_position'][] = '1';
        $FID['text_justification'][] = '';
        $FID['x_pos'][] = $template_data['field_qr_x'];
        $FID['y_pos'][] = $template_data['field_qr_y'];
        $FID['width'][] = $template_data['field_qr_width'];
        $FID['height'][] = $template_data['field_qr_height'];
        $FID['font_style'][] = '';
        $FID['font_id'][] = '';
        $FID['font_size'][] = '';
        $FID['font_color'][] = '0';
        $FID['sample_image'][] = $template_data['field_qr_image'];
        $FID['include_image'][] = $template_data['field_qr_image_chk'];
        $FID['field_image'][] = $template_data['field_qr_image'];
        $FID['line_gap'][] = '0';
        // ID Barcode
        $FID['id'][] = '';
        $FID['template_id'][] = $template_data['template_id'];
        $FID['mapped_name'][] = '';
        $FID['mapped_excel_col'][] = '';
        $FID['data'][] = 'This is data';
        $FID['security_type'][] = 'ID Barcode';
        $FID['field_position'][] = '2';
        $FID['text_justification'][] = '';
        $FID['x_pos'][] = $template_data['field_id_x'];
        $FID['y_pos'][] = $template_data['field_id_y'];
        $FID['width'][] = $template_data['field_id_width'];
        $FID['height'][] = $template_data['field_id_height'];
        $FID['font_style'][] = '';
        $FID['font_id'][] = '';
        $FID['font_size'][] = '';
        $FID['font_color'][] = '0';
        $FID['include_image'][] = '0';
        $FID['field_image'][] = '';
        $FID['line_gap'][] = '0';

        if(isset($template_data['field_extra_name'])) 
        {
            $count = count($template_data['field_extra_name']);
            $field_extra_name = $template_data['field_extra_name'];
            $field_extra_security_type = $template_data['field_extra_security_type'];
            $field_extra_font_id = $template_data['field_extra_font_id'];
            $field_extra_font_style = $template_data['field_extra_font_style'];
            $field_extra_text_align = $template_data['field_extra_text_align'];
            $field_extra_font_size = $template_data['field_extra_font_size'];
            $field_extra_font_color = $template_data['field_extra_font_color'];
            $field_sample_text = $template_data['field_sample_text'];
            $field_extra_x = $template_data['field_extra_x'];
            $field_extra_y = $template_data['field_extra_y'];
            $field_extra_width = $template_data['field_extra_width'];
            $field_extra_height = $template_data['field_extra_height'];
            
            // Store Value in variable
            $field_sample_text_width = $template_data['field_sample_text_width'];
            $field_sample_text_vertical_width = $template_data['field_sample_text_vertical_width'];
            $field_sample_text_horizontal_width = $template_data['field_sample_text_horizontal_width'];
            
            $field_image = $template_data['field_image1'];
            
            $angle = $template_data['angle'];
            $font_color_extra = $template_data['font_color_extra'];
            $line_gap = $template_data['line_gap'];
            $length = $template_data['length'];
            $uv_percentage = $template_data['uv_percentage'];
            $is_repeat = $template_data['is_repeat'];
            $microline_width = $template_data['microline_width'];
            $infinite_height = $template_data['infinite_height'];
            $include_image = $template_data['include_image'];
            $grey_scale = $template_data['grey_scale'];
            $is_uv_image = $template_data['is_uv_image'];
           
            if(isset($template_data['is_mapped'])){
                $field_is_mapped = $template_data['is_mapped'];
            }
            for($i = 0; $i < $count; $i++)
            {
                $FID['id'][] = '';
                $FID['template_id'][] = $template_data['template_id'];
                $FID['mapped_name'][] = '';
                $FID['mapped_excel_col'][] = '';
                $FID['security_type'][] = $field_extra_security_type[$i];
                $FID['field_position'][] = $i + 2;
                $FID['text_justification'][] = $field_extra_text_align[$i];
                $FID['x_pos'][] = $field_extra_x[$i];
                $FID['y_pos'][] = $field_extra_y[$i];
                $FID['width'][] = $field_extra_width[$i];
                $FID['height'][] = $field_extra_height[$i];
                $FID['font_style'][] = $field_extra_font_style[$i];
                $FID['font_id'][] = $field_extra_font_id[$i];
                $FID['font_size'][] = $field_extra_font_size[$i];
                $FID['font_color'][] = $field_extra_font_color[$i];
                $FID['text_opacity'][] = $template_data['text_opicity'][$i];
                
                // Store Value in variable
                $FID['field_sample_text_width'][$i] = $field_sample_text_width[$i];
                $FID['field_sample_text_vertical_width'][] = $field_sample_text_vertical_width[$i];
                $FID['field_sample_text_horizontal_width'][] = $field_sample_text_horizontal_width[$i];
                
                $FID['field_image'][] = $field_image[$i];
                
                $FID['angle'][] = $angle[$i];
                $FID['font_color_extra'][] = $font_color_extra[$i];
                $FID['line_gap'][] = $line_gap[$i];
                $FID['length'][] = $length[$i];
                $FID['uv_percentage'][] = $uv_percentage[$i];
                $FID['is_repeat'][] = $is_repeat[$i];
                $FID['microline_width'][] = $microline_width[$i];
                $FID['infinite_height'][] = $infinite_height[$i];
                $FID['include_image'][] = $include_image[$i];
                $FID['grey_scale'][] = $grey_scale[$i];
                $FID['is_uv_image'][] = $is_uv_image[$i];
                if(isset($template_data['field_is_mapped'][$i])){
                    $FID['is_mapped'][] = $field_is_mapped[$i];
                }

                if($field_sample_text[$i] != '')
                    $FID['data'][] = $field_sample_text[$i];
                else
                    $FID['data'][] = $field_extra_name[$i];
            }
        }
        Session::put('template_data', $FID);
    }

    //preview pdf
    public function previewpdf(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $FID = Session::get('template_data');
      
        $isPreview = true;

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

        // Load fonts 
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
                            $font_filename = public_path().'/'.$subdomain[0].'/backend/canvas/fonts/' . $get_font_data['font_filename_N'];
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
                    $name = explode('fonts/',$font_filename);
                    // echo $font_filename;
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
                        if(!file_exists($font_filename) || empty($name[1]))
                        {
                            // if other styles are not present then load normal file
                            $font_filename = public_path().'/backend/canvas/fonts/' . $get_font_data['font_filename_N'];
                            
                            $fonts_array[$font_key] = \TCPDF_FONTS::addTTFfont($font_filename, 'TrueTypeUnicode', '', false);
                            
                            if(!file_exists($font_filename))
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
                    
                    if(!empty($name[1])){
                        $fonts_array[$font_key] = \TCPDF_FONTS::addTTFfont($font_filename, 'TrueTypeUnicode', '', false);
                    }
                    
                } catch (PDOExcelption $e) {

                    $tmp_name = public_path().'/backend/canvas/dummy_images/'.$FID['template_name'].'/'.$excelfile;
                    if (file_exists($tmp_name)) {
                        unlink($tmp_name);
                    }
                    $message = 'font file "' . $font_filename . '" not found';
                    return response()->json(['success'=>false,'message'=>$message]);       
                }
            }
        }
        //store ghost image
        $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();

        // Get Background Template image
        $bg_template_img = '';

        if(isset($FID['bg_template_id']) && $FID['bg_template_id'] != '') {
            if($FID['bg_template_id'] == 0) {
                $bg_template_width = $FID['template_width'];
                $bg_template_height = $FID['template_height'];
            } 
            else {
                $get_bg_template_data = BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    $bg_template_img = \Config::get('constant.aws_path').'/backend/bg_images/' . $get_bg_template_data['image_path'];
                }
                else{
                    $bg_template_img = \Config::get('constant.server_path').'/backend/bg_images/' . $get_bg_template_data['image_path'];
                }
                $bg_template_width = $get_bg_template_data['width'];
                $bg_template_height = $get_bg_template_data['height'];
                
            }
        } 
        else 
        {
            $bg_template_img = '';
            $bg_template_width = 210;
            $bg_template_height = 297;
        }
        $this->dispatch(new PreviewPDFGenerateJob($isPreview,2,'',$FID,'','','','','','',$bg_template_img,$bg_template_width,$bg_template_height,'','','',$tmpDir,'','','',$style2D,$style1D,$style1Da,$fonts_array,$font_name,$admin_id));
    }

    public function templateMap($id){
        $template_data = TemplateMaster::find($id);
        
        $fields = FieldMaster::where('template_id',$id)->get()->toArray();
        
        return view('admin.templateMaster.template_map',compact('template_data','fields'));
    }
    // get Excel columns
    public function uploadMapFile(Request $request){
      
       if($request->hasFile('field_file'))
       {
          $columns = \Excel::toArray(new TemplateMapImport, $request->field_file);
           
          $columns=head($columns);
          $columns=$columns[0];
          // array remove null value
          $columns=array_filter($columns);
          return response()->json(['success'=>true,'fields'=>$columns]);
       }  

    }
    // this function uploadMapColumns 
    public function uploadMapColumns(Request $request)
    {     
        $databse_detail=$request->session()->get('databse_detail');
        $is_mapped=$request['is_mapped'];
        $field_value=$request['f_value'];
        $f_id=$request['f_id'];
        $excel_serial_no=$request['excel_serial_no'];
        $id=$request['id'];
        // check array value can not empty
        foreach ($field_value as $key => $value) {
            if(empty($value))
            { 
             return response()->json(['error'=>"mapped fields can't be empty!"]);
            }
        }
       // two array are combine first array $f_id is and second array 
       $field_value=array_combine($f_id, $field_value);
    
       // update Unique_serial_no 
       $TemplateMaster=TemplateMaster::where('id',$id)
                         ->update(['Unique_serial_no'=>$excel_serial_no]);
  
        // update mapped_name columns
        foreach ($field_value as $key => $value) {
                    
             $new_data=FieldMaster::where('id',$key)     
                            ->update(['mapped_name'=>$value,'is_mapped'=>$is_mapped]);        
            }
      
        if($databse_detail[0]['is_mapped']==$is_mapped)
        {
            $detail=DbDetail::firstOrNew(['template_id'=>$id]);
            $detail->db_name=$databse_detail[0]['db_name'];
            $detail->db_host_address=$databse_detail[0]['host_address'];
            $detail->username=$databse_detail[0]['username'];
            $detail->password=$databse_detail[0]['password'];
            $detail->port=$databse_detail[0]['port'];
            $detail->table_name=$databse_detail[0]['table_name'];
            $detail->template_id=$id;
            $detail->save(); 

            $request->session()->forget('databse_detail');
        }

        return response()->json(['success'=>true]); 
    
    }
     // map columns from databse
    public function mapFromDatabase(MappingDatabaseRequest $request)
      {
        
        $databse_detail=[
           'is_mapped'=>'database', 
           'table_name'=>$request['table_name'], 
           'db_name'=>$request['db_name'],
           'host_address'=>$request['host_address'],
           'username'=>$request['username'],
           'password'=>$request['password'],
           'port'=>$request['port'],
        ];
        
       try {

       config(['database.connections.mysql2'=>[

            'driver' => 'mysql',
            'url' => '',
            'host' => $databse_detail['host_address'],
            'port' => $databse_detail['port'],
            'database' =>$databse_detail['db_name'],
            'username' =>$databse_detail['username'],
            'password' =>$databse_detail['password'],
            'unix_socket' =>'',
            'charset' => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,

          ]
        ]); 
       $columns= DB::connection('mysql2')->getSchemaBuilder()->getColumnListing($databse_detail['table_name']);
       
       if(!empty($columns))
       {
         $request->session()->forget('databse_detail');
         $request->session()->push('databse_detail',$databse_detail);
         return response()->json(['success'=>true,'table_columns'=>$columns]);
       } 
       else
       {
         return response()->json(['table_error'=>"table doesn't exists"]);
       }

      }
   
    catch (\Exception $e)
      {
        return response()->json(['connection_error'=>'Unable to connect']);
      }
       
    }  
     // copy template
    public function copyTemplate(Request $request){
        
      if(!empty($request['template_id']))
       {
        
        $copyTemplate_data=TemplateMaster::where('id',$request['template_id'])
                           ->get()
                           ->first()
                           ->toArray();
         
        $copyTemplate_data['template_name']=$copyTemplate_data['template_name'].'-copy';

        $copyTemplate = new TemplateMaster();
        $copyTemplate->fill($copyTemplate_data);
        $copyTemplate->save();
        
        $copyTemplate=$copyTemplate->toArray();
        $NewTemplate_id=$copyTemplate['id'];

        $field_data=FieldMaster::where('template_id',$request['template_id'])
                              ->get()
                              ->toArray(); 
       
        foreach ($field_data as $key => $value) {
           $field_data[$key]['template_id']=$NewTemplate_id;

           $copyField_data=new FieldMaster();
           $copyField_data->fill($field_data[$key]);
           $copyField_data->save();
        }

        return response()->json(['success'=>true,'msg'=>'template copy successfully']);
      }

    }   

     public function demo(Request $request){
        dd(55);
    }
}
