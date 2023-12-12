<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\models\BackgroundTemplateMaster;
use TCPDF;
use App\models\FontMaster;
use App\models\SystemConfig;
use QrCode,DB;
use App\models\Config;
use App\models\StudentTable;
use App\models\PrintingDetail;
use App\models\ExcelUploadHistory;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\Helpers\CoreHelper;
use App\Utility\GibberishAES;

class PreviewPDFGenerateJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $isPreview;
    protected $highestRow;
    protected $highestColumn;
    protected $FID;
    protected $Orientation;
    protected $template_Width;
    protected $template_height;
    protected $bg_template_img_generate;
    protected $bg_template_width_generate;
    protected $bg_template_height_generate;
    protected $bg_template_img;
    protected $bg_template_width;
    protected $bg_template_height;
    protected $mapped_excel_col_unique_serial_no;
    protected $ext;
    protected $fullpath;
    protected $tmpDir;
    protected $excelfile;
    protected $timezone;
    protected $printer_name;
    protected $style2D;
    protected $style1D;
    protected $style1Da;
    protected $fonts_array;
    protected $font_name;
    protected $admin_id;

    public function __construct($isPreview,$highestRow,$highestColumn,$FID,$Orientation,$template_Width,$template_height,$bg_template_img_generate,$bg_template_width_generate,$bg_template_height_generate,$bg_template_img,$bg_template_width,$bg_template_height,$mapped_excel_col_unique_serial_no,$ext,$fullpath,$tmpDir,$excelfile,$timezone,$printer_name,$style2D,$style1D,$style1Da,$fonts_array,$font_name,$admin_id)
    {
        $this->isPreview = $isPreview;
        $this->highestRow = $highestRow;
        $this->highestColumn = $highestColumn;
        $this->FID = $FID;
        $this->Orientation = $Orientation;
        $this->template_Width = $template_Width;
        $this->template_height = $template_height;
        $this->bg_template_img_generate = $bg_template_img_generate;
        $this->bg_template_width_generate = $bg_template_width_generate;
        $this->bg_template_height_generate = $bg_template_height_generate;
        $this->bg_template_img = $bg_template_img;
        $this->bg_template_width = $bg_template_width;
        $this->bg_template_height = $bg_template_height;
        $this->mapped_excel_col_unique_serial_no = $mapped_excel_col_unique_serial_no;
        $this->ext = $ext;
        $this->fullpath = $fullpath;
        $this->tmpDir = $tmpDir;
        $this->excelfile = $excelfile;
        $this->timezone = $timezone;
        $this->printer_name = $printer_name;
        $this->style2D = $style2D;
        $this->style1D = $style1D;
        $this->style1Da = $style1Da;
        $this->fonts_array = $fonts_array;
        $this->font_name = $font_name;
        $this->admin_id = $admin_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        

        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        
        $isPreview = $this->isPreview;
        $highestRow = $this->highestRow;
        $highestColumn = $this->highestColumn;
        $FID = $this->FID;
        $Orientation = $this->Orientation;
        $template_Width = $this->template_Width;
        $template_height = $this->template_height;
        $bg_template_img_generate = $this->bg_template_img_generate;
        $bg_template_width_generate = $this->bg_template_width_generate;
        $bg_template_height_generate = $this->bg_template_height_generate;
        $bg_template_img = $this->bg_template_img;
        $bg_template_width = $this->bg_template_width;
        $bg_template_height = $this->bg_template_height;
        $mapped_excel_col_unique_serial_no = $this->mapped_excel_col_unique_serial_no;
        $ext = $this->ext;
        $fullpath = $this->fullpath;
        $tmpDir = $this->tmpDir;
        $excelfile = $this->excelfile;
        $timezone = $this->timezone;
        $printer_name = $this->printer_name;
        $style2D = $this->style2D;
        $style1D = $this->style1D;
        $style1Da = $this->style1Da;
        $font_name = $this->font_name;
        $admin_id = $this->admin_id;
        
        $fonts_array = $this->fonts_array;

        $template_name = '';
        if(isset($FID['template_name'])){
            $template_name = $FID['template_name'];
        }

        if($ext == 'xlsx' || $ext == 'Xlsx'){
            $inputFileType = 'Xlsx';
        }
        else{
            $inputFileType = 'Xls';
        }

        $pdfBig = new TCPDF($Orientation, 'mm', array($bg_template_width, $bg_template_height), true, 'UTF-8', false);
            
        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);
        
        if($subdomain[0]=="minoshacloud"){
        $pdfBig->AddSpotColor('Special', 0, 0, 100, 0);
        }
        $log_serial_no = 1;
        
        for($excel_row = 2; $excel_row <= $highestRow; $excel_row++)
        {
           
            $pdfBig->AddPage();
           
            if($bg_template_img != ''){

                 $basename = basename($bg_template_img);
                $template_img = str_replace(' ', '_', $bg_template_img);
                // $template_img = $bg_template_img;    
                $ext = pathinfo($bg_template_img, PATHINFO_EXTENSION);
            
                $image_extension = 'JPG';
                if ($ext == 'PNG' || 'png') {
                    $image_extension = 'PNG';
                }
                if ($ext == 'jpg') {
                    $image_extension = 'jpg';
                }
                
                 //Mandar Update
                 $template_img_new = public_path().'\\'.$subdomain[0].'\\backend\canvas\bg_images\\'.$basename;

                
                $pdfBig->Image($template_img_new, 0, 0, $bg_template_width, $bg_template_height, $image_extension, '', 'R', true);
            }

            $pdfBig->SetCreator('TCPDF');

            // put data into pdf
            $count = count($FID['field_position']);

                
            $d = 0;
            $draw_key = 0;
            $image_key = 0;
            $microline_width_key = 0;
            $microtext_key = 0;
            for($extra_fields = 0; $extra_fields < $count; $extra_fields++)
            {
                $str = $FID['data'][$extra_fields];
               
                $bold = '';
            
                if($FID['font_style'][$extra_fields] != ''){
                    $bold = $FID['font_style'][$extra_fields];
                }
                if(isset($FID['font_id'][$extra_fields]))
                {

                    $font = $FID['font_id'][$extra_fields];
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
                
                $print_serial_no = $this->nextPrintSerial();

                $text_align = $FID['text_justification'][$extra_fields];
                $width = $FID['width'][$extra_fields];
                if(isset($FID['uv_percentage'][$extra_fields])){
                    $uv_percentage =  $FID['uv_percentage'][$extra_fields];
                }

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

               /* if($subdomain[0]=='raisoni'){
                        echo $str;
                    }*/
                $singleQuotePosition = strpos($str, "'");

                if ($singleQuotePosition === 0){
                 
                    if($subdomain[0]=='raisoni'){
                     //   echo $str;
                        $str = str_replace("'", '', $str); 
                    }else{
                        $str = str_replace("'", '\'', $str);
                    }      
         
                 
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

                $strposDouble  = substr($str, 0,1);
                $strposSingle  = substr($str, 0,1);

                if($strposDouble == '"'){

                    $str = str_replace('"', '"', $str);
                } 
                
                switch  ($security_type) {

                    case 'Normal':

                                $pdfBig->SetAlpha(1);
                                $textAlign = $FID['text_justification'][$extra_fields];
                                
                                if(isset($FID['infinite_height'][$extra_fields-2]) && $FID['infinite_height'][$extra_fields-2] != 1){
                                    

                                    if(($extra_fields==87&&isset($FID['data'][87])&&$template_name=="GalgotiyaUniversity")||($extra_fields==111&&isset($FID['data'][111])&&$template_name=="GalgotiyaUniversity-copy")){
                                       $strArr = str_split($str);
                                       $i =0;
                                       $j=0;
                                       $y=$y+5;
                                       $z=0;
                                       foreach ($strArr as $character) {
                                            $pdfBig->SetTextColor($r,$g,$b);
                                            $pdfBig->SetFont($fonts_array[$extra_fields],$bold, $font_size, '', false);

                                            $pdfBig->SetXY($x, $y+$z);
                                            if($i==3){
                                                $j=$j+0.2;
                                            }else if($i>1){
                                            
                                             $j=$j+0.1;   
                                            }
                                         
                                            if($i>1){
                                               
                                                $z=$z+0.1;
                                            }
                                            if($i>3){
                                              $x=$x+0.4;  
                                            }else if($i>2){
                                              $x=$x+0.2;  
                                            
                                            }
                                            
                                        
                                            $pdfBig->Cell($width, 0, $character, 0, $ln=0,  $textAlign, 0, '', 0, false, 'B', 'B');
                                            $i++;
                                            $x=$x+2.2+$j; 
                                            if($i>2){
                                               
                                             $font_size=$font_size+1.7;   
                                            }
                                            
                                            
                                       }
                                      
                                    }else{
                                        

                                    $pdfBig->SetTextColor($r,$g,$b);
                                    $pdfBig->SetFont($fonts_array[$extra_fields],$bold, $font_size, '', false);
                                    $pdfBig->SetXY($x, $y);
                                    $pdfBig->Cell($width, $height, $str, 0, false, $textAlign);
                                    }
                                    
                                    
                                }else{


                                    $pdfBig->SetFillColor(255, 255, 255);
                                    $pdfBig->SetTextColor($r,$g,$b);
                                    if ($fonts_array[$extra_fields] == 'times_b' || $fonts_array[$extra_fields] == 'times_n' || $fonts_array[$extra_fields] == 'times_i') {
                                        
                                        $fonts_array[$extra_fields] = 'times';
                                    }

                                    $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                    $pdfBig->MultiCell($width, $height, $str."\n", 0, $textAlign, 1, 1, $x, $y, true, 0, false, true, 0, 'T', true);
                                }
                            
                        break;

                    case 'QR Code':


                        // $dt = date("_ymdHis");
                        // $codeContents = strtoupper(md5($str . $dt));
                        // $codeData = $codeContents;
                        if($subdomain[0]=="demo") {

                            $fullString=$FID['field_qr_combo_qr_text'][0];
                            $EncryptedString=$FID['encrypted_qr_text'][0]; 

                            
                            if($fullString!="{{QR Code}}"){
                                if (preg_match_all('#\{\{(.*?)\}\}#', $fullString[0], $matches)) {
                                    
                                    $qr_fields=$matches[1]; // Get Group 1 values
                                    
                                    $fullString=str_replace("{{".$field."}}",'',$fullString);                       
                                    
                                }
                                
                                $dt = date("_ymdHis");
                                $QRName = strtoupper(md5($str . $dt));
                                $codeContents = strtoupper(md5($str . $dt));
                                
                                //Blockchain Integration
                                if($subdomain[0]=="demo"&&($FID['template_id']==697||$FID['template_id']==698||$FID['is_block_chain']==1)){
                                    $codeData=$fullString."\n\n".CoreHelper::getBCVerificationUrl(encrypt($codeContents));
                                }else{
                                    if($subdomain[0]=="nidantechnologies"&&$FID['template_id']==12){
                                        $certName = preg_replace('/[^a-zA-Z0-9]/', '_', $serial_no).".pdf";
                                        $url = 'http://'.$subdomain[0].'.'.$subdomain[1].'.com/'; 
                                        $codeData =$url.'/'.$subdomain[0].'/backend/pdf_file/'.$certName;
                                    }else{
                                        $codeData = $fullString."\n\n".$codeContents;    
                                    }
                                }
                            }
                            if(!empty($EncryptedString)){
                                
                                
                                if (preg_match_all('#\{\{(.*?)\}\}#', $EncryptedString, $matches)) {
                                
                                    $qr_fields=$matches[1]; // Get Group 1 values
                                    $EncryptedString=str_replace("{{".$field."}}",'',$EncryptedString);
                                }
                                
                                $dt = date("_ymdHis");
                                
                                $QRName = strtoupper(md5($str . $dt));
                                $codeContents = strtoupper(md5($str . $dt));
                                
                                $conStriCode = $EncryptedString."\n\n".$codeContents;
                                if($FID['is_encrypted_qr'][0] == 1) {
                                    $conStriCode= GibberishAES::enc($conStriCode, \Config::get('constant.EStamp_Salt'));
                                }
                                //Blockchain Integration
                                if($subdomain[0]=="demo"&&($FID['template_id']==697||$FID['template_id']==698||$FID['is_block_chain']==1)){
                                    if(!empty($fullString)) {
                                        $codeData=$fullString."\n\n".$EncryptedString."\n\n".CoreHelper::getBCVerificationUrl(encrypt($codeContents));
                                    } else {
                                        $codeData=$EncryptedString."\n\n".CoreHelper::getBCVerificationUrl(encrypt($codeContents));
                                    }
                                
                                }else{
                                    
                                    if($subdomain[0]=="nidantechnologies"&&$FID['template_id']==12){
                                        $certName = preg_replace('/[^a-zA-Z0-9]/', '_', $serial_no).".pdf";
                                        $url = 'http://'.$subdomain[0].'.'.$subdomain[1].'.com/'; 
                                        $codeData =$url.'/'.$subdomain[0].'/backend/pdf_file/'.$certName;
                                    }else{
                                        if(!empty($fullString)) {
                                            $codeData = $fullString."\n\n".$conStriCode;    
                                        } else {
                                            $codeData = $conStriCode;    
                                        }
                                    }
                                }
                                
                            }

                            if($fullString=="{{QR Code}}"){
                                $dt = date("_ymdHis");
                                $codeContents = strtoupper(md5($str . $dt));
                                if($FID['is_encrypted_qr'][0] == 1) {
                                    $codeData= GibberishAES::enc($codeContents, \Config::get('constant.EStamp_Salt'));
                                } else {
                                    $codeData = $codeContents;
                                }
                                // $codeData = $codeContents;
                            }


                        } else {
                            $dt = date("_ymdHis");
                            $codeContents = strtoupper(md5($str . $dt));
                            $codeData = $codeContents;
                            $QRName = strtoupper(md5($str . $dt));
                            
                        }

                        $pngAbsoluteFilePath = "$tmpDir/$QRName.png";
                        if($FID['include_image'][$extra_fields] == 1){
                        
                            $logopath = $FID['sample_image'][$extra_fields];
                            
                            if($logopath == "null"){

                                $logopath = public_path()."/backend/canvas/dummy_images/QR.png";
                            }
                        }else{

                            $logopath = public_path()."/backend/canvas/dummy_images/QR.png";
                        }
                        
                        QrCode::format('png')->size(200)->generate($codeData,$pngAbsoluteFilePath);
                        $QR = imagecreatefrompng($pngAbsoluteFilePath);
                    
                        if($FID['include_image'][$extra_fields] == 1){
                            $logo = imagecreatefromstring(file_get_contents($logopath));
                            imagecolortransparent($logo, imagecolorallocatealpha($logo, 0, 0, 0, 127));
                            imagealphablending($logo, false);
                            imagesavealpha($logo, true);
                            
                            $logo_width = imagesx($logo);
                            $logo_height = imagesy($logo);
                        }
                        
                        $QR_width = imagesx($QR);
                        $QR_height = imagesy($QR);

                        $logo_qr_width = $QR_width/3;
                        

                        if($FID['include_image'][0] == 1){

                            $scale = $logo_width/$logo_qr_width;
                            $logo_qr_height = $logo_height/$scale;

                            imagecopyresampled($QR, $logo, $QR_width/3, $QR_height/3, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);

                        }
                        
                        // Save QR code again, but with logo on it
                        imagepng($QR,$pngAbsoluteFilePath);

                        $pdfBig->SetAlpha(1);
                        $pdfBig->Image($pngAbsoluteFilePath, $x, $y, $width,  $height, "PNG", '', 'R', true);
                        
                        if(file_exists($pngAbsoluteFilePath)){
                            unlink($pngAbsoluteFilePath);
                        }
                        break;
                    case 'Qr Code':

                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            $strName = str_replace(' ', '-', $str); // Replaces all spaces with hyphens.
                            $strName =  preg_replace('/[^A-Za-z0-9\-]/', '', $strName); // Removes special chars.
                                 
                            $dt = date("_ymdHis");
                            
                            $codeContents = str_replace('/', '\\', $str);
                            $codeName=str_replace('/', '\\', $strName);
                            
                            $pngAbsoluteFilePath = "$tmpDir/$codeName.png";
                            
                            if($FID['include_image'][$extra_fields] == 1){
                                $field_image = explode('qr/',$FID['field_image'][$extra_fields]);
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $logopath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.canvas').'/qr/'.$field_image[1];
                                }
                                else{
                                    $logopath = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.canvas').'/qr/'.$field_image[1];
                                }
                                $imagepath = $logopath;
                            
                            }else{
                                $logopath = public_path()."/backend/canvas/dummy_images/QR.png";
                                
                                QrCode::format('png')->size(200)->generate($codeContents,$pngAbsoluteFilePath);
                                $QR = imagecreatefrompng($pngAbsoluteFilePath);
                                $imagepath = $pngAbsoluteFilePath;
                            }   

                            $include_image = $FID['include_image'][$extra_fields];
                            $pdfBig->SetAlpha(1);
                            $pdfBig->Image($imagepath, $x, $y, $width / 3,  $height / 3, "PNG", '', 'R', true);
                        }                        
                        break;
                    case 'ID Barcode':

                        if($auth_site_id != 238){

                            if($FID['field_id_visible'][0] == 0){
                            
                                $pdfBig->SetAlpha(1);
                                $pdfBig->write1DBarcode($print_serial_no, 'C39', $x, $y, $width, $height, 0.4, $style1Da, 'N');
                            }
                            break;
                        }
                     
                    case 'Micro line':
                        
                            $microline_width = explode(',',$FID['microline_width'][0]);
                            
                            //Updated by Mandar

                            $microlinestr = $str;
                            $latestWidth = round($width*3.7795280352161);
                            $microlinestr = preg_replace('/\s+/', '', $microlinestr); 
                            $microlinestrLength=strlen($microlinestr);

                            $strWidth =$microlinestrLength*1.4;

                            //width per character
                            $microLinecharacterWd =$strWidth/$microlinestrLength;

                            //Required no of characters required in string to match width
                             $microlinestrCharReq=$latestWidth/$microLinecharacterWd;
                            $microlinestrCharReq=round($microlinestrCharReq);
                           
                            //No of time string should repeated
                             $repeateMicrolineStrCount=$latestWidth/$strWidth;
                             $repeateMicrolineStrCount=round($repeateMicrolineStrCount)+1;

                            //Repeatation of string 
                             $microlinestrRep = str_repeat($microlinestr, $repeateMicrolineStrCount);
                          
                            //Cut string in required characters (final string)
                            $array = substr($microlinestrRep,0,$microlinestrCharReq);
                          
                              $realWidth = $width / 1.4;
                            
                            if($subdomain[0]=='minoshacloud'){
                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 2, '', false);
                            }else{
                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            
                            }
                            $pdfBig->SetTextColor(0, 0, 0);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x, $y);
                            
                            $is_repeat =  1;
                            if($is_repeat == 1){
                                $pdfBig->Cell($realWidth, $height, $array, 0, false, $text_align);
                            }else{
                                $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                            }

                                $pdfBig->StopTransform();
                            $microline_width_key++;
                        
                        break;
                    case 'Micro Text Border':

                        /*
                            if case is microtext border than we get height,width and string after we use for loop
                            because string repeate upto width and after we rotate the line and use for loop upto 
                            height.
                            we can multiply height and width to 0.2645833333 because pdf in mm format.  
                        */
                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            if($subdomain[0]=="demo"){
                            $explode_default = explode(',',$FID['field_sample_text_width'][0]);
                            
                            $explode_vertical = explode(',',$FID['field_sample_text_vertical_width'][0]);
                            $explode_horizontal = explode(',',$FID['field_sample_text_horizontal_width'][0]);
                            $length = $explode_default[$draw_key];
                            $vertical_length = $explode_vertical[$draw_key] * (25.4/100);
                            $horizontal_length = $explode_horizontal[$draw_key] *  (25.4/100);
                          // print_r($explode_vertical);
                          // exit;
                            //Horizontal line Calculation

                            //$str = preg_replace('/\s+/', '', $str); //added by Mandar
                            $textArray = imagettfbbox(1, 0, public_path().'/backend/fonts/Arial.ttf', $str);
                            $strWidth = ($textArray[2] - $textArray[0]);
                            $strHeight = $textArray[6] - $textArray[1] / 1;
                            
                            $latestWidth =$width;
                        
                             //Updated by Mandar
                            $microlinestr=$str;
                            $microlinestrLength=strlen($microlinestr);
                        
                            //width per character
                            $microLinecharacterWd =$strWidth/$microlinestrLength;

                            //Required no of characters required in string to match width
                             $microlinestrCharReq=$latestWidth/$microLinecharacterWd;
                             $microlinestrCharReq=ceil($microlinestrCharReq);

                            //No of time string should repeated
                             $repeateMicrolineStrCount=$latestWidth/$strWidth;
                             $repeateMicrolineStrCount=ceil($repeateMicrolineStrCount)+1;

                            //Repeatation of string 
                             $microlinestrRep = str_repeat($microlinestr, $repeateMicrolineStrCount);
                           
                            //Cut string in required characters (final string)
                            $array =$microlinestrRep;
                           // $array = substr($microlinestrRep,0,$microlinestrCharReq);

                            /*******Code To trim characters**********************************************************/
                            $pdfBig->startTransaction();
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $strlenHorizontal=strlen($array);
                            $firstLoop=true;
                            do{
                                if(!$firstLoop){
                                   $strlenHorizontal=$strlenHorizontal-1;
                                }
                                $array = substr($array,0,$strlenHorizontal);
                                $lines = $pdfBig->MultiCell($horizontal_length, 0, $array, 0, 'C', 0, 0, '', '', true, 0, false,true, 0);
                                $firstLoop=false;
                            }while($lines>1);
                            $pdfBig=$pdfBig->rollbackTransaction();
                            $horizontalString=$array;
                            /*******************************************************************/

                            //Verticle Line Calculation
                          
                            //$str = preg_replace('/\s+/', '', $str); //added by Mandar
                            $textArray = imagettfbbox(1, 0, public_path().'/backend/fonts/Arial.ttf', $str);
                            $strWidth = ($textArray[2] - $textArray[0]);
                            $strHeight = $textArray[6] - $textArray[1] / 1;
                            
                            //$latestWidth = round($height*3.7795280352161);
                            $latestWidth = $height;

                             //Updated by Mandar
                            $microlinestr=$str;
                            $microlinestrLength=strlen($microlinestr);

                            //width per character
                            $microLinecharacterWd =$strWidth/$microlinestrLength;

                            //Required no of characters required in string to match width
                             $microlinestrCharReq=$latestWidth/$microLinecharacterWd;
                            $microlinestrCharReq=ceil($microlinestrCharReq);

                            //No of time string should repeated
                             $repeateMicrolineStrCount=$latestWidth/$strWidth;
                             $repeateMicrolineStrCount=round($repeateMicrolineStrCount)+1;

                            //Repeatation of string 
                             $microlinestrRep = str_repeat($microlinestr, $repeateMicrolineStrCount);
                            
                            //Cut string in required characters (final string)
                            //$horizontal_array = substr($microlinestrRep,0,$microlinestrCharReq);

                             $verticle_array =$microlinestrRep;

                            /*******Code To trim characters**********************************************************/
                            $pdfBig->startTransaction();
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $strlenVerticle=strlen($verticle_array);
                            $firstLoop=true;
                            do{
                                if(!$firstLoop){
                                   $strlenVerticle=$strlenVerticle-1;
                                }
                                $verticle_array = substr($verticle_array,0,$strlenVerticle);
                                $lines = $pdfBig->MultiCell($vertical_length, 0, $verticle_array, 0, 'C', 0, 0, '', '', true, 0, false,true, 0);
                                $firstLoop=false;
                            }while($lines>1);
                            $pdfBig=$pdfBig->rollbackTransaction();
                            $verticleString=$verticle_array;
                            /*******************************************************************/

                            // first horizontal line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Cell($horizontal_length, 0, $horizontalString, 0, false, $text_align);
                            
                            //first vertical line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x + 1, $y-1);
                            $pdfBig->Rotate(-90);
                            $pdfBig->Cell($vertical_length, 0, $verticleString, 0, false, $text_align);
                            $pdfBig->StopTransform();

                            // second horizontal line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->SetXY($x, $y + $vertical_length-2.5);
                            $pdfBig->Cell($horizontal_length, 0, $horizontalString, 0, false, $text_align);
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->StartTransform();

                            //second vertical line
                            $pdfBig->SetXY($x+ $horizontal_length-1,$y-1);// / 1.18
                            $pdfBig->Rotate(-90);
                            $pdfBig->Cell($vertical_length, 0, $verticleString, 0, false, $text_align);
                            $pdfBig->StopTransform();
                           
                            }else{
                            $explode_default = explode(',',$FID['field_sample_text_width'][0]);
                            $explode_vertical = explode(',',$FID['field_sample_text_vertical_width'][0]);
                            $explode_horizontal = explode(',',$FID['field_sample_text_horizontal_width'][0]);
                            $length = $explode_default[$draw_key];
                            $vertical_length = $explode_vertical[$draw_key] /  3.1;
                            $horizontal_length = $explode_horizontal[$draw_key] / 3.1;
                            $last_width = 0;
                            $message = array();
                            for($i=1;$i<1000;$i++){

                                if($i * $length > $width){
                                    $wd = $i * $length;
                                    $last_width =$wd - $length;
                                    
                                    break;
                                }
                                $message[$i] = $str;
                            }

                            
                            $horizontal_line = array();
                            foreach ($message as $key => $value) {
                                $horizontal_line[] = $value;
                            }

                            
                            $string = implode(',', $horizontal_line);
                            $array = str_replace(',', '', $string);
                            
                            $msg = array();

                            for ($i=1; $i < 1000; $i++) { 
                                if($i*$length > $height){
                                    break;
                                }
                                $msg[$i] = $str;
                            }
                            $vertical_line = array();
                            foreach ($msg as $key => $value) {
                                $vertical_line[] = $value;
                            }

                            $vertical_string = implode(',', $vertical_line);
                            $horizontal_array = str_replace(',', '', $vertical_string);
                            
                        
                            // first horizontal line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Cell(0, 0, $array, 0, false, $text_align);
                            
                            //first vertical line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x + 1, $y - 1);
                            $pdfBig->Rotate(-90);
                            $pdfBig->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdfBig->StopTransform();


                            // second horizontal line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->SetXY($x, $y + ($vertical_length / 1.18));
                            $pdfBig->Cell(0, 0, $array, 0, false, $text_align);
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->StartTransform();


                            //second vertical line
                            $pdfBig->SetXY($x+ ($horizontal_length / 1.18) + 1,$y-1);
                            $pdfBig->Rotate(-90);
                            $pdfBig->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdfBig->StopTransform();
                            }
                            $draw_key++;
                            //end microtext border
                        }

                        break;
                    case 'Static Microtext Border':

                        /*
                            if case is microtext border than we get height,width and string after we use for loop
                            because string repeate upto width and after we rotate the line and use for loop upto 
                            height.
                            we can multiply height and width to 0.2645833333 because pdf in mm format.  
                        */
                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            $explode_default = explode(',',$FID['field_sample_text_width'][0]);
                            $explode_vertical = explode(',',$FID['field_sample_text_vertical_width'][0]);
                            $explode_horizontal = explode(',',$FID['field_sample_text_horizontal_width'][0]);
                            
                            $length = $explode_default[$draw_key];
                            $vertical_length = $explode_vertical[$draw_key] /  3.1;
                            $horizontal_length = $explode_horizontal[$draw_key] / 3.1;
                            
                            $last_width = 0;
                            $message = array();
                            for($i=1;$i<1000;$i++){

                                if($i * $length > $width){
                                    $wd = $i * $length;
                                    $last_width =$wd - $length;
                                    
                                    break;
                                }
                                $message[$i] = $str;
                            }

                            
                            $horizontal_line = array();
                            foreach ($message as $key => $value) {
                                $horizontal_line[] = $value;
                            }

                            
                            $string = implode(',', $horizontal_line);
                            $array = str_replace(',', '', $string);
                            
                            $msg = array();

                            for ($i=1; $i < 1000; $i++) { 
                                if($i*$length > $height){
                                    break;
                                }
                                $msg[$i] = $str;
                            }
                            $vertical_line = array();
                            foreach ($msg as $key => $value) {
                                $vertical_line[] = $value;
                            }

                            $vertical_string = implode(',', $vertical_line);
                            $horizontal_array = str_replace(',', '', $vertical_string);
                            
                        
                            // first horizontal line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Cell(0, 0, $array, 0, false, $text_align);
                            
                            //first vertical line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x + 1, $y - 1);
                            $pdfBig->Rotate(-90);
                            $pdfBig->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdfBig->StopTransform();


                            // second horizontal line
                            $pdfBig->SetXY($x, $y + ($vertical_length / 1.185));
                            $pdfBig->Cell(0, 0, $array, 0, false, $text_align);
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdfBig->StartTransform();


                            //second vertical line
                            $pdfBig->SetXY($x+ ($horizontal_length / 1.181) + 1,$y-1);
                            $pdfBig->Rotate(-90);
                            $pdfBig->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdfBig->StopTransform();

                            $draw_key++;
                            //end microtext border
                        $microtext_key++;
                    }
                    break;
                    case 'Static Text':
                    
                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            $pdfBig->SetAlpha(1);
                            $pdfBig->SetTextColor($r,$g,$b);
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                        }
                    break;

                    case 'Security line':
                        
                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            if($FID['is_font_case'][$extra_fields-2]==1){
                                $str=strtoupper($str);

                            }
                            $strWidthPx = $pdfBig->GetStringWidth($str, $fonts_array[$extra_fields], $bold, $font_size, false);

                            $loopCount=ceil($width/$strWidthPx);
                            if($loopCount<1){
                                $loopCount=1;
                            }
                            
                            $strLength=strlen($str);
                            $widthPerCharacter=$strWidthPx/$strLength;
                            $noOfCharactesRequired=round($width/$widthPerCharacter);
                            $multipliedStr=str_repeat($str,$loopCount);

                            $security_line= substr($multipliedStr, 0, $noOfCharactesRequired);
                        
                                // SetAlpha
                                $pdfBig->SetAlpha($FID['text_opacity'][$extra_fields-2]);
                                $pdfBig->SetTextColor($r,$g,$b);
                                
                                $pdfBig->SetFont('', $bold, $font_size, '', false);

                                $pdfBig->StartTransform();
                                $pdfBig->SetXY($x-2, $y);
                                $pdfBig->Rotate($FID['angle'][$extra_fields-2]);
                                $pdfBig->Cell($width, $height, $security_line, 0, 0, $text_align);
                                $pdfBig->StopTransform();
                        }
                    break;
                    case 'Dynamic Image':
                        /* we can display image from 2 path if without save preview then check template name is avalable or not
                            if available then image display from template name folder otherwise custom image folder 
                        */

                            if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                                $uv_image = $FID['is_uv_image'][$extra_fields-2];
                                if ($uv_image == 1) {

                                    $image = $FID['field_image'][$extra_fields];
                                   
                                    if (isset($FID['template_name']) && isset($FID['template_id'][$extra_fields]) && !empty($FID['template_id'][$extra_fields])) {
                                        $template_name = str_replace(' ', '', $FID['template_name']);
                                        $templateId = $FID['template_id'][$extra_fields];
                                        if($get_file_aws_local_flag->file_aws_local == '1'){
                                            
                                                $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$image;
                                            
                                        }
                                        else{
                                       
                                                $location = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$image;
                                           
                                        }
                                        
                                        $path_info = pathinfo($location);
                                        $file_name = $path_info['filename'];
                                        $extension = $path_info['extension'];
                                    
                                        if($get_file_aws_local_flag->file_aws_local == '1'){ 

                                                $uvImagePath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$file_name.'_uv.'.$extension;
                                        }
                                        else{
                                           
                                                $uvImagePath = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$file_name.'_uv.'.$extension; 
                                            
                                        } 
                                    }else{
                                        if($get_file_aws_local_flag->file_aws_local == '1'){
                                            $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$image;
                                        }
                                        else{
                                            $location = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$image;
                                        }
                                        $path_info = pathinfo($location);
                                        $file_name = $path_info['filename'];
                                        $extension = $path_info['extension'];
                                        if($get_file_aws_local_flag->file_aws_local == '1'){
                                            $uvImagePath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$file_name.'_uv.'.$extension;    
                                        }
                                        else{
                                            $uvImagePath =public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$file_name.'_uv.'.$extension;  
                                        }     
                                    }
                                    
                                    $pdfBig->image($uvImagePath,$x,$y,$width / 3,$height / 3,"",'','L',true,3600);
                                }else{

                                    $keyOfImage = $extra_fields;
                                    
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                        $imageSrc = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$FID['field_image'][$keyOfImage];
                                        $imageSrcNew = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages')."/grey_".$FID['field_image'][$keyOfImage];
                                    }
                                    else{
                                        
                                        $imageSrc = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$FID['field_image'][$keyOfImage];
                                        $imageSrcNew = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages')."/grey_".$FID['field_image'][$keyOfImage];
                                    }

                                    
                                    if(isset($FID['template_name']) && isset($FID['template_id'][$extra_fields]) && !empty($FID['template_id'][$extra_fields])){
                                        $template_name = str_replace(' ', '', $FID['template_name']);
                                        $templateId = $FID['template_id'][$extra_fields];
                                        if($get_file_aws_local_flag->file_aws_local == '1'){
                                            
                                                $imageSrc = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$FID['field_image'][$keyOfImage];
                                            
                                                $imageSrcNew = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/grey_'.$FID['field_image'][$keyOfImage];
                                            
                                        }
                                        else{
                                            
                                                 $imageSrc = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$FID['field_image'][$keyOfImage];
                                                
                                                $imageSrcNew = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/grey_'.$FID['field_image'][$keyOfImage];
                                            
                                        }
                                    }
                                    if($FID['grey_scale'][$extra_fields-2] == 1){
                                        
                                        $pdfBig->SetAlpha(1);
                                        $pdfBig->image($imageSrc,$x,$y,$width / 3,$height / 3,'','', '', false);
                                    }else{

                                        $path_info = pathinfo($imageSrc);
                                    
                                    $file_name = $path_info['filename'];
                                    $ext = $path_info['extension'];
                                    
                                    // $uv_location = public_path()."/backend/canvas/dummy_images/".$FID['template_name']."/".$file_name.'_uv.'.$ext;

                                    if($ext == 'png'){

                                        $im = imagecreatefrompng($imageSrc);

                                        if($im && imagefilter($im, IMG_FILTER_GRAYSCALE) )//&& imagefilter($im, IMG_FILTER_CONTRAST, -255)
                                        {
                                          
                                            imagepng($im, $imageSrc);
                                            imagedestroy($im);
                                        }
                                        
                                    }else if($ext == 'jpeg' || $ext == 'jpg'){

                                        $im = imagecreatefromjpeg($imageSrc);

                                        if($im && imagefilter($im, IMG_FILTER_GRAYSCALE) )//&& imagefilter($im, IMG_FILTER_CONTRAST, -255)
                                        {
                                          
                                            imagejpeg($im, $imageSrc);
                                            imagedestroy($im);
                                        }
                                        
                                    }
                                        
                                       /* $image = new Imagick($imageSrc);
                                        $image->setImageColorspace(imagick::COLORSPACE_GRAY);
                                        $image->writeImage($imageSrcNew);
                                        $pdfBig->SetAlpha(1);*/
                                        $pdfBig->image($imageSrcNew,$x,$y,$width / 3,$height / 3,"",'','L',true,3600);
                                    }
                                    
                                    $image_key++;
                                }
                            }   
                    break;
                    case 'Static Image':

                        // same as Dynamic Image
                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            $uv_image = $FID['is_uv_image'][$extra_fields-2];

                            $keyOfImage = $extra_fields;
                            if ($uv_image == 1) {

                                $image = $FID['field_image'][$extra_fields];
                                 
                                if (isset($FID['template_name']) && isset($FID['template_id'][$extra_fields]) && !empty($FID['template_id'][$extra_fields])) {
                                    $template_name = str_replace(' ', '', $FID['template_name']);
                                    $templateId = $FID['template_id'][$extra_fields];
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                          
                                            $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$image;
                                        
                                    }
                                    else{
                               
                                            $location = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$image;
                                        
                                    }
                                    $path_info = pathinfo($location);
                                    $file_name = $path_info['filename'];
                                    $extension = $path_info['extension'];
                                    if($get_file_aws_local_flag->file_aws_local == '1'){  
                       
                                            $uvImagePath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$file_name.'_uv.'.$extension; 
                                         
                                    }
                                    else{
                               
                                            $uvImagePath = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$file_name.'_uv.'.$extension; 
                                        
                                    }     
                                }else{
                                    $templateId = $FID['template_id'][$extra_fields];
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                        $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$templateId.'/'.$image;
                                    }
                                    else{
                                        $location = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$templateId.'/'.$image;
                                    }
                                    $path_info = pathinfo($location);
                                    $file_name = $path_info['filename'];
                                    $extension = $path_info['extension'];
                                    if($get_file_aws_local_flag->file_aws_local == '1'){        
                                        $uvImagePath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$templateId.'/'.$file_name.'_uv.'.$extension;
                                    }
                                    else{
                                        $uvImagePath = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$templateId.'/'.$file_name.'_uv.'.$extension; 
                                    }      
                                }
                                
                                $pdfBig->image($uvImagePath,$x,$y,$width / 3,$height / 3,"",'','L',true,3600);

                            }else{
                                $templateId = $FID['template_id'][$extra_fields];
                                
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $imageSrc = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$FID['field_image'][$keyOfImage];
                                    $imageSrcNew = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$templateId."/grey_".$FID['field_image'][$keyOfImage];
                                }
                                else{
                                    $imageSrc = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$FID['field_image'][$keyOfImage];
                                    $imageSrcNew = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.customImages').'/'.$templateId."/grey_".$FID['field_image'][$keyOfImage];
                                }

                                if(isset($FID['template_name']) && isset($FID['template_id'][$extra_fields]) && !empty($FID['template_id'][$extra_fields])){
                                    $template_name = str_replace(' ', '', $FID['template_name']);
                                    $templateId = $FID['template_id'][$extra_fields];
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                        
                                            $imageSrc = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$FID['field_image'][$keyOfImage];
                                            $imageSrcNew = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/grey_'.$FID['field_image'][$keyOfImage];
                                        
                                    }
                                    else{
                                        
                                            $imageSrc = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/'.$FID['field_image'][$keyOfImage];
                                            $imageSrcNew = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$templateId.'/grey_'.$FID['field_image'][$keyOfImage];
                                        
                                    }
                                }

                                if($FID['grey_scale'][$extra_fields-2] == 1){
                                    
                                    $pdfBig->image($imageSrc,$x,$y,$width / 3.34,$height / 3.34);
                                }else{
                                    
                                    $image = new Imagick($imageSrc);
                                    $image->setImageColorspace(imagick::COLORSPACE_GRAY);
                                    $image->writeImage($imageSrcNew);
                                    $pdfBig->image($imageSrcNew,$x,$y,$width / 3,$height  / 3,"",'','L',true,3600);
                                }
                            }
                        }
                        $image_key++;
                    break;
                    case 'UV Repeat line':
                        /* this is the combine of invisible and Security line in this task we can create uv repeat line
                            this line use to repeat text but in uv ink so repeate text and then rotate angle wise.              
                        */
                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            $font_color_extra = $FID['font_color_extra'][$extra_fields-2];
                            $security_line = '';
                            for($d = 0; $d < 15; $d++)
                                $security_line .= $str . ' ';
                            $pdfBig->SetOverprint(true, true, 0);
                            if ($print_color == 'CMYK') {
                                
                                if($font_color_extra == '000000'){

                                    $pdfBig->SetTextColor(0, 0, 0, $uv_percentage, false, '');
                                
                                }else{
                                    $pdfBig->SetTextColor(0, 0, $uv_percentage, 0, false, '');
                                }
                                
                            }else{
                                if($font_color_extra == '000000'){

                                    $pdfBig->SetTextColor(0, 0, 0);
                                
                                }else{
                                    if($subdomain[0]=="minoshacloud"){
                                    $pdfBig->SetTextSpotColor('Special', 100);
                                    }else{
                                    $pdfBig->SetTextColor(255,255, 0);
                                    }
                                }
                            }
                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                $pdfBig->StartTransform();
                                $pdfBig->SetXY($x, $y);
                                $pdfBig->Rotate($FID['angle'][$extra_fields-2]);
                                $pdfBig->Cell($width, $height, $security_line, 0, false, $text_align);
                                $pdfBig->StopTransform();
                                $pdfBig->SetOverprint(false, false, 0);
                        }
                    break;
                    case 'Ghost Image':

                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            $length =  $FID['length'][$extra_fields-2];
                            $name = substr($str, 0, $length);
                            
                            if($font_size == '10'){
                                $imageHeight = 5;
                                $imageWidth = 32;
                            }
                            else if($font_size == '11'){
                                $imageHeight = 7;
                                $imageWidth = 33.426664583;
                            }else if($font_size == '12'){
                                $imageHeight = 8;
                                $imageWidth = 36.415397917;
                            }
                            else if($font_size == '13'){
                                $imageHeight = 10;
                                $imageWidth = 39.405983333; 
                            }
                            else if($font_size == '14'){
                                $imageHeight = 12;
                                $imageWidth = 42.39656875;
                            }
                            else if($font_size == '15'){
                                $imageHeight = 14;
                                $imageWidth = 45.3758875;
                            }else if($font_size == '16'){
                                $imageHeight = 5.341;
                                $imageWidth = 48.3758875;
                            }
                            else if($font_size == '17'){
                                $imageHeight = 5.722;
                                $imageWidth = 51.366472917;
                            }
                            else if($font_size == '18'){
                                $imageHeight = 5.976;
                                $imageWidth = 54.35520625;
                            }
                            else if($font_size == '19'){
                                $imageHeight = 6.357;
                                $imageWidth = 57.345791667;
                            }
                            else if($font_size == '20'){
                                $imageHeight = 6.738;
                                $imageWidth = 60.334525;
                            }
                            else if($font_size == '21'){
                                $imageHeight = 6.992;
                                $imageWidth = 63.325110417;
                            }
                            else{
                                $imageHeight = 10;
                                $imageWidth = 32;
                            }

                                
                                $name = str_replace(' ', '-', $name); // Replaces all spaces with hyphens.
                                $w = $this->CreateMessage( $tmpDir, $name ,$font_size,$print_color);
                                
                                $ghostImgArr[$name] = $w;
                            
                           
                               

                                  $name =  preg_replace('/[^A-Za-z0-9\-]/', '', $name); // Removes special chars.
                                 $name = strtoupper($name);
                         
                            $pdfBig->Image("$tmpDir/" . $name."".$font_size.".png", $x, $y, $w, $imageHeight, "PNG", '', 'L', true, 3600);
                            
                        }
                    break;
                    case 'Invisible':

                        
                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){
  
                            $font_color_extra = $FID['font_color_extra'][$extra_fields-2];
                            $pdfBig->SetOverprint(true, true, 0);
                            
                            if ($print_color == 'CMYK') {
                                
                                if($font_color_extra == '000000'){

                                    $pdfBig->SetTextColor(0, 0, 0, $uv_percentage, false, '');
                                
                                }else{
                                    $pdfBig->SetTextColor(0, 0, $uv_percentage, 0, false, '');
                                }
                                
                            }else{
                                if($font_color_extra == '000000'){

                                    $pdfBig->SetTextColor(0, 0, 0);
                                
                                }else{

                                    if($subdomain[0]=="minoshacloud"){
                                    $pdfBig->SetTextSpotColor('Special', 100);
                                    }else{
                                    $pdfBig->SetTextColor(255,255, 0);
                                    }
                                }
                            }
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                            $pdfBig->SetOverprint(false, false, 0);
                        }
                    break;  
                    case 'Anti-Copy':

                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){


                            if ($print_color == 'CMYK') {

                                $imageSource = public_path().'/backend/canvas/ghost_images/S-017-18 ANTICOPY.png';
                                $pdfBig->Image($imageSource, $x, $y, 50, 10, "PNG", '', 'L', true, 3600);
                                
                            }else{
                                if($subdomain[0]=='minoshacloud'){
                                    $imageSource = public_path().'/backend/canvas/ghost_images/VOID FINAL FOR BROTHER_NEW.SVG';
                                }else{
                                    $imageSource = public_path().'/backend/canvas/ghost_images/VOID FINAL FOR BROTHER.SVG';
                                }
                                $pdfBig->ImageSVG($file=$imageSource, $x, $y, 50, 10, $link='', $align='', $palign='', $border=0, $fitonpage=false);
                            }
                        }
                    break;


                    case '1D Barcode':

                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            $pdfBig->write1DBarcode($str, 'C128', $x, $y, $width, $height, 0.4, $style1D, 'N');
                        }
                    break;

                    case 'UV Repeat Fullpage':

                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){


                            $font_color_extra = $FID['font_color_extra'][$extra_fields-2];
                            $security_line = '';
                            for($d = 0; $d < 50; $d++)
                                $security_line .= $str . ' ';

                            
                            
                            $pdfBig->SetOverprint(true, true, 0);

                            if ($print_color == 'CMYK') {
                            
                                if($font_color_extra == '000000'){

                                    $pdfBig->SetTextColor(0, 0, 0, $uv_percentage, false, '');
                                
                                }else{
                                    $pdfBig->SetTextColor(0, 0, 0, $uv_percentage, false, '');
                                }
                            
                            }else{
                                $rgb_opacity=$uv_percentage/100;
                                if($font_color_extra == '000000'){
                                    $pdfBig->SetAlpha($rgb_opacity);
                                    $pdfBig->SetTextColor(0, 0, 0);                                        
                                }else{
                                    $pdfBig->SetTextColor(255,255, 0);
                                }
                            }
                            
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);

                            if ($bg_template_width < $bg_template_height){
                                $pdfWidth = 210;
                                $pdfHeight = 297;
                            }else{
                                $pdfWidth = $bg_template_width;
                                $pdfHeight = $bg_template_height;
                            }
                            for ($i=0; $i < $pdfHeight; $i+=$line_gap) {                                    
                                $pdfBig->SetXY(0,$i);
                                $pdfBig->StartTransform();  
                                $pdfBig->Rotate($FID['angle'][$extra_fields-2]);
                                $pdfBig->Cell(0, 0, $security_line, 0, false, $text_align);
                                $pdfBig->StopTransform();
                            }
                            for ($j=0; $j < $pdfWidth; $j+=$line_gap) {                                    
                                $pdfBig->SetXY($j+3.5,$pdfHeight);
                                $pdfBig->StartTransform();  
                                $pdfBig->Rotate($FID['angle'][$extra_fields-2]);
                                $pdfBig->Cell(0, 0, $security_line, 0, false, $text_align);
                                $pdfBig->StopTransform();
                            }
                            
                            $pdfBig->SetOverprint(false, false, 0);
                            $pdfBig->SetAlpha(1);
                        }
                    break;
                    case '2D Barcode':

                        if($FID['field_extra_visible'][$extra_fields - 2] == 0){

                            // set style for barcode
                            $style = array(
                                'border' => false,
                                'vpadding' => 'auto',
                                'hpadding' => 'auto',
                                'fgcolor' => array(0,0,0),
                                'bgcolor' => false, //array(255,255,255)
                                'module_width' => 1, // width of a single module in points
                                'module_height' => 1 // height of a single module in points
                            );
                            
                            // same as Dynamic Image
                            // write RAW 2D Barcode
                            $pdfBig->write2DBarcode($str, 'DATAMATRIX', $x, $y, $width / 3, $height / 3, $style, 'N');
                        }
                    break;
                    default:
                        break;
                }
                $log_serial_no++;
            }

        }
            //if($subdomain[0]=="test"){
                //delete temp dir 26-04-2022 
                CoreHelper::rrmdir($tmpDir);
            //}
            $pdfBig->output('pdfcertificate.pdf', 'I');
            
            // deleteDir($tmpDir);

    }

    public function nextPrintSerial()
    {
        $current_year = 'PN/' . $this->getFinancialyear() . '/';
        // find max
        $maxNum = 0;

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

    public function CreateMessage($tmpDir, $name = "",$font_size,$print_color)
    {
        if($name == "")
            return;
        $name = strtoupper($name);
        // Create character image
        if($font_size == 15){


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
                "Z" => array(20827, 876),                
                "0" => array(21703, 850),
                "1" => array(22553, 850),
                "2" => array(23403, 850),
                "3" => array(24253, 850),
                "4" => array(25103, 850),
                "5" => array(25953, 850),
                "6" => array(26803, 850),
                "7" => array(27653, 850),
                "8" => array(28503, 850),
                "9" => array(29353, 635)
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
                "Z" => array(20842, 792),
                "0" => array(21634, 850),
                "1" => array(22484, 850),
                "2" => array(23334, 850),
                "3" => array(24184, 850),
                "4" => array(25034, 850),
                "5" => array(25884, 850),
                "6" => array(26734, 850),
                "7" => array(27584, 850),
                "8" => array(28434, 850),
                "9" => array(29284, 700)
            
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
                "Z" => array(17710, 680),
                "0" => array(18310, 725),
                "1" => array(19035, 725),
                "2" => array(19760, 725),
                "3" => array(20485, 725),
                "4" => array(21210, 725),
                "5" => array(21935, 725),
                "6" => array(22660, 725),
                "7" => array(23385, 725),
                "8" => array(24110, 725),
                "9" => array(24835, 630)
                
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
                "Z" => array(20867, 848),
                "0" => array(21715, 850),
                "1" => array(22565, 850),
                "2" => array(23415, 850),
                "3" => array(24265, 850),
                "4" => array(25115, 850),
                "5" => array(25695, 850),
                "6" => array(26815, 850),
                "7" => array(27665, 850),
                "8" => array(28515, 850),
                "9" => array(29365, 610)
            
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

        }else if($font_size == 13){

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
                "Z" => array(20880, 800),
                "0" => array(21680, 850),
                "1" => array(22530, 850),
                "2" => array(23380, 850),
                "3" => array(24230, 850),
                "4" => array(25080, 850),
                "5" => array(25930, 850),
                "6" => array(26780, 850),
                "7" => array(27630, 850),
                "8" => array(28480, 850),
                "9" => array(29330, 670)
            
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

        }else if($font_size == 14){

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
                "Z" => array(20884, 808),
                "0" => array(21692, 825),
                "1" => array(22517, 825),
                "2" => array(23342, 825),
                "3" => array(24167, 825),
                "4" => array(24992, 825),
                "5" => array(25817, 825),
                "6" => array(26642, 825),
                "7" => array(27467, 825),
                "8" => array(28292, 825),
                "9" => array(29117, 825)
            
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
                "Z" => array(23287, 880),

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
}
