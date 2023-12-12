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
use QrCode;
use App\models\Config;
use App\models\StudentTable;
use App\models\PrintingDetail;
use App\models\ExcelUploadHistory;
use App\Jobs\SendMailJob;
use App\models\SiteDocuments;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;

class PDFGenerateDatabaseJob 
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $isPreview;
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
    protected $tmpDir;
    protected $timezone;
    protected $printer_name;
    protected $style2D;
    protected $style1D;
    protected $style1Da;
    protected $fonts_array;
    protected $font_name;
    protected $admin_id;

    public function __construct($isPreview,$FID,$Orientation,$template_Width,$template_height,$bg_template_img_generate,$bg_template_width_generate,$bg_template_height_generate,$bg_template_img,$bg_template_width,$bg_template_height,$tmpDir,$timezone,$printer_name,$style2D,$style1D,$style1Da,$fonts_array,$font_name,$admin_id)
    {
        $this->isPreview = $isPreview;
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
        $this->tmpDir = $tmpDir;
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

        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();


        $isPreview = $this->isPreview;
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
        $tmpDir = $this->tmpDir;
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
        $pdfBig = new TCPDF($Orientation, 'mm', array($bg_template_width, $bg_template_height), true, 'UTF-8', false);

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);
        $pdfBig->SetProtection(array('modify', 'copy','annot-forms','fill-forms','extract','assemble'),'',null,0,null);

        $log_serial_no = 1;
        $data_count =  count($FID['data']);
        for($excel_row = 0; $excel_row < $data_count; $excel_row++)
        {
            if(!$isPreview) 
            {
                $pdf = new TCPDF($Orientation, 'mm', array($template_Width, $template_height), true, 'UTF-8', false);

                $pdf->SetCreator('TCPDF');
                $pdf->SetAuthor('TCPDF');
                $pdf->SetTitle('Certificate');
                $pdf->SetSubject('');

                // remove default header/footer
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetAutoPageBreak(false, 0);
                
                $pdf->SetCreator('SetCreator');

                $pdf->AddPage();
                if($bg_template_img_generate != ''){
                    $pdf->Image($bg_template_img_generate, 0, 0, $bg_template_width_generate, $bg_template_height_generate, "JPG", '', 'R', true);
                }
            }

            $pdfBig->AddPage();
            if($bg_template_img != ''){
                $pdfBig->Image($bg_template_img, 0, 0, $bg_template_width, $bg_template_height, "JPG", '', 'R', true);
            }
            $pdfBig->SetProtection($permissions = array('modify', 'copy', 'annot-forms', 'fill-forms', 'extract', 'assemble'), '', null, 0, null);

            $pdfBig->SetCreator('TCPDF');
            // Get Serial No
            if(!$isPreview){
                //code for check map excel or database and pass serial no 
                $pdfBig->SetTextColor(0, 0, 0, 100, false, '');
                if ($FID['is_mapped'][0] == 'database') {
                    $serial_no = $FID['unique_serial_no'];
                }
                else
                {
                    $serial_no = 1;

                }
            }

            if(!$isPreview) 
            {
                $count =  count($FID['mapped_name']);
            } else {
                $count = count($FID['field_position']);
            }


            $d = 0;
            $draw_key = 0;
            $image_key = 0;
            $microline_width_key = 0;
            $microtext_key = 0;

            for($extra_fields = 0; $extra_fields < $count; $extra_fields++)
            {
                if(!$isPreview) 
                {
                    
                    if($FID['security_type'][$extra_fields] == "Static Microtext Border" || $FID['security_type'][$extra_fields] == "Static Text")
                    {
                        $str = $FID['sample_text'][$extra_fields];
                    }
                    else
                    {
                        //code for check map excel or database 
                        // get string from excel columns or database selected columns value
                        if ($FID['is_mapped'][0] == 'database') 
                        {
                            // dd($FID['data']);
                            if (isset($FID['data'][$excel_row][$FID['mapped_name'][$extra_fields]])) {
                                $str = $FID['data'][$excel_row][$FID['mapped_name'][$extra_fields]];

                                
                            }
                            else
                            {
                                $str = 'Not Mapped';
                            }
                        }
                    }
                    
                    $bold = '';

                    if($FID['font_style'][$extra_fields] != ''){
                        $bold = $FID['font_style'][$extra_fields];
                    }
                    $angle = $FID['angle'][$extra_fields];
                    if(isset($FID['font_id'][$extra_fields]))
                    {
                        $font = $FID['font_id'][$extra_fields];
                    }
                    
                    if(isset($FID['sample_image'][$extra_fields])){
                        $sample_image = $FID['sample_image'][$extra_fields];
                    }
                }

                if(isset($FID['line_gap'][$extra_fields]))
                {
                    $line_gap =  $FID['line_gap'][$extra_fields];
                }
                $style = $FID['font_style'][$extra_fields];

                if(isset($FID['length'][$extra_fields]))
                {
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

                if(!$isPreview)
                {
                    $pdfBig->SetTextColor(0, 0, 0, 100, false, '');
                }

                $print_serial_no = $this->nextPrintSerial();

                $text_align = $FID['text_justification'][$extra_fields];
                $width = $FID['width'][$extra_fields];

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
                $singleQuotePosition = strpos($str, "'");

                if ($singleQuotePosition === 0)
                {
                    $str = str_replace("'", "", $str);          
                }else
                {
                    $str = $str;
                }

                $x = $FID['x_pos'][$extra_fields];
                $y = $FID['y_pos'][$extra_fields];
                $height = $FID['height'][$extra_fields];
                $font_size = $FID['font_size'][$extra_fields];
                if(empty($font_size)){
                    $font_size = 0;
                }

                if(isset($FID['font_color_extra'][$extra_fields]))
                {
                    $font_color_extra = $FID['font_color_extra'][$extra_fields];
                }

                switch  ($security_type) 
                {
                    case 'Normal':
                        if(!$isPreview) 
                        {

                            if (isset($FID['data'][$excel_row][$FID['mapped_name'][$extra_fields]])) {

                                $str_date = $FID['data'][$excel_row][$FID['mapped_name'][$extra_fields]];

                                if (\DateTime::createFromFormat('Y-m-d H:i:s', $str_date) !== FALSE) 
                                {
                                  $str = date("d-m-Y", $str_date);
                                }
                            }
                            else
                            {
                                $str = 'Not Mapped';
                            }

                            if($FID['infinite_height'][$extra_fields] != 1){
                                $pdf->SetTextColor($r,$g,$b);
                                $pdf->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                $pdf->SetXY($x, $y);
                                $pdf->Cell($width, $height, $str, 0, false, $text_align);
                                $pdf->SetAlpha(1);

                                $pdfBig->SetAlpha(1);
                                $pdfBig->SetTextColor($r,$g,$b);
                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                $pdfBig->SetXY($x, $y);
                                $pdfBig->Cell($width, $height, $str, 0, false, $text_align);

                            }else
                            {
                                $pdf->SetAlpha(1);

                                $pdfBig->SetAlpha(1);
                                
                                $pdf->SetFillColor(255, 255, 255);
                                $pdf->SetTextColor($r, $g, $b);
                                
                                if ($fonts_array[$extra_fields] == 'times_b' || $fonts_array[$extra_fields] == 'times_n' || $fonts_array[$extra_fields] == 'times_i') {
                                    
                                    $fonts_array[$extra_fields] = 'times';
                                }
                                $pdf->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            
                                $pdf->MultiCell($width, $height, $str."\n", 0, 'L', 1, 1, $x, $y, true, 0, false, true, 0, 'T', true);

                                $pdfBig->SetFillColor(255, 255, 255);
                                $pdfBig->SetTextColor($r, $g, $b);
                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                $pdfBig->MultiCell($width, $height, $str."\n", 0, $text_align, 1, 1, $x, $y, true, 0, false, true, 0, 'T', true);
                            }
                        }
                    break;
                    case 'QR Code':
                        $dt = date("_ymdHis");
                        $codeContents = strtoupper(md5($str . $dt));
                        $codeData = $codeContents;
                        $pngAbsoluteFilePath = public_path()."/backend/canvas/dummy_images/$codeContents.png";
                        if(!$isPreview)
                        {
                            if($FID['include_image'][$extra_fields] == 1){
                                $logopath = $FID['sample_image'][$extra_fields];
                                
                            }else{
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $logopath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.canvas').'/QR.png';
                                }
                                else{
                                    $logopath = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.canvas').'/QR.png';
                                }
                            }
                        }
                    
                        QrCode::format('png')->size(200)->generate($codeContents,$pngAbsoluteFilePath);
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
                    
                        if(!$isPreview){
                            $pdf->SetAlpha(1);
                            $pdf->Image($pngAbsoluteFilePath, $x, $y, $width, $height, "PNG", '', 'R', true);

                            $pdfBig->SetAlpha(1);
                            $pdfBig->Image($pngAbsoluteFilePath, $x, $y, $width, $height, "PNG", '', 'R', true);
                        }
                        
                        if(file_exists($pngAbsoluteFilePath)){
                            unlink($pngAbsoluteFilePath);
                        }
                    break;
                    case 'Qr Code':
                        $dt = date("_ymdHis");
                        $codeContents = strtoupper(md5($str . $dt));
                        $codeData = $codeContents;
                        $pngAbsoluteFilePath = "$tmpDir/$codeContents.png";
                        
                        if(!$isPreview)
                        {
                            if($FID['include_image'][$extra_fields] == 1){
                                $field_image = explode('qr/',$FID['sample_image'][$extra_fields]);
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $logopath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.canvas').'/qr/'.$field_image[1];
                                }
                                else{
                                    $logopath = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.canvas').'/qr/'.$field_image[1];
                                }
                                $image_name = $field_image[1];
                                
                            }else{
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $logopath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.canvas')."/QR.png";
                                }
                                else{
                                    $logopath = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.canvas')."/QR.png";
                                }
                                $image_name = 'QR.png';
                            }
                    
                            QrCode::format('png')->size(200)->generate($codeContents,$pngAbsoluteFilePath);
                            $QR = imagecreatefrompng($pngAbsoluteFilePath);
                            
                            if($FID['include_image'] == 1){
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

                            if($FID['include_image'] == 1){

                                $scale = $logo_width/$logo_qr_width;
                                $logo_qr_height = $logo_height/$scale;

                                imagecopyresampled($QR, $logo, $QR_width/3, $QR_height/3, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);

                            }
                        
                            // Save QR code again, but with logo on it
                            imagepng($QR,$pngAbsoluteFilePath);

                            $pdf->SetAlpha(1);
                            $pdf->Image($pngAbsoluteFilePath, $x, $y, $width / 3, $height / 3, "PNG", '', 'R', true);

                            $pdfBig->SetAlpha(1);
                            $pdfBig->Image($pngAbsoluteFilePath, $x, $y, $width / 3, $height / 3, "PNG", '', 'R', true);

                            $pngAbsoluteFilePath1 = '/'.$subdomain[0].'/backend/canvas/dummy_images/qr/'.$codeContents.'.png';

                            $aws_qr = \Storage::disk('s3')->put($pngAbsoluteFilePath1, file_get_contents($pngAbsoluteFilePath), 'public');
                            $filename1 = \Storage::disk('s3')->url($codeContents.'.png');

                            if(file_exists($pngAbsoluteFilePath) && $get_file_aws_local_flag->file_aws_local == '1'){
                                unlink($pngAbsoluteFilePath);
                            }
                        }
                    break;
                    case 'ID Barcode':
                        if(!$isPreview){

                            $pdfBig->SetAlpha(1);
                            $pdf->write1DBarcode($print_serial_no, 'C128', $x, $y, $width, $height, 0.4, $style1Da, 'N');
                            
                            $pdfBig->write1DBarcode($print_serial_no, 'C128', $x, $y, $width, $height, 0.4, $style1Da, 'N');
                        }
                    break;
                    case 'Micro line':

                        if(!$isPreview) 
                        {
                            $textArray = imagettfbbox(1.4, 0, public_path().'/backend/fonts/Arial.ttf', $str);
                            $strWidth = ($textArray[2] - $textArray[0]);
                            $strHeight = $textArray[6] - $textArray[1] / 1.4;
                            
                            $latestWidth = $width;
                            $wd = '';
                            $last_width = 0;
                            $message = array();
                            for($i=1;$i<=1000;$i++){
                                if($i * $strWidth > $latestWidth)
                                {
                                    $wd = $i * $strWidth;
                                    $last_width =$wd - $strWidth;
                                    $extraWidth = $latestWidth - $last_width;
                                    $stringLength = strlen($str);
                                    $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                                    $message[$i]  = mb_substr($str, 0,$extraCharacter);
                                    break;
                                }
                                $message[$i] = $str.' ';
                            }

                            $horizontal_line = array();
                            foreach ($message as $key => $value) {
                                $horizontal_line[] = $value;
                            }
                            
                            $string = implode(',', $horizontal_line);
                            $array = str_replace(',', '', $string);
                            $is_repeat =  $FID['is_repeat'][$extra_fields];
                            
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, 2, '', false);
                            $pdf->SetTextColor(0, 0, 0);
                            $pdf->StartTransform();
                            $pdf->SetXY($x, $y);
                            $pdf->Rotate($angle);

                            if($is_repeat == 1){
                                $pdf->Cell($width, $height, $array, 0, false, $text_align);
                            }else{
                                $pdf->Cell($width, $height, $str, 0, false, $text_align);
                            }
                            $pdf->StopTransform();
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 2, '', false);
                            $pdfBig->SetTextColor(0, 0, 0);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Rotate($angle);

                            if($is_repeat == 1){

                                $pdfBig->Cell($width, $height, $array, 0, false, $text_align);
                            }else{
                                $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                            }
                            $pdfBig->StopTransform();
                        }
                        $microline_width_key++;
                    break;
                    case 'Micro Text Border':
                        /*
                            if case is microtext border than we get height,width and string after we use for loop
                            because string repeate upto width and after we rotate the line and use for loop upto 
                            height.
                            we can multiply height and width to 0.2645833333 because pdf in mm format.  
                        */
                        if(!$isPreview) {
                            $textArray = imagettfbbox(1.4, 0, public_path().'/backend/fonts/Arial.ttf', $str);
                            $mbstring = mb_strwidth($str);
                            $strWidth = $mbstring * 1.09;
                            $wd = '';
                            $last_width = 0;
                            $message = array();
                            for($i=1;$i<=1000;$i++){

                                if($i * $strWidth > $width){
                                    $wd = $i * $strWidth;
                                    $last_width =$wd - $strWidth;
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
                            $ht = '';
                            $last_height = 0;
                            for ($i=1; $i < 1000 ; $i++) {
                                if($i*$strWidth > $height){
                                    $ht = $i * $strWidth;
                                    
                                    $last_height = $ht - $strWidth;
                                    break;
                                }
                                $msg[$i] = $str;
                            }
                            $vertical_length = ($last_height  * 25.4)  / 95;
                            $horizontal_length = ($last_width * 25.4)  / 95;
                            
                            $vertical_line = array();
                            foreach ($msg as $key => $value) {
                                $vertical_line[] = $value;
                            }

                            $vertical_string = implode(',', $vertical_line);
                            $horizontal_array = str_replace(',', '', $vertical_string);

                            // first horizontal line    
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, 1.4, '', false);
                            $pdf->SetXY($x, $y);
                            $pdf->Cell(0, 0, $array, 0, false, $text_align);
                            

                            // // first vertical line
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, 1.4, '', false);
                            $pdf->StartTransform();
                            $pdf->SetXY($x+1, $y-1);
                            $pdf->Rotate(-90);
                            $pdf->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdf->StopTransform();

                            // // second horizontal line
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, 1.4, '', false);
                            $pdf->SetXY($x, $y + $vertical_length);
                            $pdf->Cell(0, 0, $array, 0, false, $text_align);
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdf->StartTransform();

                            // // second vertical line
                            $pdf->SetXY($x + $horizontal_length,$y);
                            $pdf->Rotate(-90);
                            $pdf->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdf->StopTransform();

                            // first Horizontal line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1.4, '', false);
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Cell(0, 0, $array, 0, false, $text_align);
                            
                            // first vertical line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1.4, '', false);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x+1, $y-1);
                            $pdfBig->Rotate(-90);
                            $pdfBig->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdfBig->StopTransform();

                            // second horizontal line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1.4, '', false);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Translate(0,($vertical_length * 1.05));
                            $pdfBig->Cell(0, 0, $array, 0, false, $text_align);

                            $pdfBig->StopTransform();
                            // second vertical line
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1.4, '', false);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x + 1,$y - 1);
                            $pdfBig->Translate($horizontal_length * 1.10,0);
                            $pdfBig->Rotate(-90);
                            $pdfBig->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdfBig->StopTransform();
                        }
                    break;

                    case 'Static Microtext Border':

                        /*
                            if case is microtext border than we get height,width and string after we use for loop
                            because string repeate upto width and after we rotate the line and use for loop upto 
                            height.
                            we can multiply height and width to 0.2645833333 because pdf in mm format.  
                        */
                        if(!$isPreview) {

                        
                            $length = $expload_sample_text_width[$microtext_key];
                            $vertical_length = $expload_sample_text_vertical_width[$microtext_key] /  3.1;
                            $horizontal_length = $expload_sample_text_horizontal_width[$microtext_key] / 3.1;
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
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdf->SetXY($x, $y);
                            $pdf->Cell(0, 0, $array, 0, false, $text_align);
                            

                            // first vertical line
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdf->StartTransform();
                            $pdf->SetXY($x+1, $y-1);
                            $pdf->Rotate(-90);
                            $pdf->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdf->StopTransform();

                            // second horizontal line
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdf->SetXY($x, $y + $vertical_length);
                            $pdf->Cell(0, 0, $array, 0, false, $text_align);
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                            $pdf->StartTransform();

                            //second vertical line
                            $pdf->SetXY($x + $horizontal_length,$y);
                            $pdf->Rotate(-90);
                            $pdf->Cell(0, 0, $horizontal_array, 0, false, $text_align);
                            $pdf->StopTransform();


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
                        $microtext_key++;
                    break;

                    case 'Static Text':
                        if(!$isPreview) 
                        {
                            $pdf->SetAlpha(1);
                            $pdf->SetTextColor($r,$g,$b);
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            $pdf->SetXY($x, $y);
                            $pdf->Cell($width, $height, $str, 0, false, $text_align);
                        }
                        $pdfBig->SetAlpha(1);
                        $pdfBig->SetTextColor($r,$g,$b);
                        $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                        $pdfBig->SetXY($x, $y);
                        $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                    break;
                    case 'Security line':
                        $security_line = '';
                        for($d = 0; $d < 100; $d++)
                            $security_line .= $str . '';
                        if(!$isPreview) 
                        {
                            $pdf->SetTextColor($r,$g,$b);
                            $pdf->SetAlpha($FID['text_opacity'][$extra_fields]);
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            $pdf->StartTransform();
                            $pdf->SetXY($x, $y);
                            $pdf->Rotate($angle);
                            $pdf->Cell($width, $height, $security_line, 0, 0, $text_align);
                            $pdf->StopTransform();

                            $pdfBig->SetTextColor($r,$g,$b);
                            $pdfBig->SetAlpha($FID['text_opacity'][$extra_fields]);
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Rotate($angle);
                            $pdfBig->Cell($width, $height, $security_line, 0, 0, $text_align);
                            $pdfBig->StopTransform();
                        }
                    break;
                    case 'Dynamic Image':
                        /* we can display image from 2 path if without save preview then check template name is avalable or not
                            if available then image display from template name folder otherwise custom image folder 
                        */
                        if(!$isPreview){
                            $pdf->SetAlpha(1);
                            $pdfBig->SetAlpha(1);
                            $uv_image = $FID['is_uv_image'][$extra_fields];
                            if($uv_image == 1){
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$str;
                                }
                                else{
                                    $location = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$str;
                                }
                              
                                $path_info = pathinfo($location);
                                $file_name = $path_info['filename'];
                                $ext = $path_info['extension'];
                                $uv_location = public_path()."/backend/canvas/dummy_images/".$FID['template_name']."/".$file_name.'_uv.'.$ext;

                                if($ext == 'png')
                                {
                                    $im = imagecreatefrompng($location);
                                    if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0))
                                    {
                                        imagepng($im, $uv_location);
                                        imagedestroy($im);
                                    }
                                    
                                }
                                else if($ext == 'jpeg' || $ext == 'jpg')
                                {
                                    $im = imagecreatefromjpeg($location);
                                    if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0))
                                    {
                                        imagejpeg($im, $uv_location);
                                        imagedestroy($im);
                                    }
                                    
                                }
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $aws_uv_image_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name']."/".$file_name.'_uv.'.$ext;

                                    $aws_default_storage = \Storage::disk('s3')->put($aws_uv_image_path, file_get_contents($uv_location), 'public');
                                    $aws_default_filename = \Storage::disk('s3')->url($file_name.'_uv.'.$ext);
                                    $get_aws_uv_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$file_name.'_uv.'.$ext;
                                }
                                else{
                                    $aws_uv_image_path = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name']."/".$file_name.'_uv.'.$ext;
                                    \File::copy($uv_location,$aws_uv_image_path);
                                    $get_aws_uv_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$file_name.'_uv.'.$ext;
                                }

                                $pdf->image($get_aws_uv_image,$x,$y,$width / 3,$height / 3,"",'','L',true,3600);
                                $pdfBig->image($get_aws_uv_image,$x,$y,$width / 3,$height / 3,"",'','L',true,3600);

                            }
                            else
                            {
                                if($FID['grey_scale'][$extra_fields] == 1){
                                
                                    if($FID['is_uv_image'][$extra_fields] != 1){
                                        if($get_file_aws_local_flag->file_aws_local == '1'){
                                            $pdf->image(\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_name'].'/'.$str, $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                        }
                                        else{
                                            $pdf->image(\Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_name'].'/'.$str, $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                        }
                                    }

                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                        $upload_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_name'].'/'.$str;
                                    }
                                    else{
                                        $upload_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_name'].'/'.$str;
                                    }
                                    $pdfBig->image($upload_image, $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                
                                }else{
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                        $simple_image =  \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_name'].'/'.$str;
                                        $grey_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_name'].'/grey_'.$str;
                                    }
                                    else{
                                        $simple_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_name'].'/'.$str;
                                        $grey_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_name'].'/grey_'.$str;
                                    }


                                    $image = new Imagick($simple_image);
                                    $image->setImageColorspace(imagick::COLORSPACE_GRAY);
                                    $image->stripImage();
                                    $image->writeImage($grey_image);
                                    if($FID['is_uv_image'][$extra_fields] != 1)
                                    {
                                        $pdf->image($grey_image,$x,$y,$width / 3,$height  / 3,"",'','L',true,3600);
                                    }
                                    $pdfBig->image($grey_image,$x,$y,$width / 3,$height  / 3,"",'','L',true,3600);
                                }
                            } 
                            if(isset($uv_location)){

                                unlink($uv_location);
                            }     
                        }
                    break;
                    case 'Static Image':
                        // same as Dynamic Image
                        if(!$isPreview){
                            $uv_image = $FID['is_uv_image'][$extra_fields];
                            if ($uv_image == 1) {
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_name.'/'.$sample_image;
                                }
                                else{
                                    $location = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_name.'/'.$sample_image;
                                }
                                $path_info = pathinfo($location);
                                $file_name = $path_info['filename'];
                                $extension = $path_info['extension'];
                            
                                if($get_file_aws_local_flag->file_aws_local == '1'){  
                                    $uvImagePath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$file_name.'_uv.'.$extension;
                                }
                                else{
                                    $uvImagePath = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$file_name.'_uv.'.$extension;
                                }
                                $pdf->image($uvImagePath,$x,$y,$width / 3,$height  / 3,"",'','L',true,3600);
                                $pdfBig->image($uvImagePath,$x,$y,$width / 3,$height  / 3,"",'','L',true,3600);

                            }
                            else{
                                if($FID['grey_scale'][$extra_fields] == 1){
                                    if($FID['is_uv_image'][$extra_fields] != 1){
                                        if($get_file_aws_local_flag->file_aws_local == '1'){
                                            $pdf->image(\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$FID['sample_image'][$extra_fields], $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                        }
                                        else{
                                            $pdf->image(\Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$FID['sample_image'][$extra_fields], $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                        }
                                    }
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                        $upload_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$FID['sample_image'][$extra_fields];
                                    }
                                    else{
                                        $upload_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$FID['sample_image'][$extra_fields];
                                    }
                                    $pdfBig->image($upload_image, $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);

                                }
                                else
                                {
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                       $simple_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$sample_image;
                                        $grey_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/grey_'.$sample_image;
                                    }
                                    else{
                                        $simple_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/'.$sample_image;
                                        $grey_image = \Config::get('constant.local_base_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_name'].'/grey_'.$sample_image;
                                    }

                                    $image = new Imagick($simple_image);
                                    $image->setImageColorspace(imagick::COLORSPACE_GRAY);
                                    $image->stripImage();
                                    $image->writeImage($grey_image);
                                    if($FID['is_uv_image'][$extra_fields] != 1){
                                        $pdf->image($grey_image,$x,$y,$width / 3,$height  / 3,"",'','L',true,3600);
                                    }
                                    $pdfBig->image($grey_image,$x,$y,$width / 3,$height  / 3,"",'','L',true,3600);
                                }
                            }
                        }
                    break;
                    case 'UV Repeat line':
                        /* this is the combine of invisible and Security line in this task we can create uv repeat line
                            this line use to repeat text but in uv ink so repeate text and then rotate angle wise.              
                        */
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
                                $pdfBig->SetTextColor(255,255, 0);
                            }
                        }
                        if(!$isPreview) {

                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            $pdfBig->StartTransform();
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Rotate($angle);
                            $pdfBig->Cell($width, $height, $security_line, 0, false, $text_align);
                            $pdfBig->StopTransform();
                            $pdfBig->SetOverprint(false, false, 0);
                        }
                    break;
                    case 'Ghost Image':
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

                            $w = $this->CreateMessage( $tmpDir, $name ,$font_size,$print_color);
                            $ghostImgArr[$name] = $w;
                            
                            $name = strtoupper($name);
                            if(!$isPreview){ 
                                $pdf->Image("$tmpDir/" . $name."".$font_size.".png", $x, $y, $w, $imageHeight, "PNG", '', 'L', true, 3600);
                            }
                            $pdfBig->Image("$tmpDir/" . $name."".$font_size.".png", $x, $y, $w, $imageHeight, "PNG", '', 'L', true, 3600);
                            if(!$isPreview){
                                unlink("$tmpDir/" . $name."".$font_size.".png");
                            }
                    break;
                    case 'Invisible':  
                        $pdfBig->SetOverprint(true, true, 0);
                        if(!$isPreview) {
                            if ($print_color == 'CMYK') 
                            {
                                if($font_color_extra == '000000'){
                                    $pdfBig->SetTextColor(0, 0, 0, $uv_percentage, false, '');
                                    }else{
                                    $pdfBig->SetTextColor(0, 0, $uv_percentage,0, false, '');
                                }
                            
                            }else{

                                if($font_color_extra == '000000'){

                                    $pdfBig->SetTextColor(0, 0, 0);
                                
                                }else{
                                    $pdfBig->SetTextColor(255,255, 0);
                                }
                            }
                        

                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            $pdfBig->SetXY($x, $y);
                            $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                            $pdfBig->SetOverprint(false, false, 0);
                        }
                        
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
                                $pdfBig->SetTextColor(255,255, 0);
                            }
                        }
                        $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                        $pdfBig->SetXY($x, $y);
                        $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                        $pdfBig->SetOverprint(false, false, 0);
                    break;  
                    case 'Anti-Copy':
                        
                        if(!$isPreview)
                        {
                            if ($print_color == 'CMYK') 
                            {
                               
                                
                                $imageSource = public_path().'/backend/canvas/ghost_images/S-017-18 ANTICOPY.png';

                                $pdf->Image($imageSource, $x, $y, 50, 10, "PNG", '', 'L', true, 3600);
                                $pdfBig->Image($imageSource, $x, $y, 50, 10, "PNG", '', 'L', true, 3600);
                            }else{
                                
                                $imageSource = public_path().'/backend/canvas/ghost_images/VOID FINAL FOR BROTHER.SVG';
                                

                                $pdf->ImageSVG($file=$imageSource, $x, $y, 50, 10, $link='', $align='', $palign='', $border=0, $fitonpage=false);
                                $pdfBig->ImageSVG($file=$imageSource, $x, $y, 50, 10, $link='', $align='', $palign='', $border=0, $fitonpage=false);
                            }
                        }
                    break;
                    case '1D Barcode':
                        if(!$isPreview){
                            $pdf->write1DBarcode($str, 'C128',$x, $y, $width, $height, 0.4, $style1D, 'N');
                        }
                        $pdfBig->write1DBarcode($str, 'C128', $x, $y, $width, $height, 0.4, $style1D, 'N');
                    break;
                    case 'UV Repeat Fullpage':
                        $security_line = '';
                        for($d = 0; $d < 50; $d++)
                            $security_line .= $str . ' ';

                        $pdfWidth = 210;
                        $pdfHeight = 297;
                        
                        $pdfBig->SetOverprint(true, true, 0);
                        if(!$isPreview){
                            if ($print_color == 'CMYK') {
                                if($font_color_extra == '000000'){
                                    $pdfBig->SetTextColor(0, 0, 0, $uv_percentage, false, '');
                                
                                }else{
                                    $pdfBig->SetTextColor(0, 0, $uv_percentage,0, false, '');
                                }
                            
                            }else{
                                if($font_color_extra == '000000'){
                                    $pdf->SetTextColor(0, 0, 0);
                                    $pdfBig->SetTextColor(0, 0, 0);
                                
                                }else{
                                    $pdf->SetTextColor(255,255, 0);
                                    $pdfBig->SetTextColor(255,255, 0);
                                }
                            }
                            $pdf->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                            for ($i=0; $i <= $pdfHeight; $i+=$line_gap) {

                                $pdf->SetXY(0,$i);
                                $pdf->StartTransform();  
                                $pdf->Rotate($angle);
                                $pdf->Cell(0, 0, $security_line, 0, false, $text_align);
                                $pdf->StopTransform();

                                $pdfBig->SetXY(0,$i);
                                $pdfBig->StartTransform();  
                                $pdfBig->Rotate($angle);
                                $pdfBig->Cell(0, 0, $security_line, 0, false, $text_align);
                                $pdfBig->StopTransform();

                                
                            }
                            for ($j=0; $j < $pdfWidth; $j+=$line_gap) {
                                $pdf->SetXY($j+5,$pdfHeight);
                                $pdf->StartTransform();  
                                $pdf->Rotate($angle);
                                $pdf->Cell(0, 0, $security_line, 0, false, $text_align);
                                $pdf->StopTransform();

                                $pdfBig->SetXY($j+5,$pdfHeight);
                                $pdfBig->StartTransform();  
                                $pdfBig->Rotate($angle);
                                $pdfBig->Cell(0, 0, $security_line, 0, false, $text_align);
                                $pdfBig->StopTransform();
                            }
                        }
                    break;
                    case '2D Barcode':
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
                        if(!$isPreview){
                            $code = (string)$str;
                            $pdf->write2DBarcode($code, 'DATAMATRIX', $x, $y, $width / 3, $height / 3, $style, 'N');
                            $pdfBig->write2DBarcode($code, 'DATAMATRIX', $x, $y, $width / 3, $height / 3, $style, 'N');
                        }
                    break;
                    default:
                    break;
                } //Switch End

                $log_serial_no++;
            } //for loop end
            
            if(!$isPreview) 
            {
               
                $template_name  = $FID['template_name'];
                $template_name = str_replace(' ','_', $template_name);
            
                $dt = date("_ymdHis");
                $certName = str_replace("/", "_", $serial_no).'_'.$dt.'_'.$excel_row.".pdf";
                $myPath = public_path().'/backend/temp_pdf_file';
                $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

                $student_table_id = $this->addCertificate($serial_no, $certName, $dt,$FID['template_id'],$admin_id);
                $username = $admin_id['username'];
                date_default_timezone_set('Asia/Kolkata');
                $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
                $date = date('Y-m-d H:i:s').PHP_EOL;
    
                $print_datetime = date("Y-m-d H:i:s");
                $print_count = $this->getPrintCount($serial_no);
                $printer_name = /*'HP 1020';*/$printer_name;
                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_name,$admin_id,$student_table_id);
            }            
        } // for loop end
        
        if(!$isPreview) {
            if(is_dir($tmpDir)){
                rmdir($tmpDir);
            }
            $filename = '';
            if($get_file_aws_local_flag->file_aws_local == '1') {
                $s3=\Storage::disk('s3');
                $tcpdf_directory = $subdomain[0].'/backend/tcpdf/';
                $examples_directory = $subdomain[0].'/backend/tcpdf/examples/';

                if(!$s3->exists($tcpdf_directory))
                {
                 $s3->makeDirectory($tcpdf_directory, 0777);  
                }if(!$s3->exists($examples_directory))
                {
                 $s3->makeDirectory($examples_directory, 0777);  
            }
            else{
                $tcpdf_directory = public_path().'/'.$subdomain[0].'/backend/tcpdf/';
                $examples_directory = public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/';


                if(!is_dir($tcpdf_directory)){

                    mkdir($tcpdf_directory, 0777);
                }

                if(!is_dir($examples_directory)){

                    mkdir($examples_directory, 0777);
                }

            }


            if($FID['print_type'] == 'pdf'){
                $highestRecord = 10;
                $file_name = $template_name.date("Ymdhms").'.pdf';
                $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
                $pdfBig->output($filename,'F');
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    $aws_qr = \Storage::disk('s3')->put('/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name, file_get_contents($filename), 'public');
                    $filename1 = \Storage::disk('s3')->url($file_name);
                }
                else{
                    $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
                }
                
            }else{
                $inputFile = 'pdfcertificate.pdf';
                $outputFile = 'pdfcertificate.Seqr';
                $filename = public_path().'/backend/tcpdf/examples/pdfcertificate.pdf';
                ob_clean(); 
                $pdfBig->output($filename,'F');
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    $aws_qr = \Storage::disk('s3')->put('/'.$subdomain[0].'/backend/tcpdf/examples/pdfcertificate.pdf', file_get_contents($filename), 'public');
                    $filename1 = \Storage::disk('s3')->url('pdfcertificate.pdf');
                }
                else{
                    $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/pdfcertificate.pdf');
                
                }   
                    
                @unlink($filename);

                $result = $pdfBig->encryptFile($inputFile,$outputFile);
                $file_name = $inputFile;
            }
            //mail pdf
            $mail_view = 'mail.pdf';
            $user_email = $admin_id['email'];
            $mail_subject = "PDF generated from mapped database.";
            $user_data = ['file_name'=>$file_name,'path'=>$filename];
            \File::deleteDirectory(public_path().'/backend/canvas/dummy_images/'.$FID['template_name']);
            SendMailJob::dispatch($mail_view,$user_email,$mail_subject,$user_data);
        }
    }

    public function getPrintCount($serial_no)
    {
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        return $numCount + 1;
    }

    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$student_table_id)
    {
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        // Insert the new record
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'student_table_id'=>$student_table_id]);
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

    public function CreateMessage($tmpDir, $name = "",$font_size,$print_color,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
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
                "Z" => array(20827, 876)
            );

            
            $filename = public_path().'/backend/canvas/ghost_images/F15_H14_W504.png';
            

            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            
            $filename = public_path().'/backend/canvas/ghost_images/alpha_GHOST.png';
            

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
                
            
            $filename = public_path().'/backend/canvas/ghost_images/F12_H8_W288.png';
            

            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename = public_path().'/backend/canvas/ghost_images/alpha_GHOST.png';
            
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
            
            $filename = public_path().'/backend/canvas/ghost_images/F10_H5_W180.png';
            
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename = public_path().'/backend/canvas/ghost_images/alpha_GHOST.png';
            
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
                
            
            $filename = public_path().'/backend/canvas/ghost_images/F11_H7_W250.png';
            
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename = public_path().'/backend/canvas/ghost_images/alpha_GHOST.png'; 
            
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
                "Z" => array(20880, 800)
            
            );
            
            
            $filename = public_path().'/backend/canvas/ghost_images/F13_H10_W360.png';
            
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename = public_path().'/backend/canvas/ghost_images/alpha_GHOST.png';
            
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
                "Z" => array(80884, 808)
            
            );
                
            
            $filename = public_path().'/backend/canvas/ghost_images/F14_H12_W432.png';
            
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename = public_path().'/backend/canvas/ghost_images/alpha_GHOST.png';

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

            
            $filename = public_path().'/backend/canvas/ghost_images/ALPHA_GHOST.png';
            
            $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);

            // Create Backgoround image
            $filename = public_path().'/backend/canvas/ghost_images/alpha_GHOST.png';
            
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

    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        copy($file1, $file2);

        if($get_file_aws_local_flag->file_aws_local == '1') {
            $s3=\Storage::disk('s3');
            $pdf_file_directory = $subdomain[0].'/backend/pdf_file/';

            if(!$s3->exists($pdf_file_directory))
            {
             $s3->makeDirectory($pdf_file_directory, 0777);  
            }
        }
        else{
            $pdf_file_directory = public_path().'/'.$subdomain[0].'/backend/pdf_file/';


            if(!is_dir($pdf_file_directory)){

                mkdir($pdf_file_directory, 0777);
            }

        }

        if($get_file_aws_local_flag->file_aws_local == '1'){
            $aws_qr = \Storage::disk('s3')->put('/'.$subdomain[0].'/backend/pdf_file/'.$certName, file_get_contents($file2), 'public');
            $filename1 = \Storage::disk('s3')->url($certName);
        }
        else{
            $aws_qr = \File::copy($file2,public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName);
        }
      
        @unlink($file2);

        @unlink($file1);

        $sts = 1;
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

        // Mark all previous records of same serial no to inactive if any
        
        $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record

        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts]);

        $dbName = 'seqr_demo';
        
        \DB::disconnect('mysql'); 
        
        \Config::set("database.connections.mysql", [
            'driver'   => 'mysql',
            'host'     => 'seqrdoc.com',
            "port" => "3306",
            'database' => $dbName,
            'username' => 'seqrdoc_multi',
            'password' => 'SimAYam4G2',
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

        $last_add = StudentTable::select('id')->orderBy('id','desc')->skip(0)->take(1)->first();
        $active_documents = StudentTable::select('id')->where('site_id',$auth_site_id)->where('status','1')->count();
        $inactive_documents = StudentTable::select('id')->where('site_id',$auth_site_id)->where('status','0')->count();

        $last_genrate_date = StudentTable::select('created_at')->where('site_id',$auth_site_id)->where('id',$last_add['id'])->first();

        SiteDocuments::where('site_id',$auth_site_id)->update(['active_documents'=>$active_documents]);
        SiteDocuments::where('site_id',$auth_site_id)->update(['inactive_documents'=>$inactive_documents]);
        SiteDocuments::where('site_id',$auth_site_id)->update(['last_genration_date'=>$last_genrate_date['created_at']]);

        if($subdomain[0] == 'demo')
        {
            $dbName = 'seqr_'.$subdomain[0];
        }else{

            $dbName = 'seqr_d_'.$subdomain[0];
        }

        \DB::disconnect('mysql');     
        \Config::set("database.connections.mysql", [
            'driver'   => 'mysql',
            'host'     => 'seqrdoc.com',
            "port" => "3306",
            'database' => $dbName,
            'username' => 'seqrdoc_multi',
            'password' => 'SimAYam4G2',
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
        return $result['id'];
    }

}
