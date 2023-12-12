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
class PdfGenerateUASBUGPGJob
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
        $template_type=$pdf_data['template_type'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];

        $template_data =array("id"=>100,"template_name"=>"Degree Certificate");
        $fetch_degree_data =$studentDataOrg;
     
        $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

     

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
     
        $printer_name = $systemConfig['printer_name'];

        $ghostImgArr = array();
        //set fonts
        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);

       $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewroman.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromanb.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromanbi.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromani.TTF', 'TrueTypeUnicode', '', 96);

        $nudi01e = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e.TTF', 'TrueTypeUnicode', '', 96);
        $nudi01eb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e b.TTF', 'TrueTypeUnicode', '', 96);

        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);
        $log_serial_no = 1;

        //print_r($fetch_degree_data);
        for($excel_row = 0; $excel_row < count($fetch_degree_data); $excel_row++)
        {  
         $serial_no = trim($fetch_degree_data[$excel_row][0]); //sr_no

            $path=public_path().'/'.$subdomain[0].'/backend/DC_Photo/';
             $file =trim($fetch_degree_data[$excel_row][1]); //id_number
            $file0 = str_replace("/", " ", $fetch_degree_data[$excel_row][1]);
            $file1 = str_replace(" ","",$fetch_degree_data[$excel_row][1]);
            $file2 = $fetch_degree_data[$excel_row][1];
           if(file_exists($path.$file.".jpg")){
                $profile_path =$path.$file.".jpg";
            }elseif(file_exists($path.$file.".jpeg")){
                $profile_path =$path.$file.".jpeg";
            }elseif(file_exists($path.$file0.".jpg")){
                $profile_path =$path.$file0.".jpg";
            }elseif(file_exists($path.$file0.".jpeg")){
                $profile_path =$path.$file0.".jpeg";
            }elseif(file_exists($path.$file1.".jpg")){
                $profile_path =$path.$file1.".jpg";
            }elseif(file_exists($path.$file1.".jpeg")){
                $profile_path =$path.$file1.".jpeg";
            }elseif(file_exists($path.$file2.".jpg")){
                $profile_path =$path.$file2.".jpg";
            }elseif(file_exists($path.$file2.".jpeg")){
                $profile_path =$path.$file2.".jpeg";
            }else{
                $profile_path ='';
                
            }

           

            $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

            $pdf->SetCreator('TCPDF');
            $pdf->SetAuthor('TCPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');

            // remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45);


            $pdfBig->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
            $pdfBig->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo

            $pdf->AddPage();
            
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\UASB 2020 CONV certificate_Background.jpg';
           
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

           
            $pdfBig->AddPage();
            
            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');


            $pdf->SetTextColor(0, 0, 0, 100, false, '');
                      

            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');
            if($previewPdf==1){
                if($previewWithoutBg!=1){
                $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
                }
                 $date_font_size = '11';
                $date_nox = 40;
                $date_noy = 30;
                $date_nostr = 'PREVIEW '.date('d-m-Y H:i:s');
                $pdfBig->SetFont($arialb, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(192,192,192);
                $pdfBig->SetXY($date_nox, $date_noy);
                $pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetFont($arial, '', 8, '', false);

            }
            $pdfBig->setPageMark();

        
        $profilex = 181.5;
        $profiley = 8.3;
        $profileWidth = 19.20;
        $profileHeight = 24;
        
        if(!empty($profile_path)){
            $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
        $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);    
        }
        

        //set serial No
      $serial_no_split = (string)($serial_no);
       $strArr = str_split($serial_no_split);
       $x_org=$x=16;
       $y_org=$y=37.5;
       $font_size=10;
       $width=100;
       $font_size_org=$font_size;
       $i =0;
       $j=0;
       $y=$y+4.5;
       $z=0;
       $serial_font_size = 10;
       foreach ($strArr as $character) {
            if($character=="-"){
                $x=$x+1;
            }
        
            $pdf->SetFont($arial,'', $font_size, '', false);
            $pdf->SetXY($x, $y+$z);
            
            $pdfBig->SetFont($arial,'', $font_size, '', false);
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

            $pdf->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
            $pdfBig->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
            if($character=="-"){
                $x=$x+0.5;
            }
            $i++;
            if($i==1){
                $x=$x+2+$j;
            }else{
                $x=$x+1.8+$j;
            }
             
            if($i>2){
               
             $font_size=$font_size+1.4;   
            }
       }

         //set enrollment no
        $enrollment_font_size = '9';
        $enrollmentx= 183.2;
        if(strpos($fetch_degree_data[$excel_row][1], '/') !== false){
            $enrollmentx=172;
        }
        $enrollmenty = 37.5;
        $enrollmentstr = $fetch_degree_data[$excel_row][1];
        $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdf->SetXY($enrollmentx, $enrollmenty);
        $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');

        $pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdfBig->SetXY($enrollmentx, $enrollmenty);
        $pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');

        if(!isset($fetch_degree_data[$excel_row][4])){
            $fetch_degree_data[$excel_row][4]="GOLD";
        }
       
        //UG
        if(trim($fetch_degree_data[$excel_row][4])=="Under Graduate"){
            
        $minus=0;
        if(trim($fetch_degree_data[$excel_row][10])=="Bachelor of Technology (Agricultural Engineering)"){
            $minus=1.5;
        }
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2=$fetch_degree_data[$excel_row][9];
        $str3=" ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ".$str2." ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";
        $z=0;
        if(strlen($str)>145){

            $strNew=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";

            if(strlen($strNew)<=290){

                $strArr = explode( "\n", wordwrap( $strNew, 145));

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

                $strY=57-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');
            }else{

                $strNew=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ";
                $strArr = explode( "\n", wordwrap( $strNew, 145));

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

                $strY=57-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');


                $str="¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
                if($minus>0){
                    $minus=$minus+1.5;
                }
                
                $strY=67-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $str, 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $str, 0, false, 'C');

                $z=10;
            }


        }else{
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 57-$minus;
            $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');
        }


        

        $strFontB = '30';
        $strFont = '18';
        $strY = 64-$minus;
        $str1=$fetch_degree_data[$excel_row][3]." ";
        $str2="CªÀgÀ£ÀÄß";
       
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );
            

        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY($result[0], $strY+$z);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY($result[0], $strY+$z);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3+$z);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY+3+$z);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
        
        
        if(trim($fetch_degree_data[$excel_row][10])!="Bachelor of Science (Sericulture)"&&trim($fetch_degree_data[$excel_row][10])!="Bachelor of Science (Agriculture)"&&trim($fetch_degree_data[$excel_row][10])!="Bachelor of Science (Agricultural Biotechnology)"){
        $strFont = '23';
        $strX = 55;
        $strY = 75+1.2-$minus;
        $str=trim($fetch_degree_data[$excel_row][11]);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY+$z);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY+$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }

        if($minus>0){
            $minus=$minus+1;
        }

        if($z==0){

            if(trim($fetch_degree_data[$excel_row][10])=="Bachelor of Science (Sericulture)"||trim($fetch_degree_data[$excel_row][10])=="Bachelor of Science (Agriculture)"||trim($fetch_degree_data[$excel_row][10])=="Bachelor of Science (Agricultural Biotechnology)"){
            
        $strFont = '18';
        $strFontB = '23';
        $strX = 55;
        $strY = 75+1.2-$minus;
        $str1=trim($fetch_degree_data[$excel_row][11])." ";
        $str2="¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
       
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );
            

        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strY+$z);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strY+$z);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+$z+1);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY+$z+1);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

        $minusConcat=10;
            }else{
            $strFont = '18';
            $strX = 85.1;
            $strY = 85.3+1.2-$minus;
            $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $minusConcat=0;
        }

        $strFont = '18';
        $strX = 85.1;
        $strY = 95.3+1.2-$minus-$minusConcat;
        $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 105.3+1.2-$minus-$minusConcat;
        $str = "ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ 2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 115.3+1.2-$minus-$minusConcat;
        $str = "28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    }else{

        

        $strFont = '18';
        $strX = 85.1;
        $strY = 95.3+1.2-$minus;
        $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        if($minus>0){
           $minus=$minus+2; 
        }

        $strFont = '18';
        $strX = 85.1;
        $strY = 95.3+1.2-$minus+$z;
        
        $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        if($minus>0){
           $minus=$minus+1.5; 
        }

        $strFont = '18';
        $strX = 85.1;
        $strY = 105.3+1.2-$minus+$z;
        $str = "UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        if($minus>0){
           $minus=$minus+1.5; 
        }

        $strFont = '18';
        $strX = 85.1;
        $strY = 115.3+1.2-$minus+$z;
        $str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');
    }

        $strFont = '19';
        $strX = 55;
        $strY = 155.5;
        $str = "By authority of the Board of Management and upon recommendation";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');
        $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        
        $str1="of the Board of Studies, Under Graduate, Faculty of ";
        $str2=$fetch_degree_data[$excel_row][8];
        $str = "of the Board of Studies, Under Graduate, Faculty of ".$str2;

        if(strlen($str)>65){

            $str=$str." "."and the Academic Council";
            $strArr = explode( "\n", wordwrap( $str, 65));

            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');

        }else{
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '19';
            $strX = 55;
            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');
        }

        $strFont = '22';//19
        $strX = 55;
        $strY = 180.7;
        $str = $fetch_degree_data[$excel_row][2];
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 188.7;
        $str = "has been admitted to the degree of";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 196.7;
        $str = $fetch_degree_data[$excel_row][10];
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 204.7;
        $str = "and is entitled to all rights and honours thereto appertaining";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 212.7;
        $str = "witness the seal of the University and the signatures of its";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 220.7;
        $str = "Officers this 28  day of November 2020";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '13';
        $strX = 55;
        $strY = 219;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(92.7, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(92.7, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');
        }else if(trim($fetch_degree_data[$excel_row][4])=="Post Graduate"){
        

            //Template 04
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ PÀÈ¶ «eÁÕ£À ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 57;
        $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFontB = '30';
        $strFont = '18';
        $strY = 64;
        $str1=$fetch_degree_data[$excel_row][3]." ";
        $str2="CªÀgÀ£ÀÄß";
       
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );
            

        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY($result[0], $strY);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY($result[0], $strY);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY+3);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

        if(trim($fetch_degree_data[$excel_row][10])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row][12])!="IN SOIL AND WATER ENGINEERING"){
            
            
            $strYDegKan=75+1.2;
            $strYSpecKan=85+1.2;

             $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = trim($fetch_degree_data[$excel_row][11]);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYDegKan);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strYDegKan);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

      
        $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row][13]." ";
        $str2="CAVÃPÀj¸À¯ÁVzÉ";
       
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );
            

        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strYSpecKan);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strYSpecKan);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
        }elseif(trim($fetch_degree_data[$excel_row][10])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row][12])=="IN SOIL AND WATER ENGINEERING"){
             $strYSpecKan=85+1.2;
            $strYDegKan=75+1.2;
             $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = trim($fetch_degree_data[$excel_row][13]);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYDegKan);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strYDegKan);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');


        $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row][11]." ";
        $str2="CAVÃPÀj¸À¯ÁVzÉ";
       
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );
            

        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strYSpecKan);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strYSpecKan);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
        }else{
            $strYSpecKan=85+1.2;
            $strYDegKan=75+1.2;
             $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = trim($fetch_degree_data[$excel_row][13]);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYDegKan);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strYDegKan);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

      
        $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row][11]." ";
        $str2="CAVÃPÀj¸À¯ÁVzÉ";
       
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );
            

        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strYSpecKan);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strYSpecKan);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
        }
       
        
        $strFont = '18';
        $strX = 85.1;
        $strY = 95.3+1.2;
        $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 105.3+1.2;
        $str = "UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 115.3+1.2;
        $str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');
 
        $strFont = '19';
        $strX = 55;
        $strY = 155.5;
        $str = "By authority of the Board of Management and upon recommendation";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 163.5;
        $str = "of the Board of Studies (Post Graduate) of the Faculty of Agriculture";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 170.5;
        $str = "and the Academic Council";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 178;
        $str = $fetch_degree_data[$excel_row][2];
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 185.5;
        $str = "has been admitted to the degree of";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 193;
        $str = $fetch_degree_data[$excel_row][10];
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 200.5;
        $str = $fetch_degree_data[$excel_row][12];
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 208;
        $str = "and is entitled to all rights and honours thereto appertaining";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 215.5;
        $str = "witness the seal of the University and the signatures of its";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 223;
        $str = "Officers this 28  day of November 2020";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '13';
        $strX = 55;
        $strY = 222;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(92.7, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(92.7, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        }else if(trim($fetch_degree_data[$excel_row][4])=="GOLD"){
         
        

          //Template 02
        $strY = 44;
        if(trim($fetch_degree_data[$excel_row]['subtype'])!="1"){    
        $strFont = '30';
        $strX = 85.1;
        
        $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±À¹Û";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strY = 57;
        }

        $strFont = '30';
        $strX = 86;
        $str =trim($fetch_degree_data[$excel_row][3]);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,92,88,7,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        if(strlen(trim($fetch_degree_data[$excel_row][13]))>90){
            $strFont = '16.5';
            $strY = $strY+14.3;//69.3
        }else{
           $strFont = '20'; 
           $strY = $strY+13.3;//68.3
        }
        
        $strX = 93;
        
        $str = trim($fetch_degree_data[$excel_row][13]);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');


        $strFont = '18';
        $strX = 55;
        $strY =  $strY+10;//104.5
        $str=trim($fetch_degree_data[$excel_row][11]);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');



        $strFont = '18';
        $strX = 13;
        $strY = $strY+10;
        $str = trim($fetch_degree_data[$excel_row]['year_degree']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(16.8, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(16.8, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $str = "£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(37.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(37.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 13;
        $str = trim($fetch_degree_data[$excel_row]['ogpa']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(125, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(125, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY=$strY-1;
        $str = "CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(137.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(137.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = $strY+10.5;
        $str = "±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAPÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $str = "28.11.2020";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(110.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(110.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $str = "gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(139, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(139, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');
     
        $strFont = '18';
        $strX = 55;
        $strY = $strY+10;//97
        $str ="PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        if(trim($fetch_degree_data[$excel_row]['type'])=="UG"){
            $str = "¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        }else{
            $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        }
        $strFont = '20';
        $strX = 55;
        $strY = $strY+9;
        
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');


       $strFont = '20';
        $strX = 55;
        $strY = 155;
        $str = "AWARD OF GOLD MEDAL";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 165;
        $str = trim($fetch_degree_data[$excel_row][2]);
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,92,88,7,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 174;
        $str = "is awarded";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

       $str=trim($fetch_degree_data[$excel_row][12]);
       
        $strArr = explode( "\n", wordwrap( $str, 58));
        $strFont = '18';
        $strX = 55;
        $strY = 183;
      
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

        $z=0;
        if(count($strArr)>1){
        $strFont = '18';
        $strX = 55;
        $strY = 192.5;
      
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');
        }else{
        $z=9;
        }
       $strFont = '17.9';
        $strX = 55;
        $strY = 201.5;
        $str = "at the 54   Convocation of UAS, Bangalore held on";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(20, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(20, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '13';
        $strX = 55;
        $strY = 199.5;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(42.6, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(42.6, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '16.9';
        $strX = 55;
        $strY = 201.2;
        $str = "28.11.2020";
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(151, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(151, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 201.2;
        $str = "for";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(180, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(180, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 210.7;
        $str = "having secured the highest OGPA of";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(32, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(32, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '16.9';
        $strX = 55;
        $strY = 210.7;
        $str = trim($fetch_degree_data[$excel_row]['ogpa']);
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(126.5, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(126.5, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 210.7;
        $str = "out of 10.00 in";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(140, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(140, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 219;
        $str = trim($fetch_degree_data[$excel_row][10]);
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFontB = '17.9';
        $strFont = '17.9';
        $strY = 227.5;
      
      
        $str1="during the year ";
        $str2=trim($fetch_degree_data[$excel_row]['year_degree']);
         $result = $this->GetStringPositions(
                    array( 
                        array($str1, $timesNewRomanI, '', $strFont),
                        array($str2, $timesNewRomanBI, '', $strFontB)
                    ),$pdf
                );
            

            $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[0], $strY-$z);
            $pdf->Cell(0, 0, $str1, 0, false, 'L');

            $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[0], $strY-$z);
            $pdfBig->Cell(0, 0, $str1, 0, false, 'L');


            $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[1], $strY-$z);
            $pdf->Cell(0, 0, $str2, 0, false, 'L');

            $pdfBig->SetFont($timesNewRomanBI, '', $strFontB, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[1], $strY-$z);
            $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

        }

       //qr code    
       $dt = date("_ymdHis");
        $str=$serial_no;
       $encryptedString = strtoupper(md5($str));
        $codeContents = $fetch_degree_data[$excel_row][1]."\n".$fetch_degree_data[$excel_row][2]."\n".$fetch_degree_data[$excel_row][10]."\n\n".$encryptedString;

        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 17;
        $qrCodey = 234.8;
        $qrCodeWidth =19.3;
        $qrCodeHeight = 19.3;
                
        \QrCode::size(75.6)
            
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        
        //micro line name

       $str = $fetch_degree_data[$excel_row][2];
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
         $fontImage=public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF';
        $textArray = imagettfbbox(1.4, 0, $fontImage, $str);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
         $width=18;
        $latestWidth = round($width*3.7795280352161);
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
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(17.6, 253.5);
        $pdf->Cell(0, 0, $array, 0, false, 'L');
        
        $pdfBig->SetFont($arialb, '', 1.2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->StartTransform();
        $pdfBig->SetXY(17.6, 253.5);
        $pdfBig->Cell(0, 0, $array, 0, false, 'L');
        $pdfBig->StopTransform();        


        if($previewPdf!=1){      
            $certName = str_replace("/", "_", $serial_no) .".pdf";
            
            $myPath = public_path().'/backend/temp_pdf_file';
            $dt = date("_ymdHis");
            
            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

            $this->addCertificate($serial_no, $certName, $dt,$template_data['id'],$admin_id);

            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            $print_datetime = date("Y-m-d H:i:s");
            

            $print_count = $this->getPrintCount($serial_no);
            $printer_name = /*'HP 1020';*/$printer_name;
            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_data['template_name'],$admin_id);
            
           
        }
        }


        $msg = '';
        
        $file_name = $template_type.'_'.date("Ymdhms").'.pdf';
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        if($previewPdf==1){
            $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name;
            $filePath =public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$file_name;
        }else{
            $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name;
            $filePath =public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name;
        
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name=$template_data['template_name'];
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                // with sandbox
                
                $result = SbExceUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            }else{
                // without sandbox
                $result = ExcelUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            } 

        }
       
        
        
        $aws_qr = \File::copy($filename,$filePath);
        @unlink($filename);

      

        return $pdf_url;

    }


   public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id)
{

    $domain = \Request::getHost();
    $subdomain = explode('.', $domain);

    $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
    $file2 = public_path().'/backend/pdf_file/'.$certName;

    $auth_site_id=Auth::guard('admin')->user()->site_id;

    $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

    /*copy($file1, $file2);
    $aws_qr = \File::copy($file2,public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName);
    @unlink($file2);*/
	$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;
	$output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName; 
	CoreHelper::compressPdfFile($source,$output);
    @unlink($file1);

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


    $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
    $auth_site_id=Auth::guard('admin')->user()->site_id;
    $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>2]);

}

public function getPrintCount($serial_no)
{
    $auth_site_id=Auth::guard('admin')->user()->site_id;
    $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
    $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();

    return $numCount + 1;
}

public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id)
{
        
    $sts = 1;
    $datetime = date("Y-m-d H:i:s");
    $ses_id = $admin_id["id"];

    $auth_site_id=Auth::guard('admin')->user()->site_id;

    $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

    $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
}

public function nextPrintSerial()
{
    $current_year = 'PN/' . $this->getFinancialyear() . '/';
        // find max
    $maxNum = 0;

    $auth_site_id=Auth::guard('admin')->user()->site_id;

    $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
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
