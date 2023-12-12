<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use App\models\FieldMaster;
use App\models\SuperAdmin;
use App\models\SiteDocuments;
use Auth,Storage;
use App\models\SystemConfig;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\Helpers\CoreHelper;

class TemplateJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $get_template_data;
    public function __construct($get_template_data)
    {
        $this->get_template_data = $get_template_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
    
        //get data from listener
        $get_template_data = $this->get_template_data;
          //Checking field names for combo qr
         if(isset($get_template_data['field_extra_name'])){
            $extraFieldCount=count($get_template_data['field_extra_name']);
            $extraFieldArr=$get_template_data['field_extra_name'];
        }else{
            $extraFieldCount=0;
            $extraFieldArr=array();
        }
        
        $id = $request->id;
        
        //if edit
        if(isset($get_template_data['template_id'])){
            $id = $get_template_data['template_id'];
        }
        //check id is exist in db or not if exist then update else create
        //save data in tempalte master
        $site_id=Auth::guard('admin')->user()->site_id;

        $save_template_data = TemplateMaster::firstorNew(['id'=>$id]);
        $save_template_data->site_id=$site_id;
        $save_template_data->fill($get_template_data);
        //get login user id
        $admin_id = \Auth::guard('admin')->user()->toArray();

        //if extra field is empty
        if(isset($get_template_data['field_extra_name'])){
            $arr = array('field_extra_name', 'field_extra_security_type', 'field_extra_font_id', 'field_extra_font_size', 
                'field_extra_font_color', 'field_extra_x', 'field_extra_y', 'field_extra_width', 'field_extra_height','length');

            foreach ($arr as $value_a) {
                foreach ($get_template_data as $key => $value) {
                    if(in_array($key, $arr)){
                        if($value[0] == '')
                        {
                            
                        }else if($key == 'field_extra_font_color' && !ctype_xdigit($value[0])) {
                            $message = Array('type' => 'error', 'message' => 'Invalid color field parameters' );
                            return $message;
                        }
                        else if($key == 'length' && (int)$value[0] >= 11) {
                            $message = Array('type' => 'error', 'message' => $key.' field parameters between 1 to 10' );
                            return $message;
                        }
                    }
                }
            }
        }

        /**Blockchain**/
        if($subdomain[0]=="demo"&&isset($_POST['is_block_chain'])&&$_POST['is_block_chain']==1){

            if(empty($_POST['bc_document_description'])){
                return $message = Array('type' => 'error', 'message' => 'Document description is required.' );
            }

            if(empty($_POST['bc_document_type'])){
               return $message = Array('type' => 'error', 'message' => 'Document type is required.' );
            }
            if(isset($_POST['is_meta_data'])){
                $metaDataArr=$_POST['is_meta_data'];
                $remove = array(0);
                $result = array_diff($metaDataArr, $remove);  
                $metaDataCount=count($result);
                if($metaDataCount>5){
                     $message = Array('type' => 'error', 'message' => 'Maximum 5 meta data fields are allowed for block chain. You have selected '.$metaDataCount.' fields' );
                                return $message;
                }
            }
        }elseif($subdomain[0]=="demo"&&!empty($id)&&isset($_POST['is_block_chain'])&&$_POST['is_block_chain']==0){

            $prev_template_data= TemplateMaster::select('is_block_chain')->where('id',$id)->first();

            if(!empty($prev_template_data)&&$prev_template_data->is_block_chain==1){
                return $message = Array('type' => 'error', 'message' => 'You can not disable Upload Data To Blockchain when it once enabled.' );
            }

        }
        /**End Blockchain**/

        if(isset($get_template_data['actual_template_name'])){
            $save_template_name = $get_template_data['actual_template_name'];
        }
        else{
            $save_template_name = $get_template_data['template_name'];
        }
        $save_template_data->actual_template_name = $save_template_name;
        //$get_template_data['template_name'] = str_replace(' ','',$get_template_data['template_name']);
        $get_template_data['template_name'] = str_replace(' ','',$save_template_name);
        $save_template_data->template_name = $get_template_data['template_name'];
        $save_template_data->created_by = $admin_id['id'];
        $save_template_data->updated_by = $admin_id['id'];
        $save_template_data->status = $get_template_data['template_status'];
        $save_template_data->bg_template_id = (int)$get_template_data['bg_template_id'];
        $save_template_data->background_template_status = $get_template_data['print_with_background'];
        //if after save we refresh the create page so using this we get id data during create

        /**Blockchain**/
        if($subdomain[0]=="demo"&&isset($_POST['is_block_chain'])&&$_POST['is_block_chain']==1){
        $save_template_data->is_block_chain = $get_template_data['is_block_chain'];
        $save_template_data->bc_document_description = $get_template_data['bc_document_description'];
        $save_template_data->bc_document_type = $get_template_data['bc_document_type'];
        }
        /**End Blockchain**/

        $save_template_data->create_refresh = 1;
        $save_template_data->save();


        
        $dbName = 'seqr_demo';
        
        \DB::disconnect('mysql'); 
        
        \Config::set("database.connections.mysql", [
            'driver'   => 'mysql',
            'host'     => \Config::get('constant.SDB_HOST'),
            'port' => \Config::get('constant.SDB_PORT'),
            'database' => \Config::get('constant.SDB_NAME'),
            'username' => \Config::get('constant.SDB_UN'),
            'password' => \Config::get('constant.SDB_PW'),
            "unix_socket" => "",
            "charset" => "utf8mb4",
            "collation" => "utf8mb4_unicode_ci",
            "prefix" => "",
            "prefix_indexes" => true,
            "strict" => true,
            "engine" => null,
            "options" => []
        ]);
        \DB::reconnect();
        $templates_count = TemplateMaster::select('id')->where('site_id',$site_id)->count();

        SiteDocuments::where('site_id',$site_id)
        
        ->update(['template_number'=>$templates_count]);
        if($subdomain[0] == 'demo')
        {
            $dbName = 'seqr_'.$subdomain[0];
        }else{

            $dbName = 'seqr_d_'.$subdomain[0];
        }
        \DB::disconnect('mysql'); 
        
        \Config::set("database.connections.mysql", [
            'driver'   => 'mysql',
            'host'     => \Config::get('constant.DB_HOST'),
            "port" => \Config::get('constant.DB_PORT'),
            'database' => $dbName,
            'username' => \Config::get('constant.DB_UN'),
            'password' => \Config::get('constant.DB_PW'),
            "unix_socket" => "",
            "charset" => "utf8mb4",
            "collation" => "utf8mb4_unicode_ci",
            "prefix" => "",
            "prefix_indexes" => true,
            "strict" => true,
            "engine" => null,
            "options" => []
        ]);
        \DB::reconnect();
        //add template no to super admin table
        if(!isset($get_template_data['template_id'])){
            $get_max_template_count = SuperAdmin::select('current_value')->where('property','max_templates')->first();
            $increase_template_count = $get_max_template_count['current_value']+1;
            //store count in super admin
            SuperAdmin::where('property','max_templates')->update(['current_value'=>$increase_template_count]);
        }
        //delete data of field master during edit

        if(isset($get_template_data['template_id'])){
            
            FieldMaster::where('template_id',$id)->delete();
        }
        //save data in field master of qr code
        if(isset($get_template_data['field_qr_x'])){
            $save_field_qr_data = new FieldMaster();
            $save_field_qr_data->template_id = $save_template_data->id;
            $save_field_qr_data->name = 'QR Code';
            $save_field_qr_data->security_type = 'QR Code';
            $save_field_qr_data->field_position = 1;
            $save_field_qr_data->text_justification = 'C';
            if(isset($get_template_data['field_qr_x1'])){
                $x_pos = $get_template_data['field_qr_x1'];
            }
            else{
                $x_pos = $get_template_data['field_qr_x'];
            }

            if(isset($get_template_data['field_qr_y1'])){
                $y_pos = $get_template_data['field_qr_y1'];
            }
            else{
                $y_pos = $get_template_data['field_qr_y'];
            }
            //if add image in qr code then set include image 1
            $include_image = 0;
           
            if(!empty($get_template_data['field_qr_image'])&&$get_template_data['field_qr_image']!='null'){
                $include_image = 1;
            }

            $save_field_qr_data->include_image = $include_image;
            $save_field_qr_data->x_pos = $x_pos;
            $save_field_qr_data->Y_pos = $y_pos;
            $save_field_qr_data->width = $get_template_data['field_qr_width'];
            $save_field_qr_data->height = $get_template_data['field_qr_height'];
            $save_field_qr_data->sample_image = $get_template_data['field_qr_image'];
            $mapped_qr_data = '';
            if(isset($get_template_data['field_qr_mapped'])){
                $mapped_qr_data = $get_template_data['field_qr_mapped'];
            }
            if(isset($get_template_data['field_qr_combo_qr_text1'])){
               $save_field_qr_data->combo_qr_text = $get_template_data['field_qr_combo_qr_text1'];
            }
            else{
                $save_field_qr_data->combo_qr_text = $get_template_data['field_qr_combo_qr_text'];
            }
            // Encrypted QR Code Store statement
            if($subdomain[0] == 'demo')
            {
                // if(isset($get_template_data['is_encrypted_qr'])){
                if(isset($get_template_data['is_encrypted_qr'][0])){

                    $save_field_qr_data->is_encrypted_qr = $get_template_data['is_encrypted_qr'];
                    if($get_template_data['is_encrypted_qr'] == 1) {
                        if(isset($get_template_data['encrypted_qr_text'])){
                            $save_field_qr_data->encrypted_qr_text = $get_template_data['encrypted_qr_text'];
                        }
                    }


                }
            }
            // \ Encrypted QR Code Store statement

            $save_field_qr_data->mapped_name = $mapped_qr_data;
            $save_field_qr_data->is_mapped = $get_template_data['is_mapped_qr'];
            $save_field_qr_data->font_style = '';
            $save_field_qr_data->font_id = null;
            $save_field_qr_data->font_size = '0';
            $save_field_qr_data->font_color = '0';
            $save_field_qr_data->created_by = $admin_id['id'];
            $save_field_qr_data->updated_by = $admin_id['id'];
            $save_field_qr_data->save();
        }


        //save barcode data inside field master

        if(isset($get_template_data['field_id_x'])){
            $save_field_barcode_data = new FieldMaster();
            $save_field_barcode_data->template_id = $save_template_data->id;
            $save_field_barcode_data->name = 'ID Barcode';
            $save_field_barcode_data->security_type = 'ID Barcode';
            $save_field_barcode_data->field_position = 2;
            $save_field_barcode_data->text_justification = 'C';
            if(isset($get_template_data['field_id_x1'])){
                $x_pos = $get_template_data['field_id_x1'];
            }
            else{
                $x_pos = $get_template_data['field_id_x'];
            }

            if(isset($get_template_data['field_id_y1'])){
                $y_pos = $get_template_data['field_id_y1'];
            }
            else{
                $y_pos = $get_template_data['field_id_y'];
            }

            if(isset($get_template_data['field_id_width1'])){
                $width = $get_template_data['field_id_width1'];
            }
            else{
                $width = $get_template_data['field_id_width'];
            }

            if(isset($get_template_data['field_id_height1'])){
                $height = $get_template_data['field_id_height1'];
            }
            else{
                $height = $get_template_data['field_id_height'];
            }

            if(isset($get_template_data['field_id_visible'])){
                $field_id_visible = $get_template_data['field_id_visible'];
            }
            else{
                $field_id_visible = $get_template_data['field_id_visible1'];
            }

            if(isset($get_template_data['field_id_varification'])){
                $field_id_varification = $get_template_data['field_id_varification'];
            }
            else{
                $field_id_varification = $get_template_data['field_id_varification1'];
            }

            $save_field_barcode_data->x_pos = $x_pos;
            $save_field_barcode_data->Y_pos = $y_pos;
            $save_field_barcode_data->width = $width;
            $save_field_barcode_data->height = $height;
            $save_field_barcode_data->visible = $field_id_visible;
            $save_field_barcode_data->visible_varification = $field_id_varification;
            $save_field_barcode_data->mapped_name = $get_template_data['field_id_mapped'];
            $mapped_id_data = '';
            if(isset($get_template_data['is_mapped_id'])){
                $mapped_id_data = $get_template_data['is_mapped_id'];
            }
            $save_field_barcode_data->is_mapped = $mapped_id_data;
            $save_field_barcode_data->font_style = '';
            $save_field_barcode_data->font_id = null;
            $save_field_barcode_data->font_size = '0';
            $save_field_barcode_data->font_color = '0';
            $save_field_barcode_data->created_by = $admin_id['id'];
            $save_field_barcode_data->updated_by = $admin_id['id'];
            $save_field_barcode_data->save();

           
        }

            $saved_template_id = $save_template_data->id;

        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        if($get_file_aws_local_flag->file_aws_local == '1'){
            $sandbox_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
            //if directory not exist make directory
            if(!is_dir($sandbox_directory)){
    
                mkdir($sandbox_directory, 0777);
            }
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $check_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/';
            }
            else{
                $check_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/';
            }
        }
        else{
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $sandbox_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                //if directory not exist make directory
                if(!is_dir($sandbox_directory)){
        
                    mkdir($sandbox_directory, 0777);
                }

                $check_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/';
            }
            else{
                $check_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/';
            }
        }
        //check directory exist or not
        if(!is_dir($check_directory)){
            //Directory does not exist, then create it.
            mkdir($check_directory, 0777);
        }

        $default_image = \Config::get('constant.default_image');
        if($get_file_aws_local_flag->file_aws_local == '1'){
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $destination_path = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/'.$default_image;
            }
            else{
                $destination_path = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/'.$default_image;
            }
        }
        else{
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $destination_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/'.$default_image;
            }
            else{
                $destination_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/'.$default_image;
            }
        }

        //save extra field
        $count = 0;//count extra field initially as 0
        if(isset($get_template_data['field_extra_name'])){
            $count = count($get_template_data['field_extra_name']);//count extra field

            //if extra field is more than 1 so loop
            for ($i=0; $i <$count ; $i++) { 
                $save_extra_field_data = new FieldMaster();
                //set field position
                $pos = $i + 3;
                //for text
                $save_extra_field_data->template_id = $save_template_data->id;
                $save_extra_field_data->name = $get_template_data['field_extra_name'][$i];
                $save_extra_field_data->security_type = $get_template_data['field_extra_security_type'][$i];
                $save_extra_field_data->field_position = $pos;
                $jutification = 'C';
                if(isset($_POST['field_extra_text_align'])){
                    $jutification = $_POST['field_extra_text_align'][$i];
                }
                $save_extra_field_data->text_justification = $jutification;
                $save_extra_field_data->font_style = $get_template_data['field_extra_font_style'][$i];
                $save_extra_field_data->x_pos = $get_template_data['field_extra_x'][$i];
                $save_extra_field_data->y_pos = $get_template_data['field_extra_y'][$i];
                $save_extra_field_data->width = $get_template_data['field_extra_width'][$i];
                $save_extra_field_data->height = $get_template_data['field_extra_height'][$i];

                $font_id = (int)$get_template_data['field_extra_font_id'][$i];
                if(empty($get_template_data['field_extra_font_id'])){
                    $font_id = 0;
                }
                $save_extra_field_data->font_id = $font_id;
                $save_extra_field_data->font_size = $get_template_data['field_extra_font_size'][$i];
                $save_extra_field_data->is_font_case = $get_template_data['field_extra_font_case'][$i];
                $save_extra_field_data->font_color = $get_template_data['field_extra_font_color'][$i];
                $save_extra_field_data->font_color_extra = $get_template_data['font_color_extra'][$i];
                $save_extra_field_data->sample_text = $get_template_data['field_sample_text'][$i];
                $save_extra_field_data->visible = $get_template_data['visible'][$i];
                $save_extra_field_data->visible_varification = $get_template_data['visible_varification'][$i];

                // //for custom images
                $grey_scale = (int)$get_template_data['grey_scale'][$i];
                if(empty($get_template_data['grey_scale'][$i])){
                    $grey_scale = 0;
                }
                $save_extra_field_data->grey_scale = $grey_scale;
                $infinite_height = (int)$get_template_data['infinite_height'][$i];
                if(empty($get_template_data['infinite_height'][$i])){               
                    $infinite_height = 0;
                }
                $save_extra_field_data->infinite_height = $infinite_height;

                $include_image = (int)$get_template_data['include_image'][$i];
                if(empty($get_template_data['include_image'][$i])){
                    $include_image = 0;
                }
                $save_extra_field_data->include_image = $include_image;

                $is_uv_image = (int)$get_template_data['is_uv_image'][$i];
                if(empty($get_template_data['is_uv_image'][$i])){
                    $is_uv_image = 0;
                }

                $save_extra_field_data->is_uv_image = $is_uv_image;

                $is_transparent_image = (int)$get_template_data['is_transparent_image'][$i];
                if(empty($get_template_data['is_transparent_image'][$i])){
                    $is_transparent_image = 0;
                }

                $save_extra_field_data->is_transparent_image = $is_transparent_image;

                
                $field_image = $get_template_data['field_image1'][$i];

                $image_name = '';
                if($field_image == $default_image){
                    //for default image
                    $image_name = $default_image;
                }
                if($field_image == 'undefined'){
                    $field_image = '';
                }
                
                if($field_image != 'null' && $field_image != '' && $field_image != NULL && $field_image != ' ' && $field_image != $default_image){
                    //save this path to db 
                    if($get_template_data['field_extra_security_type'][$i] == 'Qr Code'){
                        $save_field_image_name = explode('images/', $field_image);
                        $image_name = $save_field_image_name[1];
                    }
                    else{
                        $image_name = $field_image;
                    
                        if($get_file_aws_local_flag->file_aws_local == '1'){
                            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                                $destination = $subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/';        
                                $check_aws_directory = $subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id;
                            }
                            else{
                                $destination = $subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/';        
                                $check_aws_directory = $subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id;
                            }
                            
                            $temp_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$field_image;
                            $cp_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$field_image;
                            if(!Storage::disk('s3')->has($destination.$image_name)){


                                if(!Storage::disk('s3')->has($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/'.$default_image)){
                                    if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                                        $defualt_image_path = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/'.\Config::get('constant.default_image');
                                    }
                                    else{
                                        $defualt_image_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/'.\Config::get('constant.default_image');
                                    }
                                    $default_image = public_path().'/backend/canvas/images/customImages/'.\Config::get('constant.default_image');

                                    $cp_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$default_image;
                                    $aws_default_storage = \Storage::disk('s3')->put($defualt_image_path, file_get_contents($default_image), 'public');
                                    $aws_default_filename = \Storage::disk('s3')->url(\Config::get('constant.default_image'));
                                }

                                
                                $aws_storage = \Storage::disk('s3')->put($destination.'/'.$field_image, file_get_contents($temp_image), 'public');
                                $aws_filename = \Storage::disk('s3')->url($field_image);
                                //if default image then not remove
                                if ($temp_image != $cp_image && $field_image != $default_image) {
                                    \Storage::disk('s3')->delete(\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$field_image);
                                }

                                //move uv image from custom image to template name folder
                                
                                $file_name = pathinfo($field_image, PATHINFO_FILENAME);
                                $extension = pathinfo($field_image, PATHINFO_EXTENSION);
                                $uv_image_name = $file_name.'_uv.'.$extension;
                                $uv_image_path = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$uv_image_name;

                                $aws_uv_storage = \Storage::disk('s3')->put($destination.'/'.$uv_image_name, file_get_contents($uv_image_path), 'public');
                                $aws_uv_filename = \Storage::disk('s3')->url($uv_image_name);

                                \Storage::disk('s3')->delete(\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$uv_image_name);


                                $bw_image_name = $file_name.'_bw.'.$extension;
                                $bw_image_path = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$bw_image_name;

                                $aws_bw_storage = \Storage::disk('s3')->put($destination.'/'.$bw_image_name, file_get_contents($bw_image_path), 'public');
                                $aws_b_wfilename = \Storage::disk('s3')->url($bw_image_name);

                                \Storage::disk('s3')->delete(\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$bw_image_name);
                            
                            }

                        }
                        else{
                            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                                $destination = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/';        
                                $check_aws_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id;
                            }
                            else{
                                $destination = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/';        
                                $check_aws_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id;
                            }
                            
                            $temp_image = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$field_image;
                            $cp_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$field_image;
                            $files = glob($check_aws_directory."/*");
                            

                            if(!file_exists($destination.$image_name)){

                                
                                if(!file_exists($destination_path)){
                                    if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                                        $defualt_image_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/'.\Config::get('constant.default_image');
                                    }
                                    else{
                                        $defualt_image_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/'.\Config::get('constant.default_image');
                                    }
                                    $default_image = public_path().'/backend/canvas/images/customImages/'.\Config::get('constant.default_image');

                                    $cp_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$default_image;
                                    \File::copy($default_image,$defualt_image_path);
                                    
                                }

                                \File::copy($temp_image,$destination.$field_image);
                                //if default image then not remove
                                if ($temp_image != $cp_image && $field_image != $default_image) {
                                    unlink(public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$field_image);
                                }

                                //move uv image from custom image to template name folder
                                
                                $file_name = pathinfo($field_image, PATHINFO_FILENAME);
                                $extension = pathinfo($field_image, PATHINFO_EXTENSION);
                                $uv_image_name = $file_name.'_uv.'.$extension;
                                $uv_image_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$uv_image_name;

                                \File::copy($uv_image_path,$destination.$uv_image_name);
                                
                                unlink(public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$uv_image_name);


                                $bw_image_name = $file_name.'_bw.'.$extension;
                                $bw_image_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$bw_image_name;

                                \File::copy($bw_image_path,$destination.$bw_image_name);

                                

                                unlink(public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$bw_image_name);
                                
                            }
                        }
                    }   
                }
                else{
                    if(!file_exists($destination_path)){
                        if($get_file_aws_local_flag->file_aws_local == '1'){
                            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                                $defualt_image_path = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/'.\Config::get('constant.default_image');
                            }
                            else{
                                $defualt_image_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/'.\Config::get('constant.default_image');
                            }
                            $default_image = public_path().'/backend/canvas/images/customImages/'.\Config::get('constant.default_image');

                            $cp_image = \Config::get('constant.amazone_path').'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$default_image;
                            $aws_default_storage = \Storage::disk('s3')->put($defualt_image_path, file_get_contents($default_image), 'public');
                            $aws_default_filename = \Storage::disk('s3')->url(\Config::get('constant.default_image'));
                        }
                        else{
                            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                                $defualt_image_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$saved_template_id.'/'.\Config::get('constant.default_image');
                            }
                            else{
                                $defualt_image_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$saved_template_id.'/'.\Config::get('constant.default_image');
                            }
                            $default_image = public_path().'/backend/canvas/images/customImages/'.\Config::get('constant.default_image');

                            $cp_image = \Config::get('constant.local_base_path').'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$default_image;

                            \File::copy($default_image,$defualt_image_path);
                            
                        }
                    }
                }
                $save_extra_field_data->sample_image = $image_name;
                $save_extra_field_data->combo_qr_text = $get_template_data['combo_qr_text'][$i];
                $save_extra_field_data->lock_index = $get_template_data['field_lockIndex'][$i];
                $save_extra_field_data->mapped_name = $get_template_data['field_extra_mapped'][$i];
                $mapped_data = '';
                if(isset($get_template_data['is_mapped'][$i])){
                    $mapped_data = $get_template_data['is_mapped'][$i];
                }
                $save_extra_field_data->is_mapped = $mapped_data;
                $save_extra_field_data->angle = $get_template_data['angle'][$i];
                $save_extra_field_data->line_gap = $get_template_data['line_gap'][$i];
                $save_extra_field_data->length = $get_template_data['length'][$i];
                $save_extra_field_data->uv_percentage = $get_template_data['uv_percentage'][$i];
                $save_extra_field_data->is_repeat = $get_template_data['is_repeat'][$i];
                $save_extra_field_data->text_opicity = $get_template_data['text_opicity'][$i];
                $save_extra_field_data->created_by = $admin_id['id'];
                $save_extra_field_data->updated_by = $admin_id['id'];
                /**Blockchain**/
                if($subdomain[0]=="demo"&&isset($_POST['is_block_chain'])&&$_POST['is_block_chain']==1){
                $is_meta_data = (int)$get_template_data['is_meta_data'][$i];
                if(empty($get_template_data['is_meta_data'][$i])){
                    $save_extra_field_data->is_meta_data = 0;
                }else{
                    $save_extra_field_data->is_meta_data = 1;
                    $save_extra_field_data->meta_data_label = $get_template_data['field_metadata_label'][$i];
                    $save_extra_field_data->meta_data_value = $get_template_data['field_metadata_value'][$i];
                }
                }
                /**End Blockchain**/
                $save_extra_field_data->save();
            }
        }
        
        if($subdomain[0]=="demo"&&isset($_POST['is_block_chain'])&&$_POST['is_block_chain']==1){
            
            //Blockchain Integration
            CoreHelper::checkContactAddress($save_template_data->id,'NORMALTEMPLATE');
        }
        $message = Array('type' => 'success', 'message' => 'Template saved sucessfully','last_id'=> $save_template_data->id);
        
        return $message;
    }
}
