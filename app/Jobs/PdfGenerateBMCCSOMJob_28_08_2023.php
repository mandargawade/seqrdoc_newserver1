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
use App\models\StudentTable;
use App\models\SbStudentTable;
use App\models\PrintingDetail;
use App\models\SbPrintingDetail;
use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;
use App\Jobs\SendMailJob;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use Illuminate\Support\Facades\Redis;
use App\models\ThirdPartyRequests;
use App\Helpers\CoreHelper;
use Helper;
class PdfGenerateBMCCSOMJob
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

        $pdf_data = $this->pdf_data;

        $studentDataOrg=$pdf_data['studentDataOrg'];
        $subjectsDataOrg=$pdf_data['subjectsDataOrg'];
         $template_id=$pdf_data['template_id'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
        
        $contractAddress=$pdf_data['contractAddress'];
        $isBlockChain=$pdf_data['isBlockChain'];
        
        //print_r($previewPdf);
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];


        /*print_r($studentDataOrg);
        print_r($subjectsDataOrg);*/
           

        if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
        
            $admin_id=$pdf_data['admin_id'];
        }else{
            $admin_id = \Auth::guard('admin')->user()->toArray();  
        }
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        //$auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        // dd($systemConfig);
        $printer_name = $systemConfig['printer_name'];

        //Separate students subjects
        $subjectsArr = array();
        foreach ($subjectsDataOrg as $element) {
            $subjectsArr[$element[0]][] = $element;
        }


        $ghostImgArr = array();
        $pdfBig = new TCPDF('L', 'mm', array('297', '420'), true, 'UTF-8', false);
        $pdfBig->SetCreator(PDF_CREATOR);
        $pdfBig->SetAuthor('TCPDF');
        $pdfBig->SetTitle('Certificate');
        $pdfBig->SetSubject('');

        // remove default header/footer
        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);


        // add spot colors
        $pdfBig->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        $pdfBig->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo

        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
       // $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $log_serial_no = 1;
        $cardDetails=$this->getNextCardNo('BMCC-GC');
        $card_serial_no=$cardDetails->next_serial_no;
        $generated_documents=0;  
        foreach ($studentDataOrg as $studentData) {
         
         if($card_serial_no<9999&&$previewPdf!=1){
            echo "<h5>Your card series ended...!</h5>";
            exit;
         }

         $pdfBig->AddPage();
         $pdfBig->SetFont($arial, '', 8, '', false);
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\BMCC statment of marks_BG.jpg';
        
        if($previewPdf==1){
            if($previewWithoutBg!=1){
                $pdfBig->Image($template_img_generate, 0, 0, '420', '297', "JPG", '', 'R', true);
            }

        $date_font_size = '11';
        $date_nox = 13;
        $date_noy = 37;
        $date_nostr = 'DRAFT '.date('d-m-Y H:i:s');
        $pdfBig->SetFont($arialb, '', $date_font_size, '', false);
        $pdfBig->SetTextColor(192,192,192);
        $pdfBig->SetXY($date_nox, $date_noy);
        $pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetFont($arial, '', 8, '', false);
        }
        $pdfBig->setPageMark();


            $subjectsData=$subjectsArr[$studentData[3]];
            //Separate semesters 
            $subjects = array();
            foreach ($subjectsData as $element) {
                $subjects[$element[1]][] = $element;
            }
            ksort($subjects);
 

        $ghostImgArr = array();
        $pdf = new TCPDF('L', 'mm', array('297', '420'), true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('TCPDF');
        $pdf->SetTitle('Certificate');
        $pdf->SetSubject('');

        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);


        // add spot colors
        $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo

        //set fonts
        /*$arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);*/

        $pdf->AddPage();
        
        $print_serial_no = $this->nextPrintSerial();
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\BMCC statment of marks_BG.jpg';
        if($previewPdf!=1){
        $pdf->Image($template_img_generate, 0, 0, '420', '297', "JPG", '', 'R', true);
        }
        $pdf->setPageMark();

        if($previewPdf!=1){
        $fontEBPath=public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\E-13B_0.php';
        $pdf->AddFont('E-13B_0', '', $fontEBPath);
        $pdfBig->AddFont('E-13B_0', '', $fontEBPath);
         //set enrollment no
        $card_serial_no_font_size = '13';
        $card_serial_nox= 172.5;//172.5
        $card_serial_noy = 35.5;
        $pdf->SetFont('E-13B_0', '', $card_serial_no_font_size, '', false);
        $pdf->SetXY($card_serial_nox, $card_serial_noy);
        $pdf->Cell(23.5, 0, $card_serial_no, 0, false, 'R');


        $pdfBig->SetFont('E-13B_0', '', $card_serial_no_font_size, '', false);
        $pdfBig->SetXY($card_serial_nox, $card_serial_noy);
        $pdfBig->Cell(23.5, 0, $card_serial_no, 0, false, 'R');

        

        $card_serial_noxx= 375.5;
        $card_serial_noyy = 35.5;
        $pdf->SetFont('E-13B_0', '', $card_serial_no_font_size, '', false);
        $pdf->SetXY($card_serial_noxx, $card_serial_noyy);
        $pdf->Cell(0, 0, $card_serial_no, 0, false, 'L');


        $pdfBig->SetFont('E-13B_0', '', $card_serial_no_font_size, '', false);
        $pdfBig->SetXY($card_serial_noxx, $card_serial_noyy);
        $pdfBig->Cell(0, 0, $card_serial_no, 0, false, 'L');

        }
        $pdf->SetFont($arial, '', 8, '', false);
        $pdfBig->SetFont($arial, '', 8, '', false);

        $semRomanArr=array(1=>"I",2=>"II",3=>"III",4=>"IV",5=>"V",6=>"VI");

        if($studentData[6]=="MASTER OF COMMERCE"){
            $fontSizeCource=11;
        }else{
            $fontSizeCource=8;
        }

        $html = <<<EOD
<style>
 td{
 
  font-size:8px; 
 border-top: 0px solid black;
    border-bottom: 0px solid black;
}
#table1 {
    
  border-collapse: collapse;
}
</style>
<table id="table1" cellspacing="0" cellpadding="2" border="0.1" width="46.4%" rules="rows">
   <tr>
    <td colspan="2" style="width:52%;"> Student Name :  <b>{$studentData[2]} </b></td>
    <td style="width:48%;"> Mother's Name :  <b>{$studentData[5]} </b></td>
   </tr>
   <tr>
    <td style="width:19.1%;"> Examination Programme :</td>
    <td style="width:47.57%;"><b>{$studentData[6]}</b></td>
    <td style="width:33.33%;"> Year : <b>{$studentData[7]}</b></td>
   </tr>
   <tr>
    <td style="width:33.34%;"> Permanent Reg . No : <b>{$studentData[3]}</b></td>
    <td style="text-align:center;width:33.33%;"> Seat Number : <b>{$studentData[0]}</b></td>
    <td style="width:33.33%;"> UID. No : <b>{$studentData[4]}</b></td>
   </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='46', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y='46', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$linesLoopMaxL=42;
$linesLoopMaxR=77;
if(strlen($studentData[6])>=38){
    $offset=3; 
    $lineOffset=1;
    $linesLoopMaxR=76;
}else{
    $offset=0;
    $lineOffset=0;
}

$html = <<<EOD
<style>
 td{
 
  font-size:8px; 

}
#table111 {
    
  border-collapse: collapse;
}
</style>
<table id="table111" cellspacing="0" cellpadding="2" border="0" width="46.4%">
  <tr>
    <td>Special Subject : <b>{$studentData[8]}</b></td>
    </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y=61.7+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y=61.7+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

        $html = <<<EOD
<style>
 td{
 
  font-size:8px; 
 border-top: 0px solid black;
    border-bottom: 0px solid black;
}
#table11 {
    
  border-collapse: collapse;
}
#block_container
{
    text-align:center;
}
#bloc1, #bloc2
{
    display:inline;
}
</style>
<table id="table11" cellspacing="0" cellpadding="2" border="0.1" width="98.5%" rules="rows">
 <tr>
    <td colspan="2" style="width:52%;"> Student Name :  <b>{$studentData[2]} </b></td>
    <td style="width:48%;"> Mother's Name :  <b>{$studentData[5]} </b></td>
   </tr>
   <tr>
    <td style="width:19.1%;"> Examination Programme :</td>
    <td style="width:47.57%;"><b>{$studentData[6]}</b></td>
    <td style="width:33.33%;"> Year : <b>{$studentData[7]}</b></td>
   </tr>
   <tr>
    <td style="width:33.34%;"> Permanent Reg . No : <b>{$studentData[3]}</b></td>
    <td style="text-align:center;width:33.33%;"> Seat Number : <b>{$studentData[0]}</b></td>
    <td style="width:33.33%;"> UID. No : <b>{$studentData[4]}</b></td>
   </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='222', $y='46', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='222', $y='46', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);


$lineCount=0;
$strtdL="";
$strtdR="";
$specialSubArr=array();
foreach ($subjects as $key => $semesterData) {

  //  print_r($semesterData);

    if($studentData[6]=="MASTER OF COMMERCE"&&$key==4){

        $lineCountPrev=$lineCount;
        
       
            for ($p=$lineCountPrev; $p < 31; $p++) {  //40 35 32
                 $strtdL .= '<tr>
                            <td style="width:11%;text-align:center;"></td>
                            <td style="width:39%;text-align:left;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            </tr>';
            }
        

        $lineCount=56; //47 52 56


    }elseif($studentData[6]!="MASTER OF COMMERCE"&&$key==5){

        $lineCountPrev=$lineCount;
        
       
            for ($p=$lineCountPrev; $p < 42; $p++) { //44 
                 $strtdL .= '<tr>
                            <td style="width:11%;text-align:center;"></td>
                            <td style="width:39%;text-align:left;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            </tr>';
            }
        

        $lineCount=43;

    }


    if($lineCount<($linesLoopMaxL)){    
      $strtdL .= '<tr>
    <td style="width:11%;text-align:right;"><b>SEM '.$semRomanArr[$key].'</b></td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>';
    }else{
       $strtdR .= '<tr>
    <td style="width:11%;text-align:right;"><b>SEM '.$semRomanArr[$key].'</b></td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>'; 
    }
    $lineCount=$lineCount+1;

    foreach ($semesterData as $readSubject) {


        if($readSubject[4]==="-"&&$readSubject[5]==="-"&&$readSubject[6]==="-"&&$readSubject[10]==="-"){
            array_push($specialSubArr, $readSubject);
            
        }else{
        if(strlen($readSubject[3])>34){
            $lineCount=$lineCount+1.64;
        }else{
            $lineCount=$lineCount+1;
        }

        if($lineCount<($linesLoopMaxL)){
         $strtdL .= '<tr>
                    <td style="width:11%;text-align:right;"><b>'.$readSubject[2].'</b></td>
                    <td style="width:39%;text-align:left;"><b>'.$readSubject[3].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[4].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[5].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[6].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[7].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[8].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[9].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[10].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[11].'</b></td>
                   </tr>';
        }else{

         $strtdR .= '<tr>
                    <td style="width:11%;text-align:right;"><b>'.$readSubject[2].'</b></td>
                    <td style="width:39%;text-align:left;"><b>'.$readSubject[3].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[4].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[5].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[6].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[7].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[8].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[9].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[10].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[11].'</b></td>
                   </tr>';
        }

    }

    }
}

foreach ($specialSubArr as $readSubject) {
    if(strlen($readSubject[3])>34){
            $lineCount=$lineCount+1.64;
        }else{
            $lineCount=$lineCount+1;
        }
   $strtdR .= '<tr>
                    <td style="width:11%;text-align:right;"><b>'.$readSubject[2].'</b></td>
                    <td style="width:39%;text-align:left;"><b>'.$readSubject[3].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[4].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[5].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[6].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[7].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[8].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[9].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[10].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[11].'</b></td>
                   </tr>';
}
 
 
for ($z=$lineCount; $z < 90; $z++) { 
 
 if($lineCount<($linesLoopMaxL)){
     $strtdL .= '<tr>
    <td style="width:11%;text-align:center;"></td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>';
    
 }elseif($lineCount>($linesLoopMaxL-1)&&$lineCount<$linesLoopMaxR){
     $strtdR .= '<tr>
    <td style="width:11%;text-align:center;"></td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>';
 }
$lineCount=$lineCount+1;
}
     $html = <<<EOD
<style>
 th{

  font-size:7px; 
}
 td{

  font-size:{$fontSizeCource}px;
   border-left: 0px solid black;
    border-right: 0px solid black; 
}
#table2 {
  
    height: 400px;
  
  border-collapse: collapse;
}
#table2 td:empty {
  height:400px;
  border-left: 0;
  border-right: 0;
}
.borderspace{
  border-spacing: -1.5px;
    border-collapse: separate;
    width:99%;
   display: block;
   padding-left:2px;

}

</style>
<table id="table2" class="t-table borderspace" cellspacing="0" cellpadding="2" border="0.1" width="46.4%">
  <tr>
    <th style="width:11%;text-align:center;">Subject Code</th>
    <th style="width:39%;text-align:center;">Subject Name</th>
    <th style="width:5%;text-align:center;">INT</th>
    <th style="width:5%;text-align:center;">EXT</th>
    <th style="width:7%;text-align:center;">TOT 40/100</th>
    <th style="width:7%;text-align:center;">Subject Credits</th>
    <th style="width:7%;text-align:center;">Earn Credits</th>
    <th style="width:7%;text-align:center;">Grade Point</th>
    <th style="width:7%;text-align:center;">Credit Points</th>
    <th style="width:5%;text-align:center;">Grade</th>
   </tr>
    {$strtdL}
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y=67.5+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y=67.5+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

 

     $html = <<<EOD
<style>
 th{

  font-size:7px; 
}
 td{

  font-size:{$fontSizeCource}px;
   border-left: 0px solid black;
    border-right: 0px solid black; 
}
#table3 {
  
    height: 400px;
  
  border-collapse: collapse;
}

</style>
<table id="table3" cellspacing="0" cellpadding="2" border="0.1" width="98.5%">
  <tr>
    <th style="width:11%;text-align:center;">Subject Code</th>
    <th style="width:39%;text-align:center;">Subject Name</th>
    <th style="width:5%;text-align:center;">INT</th>
    <th style="width:5%;text-align:center;">EXT</th>
    <th style="width:7%;text-align:center;">TOT 40/100</th>
    <th style="width:7%;text-align:center;">Subject Credits</th>
    <th style="width:7%;text-align:center;">Earn Credits</th>
    <th style="width:7%;text-align:center;">Grade Point</th>
    <th style="width:7%;text-align:center;">Credit Points</th>
    <th style="width:5%;text-align:center;">Grade</th>
   </tr>
    {$strtdR}
  </table>
EOD;
if(empty($offset)){
$offsetR=2;
}else{
$offsetR=0;    
}
$pdf->writeHTMLCell($w=0, $h=0, $x='222', $y=66-$offsetR, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='222', $y=66-$offsetR, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

 

     $html = <<<EOD
<style>
 th{

  font-size:9px; 
}
 td{

  font-size:7.5px;
   
}
#table4 {
  
    height: 400px;
  
  border-collapse: collapse;
}


</style>
<table id="table4" cellspacing="0" cellpadding="2" border="0.1" width="98.5%">
    <tr>
    <td style="width:12%;text-align:center;">SGPA (I) : <b>{$studentData[9]}</b></td>
    <td style="width:12%;text-align:left;">SGPA (II) : <b>{$studentData[10]}</b></td>
    <td style="width:13%;text-align:center;">SGPA (III) : <b>{$studentData[11]}</b></td>
    <td style="width:13%;text-align:center;">SGPA (IV) : <b>{$studentData[12]}</b></td>
    <td style="width:13%;text-align:center;">SGPA (V) : <b>{$studentData[13]}</b></td>
    <td style="width:13%;text-align:center;">SGPA (VI) : <b>{$studentData[14]}</b></td>
    <td style="width:12%;text-align:center;">CGPA : <b>{$studentData[15]}</b></td>
   <td style="width:12%;text-align:center;">Grade : <b>{$studentData[16]}</b></td>
    </tr>
    <tr>
     <td style="width:35%;text-align:left;" colspan="3">Academic Earned Credits : <b>{$studentData[18]}</b></td>
    <td style="width:37%;text-align:left;" colspan="3">Environment and Skill Course Earned Credits : <b>{$studentData[19]}</b></td>
    <td style="width:28%;text-align:left;" colspan="2">Total Earned Credits : <b>{$studentData[20]}</b></td>
    </tr>
    <tr>
     <td style="width:30%;text-align:left;" colspan="2">Marks : <b>{$studentData[22]}</b></td>
    <td style="width:35%;text-align:left;" colspan="3">Result : <b>{$studentData[17]}</b></td>
    <td style="width:35%;text-align:left;" colspan="3">Date : <b>{$studentData[21]}</b></td>
    </tr>
  </table>
EOD;

/*print_r($studentData);
exit;*/
$pdf->writeHTMLCell($w=0, $h=0, $x='222', $y='248.5', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='222', $y='248.5', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
     
     /*   $strFont = '13';
        $strX = 55;
        $strY = 219;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(92.7, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');*/
        $nameOrg=$studentData[2];
// Ghost image
                $ghost_font_size = '13';
                $ghostImagex = 222.2;
                $ghostImagey = 267;
                $ghostImageWidth = 55;//68
                $ghostImageHeight = 9.8;
                $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
                // dd($name);

                $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp/temp');
                // if(!array_key_exists($name, $ghostImgArr))
                // {
                    $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
                    // $ghostImgArr[$name] = $w;   
                // }
                // else{
                //     $w = $ghostImgArr[$name];
                // }

                $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
                $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        //qr code    
        $dt = date("_ymdHis");
        $blobFile = pathinfo($studentData[23]);

        $serial_no=$GUID=$blobFile['filename'];
        $str= $GUID;
        $encryptedString = strtoupper(md5($str));
        $codeContents = $studentData[24];

        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 305;
        $qrCodey = 267;
        $qrCodeWidth =19.3;
        $qrCodeHeight = 19.3;
                
        \QrCode::size(75.6)
            //->backgroundColor(255, 255, 0)
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        

        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', $str);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        $width=2;
        $latestWidth = round($width*3.7795280352161);

         //Updated by Mandar
        $microlinestr=$str;
        $wd = '';
        $last_width = 0;
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(220.6, 285);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

        $pdfBig->SetFont($arialb, '', 1.2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->StartTransform();
        $pdfBig->SetXY(220.6, 285);
        $pdfBig->Cell(0, 0, $microlinestr, 0, false, 'C');

          //Signature
        $signature_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\Bmcc coe sign.png';
        $signaturex = 365;
        $signaturey = 270.3;
        $signatureWidth = 30;
        $signatureHeight = 11;
        $pdf->image($signature_path,$signaturex,$signaturey,$signatureWidth,$signatureHeight,"",'','L',true,3600);           
        $pdfBig->image($signature_path,$signaturex,$signaturey,$signatureWidth,$signatureHeight,"",'','L',true,3600);           

        if($previewPdf!=1){

         $certName = str_replace("/", "_", $GUID) .".pdf";
            // $myPath =    ().'/backend/temp_pdf_file';
            //$myPath = public_path().'/backend/temp_pdf_file';
            $myPath = public_path().'/backend/temp_pdf_file';
            $dt = date("_ymdHis");

            $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;


            // print_r($pdf);
            // print_r("$tmpDir/" . $name."".$ghost_font_size.".png");
            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');
       // $pdf->Output('sample.pdf', 'F');

             $this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,'BMCC\GC\\');

            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            $print_datetime = date("Y-m-d H:i:s");
            

            $print_count = $this->getPrintCount($serial_no);
            $printer_name = /*'HP 1020';*/$printer_name;

            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'BMCC-GC',$admin_id,$card_serial_no);

            //Blockchain Integration
            if($isBlockChain){
                $key=strtoupper(md5($serial_no));
                $mintData=array();
                $mintData['bc_contract_address']=$contractAddress;
                $mintData['documentType']="Grade Card";
                $mintData['description']="BMCC Documents";
                
                $mintData['metadata1']=["label"=> "Student Name", "value"=> $studentData[2]];
                $mintData['metadata2']=["label"=> "Examination Programme", "value"=> $studentData[6]];
                $mintData['metadata3']=["label"=> "Permanent Reg No", "value"=> $studentData[3]];
                $mintData['metadata4']=["label"=> "Special Subject", "value"=> $studentData[8]];
                $mintData['metadata5']=["label"=> "CGPA", "value"=> $studentData[15]];

                $mintData['uniqueHash']=$key; 
               
                $pdf_file=public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;
                $mintData['pdf_file']=$pdf_file;
                $mintData['template_id']=$template_id;
                
                 
                $response=CoreHelper::mintPDF($mintData);
               // print_r($response);

                if($response['status']==200){
                    $resultu = StudentTable::where('serial_no',''.$serial_no)->where('key',''.$key)->where('status',1)->update(['bc_txn_hash'=>$response['txnHash']]);
                }
            }

            $card_serial_no=$card_serial_no+1;
            }

             $generated_documents++;

              if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
                $updated=date('Y-m-d H:i:s');
                ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['generated_documents'=>$generated_documents,"updated_at"=>$updated]);
            }
            //delete temp dir 26-04-2022 
            CoreHelper::rrmdir($tmpDir);
       } 

       if($previewPdf!=1){
        $this->updateCardNo('BMCC-GC',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
       }
       $msg = '';
        // if(is_dir($tmpDir)){
        //     rmdir($tmpDir);
        // }   
       // $file_name = $template_data['template_name'].'_'.date("Ymdhms").'.pdf';
        //print_r($fetch_degree_array);
      //  exit;
        $file_name =  str_replace("/", "_",'BMCC-GC'.date("Ymdhms")).'.pdf';
        
       // $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


       $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        // $filename = 'C:\xampp\htdocs\seqr\public\backend\tcpdf\exmples\/'.$file_name;
        $pdfBig->output($filename,'F');

        if($previewPdf!=1){

             

            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="BMCC-GC";
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
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
        }else{
          

        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$file_name);
        @unlink($filename);
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name;
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
        }
         
         //               }
                    //}
        //echo $msg;

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
        //curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        //curl_setopt($ch, CURLOPT_TIMEOUT, 2);
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
         //  echo $pdfActualPath=public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;

        $files=$this->getDirContents(public_path().'/'.$subdomain[0].'/backend/pdf_file/');

foreach ($files as $filename) {
echo $filename."<br>";
}



        //Sore file on azure server
        //CoreHelper::uploadBlob($pdfActualPath,$blob.$certName);
    }

public function getDirContents($dir, &$results = array()) {
    $files = scandir($dir);

    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        } /*else if ($value != "." && $value != "..") {
            $this->getDirContents($path, $results);
            $results[] = $path;
        }*/
    }

    return $results;
}

public function downloadPdfsFromServer(){
 $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
$accesskey = "Tz7IOmG/9+tyxZpRTAam+Ll3eqA9jezqHdqSdgi+BjHsje0+VM+pKC6USBuR/K0nkw5E7Psw/4IJY3KMgBMLrA==";
$storageAccount = 'seqrdocpdf';

//$filetoUpload = realpath('./692671.pdf');
//$containerName = 'desdocument';
$containerName = 'pdffile';
//$containerPrefix="/desdocument";


        $files=$this->getDirContents(public_path().'/'.$subdomain[0].'/backend/pdf_file/');

foreach ($files as $filename) {
$myFile = pathinfo($filename); 
$blobName = 'BMCC\PC\\'.$myFile['basename'];
echo $destinationURL = "https://$storageAccount.blob.core.windows.net/$containerName/$blobName";


//$file_url = "https://mysite.blob.core.windows.net/container-name/" . $blob_name;   
$local_server_file_path= public_path().'/'.$subdomain[0].'/backend/pdf_file_downloaded/'.$blobName;
if(file_exists($destinationURL)){
file_put_contents($local_server_file_path, file_get_contents($destinationURL));
}
}

}

    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,$blob)
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

        // dd($file1);
        /*copy($file1, $file2);        
        $aws_qr = \File::copy($file2,$pdfActualPath);
                // $msg = "<b>PDF will be sent in mail<b>";            
        @unlink($file2);*/
		$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;//$file1
		$output=\Config::get('constant.directoryPathBackward')."\\backend\\pdf_file\\".$certName;
		CoreHelper::compressPdfFile($source,$output);
        @unlink($file1);

        //Sore file on azure server
        //Storage::disk('azure')->put('GC\\'.$certName, fopen($pdfActualPath, 'r+'));
        //CoreHelper::uploadBlob($pdfActualPath,$blob.$certName);


        $sts = '1';
        $datetime  = date("Y-m-d H:i:s");
        $ses_id  = $admin_id["id"];
        $certName = str_replace("/", "_", $certName);

        $get_config_data = Config::select('configuration')->first();
     
        $c = explode(", ", $get_config_data['configuration']);
        $key = "";


        $tempDir = public_path().'/backend/qr';
        $key = strtoupper(md5($serial_no)); 
        $codeContents = $key;
        $fileName = $key.'.png'; 
        
        $urlRelativeFilePath = 'qr/'.$fileName; 

        if($systemConfig['sandboxing'] == 1){
        $resultu = SbStudentTable::where('serial_no','T-'.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = SbStudentTable::create(['serial_no'=>'T-'.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }else{
        $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>2]);
        }
        
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
        // dd($sr_no);
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
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>'T-'.$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }else{
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);    
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
        // dd($current_year . $maxNum);
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
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result = \DB::select("SELECT * FROM sb_card_serial_numbers WHERE template_name = '$template_name'");
        }else{
        $result = \DB::select("SELECT * FROM card_serial_numbers WHERE template_name = '$template_name'");
        }
          
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
        // print_r($path);
        // dd($tmp);
        $tmpname = tempnam($path, $tmp);
        //unlink($tmpname);
        //mkdir($tmpname);
        if (file_exists($tmpname)) {
         unlink($tmpname);
        }
        mkdir($tmpname, 0777);
        return $tmpname;
    }

    public function CreateMessage($tmpDir, $name = "",$font_size,$print_color)
    {
        if($name == "")
            return;
        $name = strtoupper($name);
        // Create character image
        if($font_size == 15 || $font_size == "15"){


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
                "Z" => array(20842, 792)
            
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
                "Z" => array(17710, 680)
                
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
                "Z" => array(20867, 848)
            
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

        }else if($font_size == "13" || $font_size == 13){

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

                
            //$filename = public_path()."/backend/canvas/ghost_images/F13_H10_W360.png";
            $filename ='http://'.$_SERVER['HTTP_HOST']."/backend/canvas/ghost_images/F13_H10_W360.png";
             $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
            //$filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";
            $filename ='http://'.$_SERVER['HTTP_HOST']."/backend/canvas/ghost_images/alpha_GHOST.png";
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

        }else if($font_size == "14" || $font_size == 14){

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
            // print_r($w);echo "<pre>";
        }
        // print_r($w);
        // dd($sum);
        // exit();
        $ret = array();
        $ret[0] = (205 - $sum)/2;
        for($i = 1; $i < $len; $i++)
        {
            $ret[$i] = $ret[$i - 1] + $w[$i - 1] ;
            // print_r($ret);echo "<pre>";
        }
        // exit();
        return $ret;
    }

    function sanitizeQrString($content){
         $find = array('â€œ', 'â€™', 'â€¦', 'â€”', 'â€“', 'â€˜', 'Ã©', 'Â', 'â€¢', 'Ëœ', 'â€'); // en dash
         $replace = array('“', '’', '…', '—', '–', '‘', 'é', '', '•', '˜', '”');
        return $content = str_replace($find, $replace, $content);
    }

  
}
