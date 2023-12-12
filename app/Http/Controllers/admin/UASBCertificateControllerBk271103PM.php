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
        // $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\DO_Background Light.jpg';
        // dd($template_img_generate);
        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        
      //profile photo
        $profile_path = public_path().'\\'.$subdomain[0].'\backend\DC_Photo\nobody.jpg';
         $profilex = 181.5;
        $profiley = 8.3;
        $profileWidth = 19.20;
        $profileHeight = 24;
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

        // Template 01
       /* $strFont = '30';
        $strX = 85.1;
        $strY = 45;
        $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±À¹Û";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '30';
        $strX = 85.1;
        $strY = 56;
        $str = "¸ÀÄªÀÄ PÉ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 85.1;
        $strY = 66;
        $str = "PÀÈ¶ «eÁÕ£ÀzÀ ¨ÁåZÀÄ®gï ¥ÀzÀ«AiÀÄ°è";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 13;
        $strY = 76;
        $str = "2018-19";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(16.8, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 76;
        $str = "£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(37.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

         $strFont = '18';
        $strX = 13;
        $strY = 76;
        $str = "9.00";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(125, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 76;
        $str = "CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(137.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 85.5;
        $str = "±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAP";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 85.5;
        $str = "28.11.2020";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(110.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 85.5;
        $str = "gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(139, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');
     
        $strFont = '18';
        $strX = 55;
        $strY = 95;
        $str = "PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 104.5;
        $str = "DªÀgÀtzÀ a£ÀßzÀ ¥ÀzÀPÀ, (PÀÈ¶ PÁ¯ÉÃdÄ, fPÉ«PÉ)";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 55;
        $strY = 114;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVz";
        $str="gÉÃµÉä PÀÈ¶ «eÁÕ£ÀzÀ°è ¨ÁåZÀÄ®gï «eÁÕ£À ¥ÀzÀ«";
        $str="PÀÈ¶ EAf¤AiÀÄjAUï£À°è ¨ÁåZÀÄ®gï D¥sï mÉPÁß®f ¥ÀzÀ«";
        $str="DºÁgÀ «eÁÕ£À ªÀÄvÀÄÛ vÀAvÀæeÁÕ£ÀzÀ°è ¨ÁåZÀÄ®gï D¥sï mÉPÁß®f ¥ÀzÀ«";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');
*/

         // Template 02
        /*$strFont = '30';
        $strX = 85.1;
        $strY = 45;
        $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±ÀQÛ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '30';
        $strX = 85.1;
        $strY = 56;
        $str = "¸ÀÄªÀÄ P";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 85.1;
        $strY = 66;
        $str = "DºÁgÀ «eÁÕ£À ªÀÄvÀÄÛ vÀAvÀæeÁÕ£ÀzÀ°è ¨ÁåZÀÄ®gï D¥sï mÉPÁß®f ¥ÀzÀ«";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 13;
        $strY = 76;
        $str = "2018-19";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(16.8, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 76;
        $str = "£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(37.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

         $strFont = '18';
        $strX = 13;
        $strY = 76;
        $str = "9.00";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(125, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 76;
        $str = "CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(137.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 85.5;
        $str = "±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAP";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 85.5;
        $str = "28.11.2020";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(110.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 85.5;
        $str = "gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(139, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');
     
        $strFont = '18';
        $strX = 55;
        $strY = 95;
        $str = "PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        

        $strFont = '18';
        $strX = 55;
        $strY = 104.5;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVz";
        $str="gÉÃµÉä PÀÈ¶ «eÁÕ£ÀzÀ°è ¨ÁåZÀÄ®gï «eÁÕ£À ¥ÀzÀ«";
        $str="PÀÈ¶ EAf¤AiÀÄjAUï£À°è ¨ÁåZÀÄ®gï D¥sï mÉPÁß®f ¥ÀzÀ«";
        $str="¸ÁévÀAvÀæöå ºÉÆÃgÁlUÁgÀ PÉÆvÀÛ£ÀÆgÀÄ ªÀÄÄ¤AiÀÄ¥Àà ¥Á¥ÀtÚ ¸ÀägÀuÁxÀð a£ÀßzÀ ¥ÀzÀPÀ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 114;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');*/

        //Template 07

       
       /* $strFont = '30';
        $strX = 85.1;
        $strY = 45;
        $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±ÀQÛ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');
        

        $strFont = '30';
        $strX = 85.1;
        $strY = 56;
        $str = "¸ÀÄªÀÄ PÉ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

         $strFontB = '18';
        $strFont = '18';
        $strY = 66;
        $str1="PÀÈ¶ «eÁÕ£ÀzÀ ¨ÁåZÀÄ®gï ¥ÀzÀ«AiÀÄ°è ";
        $str2="¥ÀzÀ«AiÀÄ°è";
       
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );
            

        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strY);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $strFont = '18';
        $strX = 13;
        $strY = 76;
        $str = "2018-19";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(16.8, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 76;
        $str = "£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(37.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

         $strFont = '18';
        $strX = 13;
        $strY = 76;
        $str = "9.00";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(125, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 76;
        $str = "CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(137.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 85.5;
        $str = "±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAP";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 85.5;
        $str = "28.11.2020";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(110.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 85.5;
        $str = "gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(139, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');
     
        $strFont = '18';
        $strX = 55;
        $strY = 95;
        $str = "PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        

        $strFont = '18';
        $strX = 55;
        $strY = 104.5;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVz";
        $str="gÉÃµÉä PÀÈ¶ «eÁÕ£ÀzÀ°è ¨ÁåZÀÄ®gï «eÁÕ£À ¥ÀzÀ«";
        $str="PÀÈ¶ EAf¤AiÀÄjAUï£À°è ¨ÁåZÀÄ®gï D¥sï mÉPÁß®f ¥ÀzÀ«";
        $str="qÁ.J¸ï.dªÀgÉÃUËqÀ a£ÀßzÀ ¥ÀzÀPÀ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 114;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ.";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');*/

      /*  //Template 04
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2="PÀÈ¶ «eÁÕ£À";
        $str3=" ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01e, '', $strFont), 
                        array($str2, $nudi01e, '', $strFont),
                        array($str3, $nudi01e, '', $strFont)
                    ),$pdf
                );
            
   
        if($result[0]<1){
            $result[0]=$result[0]*-1;
        }

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strY);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');
        
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[2], $strY);
        $pdf->Cell(0, 0, $str3, 0, false, 'L');
      

        $strFont = '18';
        $strX = 85.1;
        $strY = 57;
        $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹ì£À ªÉÄÃgÉUÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFontB = '30';
        $strFont = '18';
        $strY = 64;
        $str1="C©üµÉÃPï PÉ UËq ";
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

        
        $strFont = '20';
        $strX = 85.1;
        $strY = 75;
        $str = "PÀÈ¶ QÃl±Á¸ÀÛçzÀ°è";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 85.1;
        $strY = 85;
        $str = "PÀÈ¶ «eÁÕ£À ªÀiÁ¸ÀÖgï";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');*/

        /* Already commented
        $strFontB = '20';
        $strFont = '18';
        $strY = 75;
        $str1="PÀÈ¶ «eÁÕ£ÀzÀ°è ªÀiÁ¸ÀÖgï ";
        $str2="¥ÀzÀ«U ";
        $str3="CAVÃPÀj¸À¯ÁVzÉ";
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );
            
       // print_r($result);
        if($result[0]>10){
            $result[0]=$result[0]-10;
        }else if($result[0]<0){
            $result[0]=0;
        }
         
        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strY);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1]-10, $strY);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[2]-7, $strY+1.5);
        $pdf->Cell(0, 0, $str3, 0, false, 'L');*/

       /* $strFont = '18';
        $strX = 85.1;
        $strY = 95.3;
        $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 105.3;
        $str = "UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 115.3;
        $str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');*/

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

        /*$strFontB = '16';
        $strFont = '16';
        $strY = 75;
       // $str1="DºÁgÀ «eÁÕ£À ªÀÄvÀÄÛ vÀAvÀæeÁÕ£ÀzÀ°è ¨ÁåZÀÄ®gï D¥sï mÉPÁß®f ¥ÀzÀ« ";
        $str1="gÉÃµÉäPÀÈ¶ «eÁÕ£ÀzÀ°è ¨ÁåZÀÄ®gï «eÁÕ£À ¥ÀzÀ« ";
        $str2="¥ÀzÀ«U ";
        $str3="CAVÃPÀj¸À¯ÁVzÉ";
        $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );
            
       
         
        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0]-2, $strY);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1]-2, $strY);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[2]-2, $strY);
        $pdf->Cell(0, 0, $str3, 0, false, 'L');*/
       
        $strFont = '18';
        $strX = 85.1;
        $strY = 85.3;
        $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 95;
        $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ";
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
        $str = "28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        //Template 01
        /*$strFont = '20';
        $strX = 55;
        $strY = 155;
        $str = "AWARD OF GOLD MEDAL";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 165;
        $str = "SUMA K";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 174;
        $str = "is awarded";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 55;
        $strY = 184.2;
        $str = "Campus Gold Medal (College of Agriculture, GKVK)";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '17.9';
        $strX = 55;
        $strY = 194;
        $str = "at the 54   Convocation of UAS, Bengalore held on";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(20, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '13';
        $strX = 55;
        $strY = 192.5;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(42.4, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '16.9';
        $strX = 55;
        $strY = 194;
        $str = "28.11.2020";
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(151, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 194;
        $str = "for";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(180, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 203.7;
        $str = "having secured the highest OGPA of";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(32, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '16.9';
        $strX = 55;
        $strY = 203.7;
        $str = "9.00";
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(126.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 203.7;
        $str = "out of 10.00 in";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(140, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFontB = '17';
        $strFont = '17.9';
        $strY = 213.5;
        $str1="Bachelor of Science (Agriculture) ";
        $str1="Bachelor of Science (Sericulture) ";
        $str1="Bachelor of Technology (Agril. Engg.) ";
        $str1="B. Tech. (Food Science & Technology) ";
        $str2="degree during the year ";
        $str3="2018-19";
             $result = $this->GetStringPositions(
                        array(
                            array($str1, $timesNewRomanBI, '', $strFontB), 
                            array($str2, $timesNewRomanI, '', $strFont),
                            array($str3, $timesNewRomanBI, '', $strFontB)
                        ),$pdf
                    );
                

                $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[0], $strY);
                $pdf->Cell(0, 0, $str1, 0, false, 'L');

                $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[1], $strY);
                $pdf->Cell(0, 0, $str2, 0, false, 'L');


                $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[2], $strY);
                $pdf->Cell(0, 0, $str3, 0, false, 'L');*/


         //Template 02
       /* $strFont = '20';
        $strX = 55;
        $strY = 155;
        $str = "AWARD OF GOLD MEDAL";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 165;
        $str = "SUMA K";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 174;
        $str = "is awarded";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

       $str='Dr.S.Javaregowda Gold medal';
       // echo strlen($str);
        $strArr = explode( "\n", wordwrap( $str, 65));
        $strFont = '18';
        $strX = 55;
        $strY = 183;
      //  $str = "Best Sportsman-cum-Scholar Award in Memory of Late Balachanda Nanaiah Ganapathy, Ex-student of UAS, Bangalore";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');
        $z=0;
        if(count($strArr)>1){
        $strFont = '18';
        $strX = 55;
        $strY = 192.5;
       // $str = "Papanna Memorial Gold Medal";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');
        }else{
        $z=9;
        }
       $strFont = '17.9';
        $strX = 55;
        $strY = 201.5;
        $str = "at the 54   Convocation of UAS, Bengalore held on";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(20, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '13';
        $strX = 55;
        $strY = 199.5;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(42.4, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '16.9';
        $strX = 55;
        $strY = 201.2;
        $str = "28.11.2020";
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(151, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 201.2;
        $str = "for";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(180, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 210.7;
        $str = "having secured the highest OGPA of";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(32, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '16.9';
        $strX = 55;
        $strY = 210.7;
        $str = "9.00";
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(126.5, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 210.7;
        $str = "out of 10.00 in";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(140, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $strFontB = '14';
        $strFont = '14';
        $strY = 219.5;
        $str1="Bachelor of Science (Agriculture) ";
        $str1="Bachelor of Science (Sericulture) ";
        $str1="Bachelor of Technology (Agril. Engg.) ";
        $str1="Bachelor of Science (Agriculture) degree ";
        $str2="during the year ";
        $str3="2018-19";
         $result = $this->GetStringPositions(
                    array(
                        array($str1, $timesNewRomanBI, '', $strFontB), 
                        array($str2, $timesNewRomanI, '', $strFont),
                        array($str3, $timesNewRomanBI, '', $strFontB)
                    ),$pdf
                );
            

            $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[0], $strY-$z);
            $pdf->Cell(0, 0, $str1, 0, false, 'L');

            $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[1], $strY-$z);
            $pdf->Cell(0, 0, $str2, 0, false, 'L');


            $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[2], $strY-$z);
            $pdf->Cell(0, 0, $str3, 0, false, 'L');
*/
       /* //Template 04
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
        $strY = 163.5;
        $str = "of the Board of Studies, (Post Graduate) of the Faculty of Agriculture";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 170.5;
        $str = "and the Academic Council";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 178;
        $str = "ABHISHEK K GOWDA";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 185.5;
        $str = "has been admitted to the degree of";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 193;
        $str = "Master of Science (Agriculture)";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 200.5;
        $str = "IN AGRICULTURAL ENTOMOLGY";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 208;
        $str = "and is entitled to all rights and honours therto appertaining";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 215.5;
        $str = "witness the seal of the University and the signatures of its";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 223;
        $str = "Officers this 28  day of November 2020.";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '13';
        $strX = 55;
        $strY = 222;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(92.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');*/
        
        //Template 05
        $strFont = '19';
        $strX = 55;
        $strY = 155.5;
        $str = "By authority of the Board of Management and upon recommendation";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        /*$strFont = '19';
        $strX = 55;
        $strY = 163.7;
        $str = "of the Board of Studies, Under Graduate, Faculty of Agriculture ";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');*/


          $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        
        $str1="of the Board of Studies, Under Graduate, ";
        $str2="Agriculture";
        $str = "of the Board of Studies, Under Graduate,  ".$str2;

        if(strlen($str)>65){

            $str=$str." "."and the Academic Council";
            $strArr = explode( "\n", wordwrap( $str, 65));

            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

        }else{
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
        }


        

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
        $str = "Officers this 28  day of November 2020";
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
        $pdf->SetXY(92.7, $strY);
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
            //->backgroundColor(255, 255, 0)
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        

                            $str = 'ABHISHEK K GOWDA';
                            $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
                            $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $str);
                            $strWidth = ($textArray[2] - $textArray[0]);
                            $strHeight = $textArray[6] - $textArray[1] / 1.4;
                            
                             $width=18;
                            $latestWidth = round($width*3.7795280352161);

                             //Updated by Mandar
                            $microlinestr=$str;
                            $microlinestrLength=strlen($microlinestr);

                            //width per character
                            $microLinecharacterWd =$strWidth/$microlinestrLength;

                            //Required no of characters required in string to match width
                             $microlinestrCharReq=$latestWidth/$microLinecharacterWd;
                            $microlinestrCharReq=round($microlinestrCharReq);
                           // echo '<br>';
                            //No of time string should repeated
                             $repeateMicrolineStrCount=$latestWidth/$strWidth;
                             $repeateMicrolineStrCount=round($repeateMicrolineStrCount)+1;

                            //Repeatation of string 
                             $microlinestrRep = str_repeat($microlinestr, $repeateMicrolineStrCount);
                           // echo strlen($microlinestrRep);
                            //Cut string in required characters (final string)
                            $array = substr($microlinestrRep,0,$microlinestrCharReq);

                            $wd = '';
                            $last_width = 0;
                            $pdf->SetFont($arialb, '', 1.2, '', false);
                            $pdf->SetTextColor(0, 0, 0);
                            $pdf->StartTransform();
                            // $pdf->SetXY(36.8, 146.6);
                            $pdf->SetXY(17.6, 252.5);
                            
                            $pdf->Cell(0, 0, $array, 0, false, 'L');

                       
       /* //micro line name
        // $microlinestr = strtoupper(str_replace(' ','','SHANTANU TOMARBCHELOR OF TECHNOLOGYChemical Engineering'));
        $microlinestr = strtoupper(str_replace(' ','','ABHISHEK K GOWDA'));
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $microlinestr);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        // $latestWidth = 557;
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
        // $pdf->SetXY(36.8, 146.6);
        $pdf->SetXY(17.6, 252.5);
        
        $pdf->Cell(0, 0, $array, 0, false, 'L');

        $pdf->StopTransform();*/

        $pdf->Output('sample.pdf', 'I');  
    }

    public function databaseGenerate(){

        $template_data =array("id"=>5,"template_name"=>"Degree Certificate");
        
       //Query FOR UGPG 
         //Comment this only for GOLD
        /*$fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT degree_eng, COUNT(sr_no) as sr_no_count FROM ug_pg WHERE ugpg_eng="POST Graduate" AND spec_eng="IN SOIL AND WATER ENGINEERING" AND STATUS="0" GROUP BY degree_eng ORDER BY sr_no_count ASC LIMIT 1') );*/

        // $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT spec_kan, length(spec_kan) as spec_eng_len FROM gold WHERE STATUS="0" GROUP BY spec_kan ORDER BY spec_eng_len Desc limit 10') );
    
        
     
        //Comment this only for GOLD
        /*foreach ($fetch_degree_data_prog_spec as $value) {
           $degree_eng =$value->degree_eng;
           $cert_cnt =$value->sr_no_count;*/
           //$spec_eng =$value->spec_eng;
          
            //Query FOR UGPG 
            // $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM ug_pg WHERE degree_eng ="'.$value->degree_eng.'" AND status="0" AND spec_eng="IN SOIL AND WATER ENGINEERING"'));sr_no="54-1014"

        //Query FOR GOLD AND (sr_no="54-1065" OR sr_no="54-1060" OR sr_no="54-1083" )
        $fetch_degree_data = (array)DB::select( DB::raw('SELECT *,CONCAT_WS(" ", faculty_kan, degree_kan) as word FROM `gold` WHERE type ="UG" AND (sr_no="54-1014") order by length(word) DESC LIMIT 1'));
        $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();
             
            //Uncomment only for gold 
            $cert_cnt =count($fetch_degree_data);


           // $fetch_degree_array=array();
        // dd($fetch_degree_data);
        $admin_id = \Auth::guard('admin')->user()->toArray();
        // dd($fetch_degree_data);
        // $fetch_degree_array[] = array_values($fetch_degree_data[0]);
/*        $fetch_degree_data_temp=array();
       foreach ($fetch_degree_data as $readData) {
            $fetch_degree_data_temp[] = $readData;
        }
        print_r($fetch_degree_data_temp);*/
     
        // dd($fetch_degree_array);
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        // dd($systemConfig);
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


       
      //  $pdfBig->SetProtection(array('modify', 'copy','annot-forms','fill-forms','extract','assemble'),'',null,0,null);

        $log_serial_no = 1;


        for($excel_row = 0; $excel_row < count($fetch_degree_data); $excel_row++)
        {   

            $serial_no = trim($fetch_degree_data[$excel_row]['sr_no']);
/*
            $file0 = str_replace("/", " ", $row["id_number"]);
    $file1 = str_replace(" ","",$row["id_number"]);
    $file2 = $row["id_number"];
    if((!file_exists($path.$file1.".jpg")) && (!file_exists($path.$file1.".jpeg"))
        && (!file_exists($path.$file2.".jpg")) && (!file_exists($path.$file2.".jpeg"))
        && (!file_exists($path.$file0.".jpg")) && (!file_exists($path.$file0.".jpeg"))){
        $not1[] = $row["id_number"]; echo $file1;
    }*/
       
            $fetch_degree_data[$excel_row]["id_number"]="PALB 7118";
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
            }elseif(file_exists($path.$file1.".jpeg")){
                $profile_path =$path.$file1.".jpeg";
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
            // $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\DO_Background Light.jpg';
            // dd($template_img_generate);
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
           
            // $print_serial_no = $this->nextPrintSerial();

            $pdfBig->AddPage();

             $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

         //   $pdfBig->SetProtection($permissions = array('modify', 'copy', 'annot-forms', 'fill-forms', 'extract', 'assemble'), '', null, 0, null);

            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            // dd($rowData);
            

            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

            //profile photo
        
        //$profilex = 182;
        /*$profilex = 179.5;
        $profiley = 5.8;
        $profileWidth = 21.60;
        $profileHeight = 27;*/

        $profilex = 181.5;
        $profiley = 8.3;
        $profileWidth = 19.20;
        $profileHeight = 24;

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
        if(strpos($fetch_degree_data[$excel_row]['id_number'], '/') !== false){
            $enrollmentx=172;
        }
        $enrollmenty = 37.5;
        $enrollmentstr = $fetch_degree_data[$excel_row]['id_number'];
        $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdf->SetXY($enrollmentx, $enrollmenty);
        $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');

        $pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdfBig->SetXY($enrollmentx, $enrollmenty);
        $pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');


        if(!isset($fetch_degree_data[$excel_row]['ugpg_eng'])){
            $fetch_degree_data[$excel_row]['ugpg_eng']="GOLD";
        }
       /* print_r($fetch_degree_data[$excel_row]['ugpg_eng']);
        exit;*/
            //UG
        if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Under Graduate"){
            

            //Template 05
        /*$strFont = '18';
        $strX = 85.1;
        $strY = 48;
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ PÀÈ¶ «eÁÕ£À ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";
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
        */
        $minus=0;
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Technology (Agricultural Engineering)"){
            $minus=1.5;
        }
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2=$fetch_degree_data[$excel_row]['faculty_kan'];
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
        
        
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Sericulture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agriculture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agricultural Biotechnology)"){
        $strFont = '23';
        $strX = 55;
        $strY = 75+1.2-$minus;
        $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
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

            if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Sericulture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agriculture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agricultural Biotechnology)"){
            
        $strFont = '18';
        $strFontB = '23';
        $strX = 55;
        $strY = 75+1.2-$minus;
        $str1=trim($fetch_degree_data[$excel_row]['degree_kan'])." ";
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
        //$str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
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

        /*$strFont = '19';
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
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');*/

          $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        
        $str1="of the Board of Studies, Under Graduate, Faculty of ";
        $str2=$fetch_degree_data[$excel_row]['faculty_eng'];
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


        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['spec_eng'])!="IN SOIL AND WATER ENGINEERING"){
      
            $strYDegKan=75+1.2;
            $strYSpecKan=85+1.2;

             $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = trim($fetch_degree_data[$excel_row]['degree_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYDegKan);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strYDegKan);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

      /*  $strFont = '20';
        $strX = 85.1;
        $strY = 85+1.2;
        $str = trim($fetch_degree_data[$excel_row]['spec_kan'])."  CAVÃPÀj¸À¯ÁVzÉ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYSpecKan);
        $pdf->Cell(0, 0, $strYSpecKan, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');*/

         $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['spec_kan']." ";
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
        }elseif(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['spec_eng'])=="IN SOIL AND WATER ENGINEERING"){
             $strYSpecKan=85+1.2;
            $strYDegKan=75+1.2;
             $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
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
        $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
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
        $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
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
        $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
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

        }else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="GOLD"){
         
        //Template 02
        if(trim($fetch_degree_data[$excel_row]['type'])=="UG"){    
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
           // $strY = 56.5;
            $str =trim($fetch_degree_data[$excel_row]['name_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,92,88,7,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,92,88,7,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            if(strlen(trim($fetch_degree_data[$excel_row]['spec_kan']))>90){
                $strFont = '16.5';
                $strY = $strY+14.3;//69.3
            }else{
               $strFont = '20'; 
               $strY = $strY+13.3;//68.3
            }
            
            $strX = 93;
            
            $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
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
            $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
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
           // $strY = 78;
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
            //$strY = 78;
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
            //$strY = 77;
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
       // $strY = 85.5;
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
        //$strY = 85.5;
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
        $strY = $strY+8.5;
        $str ="PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
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
        $strY = $strY+8.5;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }else{

        

        //PG & PHD
        $strY = 44;
        $strFont = '30';
        $strX = 85.1;
        $strY = 45;
        $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±À¹Û"; 
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '30';
        $strX = 85.1;
        $strY = 57;
        $str =trim($fetch_degree_data[$excel_row]['name_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,92,88,7,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');


        $str = trim($fetch_degree_data[$excel_row]['faculty_kan'])." ".trim($fetch_degree_data[$excel_row]['degree_kan']);

        if(strlen($str)>80){

           // echo strlen($str);
            $strArr = explode( "\n", wordwrap( $str, 120));
         /*   print_r($strArr);
            exit;*/
             $strFont = '20';
            $strX = 85.1;
            $strY = 69.5;
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');
            

            $strFont = '20';
            $strFont1 = '18';
            $strY=80;    
            $str1=$strArr[1];
            $str2=" ".trim($fetch_degree_data[$excel_row]['year_degree']);
            $str3="£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
            $str4 = trim($fetch_degree_data[$excel_row]['ogpa']);
            $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFont), 
                        array($str2, $nudi01eb, '', $strFont), 
                        array($str3, $nudi01e, '', $strFont), 
                        array($str4, $nudi01eb, '', $strFont)

                    ),$pdf
                );
            

            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[0], $strY);
            $pdf->Cell(0, 0, $str1, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[0], $strY);
            $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

            $pdf->SetFont($nudi01eb, 'B', $strFont1, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[1], $strY);
            $pdf->Cell(0, 0, $str2, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont1, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[1], $strY);
            $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

            $pdf->SetFont($nudi01eb, '', $strFont1, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[2], $strY);
            $pdf->Cell(0, 0, $str3, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont1, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[2], $strY);
            $pdfBig->Cell(0, 0, $str3, 0, false, 'L');

            $pdf->SetFont($nudi01eb, 'B', $strFont1, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[3]-5, $strY);
            $pdf->Cell(0, 0, $str4, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont1, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[3]-5, $strY);
            $pdfBig->Cell(0, 0, $str4, 0, false, 'L');

            $strFont = '18';
            $strY=89;    
            $str1="CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ ±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAPÀ";
            $str2 = " 28.11.2020";
            $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01e, '', $strFont), 
                        array($str2, $nudi01eb, '', $strFont), 

                    ),$pdf
                );

             $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[0], $strY);
            $pdf->Cell(0, 0, $str1, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[0], $strY);
            $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[1], $strY);
            $pdf->Cell(0, 0, $str2, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[1], $strY);
            $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

             $strFont = '18';
            $strX = 55;
            $strY = 98;
            $str ="gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }else{

            $strFont = '20';
            $strX = 85.1;
            $strY = 69.5;
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
        $strY = 78;
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
        $strY = 78;
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
       // $strY = 76;
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
        //$strY = 76;
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
        $strY = 89;
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
       // $strY = 85.5;
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
        //$strY = 85.5;
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
        $strY = 98;
        $str ="PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }

         /*$strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['faculty_kan']." ";
        $str2=$fetch_degree_data[$excel_row]['degree_kan']." ";
       
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
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');*/

        

        

        $strFont = '18';
        $strX = 55;
        $strY = 108;
        $str=trim($fetch_degree_data[$excel_row]['spec_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 117;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');
    }

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
        $str = trim($fetch_degree_data[$excel_row]['name_eng']);
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

       $str=trim($fetch_degree_data[$excel_row]['spec_eng']);
       // echo strlen($str);
        $strArr = explode( "\n", wordwrap( $str, 58));
        $strFont = '18';
        $strX = 55;
        $strY = 183;
      //  $str = "Best Sportsman-cum-Scholar Award in Memory of Late Balachanda Nanaiah Ganapathy, Ex-student of UAS, Bangalore";
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
       // $str = "Papanna Memorial Gold Medal";
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

        if($fetch_degree_data[$excel_row]['type']!="UG"){
        $strFont = '17.9';
        $str=trim($fetch_degree_data[$excel_row]['degree_eng'])." ".trim($fetch_degree_data[$excel_row]['faculty_eng']);

        if(strlen($str)>70){
             $strArr = explode( "\n", wordwrap( $str, 65));

             $strX = 55;
            $strY = 219;
            //$str = trim($fetch_degree_data[$excel_row]['degree_eng']);
            $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY-$z);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY-$z);
            $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

             $strY = 227;
             $str1=$strArr[1];
             $str2=" during the year ";
            $str3=trim($fetch_degree_data[$excel_row]['year_degree']);
         $result = $this->GetStringPositions(
                    array(
                        array($str1, $timesNewRomanBI, '', $strFont), 
                        array($str2, $timesNewRomanI, '', $strFont),
                        array($str3, $timesNewRomanBI, '', $strFont)
                    ),$pdf
                );
            

            $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[0], $strY-$z);
            $pdf->Cell(0, 0, $str1, 0, false, 'L');

            $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[0], $strY-$z);
            $pdfBig->Cell(0, 0, $str1, 0, false, 'L');


            $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[1], $strY-$z);
            $pdf->Cell(0, 0, $str2, 0, false, 'L');

           $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[1], $strY-$z);
            $pdfBig->Cell(0, 0, $str2, 0, false, 'L'); 

            $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY($result[2], $strY-$z);
            $pdf->Cell(0, 0, $str3, 0, false, 'L');

           $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY($result[2], $strY-$z);
            $pdfBig->Cell(0, 0, $str3, 0, false, 'L'); 

        }else{
        $strFont = '17.9';
        $strX = 55;
        $strY = 219;
        //$str = trim($fetch_degree_data[$excel_row]['degree_eng']);
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
      
       // $str1=trim($fetch_degree_data[$excel_row]['degree_eng']);
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
            }else{

                 $strFont = '17.9';
        $strX = 55;
        $strY = 219;
        $str = trim($fetch_degree_data[$excel_row]['degree_eng']);
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
      
       // $str1=trim($fetch_degree_data[$excel_row]['degree_eng']);
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
            //->backgroundColor(255, 255, 0)
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        
        //micro line name
    
       $str = $fetch_degree_data[$excel_row]['name_eng'];
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $str);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
         $width=18;
        $latestWidth = round($width*3.7795280352161);

         //Updated by Mandar
        $microlinestr=$str;
        $microlinestrLength=strlen($microlinestr);

        //width per character
        $microLinecharacterWd =$strWidth/$microlinestrLength;

        //Required no of characters required in string to match width
         $microlinestrCharReq=$latestWidth/$microLinecharacterWd;
        $microlinestrCharReq=round($microlinestrCharReq);
       // echo '<br>';
        //No of time string should repeated
         $repeateMicrolineStrCount=$latestWidth/$strWidth;
         $repeateMicrolineStrCount=round($repeateMicrolineStrCount)+1;

        //Repeatation of string 
         $microlinestrRep = str_repeat($microlinestr, $repeateMicrolineStrCount);
       // echo strlen($microlinestrRep);
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


              
            $certName = str_replace("/", "_", $serial_no) .".pdf";
            // $myPath =    ().'/backend/temp_pdf_file';
            //$myPath = public_path().'/backend/temp_pdf_file';
            $myPath = public_path().'/backend/temp_pdf_file';
            $dt = date("_ymdHis");
            // print_r($pdf);
            // print_r("$tmpDir/" . $name."".$ghost_font_size.".png");
           /* $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');


           $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

    
        copy($file1, $file2);
        
        $aws_qr = \File::copy($file2,public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName);
                // $msg = "<b>PDF will be sent in mail<b>";
            
        @unlink($file2);*/

       // @unlink($file1);

            // dd($fetch_degree_data[$excel_row][0]);
            // Degree::where('GUID',$fetch_degree_data[$excel_row][0])->update(['status'=>'1']);

            /*$this->addCertificate($serial_no, $certName, $dt,$template_data['id'],$admin_id);

            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            $print_datetime = date("Y-m-d H:i:s");
            

            $print_count = $this->getPrintCount($serial_no);
          //  $printer_name = $printer_name;
            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_data['template_name'],$admin_id);*/
            
            // Degree::where('GUID',$fetch_degree_data[$excel_row]['sr_no'])->update(['status'=>'1']);
            }else{
             //Degree::where('GUID',$fetch_degree_data[$excel_row]['sr_no'])->update(['status'=>'0']);;
              
            }

        }


        $msg = '';
        // if(is_dir($tmpDir)){
        //     rmdir($tmpDir);
        // }   
       // $file_name = $template_data['template_name'].'_'.date("Ymdhms").'.pdf';
        //print_r($fetch_degree_data);
      //  exit;

       //File name for UGPG
       // $file_name =  str_replace("/", "_",'ug_pg_'.$degree_eng.' '.$cert_cnt).'.pdf';
        
        //Un Comment only for GOLD File name for GOLD
        $file_name =  str_replace("/", "_",'gold '.$cert_cnt).'.pdf';
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        // $filename = 'C:\xampp\htdocs\seqr\public\backend\tcpdf\exmples\/'.$file_name;
        $pdfBig->output($filename,'F');

        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
        @unlink($filename);

      //Comment this only for GOLD
    //}
             exit();

    }

     public function dbUploadUgpg(){

        $template_data =array("id"=>5,"template_name"=>"Degree Certificate");
        
        $tableName ="ug_pg";
       //Query FOR UGPG 
         //Comment this only for GOLD
        $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT degree_eng, COUNT(sr_no) as sr_no_count FROM ug_pg WHERE ugpg_eng="POST Graduate" AND spec_eng="IN SOIL AND WATER ENGINEERING" AND STATUS="0" GROUP BY degree_eng ORDER BY sr_no_count ASC LIMIT 1') );

     
        //Comment this only for GOLD
        foreach ($fetch_degree_data_prog_spec as $value) {
           $degree_eng =$value->degree_eng;
           $cert_cnt =$value->sr_no_count;
           //$spec_eng =$value->spec_eng;
          
            //Query FOR UGPG 
             $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM ug_pg WHERE degree_eng ="'.$value->degree_eng.'" AND status="0" AND ugpg_eng="POST Graduate" AND spec_eng="IN SOIL AND WATER ENGINEERING"'));

             //Query FOR GOLD 
            // $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM gold WHERE  status="0"'));
            $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();
             
            //Uncomment only for gold 
            $cert_cnt =count($fetch_degree_data);


           // $fetch_degree_array=array();
        // dd($fetch_degree_data);
        $admin_id = \Auth::guard('admin')->user()->toArray();
        // dd($fetch_degree_data);
        // $fetch_degree_array[] = array_values($fetch_degree_data[0]);
/*        $fetch_degree_data_temp=array();
       foreach ($fetch_degree_data as $readData) {
            $fetch_degree_data_temp[] = $readData;
        }
        print_r($fetch_degree_data_temp);*/
     
        // dd($fetch_degree_array);
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        // dd($systemConfig);
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


       
      //  $pdfBig->SetProtection(array('modify', 'copy','annot-forms','fill-forms','extract','assemble'),'',null,0,null);

        $log_serial_no = 1;


        for($excel_row = 0; $excel_row < count($fetch_degree_data); $excel_row++)
        {  
         $serial_no = trim($fetch_degree_data[$excel_row]['sr_no']); 
/*
            $file0 = str_replace("/", " ", $row["id_number"]);
    $file1 = str_replace(" ","",$row["id_number"]);
    $file2 = $row["id_number"];
    if((!file_exists($path.$file1.".jpg")) && (!file_exists($path.$file1.".jpeg"))
        && (!file_exists($path.$file2.".jpg")) && (!file_exists($path.$file2.".jpeg"))
        && (!file_exists($path.$file0.".jpg")) && (!file_exists($path.$file0.".jpeg"))){
        $not1[] = $row["id_number"]; echo $file1;
    }*/
       
        //  $fetch_degree_data[$excel_row]["id_number"]="PALB 7118";
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
            }elseif(file_exists($path.$file1.".jpeg")){
                $profile_path =$path.$file1.".jpeg";
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
            // $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\DO_Background Light.jpg';
            // dd($template_img_generate);
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
           
            // $print_serial_no = $this->nextPrintSerial();

            $pdfBig->AddPage();
            //$template_img_generate_blank = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\blankbg.jpg';
            // $pdfBig->Image($template_img_generate_blank, 0, 0, '210', '297', "JPG", '', 'R', true);

         //   $pdfBig->SetProtection($permissions = array('modify', 'copy', 'annot-forms', 'fill-forms', 'extract', 'assemble'), '', null, 0, null);

            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            // dd($rowData);
           

            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

            //profile photo
        
        //$profilex = 182;
        /*$profilex = 179.5;
        $profiley = 5.8;
        $profileWidth = 21.60;
        $profileHeight = 27;*/

        $profilex = 181.5;
        $profiley = 8.3;
        $profileWidth = 19.20;
        $profileHeight = 24;
        
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
        if(strpos($fetch_degree_data[$excel_row]['id_number'], '/') !== false){
            $enrollmentx=172;
        }
        $enrollmenty = 37.5;
        $enrollmentstr = $fetch_degree_data[$excel_row]['id_number'];
        $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdf->SetXY($enrollmentx, $enrollmenty);
        $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');

        $pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdfBig->SetXY($enrollmentx, $enrollmenty);
        $pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');


        if(!isset($fetch_degree_data[$excel_row]['ugpg_eng'])){
            $fetch_degree_data[$excel_row]['ugpg_eng']="GOLD";
        }
       /* print_r($fetch_degree_data[$excel_row]['ugpg_eng']);
        exit;*/
            //UG
        if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Under Graduate"){
            

            //Template 05
        /*$strFont = '18';
        $strX = 85.1;
        $strY = 48;
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ PÀÈ¶ «eÁÕ£À ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";
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
        */
        $minus=0;
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Technology (Agricultural Engineering)"){
            $minus=1.5;
        }
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2=$fetch_degree_data[$excel_row]['faculty_kan'];
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
        
        
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Sericulture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agriculture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agricultural Biotechnology)"){
        $strFont = '23';
        $strX = 55;
        $strY = 75+1.2-$minus;
        $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
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

            if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Sericulture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agriculture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agricultural Biotechnology)"){
            
        $strFont = '18';
        $strFontB = '23';
        $strX = 55;
        $strY = 75+1.2-$minus;
        $str1=trim($fetch_degree_data[$excel_row]['degree_kan'])." ";
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
        //$str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
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

        /*$strFont = '19';
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
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');*/

          $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        
        $str1="of the Board of Studies, Under Graduate, Faculty of ";
        $str2=$fetch_degree_data[$excel_row]['faculty_eng'];
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

        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['spec_eng'])!="IN SOIL AND WATER ENGINEERING"){
            
            
            $strYDegKan=75+1.2;
            $strYSpecKan=85+1.2;

             $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = trim($fetch_degree_data[$excel_row]['degree_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYDegKan);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strYDegKan);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

      /*  $strFont = '20';
        $strX = 85.1;
        $strY = 85+1.2;
        $str = trim($fetch_degree_data[$excel_row]['spec_kan'])."  CAVÃPÀj¸À¯ÁVzÉ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYSpecKan);
        $pdf->Cell(0, 0, $strYSpecKan, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');*/

         $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['spec_kan']." ";
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
        }elseif(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['spec_eng'])=="IN SOIL AND WATER ENGINEERING"){
             $strYSpecKan=85+1.2;
            $strYDegKan=75+1.2;
             $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
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
        $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
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
        $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYDegKan);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strYDegKan);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

      /*  $strFont = '20';
        $strX = 85.1;
        $strY = 85+1.2;
        $str = trim($fetch_degree_data[$excel_row]['spec_kan'])."  CAVÃPÀj¸À¯ÁVzÉ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYSpecKan);
        $pdf->Cell(0, 0, $strYSpecKan, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');*/

         $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
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

        }else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="GOLD"){
         


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
       // $strY = 56.5;
        $str =trim($fetch_degree_data[$excel_row]['name_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,92,88,7,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        if(strlen(trim($fetch_degree_data[$excel_row]['spec_kan']))>90){
            $strFont = '16.5';
            $strY = $strY+14.3;//69.3
        }else{
           $strFont = '20'; 
           $strY = $strY+13.3;//68.3
        }
        
        $strX = 93;
        
        $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
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
        $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
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
       // $strY = 78;
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
        //$strY = 78;
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
        //$strY = 77;
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
        //$strY = 87.5;
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
        //$strY = 87.5;
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
        $str = trim($fetch_degree_data[$excel_row]['name_eng']);
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

       $str=trim($fetch_degree_data[$excel_row]['spec_eng']);
       // echo strlen($str);
        $strArr = explode( "\n", wordwrap( $str, 58));
        $strFont = '18';
        $strX = 55;
        $strY = 183;
      //  $str = "Best Sportsman-cum-Scholar Award in Memory of Late Balachanda Nanaiah Ganapathy, Ex-student of UAS, Bangalore";
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
       // $str = "Papanna Memorial Gold Medal";
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
        $str = trim($fetch_degree_data[$excel_row]['degree_eng']);
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
      
       // $str1=trim($fetch_degree_data[$excel_row]['degree_eng']);
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
        $codeContents = $fetch_degree_data[$excel_row]['id_number']."\n".$fetch_degree_data[$excel_row]['name_eng']."\n".$fetch_degree_data[$excel_row]['degree_eng']."\n\n".$encryptedString;

        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 17;
        $qrCodey = 234.8;
        $qrCodeWidth =19.3;
        $qrCodeHeight = 19.3;
                
        \QrCode::size(75.6)
            //->backgroundColor(255, 255, 0)
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        
        //micro line name
    
       $str = $fetch_degree_data[$excel_row]['name_eng'];
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $str);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
         $width=18;
        $latestWidth = round($width*3.7795280352161);

         //Updated by Mandar
        $microlinestr=$str;
        $microlinestrLength=strlen($microlinestr);

        //width per character
        $microLinecharacterWd =$strWidth/$microlinestrLength;

        //Required no of characters required in string to match width
         $microlinestrCharReq=$latestWidth/$microLinecharacterWd;
        $microlinestrCharReq=round($microlinestrCharReq);
       // echo '<br>';
        //No of time string should repeated
         $repeateMicrolineStrCount=$latestWidth/$strWidth;
         $repeateMicrolineStrCount=round($repeateMicrolineStrCount)+1;

        //Repeatation of string 
         $microlinestrRep = str_repeat($microlinestr, $repeateMicrolineStrCount);
       // echo strlen($microlinestrRep);
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


              
            $certName = str_replace("/", "_", $serial_no) .".pdf";
            // $myPath =    ().'/backend/temp_pdf_file';
            //$myPath = public_path().'/backend/temp_pdf_file';
            $myPath = public_path().'/backend/temp_pdf_file';
            $dt = date("_ymdHis");
            // print_r($pdf);
            // print_r("$tmpDir/" . $name."".$ghost_font_size.".png");
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
            
            DB::update( DB::raw('UPDATE '.$tableName.' SET status="1" WHERE sr_no ="'.$serial_no.'"'));
            }else{
             DB::update( DB::raw('UPDATE '.$tableName.' SET status="2" WHERE sr_no ="'.$serial_no.'"'));
              
            }

        }


        $msg = '';
        // if(is_dir($tmpDir)){
        //     rmdir($tmpDir);
        // }   
       // $file_name = $template_data['template_name'].'_'.date("Ymdhms").'.pdf';
        //print_r($fetch_degree_data);
      //  exit;

       //File name for UGPG
        $file_name =  str_replace("/", "_",'ug_pg_'.$degree_eng.' '.$cert_cnt).'.pdf';
        
        //File name for GOLD
        //$file_name =  str_replace("/", "_",'gold '.$cert_cnt).'.pdf';
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        // $filename = 'C:\xampp\htdocs\seqr\public\backend\tcpdf\exmples\/'.$file_name;
        $pdfBig->output($filename,'F');

        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
        @unlink($filename);

      //Comment this only for GOLD
    }
            // exit();

    }

     public function dbUploadGold(){

        $template_data =array("id"=>5,"template_name"=>"Degree Certificate");
        
       //Query FOR UGPG 
         //Comment this only for GOLD
       // $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT degree_eng, COUNT(sr_no) as sr_no_count FROM ug_pg WHERE ugpg_eng="Under Graduate" AND STATUS="0" GROUP BY degree_eng ORDER BY sr_no_count ASC') );

         //$fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT spec_kan, length(spec_kan) as spec_eng_len FROM gold WHERE STATUS="0" GROUP BY spec_kan ORDER BY spec_eng_len Desc limit 10') );
    
        
     
        //Comment this only for GOLD
       /* foreach ($fetch_degree_data_prog_spec as $value) {
           $degree_eng =$value->degree_eng;
           $cert_cnt =$value->spec_eng_len;
           $spec_eng =$value->spec_eng;*/
          
            //Query FOR UGPG 
           //  $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM ug_pg WHERE degree_eng ="'.$value->degree_eng.'" AND status="0"'));
            $tableName ="gold";
             //Query FOR GOLD 
             $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM gold WHERE  status="0" sr_no="54-1014" AND type = "UG" limit 1' ));
            $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();
             
            //Uncomment only for gold 
            $cert_cnt =count($fetch_degree_data);


           // $fetch_degree_array=array();
        // dd($fetch_degree_data);
        $admin_id = \Auth::guard('admin')->user()->toArray();
        // dd($fetch_degree_data);
        // $fetch_degree_array[] = array_values($fetch_degree_data[0]);
/*        $fetch_degree_data_temp=array();
       foreach ($fetch_degree_data as $readData) {
            $fetch_degree_data_temp[] = $readData;
        }
        print_r($fetch_degree_data_temp);*/
     
        // dd($fetch_degree_array);
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        // dd($systemConfig);
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


       
      //  $pdfBig->SetProtection(array('modify', 'copy','annot-forms','fill-forms','extract','assemble'),'',null,0,null);

        $log_serial_no = 1;


        for($excel_row = 0; $excel_row < count($fetch_degree_data); $excel_row++)
        {   


             $serial_no = trim($fetch_degree_data[$excel_row]['sr_no']);
/*
            $file0 = str_replace("/", " ", $row["id_number"]);
    $file1 = str_replace(" ","",$row["id_number"]);

    $file2 = $row["id_number"];
    if((!file_exists($path.$file1.".jpg")) && (!file_exists($path.$file1.".jpeg"))
        && (!file_exists($path.$file2.".jpg")) && (!file_exists($path.$file2.".jpeg"))
        && (!file_exists($path.$file0.".jpg")) && (!file_exists($path.$file0.".jpeg"))){
        $not1[] = $row["id_number"]; echo $file1;
    }*/
       
            //$fetch_degree_data[$excel_row]["id_number"]="PALB 7118";
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
            }elseif(file_exists($path.$file1.".jpeg")){
                $profile_path =$path.$file1.".jpeg";
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
            // $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\DO_Background Light.jpg';
            // dd($template_img_generate);
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
           
           //  $print_serial_no = $this->nextPrintSerial();

            $pdfBig->AddPage();

             //$pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

         //   $pdfBig->SetProtection($permissions = array('modify', 'copy', 'annot-forms', 'fill-forms', 'extract', 'assemble'), '', null, 0, null);

            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            // dd($rowData);
           

            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

            //profile photo
        
        //$profilex = 182;
        /*$profilex = 179.5;
        $profiley = 5.8;
        $profileWidth = 21.60;
        $profileHeight = 27;*/

        $profilex = 181.5;
        $profiley = 8.3;
        $profileWidth = 19.20;
        $profileHeight = 24;
        
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
        if(strpos($fetch_degree_data[$excel_row]['id_number'], '/') !== false){
            $enrollmentx=172;
        }
        $enrollmenty = 37.5;
        $enrollmentstr = $fetch_degree_data[$excel_row]['id_number'];
        $pdf->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdf->SetXY($enrollmentx, $enrollmenty);
        $pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');

        $pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
        $pdfBig->SetXY($enrollmentx, $enrollmenty);
        $pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');


        if(!isset($fetch_degree_data[$excel_row]['ugpg_eng'])){
            $fetch_degree_data[$excel_row]['ugpg_eng']="GOLD";
        }
       /* print_r($fetch_degree_data[$excel_row]['ugpg_eng']);
        exit;*/
            //UG
        if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Under Graduate"){
            

            //Template 05
        /*$strFont = '18';
        $strX = 85.1;
        $strY = 48;
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ PÀÈ¶ «eÁÕ£À ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";
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
        */
        $minus=0;
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Technology (Agricultural Engineering)"){
            $minus=1.5;
        }
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2=$fetch_degree_data[$excel_row]['faculty_kan'];
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
        
        
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Sericulture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agriculture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agricultural Biotechnology)"){
        $strFont = '23';
        $strX = 55;
        $strY = 75+1.2-$minus;
        $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
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

            if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Sericulture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agriculture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agricultural Biotechnology)"){
            
        $strFont = '18';
        $strFontB = '23';
        $strX = 55;
        $strY = 75+1.2-$minus;
        $str1=trim($fetch_degree_data[$excel_row]['degree_kan'])." ";
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
        //$str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
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

        /*$strFont = '19';
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
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');*/

          $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        
        $str1="of the Board of Studies, Under Graduate, Faculty of ";
        $str2=$fetch_degree_data[$excel_row]['faculty_eng'];
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

        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="IN SOIL AND WATER ENGINEERING"){
            
            
            $strYDegKan=75+1.2;
            $strYSpecKan=85+1.2;

             $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = trim($fetch_degree_data[$excel_row]['degree_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYDegKan);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strYDegKan);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

      /*  $strFont = '20';
        $strX = 85.1;
        $strY = 85+1.2;
        $str = trim($fetch_degree_data[$excel_row]['spec_kan'])."  CAVÃPÀj¸À¯ÁVzÉ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYSpecKan);
        $pdf->Cell(0, 0, $strYSpecKan, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');*/

         $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['spec_kan']." ";
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
        $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYDegKan);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strYDegKan);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

      /*  $strFont = '20';
        $strX = 85.1;
        $strY = 85+1.2;
        $str = trim($fetch_degree_data[$excel_row]['spec_kan'])."  CAVÃPÀj¸À¯ÁVzÉ";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYSpecKan);
        $pdf->Cell(0, 0, $strYSpecKan, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');*/

         $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
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

        }else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="GOLD"){
         
        //Template 02
        if(trim($fetch_degree_data[$excel_row]['type'])=="UG"){    
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
           // $strY = 56.5;
            $str =trim($fetch_degree_data[$excel_row]['name_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,92,88,7,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,92,88,7,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            if(strlen(trim($fetch_degree_data[$excel_row]['spec_kan']))>90){
                $strFont = '16.5';
                $strY = $strY+14.3;//69.3
            }else{
               $strFont = '20'; 
               $strY = $strY+13.3;//68.3
            }
            
            $strX = 93;
            
            $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
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
            $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
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
           // $strY = 78;
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
            //$strY = 78;
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
            //$strY = 77;
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
       // $strY = 85.5;
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
        //$strY = 85.5;
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
        $strY = $strY+8.5;
        $str ="PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
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
        $strY = $strY+8.5;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }else{

        

        //PG & PHD
        $strY = 44;
        $strFont = '30';
        $strX = 85.1;
        $strY = 45;
        $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±À¹Û";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '30';
        $strX = 85.1;
        $strY = 57;
        $str =trim($fetch_degree_data[$excel_row]['name_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,92,88,7,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');


        $str = trim($fetch_degree_data[$excel_row]['faculty_kan'])." ".trim($fetch_degree_data[$excel_row]['degree_kan']);

        if(strlen($str)>120){

           // echo strlen($str);
            $strArr = explode( "\n", wordwrap( $str, 120));
         /*   print_r($strArr);
            exit;*/
             $strFont = '20';
            $strX = 85.1;
            $strY = 69.5;
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');
            

            $strFont = '20';
            $strFont1 = '18';
            $strY=80;    
            $str1=$strArr[1];
            $str2=" ".trim($fetch_degree_data[$excel_row]['year_degree']);
            $str3="£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
            $str4 = trim($fetch_degree_data[$excel_row]['ogpa']);
            $result = $this->GetStringPositions(
                    array(
                 