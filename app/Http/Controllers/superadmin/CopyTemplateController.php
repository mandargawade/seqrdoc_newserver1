<?php

namespace App\Http\Controllers\superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminRoleRequest;
use App\Jobs\SuperAdminRolePermissionJob;
use App\Models\Site;
use App\Models\demo\SitesSuperdata;
use App\Models\SitePermission;
use App\models\AclPermission;
use App\models\SuperAdmin;
use App\models\SuperAdminLogin;
use App\models\SystemConfig;
use App\models\UserPermission;
use App\models\RolePermission;
use App\models\Role;
use App\models\PaymentGateway;
use App\models\PaymentGatewayConfig;
use App\models\Superapp\InstanceList;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Admin;
use Hash;
use App\Models\TemplateMaster;
use App\models\FieldMaster;
use Auth;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

class CopyTemplateController extends Controller
{
    
    public function index(Request $request){

       if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( sites_name like \"%{$search}%\""
               
                . ")";
            }  
           $status=$request->get('status');
           /* if($status==1)
            {
                $status='1';
                $where_str.= " and (sites_superdata.status =$status)";
            }
            else if($status==0)
            {
                $status='0';
                $where_str.=" and (sites_superdata.status= $status)";
            } */
                                                    
             //for serial number
            DB::statement(DB::raw('set @rownum=0'));  
           // DB::statement(DB::raw('set @site_url=SUBSTRING_INDEX(site_url, ".", 1)'));   
            $columns = ['id','sites_name','template_count','pdf2pdf_template_count','custom_templates','site_id'];

            $columnsQuery = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),DB::raw('@site_url :=SUBSTRING_INDEX(sites_superdata.sites_name, ".", 1) AS site_url'),DB::raw('(template_number+inactive_template_number) AS template_count'),DB::raw('(pdf2pdf_active_templates+pdf2pdf_inactive_templates) AS pdf2pdf_template_count'),'custom_templates','site_id'];
            
            /*$columnsQuery = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),DB::raw('@site_url :=SUBSTRING_INDEX(sites.site_url, ".", 1) AS site_url'),DB::raw('@dbInstance :=IF(@site_url="demo","seqr_demo",CONCAT("seqr_d","_",@site_url)) as dbInstance'),DB::raw('(SELECT count(*) FROM `@dbInstance`.`template_master` WHERE site_id=sites.site_id) as template_count'),'site_id'];
*/
            $font_master_count = SitesSuperdata::select($columnsQuery)
                ->whereRaw($where_str, $where_params)
               
                ->count();
  
            $fontMaster_list = SitesSuperdata::select($columnsQuery)
                 
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
            $fontMaster_list_array=$fontMaster_list->toArray();
/*             print_r($fontMaster_list_array);

            if($fontMaster_list_array){
                foreach ($fontMaster_list_array as $key => $value) {
                    
                   
                    $subdomain = explode('.', $value['site_url']);
                    //$value['site_url']=$subdomain[0];
                    if($subdomain[0] == 'demo')
                    {
                        $dbName = 'seqr_'.$subdomain[0];
                    }
                    else{
                        $dbName = 'seqr_d_'.$subdomain[0];
                    }
                     
                    $data=DB::select(DB::raw('SELECT count(*) as template_count FROM `'.$dbName.'`.`template_master`'));
                    if(isset($data[0])){
                        $template_count=$data[0]->template_count;
                    }else{
                        $template_count=0;
                    }
			
                    $value = array_slice($value, 0, 2, true) +
                            array("template_count" => $template_count) +
                            array_slice($value, 2, count($value) - 1, true) ;

                   
                    $fontMaster_list_array[$key]=$value;
                    

                }
            }*/
             
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list_array;
            

            
            return $response;
        }
        return view('superadmin.copytemplate.index');
    }
     /**
     * Show the form for creating a new Role.
     *
     * @return view response
     */
     

    public function viewtemplates($instance)
    {
        $instancesList = Site::select(DB::raw('SUBSTRING_INDEX(sites.site_url, ".", 1) as site_url'));
        $instancesList = $instancesList->orderBy('site_url','ASC');   
         $instancesList = $instancesList->get();
            $instancesListArray=$instancesList->toArray();
        return view('superadmin.copytemplate.templateslist',compact('instance','instancesListArray'));
    }

    public function viewtemplateslist(Request $request){

       if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( template_name like \"%{$search}%\"
                                 OR  status like \"%{$search}%\""
                . ")";
            }  
           $instance=$request->get('instance');


        $subdomain = explode('.', $instance);

       
       if($subdomain[0] == 'demo')
        {
            $dbName = 'seqr_'.$subdomain[0];
        }
        else{
            $dbName = 'seqr_d_'.$subdomain[0];
        }
                     
             //for serial number
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name','status','id'];
            
           

            $font_master_count = DB::table($dbName.'.template_master')
                        ->whereRaw($where_str, $where_params)
                        ->count();

        
            $fontMaster_list = DB::table($dbName.'.template_master')->select($columns)
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
            $fontMaster_list_array=$fontMaster_list->toArray();
           
             
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list_array;
            

            
            return $response;
        }
        return view('superadmin.copytemplate.templateslist');
    }

    public function copyTemplate(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){

        
        


        $domainSource=$request->source_instance;
        $subdomainSource = explode('.', $domainSource);

       $domainDest=$request->instance;
        $subdomainDest = explode('.', $domainDest);

       if($subdomainSource[0] == 'demo')
        {
            $dbNameSource = 'seqr_'.$subdomainSource[0];
        }
        else{
            $dbNameSource = 'seqr_d_'.$subdomainSource[0];
        }



         if($subdomainDest[0] == 'demo')
        {
            $dbNameDest = 'seqr_'.$subdomainDest[0];
        }
        else{
            $dbNameDest = 'seqr_d_'.$subdomainDest[0];
        }


       
         $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

     

        if(!empty($request['template_id']))
       {
         
        $template_id=$request['template_id'];
       
        //Source Template Data
        $templateData=DB::select(DB::raw('SELECT * FROM `'.$dbNameSource.'`.`template_master` WHERE id ="'.$request['template_id'].'"'));
        $copyTemplate_data=(array)$templateData[0];
        unset($copyTemplate_data['id']);

        
        $old_Template_name=$copyTemplate_data['template_name'];
        
        $copyTemplate_data['actual_template_name']=$copyTemplate_data['actual_template_name'];
        $copyTemplate_data['template_name']=$copyTemplate_data['template_name'];  
     
        $alreadyCopyData=DB::select(DB::raw('SELECT * FROM `'.$dbNameDest.'`.`template_master` WHERE actual_template_name ="'.$copyTemplate_data['actual_template_name'].'"'));             
      
        

        #already_copy check
        if(empty($alreadyCopyData))
        {
            //print_r($domainDest);

            $siteUrl=$domainDest.'.seqrdoc.com';
            //Site id
            $siteData=DB::select(DB::raw('SELECT site_id FROM `seqr_demo`.`sites` WHERE site_url ="'.$siteUrl.'"'));
            $site_id=$siteData[0]->site_id;

            //Copy BG data 
            $newBgTemplateId=0;
            if(!empty($copyTemplate_data['bg_template_id'])){
                $bgData=DB::select(DB::raw('SELECT * FROM `'.$dbNameSource.'`.`background_template_master` WHERE id ="'.$copyTemplate_data['bg_template_id'].'"'));
                $copyBgTemplate_data=(array)$bgData[0];
                $copyBgTemplate_data['site_id']=$site_id;
                unset($copyBgTemplate_data['id']);
                $newBgTemplateId=DB::table($dbNameDest.'.background_template_master')->insertGetId($copyBgTemplate_data);

                $old_BgTemplate_file=public_path().'/'.$subdomainSource[0].'/'.\Config::get('constant.bg_images').'/'.$copyBgTemplate_data['image_path'];
                $new_BgTemplate_file=public_path().'/'.$subdomainDest[0].'/'.\Config::get('constant.bg_images').'/'.$copyBgTemplate_data['image_path'];
                if (!file_exists($new_BgTemplate_file)) {
                    
                    \File::copy($old_BgTemplate_file,$new_BgTemplate_file);
                }


               
            }

            //Old Field Data
            $field_data=DB::select(DB::raw('SELECT * FROM `'.$dbNameSource.'`.`fields_master` WHERE template_id ="'.$request['template_id'].'"'));                      
            $field_data=(array)$field_data;

            
            $copyTemplate_data['site_id']=$site_id;
            if(!empty($newBgTemplateId)){
                $copyTemplate_data['bg_template_id']=$newBgTemplateId;
            }
            
            $copyTemplate_data['status']=1;
            unset($copyTemplate_data['scanning_fee']);

            //Destination template id
            $newTemplateId=DB::table($dbNameDest.'.template_master')->insertGetId($copyTemplate_data);
           
            foreach ($field_data as $key => $value) {

               $field_data[$key]=(array)$field_data[$key];

               $field_data[$key]['template_id']=$newTemplateId;
               if(empty($field_data[$key]['is_transparent_image'])){
                $field_data[$key]['is_transparent_image']=0;
               }
               unset($field_data[$key]['id']);

               unset($field_data[$key]['character_spacing']);
               unset($field_data[$key]['increment_step_value']);

               DB::table($dbNameDest.'.fields_master')->insert($field_data[$key]);
            }

            $font_data=DB::select(DB::raw('SELECT DISTINCT font_id FROM `'.$dbNameSource.'`.`fields_master` WHERE template_id ="'.$request['template_id'].'" AND font_id!=0'));  
            if($font_data){

                foreach ($font_data as $readFont) {
                    
                   
                    $fontMaster_data=DB::select(DB::raw('SELECT * FROM `'.$dbNameSource.'`.`font_master` WHERE id ="'.$readFont->font_id.'"'));                      
                    $fontMaster_data=(array)$fontMaster_data[0];
                    $oldFontId=$fontMaster_data['id'];


                    $fontMaster_data_dest=DB::select(DB::raw('SELECT * FROM `'.$dbNameSource.'`.`font_master` WHERE id ="'.$readFont->font_id.'"'));             
                    if($fontMaster_data_dest){
                        
                        $fontMaster_data_dest=(array)$fontMaster_data_dest[0];
                        DB::select(DB::raw('UPDATE `'.$dbNameDest.'`.`fields_master` SET font_id="'.$fontMaster_data_dest['id'].'"   WHERE template_id ="'.$newTemplateId.'" AND font_id="'.$oldFontId.'"'));
                    

                        if(!empty($fontMaster_data['font_filename_N'])&&empty($fontMaster_data_dest['font_filename_N'])){

                        $old_fontTemplate_file=public_path().'/'.$subdomainSource[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_N'];
                        $new_fontTemplate_file=public_path().'/'.$subdomainDest[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_N'];
                        if (!file_exists($new_fontTemplate_file)) {
                            
                            \File::copy($old_fontTemplate_file,$new_fontTemplate_file);
                        }

                       DB::select(DB::raw('UPDATE `'.$dbNameDest.'`.`font_master` SET font_filename_N="'.$fontMaster_data['font_filename_N'].'" WHERE id="'.$fontMaster_data_dest['id'].'"')); 
                    }
                    if(!empty($fontMaster_data['font_filename_B'])&&empty($fontMaster_data_dest['font_filename_B'])){

                        $old_fontTemplate_file=public_path().'/'.$subdomainSource[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_B'];
                        $new_fontTemplate_file=public_path().'/'.$subdomainDest[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_B'];
                        if (!file_exists($new_fontTemplate_file)) {
                            
                            \File::copy($old_fontTemplate_file,$new_fontTemplate_file);
                        }

                      DB::select(DB::raw('UPDATE `'.$dbNameDest.'`.`font_master` SET font_filename_B="'.$fontMaster_data['font_filename_B'].'" WHERE id="'.$fontMaster_data_dest['id'].'"'));  
                    }  
                    if(!empty($fontMaster_data['font_filename_I'])&&empty($fontMaster_data_dest['font_filename_B'])){

                        $old_fontTemplate_file=public_path().'/'.$subdomainSource[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_I'];
                        $new_fontTemplate_file=public_path().'/'.$subdomainDest[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_I'];
                        if (!file_exists($new_fontTemplate_file)) {
                            
                            \File::copy($old_fontTemplate_file,$new_fontTemplate_file);
                        }
                        DB::select(DB::raw('UPDATE `'.$dbNameDest.'`.`font_master` SET font_filename_I="'.$fontMaster_data['font_filename_I'].'" WHERE id="'.$fontMaster_data_dest['id'].'"'));
                    }  
                    if(!empty($fontMaster_data['font_filename_BI'])&&empty($fontMaster_data_dest['font_filename_BI'])){

                        $old_fontTemplate_file=public_path().'/'.$subdomainSource[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_BI'];
                        $new_fontTemplate_file=public_path().'/'.$subdomainDest[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_BI'];
                        if (!file_exists($new_fontTemplate_file)) {
                            
                            \File::copy($old_fontTemplate_file,$new_fontTemplate_file);
                        }
                        DB::select(DB::raw('UPDATE `'.$dbNameDest.'`.`font_master` SET font_filename_BI="'.$fontMaster_data['font_filename_BI'].'" WHERE id="'.$fontMaster_data_dest['id'].'"'));
                    }
                    }else{
                    
                    unset($fontMaster_data['id']);
                    $fontMaster_data['site_id']=$site_id;
                    $font_id_new=DB::table($dbNameDest.'.font_master')->insertGetId($fontMaster_data);

                    DB::select(DB::raw('UPDATE `'.$dbNameDest.'`.`fields_master` SET font_id="'.$font_id_new.'"   WHERE template_id ="'.$newTemplateId.'" AND font_id="'.$oldFontId.'"'));
                     
                    if(!empty($fontMaster_data['font_filename_N'])){

                        $old_fontTemplate_file=public_path().'/'.$subdomainSource[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_N'];
                        $new_fontTemplate_file=public_path().'/'.$subdomainDest[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_N'];
                        if (!file_exists($new_fontTemplate_file)) {
                            
                            \File::copy($old_fontTemplate_file,$new_fontTemplate_file);
                        }
                    }
                    if(!empty($fontMaster_data['font_filename_B'])){

                        $old_fontTemplate_file=public_path().'/'.$subdomainSource[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_B'];
                        $new_fontTemplate_file=public_path().'/'.$subdomainDest[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_B'];
                        if (!file_exists($new_fontTemplate_file)) {
                            
                            \File::copy($old_fontTemplate_file,$new_fontTemplate_file);
                        }
                    }  
                    if(!empty($fontMaster_data['font_filename_I'])){

                        $old_fontTemplate_file=public_path().'/'.$subdomainSource[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_I'];
                        $new_fontTemplate_file=public_path().'/'.$subdomainDest[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_I'];
                        if (!file_exists($new_fontTemplate_file)) {
                            
                            \File::copy($old_fontTemplate_file,$new_fontTemplate_file);
                        }
                    }  
                    if(!empty($fontMaster_data['font_filename_BI'])){

                        $old_fontTemplate_file=public_path().'/'.$subdomainSource[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_BI'];
                        $new_fontTemplate_file=public_path().'/'.$subdomainDest[0].'/'.\Config::get('constant.fonts').'/'.$fontMaster_data['font_filename_BI'];
                        if (!file_exists($new_fontTemplate_file)) {
                            
                            \File::copy($old_fontTemplate_file,$new_fontTemplate_file);
                        }
                    }           
                    

                }
                }
            }                    
            
            if($get_file_aws_local_flag->file_aws_local == '1'){
                /*file get all inside folder*/
                $copy_Template_file=Storage::disk('s3')->allFiles($subdomainSource[0].'/'.\Config::get('constant.canvas').'/'.$template_id);
        
                 /*new Folder to Copy All File*/
                $new_Template_file="backend/templates/".$copyTemplate_data['template_name'].'/';
            }
            else{
                $copy_Template_file= glob(public_path().'/'.$subdomainSource[0].'/backend/templates/'.$template_id."/*");
                $new_Template_file=public_path().'/'.$subdomainDest[0]."/backend/templates/".$newTemplateId.'/';

                 if(!is_dir($new_Template_file)){
                
                            mkdir($new_Template_file, 0777);
                        }
            }

        foreach ($copy_Template_file as $key => $value) {

          

            if($get_file_aws_local_flag->file_aws_local == '1'){
                $image_name_get=str_replace('/'.$subdomainSource[0].'/'.\Config::get('constant.canvas').'/'.$old_Template_name.'/','', $value);

                if (!Storage::disk('s3')->exists($value,$new_Template_file.$image_name_get)) {
                    # code...
                    
                    Storage::disk('s3')->copy($value,$new_Template_file.$image_name_get);
                }
            }
            else{
                $image_name_get=str_replace(public_path().'/'.$subdomainSource[0].'/backend/templates/'.$template_id.'/','', $value);
               $destFile=$new_Template_file.$image_name_get;
                if (!file_exists($destFile)) {
                    # code...
                    
                    \File::copy($value,$destFile);
                }
            }

        }


        return response()->json(['success'=>true,'msg'=>'Template copied successfully']);

        }
      else
      {
         return response()->json(['success'=>false,'msg'=>'This template already copied']);
      }

     }
    }



     public function viewtemplatespdf2pdf($instance)
    {
        $instancesList = Site::select(DB::raw('SUBSTRING_INDEX(sites.site_url, ".", 1) as site_url'));
        $instancesList = $instancesList->orderBy('site_url','ASC');   
         $instancesList = $instancesList->get();
            $instancesListArray=$instancesList->toArray();
        return view('superadmin.copytemplate.templateslistpdf2pdf',compact('instance','instancesListArray'));
    
        
    }

 public function searchForId($id, $array,$keyCheck) {
   foreach ($array as $key => $val) {
//    print_r($val);

       if (preg_replace('/\s+/', '',$val->$keyCheck) === preg_replace('/\s+/', '',$id)) {
           return $key;
       }
   }
   return -1;
}
    public function viewtemplateslistpdf2pdf(Request $request){

       if($request->ajax()){

            $where_str    = "1 = ?";
            $where_params = array(1); 

            if (!empty($request->input('sSearch')))
            {
                $search     = $request->input('sSearch');
                $where_str .= " and ( template_name like \"%{$search}%\"
                                 OR  publish like \"%{$search}%\""
                . ")";
            }  
           $instance=$request->get('instance');


        $subdomain = explode('.', $instance);

       
       if($subdomain[0] == 'demo')
        {
            $dbName = 'seqr_'.$subdomain[0];
        }
        else{
            $dbName = 'seqr_d_'.$subdomain[0];
        }
                            
             //for serial number
            DB::statement(DB::raw('set @rownum=0'));   
            $columns = [DB::raw('@rownum  := @rownum  + 1 AS rownum'),'template_name','publish','id'];
            
           

            $font_master_count = DB::table($dbName.'.uploaded_pdfs')
                        ->whereRaw($where_str, $where_params)
                        ->count();

            $fontMaster_list = DB::table($dbName.'.uploaded_pdfs')->select($columns)
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
            $fontMaster_list_array=$fontMaster_list->toArray();
           
             
            $response['iTotalDisplayRecords'] = $font_master_count;
            $response['iTotalRecords'] = $font_master_count;
            $response['sEcho'] = intval($request->input('sEcho'));
            $response['aaData'] = $fontMaster_list_array;
            

            
            return $response;
        }
        return view('superadmin.copytemplate.templateslistpdf2pdf');
    }

    public function copyTemplatePdf2pdf(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        


        $domainSource=$request->source_instance;
        $subdomainSource = explode('.', $domainSource);

       $domainDest=$request->instance;
        $subdomainDest = explode('.', $domainDest);

       if($subdomainSource[0] == 'demo')
        {
            $dbNameSource = 'seqr_'.$subdomainSource[0];
        }
        else{
            $dbNameSource = 'seqr_d_'.$subdomainSource[0];
        }



         if($subdomainDest[0] == 'demo')
        {
            $dbNameDest = 'seqr_'.$subdomainDest[0];
        }
        else{
            $dbNameDest = 'seqr_d_'.$subdomainDest[0];
        }



         $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

     

        if(!empty($request['template_id']))
       {
         
        $template_id=$request['template_id'];
       
        //Source Template Data
        $templateData=DB::select(DB::raw('SELECT * FROM `'.$dbNameSource.'`.`uploaded_pdfs` WHERE id ="'.$request['template_id'].'"'));
        $copyTemplate_data=(array)$templateData[0];
        unset($copyTemplate_data['id']);

        

        $old_Template_name=$copyTemplate_data['template_name'];
        $copyTemplate_data['template_name']=$copyTemplate_data['template_name'];
        $copyTemplate_data['publish']=1;

        $extractor_details=$copyTemplate_data['extractor_details'];
        $placer_details=$copyTemplate_data['placer_details'];

        $alreadyCopyData=DB::select(DB::raw('SELECT * FROM `'.$dbNameDest.'`.`uploaded_pdfs` WHERE template_name ="'.$copyTemplate_data['template_name'].'"'));             
        
        
        #already_copy check
        if(empty($alreadyCopyData))
        {
            $siteUrl=$domainDest.'.seqrdoc.com';
            //Site id
            $siteData=DB::select(DB::raw('SELECT site_id FROM `seqr_demo`.`sites` WHERE site_url ="'.$siteUrl.'"'));
            $site_id=$siteData[0]->site_id;

            $source_path_pdf=public_path()."/".$subdomainSource[0]."/".$copyTemplate_data['file_name'];
          
            //Destination template id
            $newTemplateId=DB::table($dbNameDest.'.uploaded_pdfs')->insertGetId($copyTemplate_data);
        
                //Create required directories
                $dest_path_pdf=public_path()."/".$subdomainDest[0]."/uploads/pdfs";
                if(!is_dir($dest_path_pdf)){
                      //Directory does not exist, then create it.
                      mkdir($dest_path_pdf, 0777);
                }

                $dest_path_json=public_path()."/".$subdomainDest[0]."/documents";
                if(!is_dir($dest_path_json)){
                      //Directory does not exist, then create it.
                      mkdir($dest_path_json, 0777);
                }

                $dest_path_images=public_path()."/".$subdomainDest[0]."/backend/templates/pdf2pdf_images";
                if(!is_dir($dest_path_images)){
                      //Directory does not exist, then create it.
                      mkdir($dest_path_images, 0777);
                }

                //PDF
                $fileNamePdf=$copyTemplate_data['file_name'];

                $tempFile = pathinfo($source_path_pdf);
                //$fileName=$newTemplateId.'_'.$tempFile['basename'];
                $fileName=$tempFile['basename'];

                $dest_path_pdf=$dest_path_pdf.'/'.$fileName;
                
                if (!file_exists($dest_path_pdf)) {
                    # code...
                    \File::copy($source_path_pdf,$dest_path_pdf);
                }

                $source_path_json=public_path()."/".$subdomainSource[0]."/documents/".$copyTemplate_data['template_name'].'.json';

                $tempFile = pathinfo($source_path_json);
                //$fileName=$newTemplateId.'_'.$tempFile['basename'];
                $fileName=$tempFile['basename'];

                $dest_path_json=$dest_path_json.'/'.$fileName;
                
                if (!file_exists($dest_path_json)) {
                    # code...
                    \File::copy($source_path_json,$dest_path_json);
                }

                if(!empty($placer_details)&&$placer_details!="[]"){
                    $data=json_decode($placer_details);
                    foreach ($data as $readData) {
                        if(isset($readData->image_path)&&!empty($readData->image_path)){
                            $source_path_image=public_path()."/".$subdomainSource[0]."/backend/templates/pdf2pdf_images/".$readData->image_path;
                            $dest_path_image=public_path()."/".$subdomainDest[0]."/backend/templates/pdf2pdf_images/".$readData->image_path;
                            if (!file_exists($dest_path_image)) {
                                # code...       
                                \File::copy($source_path_image,$dest_path_image);
                            }
                        }
                    }
                }

                if(!empty($extractor_details)&&$extractor_details!="[]"){
                    $data=json_decode($extractor_details);
                    foreach ($data as $readData) {
                        if(isset($readData->image_path)&&!empty($readData->image_path)){
                            $source_path_image=public_path()."/".$subdomainSource[0]."/backend/templates/pdf2pdf_images/".$readData->image_path;
                            $dest_path_image=public_path()."/".$subdomainDest[0]."/backend/templates/pdf2pdf_images/".$readData->image_path;
                            if (!file_exists($dest_path_image)) {
                                # code...       
                                \File::copy($source_path_image,$dest_path_image);
                            }
                        }
                    }
                }
                
               


                DB::select(DB::raw('UPDATE `'.$dbNameDest.'`.`uploaded_pdfs` SET file_name="'.$fileNamePdf.'" WHERE id="'.$newTemplateId.'"')); 
        return response()->json(['success'=>true,'msg'=>'Template copied successfully']);

        }
      else
      {
         return response()->json(['success'=>false,'msg'=>'This template already copied']);
      }

     }
    }
}
