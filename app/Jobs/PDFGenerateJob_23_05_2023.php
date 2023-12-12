<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\models\BackgroundTemplateMaster;
use Auth,DB;
use TCPDF;
use App\models\FontMaster;
use App\models\SystemConfig;
use QrCode;
use App\models\Config;
use App\models\StudentTable;
use App\models\SbStudentTable;
use App\models\PrintingDetail;
use App\models\SbPrintingDetail;

use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;
use App\Jobs\SendMailJob;
use App\models\SiteDocuments;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use Illuminate\Support\Facades\Redis;
use App\models\ThirdPartyRequests;
use App\Helpers\CoreHelper;
use App\models\FieldMaster;
use App\Utility\GibberishAES;

class PDFGenerateJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $timeout = 180000;
    protected $pdf_data;
    

    public function __construct($pdf_data)
    {
        $this->pdf_data = $pdf_data;
       
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
       

        $testArr =array();
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['admin_id']['site_id'];
        }   

        if($subdomain[0]=='test'){
             //ini_set('open_basedir','');
          //  echo get_current_user();
            //echo  ini_get('open_basedir');
            $storageData=CoreHelper::fetchStorageDetails();
            if($storageData['status']&&!empty($storageData['pdf_storage_path'])){
                $storagePath=$storageData['pdf_storage_path'];
            }else{
                $storagePath=public_path();
            }
        }else{
            $storagePath=public_path();
        }
      

        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();

        $pdf_data = $this->pdf_data;

        
        $isPreview = $pdf_data['isPreview'];
        $highestRow = $pdf_data['highestRow'];
        $highestColumn = $pdf_data['highestColumn'];
        $FID = $pdf_data['FID'];
        $Orientation = $pdf_data['Orientation'];
        $template_Width = $pdf_data['template_Width'];
        $template_height = $pdf_data['template_height'];
        $bg_template_img_generate = $pdf_data['bg_template_img_generate'];
        $bg_template_width_generate = $pdf_data['bg_template_width_generate'];
        $bg_template_height_generate = $pdf_data['bg_template_height_generate'];
        $bg_template_img = $pdf_data['bg_template_img'];
        $bg_template_width = $pdf_data['bg_template_width'];
        $bg_template_height = $pdf_data['bg_template_height'];
        $mapped_excel_col_unique_serial_no = $pdf_data['mapped_excel_col_unique_serial_no'];
        $ext = $pdf_data['ext'];
        $fullpath = $pdf_data['fullpath'];
        $tmpDir = $pdf_data['tmpDir'];
        $excelfile = $pdf_data['excelfile'];
        $timezone = $pdf_data['timezone'];
        $printer_name = $pdf_data['printer_name'];
        $style2D = $pdf_data['style2D'];
        $style1D = $pdf_data['style1D'];
        $style1Da = $pdf_data['style1Da'];
        $font_name = $pdf_data['font_name'];
        $admin_id = $pdf_data['admin_id'];
        
        //API Changes
        if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=="API"){
            $inputSource = $pdf_data['inputSource'];
            $jsonData = $pdf_data['jsonData'];
        }else{
            $inputSource ="";
        }

        $fonts_array = $pdf_data['fonts_array'];
        $excel_row_num = $pdf_data['excel_row_num'];
        $pdf_flag = $pdf_data['pdf_flag'];
       
        $template_name = '';
        if(isset($FID['template_name'])){
            $template_name = $FID['template_name'];
        }
        $actual_template_name = '';
        if(isset($FID['actual_template_name'])){
            $actual_template_name = $FID['actual_template_name'];
        }

        if($ext == 'xlsx' || $ext == 'Xlsx'){
            $inputFileType = 'Xlsx';
        }
        else{
            $inputFileType = 'Xls';
        }
        
        if(!$isPreview&&($inputSource =="Excel"||$inputSource =="")){
            $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
            /**  Load $inputFileName to a Spreadsheet Object  **/
            $objPHPExcel = $objReader->load($fullpath);
            $sheet = $objPHPExcel->getSheet(0);
        }

        if($pdf_flag == 0){
            \Session::forget('pdf_obj');
            $pdfBig = new TCPDF($Orientation, 'mm', array($bg_template_width_generate, $bg_template_height_generate), true, 'UTF-8', false);
            \Session::put('pdf_obj',$pdfBig);
        }
        if(\Session::get('pdf_obj') != null){
            $pdfBig = \Session::get('pdf_obj');
        }

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);

        if($subdomain[0]=="minoshacloud"){
        $pdfBig->AddSpotColor('Special', 0, 0, 100, 0);
        }

        $log_serial_no = 1;
        // dd($highestRow);
        if(isset($excel_row)){
            $excel_row = (int)$excel_row;
        }
        
        for($excel_row = $excel_row_num; $excel_row <= $highestRow; $excel_row++)
        {

    
            // Get Excel data
            if(!$isPreview) {
                
                if($inputSource =="JSON"){
            
                     $rowData =array($jsonData[$excel_row-1]);
                     $firstRow =array($jsonData[0]);
                     
                }else if ($FID['is_mapped'][0] == 'excel') {// code for getting data from excel or table colums value 
                    $rowData = $sheet->rangeToArray('A'. $excel_row . ':' . $highestColumn . $excel_row, NULL, TRUE, FALSE);
                    $firstRow =$sheet->rangeToArray('A1:' . $highestColumn .'1', NULL, TRUE, FALSE);
                    if($this->isEmptyRow(reset($rowData))) {
                        continue; 
                    }
                }
                else
                {
                    $k = $excel_row - 1;
                    $rowData = $get_mapped_data[$k - 1];
                    $firstRow = $get_mapped_data[0];
                }
                $pdf = new TCPDF($Orientation, 'mm', array($template_Width, $template_height), true, 'UTF-8', false);

                $pdf->SetCreator('TCPDF');
                $pdf->SetAuthor('TCPDF');
                $pdf->SetTitle('Certificate');
                $pdf->SetSubject('');

                // remove default header/footer
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetAutoPageBreak(false, 0);
                if($subdomain[0]=="minoshacloud"){
                $pdf->AddSpotColor('Special', 0, 0, 100, 0);
                }
                $pdf->SetCreator('SetCreator');

                $pdf->AddPage();
                if($bg_template_img_generate != ''){
                    $template_img_generate = str_replace(' ', '+', $bg_template_img_generate);
                    $pdf->Image($template_img_generate, 0, 0, $bg_template_width_generate, $bg_template_height_generate, "JPG", '', 'R', true);
                }
            }
           
            $pdfBig->AddPage();
            
            if($bg_template_img != ''){
                
                $template_img = str_replace(' ', '_', $bg_template_img);    
                $ext = pathinfo($bg_template_img, PATHINFO_EXTENSION);
            
                $image_extension = 'JPG';
                if ($ext == 'PNG' || 'png') {
                    $image_extension = 'PNG';
                }
                if ($ext == 'jpg') {
                    $image_extension = 'JPG';
                }
         
                $pdfBig->Image($template_img, 0, 0, $bg_template_width, $bg_template_height, $image_extension, '', 'R', true);
            }
           
         

            $pdfBig->SetCreator('TCPDF');

            // Get Serial No
            if(!$isPreview){
            //print_r($rowData[0]);
                //code for check map excel or database and pass serial no 
                $pdf->SetTextColor(0, 0, 0, 100, false, '');
                if ($FID['is_mapped'][0] == 'excel') {
                    //echo $mapped_excel_col_unique_serial_no;
                   // exit;
                    $serial_no = $rowData[0][$mapped_excel_col_unique_serial_no];
                }
                else
                {
                    $serial_no = 1;
                }
            }
            
            // put data into pdf
            if(!$isPreview) {
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
                    else{
                        //code for check map excel or database 
                        // get string from excel columns or database selected columns value
                        if ($FID['is_mapped'][0] == 'excel') {
                            if(isset($rowData[0][$FID['mapped_excel_col'][$extra_fields]])){
                                $str = $rowData[0][$FID['mapped_excel_col'][$extra_fields]];
                            }
                            else{
                                $str = '';
                            }
                        }else{
                            if(isset($rowData[$FID['mapped_name'][$extra_fields]]))
                                $str = $rowData[$FID['mapped_name'][$extra_fields]];
                            else
                                $str = 'Not mapped';
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

                
                if(!$isPreview){

                    $pdfBig->SetTextColor(0, 0, 0, 100, false, '');
                }
                
                    //Generating new print no
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
                $singleQuotePosition = strpos($str, "'");


                if ($singleQuotePosition === 0){
                
                    if($subdomain[0]=='raisoni'){
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

                            if(!$isPreview) {
                                
                                if($FID['infinite_height'][$extra_fields] != 1){
                                    
                                    if ($FID['visible'][$extra_fields] == 0) {


                                        
                                        $pdfBig->SetAlpha(1);

                                        if(($extra_fields==86&&isset($FID['data'][86])&&$template_name=="GalgotiyaUniversity")||($extra_fields==111&&isset($FID['data'][111])&&$template_name=="GalgotiyaUniversity-copy")){
                                       $strArr = str_split($str);
                                       $x_org=$x;
                                       $y_org=$y;
                                       $font_size_org=$font_size;
                                       $i =0;
                                       $j=0;
                                       $y=$y+4.5;
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
                                            
                                           
                                            $pdfBig->Cell($width, 0, $character, 0, $ln=0,  $text_align, 0, '', 0, false, 'B', 'B');
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
                                        $pdfBig->Cell($width, $height, $str, 0, false, $text_align);

                                        }

                                    }
                                   
                                    if ($FID['visible_varification'][$extra_fields] == 0) {
                                        if(($extra_fields==86&&isset($FID['data'][86])&&$template_name=="GalgotiyaUniversity")||($extra_fields==111&&isset($FID['data'][111])&&$template_name=="GalgotiyaUniversity-copy")){
                                       $strArr = str_split($str);

                                       $x=$x_org;
                                       $y=$y_org;
                                       $font_size=$font_size_org;
                                       $i =0;
                                       $j=0;
                                       $y=$y+4.5;
                                       $z=0;
                                       foreach ($strArr as $character) {
                                            $pdf->SetTextColor($r,$g,$b);
                                            $pdf->SetFont($fonts_array[$extra_fields],$bold, $font_size, '', false);

                                            $pdf->SetXY($x, $y+$z);
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
                                            
                                            $pdf->Cell($width, 0, $character, 0, $ln=0,  $text_align, 0, '', 0, false, 'B', 'B');
                                            $i++;
                                            $x=$x+2.2+$j; 
                                            if($i>2){
                                               
                                             $font_size=$font_size+1.7;   
                                            }
                                      
                                       }
                                      
                                    }else{

                                    
                                        $pdf->SetTextColor($r,$g,$b);
                                        $pdf->SetFont($fonts_array[$extra_fields],$bold, $font_size, '', false);
                                        $pdf->SetXY($x, $y);
                                        $pdf->Cell($width, $height, $str, 0, false, $text_align);

                                    }
                                    
                                        $pdf->SetAlpha(1);
                                    }

                                }else{
                                    
                                    /*if($subdomain[0]=='demo'){
                                            echo $str;
                                        }*/
                                    if ($FID['visible_varification'][$extra_fields] == 0) {
                                    
                                        $pdf->SetAlpha(1);
                                        $pdf->SetFillColor(255, 255, 255);
                                        $pdf->SetTextColor($r, $g, $b);
                                        
                                        if ($fonts_array[$extra_fields] == 'times_b' || $fonts_array[$extra_fields] == 'times_n' || $fonts_array[$extra_fields] == 'times_i') {
                                            
                                            $fonts_array[$extra_fields] = 'times';
                                        }
                                        $pdf->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                    
                                        $pdf->MultiCell($width, $height, $str."\n", 0, $text_align, 1, 1, $x, $y, true, 0, false, true, 0, 'T', true);
                                    }

                                    if ($FID['visible_varification'][$extra_fields] == 0) {
                                        

                                        $pdfBig->SetAlpha(1);
                                        $pdfBig->SetFillColor(255, 255, 255);
                                        $pdfBig->SetTextColor($r, $g, $b);
                                        $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                        $pdfBig->MultiCell($width, $height, $str."\n", 0, $text_align, 1, 1, $x, $y, true, 0, false, true, 0, 'T', true);
                                    }


                                }
                            }
                            
                        break;

                    case 'QR Code':

                    $fullString=$FID['combo_qr_text'][$extra_fields]; 

                    if($subdomain[0]=="demo") {
                        $EncryptedString=$FID['encrypted_qr_text'][$extra_fields]; 
                    }

                   // $fullString = "{{QR Code}}";
                    if($fullString!="{{QR Code}}"){
                        if (preg_match_all('#\{\{(.*?)\}\}#', $fullString, $matches)) {
                        
                            $qr_fields=$matches[1]; // Get Group 1 values
                           
                            foreach ($qr_fields as $field) {

                                $columnIndex = array_search($field, $FID['name']);
                                $columnName=$FID['mapped_name'][$columnIndex];
                                  $columnIndexRow = array_search($columnName, $firstRow[0]);
                           
                                if ($FID['is_mapped'][$columnIndex] == 'excel') {
                                    if(isset($rowData[0][$columnIndexRow])){
                                        $strComboQr = $rowData[0][$columnIndexRow];
                                    }
                                    else{
                                        $strComboQr = '';
                                    }
                                }else{
                                    if(isset($rowData[$columnIndexRow]))
                                        $strComboQr = $rowData[$columnIndexRow];
                                    else
                                        $strComboQr = '';
                                }

                                $fullString=str_replace("{{".$field."}}",$strComboQr,$fullString);                       
                            }
                        
                        }
                        
                        $dt = date("_ymdHis");
                        $codeContents = strtoupper(md5($str . $dt));
                        //$codeData = $fullString."\n\n".$codeContents;
                        //Blockchain Integration
                        if($subdomain[0]=="demo"&&($FID['template_id']==697||$FID['template_id']==698||$FID['is_block_chain']==1)){
                            $codeData=$fullString."\n\n".CoreHelper::getBCVerificationUrl(encrypt($codeContents));
                        }else{
                            if($subdomain[0]=="nidantechnologies"&&$FID['template_id']==12){
                                $certName = preg_replace('/[^a-zA-Z0-9]/', '_', $serial_no).".pdf";
                                $url = 'http://'.$subdomain[0].'.'.$subdomain[1].'.com/'; 
                                $codeData =$url.'/'.$subdomain[0].'/backend/pdf_file/'.$certName;
;
                            }else{
                                $codeData = $fullString."\n\n".$codeContents;    
                            }
                        }

                    }

                    if($subdomain[0]=="demo") {
                        if(!empty($EncryptedString)){
                            // if($FID['is_encrypted_qr'][$extra_fields] == 1) {
                            //     $EncryptedString= GibberishAES::enc($EncryptedString, \Config::get('constant.EStamp_Salt'));
                            // }
                            if (preg_match_all('#\{\{(.*?)\}\}#', $EncryptedString, $matches)) {
                            
                                $qr_fields=$matches[1]; // Get Group 1 values
                               
                                foreach ($qr_fields as $field) {

                                    $columnIndex = array_search($field, $FID['name']);
                                    $columnName=$FID['mapped_name'][$columnIndex];
                                      $columnIndexRow = array_search($columnName, $firstRow[0]);
                               
                                    if ($FID['is_mapped'][$columnIndex] == 'excel') {
                                        if(isset($rowData[0][$columnIndexRow])){
                                            $strComboQr = $rowData[0][$columnIndexRow];
                                        }
                                        else{
                                            $strComboQr = '';
                                        }
                                    }else{
                                        if(isset($rowData[$columnIndexRow]))
                                            $strComboQr = $rowData[$columnIndexRow];
                                        else
                                            $strComboQr = '';
                                    }

                                    $EncryptedString=str_replace("{{".$field."}}",$strComboQr,$EncryptedString);                       
                                }
                            
                            }
                            
                            $dt = date("_ymdHis");
                            
                            $QRName = strtoupper(md5($str . $dt));
                            $codeContents = strtoupper(md5($str . $dt));
                            //$codeData = $EncryptedString."\n\n".$codeContents;
                            $conStriCode = $EncryptedString."\n\n".$codeContents;
                            // echo $EncryptedString;
                            // echo "b\n\n" ;
                            if($FID['is_encrypted_qr'][$extra_fields] == 1) {
                                $conStriCode= GibberishAES::enc($conStriCode, \Config::get('constant.EStamp_Salt'));
                            }
                            // echo $conStriCode;
                            // die();
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
                    }
                    

                    if($fullString=="{{QR Code}}"){                        
                        
                        $dt = date("_ymdHis");
                        $codeContents = strtoupper(md5($str . $dt));
                        //$codeData = $codeContents;
                        //Blockchain Integration
                        if($subdomain[0]=="demo"&&($FID['template_id']==697||$FID['template_id']==698||$FID['is_block_chain']==1)){
                            $codeData=CoreHelper::getBCVerificationUrl(encrypt($codeContents));
                        }else{
                            if($subdomain[0]=="nidantechnologies"&&$FID['template_id']==12){
                                $certName = preg_replace('/[^a-zA-Z0-9]/', '_', $serial_no).".pdf";
                                $url = 'http://'.$subdomain[0].'.'.$subdomain[1].'.com/'; 
                                $codeData =$url.'/'.$subdomain[0].'/backend/pdf_file/'.$certName;
;
                            }else{
                                $codeData = $codeContents;
                            }
                        }
                    }

                            $pngAbsoluteFilePath = "$tmpDir/$codeContents.png";
                            if(!$isPreview){

                                if($FID['include_image'][$extra_fields] == 1){
                                    $logopath = $FID['sample_image'][$extra_fields];
                                    
                                    if($logopath == "null"){

                                        $logopath = public_path()."/backend/canvas/dummy_images/QR.png";
                                    }
                                }else{
                                    
                                    $logopath = public_path()."/backend/canvas/dummy_images/QR.png";
                                }
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
                        
                            if(!$isPreview){
                                if($FID['visible'][$extra_fields] == 0){

                                    $pdfBig->SetAlpha(1);
                                    $pdfBig->Image($pngAbsoluteFilePath, $x, $y, $width, $height, "PNG", '', 'R', true);
                                }
                                if($FID['visible_varification'][$extra_fields] == 0){
                                
                                    $pdf->SetAlpha(1);
                                    $pdf->Image($pngAbsoluteFilePath, $x, $y, $width, $height, "PNG", '', 'R', true);
                                }
                            }
                            
                            if(file_exists($pngAbsoluteFilePath)){
                                unlink($pngAbsoluteFilePath);
                            }
                        
                    break;
                    case 'Qr Code':

                  
                   
                                
                            $fullString = $FID['combo_qr_text'][$extra_fields];
                            if($fullString !="{{Dummy Text}}"){
                                if (preg_match_all('#\{\{(.*?)\}\}#', $fullString, $matches)) {
                                   
                                        $qr_fields=$matches[1]; // Get Group 1 values
                                       
                                        foreach ($qr_fields as $field) {

                                            $columnIndex = array_search($field, $FID['name']);
                                            $columnName=$FID['mapped_name'][$columnIndex];
                                              $columnIndexRow = array_search($columnName, $firstRow[0]);
                                           
                                            if ($FID['is_mapped'][$columnIndex] == 'excel') {
                                                if(isset($rowData[0][$columnIndexRow])){
                                                    $strComboQr = $rowData[0][$columnIndexRow];
                                                }
                                                else{
                                                    $strComboQr = '';
                                                }
                                            }else{
                                                if(isset($rowData[$columnIndexRow]))
                                                    $strComboQr = $rowData[$columnIndexRow];
                                                else
                                                    $strComboQr = '';
                                            }

                                            $fullString=str_replace("{{".$field."}}",$strComboQr,$fullString);
                                        }
                                    
                                }
                                
                                $codeContentsData =$fullString;
                                
                            }else{
                                
                                $codeContentsData =$str;  
                                
                            }
                            $dt = date("_ymdHis");
                            
                            $codeContents = $str;

                            
                            $strName = str_replace(' ', '-', $str); // Replaces all spaces with hyphens.
                            $strName =  preg_replace('/[^A-Za-z0-9\-]/', '', $strName); // Removes special chars.
                               $codeName=str_replace('/', '\\', $strName);
                            $pngAbsoluteFilePath = "$tmpDir/$codeName.png";
                            
                            if(!$isPreview){
                                if($FID['include_image'][$extra_fields] == 1){
                                    $field_image = explode('qr/',$FID['sample_image'][$extra_fields]);
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                        $logopath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.canvas').'/qr/'.$field_image[1];
                                    }
                                    else{
                                        $logopath = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.canvas').'/qr/'.$field_image[1];
                                    }
                                    $image_name = $field_image[1];
                                }else{
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                        $logopath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.canvas')."/QR.png";
                                    }
                                    else{
                                        $logopath = public_path()."/backend/canvas/dummy_images/QR.png";
                                    }
                                    $image_name = 'QR.png';
                                }  
                                 
                                QrCode::format('png')->size(200)->generate($codeContentsData,$pngAbsoluteFilePath);
                                $QR = imagecreatefrompng($pngAbsoluteFilePath);
                                $include_image = $FID['include_image'][$extra_fields];

                                if($include_image == 1){
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
                                

                                if($include_image == 1){

                                    $scale = $logo_width/$logo_qr_width;
                                    $logo_qr_height = $logo_height/$scale;

                                    imagecopyresampled($QR, $logo, $QR_width/3, $QR_height/3, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);

                                }
                                
                                // Save QR code again, but with logo on it
                                imagepng($QR,$pngAbsoluteFilePath);
                                if($FID['visible'][$extra_fields] == 0){
                                
                                    $pdfBig->SetAlpha(1);
                                    $pdfBig->Image($pngAbsoluteFilePath, $x, $y, $width / 3, $height / 3, "PNG", '', 'R', true);
                                }
                                if($FID['visible_varification'][$extra_fields] == 0){
                                
                                    $pdf->SetAlpha(1);
                                    $pdf->Image($pngAbsoluteFilePath, $x, $y, $width / 3, $height / 3, "PNG", '', 'R', true);
                                }


                                $pngAbsoluteFilePath1 = '/'.$subdomain[0].'/backend/canvas/images/qr/'.$codeName.'.png';
                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                    $aws_qr = \Storage::disk('s3')->put($pngAbsoluteFilePath1, file_get_contents($pngAbsoluteFilePath), 'public');
                                    $filename1 = \Storage::disk('s3')->url($codeName.'.png');
                                }
                                else{
                                    $aws_qr = \File::copy($pngAbsoluteFilePath,public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$codeName.'.png');
                                }

                            }
                                             
                    break;
                    case 'ID Barcode':
                        if($auth_site_id != 238){

                            if(!$isPreview){

                                if($FID['visible'][$extra_fields] == 0){
                                    
                                    $pdfBig->SetAlpha(1);
                                    $pdfBig->write1DBarcode($print_serial_no, 'C128', $x, $y, $width, $height, 0.4, $style1Da, 'N');
                                }
                                if($FID['visible_varification'][$extra_fields] == 0){
                                    
                                    $pdf->SetAlpha(1);
                                    $pdf->write1DBarcode($print_serial_no, 'C128', $x, $y, $width, $height, 0.4, $style1Da, 'N');
                                }

                            }
                        }
                    break;
                     
                    case 'Micro line':

                    	

                        if(!$isPreview) {

                            $str = preg_replace('/\s+/', '', $str); //added by Mandar
                            $textArray = imagettfbbox(1.4, 0, public_path().'/backend/fonts/Arial.ttf', $str);
                            $strWidth = ($textArray[2] - $textArray[0]);
                            $strHeight = $textArray[6] - $textArray[1] / 1.4;
                            
                            $latestWidth = round($width*3.7795280352161);

                             //Updated by Mandar
                            $microlinestr=$str;
                            $microlinestrLength=strlen($microlinestr);

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

                            $wd = '';
                            $last_width = 0;
                           
                            if($FID['visible_varification'][$extra_fields] == 0){
                                if($subdomain[0]=='minoshacloud'){
                                $pdf->SetFont($fonts_array[$extra_fields], $bold, 2, '', false);
                                }else{
                                $pdf->SetFont($fonts_array[$extra_fields], $bold, 2, '', false);
                                }
                                $pdf->SetTextColor(0, 0, 0);
                                $pdf->StartTransform();
                                $pdf->SetXY($x, $y);
                                $pdf->Rotate($angle);
                                $is_repeat =  $FID['is_repeat'][$extra_fields];
                                if($is_repeat == 1){

                                    $pdf->Cell($width, $height, $array, 0, false, $text_align);
                                }else{
                                    $pdf->Cell($width, $height, $str, 0, false, $text_align);
                                }

                                $pdf->StopTransform();
                            }
                            if($FID['visible'][$extra_fields] == 0){

                                if($subdomain[0]=='minoshacloud'){
                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 2, '', false);
                                }else{
                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 2, '', false);
                                }
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

                                if($subdomain[0]=="demo"){
                                    // $explode_default = explode(',',$FID['field_sample_text_width'][0]);
                                    //$explode_vertical = explode(',',$FID['field_sample_text_vertical_width'][0]);
                                    //$explode_horizontal = explode(',',$FID['field_sample_text_horizontal_width'][0]);
                                //  $length = $explode_default[$draw_key];
                                    $vertical_length = $height * (25.4/100);
                                    $horizontal_length = $width *  (25.4/100);
                                
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
                                    //echo   $numlines = $pdfBig->getNumLines($array, $horizontal_length);
                                    //exit;
                                    /*******Code To trim characters**********************************************************/
                                    $pdfBigTemp = new TCPDF($Orientation, 'mm', array($bg_template_width, $bg_template_height), true, 'UTF-8', false);
                                    $pdfBigTemp->addPage();
                                    $pdfBigTemp->startTransaction();
                                    $pdfBigTemp->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                                    $strlenHorizontal=strlen($array);
                                    $firstLoop=true;
                                    do{
                                        if(!$firstLoop){
                                        $strlenHorizontal=$strlenHorizontal-1;
                                        }
                                        $array = substr($array,0,$strlenHorizontal);
                                        $lines = $pdfBigTemp->MultiCell($horizontal_length, 0, $array, 0, 'C', 0, 0, '', '', true, 0, false,true, 0);
                                    // $lines = $pdfBig->getNumLines($array, $horizontal_length);
                                        $firstLoop=false;
                                    }while($lines>1);
                                    $pdfBigTemp->rollbackTransaction(true);
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
                                    //$pdfBig->startTransaction();
                                    $pdfBig->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                                    $strlenVerticle=strlen($verticle_array);
                                    $firstLoop=true;
                                    do{
                                        if(!$firstLoop){
                                        $strlenVerticle=$strlenVerticle-1;
                                        }
                                        $verticle_array = substr($verticle_array,0,$strlenVerticle);
                                        //$lines = $pdfBig->MultiCell($vertical_length, 0, $verticle_array, 0, 'C', 0, 0, '', '', true, 0, false,true, 0);
                                        $lines = $pdfBig->getNumLines($verticle_array, $vertical_length);

                                        $firstLoop=false;
                                    }while($lines>1);
                                    //$pdfBig=$pdfBig->rollbackTransaction(true);
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
                                    
                                    /**************Verification PDF***********************************/
                                    // first horizontal line
                                    $pdf->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                                    $pdf->SetXY($x, $y);
                                    $pdf->Cell($horizontal_length, 0, $horizontalString, 0, false, $text_align);
                                    
                                    //first vertical line
                                    $pdf->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                                    $pdf->StartTransform();
                                    $pdf->SetXY($x + 1, $y-1);
                                    $pdf->Rotate(-90);
                                    $pdf->Cell($vertical_length, 0, $verticleString, 0, false, $text_align);
                                    $pdf->StopTransform();

                                    // second horizontal line
                                    $pdf->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                                    $pdf->SetXY($x, $y + $vertical_length-2.5);
                                    $pdf->Cell($horizontal_length, 0, $horizontalString, 0, false, $text_align);
                                    $pdf->SetFont($fonts_array[$extra_fields], $bold, 1, '', false);
                                    $pdf->StartTransform();

                                    //second vertical line
                                    $pdf->SetXY($x+ $horizontal_length-1,$y-1);// / 1.18
                                    $pdf->Rotate(-90);
                                    $pdf->Cell($vertical_length, 0, $verticleString, 0, false, $text_align);
                                    $pdf->StopTransform();

                                }else{
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
                                if($FID['visible_varification'][$extra_fields] == 0){
                                
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
                                }
                                if($FID['visible'][$extra_fields] == 0){
                                
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

                            }
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
                                
                                if($FID['visible_varification'][$extra_fields] == 0){
                                
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
                                }

                                if($FID['visible'][$extra_fields] == 0){
                                
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

                            }
                            $microtext_key++;
                        
                        break;
                    case 'Static Text':

                            if($FID['visible_varification'][$extra_fields] == 0){
                            
                                $pdf->SetAlpha(1);
                                $pdf->SetTextColor($r,$g,$b);
                                // dd($fonts_array);
                                $pdf->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                $pdf->SetXY($x, $y);
                                $pdf->Cell($width, $height, $str, 0, false, $text_align);
                            }    
                            

                            if($FID['visible'][$extra_fields] == 0){
                            
                                $pdfBig->SetAlpha(1);
                                $pdfBig->SetTextColor($r,$g,$b);
                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                $pdfBig->SetXY($x, $y);
                                $pdfBig->Cell($width, $height, $str, 0, false, $text_align);
                            }
                        

                        break;

                    case 'Security line':
                        
                         	//Updated by mandar
                            if($FID['is_font_case'][$extra_fields]==1){
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
                            
                            if(!$isPreview) {
                                
                                if($FID['visible_varification'][$extra_fields] == 0){
                                
                                    $pdf->SetTextColor($r,$g,$b);
                                    $pdf->SetAlpha($FID['text_opacity'][$extra_fields]);
                                    $pdf->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                    $pdf->StartTransform();
                                    $pdf->SetXY($x-2, $y);
                                    $pdf->Rotate($angle);
                                    $pdf->Cell($width, $height, $security_line, 0, 0, $text_align);
                                    $pdf->StopTransform();
                                }
                                if($FID['visible'][$extra_fields] == 0){
                                
                                    $pdfBig->SetTextColor($r,$g,$b);
                                    $pdfBig->SetAlpha($FID['text_opacity'][$extra_fields]);
                                    $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                    $pdfBig->StartTransform();
                                    $pdfBig->SetXY($x-2, $y);
                                    $pdfBig->Rotate($angle);
                                    $pdfBig->Cell($width, $height, $security_line, 0, 0, $text_align);
                                    $pdfBig->StopTransform();
                                }

                            }
                        
                        break;
                    case 'Dynamic Image':
                        /* we can display image from 2 path if without save preview then check template name is avalable or not
                            if available then image display from template name folder otherwise custom image folder 
                        */
                        
                             $str = trim($str);
                            if(!$isPreview){
                                
                                $uv_image = $FID['is_uv_image'][$extra_fields];
                                if($uv_image == 1){

                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                 
                                        $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$str;
                                        
                                    }
                                    else{
                                        
                                        $location = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$str;
                                        
                                    }
                                  
                                    $path_info = pathinfo($location);
                                    
                                    $file_name = $path_info['filename'];
                                    $ext = $path_info['extension'];
                                    
                                   // echo $location;
                                   // echo "<br>";
                                    $uv_location = public_path()."/backend/canvas/dummy_images/".$FID['template_name']."/".$file_name.'_uv.'.$ext;

                                    /*if($ext == 'png'){

                                        $im = imagecreatefrompng($location);

                                        if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0))
                                        {
                                          
                                            imagepng($im, $uv_location);
                                            imagedestroy($im);
                                        }
                                        
                                    }else if($ext == 'jpeg' || $ext == 'jpg'){

                                        $im = imagecreatefromjpeg($location);

                                        if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0))
                                        {
                                          
                                            imagejpeg($im, $uv_location);
                                            imagedestroy($im);
                                        }
                                        
                                    }*/

                                     if($ext == 'png'){
                                        $im = imagecreatefrompng($location);
                                                            imagefilter($im, IMG_FILTER_GRAYSCALE);
                                                            imagefilter($im, IMG_FILTER_NEGATE);
                                                            imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0);        
                                                            imagejpeg($im, $uv_location);
                                                            imagedestroy($im);
                                                
                                    }else if($ext == 'jpeg' || $ext == 'jpg'){
                                        $im = imagecreatefromjpeg($location);
                                                            imagefilter($im, IMG_FILTER_GRAYSCALE);
                                                            imagefilter($im, IMG_FILTER_NEGATE);
                                                            imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0);        
                                                            imagejpeg($im, $uv_location);
                                                            imagedestroy($im);        
                                                             
                                    }

                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                       
                                            $aws_uv_image_path = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id']."/".$file_name.'_uv.'.$ext;
                                        

                                        $aws_default_storage = \Storage::disk('s3')->put($aws_uv_image_path, file_get_contents($uv_location), 'public');
                                        $aws_default_filename = \Storage::disk('s3')->url($file_name.'_uv.'.$ext);
                                
                                            $get_aws_uv_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$file_name.'_uv.'.$ext;
                                        
                                    
                                             if($FID['visible_varification'][$extra_fields] == 0){
                                    
                                                $pdf->SetAlpha(1);
                                                $pdf->image($get_aws_uv_image,$x,$y,$width / 3,$height / 3,"",'','L',true,3600);
                                            }


                                            if($FID['visible'][$extra_fields] == 0){
                                            
                                                $pdfBig->SetAlpha(1);
                                                $pdfBig->image($get_aws_uv_image,$x,$y,$width / 3,$height / 3,"",'','L',true,3600);
                                            }
                                    }
                                    else{
                                     
                                            $aws_uv_image_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id']."/".$file_name.'_uv.'.$ext;
                                        

                                        \File::copy($uv_location,$aws_uv_image_path);
                                 
                                    //        $get_aws_uv_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$file_name.'_uv.'.$ext;
                                         if($FID['visible_varification'][$extra_fields] == 0){
                                    
                                            $pdf->SetAlpha(1);
                                            $pdf->image($aws_uv_image_path,$x,$y,$width / 3,$height / 3,"",'','L',true,3600);
                                        }


                                        if($FID['visible'][$extra_fields] == 0){
                                        
                                            $pdfBig->SetAlpha(1);
                                            $pdfBig->image($aws_uv_image_path,$x,$y,$width / 3,$height / 3,"",'','L',true,3600);
                                        }  
                                    }
                                    
                                   

                                }else{

                                    if($FID['grey_scale'][$extra_fields] == 1){
                                        if($FID['is_uv_image'][$extra_fields] != 1){

                                            if($FID['visible_varification'][$extra_fields] == 0){
                                                if($get_file_aws_local_flag->file_aws_local == '1'){
                                         
                                                        $pdf->image(\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/'.$str, $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                                  
                                                }
                                                else{
                                         
                                                        $pdf->image(public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/'.$str, $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                                    
                                                }
                                            }
                                        }
                                        if($get_file_aws_local_flag->file_aws_local == '1'){
                                    
                                                $upload_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/'.$str;
                                            
                                        }
                                        else{
                                      
                                                $upload_image = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/'.$str;
                                           
                                        }
                                        /*if($FID['visible'][$extra_fields] == 0){
                                        	$pdfBig->SetAlpha(1);
                                            $pdfBig->image($upload_image, $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                        }*/
                                        if($FID['is_transparent_image'][$extra_fields] == 1){
                                            $upload_image_org = $upload_image;
                                            $pathInfo = pathinfo($upload_image);
                                            $upload_image = $pathInfo['dirname'].'/'.$pathInfo['filename'].'_'.$excel_row.'.'.$pathInfo['extension'];
                                            \File::copy($upload_image_org,$upload_image);
                                        
                                        
                                            if($FID['visible'][$extra_fields] == 0){
                                               
                                                $pdfBig->SetAlpha(1);
                                                $pdfBig->image($upload_image, $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                            }
                                       
                                        }else{
                                            
                                           if($FID['visible'][$extra_fields] == 0){
                                               
                                                $pdfBig->SetAlpha(1);
                                                $pdfBig->image($upload_image, $x, $y,$width / 3,$height / 3, "", '', 'L', true, 3600);
                                            }
                                        }

                                    }else{
                                        if($get_file_aws_local_flag->file_aws_local == '1'){
                                      
                                                $simple_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/'.$str;
                                                $grey_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/grey_'.$str;
                                          
                                        }
                                        else{
                                   
                                                $simple_image = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/'.$str;
                                                $grey_image = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template')."/".$FID['template_id'].'/grey_'.$str;
                                            
                                        }


                                       
                                        $path_info = pathinfo($simple_image);
                                    
                                    $file_name = $path_info['filename'];
                                    $ext = $path_info['extension'];
                                    
                                    // $uv_location = public_path()."/backend/canvas/dummy_images/".$FID['template_name']."/".$file_name.'_uv.'.$ext;

                                    if($ext == 'png'){

                                        $im = imagecreatefrompng($simple_image);

                                        if($im && imagefilter($im, IMG_FILTER_GRAYSCALE) )//&& imagefilter($im, IMG_FILTER_CONTRAST, -255)
                                        {
                                          
                                            imagepng($im, $grey_image);
                                            imagedestroy($im);
                                        }
                                        
                                    }else if($ext == 'jpeg' || $ext == 'jpg'){

                                        $im = imagecreatefromjpeg($simple_image);

                                        if($im && imagefilter($im, IMG_FILTER_GRAYSCALE) )//&& imagefilter($im, IMG_FILTER_CONTRAST, -255)
                                        {
                                          
                                            imagejpeg($im, $grey_image);
                                            imagedestroy($im);
                                        }
                                        
                                    }
                                        if($FID['is_uv_image'][$extra_fields] != 1){

                                            if($FID['visible_varification'][$extra_fields] == 0){
                                            	$pdf->SetAlpha(1);
                                                $pdf->image($grey_image,$x,$y,$width / 3,$height  / 3,"",'','L',true,3600);
                                            }
                                        }
                                        if($FID['visible'][$extra_fields] == 0){
                                        	$pdfBig->SetAlpha(1);
                                            $pdfBig->image($grey_image,$x,$y,$width / 3,$height  / 3,"",'','L',true,3600);
                                        }
                                    }
                                }
                                if(isset($uv_location)){

                                    unlink($uv_location);
                                }

                            }
                        // }
                        break;
                    case 'Static Image':
                        // same as Dynamic Image
                            

                            $extension = explode(".", $FID['sample_image'][$extra_fields]);
                            $ext = strtoupper($extension[1]);
                            
                            $uv_image = $FID['is_uv_image'][$extra_fields];

                            if ($uv_image == 1) {

                                if($get_file_aws_local_flag->file_aws_local == '1'){
                               
                                        $location = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$sample_image;
                                    
                                }
                                else{
                                   
                                        $location = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$sample_image;
                                    
                                }
                                $path_info = pathinfo($location);
                                $file_name = $path_info['filename'];
                                $extension = $path_info['extension'];
                                    
                                if($get_file_aws_local_flag->file_aws_local == '1'){     
                            
                                        $uvImagePath = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$file_name.'_uv.'.$extension;
                                    
                                }
                                else{
                                   
                                        $uvImagePath = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$file_name.'_uv.'.$extension;
                                    
                                }
                                if($FID['visible'][$extra_fields] == 0){

                                    $pdfBig->image($uvImagePath,$x,$y,$width / 3.34,$height  / 3.34,"",'','L',true,3600);
                                }
                                
                             


                            }else{
                              
                            	
                                if($FID['grey_scale'][$extra_fields] == 1){

                                  /*if($subdomain[0]=="test"){
                                     print_r($FID);
                                     print_r($sample_image);   
                                    exit;
                                    }*/

                                    if($FID['is_uv_image'][$extra_fields] != 1){
                                        if($FID['visible_varification'][$extra_fields] == 0){
                                            if($get_file_aws_local_flag->file_aws_local == '1'){      
                                                
                                                     $pdf->image(\Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$FID['sample_image'][$extra_fields], $x, $y,$width / 3.34,$height / 3.34, "", '', 'L', true, 3600);
                                                
                                            }
                                            else{
                                               
                                                    $pdf->image(public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$FID['sample_image'][$extra_fields], $x, $y,$width / 3.34,$height / 3.34, "", '', 'L', true, 3600);
                                                
                                            }
                                        }
                                    }
                                    if($get_file_aws_local_flag->file_aws_local == '1'){ 
                                        
                                            $upload_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$FID['sample_image'][$extra_fields];
                                        
                                    }
                                    else{
                                       
                                           
                                           $upload_image = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$FID['sample_image'][$extra_fields];
                                    }
                                    
                                    //Updated By Mandar
                                    
                                    if($FID['is_transparent_image'][$extra_fields] == 1){
                                        $upload_image_org = $upload_image;
                                        $pathInfo = pathinfo($upload_image);
                                        $upload_image = $pathInfo['dirname'].'/'.$pathInfo['filename'].'_'.$excel_row.'.'.$pathInfo['extension'];
                                        \File::copy($upload_image_org,$upload_image);
                                    
                                    
                                        if($FID['visible'][$extra_fields] == 0){
                                           
                                            $testArr=array($excel_row,$x,$y,$FID['sample_image'][$extra_fields]);
                                            $pdfBig->image($upload_image, $x, $y,$width / 3.34,$height / 3.34, "", '', 'L', true, 3600);
                                        }
                                    
                                    }else{
                                        
                                        
                                        if($FID['visible'][$extra_fields] == 0){
                                           
                                            if($ext == "PNG"){

                                                $pdfBig->image('@'.file_get_contents($upload_image), $x, $y,$width / 3.34,$height / 3.34, $ext, '', 'L', true, 3600);
                                            }else{
                                                
                                                $pdfBig->image($upload_image, $x, $y,$width / 3.34,$height / 3.34, $ext, '', 'L', true, 3600);
                                            }
                                        }
                                    }

                                }else{
                                    if($get_file_aws_local_flag->file_aws_local == '1'){
                                
                                            $simple_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$sample_image;
                                        $grey_image = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/grey_'.$sample_image;
                                       
                                    }
                                    else{
                                 
                                            $simple_image = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$sample_image;
                                            $grey_image = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/grey_'.$sample_image;
                                    
                                    }


                                    

                                    /*$image = new Imagick($simple_image);
                                    $image->setImageColorspace(imagick::COLORSPACE_GRAY);
                                    $image->stripImage();
                                    $image->writeImage($grey_image);*/
                                    
                                        $path_info = pathinfo($simple_image);
                                    
                                    $file_name = $path_info['filename'];
                                    $ext = $path_info['extension'];
                                    
                                    // $uv_location = public_path()."/backend/canvas/dummy_images/".$FID['template_name']."/".$file_name.'_uv.'.$ext;

                                    if($ext == 'png'){

                                        $im = imagecreatefrompng($simple_image);

                                        if($im && imagefilter($im, IMG_FILTER_GRAYSCALE) )//&& imagefilter($im, IMG_FILTER_CONTRAST, -255)
                                        {
                                          
                                            imagepng($im, $grey_image);
                                            imagedestroy($im);
                                        }
                                        
                                    }else if($ext == 'jpeg' || $ext == 'jpg'){

                                        $im = imagecreatefromjpeg($simple_image);

                                        if($im && imagefilter($im, IMG_FILTER_GRAYSCALE) )//&& imagefilter($im, IMG_FILTER_CONTRAST, -255)
                                        {
                                          
                                            imagejpeg($im, $grey_image);
                                            imagedestroy($im);
                                        }
                                        
                                    }
                                    if($FID['is_uv_image'][$extra_fields] != 1){
                                        if($FID['visible_varification'][$extra_fields] == 0){

                                            $pdf->image($grey_image,$x,$y,$width / 3.34,$height  / 3.34,"",'','L',true,3600);
                                        }
                                    }
                                    if($FID['visible'][$extra_fields] == 0){
                                    
                                        $pdfBig->image($grey_image,$x,$y,$width / 3.34,$height  / 3.34,"",'','L',true,3600);
                                    }
                                }
                            }
                            
                      
                        break;
                    case 'UV Repeat line':
                        /* this is the combine of invisible and Security line in this task we can create uv repeat line
                            this line use to repeat text but in uv ink so repeate text and then rotate angle wise.              
                        */
                        if($FID['visible'][$extra_fields] == 0){
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
                            if(!$isPreview) {


                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                $pdfBig->StartTransform();
                                $pdfBig->SetXY($x, $y);
                                $pdfBig->Rotate($angle);
                                $pdfBig->Cell($width, $height, $security_line, 0, false, $text_align);
                                $pdfBig->StopTransform();
                                $pdfBig->SetOverprint(false, false, 0);
                            }
                        }
                        break;
                    case 'Ghost Image':

                        if($FID['visible'][$extra_fields] == 0){

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
                            
                            if($FID['visible_varification'][$extra_fields] == 0){
                            
                                $pdf->Image("$tmpDir/" . $name."".$font_size.".png", $x, $y, $w, $imageHeight, "PNG", '', 'L', true, 3600);
                            }
                            
                            if($FID['visible'][$extra_fields] == 0){
                            
                                $pdfBig->Image("$tmpDir/" . $name."".$font_size.".png", $x, $y, $w, $imageHeight, "PNG", '', 'L', true, 3600);
                            }
                            if(file_exists("$tmpDir/" . $name."".$font_size.".png")){
                                unlink("$tmpDir/" . $name."".$font_size.".png");
                            }
                        }
                        break;
                    case 'Invisible':

                        if($FID['visible'][$extra_fields] == 0){

                            $pdfBig->SetOverprint(true, true, 0);
                            if(!$isPreview) {
                                
                                if ($print_color == 'CMYK') {
                                
                                    if($font_color_extra == '000000'){

                                        $pdfBig->SetTextColor(0, 0, 0, $uv_percentage, false, '');
                                    
                                    }else{
                                        $pdfBig->SetTextColor(0, 0, $uv_percentage,0, false, '');
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
                        
                        if($FID['visible'][$extra_fields] == 0){

                            if(!$isPreview){

                                if ($print_color == 'CMYK') {

                                    $imageSource = public_path().'/backend/canvas/ghost_images/S-017-18 ANTICOPY.png';
                                    if($FID['visible_varification'][$extra_fields] == 0){
                                        
                                        $pdf->Image($imageSource, $x, $y, 50, 10, "PNG", '', 'L', true, 3600);
                                    }
                                    if($FID['visible'][$extra_fields] == 0){
                                    
                                        $pdfBig->Image($imageSource, $x, $y, 50, 10, "PNG", '', 'L', true, 3600);
                                    }
                                }else{

                                    if($subdomain[0]=='minoshacloud'){
                                    $imageSource = public_path().'/backend/canvas/ghost_images/VOID FINAL FOR BROTHER_NEW.SVG';
                                    }else{
                                    $imageSource = public_path().'/backend/canvas/ghost_images/VOID FINAL FOR BROTHER.SVG';
                                    }
                                    if($FID['visible'][$extra_fields] == 0){

                                        $pdfBig->ImageSVG($file=$imageSource, $x, $y, 50, 10, $link='', $align='', $palign='', $border=0, $fitonpage=false);
                                    }
                                    
                                    if($FID['visible_varification'][$extra_fields] == 0){
                                    
                                        $pdf->ImageSVG($file=$imageSource, $x, $y, 50, 10, $link='', $align='', $palign='', $border=0, $fitonpage=false);
                                    }
                                    
                                }
                                
                            }
                        }    
                        break;
                    case '1D Barcode':
                        if($FID['visible'][$extra_fields] == 0){

                           
                            if($FID['visible'][$extra_fields] == 0){
                                $pdfBig->write1DBarcode($str, 'C128', $x, $y, $width, $height, 0.4, $style1D, 'N');
                            }
                            if($FID['visible_varification'][$extra_fields] == 0){
                                $pdf->write1DBarcode($str, 'C128', $x, $y, $width, $height, 0.4, $style1D, 'N');
                            }
                            
                        }
                        break;
                    
                    case 'UV Repeat Fullpage':

                        if($FID['visible'][$extra_fields] == 0){

                            $security_line = '';
                            for($d = 0; $d < 50; $d++)
                                $security_line .= $str . ' ';

                            //$pdfWidth = 210;
                            //$pdfHeight = 297;
                            if ($Orientation=="P"){
                                $pdfWidth = 210;
                                $pdfHeight = 297;
                                $j_increased=5;
                            }else{
                                $pdfWidth = $bg_template_width_generate;
                                $pdfHeight = $bg_template_height_generate;
                                $j_increased=15;
                            }                            
                            $pdfBig->SetOverprint(true, true, 0);
                            if(!$isPreview){
                                
                                if ($print_color == 'CMYK') {
                                
                                    if($font_color_extra == '000000'){

                                        $pdfBig->SetTextColor(0, 0, 0, $uv_percentage, false, '');
                                    
                                    }else{
                                        $pdfBig->SetTextColor(0, 0, $uv_percentage,0, false, '');
                                    }
                                
                                }else{
                                    $rgb_opacity=$uv_percentage/100;            
                                    if($font_color_extra == '000000'){                                        
                                        $pdf->SetAlpha($rgb_opacity); 
                                        $pdf->SetTextColor(0, 0, 0);                                        
                                        $pdfBig->SetAlpha($rgb_opacity);                                     
                                        $pdfBig->SetTextColor(0, 0, 0);                                    
                                    }else{
                                        $pdf->SetTextColor(255,255, 0);
                                        $pdfBig->SetTextColor(255,255, 0);
                                    }
                                }
                                $pdf->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                $pdfBig->SetFont($fonts_array[$extra_fields], $bold, $font_size, '', false);
                                for ($i=0; $i <= $pdfHeight; $i+=$line_gap) {

                                    if($FID['visible'][$extra_fields] == 0){
                                    
                                        $pdfBig->SetXY(0,$i);
                                        $pdfBig->StartTransform();  
                                        $pdfBig->Rotate($angle);
                                        $pdfBig->Cell(0, 0, $security_line, 0, false, $text_align);
                                        $pdfBig->StopTransform();
                                    }
                                    if($FID['visible_varification'][$extra_fields] == 0){
                                    
                                        $pdf->SetXY(0,$i);
                                        $pdf->StartTransform();  
                                        $pdf->Rotate($angle);
                                        $pdf->Cell(0, 0, $security_line, 0, false, $text_align);
                                        $pdf->StopTransform();
                                    }

                                    
                                }
                                for ($j=0; $j < $pdfWidth; $j+=$line_gap) {
                                    
                                    if($FID['visible'][$extra_fields] == 0){

                                        //$pdfBig->SetXY($j+5,$pdfHeight);
                                        $pdfBig->SetXY($j+$j_increased,$pdfHeight);
                                        $pdfBig->StartTransform();  
                                        $pdfBig->Rotate($angle);
                                        $pdfBig->Cell(0, 0, $security_line, 0, false, $text_align);
                                        $pdfBig->StopTransform();
                                    }
                                    if($FID['visible_varification'][$extra_fields] == 0){
                                        
                                        //$pdf->SetXY($j+5,$pdfHeight);
                                        $pdf->SetXY($j+$j_increased,$pdfHeight);
                                        $pdf->StartTransform();  
                                        $pdf->Rotate($angle);
                                        $pdf->Cell(0, 0, $security_line, 0, false, $text_align);
                                        $pdf->StopTransform();
                                    }
                                }
                                $pdfBig->SetOverprint(false, false, 0);
                                $pdf->SetAlpha(1);
                                $pdfBig->SetAlpha(1);
                            }
                        }
                        break;
                    case '2D Barcode':
                            // set style for barcode
                        if($FID['visible'][$extra_fields] == 0){
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
                         
                                $code = (string)$str;
                                if($FID['visible'][$extra_fields] == 0){
                                
                                    $pdfBig->write2DBarcode($code, 'DATAMATRIX', $x, $y, $width / 3, $height / 3, $style, 'N');
                                }
                                if($FID['visible_varification'][$extra_fields] == 0){
                                
                                    $pdf->write2DBarcode($code, 'DATAMATRIX', $x, $y, $width / 3, $height / 3, $style, 'N');
                                }
                            
                        }
                        break;
                    default:
                        break;
                }
                $log_serial_no++;

                /**Block Chain Meta Data Fields**/
                if($subdomain[0]=="demo"&&$FID['is_meta_data'][$extra_fields] == 1){
                        
                        $fullString=$FID['meta_data_value'][$extra_fields];
                        if (preg_match_all('#\{\{(.*?)\}\}#', $fullString, $matches)) {
                        
                        $value_fields=$matches[1]; // Get Group 1 values
                       
                        foreach ($value_fields as $field) {

                            $columnIndex = array_search($field, $FID['name']);
                            $columnName=$FID['mapped_name'][$columnIndex];
                           // echo "<br>";
                            $columnIndexRow = array_search($columnName, $firstRow[0]);
                            
                           /* print_r($columnIndexRow);

                            echo "<br>";
                            print_r($rowData);
                            echo "<br>";*/
                            if ($FID['is_mapped'][$columnIndex] == 'excel') {
                                if(isset($rowData[0][$columnIndexRow])){
                                    
                                    $strValueMetaData = $rowData[0][$columnIndexRow];
                                }
                                else{
                                    $strValueMetaData = '';
                                }
                            }else{
                                if(isset($FID['sample_text'][$columnIndex])&&!empty($FID['sample_text'][$columnIndex])){
                                    
                                    $strValueMetaData = $FID['sample_text'][$columnIndex];
                                }
                                else{
                                    $strValueMetaData = '';
                                }
                            }
                             
                              $fullString=str_replace("{{".$field."}}",$strValueMetaData,$fullString);                       
                        }
                         
                        
                    }
                    $metaDataArr[]=array("label"=>$FID['meta_data_label'][$extra_fields],"value"=>$fullString);
                }

                /**** End Block Chain ***************/
            }


              //API changes
            if((!$isPreview&&(!isset($pdf_data['generation_from'])||(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']!='API')))||(!$isPreview&&(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'&&$pdf_data['generation_type']=='Live'))) {

                $withoutExt = preg_replace('/\\.[^.\\s]{3,4}$/', '', $excelfile);
                $template_name  = $FID['template_name'];
                //if($subdomain[0]=="demo"){
                    $certName = preg_replace('/[^a-zA-Z0-9]/', '_', $serial_no).".pdf";
                /*}else{
                    $certName = str_replace("/", "_", $serial_no) .".pdf";    
                }*/
                
                $myPath = public_path().'/backend/temp_pdf_file';
                $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');
                
                //Adding certificate to database // blockchain
                $student_table_id = $this->addCertificate($serial_no, $certName, $dt,$FID['template_id'],$admin_id,$get_file_aws_local_flag,$storagePath,$rowData,$FID,$metaDataArr);

                $last_add = StudentTable::select('id')->orderBy('id','desc')->skip(0)->take(1)->first();
                $active_documents = StudentTable::select('id')->where('site_id',$auth_site_id)->where('status','1')->count();
                $inactive_documents = StudentTable::select('id')->where('site_id',$auth_site_id)->where('status','0')->count();
                $last_genrate_date = StudentTable::select('created_at')->where('site_id',$auth_site_id)->where('id',$last_add['id'])->first();
                $total_documents = StudentTable::select('id')->where('site_id',$auth_site_id)->count();
                $avg_per = $active_documents / $total_documents * 100;
                
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

                
                SiteDocuments::where('site_id',$auth_site_id)->update(['active_documents'=>$active_documents]);
                SiteDocuments::where('site_id',$auth_site_id)->update(['inactive_documents'=>$inactive_documents]);
                SiteDocuments::where('site_id',$auth_site_id)->update(['last_genration_date'=>$last_genrate_date['created_at']]);
                SiteDocuments::where('site_id',$auth_site_id)->update(['avg_per'=>$avg_per]);

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
                $username = $admin_id['username'];
                date_default_timezone_set('Asia/Kolkata');

                $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
                $date = date('Y-m-d H:i:s').PHP_EOL;
    
                $print_datetime = date("Y-m-d H:i:s");
                $print_count = $this->getPrintCount($serial_no);
                $printer_name = /*'HP 1020';*/$printer_name;
                //Adding printer details
                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_name,$admin_id,$student_table_id);
            }
            $new_excel_row = (int)$excel_row + 1;
            

            if($systemConfig['sandboxing'] == 1){
                if($get_file_aws_local_flag->file_aws_local == '1'){

                    $excelDelPath = '/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$FID['template_id'].'/'.$excelfile;
                }
                else{
                    $excelDelPath = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$FID['template_id'].'/'.$excelfile;
                }
            }else{
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    $excelDelPath = '/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$excelfile;
                }
                else{
                    $excelDelPath = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$FID['template_id'].'/'.$excelfile;
                }
            }
            
            if($get_file_aws_local_flag->file_aws_local == '1'){
                if(\Storage::disk('s3')->exists($excelDelPath)) {
                    \Storage::disk('s3')->delete($excelDelPath);
                }
            }
            else{
                if(file_exists($excelDelPath)&&($inputSource =="Excel"||$inputSource =="")){
                    unlink($excelDelPath);
                }
            }
           // if($subdomain[0]=="test"){
                //delete temp dir 26-04-2022 
                CoreHelper::rrmdir($tmpDir);
            //}
            //API changes
            if(($new_excel_row-1)<=$highestRow&&(!isset($pdf_data['generation_from'])||(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']!='API'))){
                $progressbar_array = ['testArr'=>$testArr,'excel_row'=>$new_excel_row,'is_progress'=>'yes','highestRow'=>$highestRow,'msg'=>'','success'=>true];
                return $progressbar_array;
            }else if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
                $updated=date('Y-m-d H:i:s');
                ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['generated_documents'=>$excel_row-1,"updated_at"=>$updated]);
            }

        }

            if(!$isPreview) {

                
                $msg = '';
                if(is_dir($tmpDir)){
                    rmdir($tmpDir);
                }
                if($get_file_aws_local_flag->file_aws_local == '1') {
                    $s3=\Storage::disk('s3');
                    $tcpdf_directory = $subdomain[0].'/backend/tcpdf/';
                    $examples_directory = $subdomain[0].'/backend/tcpdf/examples/';
                    $sandbox_directory = $subdomain[0].'/backend/tcpdf/examples/sandbox/';

                    if(!$s3->exists($tcpdf_directory))
                    {
                     $s3->makeDirectory($tcpdf_directory, 0777);  
                    }if(!$s3->exists($examples_directory))
                    {
                     $s3->makeDirectory($examples_directory, 0777);  
                    }if(!$s3->exists($sandbox_directory))
                    {
                     $s3->makeDirectory($sandbox_directory, 0777);  
                    }
                }
                else{
                    $tcpdf_directory = $storagePath.'/'.$subdomain[0].'/backend/tcpdf/';
                    $examples_directory = $storagePath.'/'.$subdomain[0].'/backend/tcpdf/examples/';
                    $sandbox_directory = $storagePath.'/'.$subdomain[0].'/backend/tcpdf/examples/sandbox/';


                    if(!is_dir($tcpdf_directory)){
    
                        mkdir($tcpdf_directory, 0777);
                    }

                    if(!is_dir($examples_directory)){
    
                        mkdir($examples_directory, 0777);
                    }

                    if(!is_dir($sandbox_directory)){
    
                        mkdir($sandbox_directory, 0777);
                    }

                }

                if($FID['print_type'] == 'pdf'){
                    

                    $highestRecord = $highestRow - 1;
                    $unique_name=$template_name.'_'.$highestRecord.'_'.date("Ymdhms");
                    $file_name = $unique_name.'.pdf';
                    
                    //Updated by Mandar for api based pdf generation
                    if(Auth::guard('admin')->user()){
                        $auth_site_id=Auth::guard('admin')->user()->site_id;
                    }else{
                        $auth_site_id=$this->pdf_data['admin_id']['site_id'];
                    }  
                    $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        

                    $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
                    
                    $pdfBig->output($filename,'F');

                    if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                        if($get_file_aws_local_flag->file_aws_local == '1') {
                            $aws_qr = \Storage::disk('s3')->put('/'.$subdomain[0].'/backend/tcpdf/examples/sandbox/'.$file_name, file_get_contents($filename), 'public');
                            $filename1 = \Storage::disk('s3')->url($file_name);
                        }
                        else{
                            \File::copy($filename,$storagePath.'/'.$subdomain[0].'/backend/tcpdf/examples/sandbox/'.$file_name);
                        }

                        @unlink($filename);
                        $pdf_name = str_replace(' ', '+', $file_name);
                        $unique_name = str_replace(' ', '+', $unique_name);
    
                        if($get_file_aws_local_flag->file_aws_local == '1'){
                            $msg = "<b>Click <a href='".\Config::get('constant.amazone_path').$subdomain[0]."/backend/tcpdf/examples/sandbox/".$pdf_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
                        }
                        else{

                             if($subdomain[0]=='test'){
                                $pdf_url = 'http://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$unique_name.'/2/2'; 
                                $msg = "<b>Click <a href='".$pdf_url."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
                            }else{
                            $path = 'http://'.$subdomain[0].'.seqrdoc.com/';
                            $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/sandbox/".$pdf_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
                            }
                        }
                    
                    }else{
                        if($get_file_aws_local_flag->file_aws_local == '1'){
                            $aws_qr = \Storage::disk('s3')->put('/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name, file_get_contents($filename), 'public');
                            $filename1 = \Storage::disk('s3')->url($file_name);
                        }
                        else{
                            $aws_qr = \File::copy($filename,$storagePath.'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
                        }

                        @unlink($filename);
                        
                        $pdf_name = $file_name;

                        if($get_file_aws_local_flag->file_aws_local == '1'){
                            $pdf_url=\Config::get('constant.amazone_path').$subdomain[0]."/backend/tcpdf/examples/".$pdf_name;//API changes
                            $msg = "<b>Click <a href='".\Config::get('constant.amazone_path').$subdomain[0]."/backend/tcpdf/examples/".$pdf_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
                        }
                        else{

                            if($subdomain[0]=='test'){
                                $pdf_url = 'http://'.$subdomain[0].'.seqrdoc.com/api/pdf/'.$unique_name.'/2/1'; 
                                $msg = "<b>Click <a href='".$pdf_url."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
                            }else{


                           $path = 'http://'.$subdomain[0].'.seqrdoc.com/'; 
                           $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/".$pdf_name; //API changes
                            $path = 'http://'.$subdomain[0].'.seqrdoc.com/';
                            $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$pdf_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
                             }
                        }
                        //API changes
                        if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
                         $updated=date('Y-m-d H:i:s');
                        
                        ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['status'=>'Completed','printable_pdf_link'=>$pdf_url,"updated_at"=>$updated]);
                        //Sending data to call back url
                             $reaquestParameters = array(
                                                            'request_id'=>$pdf_data['request_id'],
                                                            'printable_pdf_link' => $pdf_url,
                                                        );
                            $url = $pdf_data['call_back_url'];
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            //curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reaquestParameters));
                            $result = curl_exec($ch);
                             curl_close($ch);

                             if(is_string($result) && is_array(json_decode($result, true)) && (json_last_error() == JSON_ERROR_NONE)){
                                
                                $resp=$result;
                             }else{
                                $resp=json_encode($result);
                             }

                            $updated=date('Y-m-d H:i:s');
                            ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['call_back_response'=>$resp,"updated_at"=>$updated]);

                           
                        }
                    }
                

                }else{
                    $inputFile = 'pdfcertificate.pdf';
                    $outputFile = 'pdfcertificate.Seqr';

                    $filename = public_path().'/backend/tcpdf/examples/pdfcertificate.pdf';

                    $pdfBig->output($filename,'F');

                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $aws_qr = \Storage::disk('s3')->put('/'.$subdomain[0].'/backend/tcpdf/examples/pdfcertificate.pdf', file_get_contents($filename), 'public');
                        $filename1 = \Storage::disk('s3')->url('pdfcertificate.pdf');
                    }
                    else{
                        $aws_qr = \File::copy($filename,$storagePath.'/'.$subdomain[0].'/backend/tcpdf/examples/pdfcertificate.pdf');
                    }
                    
                    @unlink($filename);


                    $result = $pdfBig->encryptFile($inputFile,$outputFile);
                    $file_name = $inputFile;
                    if($get_file_aws_local_flag->file_aws_local == '1'){
                        $msg = "<b>Click <a href='".\Config::get('constant.amazone_path').$subdomain[0]."/backend/tcpdf/examples/pdfcertificate.pdf' class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
                    }
                    else{
                        $path = 'http://'.$subdomain[0].'.seqrdoc.com/';
                        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/pdfcertificate.pdf' class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
                    }

                }
                // can update here no of records successfully uploaded
                $no_of_records = $highestRow - 1;
                $datetime = date("Y-m-d H:i:s");
                $user = $admin_id['username'];
                //Updated by Mandar for api based pdf generation
                    if(Auth::guard('admin')->user()){
                        $auth_site_id=Auth::guard('admin')->user()->site_id;
                    }else{
                        $auth_site_id=$this->pdf_data['admin_id']['site_id'];
                    }  
                
                $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

                if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                    // with sandbox
                    
                    $result = SbExceUploadHistory::create(['template_name'=>$FID['template_id'],'excel_sheet_name'=>$excelfile,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id,'pdf_file'=>$pdf_name]);
                }else{
                    // without sandbox
                    $result = ExcelUploadHistory::create(['template_name'=>$FID['template_id'],'excel_sheet_name'=>$excelfile,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id,'pdf_file'=>$pdf_name]);
                }    
                
                //mail pdf
                $mail_view = 'mail.pdf';
                
                $user_email = 'aakashi.thinktanker@gmail.com';
                $mail_subject = 'PDF Generation';
                $user_data = ['file_name'=>$file_name,'path'=>$filename];
                \File::deleteDirectory(public_path().'/backend/canvas/dummy_images/'.$FID['template_name']);
                
            }
            $progressbar_array_last = ['testArr'=>$testArr,'excel_row'=>$excel_row,'is_progress'=>'no','highestRow'=>$highestRow,'msg'=>$msg];
            return $progressbar_array_last;
            
    }

    public function isEmptyRow($row){
        foreach($row as $cell){
            if (null !== $cell) return false;
        }
        return true;
    }

    public function nextPrintSerial()
    {
        $current_year = 'PN/' . $this->getFinancialyear() . '/';
        $current_year_sandbox = 'SPN/' . $this->getFinancialyear() . '/';
        // find max
        $maxNum = 0;
        
         //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['admin_id']['site_id'];
        }

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){


            $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 11), UNSIGNED)), 0) AS next_num "
                . "FROM sb_printing_details WHERE SUBSTR(print_serial_no, 1, 10) = '$current_year_sandbox'");
        }else{
            $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 10), UNSIGNED)), 0) AS next_num "
                . "FROM printing_details WHERE SUBSTR(print_serial_no, 1, 9) = '$current_year'");
            

        }
        
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

    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,$get_file_aws_local_flag,$storagePath,$excelRow=[],$FID,$metaDataArr)
    {

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
         //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['admin_id']['site_id'];
        }

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        
        

        if($subdomain[0]=='test'){

            /* S3 Bucket check for already generated pdf and move to inactive folder */
            $file_existes = 'public/'.$subdomain[0].'/backend/pdf_file/'.$certName;
            $responseS3 =  \Storage::disk('s3')->has($file_existes);
            if($responseS3){
                $activePdf = 'public/'.$subdomain[0].'/backend/pdf_file/'.$certName;
                $student = StudentTable::where('status',1)->where('serial_no',$serial_no)->value('id');
                $inactivePdf = 'public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF/'.$student.'_'.$certName;
                \Storage::disk('s3')->copy($activePdf,$inactivePdf);
            }

            //Compress pdf file
           // $pyscript = \Config::get('constant.pathCompressScript'); 
            $source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;//$file1
            $output=\Config::get('constant.directoryPathBackward')."\\backend\\pdf_file\\".$certName;
            //$cmd = "$pyscript $source $output 2>&1";
            //exec($cmd, $output, $return);
            CoreHelper::compressPdfFile($source,$output);
            
        }else{
            /* Server Storage check for already generated pdf and move to inactive folder */
            $file_existes = $storagePath.'/'.$subdomain[0].'/backend/pdf_file/'.$certName;
            if(file_exists($file_existes)){    
            
                if(!is_dir($storagePath.'/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF')){
                    mkdir($storagePath.'/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF');
                }
                $student = StudentTable::where('status',1)->where('serial_no',$serial_no)->value('id');
                $inactivePdf = $storagePath.'/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF/'.$student.'_'.$certName;
                
                if($subdomain[0]=='test'){
                    $aws_qr = \Storage::disk('s3')->put('public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF/'.$student.'_'.$certName, file_get_contents($file_existes), 'public');
                    $filename1 = \Storage::disk('s3')->url($certName);

                             
                }else{
                    copy($file_existes, $inactivePdf);
                }
            }

            //Compress pdf file  //Enable compression code for all instances at 02-12-2022 12:43
            /*if($subdomain[0]=='utk'){
                 copy($file1, $file2);
            }else{*/
            //if($subdomain[0]=='tpsdi' || $subdomain[0]=='galgotias' || $subdomain[0]=="ghrietn"){
            //    $source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;//$file1
            //    $output=\Config::get('constant.directoryPathBackward')."\\backend\\pdf_file\\".$certName;
            //    CoreHelper::compressPdfFile($source,$output);
            //}else{
                copy($file1, $file2);    
            //}
           // }
            
        }
        
        
        if($get_file_aws_local_flag->file_aws_local == '1') {
            $s3=\Storage::disk('s3');
            $pdf_file_directory =  "public/".$subdomain[0].'/backend/pdf_file/';
            $sandbox_directory =  "public/".$subdomain[0].'/backend/pdf_file/sandbox/';

            if(!$s3->exists($pdf_file_directory))
            {
             $s3->makeDirectory($pdf_file_directory, 0777);  
            }if(!$s3->exists($sandbox_directory))
            {
             $s3->makeDirectory($sandbox_directory, 0777);  
            }
        }
        else{
            $pdf_file_directory = $storagePath.'/'.$subdomain[0].'/backend/pdf_file/';
            $sandbox_directory = $storagePath.'/'.$subdomain[0].'/backend/pdf_file/sandbox/';


            if(!is_dir($pdf_file_directory)){

                mkdir($pdf_file_directory, 0777);
            }

            if(!is_dir($sandbox_directory)){

                mkdir($sandbox_directory, 0777);
            }

        }


        if($get_file_aws_local_flag->file_aws_local == '1'){
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            
                $aws_qr = \Storage::disk('s3')->put('/public/'.$subdomain[0].'/backend/pdf_file/sandbox/'.$certName, file_get_contents($file2), 'public');
                $filename1 = \Storage::disk('s3')->url($certName);
            }else{

                $aws_qr = \Storage::disk('s3')->put('/public/'.$subdomain[0].'/backend/pdf_file/'.$certName, file_get_contents($file2), 'public');
                $filename1 = \Storage::disk('s3')->url($certName);
                
            }
        }
        else{
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                $aws_qr = \File::copy($file2,$storagePath.'/'.$subdomain[0].'/backend/pdf_file/sandbox/'.$certName);
            }else{
                
                /***** Uploaded verification pdf to s3 bucket ******/
                if($subdomain[0]=='test'){
                    $aws_qr = \Storage::disk('s3')->put('public/'.$subdomain[0].'/backend/pdf_file/'.$certName, file_get_contents($file2), 'public');
                    $filename1 = \Storage::disk('s3')->url($certName);
                         
                }else{
                  $aws_qr = \File::copy($file2,$storagePath.'/'.$subdomain[0].'/backend/pdf_file/'.$certName);  
                }
            }
        }

        // Rohit Changes 18/05/2023
        
        $livePdf = 'public/'.$subdomain[0].'/backend/pdf_file/'.$certName;

        //$file2
        $liveOutput=$file2;
        //$liveOutput=\Config::get('constant.directoryPathBackward')."\\backend\\pdf_file\\".$certName;

        // $newFileNamePdf = 'public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF/'.$student.'_'.$certName;

        //$outputFile = 'public/'.$subdomain[0]."\\backend\\pdf_file\\".$certName;
        //$movedfolder = 'public/'.$subdomain[0]."/backend/pdf_file";
        
        // awsS3Instances
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        
        if(in_array($subdomain[0], $awsS3Instances)) {
            $s3 = \Storage::disk('s3');
            // File Exist in folder
            if($s3->exists($livePdf)) {
                $student = StudentTable::where('status',1)->where('serial_no',$serial_no)->value('id');
                $awsInactivePDFFolder = 'public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF';
                // check folder 
                if($s3->exists($awsInactivePDFFolder)) {
                    $newFileNamePdf = 'public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF/'.$student.'_'.$certName;

                    
                    $s3->move($livePdf, $newFileNamePdf);
                } else {
                    // folder create
                    $s3->makeDirectory($awsInactivePDFFolder, 0777);
                    $newFileNamePdf = 'public/'.$subdomain[0].'/backend/pdf_file/Inactive_PDF/'.$student.'_'.$certName;
                    $s3->move($livePdf, $newFileNamePdf);
                }
            }
            if(!$s3->exists($livePdf)) {
                $s3->put($livePdf, file_get_contents($liveOutput));
            }
            
            @unlink($liveOutput);
        }
       
        
        // rohit changes 18/05/2023

        @unlink($file2);

        @unlink($file1);


        if($subdomain[0]=="demo"){
    
            //Blockchain Integration
            $pdf_file=$storagePath.'/'.$subdomain[0].'/backend/pdf_file/'.$certName;

        }
        
        

        


        $sts = '1';
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
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
        
            $resultu = SbStudentTable::where('serial_no','T-'.$serial_no)->update(['status'=>'0']);
            // Insert the new record
             //Updated by Mandar for api based pdf generation
            if(Auth::guard('admin')->user()){
                $auth_site_id=Auth::guard('admin')->user()->site_id;
            }else{
                $auth_site_id=$this->pdf_data['admin_id']['site_id'];
            }  
            $result = SbStudentTable::create(['serial_no'=>'T-'.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);

        }else{

            $resultu = StudentTable::where('serial_no',''.$serial_no)->update(['status'=>'0']);
            // Insert the new record
             //Updated by Mandar for api based pdf generation
            if(Auth::guard('admin')->user()){
                $auth_site_id=Auth::guard('admin')->user()->site_id;
            }else{
                $auth_site_id=$this->pdf_data['admin_id']['site_id'];
            }  
            $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);



        }


        if($subdomain[0]=="demo"){
            
            //Blockchain Integration
            if($template_id==697||$template_id==698){
             
            $excelRow=$excelRow[0];
            if(!empty($excelRow)){
              // print_r($excelRow);

               /*if($excelRow[0]=="SCUBE002"){
                print_r($excelRow);
                }*/
                /*$fields = FieldMaster::select('mapped_name','field_position','security_type')->where('template_id',$template_id)->get();
                if( $fields){
                //print_r($fields);
                    $i=1;
                    foreach($fields as $readData){
                        if($readData->security_type!="QR Code"&&$readData->security_type!="ID Barcode"){
                           print_r($readData->field_position,$readData->mapped_name);
                           echo "<br>";
                            if($readData->mapped_name=="Document Type"){
                                $mintData['documentType']=$excelRow[$readData->field_position];
                            }elseif ($readData->mapped_name=="Description") {
                                
                                $mintData['description']=$excelRow[$readData->field_position];
                            }else if(!empty($readData->mapped_name)){
                                $mintData['metadata'.$i]=["label"=> $readData->mapped_name, "value"=> $excelRow[$readData->field_position]];
                                $i++;
                            }
                        }
                    }
                    
                }*/
            $mintData['documentType']=$excelRow[1];
            $mintData['description']=$excelRow[2];
            $mintData['metadata1']=["label"=> "Student Name", "value"=> $excelRow[3]];
            $mintData['metadata2']=["label"=> "Department", "value"=> $excelRow[4]];
            $mintData['metadata3']=["label"=> "Programme", "value"=> $excelRow[5]];
            $mintData['metadata4']=["label"=> "Course Code", "value"=> $excelRow[6]];
            $mintData['metadata5']=["label"=> "CGPA", "value"=> $excelRow[8]];

            $mintData['uniqueHash']=$key;
            $mintData['pdf_file']=$pdf_file;
            $mintData['template_id']=$template_id;
            
            //$mintData['description']="Scube University, Maharashtra";
            //$mintData['uniqueHash']=$serial_no;
           /* $mintData['metadata1']=["label"=> "Name", "value"=> "Mandar Gawade"];
            $mintData['metadata2']=["label"=> "Course", "value"=> "SeQR Doc" ];
            $mintData['metadata3']=["label"=> "Specialization", "value"=> "Block Chain"];
            $mintData['metadata4']=["label"=> "College", "value"=> "Scube" ];
            $mintData['metadata5']=["label"=> "CGPA", "value"=> "9.2" ];*/
         /* print_r($mintData);
           exit;*/
            $response=CoreHelper::mintPDF($mintData);
            if($response['status']==200){
                $resultu = StudentTable::where('serial_no',''.$serial_no)->where('key',''.$key)->where('status',1)->update(['bc_txn_hash'=>$response['txnHash']]);
            }
            
            }
            }else if($subdomain[0]=="demo"&&$FID['is_block_chain']==1){


                $mintData['documentType']=$FID['bc_document_type'];
                $mintData['description']=$FID['bc_document_description'];

                if(count($metaDataArr)>0){
                    for($z=1;$z<=5;$z++){
                        $mintData['metadata'.$z]=["label"=> $metaDataArr[$z-1]['label'], "value"=> $metaDataArr[$z-1]['value']];
                    }
                }
                /*$mintData['metadata1']=["label"=> "Student Name", "value"=> $excelRow[3]];
                $mintData['metadata2']=["label"=> "Department", "value"=> $excelRow[4]];
                $mintData['metadata3']=["label"=> "Programme", "value"=> $excelRow[5]];
                $mintData['metadata4']=["label"=> "Course Code", "value"=> $excelRow[6]];
                $mintData['metadata5']=["label"=> "CGPA", "value"=> $excelRow[8]];*/

                $mintData['uniqueHash']=$key;
                $mintData['pdf_file']=$pdf_file;
                $mintData['template_id']=$template_id;
                
                //$mintData['description']="Scube University, Maharashtra";
                //$mintData['uniqueHash']=$serial_no;
               /* $mintData['metadata1']=["label"=> "Name", "value"=> "Mandar Gawade"];
                $mintData['metadata2']=["label"=> "Course", "value"=> "SeQR Doc" ];
                $mintData['metadata3']=["label"=> "Specialization", "value"=> "Block Chain"];
                $mintData['metadata4']=["label"=> "College", "value"=> "Scube" ];
                $mintData['metadata5']=["label"=> "CGPA", "value"=> "9.2" ];*/
             /* print_r($mintData);
               exit;*/
                $response=CoreHelper::mintPDF($mintData);
                if($response['status']==200){
                    $resultu = StudentTable::where('serial_no',''.$serial_no)->where('key',''.$key)->where('status',1)->update(['bc_txn_hash'=>$response['txnHash']]);
                }
            }
        }

        return $result->id;
    }

    public function getPrintCount($serial_no)
    {
         //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['admin_id']['site_id'];
        }
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){

            $numCount = SbPrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        }else{

            $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        }
        return $numCount + 1;
    }

    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$student_table_id)
    {
        
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

         //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['admin_id']['site_id'];
        }

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){

            $sb_print_serial_no = 'S'.$print_serial_no;
            $sr_no = 'T-'.$sr_no;
            $result = SbPrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$sb_print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }else{

            $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1,'student_table_id'=>$student_table_id]);
        }
        // Insert the new record
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
            // dd($rect);
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
                "Z" => array(80884, 808),
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
                "Z" => array(23287, 880)
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
