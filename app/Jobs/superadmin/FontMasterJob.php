<?php

namespace App\Jobs\superadmin;

use App\models\superadmin\SuperFontMaster as FontMaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Auth;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

class FontMasterJob 
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $fontMaster_data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($fontMaster_data)
    {
        $this->fontMaster_data=$fontMaster_data;   
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    // store and update fontMaster 
    public function handle(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    { 

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $fontMaster_data=$this->fontMaster_data;
         // remove space between string and word
        $font_filename=ltrim($fontMaster_data['font_name']);
        $font_filename=str_replace(' ' ,'',$font_filename);
        
        $id=null;
        if(isset($fontMaster_data['font_id']))
        {
            $id = $fontMaster_data['font_id'];  
        }
        if(!empty($id))
        {

          $old_data=FontMaster::find($id)->toArray(); 
        }
        $filename_BI=null;
        $filename_N=null;
        $filename_I=null;
        $filename_BI=null;
        $array_update=array();

        /*$domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $subdomain[0]=""; $subdomain[0].'/'.*/
        if($get_file_aws_local_flag->file_aws_local == '1'){
          $s3=\Storage::disk('s3');
          $fonts_directory = \Config::get('constant.fonts').'/';
          $s3->makeDirectory($fonts_directory, 0777);
        }else{
          $fonts_directory = public_path().'/'.\Config::get('constant.fonts').'/';
          if(!is_dir($fonts_directory)){
              //Directory does not exist, then create it.
              mkdir($fonts_directory, 0777);
          }
        }

      if($request->hasFile('upload_font_N'))
       {
         if(!empty($id) and !empty($old_data['font_filename_N']))
          {  
            
            //remove existing file on folder
            if($get_file_aws_local_flag->file_aws_local == '1'){
              if($s3->exists(\Config::get('constant.fonts').'/'.$old_data['font_filename_N']))
              {
                 $s3->delete(\Config::get('constant.fonts').'/'.$old_data['font_filename_N']);
              }
            }
            else{
              $public_path=public_path(\Config::get('constant.fonts')).'/'.$old_data['font_filename_N'];
               if(file_exists($public_path))
               {

                 unlink($public_path); 
               }   
            }     
          }
         
          $file=$request->file('upload_font_N');
          $filename_N=$file->getClientOriginalName();
          $filename_array=explode('.',$filename_N);
          //$filename_N=$filename_array[0].'_N.'.$filename_array[1];
          $filename_N=$font_filename.'_N.'.$filename_array[1];
          $path=public_path('/'.\Config::get('constant.fonts'));
          $filename_N_ref="ref_".$filename_N;
          $file->move($path,$filename_N_ref); 
          if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_path=\Config::get('constant.fonts');
            $s3->put($aws_path.'/'.$filename_N,file_get_contents($path.'/'.$filename_N_ref));
            //unlink($path.'/'.$filename_N_ref);
          }else{
             \File::copy($path.'/'.$filename_N_ref,$path.'/'.$filename_N);
          }
          unlink($path.'/'.$filename_N_ref);
       }
      if($request->hasFile('upload_font_B'))
       {
        
          if(!empty($id) and !empty($old_data['font_filename_B']))
          {
              //remove existing file on folder
              if($get_file_aws_local_flag->file_aws_local == '1'){
                if($s3->exists(\Config::get('constant.fonts').'/'.$old_data['font_filename_B']))
                {
                   $s3->delete(\Config::get('constant.fonts').'/'.$old_data['font_filename_B']);
                }
              }
              else{
                $public_path=public_path(\Config::get('constant.fonts')).'/'.$old_data['font_filename_B'];
                 if(file_exists($public_path))
                 {
                   unlink($public_path); 
                 } 
              }  
          }
         
          $file=$request->file('upload_font_B');
          $filename_B=$file->getClientOriginalName();
          $filename_array=explode('.',$filename_B);
          //$filename_B=$filename_array[0].'_B.'.$filename_array[1];
          $filename_B=$font_filename.'_B.'.$filename_array[1];
          $path=public_path('/'.\Config::get('constant.fonts'));
          /*$file->move($path,$filename_B);  
          if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_path=\Config::get('constant.fonts');
            $s3->put($aws_path.'/'.$filename_B,file_get_contents($path.'/'.$filename_B));
            unlink($path.'/'.$filename_B);
          }*/
          $filename_B_ref="ref_".$filename_B;
          $file->move($path,$filename_B_ref); 
          if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_path=\Config::get('constant.fonts');
            $s3->put($aws_path.'/'.$filename_B,file_get_contents($path.'/'.$filename_B_ref));
            
          }else{
             \File::copy($path.'/'.$filename_B_ref,$path.'/'.$filename_B);
          }
          unlink($path.'/'.$filename_B_ref);
       }
      if($request->hasFile('upload_font_I'))
       {
          if(!empty($id) and !empty($old_data['upload_font_I']))
          {
            //remove existing file on folder
            if($get_file_aws_local_flag->file_aws_local == '1'){
              if($s3->exists(\Config::get('constant.fonts').'/'.$old_data['font_filename_I']))
              {
                 $s3->delete(\Config::get('constant.fonts').'/'.$old_data['font_filename_I']);
              }
            }
            else{
                $public_path=public_path(\Config::get('constant.fonts')).'/'.$old_data['font_filename_I'];
                 if(file_exists($public_path))
                 {
                   unlink($public_path); 
                 }  
            } 
          }
          $file=$request->file('upload_font_I');
          $filename_I=$file->getClientOriginalName();
          $filename_array=explode('.',$filename_I);
          //$filename_I=$filename_array[0].'_I.'.$filename_array[1];
          $filename_I=$font_filename.'_I.'.$filename_array[1];
          $path=public_path('/'.\Config::get('constant.fonts'));
          /*$file->move($path,$filename_I);  
          if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_path=\Config::get('constant.fonts');
            $s3->put($aws_path.'/'.$filename_I,file_get_contents($path.'/'.$filename_I));
            unlink($path.'/'.$filename_I);
          }*/
          $filename_I_ref="ref_".$filename_I;
          $file->move($path,$filename_I_ref); 
          if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_path=\Config::get('constant.fonts');
            $s3->put($aws_path.'/'.$filename_I,file_get_contents($path.'/'.$filename_I_ref));
            
          }else{
             \File::copy($path.'/'.$filename_I_ref,$path.'/'.$filename_I);
          }
          unlink($path.'/'.$filename_I_ref);
       }
      if($request->hasFile('upload_font_BI'))
        {
         if(!empty($id) and !empty($old_data['upload_font_BI']))
          {
            //remove existing file on folder
            if($get_file_aws_local_flag->file_aws_local == '1'){
              if($s3->exists(\Config::get('constant.fonts').'/'.$old_data['font_filename_BI']))
              {
                 $s3->delete(\Config::get('constant.fonts').'/'.$old_data['font_filename_BI']);
              }
            }
            else{
              $public_path=public_path(\Config::get('constant.fonts')).'/'.$old_data['font_filename_BI'];
               if(file_exists($public_path))
               {
                 unlink($public_path); 
               } 
            } 
          }
          $file=$request->file('upload_font_BI');
          $filename_BI=$file->getClientOriginalName();
          $filename_array=explode('.',$filename_BI);
          //$filename_BI=$filename_array[0].'_BI.'.$filename_array[1];
          $filename_BI=$font_filename.'_BI.'.$filename_array[1];
          $path=public_path('/'.\Config::get('constant.fonts'));
          /*$file->move($path,$filename_BI);  
          if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_path=\Config::get('constant.fonts');
            $s3->put($aws_path.'/'.$filename_BI,file_get_contents($path.'/'.$filename_BI));
            unlink($path.'/'.$filename_BI);
          }*/
          $filename_BI_ref="ref_".$filename_BI;
          $file->move($path,$filename_BI_ref); 
          if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_path=\Config::get('constant.fonts');
            $s3->put($aws_path.'/'.$filename_BI,file_get_contents($path.'/'.$filename_BI_ref));
            
          }else{
             \File::copy($path.'/'.$filename_BI_ref,$path.'/'.$filename_BI);
          }
          unlink($path.'/'.$filename_BI_ref);
       }
       
     
       
       // get auth admin id
       $auth_id=Auth::guard('superadmin')->user()->id;
       $auth_site_id=Auth::guard('superadmin')->user()->site_id;
       // save data  on fontmaster
       $fontMaster=FontMaster::firstOrNew(['id'=>$id]);

       $fontMaster->font_name=$fontMaster_data['font_name'];
       $fontMaster->font_filename=$font_filename;
       if(!empty($filename_N))
       {
          $fontMaster->font_filename_N=$filename_N;
       }
       if(!empty($filename_B))
       {
         $fontMaster->font_filename_B=$filename_B;
       }
       if(!empty($filename_I))
       {
        $fontMaster->font_filename_I=$filename_I;
       }
       if(!empty($filename_BI))
       {
         $fontMaster->font_filename_BI=$filename_BI;
       }
       $fontMaster->created_by=$auth_id;
       $fontMaster->updated_by=$auth_id;
       $fontMaster->site_id=$auth_site_id;                     
       $fontMaster->status=$fontMaster_data['opt_status'];
       //print_r($fontMaster);
       $fontMaster->save();

    }
}
