<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Utility\ApiSecurityLayerThirdParty;
use App\models\SessionManager;
use App\models\TemplateMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\models\SystemConfig;
use App\models\SuperAdmin;
use DB,Event;
use App\models\StudentTable;
use App\models\FieldMaster;
use App\Jobs\PDFGenerateJob;
use App\models\BackgroundTemplateMaster;
use TCPDF,Session;
use App\models\FontMaster;
use Storage;
use App\models\Admin;
use Carbon\Carbon;
use App\Events\PDFGenerateEvent;
use App\models\SeqrdocRequests;
use App\models\ThirdPartyRequests;
use App\Jobs\ValidateExcelBMCCSOMJob;
use App\Jobs\PdfGenerateBMCCSOMJob;
use App\Jobs\ValidateExcelBMCCPassingJob;
use App\Jobs\PdfGenerateBMCCPassingJob;
use App\models\DemoERPData;
class SeqrdocThirdPartyController extends Controller
{
   
   public function login(Request $request){
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $request->all(); 

        if(ApiSecurityLayerThirdParty::checkAuthorization()){

            $rules = [
                'username' => 'required',
                'password' => 'required'
            ];

            $messages = [
                'username.required' => 'Please enter username.',
                'password.required' => 'Please enter password.',
            ];

            $validator = \Validator::make($request->all(),$rules,$messages);

            if ($validator->fails()) {
               
                  $message = array('status' => 400, 'message' => ApiSecurityLayerThirdParty::getMessage($validator->errors()));
                   return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }else{

            $credential=
            [
                "username"=>$request->username,
                "password"=>$request->password,
            ]; 
            
            if(Auth::guard('admin')->attempt($credential))
            {  
                $result = Auth::guard('admin')->user(); 
                $session_id = ApiSecurityLayerThirdParty::generateAccessToken();
                $login_time = date('Y-m-d H:i:s');
                $ip = $_SERVER['REMOTE_ADDR'];
                $id = $result['id'];
                //storing session details in db
                $sessionData = new SessionManager();
                $sessionData->user_id = $id;
                $sessionData->session_id = $session_id;
                $sessionData->login_time = $login_time;
                $sessionData->is_logged = 1;
                $sessionData->device_type = 'Third Party Web';
                $sessionData->ip = $ip;
                $sessionData->save();
             
               
                $userData = Auth::guard('admin')->user(); 
                $message = array('status'=>200,'message'=>'You have logged in successfully.','data'=>$userData);  
                 return ApiSecurityLayerThirdParty::apiResponse($message,array('accesstoken'=>$session_id),$requestUrl,$requestMethod,$requestParameter);
                
            }else
            {
                $message = array('status' => 403,'message'=>'You have entered wrong username & password.');  
                 return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            
            }
        }
        }else{
             $message = array('status' => 403, 'message' => 'Access forbidden.');
              return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
        }

        
      
    }

    public function getTemplatesList(Request $request){

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $request->all(); 
        if (ApiSecurityLayerThirdParty::checkAuthorization()){
            

            $rules = [
                'user_id' => 'required'
            ];

            $messages = [
                'user_id.required' => 'Please enter user id.',
            ];

            $validator = \Validator::make($request->all(),$rules,$messages);

            if ($validator->fails()) {
               
                  $message = array('status' => 400, 'message' => ApiSecurityLayerThirdParty::getMessage($validator->errors()));
                  return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }else{
            
            $user_id = $request['user_id'];
           
            if (!empty($user_id) && ApiSecurityLayerThirdParty::checkAccessTokenAdmin($user_id)) 
            {
                //get template data from id
                $templateData = TemplateMaster::select('template_name','id','status')->where('status',1)->get()->toArray();

                $customTemplateData=DB::select(DB::raw('SELECT id,template_name,status FROM `seqr_demo`.`custom_templates` WHERE domain ="'.$domain.'"'));
             
                if($customTemplateData){
                    foreach ($customTemplateData as $readData) {
                       $templateData[]=array('id'=>$readData->id,'template_name'=>$readData->template_name,'status'=>$readData->status);
                    }
                }


                 $message = array('status' => 200, 'message' => 'success','data'=>$templateData);
                 return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);   
            }else
            {
                $message = array('status' => 400, 'message' => 'User id is missing or You dont have access to this api.');
                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }
        }
        }else
        {
            $message = array('status' => 403, 'message' => 'Access forbidden.');
            return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
        }
       
    }

    public function getTemplatesColumns(Request $request){

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $request->all(); 
        if (ApiSecurityLayerThirdParty::checkAuthorization()){
            

            $rules = [
                'user_id' => 'required',
                'template_id' => 'required',
            ];

            $messages = [
                'user_id.required' => 'Please enter user id.',
                'template_id.required' => 'Please enter template id.',
            ];

            $validator = \Validator::make($request->all(),$rules,$messages);

            if ($validator->fails()) {
               
                  $message = array('status' => 400, 'message' => ApiSecurityLayerThirdParty::getMessage($validator->errors()));
                  return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }else{
            
            $user_id = $request['user_id'];
            $template_id = $request['template_id'];
            if (!empty($user_id) && ApiSecurityLayerThirdParty::checkAccessTokenAdmin($user_id)) 
            {
                //get template data from id
                $templateData = TemplateMaster::select('template_name','id','status')->where('status',1)->where('id',$template_id)->get();
                $customTemplateData=DB::select(DB::raw('SELECT id,template_name,status,columns FROM `seqr_demo`.`custom_templates` WHERE domain ="'.$domain.'" AND id="'.$template_id.'"'));
               
                if(isset($templateData[0])&&!empty($templateData[0])){
                
                    $templateColumns = FieldMaster::select('mapped_name')->where('template_id',$template_id)->get()->toArray();
                    $columns=array();
                    foreach ($templateColumns as $key => $value) {
                        if(!empty($value['mapped_name'])&&!in_array($value['mapped_name'], $columns)){
                            array_push($columns, $value['mapped_name']);
                        }
                        
                    }
                 
                }elseif (isset($customTemplateData[0])&&!empty($customTemplateData[0])) {
                    
                    $columns=json_decode($customTemplateData[0]->columns);
                    
                }else{
                    $message = array('status' => 400, 'message' => 'Please enter valid template id.');
                  return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                }

                


                 $message = array('status' => 200, 'message' => 'success','data'=>$columns);
                 return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);   
            }else
            {
                $message = array('status' => 400, 'message' => 'User id is missing or You dont have access to this api.');
                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }
        }
        }else
        {
            $message = array('status' => 403, 'message' => 'Access forbidden.');
            return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
        }
       
    }

    public function generateDocuments(Request $request){

       

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $request->all(); 

        $message =false;


        if (ApiSecurityLayerThirdParty::checkAuthorization()){    
            
            if(isset($request->inputSource)&&($request->inputSource=="Excel"||$request->inputSource=="JSON")){

            if($request->inputSource=="Excel"){

                $rules = [
                    'user_id' => 'required',
                    'template_id' => 'required',
                    'file' => 'required',
                    'generation_type' => 'required',
                    'call_back_url' => 'required',
                ];

                $messages = [
                    'user_id.required' => 'Please enter user id.',
                    'template_id.required' => 'Please enter template id.',
                    'file.required' => 'Please select excel file.',
                    'generation_type.required' => 'Please enter generation type.',
                    'call_back_url.required' => 'Please enter call_back_url.',

                ];

            }else{
                $rules = [
                    'user_id' => 'required',
                    'template_id' => 'required',
                    'jsonData' => 'required',
                    'generation_type' => 'required',
                    'call_back_url' => 'required',
                ];

                $messages = [
                    'user_id.required' => 'Please enter user id.',
                    'template_id.required' => 'Please enter template id.',
                    'jsonData.required' => 'Please enter json data.',
                    'generation_type.required' => 'Please enter generation type.',
                    'call_back_url.required' => 'Please enter call_back_url.',
                ];
            }    

            
            $validator = \Validator::make($request->all(),$rules,$messages);

            }else{
                 $message = array('status' => 400, 'message' => "Please select valid input source.");
                  return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }
            if ($validator->fails()) {
               
                  $message = array('status' => 400, 'message' => ApiSecurityLayerThirdParty::getMessage($validator->errors()));
                  return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }else{

            $user_id = $request['user_id'];
            $userData=false;
            if (!empty($user_id)) 
            {
                $userData=ApiSecurityLayerThirdParty::checkAccessTokenAdmin($user_id);

            }

            if($userData){
            
            $template_id=$request->template_id;

            if($template_id=='BMCCGC'||$template_id=='BMCCPC'){
            $customTemplateId=$template_id;
            $template_id=100;

            }else{
            $customTemplateId=0;
            }
            $site_id=$auth_site_id=$userData['site_id'];
            $get_file_aws_local_flag =SystemConfig::select('file_aws_local')->where('site_id',$site_id)->first();
            $admin_id = Admin::where('id',$user_id)->first();
            $inProcesssGenerationCounts = ThirdPartyRequests::select('id')->where('template_id',$template_id)->where('status','In Process')->count();

            if($inProcesssGenerationCounts>=5){
               $message = array('status'=>422, 'message' => 'We will not accept  more than 5 api generation request that all are in-progress.');
                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter); 
            }
  

            $studentTableCounts = StudentTable::select('id')->where('site_id',$site_id)->count();

            $superAdminUpdate = SuperAdmin::where('property','print_limit')
                                ->update(['current_value'=>$studentTableCounts]);
            //get value,current value from super admin
            $template_value = SuperAdmin::select('value','current_value')->where('property','print_limit')->first();
            if($template_value['value'] != null && $template_value['value'] != 0 && (int)$template_value['current_value'] >= (int)$template_value['value']){
                $message = array('status'=>422, 'message' => 'Your certificate generation limit exceeded!');
                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            } 

            if(empty($customTemplateId)){
                $templateCheck = TemplateMaster::where('id',$template_id)
                                ->first();
               
                if(!$templateCheck){
                    $message = array('status'=>422, 'message' => 'Please enter valid template id.');
                    return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                }

            #check template is mapped or not
            $get_template_fields = FieldMaster::select('id','is_mapped')->where('template_id',$template_id)->
                                                                where(function($q) use($template_id){
                                                                    $q->where('security_type','!=','ID Barcode')
                                                                    ->Where('security_type','!=','Static Text')
                                                                    ->Where('security_type','!=','Static Image');
                                                                })->orderBy('is_mapped', 'ASC')->
                                                                get()->toArray(); 
           
            if ($get_template_fields[0]['is_mapped'] != 'excel' && $get_template_fields[0]['is_mapped'] != 'database'  ) {
                $message = array('status'=>422, 'message' => 'Can\'t generate pdf from unmapped template!');
                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }
            
            }

            if($request->inputSource=="Excel"){
             #check file is valid or not
            
            if($request['file'] != 'undefined'&&$request['file'] != ''){
                $file_name = $request['file']->getClientOriginalName();
                $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                
                if($ext != 'xls' && $ext != 'xlsx'){    
                    $message = array('status'=>422,'message'=>'Please select valid excel sheet');
                    return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                }
                
                #Upload Excel file
                $excelfile =  date("YmdHis") . "_" . $file_name;
                $target_path = public_path().'/'.$subdomain[0].'/backend/templates/'.$template_id;
              
                $fullpath = $target_path.'/'.$excelfile;
                if($request['file']->move($target_path,$excelfile)){

                    if($ext == 'xlsx' || $ext == 'Xlsx'){
                        $inputFileType = 'Xlsx';
                    }
                    else{
                        $inputFileType = 'Xls';
                    }

                    switch ($customTemplateId) {
                        case 'BMCCGC':
                            $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                            /**  Load $inputFileName to a Spreadsheet Object  **/
                            $objPHPExcel1 = $objReader->load($fullpath);
                            $sheet1 = $objPHPExcel1->getSheet(0);
                            $highestColumn1 = $sheet1->getHighestColumn();
                            $highestRow1 = $sheet1->getHighestDataRow();
                            $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                           
                            
                            $objPHPExcel2 = $objReader->load($fullpath);
                            $sheet2 = $objPHPExcel2->getSheet(1);
                            $highestColumn2 = $sheet2->getHighestColumn();
                            $highestRow2 = $sheet2->getHighestDataRow();
                            $rowData2 = $sheet2->rangeToArray('A1:' . $highestColumn2 . $highestRow2, NULL, TRUE, FALSE);
                           
                           $excelData=array('rowData1'=>$rowData1,'rowData2'=>$rowData2,'auth_site_id'=>$auth_site_id);
                            $response = $this->dispatch(new ValidateExcelBMCCSOMJob($excelData));

                            $responseData =$response->getData();
                            
                            if($responseData->success){

                                $old_rows=$responseData->old_rows;
                                $new_rows=$responseData->new_rows;
                                if(((isset($request->OverWriteRepeat)&&$request->OverWriteRepeat!=1)||!isset($request->OverWriteRepeat))&&!empty($old_rows)){   
                                    $message = array('status'=>422,'message' => 'Excel history founded!','oldRows'=>$old_rows,'newRows'=>$new_rows);
                                    return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);

                                }

                                $pdf_data=$excelData;

                                
                            }else{
                               
                                $message = array('status'=>422,'message' => $responseData->message);
                                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                            }
                            break;
                        case 'BMCCPC':
                            $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                            /**  Load $inputFileName to a Spreadsheet Object  **/
                            $objPHPExcel1 = $objReader->load($fullpath);
                            $sheet1 = $objPHPExcel1->getSheet(0);
                            $highestColumn1 = $sheet1->getHighestColumn();
                            $highestRow1 = $sheet1->getHighestDataRow();
                            $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                            
                            $excelData=array('rowData1'=>$rowData1,'auth_site_id'=>$auth_site_id);
                            $response = $this->dispatch(new ValidateExcelBMCCPassingJob($excelData));

                            $responseData =$response->getData();
                           
                            if($responseData->success){
                                $old_rows=$responseData->old_rows;
                                $new_rows=$responseData->new_rows;
                                $pdf_data=$excelData;
                            }else{
                               
                                $message = array('status'=>422,'message' => $responseData->message);
                                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                            }
                        break;
                        default:
                            //For template maker excel 
                            $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                            $objPHPExcel = $objReader->load($fullpath);
                            $sheet = $objPHPExcel->getSheet(0);
                            $highestColumn = $sheet->getHighestColumn();
                            $highestRow = $sheet->getHighestDataRow();
                            $rowData = $sheet->rangeToArray('A1:' . $highestColumn . 1, NULL, TRUE, FALSE);

                            $rowData[0] = array_filter($rowData[0]);
                            $ab = array_count_values($rowData[0]);
                            break;
                    }


                    if(empty($customTemplateId)){
                 
                    

                    # Excel has more than 1 column having same name. i.e. <column name>
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
                        $message = array('status'=>422,'message' => 'Excel has more than 1 column having same name. i.e. : '.$duplicate_columns);
                        return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                    }
                  

                    # overwrite is not set
                    $TemplateUniqueColumn = TemplateMaster::select('unique_serial_no')->where('id',$template_id)->first();
                    
                     $unique_serial_no = $TemplateUniqueColumn['unique_serial_no'];
                    $getKey = '';
                   
                    foreach ($rowData[0] as $key => $value) {
                        
                        if($unique_serial_no == $value){

                            $getKey = $key;
                        }
                    }
                 
                    if(is_numeric($getKey)){

                    $rowData2 = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow, NULL, TRUE, FALSE);
                     
                    $rowData1 = $sheet->rangeToArray('A2:' . $highestColumn . $highestRow, NULL, TRUE, FALSE);
                   
                    $old_rows = 0;
                    $new_rows = 0;
                    foreach ($rowData1 as $key1 => $value1) {

                            if(empty($value1[$getKey])){
                                $message = array('status'=>422,'message' => 'Unique column is having empty value.');
                                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                            }
                        
                            $studentTableCounts = StudentTable::where('serial_no',$value1[$getKey])->count();
                            if($studentTableCounts > 0){
                                $old_rows += 1;
                            }else{
                                $new_rows += 1;
                            }
                         
                    }

                 if(((isset($request->OverWriteRepeat)&&$request->OverWriteRepeat!=1)||!isset($request->OverWriteRepeat))&&!empty($old_rows)){   
                    $message = array('status'=>422,'message' => 'Excel history founded!','oldRows'=>$old_rows,'newRows'=>$new_rows);
                    return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);

                }
                   
                }else{
                    $message = array('status'=>422,'message' => 'Excel do not contains template unique column.');
                    return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                }

                }//End custom template check
                    
                }else{
                     $message = array('status'=>422,'message'=>'Error while uploading file!');
                     return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                }
            }
            else{
                $message = array('status'=>422,'message'=>'Please upload file with .xls or .xlsx extension!');
                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }
            $jsonData="";
            }else{


                
                 $jsonData=$request->jsonData;
                  $jsonData=json_decode($jsonData);
               
                if(empty($jsonData)){
                    $message = array('status'=>422,'message' => 'Please enter valid JSON data.');
                                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                }
                if(empty($customTemplateId)){
                    $highestColumn = count($jsonData[0]);
                    $highestRow = count($jsonData);
                    $rowData =  $jsonData;
                    
                    $rowData[0] = array_filter($rowData[0]);
                    $ab = array_count_values($rowData[0]);

                    # Excel has more than 1 column having same name. i.e. <column name>
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
                        $message = array('status'=>422,'message' => 'Json data has more than 1 column having same name. i.e. : '.$duplicate_columns);
                        return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                    }
                  

                    # overwrite is not set
                    $TemplateUniqueColumn = TemplateMaster::select('unique_serial_no')->where('id',$template_id)->first();
                    
                      $unique_serial_no = $TemplateUniqueColumn['unique_serial_no'];
                    $getKey = '';
                    foreach ($rowData[0] as $key => $value) {
                        
                        if($unique_serial_no == $value){

                            $getKey = $key;
                        }
                    }
              
                    if(is_numeric($getKey)){

                
                    $jsonData2=$jsonData;
                    unset($jsonData2[0]);
                   $rowData1=$jsonData2;
                   
                    $old_rows = 0;
                    $new_rows = 0;
                    foreach ($rowData1 as $key1 => $value1) {

                            if(empty($value1[$getKey])){
                                $message = array('status'=>422,'message' => 'Unique column is having empty value.');
                                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                            }
                            $studentTableCounts = StudentTable::where('serial_no',$value1[$getKey])->count();
                            if($studentTableCounts > 0){
                                $old_rows += 1;
                            }else{
                                $new_rows += 1;
                            }
                         
                    }

                 if(((isset($request->OverWriteRepeat)&&$request->OverWriteRepeat!=1)||!isset($request->OverWriteRepeat))&&!empty($old_rows)){   
                    $message = array('status'=>422,'message' => 'Json data history founded!','oldRows'=>$old_rows,'newRows'=>$new_rows);
                    return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);

                }
                }else{
                    $message = array('status'=>422,'message' => 'Json data do not contains template unique column.');
                    return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                }
                }else{


                    switch ($customTemplateId) {
                        case 'BMCCGC':

                            if(!isset($jsonData->studentData)||!isset($jsonData->subjects)){
                                $message = array('status'=>422,'message' => 'Please enter valid JSON data.');
                                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                            }

                            $excelData=array('rowData1'=>$jsonData->studentData,'rowData2'=>$jsonData->subjects,'auth_site_id'=>$auth_site_id);
                            $response = $this->dispatch(new ValidateExcelBMCCSOMJob($excelData));

                            $responseData =$response->getData();
                            if($responseData->success){

                                $old_rows=$responseData->old_rows;
                                $new_rows=$responseData->new_rows;
                                if(((isset($request->OverWriteRepeat)&&$request->OverWriteRepeat!=1)||!isset($request->OverWriteRepeat))&&!empty($old_rows)){   
                                    $message = array('status'=>422,'message' => 'Excel history founded!','oldRows'=>$old_rows,'newRows'=>$new_rows);
                                    return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);

                                }

                                $pdf_data=$excelData;

                                
                            }else{
                                $message = array('status'=>422,'message' => $responseData->message);
                                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                            }
                            break;

                        case 'BMCCPC':
                             $excelData=array('rowData1'=>$jsonData,'auth_site_id'=>$auth_site_id);
                            $response = $this->dispatch(new ValidateExcelBMCCPassingJob($excelData));
                            $responseData =$response->getData();
                           
                            if($responseData->success){
                                $old_rows=$responseData->old_rows;
                                $new_rows=$responseData->new_rows;
                                $pdf_data=$excelData;
                            }else{
                                $message = array('status'=>422,'message' => $responseData->message);
                                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                            }
                            break;
                        
                        default:
                           $message = array('status'=>422,'message' => 'Please enter correct template id.');
                    return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                            break;
                    }
                }
            }


            //Store data in database custom template
            if(!empty($customTemplateId)){

                    
            
            if($request->inputSource!="Excel"){
             $excelfile="";             
             $jsonfile =  date("YmdHis") . "_json_data.json";
             $target_path = public_path().'/'.$subdomain[0].'/backend/templates/'.$template_id; 
             $fullpathJson = $target_path.'/'.$jsonfile;
             $fp = fopen($fullpathJson,"wb");
             fwrite($fp,$request->jsonData);
             fclose($fp); 
             
            }
            #Add third party request in table
            $insertRequest = new ThirdPartyRequests();
            $insertRequest->session_manager_id=$userData['id'];
            $insertRequest->template_id=$template_id;
            if($request->inputSource=="Excel"){
            $insertRequest->excel_file=$excelfile;
            }else{
             $insertRequest->excel_file=$jsonfile;   
            }
           
            $insertRequest->generation_type=$request->generation_type;
            $insertRequest->ip_address=$_SERVER['REMOTE_ADDR'];
            $insertRequest->status='In Process';
            $insertRequest->total_documents=$old_rows+$new_rows;
            $insertRequest->generated_documents=0;
            $insertRequest->regenerated_documents=$old_rows;
            $insertRequest->input_source=$request->inputSource;
            $insertRequest->call_back_url=$request->call_back_url;
            $insertRequest->created_at=date('Y-m-d H:i:s');
            $insertRequest->save();
            
            $insertRequest=$insertRequest->toArray();
            $request_id=$insertRequest['id'];
            
            if($request->inputSource=="Excel"){
                $excelUrl='http://'.$domain.'/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$excelfile;
            }else{
                $excelUrl='http://'.$domain.'/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$jsonfile;
                $ext ="";
                $fullpath ="";
            }

            $dataArr=array("request_id"=>$request_id,"template_id"=>$template_id,"excel_file"=>$excelUrl,"generation_type"=>$request->generation_type,"status"=>"In Process","total_documents"=>$old_rows+$new_rows);

            $updated=date('Y-m-d H:i:s');
            $message = array('status' => 200, 'message' => 'Generation started.',"data"=>$dataArr);
            ThirdPartyRequests::where('id',$request_id)->update(["updated_at"=>$updated,"response"=>json_encode($message)]);

             #Add third party request in table
            $insertRequestSeqr = new SeqrdocRequests();
            $insertRequestSeqr->request_id=$request_id;
            $insertRequestSeqr->template_id=$template_id;

            $insertRequestSeqr->excel_file=$excelUrl;
            $insertRequestSeqr->generation_type=$request->generation_type;
            $insertRequestSeqr->status='In Process';
            $insertRequestSeqr->total_documents=$old_rows+$new_rows;
            $insertRequestSeqr->generated_documents=0;
            $insertRequestSeqr->regenerated_documents=$old_rows;
            $insertRequestSeqr->response=json_encode($message);
            $insertRequestSeqr->call_back_url=$request->call_back_url;
            $insertRequestSeqr->input_source=$request->inputSource;
            $insertRequestSeqr->created_at=date('Y-m-d H:i:s');
            $insertRequestSeqr->save();


                //Sending backend request

                $headers = apache_request_headers();
                
                if (isset($headers['accesstoken'])) {
                    if (!empty($headers['accesstoken'])) {
                        $accessToken = $headers['accesstoken'];
                    }
                }

                if (isset($headers['Accesstoken']) && empty($accessToken)) {
                    if (!empty($headers['Accesstoken'])) {
                        $accessToken = $headers['Accesstoken'];

                    }
                }

                $pdf_data['customTemplateId']=$customTemplateId;
                $pdf_data['template_id']=$template_id;
                $pdf_data['generation_from']='API';
                $pdf_data['template_id']=$template_id;
                $pdf_data['request_id']=$request_id;
                $pdf_data['auth_site_id']=$auth_site_id;
                $pdf_data['excelfile']=$excelfile;
                $pdf_data['admin_id']=$admin_id;
                $pdf_data['call_back_url']=$request->call_back_url;
                
                if($request->generation_type=='Live'){
                    $pdf_data['previewPdf'] = array(0,1);
                }else{
                    $pdf_data['previewPdf'] = array(1,0);
                }

                
                $reaquestParameters = array
                (
                    'user_id'=>$user_id,
                    'pdf_data' => $pdf_data,
                );

                $headers = array
                    (
                    'Authorization: key=SEQRDOC',
                    'Content-Type: application/json',
                    'apikey: '.apiKey,
                    'accesstoken: '.$accessToken,
                );
                
                $url = "http://".$domain."/api/seqrdoc-process-docs-custom";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reaquestParameters));
            
                $result = curl_exec($ch);
            
                curl_close($ch);

                 return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                    

        }


            
            //get template data from id
            $template_data = TemplateMaster::select('id','unique_serial_no','template_name','bg_template_id','width','height','background_template_status','actual_template_name')->where('id',$template_id)->first();
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
            $FID['actual_template_name']  =  $template_data['actual_template_name'];
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

                        $message = array('status' => 400, 'message' => 'Can\'t generate pdf from unmapped template!');
                        return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
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
                $FID['print_type'] = 'pdf';
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
                
                $diff_name = implode(", ", $diff);
              
                $message = array('status' => 422, 'message' => 'Following mapping columns are missing : '.$diff_name);
                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);

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
                                $bg_template_img = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/'. $get_bg_template_data['image_path'];
                            }
                            else{
                                $bg_template_img = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/'.$get_bg_template_data['image_path'];
                            }
                            $bg_template_width = $get_bg_template_data['width'];
                            $bg_template_height = $get_bg_template_data['height'];
                        }
                    }
                    else
                    {
                     
                        $get_bg_template_data = BackgroundTemplateMaster::select('image_path','width','height')->where('id',$FID['bg_template_id'])->first();
                        if($get_file_aws_local_flag->file_aws_local == '1'){ 
                            $bg_template_img = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/'.$get_bg_template_data['image_path'];
                        }
                        else{
                            $bg_template_img = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.bg_images').'/'.$get_bg_template_data['image_path'];
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
                if ($FID['is_mapped'][0] == 'excel'&&$request->inputSource=="Excel") {
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

                    $highestColumn = $highestColumn;
                    $highestRow = $highestRow;
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
                if($FID['map_type'] != 'database'&&$request->inputSource=="Excel"){ 
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


            //store ghost image
            $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp/temp');

            //pdf generation process
            $excel_row_num = 2;
            $pdf_flag = 0;
            if(isset($request->is_progress)){
                $excel_row_num = $request->excel_row;
                $pdf_flag = 1;
            }
            

            if($request->inputSource!="Excel"){
            $excelfile="";

             
             $jsonfile =  date("YmdHis") . "_json_data.json";
             $target_path = public_path().'/'.$subdomain[0].'/backend/templates/'.$template_id; 
             $fullpathJson = $target_path.'/'.$jsonfile;
             $fp = fopen($fullpathJson,"wb");
             fwrite($fp,$request->jsonData);
             fclose($fp); 
             
            }
            #Add third party request in table
            $insertRequest = new ThirdPartyRequests();
            $insertRequest->session_manager_id=$userData['id'];
            $insertRequest->template_id=$template_id;
            if($request->inputSource=="Excel"){
            $insertRequest->excel_file=$excelfile;
            }else{
             $insertRequest->excel_file=$jsonfile;   
            }
           
            $insertRequest->generation_type=$request->generation_type;
            $insertRequest->ip_address=$_SERVER['REMOTE_ADDR'];
            $insertRequest->status='In Process';
            $insertRequest->total_documents=$old_rows+$new_rows;
            $insertRequest->generated_documents=0;
            $insertRequest->regenerated_documents=$old_rows;
            $insertRequest->input_source=$request->inputSource;
            $insertRequest->call_back_url=$request->call_back_url;
            $insertRequest->created_at=date('Y-m-d H:i:s');
            $insertRequest->save();
            
            $insertRequest=$insertRequest->toArray();
            $request_id=$insertRequest['id'];
            
            if($request->inputSource=="Excel"){
                $excelUrl='http://'.$domain.'/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$excelfile;
            }else{
                $excelUrl='http://'.$domain.'/'.$subdomain[0].'/backend/templates/'.$template_id.'/'.$jsonfile;
                $ext ="";
                $fullpath ="";
            }

            $dataArr=array("request_id"=>$request_id,"template_id"=>$template_id,"excel_file"=>$excelUrl,"generation_type"=>$request->generation_type,"status"=>"In Process","total_documents"=>$old_rows+$new_rows);
            
            $updated=date('Y-m-d H:i:s');
            $message = array('status' => 200, 'message' => 'Generation started.',"data"=>$dataArr);
            ThirdPartyRequests::where('id',$request_id)->update(["updated_at"=>$updated,"response"=>json_encode($message)]);

             #Add third party request in table
            $insertRequestSeqr = new SeqrdocRequests();
            $insertRequestSeqr->request_id=$request_id;
            $insertRequestSeqr->template_id=$template_id;

            $insertRequestSeqr->excel_file=$excelUrl;
            $insertRequestSeqr->generation_type=$request->generation_type;
            $insertRequestSeqr->status='In Process';
            $insertRequestSeqr->total_documents=$old_rows+$new_rows;
            $insertRequestSeqr->generated_documents=0;
            $insertRequestSeqr->regenerated_documents=$old_rows;
            $insertRequestSeqr->response=json_encode($message);
            $insertRequestSeqr->call_back_url=$request->call_back_url;
            $insertRequestSeqr->input_source=$request->inputSource;
            $insertRequestSeqr->created_at=date('Y-m-d H:i:s');
            $insertRequestSeqr->save();
        
            
                 $pdf_data = ['isPreview'=>$isPreview,'highestRow'=>$highestRow,'highestColumn'=>$highestColumn,'FID'=>$FID,'Orientation'=>$Orientation,'template_Width'=>$template_Width,'template_height'=>$template_height,'bg_template_img_generate'=>$bg_template_img_generate,'bg_template_width_generate'=>$bg_template_width_generate,'bg_template_height_generate'=>$bg_template_height_generate,'bg_template_img'=>$bg_template_img,'bg_template_width'=>$bg_template_width,'bg_template_height'=>$bg_template_height,'mapped_excel_col_unique_serial_no'=>$mapped_excel_col_unique_serial_no,'ext'=>$ext,'fullpath'=>$fullpath,'tmpDir'=>$tmpDir,'excelfile'=>$excelfile,'timezone'=>$timezone,'printer_name'=>$printer_name,'style2D'=>$style2D,'style1D'=>$style1D,'style1Da'=>$style1Da,'fonts_array'=>$fonts_array,'font_name'=>$font_name,'admin_id'=>$admin_id,'excel_row_num'=>$excel_row_num,'pdf_flag'=>$pdf_flag,'generation_from'=>'API','request_id'=>$request_id,'generation_type'=>$request['generation_type'],"inputSource"=>$request->inputSource,"jsonData"=>$jsonData,"call_back_url"=>$request->call_back_url];
           

                 //Sending backend request

                $headers = apache_request_headers();
                
                if (isset($headers['accesstoken'])) {
                    if (!empty($headers['accesstoken'])) {
                        $accessToken = $headers['accesstoken'];
                    }
                }

                if (isset($headers['Accesstoken']) && empty($accessToken)) {
                    if (!empty($headers['Accesstoken'])) {
                        $accessToken = $headers['Accesstoken'];

                    }
                }

                $reaquestParameters = array
                (
                    'user_id'=>$user_id,
                    'pdf_data' => $pdf_data,
                );

                $headers = array
                    (
                    'Authorization: key=SEQRDOC',
                    'Content-Type: application/json',
                    'apikey: '.apiKey,
                    'accesstoken: '.$accessToken,
                );

                $url = "http://".$domain."/api/seqrdoc-process-docs";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reaquestParameters));
              
                $result = curl_exec($ch);
            
                curl_close($ch);



                 return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            
             return "success";       
            }else
            {
                $message = array('status' => 400, 'message' => 'User id is missing or You dont have access to this api.');
                return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }
            
        }
        }else
        {
            $message = array('status' => 403, 'message' => 'Access forbidden.');
            return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
        }
       
    }

    public function processDocuments(){


        if (ApiSecurityLayerThirdParty::checkAuthorization()){
       $json = file_get_contents('php://input');
       $request=json_decode($json);
       $array = json_decode(json_encode($request), true);
       
       
       if(ApiSecurityLayerThirdParty::checkAccessTokenAdmin($array['user_id'])){

        $pdf_data=$array['pdf_data'];
        $response = $this->dispatch(new PDFGenerateJob($pdf_data));
        
        return $message = array('status' => 200, 'message' => 'success',"data"=>$response);
        }else
        {
            return $message = array('status' => 403, 'message' => 'Access forbidden.');
        }
        }else
        {
            return $message = array('status' => 403, 'message' => 'Access forbidden.');
        }
    }

    public function processDocumentsCustomTemplates(){

        
        if (ApiSecurityLayerThirdParty::checkAuthorization()){
       $json = file_get_contents('php://input');
       $request=json_decode($json);
       $array = json_decode(json_encode($request), true);
       
       
       if(ApiSecurityLayerThirdParty::checkAccessTokenAdmin($array['user_id'])){

        $pdf_data=$array['pdf_data'];

        switch ($pdf_data['customTemplateId']) {
            case 'BMCCGC':
            $studentDataOrg=$pdf_data['rowData1'];
            $subjectsDataOrg=$pdf_data['rowData2'];
                 unset($studentDataOrg[0]);
            unset($subjectsDataOrg[0]);
            $studentDataOrg=array_values($studentDataOrg);
            $subjectsDataOrg=array_values($subjectsDataOrg);
            //store ghost image
            $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp/temp');
                $pdfData=array('studentDataOrg'=>$studentDataOrg,'subjectsDataOrg'=>$subjectsDataOrg,'auth_site_id'=>$pdf_data['auth_site_id'],'template_id'=>$pdf_data['template_id'],'previewPdf'=>$pdf_data['previewPdf'],'excelfile'=>$pdf_data['excelfile'],'request_id'=>$pdf_data['request_id'],'generation_from'=>$pdf_data['generation_from'],'admin_id'=>$pdf_data['admin_id'],'call_back_url'=>$pdf_data['call_back_url']);
                $response = $this->dispatch(new PdfGenerateBMCCSOMJob($pdfData));
        
        return $message = array('status' => 200, 'message' => 'success',"data"=>$response);
                break;
            case 'BMCCPC':
            $studentDataOrg=$pdf_data['rowData1'];
             unset($studentDataOrg[0]);
        $studentDataOrg=array_values($studentDataOrg);
        //store ghost image
        $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp/temp');
               $pdfData=array('studentDataOrg'=>$studentDataOrg,'auth_site_id'=>$pdf_data['auth_site_id'],'template_id'=>$pdf_data['template_id'],'previewPdf'=>$pdf_data['previewPdf'],'excelfile'=>$pdf_data['excelfile'],'request_id'=>$pdf_data['request_id'],'generation_from'=>$pdf_data['generation_from'],'admin_id'=>$pdf_data['admin_id'],'call_back_url'=>$pdf_data['call_back_url']);
                $link = $this->dispatch(new PdfGenerateBMCCPassingJob($pdfData));
        return $message = array('status' => 200, 'message' => 'success',"data"=>$response);
                break;
            default:
                return $message = array('status' => 403, 'message' => 'Access forbidden.');
                break;
        }
        
        }else
        {
            return $message = array('status' => 403, 'message' => 'Access forbidden.');
        }

        }else
        {
            return $message = array('status' => 403, 'message' => 'Access forbidden.');
        }
    }

    public function checkRequestStatus($token,Request $request){

         $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $request->all();
        if (ApiSecurityLayerThirdParty::checkAuthorization()){
            if(!empty($token)){
                $key=\Config::get('constant.encryption_key');
                $requestId=ApiSecurityLayerThirdParty::decrypt_adv($token, $key);

                $requestData = ThirdPartyRequests::select('id as request_id','status','generated_documents','printable_pdf_link','updated_at')->where('id',$requestId)->first();

                if($requestData){
                     $message = array('status' => 200, 'message' => 'success',"data"=>$requestData);
                     return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                }else{
                    $message = array('status' => 400, 'message' => 'Something went wrong!');
                     return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
                }
              
            }else{
                $message = array('status' => 403, 'message' => 'Access forbidden.'); 
               return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }
        }else
        {
            return $message = array('status' => 403, 'message' => 'Access forbidden.');
        }
    }

    public function callBackUrl(Request $request)
    {
        

     
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        
        $requestParameter =$json = file_get_contents('php://input');
       $request=json_decode($json);
       $array = json_decode(json_encode($request), true);

           if (!isset($array['request_id'])||empty($array['request_id'])||!isset($array['printable_pdf_link'])||empty($array['printable_pdf_link'])) {
               
                  $message = array('status' => 400, 'message' => 'Required parameters not found.');
                  return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }else{
            
            $user_id = $array['user_id'];
            $printable_pdf_link = $array['printable_pdf_link'];
                    $updated = date('Y-m-d H:i:s');
                    SeqrdocRequests::where('request_id',$request_id)->update(["updated_at"=>$updated,"printable_pdf_link"=>$printable_pdf_link]);
                    $message = array('success' => true, 'status'=>200, 'message' => 'Updated successfully.');
               
        } 
        
        return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
    }

     public function callBackUrlDemo(Request $request)
    {
      
      
        $json = file_get_contents('php://input');
       $request=json_decode($json);
       $array = json_decode(json_encode($request), true);


        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
         $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter =$array;
     

            if (!isset($array['request_id'])||empty($array['request_id'])||!isset($array['printable_pdf_link'])||empty($array['printable_pdf_link'])) {
               
                  $message = array('status' => 400, 'message' => 'Required parameters not found.');
            }else{
            
                $request_id = $array['request_id'];
                $printable_pdf_link = $array['printable_pdf_link'];
                $updated_at = date('Y-m-d H:i:s');
                DemoERPData::where('request_id',$request_id)->update(["updated_at"=>$updated_at,"printable_pdf_link"=>$printable_pdf_link,"status"=>"Regenerate"]);
                
                $message = array('status'=>200, 'message' => 'Data updated successfully.');
               
        } 
        
        return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
    }

    public function logout(Request $request)
    {
        

     
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $requestUrl = \Request::Url();
        $requestMethod = \Request::method();
        $requestParameter = $request->all();
     
        if (ApiSecurityLayerThirdParty::checkAuthorization()){
            

            $rules = [
                'user_id' => 'required'
            ];

            $messages = [
                'user_id.required' => 'Please enter user id.',
            ];

            $validator = \Validator::make($request->all(),$rules,$messages);

            if ($validator->fails()) {
               
                  $message = array('status' => 400, 'message' => ApiSecurityLayerThirdParty::getMessage($validator->errors()));
                  return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
            }else{
            
            $user_id = $request['user_id'];
           
            if (!empty($user_id) && ApiSecurityLayerThirdParty::checkAccessTokenAdmin($user_id)) 
            {
                $session_id = ApiSecurityLayerThirdParty::fetchSessionId($user_id);

                                
                if ($session_id) 
                {
                    $logout_time = date('Y-m-d H:i:s');
                    $query =SessionManager::where('id',$session_id)
                                            ->where('user_id',$user_id)
                                                    ->update(['logout_time' => $logout_time,'is_logged'=>0]);
                    $message = array('success' => true, 'status'=>200, 'message' => 'Successfully Logout');
                } 
                else 
                {
                    $message = array('success' => false,'status'=>403, 'message' => 'Access forbidden.');
                }
            } 
            else 
            {
                $message = array('success' => false,'status'=>403, 'message' => 'Access forbidden.');
            }
        } 
        
       } 
            else 
            {
                $message = array('success' => false,'status'=>403, 'message' => 'Access forbidden.');
            }
        return ApiSecurityLayerThirdParty::apiResponse($message,false,$requestUrl,$requestMethod,$requestParameter);
    }

     //create ghost image folder
    public function createTemp($path){
        $tmp = date("ymdHis");
        
        $tmpname = tempnam($path, $tmp);
        unlink($tmpname);
        mkdir($tmpname);
        return $tmpname;
    }

}