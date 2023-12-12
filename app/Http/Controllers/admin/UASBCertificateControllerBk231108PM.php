<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use Session,TCPDF,TCPDF_FONTS,Auth,DB;
use App\models\StudentTable;
use App\models\SuperAdmin;
use App\Http\Requests\ExcelValidationRequest;
use App\models\BackgroundTemplateMaster;
use App\models\SystemConfig;
use App\Jobs\DegreeCertificatePdfJob;
use App\models\Degree;
use App\models\Config;
use App\models\PrintingDetail;
use App\models\ExcelUploadHistory;

class UASBCertificateController extends Controller
{
    public function index(Request $request)
    {
       
    }

    public function pdfGenerate(){


        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $ghostImgArr = array();
        $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdf->SetCreator('TCPDF');
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
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);

       $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);

        $nudi01e = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e.TTF', 'TrueTypeUnicode', '', 96);
        $nudi01eb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e b.TTF', 'TrueTypeUnicode', '', 96);


        $pdf->AddPage();

        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\UASB 2020 CONV certificate_Background.jpg';
        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        
      //profile photo
        $profile_path = public_path().'\\'.$subdomain[0].'\backend\templates\nobody.jpg';
        $profilex = 182;
        $profiley = 10.8;
        $profileWidth = 18;
        $profileHeight = 18;
        $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);



        //set serial No
      $serial_no_split = (string)('54-0001');
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
        
           $pdf->SetFont($arial,'', $font_size, '', false);

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
            
            $pdf->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
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
        $enrollmenty = 37.5;
        $enrollmentstr = 'BLH 0123';
        $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdf->SetXY($enrollmentx, $enrollmenty);
        $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');

        //Template 05
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2="DºÁgÀ «eÁÕ£À ªÀÄvÀÄÛ vÀAvÀæeÁÕ£À";
        $str3=" ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ".$str2." ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";

        if(strlen($str)>145){

            $str=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹ì£À ªÉÄÃgÉUÉ";
        $strArr = explode( "\n", wordwrap( $str, 145));
        $z=0;

            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, 57);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

            if(count($strArr)>2){
                $z=10;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, 67);
                $pdf->Cell(0, 0, $strArr[2], 0, false, 'C');
            }

        }else{
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 57;
            $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹ì£À ªÉÄÃgÉUÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');
        }

        $strFontB = '30';
        $strFont = '18';
        $strY = 64;
        $str1="C©üµÉÃPï PÉ UËqÀ ";
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

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        
         $strFont = '23';
        $strX = 55;
        $strY = 75;
        $str="PÀÈ¶ «eÁÕ£ÀzÀ°è ¨ÁåZÀÄ®gï";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

       
        $strFont = '18';
        $strX = 85.1;
        $strY = 85.3;
        $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVz";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 95;
        $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄz";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 105;
        $str = "ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ 2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 115;
        $str = "28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ.";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        
        //Template 05
        $strFont = '19';
        $strX = 55;
        $strY = 155.5;
        $str = "By authority of the Board of Management and upon recommendation";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        $str = "of the Board of Studies, Under Graduate, Faculty of Agriculture ";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 172.7;
        $str = "and the Academic Council";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 180.7;
        $str = "ABHISHEK K GOWDA";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 188.7;
        $str = "has been admitted to the degree of";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 196.7;
        $str = "B. Tech. (Food Science & Technology)";
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 204.7;
        $str = "and is entitled to all rights and honours therto appertaining";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 212.7;
        $str = "witness the seal of the University and the signatures of its";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 220.7;
        $str = "Officers this 28  day of November 2020.";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '13';
        $strX = 55;
        $strY = 219;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(92.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        //qr code    
        $dt = date("_ymdHis");
        $str="TEST0001";
        $encryptedString = strtoupper(md5($str));
        $codeContents = "SLC 5001"."\n"."ABHISHEK K GOWDA"."\n"."Bachelor of Science (Sericulture)"."\n\n".$encryptedString;

        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 17;
        $qrCodey = 234.8;
        $qrCodeWidth =19.3;
        $qrCodeHeight = 19.3;
                
        \QrCode::size(75.6)
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        
        //micro line name
        $microlinestr = strtoupper(str_replace(' ','','ABHISHEK K GOWDA'));
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $microlinestr);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        
        $latestWidth = 70;
        $wd = '';
        $last_width = 0;
        $message = array();
        for($i=1;$i<=1000;$i++){

            if($i * $strWidth > $latestWidth){
                $wd = $i * $strWidth;
                $last_width =$wd - $strWidth;
                $extraWidth = $latestWidth - $last_width;
                $stringLength = strlen($microlinestr);
                $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                $message[$i]  = mb_substr($microlinestr, 0,$extraCharacter);
                break;
            }
            $message[$i] = $microlinestr.'';
        }

        $horizontal_line = array();
        foreach ($message as $key => $value) {
            $horizontal_line[] = $value;
        }
        
        $string = implode(',', $horizontal_line);
        $array = str_replace(',', '', $string);
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        
        $pdf->SetXY(17.6, 252.5);
        
        $pdf->Cell(0, 0, $array, 0, false, 'L');

        $pdf->StopTransform();

        $pdf->Output('sample.pdf', 'I');  
    }

    public function databaseGenerate(){

        $template_data =array("id"=>5,"template_name"=>"Degree Certificate");
        

        $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT degree_eng, COUNT(sr_no) AS sr_no_count FROM ug_pg WHERE ugpg_eng="Under Graduate" AND STATUS="0" AND faculty_eng="agriculture" GROUP BY degree_eng ORDER BY sr_no_count ASC') );
        
     
        
        foreach ($fetch_degree_data_prog_spec as $value) {
           $degree_eng =$value->degree_eng;
           $cert_cnt =$value->sr_no_count;
             $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM ug_pg WHERE degree_eng ="'.$value->degree_eng.'" AND status="0"'));
            $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();
   
        $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

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

       $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);

        $nudi01e = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e.TTF', 'TrueTypeUnicode', '', 96);
        $nudi01eb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e b.TTF', 'TrueTypeUnicode', '', 96);

        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);

        $log_serial_no = 1;


        for($excel_row = 0; $excel_row < count($fetch_degree_data); $excel_row++)

            
            //Checking profile pic
            $path=public_path().'/'.$subdomain[0].'/backend/DC_Photo/';
             $file =trim($fetch_degree_data[$excel_row]["id_number"]); 
            $file0 = str_replace("/", " ", $fetch_degree_data[$excel_row]["id_number"]);
            $file1 = str_replace(" ","",$fetch_degree_data[$excel_row]["id_number"]);
            $file2 = $fetch_degree_data[$excel_row]["id_number"];
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
            }elseif(file_exists($path.$file2.".jpeg")){
                $profile_path =$path.$file2.".jpeg";
            }elseif(file_exists($path.$file2.".jpg")){
                $profile_path =$path.$file2.".jpg";
            }elseif(file_exists($path.$file2.".jpeg")){
                $profile_path =$path.$file2.".jpeg";
            }else{
                $profile_path ='';
                
            }

            if(!empty($profile_path)){

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

             $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);


            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            
            $serial_no = trim($fetch_degree_data[$excel_row]['sr_no']);

            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

            //profile photo
        
        $profilex = 182;
        $profiley = 10.8;
        $profileWidth = 18;
        $profileHeight = 18;
        $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
        $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

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
        $enrollmenty = 37.5;
        $enrollmentstr = $fetch_degree_data[$excel_row]['id_number'];
        $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdf->SetXY($enrollmentx, $enrollmenty);
        $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');

        $pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdfBig->SetXY($enrollmentx, $enrollmenty);
        $pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');

            //UG
        if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Under Graduate"){

          $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2=$fetch_degree_data[$excel_row]['faculty_kan'];
        $str3=" ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ".$str2." ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";

        if(strlen($str)>145){

            $str=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹ì£À ªÉÄÃgÉUÉ";
        $strArr = explode( "\n", wordwrap( $str, 145));
        $z=0;

            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, 57);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, 57);
            $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');

            if(count($strArr)>2){
                $z=10;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, 67);
                $pdf->Cell(0, 0, $strArr[2], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, 67);
                $pdfBig->Cell(0, 0, $strArr[2], 0, false, 'C');
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
            $strY = 57;
            $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹ì£À ªÉÄÃgÉUÉ";
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
        $strY = 64;
        $str1=$fetch_degree_data[$excel_row]['name_kan']." ";
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
        
        $strFont = '23';
        $strX = 55;
        $strY = 75+1.2;
        $str=$fetch_degree_data[$excel_row]['degree_kan'];
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 85.3+1.2;
        $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVz";
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
        $strY = 95.3+1.2;
        $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄz";
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
        $strY = 115.3+1.2;
        $str = "28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ.";
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
        $strY = 163.7;
        $str = "of the Board of Studies, Under Graduate, Faculty of Agriculture ";
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

        $strFont = '19';
        $strX = 55;
        $strY = 180.7;
        $str = $fetch_degree_data[$excel_row]['name_eng'];
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
        $str = $fetch_degree_data[$excel_row]['degree_eng'];
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
        $str = "and is entitled to all rights and honours therto appertaining";
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
        $str = "Officers this 28  day of November 2020.";
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
        $pdf->SetXY(92.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(92.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');
        }else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Post Graduate"){
           
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
        $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹ì£À ªÉÄÃgÉUÉ";
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
        $str1=$fetch_degree_data[$excel_row]['name_kan']." ";
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

        
        $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = $fetch_degree_data[$excel_row]['degree_kan'];
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 85.1;
        $strY = 85+1.2;
        $str = $fetch_degree_data[$excel_row]['spec_kan'];
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 95.3+1.2;
        $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
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
        $str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ.";
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
        $str = "of the Board of Studies, (Post Graduate) of the Faculty of Agriculture";
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
        $str = $fetch_degree_data[$excel_row]['name_eng'];
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
        $str = $fetch_degree_data[$excel_row]['degree_eng'];
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
        $str = $fetch_degree_data[$excel_row]['spec_eng'];
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
        $str = "and is entitled to all rights and honours therto appertaining";
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
        $str = "Officers this 28  day of November 2020.";
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
        $pdf->SetXY(92.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(92.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        }
        //qr code    
       $dt = date("_ymdHis");
        $str=$serial_no;
       $encryptedString = strtoupper(md5($str));
        $codeContents = $fetch_degree_data[$excel_row]['id_number']."\n".$fetch_degree_data[$excel_row]['name_eng']."\n".$fetch_degree_data[$excel_row]['degree_eng']."\n\n".$encryptedString;

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
        $microlinestr = strtoupper(str_replace(' ','',$fetch_degree_data[$excel_row]['name_eng']));
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $microlinestr);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        
        $latestWidth = 68;
        $wd = '';
        $last_width = 0;
        $message = array();
        for($i=1;$i<=1000;$i++){

            if($i * $strWidth > $latestWidth){
                $wd = $i * $strWidth;
                $last_width =$wd - $strWidth;
                $extraWidth = $latestWidth - $last_width;
                $stringLength = strlen($microlinestr);
                $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                $message[$i]  = mb_substr($microlinestr, 0,$extraCharacter);
                break;
            }
            $message[$i] = $microlinestr.'';
        }

        $horizontal_line = array();
        foreach ($message as $key => $value) {
            $horizontal_line[] = $value;
        }
        
        $string = implode(',', $horizontal_line);
        $array = str_replace(',', '', $string);
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(17.6, 252.5);
        $pdf->Cell(0, 0, $array, 0, false, 'L');
        $pdf->StopTransform();
        
        $pdfBig->SetFont($arialb, '', 1.2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->StartTransform();
        $pdfBig->SetXY(17.6, 252.5);
        $pdfBig->Cell(0, 0, $array, 0, false, 'L');
        $pdfBig->StopTransform();        

          $certName = str_replace("/", "_", $serial_no) .".pdf";

            $myPath = public_path().'/backend/temp_pdf_file';
            $dt = date("_ymdHis");

        @unlink($file1);

           
            }else{
            
            }

        }


        $msg = '';

        $file_name =  str_replace("/", "_",'ug_pg_'.$degree_eng.' '.$cert_cnt).'.pdf';
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
        @unlink($filename);


    }
            

    }


    public function dbUploadfileTest(){
        $template_data = TemplateMaster::select('id','template_name')->where('id',1)->first()->toArray();
        
        //Test by Bhavin
        $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) AS prog_spec, COUNT(guid) FROM gu_dc_2 WHERE STATUS="0" GROUP BY prog_spec ORDER BY COUNT(guid) ASC') );
        

        foreach ($fetch_degree_data_prog_spec as $value) {
           $progSpec =$value->prog_spec;
            
             $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM gu_dc_2 WHERE CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) ="'.$value->prog_spec.'" AND status="0"'));
            $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();
          
            $fetch_degree_array=array();
        
        $admin_id = \Auth::guard('admin')->user()->toArray();

        foreach ($fetch_degree_data as $key => $value) {
            $fetch_degree_array[$key] = array_values($fetch_degree_data[$key]);
        }
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        
        $printer_name = $systemConfig['printer_name'];

        $ghostImgArr = array();
        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);
        

        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);
    
        $log_serial_no = 1;

        print_r($fetch_degree_array);

        for($excel_row = 0; $excel_row < count($fetch_degree_array); $excel_row++)
        {   

            $path=public_path().'/'.$subdomain[0].'/backend/DC_Photo/';
            $file1 = str_replace(" ","",$row["id_number"]);
            $file2 = $row["id_number"];
            if(file_exists($path.$file1.".jpg")){

            }else{
                $profile_path ='';
            }


            if(!empty($profile_path)){

            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');
            
            
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
            
            if($fetch_degree_array[$excel_row][10] == 'DB'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DB_Light Background.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'DIP'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DIP_Background_lite.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'INT'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/INT_lite background.jpg';
            }
            else if($fetch_degree_array[$excel_row][10] == 'DO'){
                 $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DO_Background Lite.jpg';   
            } else if($fetch_degree_array[$excel_row][10] == 'NU'){
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/GU Nursing background_lite.jpg';   
            }
        
            
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
           
            

            $pdfBig->AddPage();

            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            
            $serial_no = trim($fetch_degree_array[$excel_row][0]);

            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

                //set enrollment no
                $enrollment_font_size = '8';
                $enrollmentx= 26.7;
                
                $enrollmenty = 10.8;
                $enrollmentstr = trim($fetch_degree_array[$excel_row][1]);

                $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
                $pdf->SetXY($enrollmentx, $enrollmenty);
                $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');


                $pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
                $pdfBig->SetXY($enrollmentx, $enrollmenty);
                $pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');


                //set serial No
                $serial_no_split = (string)trim($fetch_degree_array[$excel_row][3]);
                $serialx = 186.4;
                $serialx = 181;
                $serialy = 10.9;
                for($i=0;$i<strlen($serial_no_split);$i++)
                { 
                    $get_last_four_digits = strlen($serial_no_split) - 4;
                    
                    $serial_font_size = 8;
                    if($i == 0){
                        $serialx = $serialx;
                    }
                    else{
                        if($i <= $get_last_four_digits){
                            if($serial_no_split[$i-1] == '/'){
                                $serialx = $serialx + (0.9);
                            }
                            else{
                                $serialx = $serialx + (1.7);
                            }
                        }
                        else{
                            $serialx = $serialx + (2.1);
                        }
                    }
                    if($i >= $get_last_four_digits){
                        
                        $serial_font_size = $serial_font_size + ($i - $get_last_four_digits) + 1;
                        $serialy = $serialy - 0.3;
                   
                    }
                    $serialstr = $serial_no_split[$i];

                    $pdf->SetFont($arial, '', $serial_font_size, '', false);
                    $pdf->SetXY($serialx, $serialy);
                    $pdf->Cell(0, 0, $serialstr, 0, false, 'L');

                    $pdfBig->SetFont($arial, '', $serial_font_size, '', false);
                    $pdfBig->SetXY($serialx, $serialy);
                    $pdfBig->Cell(0, 0, $serialstr, 0, false, 'L');
                }



                //qr code    
                //name // enrollment // degree // branch // cgpa // guid
                
                if($fetch_degree_array[$excel_row][10] == 'NU'){
                     $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$fetch_degree_array[$excel_row][17]."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                        $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                    }
                    else if($fetch_degree_array[$excel_row][10] == 'DO'){
                        $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
                    }

                }

                $codePath = strtoupper(md5(rand()));
                $qr_code_path = public_path().'/'.$subdomain[0].'/backend/canvas/images/qr/'.$codePath.'.png';
 
                $qrCodex = 5.3;
                $qrCodey = 17.9;
                $qrCodeWidth =26.3;
                $qrCodeHeight = 25.3;
                        
                \QrCode::size(75.6)
                    ->format('png')
                    ->generate($codeContents, $qr_code_path);
                $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);


                $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);                
                $profilex = 181;
                $profiley = 19.8;
                $profileWidth = 22.2;
                $profileHeight = 26.6;
                $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                //invisible data
                $invisible_font_size = '10';

                $invisible_degreex = 7.3;
                $invisible_degreey = 48.3;
                $invisible_degreestr = trim($fetch_degree_array[$excel_row][11]);
                $pdfBig->SetOverprint(true, true, 0);
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible_degreey);
                $pdfBig->Cell(0, 0, $invisible_degreestr, 0, false, 'L');

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $invisible1y = 51.9;
                    $invisible1str = trim($fetch_degree_array[$excel_row][13]);
                    
                    $pdfBig->SetTextSpotColor('Spot Red', 100);
                    $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                    $pdfBig->SetXY($invisible_degreex, $invisible1y);
                    $pdfBig->Cell(0, 0, $invisible1str, 0, false, 'L');

                }

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    
                    $invisible2y = 55.1;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $invisible2y = 51.9;   
                }else if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $invisible2y = 56.1;
                }

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                   $invisible2str = $fetch_degree_array[$excel_row][17];
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                   $invisible2str = 'CGPA '.$cgpaFormat;  
                }

                
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible2y);
                $pdfBig->Cell(0, 0, $invisible2str, 0, false, 'L');


                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    
                    $invisible3y = 58.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $invisible3y = 55.1;
                }else if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $invisible3y = 59.7;
                }
                $invisible3str = trim($fetch_degree_array[$excel_row][8]); 
                
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_font_size, '', false);
                $pdfBig->SetXY($invisible_degreex, $invisible3y);
                $pdfBig->Cell(0, 0, $invisible3str, 0, false, 'L');

                //invisible data profile name
                $invisible_profile_font_size = '10';
                $invisible_profile_name1x = 175.9;
                $invisible_profile_name1y = 47.6;
                $invisible_profile_name1str = strtoupper(trim($fetch_degree_array[$excel_row][4]));
                
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_profile_font_size, '', false);
                $pdfBig->SetXY($invisible_profile_name1x, $invisible_profile_name1y);
                $pdfBig->Cell(28.8, 0, $invisible_profile_name1str, 0, false, 'R');

                $invisible_profile_name2x = 186.6;
                $invisible_profile_name2y = 50.8;
                $invisible_profile_name2str = trim($fetch_degree_array[$excel_row][1]);
                $pdfBig->SetTextSpotColor('Spot Red', 100);
                $pdfBig->SetFont($arial, '', $invisible_profile_font_size, '', false);
                $pdfBig->SetXY($invisible_profile_name2x, $invisible_profile_name2y);
                $pdfBig->Cell(18, 0, $invisible_profile_name2str, 0, false, 'R');
            
               
                //enrollment no inside round
                $enrollment_no_font_size = '7';
                $enrollment_nox = 184.8;
                $enrollment_noy = 66;

                $enrollment_nostr = trim($fetch_degree_array[$excel_row][1]);

                $pdf->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
                $pdf->SetTextColor(0,0,0,8,false,'');
                $pdf->SetXY(186, $enrollment_noy);
                $pdf->Cell(12, 0, $enrollment_nostr, 0, false, 'C');

                $pdfBig->SetFont($arialNarrowB, '', $enrollment_no_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,8,false,'');
                $pdfBig->SetXY(186, $enrollment_noy);
                $pdfBig->Cell(12, 0, $enrollment_nostr, 0, false, 'C');



                //profile name
                $profile_name_font_size = '20';
                $profile_namex = 71.7;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $profile_namey = 83.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $profile_namey = 85;
                }
                $profile_namestr = strtoupper(trim($fetch_degree_array[$excel_row][4]));

                $pdf->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_namey);
                $pdf->Cell(190, 0, $profile_namestr, 0, false, 'C');

                $pdfBig->SetFont($timesNewRomanBI, '', $profile_name_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $profile_namey);
                $pdfBig->Cell(190, 0, $profile_namestr, 0, false, 'C');


                //degree name
                $degree_name_font_size = '20';
                $degree_namex = 55;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $degree_namey = 99.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'INT'){
                    $degree_name_font_size = '14';
                    $degree_namey = 103.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $degree_namey = 104.5;
                }
           
                $degree_namestr = trim($fetch_degree_array[$excel_row][11]);

                if($fetch_degree_array[$excel_row][10] != 'DIP'){

                $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_namey);
                $pdf->Cell(190, 0, $degree_namestr, 0, false, 'C');


                $pdfBig->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $degree_namey);
                $pdfBig->Cell(190, 0, $degree_namestr, 0, false, 'C');
                }

                //branch name
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'NU'){
                        $branch_name_font_size = '18';
                        $branch_namey = 114.2;
                    }
                    else if($fetch_degree_array[$excel_row][10] == 'INT'){
                        $branch_name_font_size = '14';
                        $branch_namey = 111.5;
                    }else if ( $fetch_degree_array[$excel_row][10] == 'DIP') {
                        $branch_name_font_size = '20';
                        $branch_namey = 99.5;
                    }

                    $branch_namex = 80;
                    $branch_namestr = trim($fetch_degree_array[$excel_row][13]);

                    $pdf->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_namey);
                    $pdf->Cell(190, 0, $branch_namestr, 0, false, 'C');


                    $pdfBig->SetFont($timesNewRomanBI, '', $branch_name_font_size, '', false);
                    $pdfBig->SetTextColor(0,92,88,7,false,'');
                    $pdfBig->SetXY(10, $branch_namey);
                    $pdfBig->Cell(190, 0, $branch_namestr, 0, false, 'C');
                }

                //grade
                $grade_font_size = '17';

                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){

                    $gradey = 137.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $gradey = 133.3;
                }elseif ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $gradey = 132.3;
                }
                $divisionStr= '';

                if($fetch_degree_array[$excel_row][10] == 'NU'){

                    if(strpos($fetch_degree_array[$excel_row][17], 'With') !== false){

                         $gradestr = $fetch_degree_array[$excel_row][17].' ';
                    }else{
                        $divisionStr= ' division ';
                        $gradestr = $fetch_degree_array[$excel_row][17]; 
                    }
                   
                }else{
                if(is_float($fetch_degree_array[$excel_row][6])){
                $gradestr = 'CGPA '. number_format(trim($fetch_degree_array[$excel_row][6]),2).' ';
                }else{
                $gradestr = 'CGPA '. trim($fetch_degree_array[$excel_row][6]).' ';    
                }
                }
                $instr = $divisionStr.'in ';
                $datestr = trim($fetch_degree_array[$excel_row][8]);


                $grade_str_result = $this->GetStringPositions(
                    array(
                        array($gradestr, $timesNewRomanBI, '', $grade_font_size), 
                        array($instr, $timesNewRomanI, '', $grade_font_size),
                        array($datestr, $timesNewRomanBI, '', $grade_font_size)
                    ),$pdf
                );
                

                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[0], $gradey);
                $pdf->Cell(0, 0, $gradestr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[0], $gradey);
                $pdfBig->Cell(0, 0, $gradestr, 0, false, 'L');


                $pdf->SetFont($timesNewRomanI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[1], $gradey);
                $pdf->Cell(0, 0, $instr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[1], $gradey);
                $pdfBig->Cell(0, 0, $instr, 0, false, 'L');


                $pdf->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_str_result[2], $gradey);
                $pdf->Cell(0, 0, $datestr, 0, false, 'L');

                $pdfBig->SetFont($timesNewRomanBI, '', $grade_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_str_result[2], $gradey);
                $pdfBig->Cell(0, 0, $datestr, 0, false, 'L');


                //micro line name
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $microlinestr = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][4].$fetch_degree_array[$excel_row][11].$fetch_degree_array[$excel_row][13]));
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $microlinestr = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][4].$fetch_degree_array[$excel_row][11]));
                }
                $textArray = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlinestr);
                $strWidth = ($textArray[2] - $textArray[0]);
                $strHeight = $textArray[6] - $textArray[1] / 1.4;
                
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $latestWidth = 557;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $latestWidth = 564;
                }
                $wd = '';
                $last_width = 0;
                $message = array();
                for($i=1;$i<=1000;$i++){

                    if($i * $strWidth > $latestWidth){
                        $wd = $i * $strWidth;
                        $last_width =$wd - $strWidth;
                        $extraWidth = $latestWidth - $last_width;
                        $stringLength = strlen($microlinestr);
                        $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                        $message[$i]  = mb_substr($microlinestr, 0,$extraCharacter);
                        break;
                    }
                    $message[$i] = $microlinestr.'';
                }

                $horizontal_line = array();
                foreach ($message as $key => $value) {
                    $horizontal_line[] = $value;
                }
                

                $string = implode(',', $horizontal_line);
                $array = str_replace(',', '', $string);
                $pdf->SetFont($arialb, '', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $pdf->SetXY(36.8, 146.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.8, 145.5);
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $pdf->SetXY(36.8, 143.5);
                }
                $pdf->Cell(0, 0, $array, 0, false, 'L');

                $pdf->StopTransform();


                $pdfBig->SetFont($arialb, '', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0);
                $pdfBig->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    $pdfBig->SetXY(36.8, 146.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdfBig->SetXY(36.8, 145.5);
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $pdfBig->SetXY(36.8, 143.5);
                }
                $pdfBig->Cell(0, 0, $array, 0, false, 'L');

                $pdfBig->StopTransform();

                if($fetch_degree_array[$excel_row][10] == 'NU'){
                   $microlineEnrollment = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][1].strtoupper($fetch_degree_array[$excel_row][17]).$fetch_degree_array[$excel_row][8])); 
                }else{
                  if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                  $microlineEnrollment = strtoupper(str_replace(' ','',$fetch_degree_array[$excel_row][1].'CGPA'.$cgpaFormat.$fetch_degree_array[$excel_row][8]));  
                }

                $textArrayEnrollment = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlineEnrollment);
                $strWidthEnrollment = ($textArrayEnrollment[2] - $textArrayEnrollment[0]);
                $strHeightEnrollment = $textArrayEnrollment[6] - $textArrayEnrollment[1] / 1.4;
                
                $latestWidthEnrollment = 627;
                $wdEnrollment = '';
                $last_widthEnrollment = 0;
                $messageEnrollment = array();
                for($i=1;$i<=1000;$i++){

                    if($i * $strWidthEnrollment > $latestWidthEnrollment){
                        $wdEnrollment = $i * $strWidthEnrollment;
                        $last_widthEnrollment =$wdEnrollment - $strWidthEnrollment;
                        $extraWidth = $latestWidthEnrollment - $last_widthEnrollment;
                        $stringLength = strlen($microlineEnrollment);
                        $extraCharacter = intval($stringLength * $extraWidth / $strWidthEnrollment);
                        $messageEnrollment[$i]  = mb_substr($microlineEnrollment, 0,$extraCharacter);
                        break;
                    }
                    $messageEnrollment[$i] = $microlineEnrollment.'';
                }

                $horizontal_lineEnrollment = array();
                foreach ($messageEnrollment as $key => $value) {
                    $horizontal_lineEnrollment[] = $value;
                }
                
                $stringEnrollment = implode(',', $horizontal_lineEnrollment);
                $arrayEnrollment = str_replace(',', '', $stringEnrollment);

                $pdf->SetFont($arialb, '', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $pdf->SetXY(36.4, 216.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.4, 216);
                }
                
                $pdf->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdf->StopTransform();


                $pdfBig->SetFont($arialb, '', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0);
                $pdfBig->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    $pdfBig->SetXY(36.4, 216.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdfBig->SetXY(36.4, 216);
                }
                
                $pdfBig->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdfBig->StopTransform();
              //profile name in hindi
                $profile_name_hindi_font_size = '25';
                $profile_name_hidix = 85.1;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $profile_name_hidiy = 156.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $profile_name_hidiy = 159;
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                   $profile_name_hidiy = 161;
                }
                $profile_name_hindistr = trim($fetch_degree_array[$excel_row][5]);

                $pdf->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $profile_name_hidiy);
                $pdf->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');

                $pdfBig->SetFont($krutidev101, '', $profile_name_hindi_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $profile_name_hidiy);
                $pdfBig->Cell(190, 0, $profile_name_hindistr, 0, false, 'C');

                //date in hindi (make whole string)
                $date_font_size =  '20';
                if($fetch_degree_array[$excel_row][10] == 'DIP'){
                $str = 'rhu o"khZ; fMIyksek ikBîØe ';
                $hindiword_str = ' ' ; 
                }else{
                $str = 'dks bl mikf/k dh çkfIr gsrq fofue; fofgr vis{kkvksa dks ';
                $hindiword_str = 'esa' ; 
                }
                $date_hindistr = trim($fetch_degree_array[$excel_row][9]).' ';
               

                $strx = 20;
                $date_hindix = 159;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                    $date_hindiy = 168.4;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $date_hindiy = 170.6;
                }else if($fetch_degree_array[$excel_row][10] == 'DIP'){
                    $date_hindiy = 180.4;
                }

                $result = $this->GetStringPositions(
                    array(
                        array($str, $krutidev100, '', $date_font_size), 
                        array($date_hindistr, $krutidev101, '', $date_font_size),
                        array($hindiword_str, $krutidev100, '', $date_font_size)
                    ),$pdf
                );

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[0], $date_hindiy);
                $pdf->Cell(0, 0, $str, 0, false, 'L');

                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[0], $date_hindiy);
                $pdfBig->Cell(0, 0, $str, 0, false, 'L');

                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[1], $date_hindiy);
                $pdf->Cell(0, 0, $date_hindistr, 0, false, 'L');

                $pdfBig->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[1], $date_hindiy);
                $pdfBig->Cell(0, 0, $date_hindistr, 0, false, 'L');

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[2], $date_hindiy);
                $pdf->Cell(0, 0, $hindiword_str, 0, false, 'L');


                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[2], $date_hindiy);
                $pdfBig->Cell(0, 0, $hindiword_str, 0, false, 'L');



                //grade in hindi
                $grade_hindix = 37.5;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT'){
                    
                    $grade_hindiy = 177.5;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $grade_hindiy = 181.7;
                }elseif ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $grade_hindix = 40.5;
                    $grade_hindiy = 188.5;
                }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {

                    if(strpos($fetch_degree_array[$excel_row][17], 'With') !== false){
                       
                        $grade_hindiy = 178.8;
                        $grade_hindix = 31.5; 
                    }else if($fetch_degree_array[$excel_row][17]=="First"){
                       $grade_hindiy = 178.8;
                        $grade_hindix = 49.5; 
                    }else{
                       $grade_hindiy = 178.8;
                        $grade_hindix = 46.5; 
                    }
                    
                }
                if ($fetch_degree_array[$excel_row][10] == 'DIP') {

                $pdf->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_hindix-6, $grade_hindiy);
                $pdf->Cell(0, 0, 'esa', 0, false, 'L'); 
                
                $pdfBig->SetFont($krutidev100, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_hindix-6, $grade_hindiy);
                $pdfBig->Cell(0, 0, 'esa', 0, false, 'L'); 
                }


                if($fetch_degree_array[$excel_row][10] == 'NU'){
                  $grade_hindistr = trim($fetch_degree_array[$excel_row][18]);
                }else{
                  $grade_hindistr = 'lh-th-ih-,- '.trim($fetch_degree_array[$excel_row][7]);  
                }

                $pdf->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($grade_hindix, $grade_hindiy);
                $pdf->Cell(0, 0, $grade_hindistr, 0, false, 'L');

                $pdfBig->SetFont($krutidev101, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($grade_hindix, $grade_hindiy);
                $pdfBig->Cell(0, 0, $grade_hindistr, 0, false, 'L');


                //degree name in hindi
                $degree_hindi_font_size = '25';
                if($fetch_degree_array[$excel_row][10] == 'INT'){
                    $degree_hindi_font_size = '18';
                    $degree_hindiy = 188.8;
                }
                $degree_hindix = 66;
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                    $degree_hindiy = 185.2;
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $degree_hindiy = 192;
                }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {
                    $degree_hindiy = 188.2;
                }

                if($fetch_degree_array[$excel_row][10] != 'DIP'){
                $degree_hindistr = trim($fetch_degree_array[$excel_row][12]);

                $pdf->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_hindiy);
                $pdf->Cell(190, 0, $degree_hindistr, 0, false, 'C');

                $pdfBig->SetFont($krutidev101, '', $degree_hindi_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $degree_hindiy);
                $pdfBig->Cell(190, 0, $degree_hindistr, 0, false, 'C');
                }
                //branch name in hindi
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    if($fetch_degree_array[$excel_row][10] == 'DIP'){
                        $branch_hindi_font_size = '25';
                    }else {
                        $branch_hindi_font_size = '20';
                    }
                    $branch_hindiy = 196.5;
                    if($fetch_degree_array[$excel_row][10] == 'INT'){
                        $branch_hindi_font_size = '15';
                        $branch_hindiy = 196.8;
                    }elseif ($fetch_degree_array[$excel_row][10] == 'NU') {
                        $branch_hindiy = 199.8;
                    }
                    $branch_hindix = 75.2;
                    $branch_hindistr = trim($fetch_degree_array[$excel_row][14]);

                    $pdf->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $branch_hindiy);
                    $pdf->Cell(190, 0, $branch_hindistr, 0, false, 'C');

                    $pdfBig->SetFont($krutidev101, '', $branch_hindi_font_size, '', false);
                    $pdfBig->SetTextColor(0,92,88,7,false,'');
                    $pdfBig->SetXY(10, $branch_hindiy);
                    $pdfBig->Cell(190, 0, $branch_hindistr, 0, false, 'C');
                }

                //today date
                $today_date_font_size = '12';
                
                $today_datex = 95;
                $today_datey = 273.8;
                $todaystr = 'September, 2020';

                $pdf->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(84, $today_datey);
                $pdf->Cell(47, 0, $todaystr, 0, false, 'C');

                $pdfBig->SetFont($timesNewRomanBI, '', $today_date_font_size, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(84, $today_datey);
                $pdfBig->Cell(47, 0, $todaystr, 0, false, 'C');

                //1D Barcode
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
                    'text' => false,
                    'font' => 'helvetica',
                    'fontsize' => 8,
                    'stretchtext' => 4
                );              
                // $barcodex = 80.8;
                $barcodex = 84;
                $barcodey = 278;
                $barcodeWidth = 46;
                $barodeHeight = 12;

                $pdf->SetAlpha(1);
                // Barcode CODE_39 - Roll no 
                $pdf->write1DBarcode(trim($fetch_degree_array[$excel_row][1]), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

                $pdfBig->SetAlpha(1);
                // Barcode CODE_39 - Roll no 
                $pdfBig->write1DBarcode(trim($fetch_degree_array[$excel_row][1]), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

                 $footer_name_font_size = '12';
                $footer_namex = 84.9;
                $footer_namey = 290.9;
                $footer_namestr = strtoupper(trim($fetch_degree_array[$excel_row][4]));



                $pdfBig->SetOverprint(true, true, 0);
                $pdfBig->SetFont($arial, '', $footer_name_font_size, '', false);
                $pdfBig->SetTextSpotColor('Spot Dark Green', 100);
                $pdfBig->SetXY(10, $footer_namey);
                $pdfBig->Cell(190, 0, $footer_namestr, 0, false, 'C');
                $pdfBig->SetOverprint(false, false, 0);
                //repeat line
                $repeat_font_size = '9.5';
                $repeatx= 0;

                    //name repeat line
                    $name_repeaty = 242.9;
                     if($fetch_degree_array[$excel_row][10] == 'NU'){
                        $cgpaFormat = strtoupper($fetch_degree_array[$excel_row][17]);
                    }else{
                        if(is_float($fetch_degree_array[$excel_row][6])){
                            $cgpaFormat='CGPA '.number_format(trim($fetch_degree_array[$excel_row][6]),2);
                        }else{
                            $cgpaFormat='CGPA '.trim($fetch_degree_array[$excel_row][6]);
                        }
                    }
                    $name_repeatstr = strtoupper(trim($fetch_degree_array[$excel_row][4])).' '.$cgpaFormat.' '.strtoupper(trim($fetch_degree_array[$excel_row][8])).' '; 
                    $name_repeatstr .= $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr . $name_repeatstr;


                    //degree repeat line
                    $degree_repeaty = 247;
                    $degree_repeatstr = strtoupper(trim($fetch_degree_array[$excel_row][11])).' '.strtoupper(trim($fetch_degree_array[$excel_row][13])).' '; 
                    $degree_repeatstr .= $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr . $degree_repeatstr;

                    //grade repeat line
                    $grade_repeaty = 251.1;
                    $grade_repeatstr = 'GALGOTIAS UNIVERSITY '; 
                    $grade_repeatstr .= $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr . $grade_repeatstr;

                    //date repeat line
                    $date_repeaty = 255.2;
                    $date_repeatstr = 'GALGOTIAS UNIVERSITY '; 
                    $date_repeatstr .= $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr . $date_repeatstr;

                        $pdf->SetTextColor(0,0,0,5,false,'');
                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $name_repeaty);
                        $pdf->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();


                        $pdfBig->SetTextColor(0,0,0,5,false,'');
                        $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdfBig->StartTransform();
                        $pdfBig->SetXY($repeatx, $name_repeaty);
                        $pdfBig->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                        $pdfBig->StopTransform();

                        //degree repeat line

                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $degree_repeaty);
                        $pdf->Cell(0, 0, $degree_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();

                        $pdfBig->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdfBig->StartTransform();
                        $pdfBig->SetXY($repeatx, $degree_repeaty);
                        $pdfBig->Cell(0, 0, $degree_repeatstr, 0, false, 'L');
                        $pdfBig->StopTransform();
                

                unlink(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

                //vc sign visible
                $vc_sign_visible_path = public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png';
                $vc_sign_visibllex = 168.5;
                $vc_sign_visiblley = 243.7;
                $vc_sign_visiblleWidth = 21;
                $vc_sign_visiblleHeight = 16;
                $pdf->image($vc_sign_visible_path,$vc_sign_visibllex,$vc_sign_visiblley,$vc_sign_visiblleWidth,$vc_sign_visiblleHeight,"",'','L',true,3600);

                $pdfBig->image($vc_sign_visible_path,$vc_sign_visibllex,$vc_sign_visiblley,$vc_sign_visiblleWidth,$vc_sign_visiblleHeight,"",'','L',true,3600);

                unlink(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');
                

                // Ghost image
                $ghost_font_size = '13';
                $ghostImagex = 10;
                $ghostImagey = 278.8;
                $ghostImageWidth = 68;
                $ghostImageHeight = 9.8;
                $name = substr(str_replace(' ','',strtoupper($fetch_degree_array[$excel_row][4])), 0, 6);
                

                $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');

                    $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
                   

                $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

                $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);


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
            
             Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'1']);
            }else{
             Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'0']);;
              
            }

        }


        $msg = '';
       
        $file_name =  str_replace("/", "_",'2019 '.$fetch_degree_array[0][11].' '.$fetch_degree_array[0][13].' '.$fetch_degree_array[0][10]).'.pdf';
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
        @unlink($filename);
    }
            

    }

    //storte student details
    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id)
    {

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        copy($file1, $file2);
        
        $aws_qr = \File::copy($file2,public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName);
                
            
        @unlink($file2);

        @unlink($file1);

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

        
        $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        
    }

    //get print count
    public function getPrintCount($serial_no)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    }

    //store printing details
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

    //create ghost image folder
    public function createTemp($path){
        $tmp = date("ymdHis");
        
        $tmpname = tempnam($path, $tmp);
        unlink($tmpname);
        mkdir($tmpname);
        return $tmpname;
    }

    // Create character image
    public function CreateMessage($tmpDir, $name = "",$font_size,$print_color)
    {
        if($name == "")
            return;
        $name = strtoupper($name);
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
