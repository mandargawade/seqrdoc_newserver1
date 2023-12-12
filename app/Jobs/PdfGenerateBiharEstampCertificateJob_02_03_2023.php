<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\models\BackgroundTemplateMaster;
use Session,TCPDF,TCPDF_FONTS,Auth,DB;
use App\models\FontMaster;
use App\models\SystemConfig;
use QrCode;
use App\models\Config;
use App\models\StudentTableEstamp;
use App\models\SbStudentTable;
use App\models\PrintingDetail;
use App\models\SbPrintingDetail;
use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;
use App\models\CardSerialNumbers;
use App\Jobs\SendMailJob;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use Illuminate\Support\Facades\Redis;
use App\models\ThirdPartyRequests;
use App\Helpers\CoreHelper;
use Helper;
use App\Utility\GibberishAES;

class PdfGenerateBiharEstampCertificateJob
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

    function negate($image)
    {
        if(function_exists('imagefilter'))
        {
        return imagefilter($image, IMG_FILTER_NEGATE);
        }
        for($x = 0; $x < imagesx($image); ++$x)
        {
            for($y = 0; $y < imagesy($image); ++$y)
            {
            $index = imagecolorat($image, $x, $y);
            $rgb = imagecolorsforindex($index);
            $color = imagecolorallocate($image, 255 - $rgb['red'], 255 - $rgb['green'], 255 - $rgb['blue']);
            imagesetpixel($im, $x, $y, $color);
            }
        }
        return(true);
    }   
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $pdf_data = $this->pdf_data;   
        //print_r($pdf_data);     
        $studentDataOrg=$pdf_data['studentDataOrg'];
                
        $Photo_key=10;
        $subj_col = $pdf_data['subj_col'];
        $subj_start = $pdf_data['subj_start'];
        $subj_end = $pdf_data['subj_end'];       
        $template_id=$pdf_data['template_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
         $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];
        
        $first_sheet=$pdf_data['studentDataOrg']; // get first worksheet rows
        //$second_sheet=$pdf_data['subjectsMark']; // get second worksheet rows
        $total_unique_records=count($first_sheet);
        $last_row=$total_unique_records+1;
        //$course_count = array_count_values(array_column($second_sheet, '0'));
        //$max_course_count = (max($course_count)); 
        
        if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
            $admin_id=$pdf_data['admin_id'];

        }else{
            $admin_id = \Auth::guard('admin')->user()->toArray();  
        }
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        
        $printer_name = $systemConfig['printer_name'];

      
        $ghostImgArr = array();
        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
        $pdfBig->SetCreator(PDF_CREATOR);
        $pdfBig->SetAuthor('TCPDF');
        $pdfBig->SetTitle('CERTIFICATE');
        $pdfBig->SetSubject('');

        // remove default header/footer
        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);


        // add spot colors
        $pdfBig->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        $pdfBig->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo
        $pdfBig->AddSpotColor('Spot Light Yellow', 0, 0, 100, 0);
        //set fonts
        //$arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        //$arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.ttf', 'TrueTypeUnicode', '', 96);
        $arialB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial_B.ttf', 'TrueTypeUnicode', '', 96);
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.ttf', 'TrueTypeUnicode', '', 96);
        $courierNew = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\cour.ttf', 'TrueTypeUnicode', '', 96);
        //$card_serial_no="";
        $preview_serial_no=1;
        $log_serial_no = 1;
        $cardDetails=$this->getNextCardNo('BiharEstampCert');
      
        $card_serial_no=$cardDetails->next_serial_no;
        $generated_documents=0;  
        // foreach ($studentDataOrg as $studentData) {
         
            if($card_serial_no>999999 && $previewPdf!=1){
                //echo "<h5>Your grade card series ended...!</h5>";
                //exit;
            }
            //For Custom Loader
            $startTimeLoader =  date('Y-m-d H:i:s'); 
            $high_res_bg="biharestamp_blank_high.jpg"; 
            $low_res_bg="biharestamp_blank.jpg";
            $pdfBig->AddPage();
            $pdfBig->setCellPaddings( $left = '', $top = '', $right = '', $bottom = '');
            $pdfBig->SetFont($arialNarrowB, '', 8, '', false);
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;   

            // if($previewPdf==1){
                // if($previewWithoutBg!=1){
                    //if($studentDataOrg['Certificate No.']!="M-12345678"){
                //    $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
                    //}
                // }

                $date_font_size = '11';
                $date_nox = 13;
                $date_noy = 40;
                $date_nostr = ''; //'DRAFT '.date('d-m-Y H:i:s');
                $pdfBig->SetFont($arialB, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(192,192,192);
                $pdfBig->SetXY($date_nox, $date_noy);
                $pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetFont($arialNarrowB, '', 9, '', false);
            // }
            $pdfBig->setPageMark();

            $ghostImgArr = array();
            // $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
            // $pdf->SetCreator(PDF_CREATOR);
            // $pdf->SetAuthor('TCPDF');
            // $pdf->SetTitle('CERTIFICATE');
            // $pdf->SetSubject('');

            // remove default header/footer
            // $pdf->setPrintHeader(false);
            // $pdf->setPrintFooter(false);
            // $pdf->SetAutoPageBreak(false, 0);


            // add spot colors
            //$pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            //$pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo

            // $pdf->AddPage();  
            // $pdf->setCellPaddings( $left = '', $top = '', $right = '', $bottom = '');           
            $print_serial_no = $this->nextPrintSerial();
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;

            // if($previewPdf!=1){
            //     $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
            // }
            // $pdf->setPageMark();
            
            // if($previewPdf!=1){ 
            // if($previewPdf == 1){    
                $x= 144;
                $y = 242.5;
                $font_size=10;
                
                if($previewPdf!=1){
                    $str = str_pad($studentDataOrg['Paper Number'], 7, '0', STR_PAD_LEFT);
                }else{
                    $str = str_pad($studentDataOrg['Paper Number'], 7, '0', STR_PAD_LEFT);   
                }               
                $strArr = str_split($str);
                $x_org=$x;
                $y_org=$y;
                $font_size_org=$font_size;
                $i =0;
                $j=0;
                $y=$y+4.5;
                $z=0;
                foreach ($strArr as $character) {
                    // $pdf->SetFont($courierNew,0, $font_size, '', false);
                    // $pdf->SetXY($x, $y+$z);

                    $pdfBig->SetFont($courierNew,0, $font_size, '', false);
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
                   // $pdf->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');
                   $pdfBig->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');
                    $i++;
                    $x=$x+2.2+$j; 
                    if($i>2){
                     $font_size=$font_size+1;   
                    }
                }         
            // }
                $fontSize = 11;
                $totalAmount = $studentDataOrg['Total Amount (Rs.)'];
                $certificateNo = $studentDataOrg['Certificate No.'];
                $partyName = $studentDataOrg['Party Name'];
                $certificateIssueDate = $studentDataOrg['Certificate Issue Date'];
                $stampDutyPaid = $studentDataOrg['Stamp Duty Paid (Rs.)'];
                $purchasedBy = $studentDataOrg['Purchased by'];
                $rupees = substr($totalAmount, 0, strpos($totalAmount, "("));

                $repeatCertificateNo = $this->microLine($certificateNo, 750, 8, $subdomain[0]);
                // $repeatCertificateNo = substr($repeatCertificateNo, 0, 103);
                $repeatPartyName = $this->microLine($partyName, 750, 8, $subdomain[0]);
                // $repeatPartyName = substr($repeatPartyName, 0, 103);
                $repeatIssueDate = $this->microLine($certificateIssueDate, 73, 1.2, $subdomain[0]);
                $repeatRupees = $this->microLine($rupees, 750, 8, $subdomain[0]);
                // $repeatRupees = substr($repeatRupees, 0, 126);


                $serial_no=$GUID=$certificateNo;
                $dt = date("_ymdHis");
                $str=$certificateNo.$dt;

                $encryptedString = strtoupper(md5($str));
                 
                $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';


                // $resultsQR = [
                //     'Certificate No.' => $certificateNo, 
                //     'Certificate Issued Date' => $certificateIssueDate,
                //     'Party Name' => $partyName,
                //     'Stamp Duty Paid (Rs.)' => $stampDutyPaid,
                // ];

                    
                $resultsQR = $certificateNo.' | '.$certificateIssueDate.' | '.$purchasedBy.' | '.$partyName.' | '.$totalAmount;
                //$qrJson=json_encode($resultsQR);
                // $encryptedString=CoreHelper::encrypt_adv($qrJson, \Config::get('constant.EStamp_Salt'));
                $encryptedString= GibberishAES::enc($resultsQR, \Config::get('constant.EStamp_Salt'));

                $codeContents = $encryptedString;
                // \PHPQRCode\QRcode::png($codeContents, $qr_code_path, 'L', 1, 1);


                


               /*  $imageSource = public_path().'/backend/canvas/ghost_images/VOID FINAL FOR BROTHER.SVG';
                 $pdfBig->ImageSVG($file=$imageSource, 20, 200, 50, 10, $link='', $align='', $palign='', $border=0, $fitonpage=false);
                
                 $imageSource = public_path().'/backend/canvas/ghost_images/VOID FINAL FOR BROTHER_NEW.SVG';
                 $pdfBig->ImageSVG($file=$imageSource, 20, 230, 50, 10, $link='', $align='', $palign='', $border=0, $fitonpage=false);
                
*/
                

                $pdfBig->SetTextColor(0, 0, 0, 7);
                $pdfBig->SetFont($timesNewRoman, '', 8, '', false);
                $html = '<span style="font-family:dejavusans;">&#8377;'.$rupees.'</span>';
                $pdfBig->setXY(141, 181);
                $pdfBig->MultiCell(50, 0, $html, '', 'L', 0, 1, '', '', true, 0, true);

                $htmlVal = "";
                for($i=1;$i<5;$i++)
                {
                    $htmlVal .= '<span style="font-family:dejavusans;">&#8377;'.$rupees.'</span>';
                }
                $pdfBig->setXY(80, 206);
                $pdfBig->MultiCell(185, 0, $htmlVal, '', 'L', 0, 0, '', '', true, 0, true);
                $pdfBig->SetFont($timesNewRoman, '', 12, '', false);
                $pdfBig->setXY(136, 94);
                $pdfBig->MultiCell(90, 0, $html, '', 'L', 0, 1, '', '', true, 0, true);
               
                $pdfBig->SetTextColor(0, 0, 0);


                $pdfBig->SetFont($timesNewRoman, 'B', 17, '', false);
                $pdfBig->setXY(15, 68);
                $pdfBig->MultiCell(180, 0, 'e-Stamp', '', 'C', 0, 1);

                $pdfBig->SetFont($timesNewRoman, 'B', $fontSize, '', false);
                $pdfBig->setXY(30, 85);
                $pdfBig->MultiCell(55, 0, 'Certificate No.', '', 'L', 0, 1);
                $pdfBig->setXY(82, 85);
                $pdfBig->MultiCell(4, 0, ':', '', 'L', 0, 1);
                $pdfBig->setXY(90, 85);
                $pdfBig->MultiCell(105, 0, $certificateNo, '', 'L', 0, 1);

                $yAxis = 93;
                $pdfBig->SetFont($timesNewRoman, '', $fontSize, '', false);
                foreach($studentDataOrg as $key => $value )
                {
                    if($key == 'Certificate No.' || $key == 'Paper Number'){
                        continue;
                    }
                    $pdfBig->setXY(30, $yAxis);
                    $pdfBig->MultiCell(55, 0, $key, '', 'L', 0, 1);
                    $pdfBig->setXY(82, $yAxis);
                    $pdfBig->MultiCell(4, 0, ':', '', 'L', 0, 1);
                    $pdfBig->setXY(90, $yAxis);
                    $pdfBig->MultiCell(105, 0, $value, '', 'L', 0, 1);

                    $yAxis = $yAxis + 7;
                }
                
                // $pdfBig->setPageMark(); 
                // $pdfBig->Image($qr_code_path, 20, 241, '23', '23', "png", '', true, false);
                // $pdfBig->setPageMark();

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
                    'text' => true,
                    'font' => 'calibri',
                    'fontsize' => 1,
                    'stretchtext' => 4
                );
                $qrCodex = 20;
                $qrCodey = 241;
                $qrCodeWidth =23;
                $qrCodeHeight = 23;
                $ecc = 'L'; 
                $pdfBig->write2DBarcode($codeContents, 'QRCODE,Q', $qrCodex, $qrCodey, $qrCodeWidth, $qrCodeHeight, $style1Da, 'N');

                // With JPG BAckground
                $pdfBig->SetTextColor(0, 0, 0, 2.3);
                $pdfBig->SetFont($arialB, '', 8, '', false);
                $pdfBig->setXY(4.5, 10.6);
                $pdfBig->Cell(201.5, 0, $repeatCertificateNo, 0, 1, 'L', 0, '', 4);

                $pdfBig->setXY(4.5, 283.4);//284
                $pdfBig->Cell(201.5, 0, $repeatPartyName, 0, 1, 'L', 0, '', 4);

                // With merge pdf BAckground
                // $pdfBig->SetTextColor(0, 0, 0, 2.3);
                // $pdfBig->SetFont($arialB, '', 8, '', false);
                // $pdfBig->setXY(4.5, 10);
                // $pdfBig->Cell(201.5, 0, $repeatCertificateNo, 0, 1, 'L', 0, '', 4);

                // $pdfBig->setXY(4.5, 283);
                // $pdfBig->Cell(201.5, 0, $repeatPartyName, 0, 1, 'L', 0, '', 4);

                $pdfBig->setXY(4.5, 227.3);
                $pdfBig->Cell(201.5, 0, $repeatCertificateNo, 0, 1, 'L', 0, '', 4);
                // $pdfBig->MultiCell(201.5, 0, $repeatCertificateNo, '', 'L', 0, 1);
                $pdfBig->setXY(4.5, 230.8);
                $pdfBig->Cell(201.5, 0, $repeatPartyName, 0, 1, 'L', 0, '', 4);
                // $pdfBig->MultiCell(201.5, 0, $repeatPartyName, '', 'L', 0, 1);
                $pdfBig->setXY(4.5, 234);//.3
                $pdfBig->Cell(201.5, 0, $repeatRupees, 0, 1, 'L', 0, '', 4);
                // $pdfBig->MultiCell(201.5, 0, $repeatRupees, '', 'L', 0, 1);
                
                $pdfBig->SetTextColor(0, 0, 0);
                $pdfBig->SetFont($arial, '', 1.4, '', false);
                $pdfBig->setXY(94, 75.5);
                $pdfBig->Cell(21, 0, $repeatIssueDate, 0, 1, 'L', 0, '', 4);
                // $pdfBig->MultiCell(21, 0, $repeatIssueDate, '', 'L', 0, 1);

                $partyName = $this->microLine($partyName, 55, 1.4, $subdomain[0]);
                $pdfBig->SetFont($arial, '', 1.4, '', false);
                $pdfBig->setXY(22, 265);//265
                $pdfBig->Cell(19, 0, $partyName, 0, 1, 'L', 0, '', 4);
                // $pdfBig->MultiCell(19, 0, $partyName, '', 'L', 0, 1);


                // $nameOrg="BR-1234567890";
                // $name = substr(str_replace(' ','',strtoupper($certificateNo)), 3, 10);
                $name=preg_replace("/[^0-9]/", "",$certificateNo);    
                $name = substr(str_replace(' ','',strtoupper($name)), 0, 10);
                $ghost_font_size = '11';

                if(strlen($name) == 4){
                    $ghostImagex = 160;
                }elseif (strlen($name) == 5) {
                    $ghostImagex = 146;
                }elseif (strlen($name) == 6) {
                    $ghostImagex = 141.9;
                }elseif (strlen($name) == 7) {
                    $ghostImagex = 139.5;
                }elseif (strlen($name) == 8) {
                    $ghostImagex = 135.9;
                }elseif (strlen($name) == 9) {
                    $ghostImagex = 132.5;
                }elseif (strlen($name) == 10) {
                    $ghostImagex = 129.5;
                }else{
                    $ghostImagex = 128;
                }

                $ghostImagey = 255;
                $ghostImageWidth = 6.1*strlen($name);//68
                $ghostImageHeight = 7;


                $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
                $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
                // $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'C', true, 3600);
                $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'C', true, 3600);





                /*$pdf->SetTextColor(0, 0, 0, 7);
                $pdf->SetFont($timesNewRoman, '', 8, '', false);
                $html = '<span style="font-family:dejavusans;">&#8377;'.$rupees.'</span>';
                $pdf->setXY(141, 181);
                $pdf->MultiCell(15, 0, $html, '', 'L', 0, 1, '', '', true, 0, true);

                $htmlVal = "";
                for($i=1;$i<5;$i++)
                {
                    $htmlVal .= '<span style="font-family:dejavusans;">&#8377;'.$rupees.'</span>';
                }
                $pdf->setXY(80, 206);
                $pdf->MultiCell(85, 0, $htmlVal, '', 'L', 0, 0, '', '', true, 0, true);
                $pdf->SetFont($timesNewRoman, '', 12, '', false);
                $pdf->setXY(136, 94);
                $pdf->MultiCell(25, 0, $html, '', 'L', 0, 1, '', '', true, 0, true);
               
                $pdf->SetTextColor(0, 0, 0);


                $pdf->SetFont($timesNewRoman, 'B', 17, '', false);
                $pdf->setXY(15, 68);
                $pdf->MultiCell(180, 0, 'e-Stamp', '', 'C', 0, 1);

                $pdf->SetFont($timesNewRoman, 'B', $fontSize, '', false);
                $pdf->setXY(30, 85);
                $pdf->MultiCell(55, 0, 'Certificate No.', '', 'L', 0, 1);
                $pdf->setXY(82, 85);
                $pdf->MultiCell(4, 0, ':', '', 'L', 0, 1);
                $pdf->setXY(90, 85);
                $pdf->MultiCell(105, 0, $certificateNo, '', 'L', 0, 1);

                $yAxis = 93;
                $pdf->SetFont($timesNewRoman, '', $fontSize, '', false);
                foreach($studentDataOrg as $key => $value )
                {
                    if($key == 'Certificate No.' || $key == 'Paper Number'){
                        continue;
                    }
                    $pdf->setXY(30, $yAxis);
                    $pdf->MultiCell(55, 0, $key, '', 'L', 0, 1);
                    $pdf->setXY(82, $yAxis);
                    $pdf->MultiCell(4, 0, ':', '', 'L', 0, 1);
                    $pdf->setXY(90, $yAxis);
                    $pdf->MultiCell(105, 0, $value, '', 'L', 0, 1);

                    $yAxis = $yAxis + 7;
                }
                
                $pdf->setPageMark(); 
                $pdf->Image($qr_code_path, 20, 241, '23', '23', "png", '', true, false);
                $pdf->setPageMark();

                // With jpg background
                $pdf->SetTextColor(0, 0, 0, 2.3);
                $pdf->SetFont($arialB, '', 8, '', false);
                $pdf->setXY(4.5, 10.6);//11
                $pdf->Cell(201.5, 0, $repeatCertificateNo, 0, 1, 'L', 0, '', 4);

                $pdf->setXY(4.5, 284);
                $pdf->Cell(201.5, 0, $repeatPartyName, 0, 1, 'L', 0, '', 4);


                // With merge pdf background
                // $pdf->SetTextColor(0, 0, 0, 2.3);
                // $pdf->SetFont($arialB, '', 8, '', false);
                // $pdf->setXY(4.5, 11);
                // $pdf->Cell(201.5, 0, $repeatCertificateNo, 0, 1, 'L', 0, '', 4);

                // $pdf->setXY(4.5, 284);
                // $pdf->Cell(201.5, 0, $repeatPartyName, 0, 1, 'L', 0, '', 4);


                $pdf->setXY(4.5, 227.3);//.3
                $pdf->Cell(201.5, 0, $repeatCertificateNo, 0, 1, 'L', 0, '', 4);
                // $pdf->MultiCell(201.5, 0, $repeatCertificateNo, '', 'L', 0, 1);
                $pdf->setXY(4.5, 230.8);
                $pdf->Cell(201.5, 0, $repeatPartyName, 0, 1, 'L', 0, '', 4);
                // $pdf->MultiCell(201.5, 0, $repeatPartyName, '', 'L', 0, 1);
                $pdf->setXY(4.5, 234);//.3
                $pdf->Cell(201.5, 0, $repeatRupees, 0, 1, 'L', 0, '', 4);
                // $pdf->MultiCell(201.5, 0, $repeatRupees, '', 'L', 0, 1);
                
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont($arialB, 'B', 1.4, '', false);
                $pdf->setXY(94, 75.5);
                $pdf->Cell(21, 0, $repeatIssueDate, 0, 1, 'L', 0, '', 4);

                $partyName = $this->microLine($partyName, 71, 1.4, $subdomain[0]);
                $pdf->SetFont($arialB, 'B', 1.4, '', false);
                $pdf->setXY(22, 265);
                $pdf->Cell(19, 0, $partyName, 0, 1, 'L', 0, '', 4); */






        




            if($previewPdf!=1){

                $certName = str_replace("/", "_", $GUID) .".pdf";
                
                $myPath = public_path().'/backend/temp_pdf_file';

                $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;

                // $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

                $this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id, $studentDataOrg);

                $username = $admin_id['username'];
                date_default_timezone_set('Asia/Kolkata');

                $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
                $date = date('Y-m-d H:i:s').PHP_EOL;
                $print_datetime = date("Y-m-d H:i:s");
                

                $print_count = $this->getPrintCount($serial_no);
                $printer_name = /*'HP 1020';*/$printer_name;

                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'BiharEstampCert',$admin_id,$card_serial_no);

                $card_serial_no=$card_serial_no+1;
            }else{
                $preview_serial_no=$preview_serial_no+1;
            }

            $generated_documents++;

            if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
                $updated=date('Y-m-d H:i:s');
                ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['generated_documents'=>$generated_documents,"updated_at"=>$updated]);
            }else{
              //For Custom loader calculation
                //echo $generated_documents;
              // $endTimeLoader = date('Y-m-d H:i:s');
              // $time1 = new \DateTime($startTimeLoader);
              // $time2 = new \DateTime($endTimeLoader);
              // $interval = $time1->diff($time2);
              // $interval = $interval->format('%s');

              // $jsonArr=array();
              // $jsonArr['token'] = $pdf_data['loader_token'];
              // $jsonArr['generatedCertificates'] =$generated_documents;
              // $jsonArr['timePerCertificate'] =$interval;
             
              // $loaderData=CoreHelper::createLoaderJson($jsonArr,0);
            }
            //delete temp dir 26-04-2022 
            if(isset($tempDir)){

            CoreHelper::rrmdir($tmpDir);
            }
       // } 
        
       if($previewPdf!=1){
        $this->updateCardNo('BiharEstampCert',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
       }
       $msg = '';
        //$file_name_pre =  str_replace("/", "_",'BiharEstampCert'.date("Ymdhms"));
        $file_name =  str_replace("/", "_",'BiharEstampCert'.date("Ymdhms")).'.pdf';
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


       //$filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        $pdfBig->output($filename,'F');

        //if($certificateNo=="M-12345678"){
             
           // print_r($output);
        //}

        if($previewPdf!=1){
        
             $directoryUrlForward="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/pdf2pdf/";
              $directoryUrlBackward="C:\\Inetpub\\vhosts\\seqrdoc.com\\httpdocs\\demo\\public\\pdf2pdf\\";
              $mergePath="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/estamp/backend/tcpdf/examples/";
              if($certificateNo=="TE-BR-1234567890"){
                $bgPath="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/estamp/backend/canvas/bg_pdfs/Type_B_3P_W.pdf";
              }else{
                $bgPath="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/estamp/backend/canvas/bg_pdfs/estamp_bg.pdf";
              }
              
              $pyscript = $directoryUrlBackward."Python_files\\estamp_merge.py";
              $cmd = "$pyscript $file_name $file_name $mergePath $bgPath 2>&1"; // 
              exec($cmd, $output, $return);

          //  $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
           // @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="BiharEstampCert";
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                // with sandbox
                
                $result = SbExceUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            }else{
                // without sandbox
                $result = ExcelUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            } 
            
        
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name;
         if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
       
        $msg = $path.$subdomain[0]."/backend/tcpdf/examples/".$file_name;
        }else{
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf download' target='_blank'>Here</a> to download file<b>";
            
        } 
        }else{
          
        $directoryUrlForward="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/pdf2pdf/";
        $directoryUrlBackward="C:\\Inetpub\\vhosts\\seqrdoc.com\\httpdocs\\demo\\public\\pdf2pdf\\";
        $mergePath="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/estamp/backend/tcpdf/examples/preview/";
        
        if($certificateNo=="TE-BR-1234567890"){
            $bgPath="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/estamp/backend/canvas/bg_pdfs/estamp_bg_test.pdf";
        }else{
            $bgPath="C:/Inetpub/vhosts/seqrdoc.com/httpdocs/demo/public/estamp/backend/canvas/bg_pdfs/estamp_bg.pdf";
        }
        
        $pyscript = $directoryUrlBackward."Python_files\\estamp_merge.py";
        $cmd = "$pyscript $file_name $file_name $mergePath $bgPath 2>&1"; // 
        exec($cmd, $output, $return);

        /*$aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$file_name);
        @unlink($filename);
*/        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name;
        if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
        
        $msg = $path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name;
        }else{
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name."' class='downloadpdf download' target='_blank'>Here</a> to download file<b>";    
        }
        }
        //API changes
        if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
         $updated=date('Y-m-d H:i:s');
        
        ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['status'=>'Completed','printable_pdf_link'=>$pdf_url,"updated_at"=>$updated]);
        //Sending data to call back url
        $reaquestParameters = array
        (
            'request_id'=>$pdf_data['request_id'],
            'printable_pdf_link' => $pdf_url,
        );
        $url = $pdf_data['call_back_url'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reaquestParameters));
        $result = curl_exec($ch);
        
        $updated=date('Y-m-d H:i:s');
        ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['call_back_response'=>json_encode($result),"updated_at"=>$updated]);

        curl_close($ch);
        }

        return $msg;



    }

    public function uploadPdfsToServer(){
         $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
            $certName="abc.pdf";
         
        $files=$this->getDirContents(public_path().'/'.$subdomain[0].'/backend/pdf_file/');

foreach ($files as $filename) {
echo $filename."<br>";
}
    }

public function getDirContents($dir, &$results = array()) {
    $files = scandir($dir);

    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        } 
    }

    return $results;
}


function microLine($text, $w, $fontSize, $subdomain)
{
    $microlineEnrollment = strtoupper($text);
    $microlineEnrollment = preg_replace('/\s+/', '', $microlineEnrollment);
    $textArrayEnrollment = imagettfbbox($fontSize, 0, public_path().'/'.$subdomain.'/backend/canvas/fonts/Arialb.TTF', $microlineEnrollment);
    $strWidthEnrollment = ($textArrayEnrollment[2] - $textArrayEnrollment[0]);
    $strHeightEnrollment = $textArrayEnrollment[6] - $textArrayEnrollment[1] / $fontSize;
    $latestWidthEnrollment = $w;
    $microlineEnrollmentstrLength = strlen($microlineEnrollment);
    //width per character
    $microlineEnrollmentcharacterWd = $strWidthEnrollment/$microlineEnrollmentstrLength;
    //Required no of characters required in string to match width
    $microlineEnrollmentCharReq = $latestWidthEnrollment/$microlineEnrollmentcharacterWd;
    $microlineEnrollmentCharReq = round($microlineEnrollmentCharReq);
    //No of time string should repeated
    $repeatemicrolineEnrollmentCount = $latestWidthEnrollment/$strWidthEnrollment;
    $repeatemicrolineEnrollmentCount = round($repeatemicrolineEnrollmentCount)+1;
    //Repeatation of string 
    $microlineEnrollmentstrRep = str_repeat($microlineEnrollment, $repeatemicrolineEnrollmentCount);                
    //Cut string in required characters (final string)
    $arrayEnrollment = substr($microlineEnrollmentstrRep,0,$microlineEnrollmentCharReq);

    return $arrayEnrollment;
    
}

public function downloadPdfsFromServer(){
 $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
$accesskey = "Tz7IOmG/9+tyxZpRTAam+Ll3eqA9jezqHdqSdgi+BjHsje0+VM+pKC6USBuR/K0nkw5E7Psw/4IJY3KMgBMLrA==";
$storageAccount = 'seqrdocpdf';
$containerName = 'pdffile';

        $files=$this->getDirContents(public_path().'/'.$subdomain[0].'/backend/pdf_file/');

foreach ($files as $filename) {
$myFile = pathinfo($filename); 
$blobName = 'BMCC\PC\\'.$myFile['basename'];
echo $destinationURL = "https://$storageAccount.blob.core.windows.net/$containerName/$blobName";

$local_server_file_path= public_path().'/'.$subdomain[0].'/backend/pdf_file_downloaded/'.$blobName;
if(file_exists($destinationURL)){
file_put_contents($local_server_file_path, file_get_contents($destinationURL));
}
}

}

    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id, $studentDataOrg)
    {
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        } 

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        $pdfActualPath=public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;

        // copy($file1, $file2);        
        // $aws_qr = \File::copy($file2,$pdfActualPath);
        @unlink($file2);
        /*$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;
        $output=\Config::get('constant.directoryPathBackward')."\\".$subdomain[0]."\\backend\\pdf_file\\".$certName; 
        CoreHelper::compressPdfFile($source,$output);*/
        @unlink($file1);

        //Sore file on azure server

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

        if($systemConfig['sandboxing'] == 1){
        $resultu = SbStudentTable::where('serial_no','T-'.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = SbStudentTable::create(['serial_no'=>'T-'.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }else{
        $resultu = StudentTableEstamp::where('serial_no',''.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = StudentTableEstamp::create(['serial_no'=>''.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>2, 'json_data'=>json_encode($studentDataOrg)]);
        }
        
    }
    
    public function testUpload($certName, $pdfActualPath)
    {
        // FTP server details
        $ftpHost = \Config::get('constant.monad_ftp_host');
        $ftpPort = \Config::get('constant.monad_ftp_port');
        $ftpUsername = \Config::get('constant.monad_ftp_username');
        $ftpPassword = \Config::get('constant.monad_ftp_pass');        
        // open an FTP connection
        $connId = ftp_connect($ftpHost,$ftpPort) or die("Couldn't connect to $ftpHost");
        // login to FTP server
        $ftpLogin = ftp_login($connId, $ftpUsername, $ftpPassword);
        // local & server file path
        $localFilePath  = $pdfActualPath;
        $remoteFilePath = $certName;
        // try to upload file
        if(ftp_put($connId, $remoteFilePath, $localFilePath, FTP_BINARY)){
            //echo "File transfer successful - $localFilePath";
        }else{
            //echo "There was an error while uploading $localFilePath";
        }
        // close the connection
        ftp_close($connId);
    }
    
    public function getPrintCount($serial_no)
    {
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        }
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    
}
public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$card_serial_no)
    {
       
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        }

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>'T-'.$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }else{
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);    
        }
    }

    public function nextPrintSerial()
    {
        $current_year = 'PN/' . $this->getFinancialyear() . '/';
        // find max
        $maxNum = 0;
        
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        }

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 10), UNSIGNED)), 0) AS next_num "
                . "FROM printing_details WHERE SUBSTR(print_serial_no, 1, 9) = '$current_year'");
        //get next num
        $maxNum = $result[0]->next_num + 1;
       
        return $current_year . $maxNum;
    }

    public function getNextCardNo($template_name)
    { 
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        }
         //print_r($auth_site_id);
      
        //\DB::enableQueryLog();

        // print_r($auth_site_id);
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        //print_r($systemConfig);
      // dd(\DB::getQueryLog()); 
        if($systemConfig['sandboxing'] == 1){

        $result = \DB::select("SELECT * FROM sb_card_serial_numbers WHERE template_name = '$template_name'");
        }else{
        $result = \DB::select("SELECT * FROM card_serial_numbers WHERE template_name = '$template_name'");
        
        }
        //print_r($result);  
        //exit;
       
        return $result[0];
    }

    public function updateCardNo($template_name,$count,$next_serial_no)
    { 
        //Updated by Mandar for api based pdf generation
        if(Auth::guard('admin')->user()){
            $auth_site_id=Auth::guard('admin')->user()->site_id;
        }else{
            $auth_site_id=$this->pdf_data['auth_site_id'];
        }

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        if($systemConfig['sandboxing'] == 1){
        $result = \DB::select("UPDATE sb_card_serial_numbers SET card_count='$count',next_serial_no='$next_serial_no' WHERE template_name = '$template_name'");
        }else{
        $result = \DB::select("UPDATE card_serial_numbers SET card_count='$count',next_serial_no='$next_serial_no' WHERE template_name = '$template_name'");

        }
        
        return $result;
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

    public function createTemp($path){
        //create ghost image folder
        $tmp = date("ymdHis");
       
        $tmpname = tempnam($path, $tmp);
        //unlink($tmpname);
        //mkdir($tmpname);
        if (file_exists($tmpname)) {
         unlink($tmpname);
        }
        mkdir($tmpname, 0777);
        return $tmpname;
    }

    public function CreateMessage($tmpDir, $name ="",$font_size,$print_color)
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
                "6" => array(26645, 850),
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

    function GetStringPositions($strings,$pdf)
    {
        $len = count($strings);
        $w = array();
        $sum = 0;
        foreach ($strings as $key => $str) {
            $width = $pdf->GetStringWidth($str[0], $str[1], $str[2], $str[3], false);
            $w[] = $width;
            $sum += intval($width);
            
        }
        
        $ret = array();
        $ret[0] = (205 - $sum)/2;
        for($i = 1; $i < $len; $i++)
        {
            $ret[$i] = $ret[$i - 1] + $w[$i - 1] ;
            
        }
        
        return $ret;
    }

    function sanitizeQrString($content){
         $find = array('â€œ', 'â€™', 'â€¦', 'â€”', 'â€“', 'â€˜', 'Ã©', 'Â', 'â€¢', 'Ëœ', 'â€'); // en dash
         $replace = array('“', '’', '…', '—', '–', '‘', 'é', '', '•', '˜', '”');
        return $content = str_replace($find, $replace, $content);
    }

    function getNumberWords(float $number)
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $hundred = null;
        $digits_length = strlen($no);
        $i = 0;
        $str = array();
        $words = array(0 => '', 1 => 'one', 2 => 'two',
            3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
            7 => 'seven', 8 => 'eight', 9 => 'nine',
            10 => 'ten', 11 => 'eleven', 12 => 'twelve',
            13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
            16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
            19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
            40 => 'forty', 50 => 'fifty', 60 => 'sixty',
            70 => 'seventy', 80 => 'eighty', 90 => 'ninety');
        $digits = array('', 'hundred','thousand','lakh', 'crore');
        while( $i < $digits_length ) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += $divider == 10 ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
            } else $str[] = null;
        }
        $NumberWords = implode('', array_reverse($str));
        $result=($NumberWords ? $NumberWords : '');
        return strtoupper($result);
    }   
  
}
