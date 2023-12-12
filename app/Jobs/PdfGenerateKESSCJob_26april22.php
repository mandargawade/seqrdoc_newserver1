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
class PdfGenerateKESSCJob
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
        $template_id=$pdf_data['template_id'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];

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
        $pdfBig->SetTitle('Certificate');
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
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrow = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arialn.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);


        $log_serial_no = 1;
        $cardDetails=$this->getNextCardNo('KESSC-GC');
        $card_serial_no=$cardDetails->next_serial_no;
        //For custom loader
        $generated_documents=0;  
        foreach ($studentDataOrg as $studentData) {

        //For Custom Loader
        $startTimeLoader =  date('Y-m-d H:i:s');
         
         if($card_serial_no>999999&&$previewPdf!=1){
            echo "<h5>Your card series ended...!</h5>";
            exit;
         }
          
         $pdfBig->AddPage();
         $pdfBig->SetFont($arialNarrowB, '', 8, '', false);
        //set background image
          $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\KESSC_Shroff_grade_BG.jpg';   

        if($previewPdf==1){
            if($previewWithoutBg!=1){
                $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
            }

        $date_font_size = '11';
        $date_nox = 13;
        $date_noy = 40;
        $date_nostr = 'DRAFT '.date('d-m-Y H:i:s');
        $pdfBig->SetFont($arialb, '', $date_font_size, '', false);
        $pdfBig->SetTextColor(192,192,192);
        $pdfBig->SetXY($date_nox, $date_noy);
        $pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetFont($arialNarrowB, '', 9, '', false);
        }
        $pdfBig->setPageMark();

        $ghostImgArr = array();
        $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
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


        $pdf->AddPage();
        
         $print_serial_no = $this->nextPrintSerial();
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\KESSC_Shroff_grade_BG_Low_Res.jpg';
        
        $profile_path_org = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[141];

        $path_info = pathinfo($profile_path_org);                            
        $file_name = $path_info['filename'];
        $ext = $path_info['extension'];
        $bw_location = public_path()."/".$subdomain[0]."/backend/templates/100/".$file_name.'_bw.'.$ext;
        
        if(!file_exists($bw_location)){  
            copy($profile_path_org, $bw_location);
        }

        if($previewPdf!=1){
        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        }
        $pdf->setPageMark();

        if($previewPdf!=1){

            $x= 175.5;
        $y = 39.5;
        $font_size=11;
        $str = str_pad($card_serial_no, 6, '0', STR_PAD_LEFT);;
        $strArr = str_split($str);
                   $x_org=$x;
                   $y_org=$y;
                   $font_size_org=$font_size;
                   $i =0;
                   $j=0;
                   $y=$y+4.5;
                   $z=0;
                   foreach ($strArr as $character) {
                        $pdf->SetFont($arialNarrowB,0, $font_size, '', false);
                        $pdf->SetXY($x, $y+$z);

                        $pdfBig->SetFont($arialNarrowB,0, $font_size, '', false);
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
                       $pdf->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');
                       $pdfBig->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');
                        $i++;
                        $x=$x+2.2+$j; 
                        if($i>2){
                           
                         $font_size=$font_size+1.7;   
                        }
                   }

        }
        $pdf->SetFont($arialNarrowB, '', 9, '', false);
        $pdfBig->SetFont($arialNarrowB, '', 9, '', false);

       
        $html = <<<EOD
<style>
 td{
 
  font-size:9px; 
 border-top: 0px solid black;
    border-bottom: 0px solid black;
}
#table1 {
    
  border-collapse: collapse;
}
</style>
<table id="table1" cellspacing="0" cellpadding="2" border="0.1" width="86%" rules="rows">
   <tr>
    <td colspan="2"> Name of the Learner :  {$studentData[1]}</td>
   </tr>
   <tr>
    <td style="width:12%;"> Programme :</td>
    <td style="width:88%;">{$studentData[2]}</td>
   </tr>
   <tr>
    <td style="width:50%;"> Semester : {$studentData[3]}</td>
    <td style="width:50%;"> Month and Year of Examination : {$studentData[6]}</td>
   </tr>
   <tr>
    <td style="width:50%;"> Roll No.: {$studentData[4]}</td>
    <td style="width:50%;"> PRN/Reg.No.: {$studentData[7]}</td>
   </tr>
   <tr>
    <td style="width:50%;"> Seat No.: {$studentData[5]}</td>
    <td style="width:50%;"> UID No.: {$studentData[8]}</td>
   </tr>
  </table>
EOD;


$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='51.1', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y='51.1', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);



// define style for border
//$border_style = array('all' => array('width' => 2, 'cap' => 'square', 'join' => 'miter', 'dash' => 0, 'phase' => 0));
//invisible data
        
        $invisible_font_size = '8';
        $invisible_namex = 110;
        $invisible_namey = 52.3;
        $invisible_namestr = $studentData[1];
       /* $pdfBig->SetOverprint(true, true, 0); Spot Light Yellow
        $pdfBig->SetTextSpotColor('Spot Red', 100);*/
        //$pdfBig->SetTextColor(255,255,0,100, false, '');
       // $pdfBig->SetTextColor(0, 0, 100, 0);
        $pdfBig->SetTextColor(255,255,0);
        $pdfBig->SetFont($arialNarrowB, '', $invisible_font_size, '', false);
        $pdfBig->SetXY($invisible_namex, $invisible_namey);
        $pdfBig->Cell(0, 0, $invisible_namestr, 0, false, 'L');

        /*$pdfBig->SetDrawColor(0, 0, 50, 0);
$pdfBig->SetFillColor(0, 0, 100, 0);
$pdfBig->SetTextColor(0, 0, 100, 0);
$pdfBig->Rect(110, 60, 30, 30, 'DF', $border_style);
$pdfBig->Text(110, 92, 'Yellow');*/


        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetFont($arialNarrowB, '', 9, '', false);

    //set profile image

   /* imagefilter($profile_path, IMG_FILTER_GRAYSCALE);
    imagefilter($profile_path, IMG_FILTER_CONTRAST, -1000);*/
   
    
    

    if($ext == 'png'){

        $im = imagecreatefrompng($bw_location);

        if($im && imagefilter($im, IMG_FILTER_GRAYSCALE))
        {
          
            imagepng($im, $bw_location);
            imagedestroy($im);
        }
        
    }else if($ext == 'jpeg' || $ext == 'jpg'){

        $im = imagecreatefromjpeg($bw_location);
       
        if($im && imagefilter($im, IMG_FILTER_GRAYSCALE))
        {
          
            imagejpeg($im, $bw_location);
            imagedestroy($im);
        }
        
    }
    $profilex = 174.5;
    $profiley = 51.3;
    $profileWidth = 23;
    $profileHeight = 26;
    $pdf->image($bw_location,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
    $pdfBig->image($bw_location,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

    $subjectData = array_slice($studentData, 9, 84);
    $subjectsArr=array_chunk($subjectData, 12);
     
    $strtd="";
    $lineCount=0;
    $inches=0;
    for ($i=0; $i <7 ; $i++) { 

        
        if(!empty($subjectsArr[$i][0])){
        	if(strlen($subjectsArr[$i][1])>31){
            $lineCount=$lineCount+1.8;
           // 0.21  0.37  4.3
            $inches=$inches+0.39;
        }else{
            $lineCount=$lineCount+1;
            $inches=$inches+0.23;
        }
            $strtd .= '<tr>
            <td style="width:11%;text-align:center;"><b>'.$subjectsArr[$i][0].'</b></td>
            <td style="width:29%;text-align:left;"><b>'.$subjectsArr[$i][1].'</b></td>
            <td style="width:6%;text-align:center;"><b>'.$subjectsArr[$i][2].'</b></td>
            <td style="width:7%;text-align:center;"><b>'.$subjectsArr[$i][3].'</b></td>
            <td style="width:6%;text-align:center;"><b>'.$subjectsArr[$i][4].'</b></td>
            <td style="width:7%;text-align:center;"><b>'.$subjectsArr[$i][5].'</b></td>
            <td style="width:5%;text-align:center;"><b>'.$subjectsArr[$i][6].'</b></td>
            <td style="width:5%;text-align:center;"><b>'.$subjectsArr[$i][7].'</b></td>
            <td style="width:6%;text-align:center;"><b>'.$subjectsArr[$i][8].'</b></td>
            <td style="width:6%;text-align:center;"><b>'.$subjectsArr[$i][9].'</b></td>
            <td style="width:6%;text-align:center;"><b>'.$subjectsArr[$i][10].'</b></td>
            <td style="width:6%;text-align:center;"><b>'.$subjectsArr[$i][11].'</b></td>
           </tr>';
        }
    }

    /*if($lineCount<18){
        $lineCount=round($lineCount);
        for ($i=$lineCount; $i <17 ; $i++) { 
            $strtd .= '<tr>
            <td style="width:11%;text-align:center;"><b></b></td>
            <td style="width:29%;text-align:left;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:7%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:7%;text-align:center;"><b></b></td>
            <td style="width:5%;text-align:center;"><b></b></td>
            <td style="width:5%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
           </tr>';
        }
    }*/


    while($inches<3.6){//3.83

    	$strtd .= '<tr>
            <td style="width:11%;text-align:center;"><b></b></td>
            <td style="width:29%;text-align:left;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:7%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:7%;text-align:center;"><b></b></td>
            <td style="width:5%;text-align:center;"><b></b></td>
            <td style="width:5%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
           </tr>';
           $inches=$inches+0.23;
    }
    $strtdBig=$strtd;
    /*$strtd .= '<tr>
            <td style="width:11%;text-align:center;"><b></b></td>
            <td style="width:29%;text-align:left;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:7%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:7%;text-align:center;"><b></b></td>
            <td style="width:5%;text-align:center;"><b></b></td>
            <td style="width:5%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
            <td style="width:6%;text-align:center;"><b></b></td>
           </tr>';*/

    $strtd .= '<tr>
    <th style="width:11%;text-align:center;"></th>
    <th style="width:29%;text-align:center;">Total</th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[93].'<br></b></th>
    <th style="width:7%;text-align:center;"><b>'.$studentData[94].'<br></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[95].'<br></b></th>
    <th style="width:7%;text-align:center;"><b>'.$studentData[96].'<br></b></th>
    <th style="width:5%;text-align:center;"><b>'.$studentData[97].'<br></b></th>
    <th style="width:5%;text-align:center;"><b></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[98].'<br></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[99].'<br></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[100].'<br></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[101].'<br></b></th>
   </tr>';

    $strtdBig .= '<tr>
    <th style="width:11%;text-align:center;"></th>
    <th style="width:29%;text-align:center;">Total</th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[93].'<br><span style="color:yellow;">'.$studentData[93].'</span></b></th>
    <th style="width:7%;text-align:center;"><b>'.$studentData[94].'<br><span style="color:yellow;">'.$studentData[94].'</span></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[95].'<br><span style="color:yellow;">'.$studentData[95].'</span></b></th>
    <th style="width:7%;text-align:center;"><b>'.$studentData[96].'<br><span style="color:yellow;">'.$studentData[96].'</span></b></th>
    <th style="width:5%;text-align:center;"><b>'.$studentData[97].'<br><span style="color:yellow;">'.$studentData[97].'</span></b></th>
    <th style="width:5%;text-align:center;"><b></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[98].'<br><span style="color:yellow;">'.$studentData[98].'</span></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[99].'<br><span style="color:yellow;">'.$studentData[99].'</span></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[100].'<br><span style="color:yellow;">'.$studentData[100].'</span></b></th>
    <th style="width:6%;text-align:center;"><b>'.$studentData[101].'<br><span style="color:yellow;">'.$studentData[101].'</span></b></th>
   </tr>';
    $html = <<<EOD
<style>
 th{

  font-size:9px; 
}
 td{

  font-size:9px;
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
<table id="table2" class="t-table borderspace" cellspacing="0" cellpadding="2" border="0.1" width="99%">
  <tr>
    <th style="width:11%;text-align:center;">Course Code</th>
    <th style="width:29%;text-align:center;">Course Title</th>
    <th style="width:6%;text-align:center;">Internal <span style="font-size:8px;">Min: 16 Max: 40</span></th>
    <th style="width:7%;text-align:center;">Practical <span style="font-size:8px;">Min: 20 Max: 50</span></th>
    <th style="width:6%;text-align:center;">Theory <span style="font-size:8px;">Min: 24 Max: 60</span></th>
    <th style="width:7%;text-align:center;">Overall Marks</th>
    <th style="width:5%;text-align:center;">Max. Marks</th>
    <th style="width:5%;text-align:center;">Grade</th>
    <th style="width:6%;text-align:center;">Grade Points (G)</th>
    <th style="width:6%;text-align:center;">Course Credit (C)</th>
    <th style="width:6%;text-align:center;">(C X G)</th>
    <th style="width:6%;text-align:center;">SGPA = ΣCG / ΣC</th>
   </tr>
   
    {$strtd}
  </table>
EOD;

$htmlBig = <<<EOD
<style>
 th{

  font-size:9px; 
}
 td{

  font-size:9px;
   border-left: 0px solid black;
    border-right: 0px solid black; 
}
#table6 {
  
    height: 400px;
  
  border-collapse: collapse;
}
#table6 td:empty {
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
<table id="table6" class="t-table borderspace" cellspacing="0" cellpadding="2" border="0.1" width="99%">
  <tr>
    <th style="width:11%;text-align:center;">Course Code</th>
    <th style="width:29%;text-align:center;">Course Title</th>
    <th style="width:6%;text-align:center;">Internal <span style="font-size:8px;">Min: 16 Max: 40</span></th>
    <th style="width:7%;text-align:center;">Practical <span style="font-size:8px;">Min: 20 Max: 50</span></th>
    <th style="width:6%;text-align:center;">Theory <span style="font-size:8px;">Min: 24 Max: 60</span></th>
    <th style="width:7%;text-align:center;">Overall Marks</th>
    <th style="width:5%;text-align:center;">Max. Marks</th>
    <th style="width:5%;text-align:center;">Grade</th>
    <th style="width:6%;text-align:center;">Grade Points (G)</th>
    <th style="width:6%;text-align:center;">Course Credit (C)</th>
    <th style="width:6%;text-align:center;">(C X G)</th>
    <th style="width:6%;text-align:center;">SGPA = ΣCG / ΣC</th>
   </tr>
   
    {$strtdBig}
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='79', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y='79', $htmlBig, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
        

     $html = <<<EOD
<style>

th{

  font-size:9px;
   border-top: 0px solid black;
    border-bottom: 0px solid black; 
}
 td{

  font-size:9px;
   
}
#table4 {
  
    height: 400px;
  
  border-collapse: collapse;
}


</style>
<table id="table4" cellspacing="0" cellpadding="2" border="0.1" width="99%">
    <tr>
    <th colspan="2" style="width:20%;text-align:left;">Remark : {$studentData[102]}</th>
    <th style="width:16%;text-align:left;">Grade : {$studentData[103]}</th>
    <th style="width:16%;text-align:left;">Credit Earned : {$studentData[104]}</th>
    <th style="width:16%;text-align:left;">SGPA : {$studentData[105]}</th>
    <th style="width:16%;text-align:left;">CGPA : {$studentData[106]}</th>
    <th style="width:16%;text-align:left;">Overall : {$studentData[107]}</th>
    </tr>
    <tr>
      <td style="width:16%;text-align:left;">Credit Earned</td>
    <td style="width:14%;text-align:left;">Sem I : <b>{$studentData[108]}</b></td>
    <td style="width:14%;text-align:left;">Sem II : <b>{$studentData[109]}</b></td>
    <td style="width:14%;text-align:left;">Sem III : <b>{$studentData[110]}</b></td>
    <td style="width:14%;text-align:left;">Sem IV : <b>{$studentData[111]}</b></td>
    <td style="width:14%;text-align:left;">Sem V : <b>{$studentData[112]}</b></td>
    <td style="width:14%;text-align:left;">Sem VI: <b>{$studentData[113]}</b></td>
    </tr>
    <tr>
      <td style="width:16%;text-align:left;">SGPA</td>
    <td style="width:14%;text-align:left;">Sem I : <b>{$studentData[114]}</b></td>
    <td style="width:14%;text-align:left;">Sem II : <b>{$studentData[115]}</b></td>
    <td style="width:14%;text-align:left;">Sem III : <b>{$studentData[116]}</b></td>
    <td style="width:14%;text-align:left;">Sem IV : <b>{$studentData[117]}</b></td>
    <td style="width:14%;text-align:left;">Sem V : <b>{$studentData[118]}</b></td>
    <td style="width:14%;text-align:left;">Sem VI: <b>{$studentData[119]}</b></td>
    </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='194.7', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y='194.7', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
     $semArr=array();
     $semArr[0]=array("Sem I","Sem II","Sem III","Sem IV","Sem V","Sem VI");
     $semArr[1]=array($studentData[133],$studentData[134],$studentData[135],$studentData[136],$studentData[137],$studentData[138]);

    $z=0;
    $sr='';
    $strtd='';
    if(!empty($studentData[120])){
        $sr=$z=$z+1;
        $strtd .='<tr>
    <td style="width:9%;text-align:center;"><b>'.$z.'</b></td>
    <td colspan="2" style="width:54%;text-align:left;">'.$studentData[120].'</td>
    <td style="width:12%;text-align:center;">'.$studentData[121].'</td>
    <td style="width:12%;text-align:center;">'.$semArr[0][$z-1].'</td>
    <td style="width:13%;text-align:center;">'.$semArr[1][$z-1].'</td>
    </tr>';
    }
    
    if(!empty($studentData[122])){
        $sr=$z=$z+1;
    
   $strtd .='<tr>
    <td style="width:9%;text-align:center;"><b>'.$z.'</b></td>
    <td colspan="2" style="width:54%;text-align:left;">'.$studentData[122].'</td>
    <td style="width:12%;text-align:center;">'.$studentData[123].'</td>
    <td style="width:12%;text-align:center;">'.$semArr[0][$z-1].'</td>
    <td style="width:13%;text-align:center;">'.$semArr[1][$z-1].'</td>
    </tr>'; 
    }
    if(!empty($studentData[124])){
        $sr=$z=$z+1;
    
    $strtd .='<tr>
    <td style="width:9%;text-align:center;">'.$z.'</td>
    <td colspan="2" style="width:54%;text-align:left;">'.$studentData[124].'</td>
    <td style="width:12%;text-align:center;">'.$studentData[125].'</td>
    <td style="width:12%;text-align:center;">'.$semArr[0][$z-1].'</td>
    <td style="width:13%;text-align:center;">'.$semArr[1][$z-1].'</td>
    </tr>';
    }
    if(!empty($studentData[126])){
        $sr=$z=$z+1;
    
    $strtd .='<tr>
    <td style="width:9%;text-align:center;">'.$z.'</td>
    <td colspan="2" style="width:54%;text-align:left;">'.$studentData[126].'</td>
    <td style="width:12%;text-align:center;">'.$studentData[127].'</td>
    <td style="width:12%;text-align:center;">'.$semArr[0][$z-1].'</td>
    <td style="width:13%;text-align:center;">'.$semArr[1][$z-1].'</td>
    </tr>';
    }

    if(!empty($studentData[128])){
        $sr=$z=$z+1;
    
    $strtd .='<tr>
    <td style="width:9%;text-align:center;">'.$z.'</td>
    <td colspan="2" style="width:54%;text-align:left;">'.$studentData[128].'</td>
    <td style="width:12%;text-align:center;">'.$studentData[129].'</td>
    <td style="width:12%;text-align:center;">'.$semArr[0][$z-1].'</td>
    <td style="width:13%;text-align:center;">'.$semArr[1][$z-1].'</td>
    </tr>';
    }

    if(!empty($studentData[130])){
        $sr=$z=$z+1;
    
    $strtd .='<tr>
    <td style="width:9%;text-align:center;">'.$z.'</td>
    <td colspan="2" style="width:54%;text-align:left;">'.$studentData[130].'</td>
    <td style="width:12%;text-align:center;">'.$studentData[131].'</td>
    <td style="width:12%;text-align:center;">'.$semArr[0][$z-1].'</td>
    <td style="width:13%;text-align:center;">'.$semArr[1][$z-1].'</td>
    </tr>';
    }
    if($z<=4){

        for ($p=$z; $p <5 ; $p++) { 
            $z=$z+1;
             $strtd .='<tr>
                    <td style="width:9%;text-align:center;"></td>
                    <td colspan="2" style="width:54%;text-align:left;"></td>
                    <td style="width:12%;text-align:center;"></td>
                    <td style="width:12%;text-align:center;">'.$semArr[0][$z-1].'</td>
                    <td style="width:13%;text-align:center;">'.$semArr[1][$z-1].'</td>
                    </tr>';

        }
        $z=6;
        $strtd .='<tr>
    <th style="width:20%;text-align:left;" class="no-border-right">Place : '.$studentData[139].'</th>
    <th style="width:31%;text-align:center;" class="no-border-right">Date : '.$studentData[140].'</th>
    <th style="width:12%;text-align:center;">Total Credit</th>
    <th style="width:12%;text-align:center;">'.$studentData[132].'</th>
    <td style="width:12%;text-align:center;">'.$semArr[0][$z-1].'</td>
    <td style="width:13%;text-align:center;">'.$semArr[1][$z-1].'</td>
    </tr>';

    }else if($z==5){
        $z=6;
        $strtd .='<tr>
    <th style="width:20%;text-align:left;" class="no-border-right">Place : '.$studentData[139].'</th>
    <th style="width:31%;text-align:center;" class="no-border-right">Date : '.$studentData[140].'</th>
    <th style="width:12%;text-align:center;">Total Credit</th>
    <th style="width:12%;text-align:center;">'.$studentData[132].'</th>
    <td style="width:12%;text-align:center;">'.$semArr[0][$z-1].'</td>
    <td style="width:13%;text-align:center;">'.$semArr[1][$z-1].'</td>
    </tr>';
    }else{
        if(!empty($studentData[140])){
            $dateCert=date('d/m/Y',strtotime($studentData[140]));
        }else{
            $dateCert='';
        }
        
        $strtd .='<tr>
    <th style="width:20%;text-align:left;" class="no-border-right">Place : '.$studentData[139].'</th>
    <th style="width:31%;text-align:center;" class="no-border-right">Date : '.$dateCert.'</th>
    <th style="width:12%;text-align:center;">Total Credit</th>
    <th style="width:12%;text-align:center;">'.$studentData[132].'</th>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:13%;text-align:center;"></td>
    </tr>';
    }
    

     $html = <<<EOD
<style>
 

th{

  font-size:9px; 
}
 td{

  font-size:9px;
   border-left: 0px solid black;
    border-right: 0px solid black; 
}
#table5 {
  
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
  
  .no-border-right {
     border-top: 0px solid black;
    border-bottom: 0px solid black; 
  }
</style>
<table id="table5" cellspacing="0" cellpadding="2" border="0.1" width="99%">
    <tr>
    <th style="width:9%;text-align:center;">Sr.</th>
    <th colspan="2" style="width:54%;text-align:center;">Additional Credit Courses</th>
    <th style="width:12%;text-align:center;">Course Credit</th>
    <th colspan="2" style="width:25%;text-align:center;">Attendance in (Grade / %)</th>
    </tr>
    
    {$strtd}
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='213', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y='213', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

// Ghost image
        $nameOrg=$studentData[1];
        $ghost_font_size = '13';
        $ghostImagex = 136;
        $ghostImagey = 275.5;
        $ghostImageWidth = 39.405983333;//68//55
        $ghostImageHeight = 10;//9.8
        $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
        $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);

        //sign
        $sign_path_control = public_path().'\\'.$subdomain[0].'\backend\canvas\images\Controller_Sign.png';
        $sign_x_control = 20;
        $sign_y_control = 263;
        $sign_Width_control = 21;
        $sign_Height_control = 16;
        $pdf->image($sign_path_control,$sign_x_control,$sign_y_control,$sign_Width_control,$sign_Height_control,"",'','L',true,3600);
        $pdfBig->image($sign_path_control,$sign_x_control,$sign_y_control,$sign_Width_control,$sign_Height_control,"",'','L',true,3600);

        $sign_path_princ = public_path().'\\'.$subdomain[0].'\backend\canvas\images\Principal Sign.png';
        $sign_x_princ = 102;
        $sign_y_princ = 265;
        $sign_Width_princ = 21;
        $sign_Height_princ = 16;
        $pdf->image($sign_path_princ,$sign_x_princ,$sign_y_princ,$sign_Width_princ,$sign_Height_princ,"",'','L',true,3600);
        $pdfBig->image($sign_path_princ,$sign_x_princ,$sign_y_princ,$sign_Width_princ,$sign_Height_princ,"",'','L',true,3600);
        

        $serial_no=$GUID=$studentData[0];
        //qr code    
        $dt = date("_ymdHis");
        $str=$GUID.$dt;
        $codeContents =$encryptedString = strtoupper(md5($str));
        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 180;
        $qrCodey = 254;
        $qrCodeWidth =19.3;
        $qrCodeHeight = 19.3;
        \QrCode::size(75.6)
            ->backgroundColor(255, 255, 0)
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
        $pdf->SetFont($arialb, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(180, 271.5);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

        $pdfBig->SetFont($arialb, '', 2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->StartTransform();
        $pdfBig->SetXY(180, 271.5);
        $pdfBig->Cell(0, 0, $microlinestr, 0, false, 'C');


        if($previewPdf!=1){

         $certName = str_replace("/", "_", $GUID) .".pdf";
            
            $myPath = public_path().'/backend/temp_pdf_file';

            $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;

            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

             $this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id);

            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            $print_datetime = date("Y-m-d H:i:s");
            

            $print_count = $this->getPrintCount($serial_no);
            $printer_name = /*'HP 1020';*/$printer_name;

            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'BMCC-GC',$admin_id,$card_serial_no);

            $card_serial_no=$card_serial_no+1;
            }

             $generated_documents++;

              if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
                $updated=date('Y-m-d H:i:s');
                ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['generated_documents'=>$generated_documents,"updated_at"=>$updated]);
            }else{


            //For Custom loader calculation
            $endTimeLoader = date('Y-m-d H:i:s');
            $time1 = new \DateTime($startTimeLoader);
            $time2 = new \DateTime($endTimeLoader);
            $interval = $time1->diff($time2);
            $interval = $interval->format('%s');

            $jsonArr=array();
            $jsonArr['token'] = $pdf_data['loader_token'];
            $jsonArr['generatedCertificates'] =$generated_documents;
            $jsonArr['timePerCertificate'] =$interval;
           
            $loaderData=CoreHelper::createLoaderJson($jsonArr,0);

            
            }
       } 

       if($previewPdf!=1){
        $this->updateCardNo('KESSC-GC',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
       }
       $msg = '';
        
        $file_name =  str_replace("/", "_",'KESSC-GC'.date("Ymdhms")).'.pdf';
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


       $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        if($previewPdf!=1){
     

            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="KESSC-GC";
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

    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id)
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

        copy($file1, $file2);
        
        $aws_qr = \File::copy($file2,$pdfActualPath);
            
        @unlink($file2);

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
       
        $tmpname = tempnam($path, $tmp);
        unlink($tmpname);
        mkdir($tmpname);
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

            $filename ='http://'.$_SERVER['HTTP_HOST']."/backend/canvas/ghost_images/F13_H10_W360.png";
             $charsImage = imagecreatefrompng($filename);
            $size = getimagesize($filename);
            // Create Backgoround image
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

  
}
