<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\FieldMaster;
use App\models\TemplateMaster;
use App\models\BackgroundTemplateMaster;
use App\models\SystemConfig;
use App\models\FontMaster;
use App\models\DBDetails;
use TCPDF;
use PDF;
use App\Jobs\PDFGenerateDatabaseJob;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

class MappingTableController extends Controller
{
    public function index(Request $request,$template_id)
    {
    	$columns = [];
        $columns_array = FieldMaster::select('mapped_name')
                                    ->where('is_mapped','database')
            						->where('template_id',$template_id)
            						->get()
            						->toArray();

       	foreach ($columns_array as $key => $value) {
       		foreach ($value as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                else
                {
       			  array_push($columns,$value);
                }
       		}
       	}

        $db_details = DBDetails::where('template_id',$template_id)
                                    ->first();
        
        $this->args['db_details'] = $db_details;
        try 
        {       
            config(['database.connections.mysql2'=>
                [
                    'driver' => 'mysql',
                    'url' => '',
                    'host' => $db_details['db_host_address'],
                    'port' => $db_details['port'],
                    'database' =>$db_details['db_name'],
                    'username' =>$db_details['username'],
                    'password' => $db_details['password'],
                    'unix_socket' =>'',
                    'charset' => 'utf8',
                    'collation' => 'utf8_general_ci',
                    'prefix' => '',
                    'prefix_indexes' => true,
                    'strict' => false,
                    'engine' => null,
                ]   
            ]); 


            $table = \DB::connection('mysql2')->table($db_details["table_name"])
                                                ->get();
            

            if($request->ajax())
            {
                $data = $request->all();
                $where_str = '1 = ?';
                $where_params = [1];

                

                $table = \DB::connection('mysql2')->table($db_details["table_name"])
                                                ->whereRaw($where_str, $where_params)
                                                ->get();
            
                $mappedData_count = count($table); 

                if($request->has('iDisplayStart') && $request->get('iDisplayLength') !='-1'){
                    $table = $table->take($request->get('iDisplayLength'))->skip($request->get('iDisplayStart'));
                }

                if($request->has('iSortCol_0')){
                    for ( $i = 0; $i < $request->get('iSortingCols'); $i++ )
                    {
                        $column = $columns[$request->get('iSortCol_' . $i)];
                        
                        if (false !== ($index = strpos($column, ' as '))) {
                            $column = substr($column, 0, $index);
                        }
                        
                    }
                }

                
                
                $response['iTotalDisplayRecords'] = $mappedData_count;
                $response['iTotalRecords'] = $mappedData_count;

                $response['sEcho'] = intval($request->get('sEcho'));

                $response['aaData'] = $table;
                $this->args['response'] = $response; 

                return $response;
            }
            $this->args['template_id'] = $template_id;
            $this->args['columns'] = $columns;
            return view('admin.mappingTable.index',$this->args);

        } //try close
   
        catch (\Exception $e)
        {
            return response()->json(['connection_error'=>'Unable to connect']);
        }  
    }

    public function print(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {

        //check upload to aws/local (var(get_file_aws_local_flag) coming through provider)
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

    	$resp = $request->all();
    	$template_id = $resp['template_id'];

        $db_details = DBDetails::where('template_id',$template_id)
                                    ->first();
        $this->args['db_details'] = $db_details;

        $columns = [];
       	$mapTable = [];

        //get template data from id
        $template_data = TemplateMaster::select('id','unique_serial_no','template_name','bg_template_id','width','height','background_template_status')->where('id',$template_id)->first();
        //get background template data
        if($template_data['bg_template_id'] != 0){
            $bg_template_data = BackgroundTemplateMaster::select('width','height')
                                        ->where('id',$template_data['bg_template_id'])
                                        ->first();
        }

        // store all data in one variable
        $FID = array();
        $FID['template_id']  = $template_data['id'];
        $FID['unique_serial_no']  = $template_data['unique_serial_no'];
        $FID['bg_template_id']  = $template_data['bg_template_id'];
        $FID['template_width']  = $template_data['width'];
        $FID['template_height'] = $template_data['height'];
        $FID['template_name']  =  $template_data['template_name'];
        $FID['background_template_status']  =  $template_data['background_template_status'];
        $FID['printing_type'] = 'pdfGenerate';
        //get field data
        
        $field_data = [];
        
        $field_data = FieldMaster::where('template_id',$template_id)
                                        ->where('is_mapped','database')
                                        ->orderBy('field_position')->get()->toArray();
        
        $check_mapped = array();

        $isPreview = false;

        foreach ($field_data as $field_key => $field_value) 
        {
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
        
        foreach ($FID['mapped_name'] as $key => $value) 
        {
            array_push($columns,$value);
        }
        $data = [];
        
        $mapped_data[] = [];
        try 
        {       
            config(['database.connections.mysql2'=>
                [
                    'driver' => 'mysql',
                    'url' => '',
                    'host' => $db_details['db_host_address'],
                    'port' => $db_details['port'],
                    'database' =>$db_details['db_name'],
                    'username' =>$db_details['username'],
                    'password' => $db_details['password'],
                    'unix_socket' =>'',
                    'charset' => 'utf8',
                    'collation' => 'utf8_general_ci',
                    'prefix' => '',
                    'prefix_indexes' => true,
                    'strict' => false,
                    'engine' => null,
                ]   
            ]); 

            $columns = [];
            $columns_array = FieldMaster::select('mapped_name')
                                        ->where('is_mapped','database')
                                        ->where('template_id',$template_id)
                                        ->get()
                                        ->toArray();

            foreach ($columns_array as $key => $value) {
                foreach ($value as $key => $value) {
                    array_push($columns,$value);
                }
            }

            if ($resp['status']=='print_all') 
            {
                $table = \DB::connection('mysql2')->table($db_details["table_name"])
                                                ->get()->toArray();   
            }
            else
            {
                $table = \DB::connection('mysql2')->table($db_details["table_name"])
                                                ->whereIn('id',$resp['id'])
                                                ->get()->toArray();
            }

            $data[] = [];
            foreach ($table as $key => $value) 
            {
                foreach ($value as $key_value => $value_data) {
                    $data[$key][$key_value] = $value_data;
                }
            }

            foreach ($data as $table_data_key => $table_value) 
            {
                 foreach ($columns as $col_key => $column_value) 
                 {   
                     $mapped_col = in_array($column_value,$table_value);
                     if ($mapped_col == true) 
                     {
                        foreach ($table_value as $key => $value) 
                        {
                            //dd($table_value,$key,$value); 
                            if ($key == $column_value) {
                                $mapped_data[$table_data_key][$key] = $value; 
                            }                            
                        }
                    }
                }                
            }
            
        }
        
        catch (\Exception $e)
        {
            
            return response()->json(['connection_error'=>'Unable to connect']);
        }  

    
       $FID['data'] = $mapped_data;
       

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
                            $bg_template_img = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/bg_images/'.$get_bg_template_data['image_path'];
                        }
                        else{
                            $bg_template_img = \Config::get('constant.local_base_path').$subdomain[0].'/backend/canvas/bg_images/'.$get_bg_template_data['image_path'];
                        }

                        $bg_template_width = $get_bg_template_data['width'];
                        $bg_template_height = $get_bg_template_data['height'];
                    }
                }
                else
                {
                    $get_bg_template_data = BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();
                       
                    
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $bg_template_img = \Config::get('constant.amazone_path').$subdomain[0].'/backend/canvas/bg_images/'.$get_bg_template_data['image_path'];
                    }
                    else{
                        $bg_template_img = \Config::get('constant.local_base_path').$subdomain[0].'/backend/canvas/bg_images/'.$get_bg_template_data['image_path'];
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
        {
            $Orientation = 'L';
        }

        $fonts_array = array();
        $font_name = '';
        foreach ($FID['font_id'] as $font_key => $font_value) {
            if(($font_value != '' && $font_value != 'null' && !empty($font_value)) || ($font_value == '0'))
            {   
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
                            $font_filename = public_path().'/backend/fonts/' . $get_font_data['font_filename_N'];

                            $font_name[$font_key] = $get_font_data['font_filename'];;
                            
                            if(!file_exists($font_filename))
                            {
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
                    
                } catch (Exception $e) {

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
   

        $bg_template_img_generate = '';

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
                    $bg_template_img_generate = Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/'.$get_bg_template_data['image_path'];
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
        $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp');
        $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $this->dispatch(new PDFGenerateDatabaseJob($isPreview,$FID,$Orientation,$template_Width,$template_height,$bg_template_img_generate,$bg_template_width_generate,$bg_template_height_generate,$bg_template_img,$bg_template_width,$bg_template_height,$tmpDir,$timezone,$printer_name,$style2D,$style1D,$style1Da,$fonts_array,$font_name,$admin_id));

        return response()->json(['success'=>True,'message'=>'PDF is Send in Your Mail.']);
    }

    public function createTemp($path){
        //create ghost image folder
        $tmp = date("ymdHis");
        $tmpname = tempnam($path, $tmp);
        unlink($tmpname);
        mkdir($tmpname);
        return $tmpname;
    }
}
