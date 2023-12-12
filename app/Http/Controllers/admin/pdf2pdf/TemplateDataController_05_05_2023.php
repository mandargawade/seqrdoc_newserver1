<?php

namespace App\Http\Controllers\admin\pdf2pdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\pdf2pdf\TemplateMaster;
use App\models\SystemConfig;
use DB;
use Auth;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use File;
use App\models\StudentTable;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Helpers\CoreHelper;

class TemplateDataController extends Controller
{   

      public function index(Request $request)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);        
        //if we are in index page then make create_refresh field inside template 0
       // TemplateMaster::where('create_refresh',1)->update(['create_refresh'=>0]);
        //$data = session()->all();  
        //$admin_userid=$data['login_admin_59ba36addc2b2f9401580f014c7f58ea4e30989d'];
        $admin_data = \Auth::guard('admin')->user()->toArray();
        $admin_userid=$admin_data['id'];
        if($subdomain[0] == "icat"){
            $LA = DB::table('admin_table')
                    ->select('assigned_labs','role_id')
                    ->where('id', '=', $admin_userid)
                    ->get();
            $assigned_labs=$LA[0]->assigned_labs;      
            $roleid=$LA[0]->role_id;      
        }
        if($request->ajax()){
            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and (template_name like \"%{$search}%\""
                . ")";
            }

            $status=$request->get('status');

            if($status==1)
            {
                $status=1;
                $where_str.= " and (uploaded_pdfs.publish =$status)";
            }
            else if($status==0)
            {
                $status=0;
                $where_str.=" and (uploaded_pdfs.publish= $status)";
            }   
            if($subdomain[0] == "icat"){
                if($roleid != 1){
                    $where_str.=" and (assigned_labs REGEXP '[[:<:]]".$assigned_labs."[[:>:]]')";
                }
            } 
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            //for serial number
            $iDisplayStart=$request->input('iDisplayStart'); 
            DB::statement(DB::raw('set @rownum='.$iDisplayStart));
            //DB::statement(DB::raw('set @rownum=0'));
            if($subdomain[0]=="icat"){
                $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name','pdf_page','id','file_name','publish',DB::raw('IFNULL(`template_title`,"") AS template_title'),'print_bg_file', 'print_bg_status', 'verification_bg_file', 'verification_bg_status', DB::raw('CASE WHEN assigned_labs="0" THEN "" ELSE LENGTH(assigned_labs)-LENGTH(REPLACE(assigned_labs,",",""))+1 END AS LabCount')];
            }else{
                $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name','pdf_page','id','file_name','publish',DB::raw('IFNULL(`template_title`,"") AS template_title'),'print_bg_file', 'print_bg_status', 'verification_bg_file', 'verification_bg_status'];    
            }
                        
            
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
        return view('admin.pdf2pdf.templateMaster.index');
    }

    public function templateMaker(Request $request)
    {   
   
         $template_data=array("action"=>"add");
        return view('admin.pdf2pdf.addtemplate.index',compact('template_data'));
    }

   public function edit(){
    $id=$_POST['id'];
        $templateData = TemplateMaster::select('template_name','file_name','pdf_page','template_title')->where(['id'=>$id])->first();
      
        $templateData=DB::select(DB::raw('SELECT template_name,file_name,pdf_page,template_title FROM `uploaded_pdfs` WHERE id ="'.$id.'"'));                      
        $templateData=(object)$templateData[0];
       
        $template_data=array("action"=>"edit","id"=>$id,"template_name"=>$templateData->template_name,"file_name"=>$templateData->file_name,"pdf_page"=>$templateData->pdf_page,"template_title"=>$templateData->template_title);
    return view('admin.pdf2pdf.addtemplate.index',compact('template_data'));
   }


public function storeFile(Request $request)
{


    $pdf_path=$request->get('pdf_path');
    $site_id=$request->get('site_id');
    if(!empty($pdf_path)&&!empty($site_id)){
        //Path of the file stored under pathinfo
        $myFile = pathinfo($pdf_path);
  
        //Show the file name
        $certName=$myFile['basename'];
        
        if(!empty($certName)){
            $domain = \Request::getHost();
            $subdomain = explode('.', $domain);
            $siteData=SystemConfig::select('sandboxing','file_aws_local')->where('site_id',$site_id)->first();
           
            if($siteData){
              if($siteData->file_aws_local == '1') {
                    $s3=\Storage::disk('s3');
                    $pdf_file_directory = "public/".$subdomain[0].'/backend/pdf_file/';
                    $sandbox_directory =  "public/".$subdomain[0].'/backend/pdf_file/sandbox/';

                    if(!$s3->exists($pdf_file_directory))
                    {
                     $s3->makeDirectory($pdf_file_directory, 0777,true);  
                    }
                    if(!$s3->exists($sandbox_directory))
                    {
                     $s3->makeDirectory($sandbox_directory, 0777,true);  
                    }
                }
                else{
                    $pdf_file_directory = public_path().'/'.$subdomain[0].'/backend/pdf_file/';
                    $sandbox_directory = public_path().'/'.$subdomain[0].'/backend/pdf_file/sandbox/';


                    if(!is_dir($pdf_file_directory)){

                        mkdir($pdf_file_directory, 0777,true);
                    }

                    if(!is_dir($sandbox_directory)){

                        mkdir($sandbox_directory, 0777,true);
                    }

                }

                /***** Uploaded verification pdf to s3 bucket ******/
                if($siteData->file_aws_local == '1'||$subdomain[0]=='test'){

                    $temp_pdf_file_directory = public_path().'/'.$subdomain[0].'/backend/compressed/';
                    $temp_pdf_file = public_path().'/'.$subdomain[0].'/backend/compressed/'.$certName;

                    if(!is_dir($temp_pdf_file_directory)){

                        mkdir($temp_pdf_file_directory, 0777,true);
                    }

                   // $pyscript = \Config::get('constant.pathCompressScript'); 
                    /*$source=$pdf_path;//$file1
                    $output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\compressed\\".$certName;
                    //$cmd = "$pyscript $source $output 2>&1";
                    //exec($cmd, $output, $return);
                    CoreHelper::compressPdfFile($source,$output);
                    $aws_qr = \Storage::disk('s3')->put('/public/'.$subdomain[0].'/backend/pdf_file/'.$certName, file_get_contents($temp_pdf_file), 'public');
                    $filename1 = \Storage::disk('s3')->url($certName);*/ 
                    $temp_pdf_file = public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;
                    $aws_qr = \Storage::disk('s3')->put('/public/'.$subdomain[0].'/backend/pdf_file/'.$certName, file_get_contents($temp_pdf_file), 'public');
                    $filename1 = \Storage::disk('s3')->url($certName);
                    //unlink($temp_pdf_file);
                }else{

                    /*$pyscript = \Config::get('constant.pathCompressScript'); 
                    $source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;//$file1
                    $output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName;
                    $cmd = "$pyscript $source $output 2>&1";
                    exec($cmd, $output, $return);*/
                   /* if($subdomain[0]=='test121212'){
                        $pyscript = \Config::get('constant.pathCompressScript'); 
                        $source=$pdf_path;//$file1
                        $output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName;
                        $cmd = "$pyscript $source $output 2>&1";
                        exec($cmd, $output, $return);
                    }else{*/
						//$source=$pdf_path;//$file1
						//$output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName;
						//CoreHelper::compressPdfFile($source,$output);    
						$aws_qr = \File::copy($pdf_path,public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName);
                    //}
                    
                   
                }
                return  response()->json(['status'=>200,'message'=>'File uploaded successfully.']);
         
            }else{
            return  response()->json(['status'=>422,'message'=>'Site id not found.']);
            } 
        }else{
            return  response()->json(['status'=>422,'message'=>'Certificate name not found.']);
        } 
    }else{
         return  response()->json(['status'=>422,'message'=>'Pdf path or site id not found.']);
    }
    
}


    public function createTemplate(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
   
        $site_id=Auth::guard('admin')->user()->site_id;
        $admin_id = \Auth::guard('admin')->user()->toArray();

        $valid_extensions = array('pdf'); // valid extensions
        $path = 'uploads/pdfs/'; // upload directory
        $template_id=$request->get('template_id');
        if($request->hasFile('pdf_upload')){

            $file_name = $request['pdf_upload']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            if($ext != 'pdf' && $ext != 'PDF'){    
                return response()->json(['success'=>false,'message'=>'Please upload valid pdf file.','rstatus' => 'invalid']);
            }

            $pdfFile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'/'.$subdomain[0].'/uploads/pdfs/';
            $path='uploads/pdfs/'.$pdfFile ;
            $fullpath = $target_path.$pdfFile;

            //if directory not exist make directory
            if(!is_dir($target_path)){
    
                mkdir($target_path, 0777,true);
            }

            if($request['pdf_upload']->move($target_path,$pdfFile)){

                $extractor_boxes = $_POST['extractor_boxes'];
                $placer_boxes = $_POST['placer_boxes'];
                $ep_boxes = $_POST['ep_boxes'];
                $template_name = $_POST['template_name'];
                $template_id = $_POST['template_id'];
                $pdf_page = $_POST['pdf_page'];
                $pdf_data = $_POST['pdf_data'];


                if(!empty($template_id)){
                    $templateDataCount = TemplateMaster::select('id')->where('id','!=',$template_id)
                                                                 ->where('template_name',$template_name)
                                                                 ->count();
                    if($templateDataCount==0){

                        TemplateMaster::where('id',$template_id)->update(["extractor_details"=>$extractor_boxes,"placer_details"=>$placer_boxes,"ep_details"=>$ep_boxes]);

                        
                        $folder=public_path().'/'.$subdomain[0].'/documents/';
                        unlink($folder.$template_name.".json");
                        $myfile = fopen($folder."/".$template_name.".json", "w") or die("Unable to open file!");
                        fwrite($myfile, $pdf_data);              
                        fclose($myfile);
                        return response()->json(['success'=>"success",'message'=>'Template updated successfully.','type'=>'toaster','rstatus' => 'edit']);
                    }else{
                        return response()->json(['success'=>false,'message'=>'Template already exist with same name!','type'=>'toaster','rstatus' => 'exist']);
                    }
                }else{

                    $templateDataCount = TemplateMaster::select('id')->where('template_name',$template_name)
                                                                 ->count();
                    if($templateDataCount==0){

                    #Upload template in table
                    $insertRequest = new TemplateMaster();
                    $insertRequest->file_name=$path;
                    $insertRequest->extractor_details=$extractor_boxes;
                    $insertRequest->placer_details=$placer_boxes;
                    $insertRequest->ep_details=$ep_boxes;
                    $insertRequest->template_name=$template_name;
                    $insertRequest->pdf_page=$pdf_page;
                    $insertRequest->generated_by=$admin_id['id'];
                  
                    $insertRequest->save();
                    
                    $insertRequest=$insertRequest->toArray();
                    $template_id=$insertRequest['id'];

                    $folder=public_path().'/'.$subdomain[0].'/documents';
                    if (!file_exists($folder)) {
                        mkdir($folder, 0777,true);
                    }                           
                    $myfile = fopen($folder."/".$template_name.".json", "w") or die("Unable to open file!");
                    fwrite($myfile, $pdf_data);              
                    fclose($myfile);                                 
                    
                    return response()->json(['success'=>"success",'message'=>'Template created successfully.','type'=>'toaster','rstatus' => 'insert','id'=>$template_id]);
                    }else{
                        return response()->json(['success'=>false,'message'=>'Template already exist with same name!','type'=>'toaster','rstatus' => 'exist']);
                    }
                }

            }else{
               return response()->json(['success'=>false,'message'=>'Error while uploading file!','type'=>'toaster','rstatus' => 'invalid']); 
            }
        }else if(!empty($template_id)){

                    $extractor_boxes = $_POST['extractor_boxes'];
                    $placer_boxes = $_POST['placer_boxes'];
                    $ep_boxes = $_POST['ep_boxes'];
                    $template_name = $_POST['template_name'];
                    $template_id = $_POST['template_id'];
                    $pdf_data = $_POST['pdf_data']; 

                    $templateDataCount = TemplateMaster::select('id')->where('id','!=',$template_id)
                                                                 ->where('template_name',$template_name)
                                                                 ->count();
                    if($templateDataCount==0){

                        TemplateMaster::where('id',$template_id)->update(["extractor_details"=>$extractor_boxes,"placer_details"=>$placer_boxes,"ep_details"=>$ep_boxes]);

                        
                        $folder=public_path().'/'.$subdomain[0].'/documents/';
                        unlink($folder.$template_name.".json");
                        $myfile = fopen($folder."/".$template_name.".json", "w") or die("Unable to open file!");
                        fwrite($myfile, $pdf_data);              
                        fclose($myfile);
                        return response()->json(['success'=>"success",'message'=>'Template updated successfully.','type'=>'toaster','rstatus' => 'edit']);
                    }else{
                        return response()->json(['success'=>false,'message'=>'Template already exist with same name!','type'=>'toaster','rstatus' => 'exist']);
                    }
        }
        else{
            return response()->json(['success'=>false,'message'=>'Please upload file with .pdf extension!','type'=>'toaster','rstatus' => 'invalid']);
        }

    }

    public function createTextFile(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        if(!empty($_POST['file']))
        {   

            $domain = \Request::getHost();
            $subdomain = explode('.', $domain);
             $target_path = public_path().'/'.$subdomain[0].'/processed_pdfs';
            if (!file_exists($target_path)) {
                        mkdir($target_path, 0777,true);
            }    
            if($_POST['status'] == 'start'){
                $filename = $_POST['file'];
                $file = $target_path."/" . $filename;    
                if (!file_exists($file)) {
                    $myfile=fopen($file, "w");
                          
                    echo "File created.";
                } 
            }
            if($_POST['status'] == 'end'){
                $filename = $_POST['file'];
                $file = $target_path."/" . $filename;    
                if (file_exists($file)) {
                    unlink($file);
                    echo "File deleted.";
                }         
            }
        }
    }

     public function duplicateTemplate(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $domain = \Request::getHost();
            $subdomain = explode('.', $domain);
        if($_POST['id'] != ''){
            $id = $_POST['id'];     
            /*$query = $conn->prepare("SELECT * FROM `uploaded_pdfs` WHERE id = $id");
            $query->execute();              
            $data = $query->fetch(PDO::FETCH_ASSOC);*/

            $field_data=DB::select(DB::raw('SELECT * FROM `uploaded_pdfs` WHERE id ="'.$id.'"'));                      
            $data=(array)$field_data[0];
            //print_r($data);
            //exit;
            $path=$data['file_name'];
            $extractor_boxes=$data['extractor_details'];
            $placer_boxes=$data['placer_details'];
            $ep_boxes=$data['ep_details'];
            $template_name=$data['template_name'];
            $pdf_page=$data['pdf_page'];
            $generated_by=$data['generated_by'];
            if(empty($data)){
                $result=array('rstatus' => 'invalid');
                echo json_encode($result);  
                return;
            }   
            if (strpos($template_name, '-copy-') !== false) {
                list($before, $after) = explode('-copy-', $template_name);    
                /*$qry = $conn->prepare("SELECT * FROM `uploaded_pdfs` WHERE template_name LIKE '".$before."-copy-%' order by id desc");
                $qry->execute();                
                $records = $qry->fetch(PDO::FETCH_ASSOC);*/
                $field_data=DB::select(DB::raw("SELECT * FROM `uploaded_pdfs` WHERE template_name LIKE '".$before."-copy-%' order by id desc"));                      
                $records=(array)$field_data[0];   
                list($new_before, $new_after) = explode('-copy-', $records['template_name']); 
                $count=$new_after+1;
                $source="documents/".$template_name.".json";
                $dest="documents/".$new_before."-copy-".$count.".json";
                $template_name=$new_before."-copy-".$count;
                
            }else{
                /*$qry = $conn->prepare("SELECT * FROM `uploaded_pdfs` WHERE template_name LIKE '".$template_name."-copy-%' order by id desc");
                $qry->execute();                
                $records = $qry->fetch(PDO::FETCH_ASSOC);  */ 
                $field_data=DB::select(DB::raw("SELECT * FROM `uploaded_pdfs` WHERE template_name LIKE '".$template_name."-copy-%' order by id desc"));                      
                $records=(array)$field_data;   
                if(empty($records)){
                    $source="documents/".$template_name.".json";
                    $dest="documents/".$template_name."-copy-1.json";    
                    $template_name=$template_name."-copy-1";            
                }else{            
                    list($new_before, $new_after) = explode('-copy-', $records['template_name']); 
                    $count=$new_after+1;        
                    $source="documents/".$template_name.".json";
                    $dest="documents/".$template_name."-copy-".$count.".json"; 
                    $template_name=$new_before."-copy-".$count;
                }        
            }
            
            //copy($source, $dest);

            $source=public_path().'/'.$subdomain[0].'/'.$source;
            $dest=public_path().'/'.$subdomain[0].'/'.$dest;
            if (!file_exists($dest)) {
                
                \File::copy($source,$dest);
            }
/*
            $bgData=DB::select(DB::raw('SELECT * FROM `'.$dbNameSource.'`.`background_template_master` WHERE id ="'.$copyTemplate_data['bg_template_id'].'"'));
                $copyBgTemplate_data=(array)$bgData[0];
                $copyBgTemplate_data['site_id']=$site_id;
                unset($copyBgTemplate_data['id']);
                $newBgTemplateId=DB::table($dbNameDest.'.background_template_master')->insertGetId($copyBgTemplate_data);
              */
                $dataTemplateInsert=array();
                $dataTemplateInsert['file_name'] =$path;
                $dataTemplateInsert['extractor_details'] =$extractor_boxes;
                $dataTemplateInsert['placer_details'] =$placer_boxes;
                $dataTemplateInsert['ep_details'] =$ep_boxes;
                $dataTemplateInsert['template_name'] =$template_name;
                $dataTemplateInsert['pdf_page'] =$pdf_page;
                $dataTemplateInsert['generated_by'] =$generated_by;
            /*$conn->query("INSERT INTO uploaded_pdfs (file_name, extractor_details, placer_details, ep_details, template_name, pdf_page, generated_by) 
                        VALUES ('".$path."','".$extractor_boxes."','".$placer_boxes."','".$ep_boxes."','".$template_name."', '".$pdf_page."',".$generated_by.")");*/    
            $newTemplateId=DB::table('uploaded_pdfs')->insertGetId($dataTemplateInsert);
            $result=array('rstatus' => 'Success', 'template_name' => $template_name);
            echo json_encode($result);  
        }
        else{ 
            $result=array('rstatus' => 'missing');
            echo json_encode($result);
            return;
        }
    }
    public function processPdf(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        
        $directoryUrlForward="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/pdf2pdf/";
        $directoryUrlBackward="C:\\Inetpub\\vhosts\\seqrdoc.com\\httpdocs\\demo\\public\\pdf2pdf\\";
        $serverName="seqrdoc.com";
        $dbUserName="seqrdoc_multi";
        $dbPassword="SimAYam4G2";
        if($subdomain[0]=='demo'){
            $dbName = 'seqr_demo';
        }else{
            $dbName = 'seqr_d_'.$subdomain[0];
        }
        if($subdomain[0]=="test"){
            $awsS3Instances = \Config::get('constant.awsS3Instances');
        }
         

        
        //get system config data
        $get_system_config_data = SystemConfig::get();
        
        $printer_name = '';
        $timezone = '';
        
        if(!empty($get_system_config_data[0])){
            $printer_name = $get_system_config_data[0]['printer_name'];
            $timezone = $get_system_config_data[0]['timezone'];
        }
        $site_id=Auth::guard('admin')->user()->site_id;
        $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $user_id=$admin_id['id'];
        $user_name=$admin_id['username'];
        $template_id = $_POST['template_id'];
        $pdf_page = $_POST['pdf_page'];
        $pdf_flag = $_POST['pdf_flag'];
        $entry_type="Fresh";
        $response = 0;
        $progressfile=$_POST['progress_file'];
        if($pdf_page=="Single"){
            // file name
            $filename = $_FILES['file']['name'];
            $temps = explode(".", $filename);
            $filename = str_replace(' ', '_', $temps[0])."_".round(microtime(true)) . '.' . end($temps);

            $target_path = public_path().'/'.$subdomain[0].'/uploads/data/';
            $fullpath = $target_path.$filename;

            //if directory not exist make directory
            if(!is_dir($target_path)){
    
                mkdir($target_path, 0777,true);
            }
            // Location
            $location = $fullpath;// 'uploads/data/'.$filename;
            $py_location = $filename;
            // file extension
            $file_extension = pathinfo($location, PATHINFO_EXTENSION);
            $file_extension = strtolower($file_extension);
            // Valid image extensions
            $image_ext = array("pdf");   
            if(in_array($file_extension,$image_ext)){
              // Upload file
              if(move_uploaded_file($_FILES['file']['tmp_name'],$location)){
                if($pdf_flag==''){
                    if($subdomain[0]=="test"){                       
                        $pyscript = $directoryUrlBackward."Python_files\\extract_and_place_niit_sikkim.py";
                    }else{
                        $pyscript = $directoryUrlBackward."Python_files\\extract_and_place.py";
                    }
                }else{
                    $pyscript = $directoryUrlBackward."Python_files\\invisible_extract_and_place.py";
                }
                $cmd = "$pyscript $template_id $py_location $user_id $entry_type $progressfile $dbName $subdomain[0] $directoryUrlForward $directoryUrlBackward $serverName $dbUserName $dbPassword $site_id $user_name $printer_name 2>&1"; // 
                exec($cmd, $output, $return);
                //print_r($output);
                if($subdomain[0]=="test"){
                    //print_r($output);
                }
                     
                if(array_values($output)[0]=='Duplicates'){
                    $count=array_values($output)[1];
                    $unids=array_values($output)[2];
                    $response = json_encode(array('type'=>'Duplicates', 'msg'=>$count.' record(s) are already existed in DB.<br />Please confirm to generate these and inactivate the old ones.', 'filename'=>$filename, 'template_id'=>$template_id, 'pdf_page'=>$pdf_page, 'unids'=>$unids, 'progressfile'=>$progressfile));
                }
                else if(array_values($output)[0]=='Over Limit'){
                    $msg=array_values($output)[1];
                    $response = json_encode(array('type'=>'Over Limit', 'msg'=>$msg));
                }
				else if(array_values($output)[0]=='Empty Extractor'){
                    $msg=array_values($output)[1];
                    $response = json_encode(array('type'=>'Empty Extractor', 'msg'=>$msg));
                }
                else{
                    $response = json_encode(array('type'=>'Success', 'dlink'=>end($output)));
                }
              }
            }
        }else{
            $folder_name = $_POST['folder_name'];
            if($folder_name == "multi_pages"){ $folder="multi_pages"; }
            else{        

                
                $f=round(microtime(true));
                $target_path = public_path().'/'.$subdomain[0].'/multi_pages/'.$f;
                 //if directory not exist make directory
                if(!is_dir($target_path)){
        
                    mkdir($target_path, 0777,true);
                }  
                $folder="multi_pages/".$f;
                foreach($_FILES['files']['name'] as $i => $name)
                {
                    if(strlen($_FILES['files']['name'][$i]) > 1)
                    {  
                        move_uploaded_file($_FILES['files']['tmp_name'][$i],$target_path."/".$name);
                    }
                }                
            }
            $pyscript = $directoryUrlBackward."Python_files\\directory_extract_and_place_QR.py"; 
            $cmd = "$pyscript $template_id $folder $user_id $entry_type $progressfile ".$dbName." ".$subdomain[0]." ".$directoryUrlForward." ".$directoryUrlBackward." ".$serverName." ".$dbUserName." ".$dbPassword." $site_id $user_name $printer_name 2>&1";
            exec($cmd, $output, $return);
            //print_r($output);

            if(array_values($output)[0]=='Duplicates'){
                $count=array_values($output)[1];
                $unids=array_values($output)[2];
                $filenames=array_values($output)[3];
                $response = json_encode(array('type'=>'Duplicates', 'msg'=>$count.' record(s) are already existed in DB.<br />Please confirm to generate these and inactivate the old ones.', 'folder'=>$folder, 'filename'=>$filenames, 'template_id'=>$template_id, 'pdf_page'=>$pdf_page, 'unids'=>$unids, 'progressfile'=>$progressfile));
            }
            else if(array_values($output)[0]=='Over Limit'){ 
                $msg=array_values($output)[1];
                $response = json_encode(array('type'=>'Over Limit', 'msg'=>$msg));
            }
            else{
                $response = json_encode(array('type'=>'Success', 'dlink'=>end($output)));
            }    
        }
        echo $response;

    }

     public function processPdfAgain(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        
        $directoryUrlForward="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/pdf2pdf/";
        $directoryUrlBackward="C:\\Inetpub\\vhosts\\seqrdoc.com\\httpdocs\\demo\\public\\pdf2pdf\\";
        $serverName="seqrdoc.com";
        $dbUserName="seqrdoc_multi";
        $dbPassword="SimAYam4G2";
        if($subdomain[0]=='demo'){
            $dbName = 'seqr_demo';
        }else{
            $dbName = 'seqr_d_'.$subdomain[0];
        }
        
        //get system config data
        $get_system_config_data = SystemConfig::get();
        
        $printer_name = '';
        $timezone = '';
        
        if(!empty($get_system_config_data[0])){
            $printer_name = $get_system_config_data[0]['printer_name'];
            $timezone = $get_system_config_data[0]['timezone'];
        }
        $site_id=Auth::guard('admin')->user()->site_id;
         $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $user_id=$admin_id['id'];
        $user_name=$admin_id['username'];
        $template_id = $_POST['template_id'];
        $pdf_page = $_POST['pdf_page'];
        $filename = $_POST['file'];
        $progressfile = $_POST['progressfile'];
        $unids = $_POST['unids'];
        $unids_arr = explode (",", $unids); 
        $total_count=count($unids_arr);
        $entry_type="Proceed";
        $enter_date = date('y-m-d h:i:s');
        $publish=2;
        $data = [
            'filename' => $filename,
            'template_id' => $template_id,
            'pdf_page' => $pdf_page,
            'unids' => $unids,
            'total_count' => $total_count,
            'user_id' => $user_id,
            'created_at' => $enter_date
        ];
        $col="";
        $v="";
        foreach ($data as $key => $value)
        {
            $col .=$key.",";
            $v .=":".$key.",";
        }
        $fields=substr($col, 0, -1);
        $vs=substr($v, 0, -1);
        $response = 0;
        if($pdf_page=="Single"){
            $newRecordId=DB::table('duplicate_records')->insertGetId($data);

            foreach ($unids_arr as $unid) {  

              DB::select(DB::raw('UPDATE `individual_records` SET publish="'.$publish.'" WHERE unique_no="'.$unid.'" AND publish!="0"')); 
               StudentTable::where('serial_no',$unid)->update(['status'=>'0']);
            }    
            $location = 'uploads/data/'.$filename;
            $py_location = $filename;

            if($subdomain[0]=="test"){
                    $pyscript = $directoryUrlBackward."Python_files\\extract_and_place_niit_sikkim.py";
            }else{
                $pyscript = $directoryUrlBackward."Python_files\\extract_and_place.py";
            }
            
            $cmd = "$pyscript $template_id $py_location $user_id $entry_type $progressfile ".$dbName." ".$subdomain[0]." ".$directoryUrlForward." ".$directoryUrlBackward." ".$serverName." ".$dbUserName." ".$dbPassword." $site_id $user_name $printer_name 2>&1"; 
            exec($cmd, $output, $return);

            $response = json_encode(array('type'=>'Success', 'dlink'=>end($output)));
        }else{
             $newRecordId=DB::table('duplicate_records')->insertGetId($data);


            foreach ($unids_arr as $unid) {  
              DB::select(DB::raw('UPDATE `individual_records` SET publish="'.$publish.'" WHERE unique_no="'.$unid.'" AND publish!="0"'));       
              StudentTable::where('serial_no',$unid)->update(['status'=>'0']);
            }    
            $folder = $_POST['folder'];
            $pyscript = $directoryUrlBackward."Python_files\\directory_extract_and_place_QR.py"; 
            $cmd = "$pyscript $template_id $folder $user_id $entry_type $progressfile ".$dbName." ".$subdomain[0]." ".$directoryUrlForward." ".$directoryUrlBackward." ".$serverName." ".$dbUserName." ".$dbPassword." $site_id $user_name $printer_name 2>&1";
            exec($cmd, $output, $return);
           // print_r($output);
            $response = json_encode(array('type'=>'Success', 'dlink'=>end($output)));
        }
        echo $response;

    }

     public function test(Request $request){

         $directoryUrlForward="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/pdf2pdf/";
        $directoryUrlBackward="C:\\Inetpub\\vhosts\\seqrdoc.com\\httpdocs\\demo\\public\\pdf2pdf\\Python_files\\excel2pdf.py";
        //$cmd="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/pdf2pdf/pdf_env/Scripts/python.exe $directoryUrlBackward";
        //$output = exec ("C:/Users/admin/AppData/Local/Programs/Python/Python38/python.exe C:/wamp64/www/seqrdoc/public/pdf2pdf/Python_files/session.py");
        $cmd="Python ".$directoryUrlBackward;
        exec($cmd, $output, $return);
        print_r($output);
        //print_r($cmd);
        //print_r($return);
        //echo $this->info($output);

      //  $command_exec = escapeshellcmd($cmd); 
       // $str_output = shell_exec($command_exec); 
        //echo $str_output; 
       }

    public function imageFormSave(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
   
        $site_id=Auth::guard('admin')->user()->site_id;
        $admin_id = \Auth::guard('admin')->user()->toArray();
        $name = trim($_POST['name']);
        $filename = $_FILES['file']['name'];
        $temps = explode(".", $filename);
        $filename = str_replace(' ', '_', $temps[0])."_".round(microtime(true)) . '.' . end($temps);
        //$location = public_path().'/'.$subdomain[0].'/upload_images/'.$filename;
        //$target_path = public_path().'/'.$subdomain[0].'/upload_images';
        $location = public_path().'/'.$subdomain[0].'/backend/templates/pdf2pdf_images/'.$filename;
        $target_path = public_path().'/'.$subdomain[0].'/backend/templates/pdf2pdf_images';   
         //if directory not exist make directory
        if(!is_dir($target_path)){
            mkdir($target_path, 0777,true);
        }
        if(move_uploaded_file($_FILES['file']['tmp_name'],$location)){
            $result = json_encode(array('type'=>'success','message'=>'Image has been added.'));
            echo $result;   
            exit;
        }else{
            $result = json_encode(array('type'=>'error','message'=>'Failed to submit the form. '.$e->getMessage( )));
            echo $result;
            exit;
        }        
        /*
        $publish_id = trim($_POST['publish_id']);
        $enter_date = date('y-m-d h:i:s');

        $data = [
            'name' => $name,
            'filename' => $filename,
            'publish' => $publish_id,
            'created_at' => $enter_date
        ];
        $col="";
        $v="";
        foreach ($data as $key => $value)
        {
            $col .=$key.",";
            $v .=":".$key.",";
        }
        $fields=substr($col, 0, -1);
        $vs=substr($v, 0, -1);

        //Check duplicate username
        try {            

            $img_data=DB::select(DB::raw('SELECT * FROM `image_data` WHERE name ="'.$name.'"'));

           // print_r($img_data);                      
           // $data=(array)$img_data[0];

            if (isset($img_data[0])&&!empty($img_data[0])) {
                $msg='Name already existed.';
                $result = json_encode(array('type'=>'error','message'=>$msg));
                echo $result;
                exit;
            } else {        
                if(move_uploaded_file($_FILES['file']['tmp_name'],$location)){
                    // insert record
                    $newRecordId=DB::table('image_data')->insertGetId($data);
                     $result = json_encode(array('type'=>'success','message'=>'Image has been added.'));
                    echo $result;   
                 exit;
                }else{
                     $result = json_encode(array('type'=>'error','message'=>'Failed to submit the form. '.$e->getMessage( )));
                        echo $result;
                        exit;
                }
            }
        } catch (PDOException $e) {
            $result = json_encode(array('type'=>'error','message'=>'Failed to connect. '.$e->getMessage()));
            echo $result;
            //exit($e->getMessage());
        }
        */
       }

       public function imageFormEdit(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
   
        $site_id=Auth::guard('admin')->user()->site_id;
         $admin_id = \Auth::guard('admin')->user()->toArray();
        $id=trim($_POST['record_id']);
        $tbl_name="image_data";
        $name = trim($_POST['name']);
        //$filename = $_FILES['file']['name'];
        $publish_id = trim($_POST['publish_id']);
        $record_id = trim($_POST['record_id']);
        $name_check = trim($_POST['name_check']);
        $filename_check = trim($_POST['filename_check']);
        $enter_date = date('y-m-d h:i:s');
        if(!empty($_FILES["file"]["name"])){
            $filename = $_FILES['file']['name'];
            $temps = explode(".", $filename);
            $filename = str_replace(' ', '_', $temps[0])."_".round(microtime(true)) . '.' . end($temps);
            $location = public_path().'/'.$subdomain[0].'/upload_images/'.$filename;
        $target_path = public_path().'/'.$subdomain[0].'/upload_images';
         //if directory not exist make directory
        if(!is_dir($target_path)){

            mkdir($target_path, 0777,true);
        }   
            move_uploaded_file($_FILES['file']['tmp_name'],$location);
            $data = [
                'name' => $name,
                'filename' => $filename,
                'publish' => $publish_id,
                'created_at' => $enter_date
            ];
            if (file_exists(public_path().'/'.$subdomain[0].'/upload_images/'.$filename_check)) {
               unlink(public_path().'/'.$subdomain[0].'/upload_images/'.$filename_check);
            }  
        }else{
            $data = [
                'name' => $name,
                'publish' => $publish_id,
                'created_at' => $enter_date
            ];    
        }
        $col="";
        $v="";
        foreach ($data as $key => $value)
        {
            $col .=$key."=:".$key.",";
            $v .=$key.",";
        }
        $fields=substr($col, 0, -1);
        $vs=substr($v, 0, -1);

        if($name_check != $name){
            //Check duplicate name
            try {
                /*$query = $conn->prepare("SELECT * FROM $tbl_name WHERE name=:name");
                $query->bindParam("name", $name, PDO::PARAM_STR);
                $query->execute();
                if ($query->rowCount() > 0) {*/
                 $img_data=DB::select(DB::raw('SELECT * FROM `image_data` WHERE name ="'.$name.'"'));

           // print_r($img_data);                      
           // $data=(array)$img_data[0];

            if (isset($img_data[0])&&!empty($img_data[0])) {    
                    $msg='Name already existed.';
                    $result = json_encode(array('type'=>'error','message'=>$msg));
                    echo $result;
                    exit;
                } else {        
                    // update record
                    /*$sql = "UPDATE $tbl_name SET $fields WHERE id=$id";
                    try {                
                        $conn->beginTransaction();
                        $conn->prepare($sql)->execute($data);
                        $conn->commit();
                        $result = json_encode(array('type'=>'success','message'=>'Image has been updated.'));            
                        echo $result;   
                        exit;
                    }catch (Exception $e){
                        $conn->rollback();
                        $result = json_encode(array('type'=>'error','message'=>'Failed to submit the form. '.$e->getMessage( )));
                        echo $result;
                        exit;
                    }*/
                    DB::select(DB::raw('UPDATE `image_data` SET "'.$fields.'" WHERE id="'.$id.'"'));
                    $result = json_encode(array('type'=>'success','message'=>'Image has been updated.'));            
                        echo $result;   
                        exit;
                }
            } catch (PDOException $e) {
                $result = json_encode(array('type'=>'error','message'=>'Failed to connect. '.$e->getMessage()));
                echo $result;
                //exit($e->getMessage());
            }
        }else{
                /*$sql = "UPDATE $tbl_name SET $fields WHERE id=$id";
                try {                
                    $conn->beginTransaction();
                    $conn->prepare($sql)->execute($data);
                    $conn->commit();
                    $result = json_encode(array('type'=>'success','message'=>'Image has been updated..'));
                    echo $result;   
                    exit;
                }catch (Exception $e){
                    $conn->rollback();
                    $result = json_encode(array('type'=>'error','message'=>'Failed to submit the form. '.$e->getMessage( )));
                    echo $result;
                    exit;
                }*/
                DB::select(DB::raw('UPDATE `image_data` SET "'.$fields.'" WHERE id="'.$id.'"'));
                $result = json_encode(array('type'=>'success','message'=>'Image has been updated.'));            
                        echo $result;   
                        exit;    
        }

       }

        public function imageList(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
            $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
            $domain = \Request::getHost();
            $subdomain = explode('.', $domain);
       
            $site_id=Auth::guard('admin')->user()->site_id;
            $admin_id = \Auth::guard('admin')->user()->toArray();
            $img_name = trim($_POST['img_name']);
            
            $files = glob(public_path().'/'.$subdomain[0].'/backend/templates/pdf2pdf_images/*.*'); //basename($image)
            $imagePath='https://'.$domain.'/'.$subdomain[0].'/backend/templates/pdf2pdf_images/';
            $counter=1;
            
            $html='<table class="table table-bordered">';
                $html .='<tbody>'; 
            for ($i = 0; $i < count($files); $i++) {
                if($counter == 1){
                    $html.='<tr>';
                }            
                $image = $files[$i];

                $html .='<td valign="top" class="text-center" width="20%">';
                if(basename($image) == $img_name){
                    $checked="checked";
                }else{
                    $checked = "";
                }            
                $supported_file = array(
                    'gif',
                    'jpg',
                    'jpeg',
                    'png'
                );
                $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));
                if (in_array($ext, $supported_file)) {
                    $html .='<input type="radio" value="'.basename($image).'" name="img_name" style="-ms-transform: scale(1.6); -webkit-transform: scale(1.6); transform: scale(1.6);" '.$checked.'><br />';
                    $html .='<img class="img-thumbnail" src="'.$imagePath.basename($image).'" />';
                    $html .='</td>';
                    $counter++;  
                    if($counter == 6){ echo "</tr>"; $counter=1;}
                    
                } else {
                    continue;
                }
            }
                $html .='</tbody>';
            $html .='</table>';
            echo $html;        
            /*$stmt = $conn->prepare("SELECT * FROM image_data where publish=1 order by created_at desc");
            $stmt->execute();  */
            /*
            $img_data=DB::select(DB::raw('SELECT * FROM image_data where publish=1 order by created_at desc'));
            $counter=1;        
            $html='<table class="table table-bordered">';
                $html .='<tbody>';        
                    
                         //while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        foreach ($img_data as $row) {
                            if($counter == 1){
                                $html.='<tr>';
                            }
                        $html .='<td valign="top" class="text-center" width="20%">';
                        if($row->filename == $img_name){
                            $checked="checked";
                        }else{
                            $checked = "";
                        }
                        $html .='<input type="radio" value="'.$row->filename.'" name="img_name" style="-ms-transform: scale(1.6); -webkit-transform: scale(1.6); transform: scale(1.6);" '.$checked.'><br />';
                        $imagePath='https://'.$domain.'/'.$subdomain[0];
                        $html .='<img class="img-thumbnail" src="'.$imagePath.'/upload_images/'.$row->filename.'" />';
                        $html .='</td>';
                       
                            $counter++;  
                            if($counter == 6){ echo "</tr>"; $counter=1;}
                        } 
                       
                      //$html.='</tr>';     
                $html .='</tbody>';
            $html .='</table>';
            echo $html;*/
        }   
    
    public function ActiveInactiveTemplate(Request $request){        
        $id=$_POST['id'];
        if($id != ''){            
            $publish=$_POST['publish'];
            $mode=$_POST['mode'];    
            if($mode=='active'){ $mode='activated'; }else{ $mode='inactivated'; }
            DB::select(DB::raw('UPDATE `uploaded_pdfs` SET publish='.$publish.' WHERE id="'.$id.'"'));
            $result = json_encode(array('rstatus'=>'Success','message'=>'Template has been '.$mode.'.','mode'=>$mode));
            echo $result;
        }
    }
    public function AssignLab(){ 
        $id = \Request::segment(3);   
        $LA = DB::table('uploaded_pdfs')
                ->select('assigned_labs','template_name')
                ->where('id', '=', $id)
                ->get();
        $assigned_labs=$LA[0]->assigned_labs;
        $template_name=$LA[0]->template_name;    
        $LabData=DB::select(DB::raw('SELECT id,lab_title FROM `lab_table` WHERE publish=1 and id not in('.$assigned_labs.') order by lab_title'));         
        $LabDataAssigned=DB::select(DB::raw('SELECT id,lab_title FROM `lab_table` WHERE id in('.$assigned_labs.')')); 
        return view('admin.pdf2pdf.templateMaster.assignlab',compact('LabData','id','template_name','LabDataAssigned'));
    }
    public function AssignLabSave(Request $request){         
        $id=$request->id;
        if (is_array($request->sbTwo)){
            $LabCount=count($request->sbTwo); 
            $List = implode(', ', $request->sbTwo);
            DB::select(DB::raw("UPDATE `uploaded_pdfs` SET assigned_labs='".$List."' WHERE id='".$id."'"));
        }else{
            DB::select(DB::raw("UPDATE `uploaded_pdfs` SET assigned_labs='0' WHERE id='".$id."'"));
        }
        //return redirect('/admin/pdf2pdf-template-list');
        return redirect('/admin/pdf2pdf-assign/'.$id)->with('success','Record is saved successfully.');;
    }
    //update template title
    public function updateTitle(Request $request,$id){
        //$get_data = $request->all(); 
        $id=$request->id;
        if($id != ''){            
            $template_title=$request->template_title;            
            DB::select(DB::raw('UPDATE `uploaded_pdfs` SET template_title="'.$template_title.'" WHERE id="'.$id.'"'));
            $message = Array('type'=>'success','message'=>'Edited Successfully');
            echo json_encode($message);
        }
        
        
    } 
    public function updateAssignBg(Request $request){
        //$get_data = $request->all(); 
        $id=$request->id;
        $pbg_id = $request->pbg_id; //print_bg_file
        $pbg_status = $request->pbg_status;
        $vbg_id = $request->vbg_id;
        $vbg_status = $request->vbg_status;        
        if($id != ''){            
            $template_title=$request->template_title;            
            DB::select(DB::raw('UPDATE `uploaded_pdfs` SET print_bg_file="'.$pbg_id.'", print_bg_status="'.$pbg_status.'", verification_bg_file="'.$vbg_id.'", verification_bg_status="'.$vbg_status.'" WHERE id="'.$id.'"'));
            $message = Array('type'=>'success','message'=>'Edited Successfully');
            echo json_encode($message);
        }
        
        
    }
	public function pdfPreview(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        
		$directoryUrlForward="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/pdf2pdf/";
        $directoryUrlBackward="C:\\Inetpub\\vhosts\\seqrdoc.com\\httpdocs\\demo\\public\\pdf2pdf\\";
        $serverName="seqrdoc.com";
        $dbUserName="seqrdoc_multi";
        $dbPassword="SimAYam4G2";
		if($subdomain[0]=='demo'){
            $dbName = 'seqr_demo';
        }else{
            $dbName = 'seqr_d_'.$subdomain[0];
        }
        //get system config data
        $get_system_config_data = SystemConfig::get();        
        $printer_name = '';
        $timezone = '';        
        if(!empty($get_system_config_data[0])){
            $printer_name = $get_system_config_data[0]['printer_name'];
            $timezone = $get_system_config_data[0]['timezone'];
        }
        $site_id=Auth::guard('admin')->user()->site_id;
        $admin_id = \Auth::guard('admin')->user()->toArray();        
        $user_id=$admin_id['id'];
        $user_name=$admin_id['username'];
        $template_id = $_POST['template_id'];
        $pdf_page = $_POST['pdf_page'];
        $entry_type="Fresh";
        $response = 0;
        $progressfile='-';
		$py_location = '-';
        if($pdf_page=="Single"){ 
			$pyscript = $directoryUrlBackward."Python_files\\preview_single.py";
			$cmd = "$pyscript $template_id $py_location $user_id $entry_type $progressfile $dbName $subdomain[0] $directoryUrlForward $directoryUrlBackward $serverName $dbUserName $dbPassword $site_id $user_name $printer_name 2>&1"; // 
			exec($cmd, $output, $return);
			//print_r($output);
			if(strpos(end($output), 'Empty Extractor') !== false){
				$response = json_encode(array('type'=>'Fail', 'dlink'=>end($output)));
			} else{
				$response = json_encode(array('type'=>'Success', 'dlink'=>end($output)));
			}
			//$response = json_encode(array('type'=>'Success', 'dlink'=>end($output)));
			
        }else{
            $folder="-";
            $pyscript = $directoryUrlBackward."Python_files\\preview_multi.py"; 
            $cmd = "$pyscript $template_id $folder $user_id $entry_type $progressfile ".$dbName." ".$subdomain[0]." ".$directoryUrlForward." ".$directoryUrlBackward." ".$serverName." ".$dbUserName." ".$dbPassword." $site_id $user_name $printer_name 2>&1";
            exec($cmd, $output, $return);
            //print_r($output);
			$response = json_encode(array('type'=>'Success', 'dlink'=>end($output)));
        }
        echo $response;
    }
    public function excelToPdf(){
		return view('admin.pdf2pdf.excel2pdf.index');
    } 
}
