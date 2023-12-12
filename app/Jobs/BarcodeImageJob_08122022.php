<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use QrCode;
use AwsS3S3Client;
use Aws\S3\S3Client;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\SystemConfig;

class BarcodeImageJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $getBarcodeImageData;
    public function __construct($getBarcodeImageData)
    {
        $this->getBarcodeImageData = $getBarcodeImageData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $getBarcodeImageData = $this->getBarcodeImageData;
     
        if($getBarcodeImageData['imageType'] == 'Qr'){
            $image_field_name = $getBarcodeImageData['field_qr_image1'];
        }
        elseif($getBarcodeImageData['security_type'] == 'Qr Code'){
            $image_field_name = $getBarcodeImageData['field_image'];
        }


        if($get_file_aws_local_flag->file_aws_local == '1'){

            $s3=\Storage::disk('s3');
            $customImages_directory = $subdomain[0].'/'.\Config::get('constant.customImages');

            $s3->makeDirectory($customImages_directory, 0777);

            $image_directory = $subdomain[0].'/'.\Config::get('constant.customImages');
            //if directory not exist make directory
            $s3->makeDirectory($image_directory, 0777);
        }
        else{
            
            $customImages_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/';
            //if directory not exist make directory
            if(!is_dir($customImages_directory)){
    
                mkdir($customImages_directory, 0777);
            }

            $image_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.canvas').'/';
            //if directory not exist make directory
            if(!is_dir($image_directory)){
    
                mkdir($image_directory, 0777);
            }
        }


        if($getBarcodeImageData['imageType'] == 'Qr' || $getBarcodeImageData['security_type'] == 'Qr Code'){
            if(!empty($image_field_name)){

                //get imagename
                $filename = $image_field_name->getClientOriginalName();
                //get image filesize
                $fileSize = $image_field_name->getSize();
                //valid extension for image
                $valid_extensions = array('jpg','jpeg', 'png');

                //if template name is not null upload file inside template name directory else upload inside custom image folder
                if(!empty($getBarcodeImageData['template_name'])){
                    
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $check_aws_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/';
                    }
                    else{
                        $check_aws_directory = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/';
                    }
                    

                    $image_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.canvas').'/';

                    if(!is_dir($image_directory)){
            
                        mkdir($image_directory, 0777);
                    }

                    $check_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.canvas').'/'.$getBarcodeImageData['template_name'].'/';
                    //if directory not exist make directory
                    if(!is_dir($check_directory)){
            
                        mkdir($check_directory, 0777);
                    }
                    if(!is_dir($check_aws_directory)){
            
                        mkdir($check_aws_directory, 0777);
                    }
                    
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $destination = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'];
                        $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.$filename;
                    }
                    else{

                        $destination = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'];
                        $location = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.$filename;
                    }
                   
                }
                else{

                    if($get_file_aws_local_flag->file_aws_local == '1'){

                        $destination = '/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/';
                        $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$filename;
                    }
                    else{

                        $destination = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/';
                        $location = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$filename;
                    }
                }
                //get image extension
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                //check extension with array
                if(in_array($ext, $valid_extensions)){
                    //if image successfully uploaded
                    
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $aws_storage = \Storage::disk('s3')->put($destination.'/'.$filename, file_get_contents($image_field_name), 'public');
                        $filename = \Storage::disk('s3')->url($filename);
                    }
                    else{
                        
                        $aws_storage = \File::copy($image_field_name,$destination.'/'.$filename);
                        $filename = $destination.'/'.$filename;
                    }



                    if($aws_storage){
                        $dt = date("_ymdHis");
                    
                        $codeContents = strtoupper(md5($dt));
                        

                        if($get_file_aws_local_flag->file_aws_local == '1'){ 
                            $qr_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.canvas').'/qr/';
                            //if directory not exist make directory
                            if(!is_dir($qr_directory)){
                    
                                mkdir($qr_directory, 0777);
                            }

                            $getImagePath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.canvas').'/qr/'.$codeContents.'.png';
                        }
                        else{
                            $qr_directory = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/';
                            //if directory not exist make directory
                            if(!is_dir($qr_directory)){
                    
                                mkdir($qr_directory, 0777);
                            }

                            $getImagePath = \Config::get('constant.local_base_path').$subdomain[0].'/backend/canvas/images/qr/'.$codeContents.'.png';
                        }
                        $pngAbsoluteFilePath = public_path().'/backend/canvas/dummy_images/qr/'.$codeContents.'.png';

                        //generatr qrcode
                        QrCode::format('png')->size(200)->generate($codeContents,$pngAbsoluteFilePath);
                  
                        $QR = imagecreatefrompng($pngAbsoluteFilePath);
                        $logo = imagecreatefromstring(file_get_contents($location));

                        imagecolortransparent($logo, imagecolorallocatealpha($logo, 0, 0, 0, 127));
                        imagealphablending($logo, false);
                        imagesavealpha($logo, true);
                        $QR_width = imagesx($QR);
                        $QR_height = imagesy($QR);

                        $logo_width = imagesx($logo);
                        $logo_height = imagesy($logo);

                        $logo_qr_width = $QR_width/3;
                        $scale = $logo_width/$logo_qr_width;
                        $logo_qr_height = $logo_height/$scale;

                        imagecopyresampled($QR, $logo, $QR_width/3, $QR_height/3, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);

                        imagepng($QR,$pngAbsoluteFilePath);




                        if($get_file_aws_local_flag->file_aws_local == '1'){ 
                            $pngAbsoluteFilePath1 = '/'.$subdomain[0].'/backend/canvas/images/qr/'.$codeContents.'.png';

                            $aws_qr = \Storage::disk('s3')->put($pngAbsoluteFilePath1, file_get_contents($pngAbsoluteFilePath), 'public');
                            $filename1 = \Storage::disk('s3')->url($codeContents.'.png');
                        }
                        else{
                            $pngAbsoluteFilePath1 = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$codeContents.'.png';

                            $aws_qr = \File::copy($pngAbsoluteFilePath,$pngAbsoluteFilePath1);
                            $filename1 = $pngAbsoluteFilePath1;
                        }
                        
                        $size = getimagesize($location);
                        $ImageWidth = $size[0];
                        $ImageHeight = $size[1];
                        $ajaxResponse['filename'] = $getImagePath;
                        $ajaxResponse['original_name'] = $filename;
                        $ajaxResponse['size'] = $fileSize;
                        $ajaxResponse['width'] = $ImageWidth;
                        $ajaxResponse['height'] = $ImageHeight;
                        $ajaxResponse['success'] = true;
                        if($getBarcodeImageData['template_name'] != ''){

                            $ajaxResponse['template_name'] = $getBarcodeImageData['template_name'];       
                        }
                        if(isset($getBarcodeImageData['security_type']) && $getBarcodeImageData['security_type'] == 'Qr Code'){
                            $ajaxResponse['security_type'] = 'Qr Code';
                        }

                        
                        unlink($pngAbsoluteFilePath);
                        return $ajaxResponse;
                    }
                    else{
                        $message = Array('type' => 'error', 'message' => 'Failed to upload image on server');
                        return $message;
                    }
                }
                else{
                    $message = Array('type' => 'error', 'message' => 'Please upload valid image');
                    return $message;
                }
            }
            else{
                $message = Array('type' => 'error', 'message' => 'Please select image to upload');
                return $message;
            }
        }
        else{
            if(!empty($getBarcodeImageData['field_image'])){

                //get imagename
                $filename = $getBarcodeImageData['field_image']->getClientOriginalName();
                //get image filesize
                $fileSize = $getBarcodeImageData['field_image']->getSize();
                //valid extension for image
                $valid_extensions = array('jpg','jpeg', 'png');


                //get image extension
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                //if template name is not null upload file inside template name directory else upload inside custom image folder

                
                if(!empty($getBarcodeImageData['template_name'])){
               
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $check_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/';
                    }
                    else{
                        // dd($getBarcodeImageData['id']);
                        $check_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/';
                    }
                   
                    if(!is_dir($check_directory)){
            
                        mkdir($check_directory, 0777);
                    }
                  
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $check_aws_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/';
                    }
                    else{
                        $check_aws_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/';
                    }
                  
                    if(!is_dir($check_aws_directory)){
            
                        mkdir($check_aws_directory, 0777);
                    }

                    $location = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.$filename;
                    $greyImage = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/';
                    $destination = $greyImage;


                     //get path info
                    $path_info = pathinfo($location);
                    //get filename from path
                    $file_name = $path_info['filename'];

               
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $aws_upload_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.$filename;
                        $aws_uv_upload_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.$file_name.'_uv.'.$ext;

                        $aws_bw_upload_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.$file_name.'_bw.'.$ext;
                    }
                    else{
                        $server_upload_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.$filename;
                        $server_uv_upload_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.$file_name.'_uv.'.$ext;

                        $server_bw_upload_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.$file_name.'_bw.'.$ext;
                    }
                    

                }
                else{
                    $destination = public_path().'/'.\Config::get('constant.customImages').'/';
                    $location = public_path().'/'.\Config::get('constant.customImages').'/'.$filename;
                    $greyImage = $destination;


                     //get path info
                    $path_info = pathinfo($location);
                    //get filename from path
                    $file_name = $path_info['filename'];
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $aws_upload_path = '/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$filename;
                        $aws_uv_upload_path = '/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$file_name.'_uv.'.$ext;
                        $aws_bw_upload_path = '/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$file_name.'_bw.'.$ext;
                    }
                    else{
                        $server_upload_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$filename;
                        $server_uv_upload_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$file_name.'_uv.'.$ext;
                        $server_bw_upload_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$file_name.'_bw.'.$ext;
                    }
                }
                
                //check extension with array
                if(in_array($ext, $valid_extensions)){

                    
                    //if image successfully uploaded
                    if($getBarcodeImageData['field_image']->move($destination,$filename)){
                       
                        if($ext == 'png'){

                            $im = imagecreatefrompng($location);
                            $im_bw = imagecreatefrompng($location);

                            
                        }else if($ext == 'jpeg' || $ext == 'jpg'){

                            $im = imagecreatefromjpeg($location);
                            $im_bw = imagecreatefromjpeg($location);
                        }
                         
                        if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0))
                        {
                          
                            imagejpeg($im, $greyImage.$file_name.'_uv.'.$ext);
                            imagedestroy($im);
                        }

                        if($im_bw && imagefilter($im_bw, IMG_FILTER_GRAYSCALE))
                        {
                          
                            imagejpeg($im_bw, $greyImage.$file_name.'_bw.'.$ext);
                            imagedestroy($im_bw);
                        }
                            
                    

                        
                        $size = getimagesize($location);

                        if($get_file_aws_local_flag->file_aws_local == '1'){

                            $aws_storage = \Storage::disk('s3')->put($aws_upload_path, file_get_contents($location), 'public');
                            $aws_filename = \Storage::disk('s3')->url($filename);



                            $aws_uv_storage = \Storage::disk('s3')->put($aws_uv_upload_path, file_get_contents($greyImage.$file_name.'_uv.'.$ext), 'public');
                            $aws_uv_filename = \Storage::disk('s3')->url($file_name.'_uv.'.$ext);


                            $aws_bw_storage = \Storage::disk('s3')->put($aws_bw_upload_path, file_get_contents($greyImage.$file_name.'_bw.'.$ext), 'public');
                            $aws_bw_filename = \Storage::disk('s3')->url($file_name.'_bw.'.$ext);
                        }
                        else{
                            \File::copy($location,$server_upload_path);

                            \File::copy($greyImage.$file_name.'_uv.'.$ext,$server_uv_upload_path);

                            \File::copy($greyImage.$file_name.'_bw.'.$ext,$server_bw_upload_path);
                        }

                        $ImageWidth = $size[0];
                        $ImageHeight = $size[1];
                        $ajaxResponse['filename'] = $filename;
                        $ajaxResponse['size'] = $fileSize;
                        $ajaxResponse['width'] = $ImageWidth;
                        $ajaxResponse['height'] = $ImageHeight;
                        $ajaxResponse['security_type'] = $getBarcodeImageData['security_type']; 
                        $ajaxResponse['grey_scale'] = $getBarcodeImageData['grey_scale']; 
                        $ajaxResponse['is_uv_image'] = $getBarcodeImageData['is_uv_image']; 
                        $ajaxResponse['template_id'] = $getBarcodeImageData['id']; 
                        $ajaxResponse['success'] = true;
                        if($getBarcodeImageData['template_name'] != ''){



                            $ajaxResponse['template_name'] = $getBarcodeImageData['template_name'];       
                
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $defualt_image_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.\Config::get('constant.default_image');
                                }
                                else{
                                    $defualt_image_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.\Config::get('constant.default_image');
                                }
                            
                            $default_image = public_path().'/backend/templates/img/'.\Config::get('constant.default_image');
                   
                            if($get_file_aws_local_flag->file_aws_local == '1'){
                                if(!file_exists(\Config::get('constant.amazone_path')).$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.\Config::get('constant.default_image')){
                                    $aws_default_storage = \Storage::disk('s3')->put($defualt_image_path, file_get_contents($default_image), 'public');
                                    $aws_default_filename = \Storage::disk('s3')->url(\Config::get('constant.default_image'));
                                }
                            }
                            else{
                                if(!file_exists(\Config::get('constant.local_base_path')).$subdomain[0].'/'.\Config::get('constant.template').'/'.$getBarcodeImageData['id'].'/'.\Config::get('constant.default_image')){

                                    \File::copy($default_image,$defualt_image_path);
                                }
                            }
                            
                        }
           
                        return $ajaxResponse;
                    }
                    else{
                        $message = Array('type' => 'error', 'message' => 'Failed to upload image on server');
                        return $message;
    
                    }
                }
                else{
                    $message = Array('type' => 'error', 'message' => 'Please upload valid image');
                    return $message;

                }
            }
            else{
                $message = Array('type' => 'error', 'message' => 'Please select image to upload');
                return $message;
            }
        }
    }
}
