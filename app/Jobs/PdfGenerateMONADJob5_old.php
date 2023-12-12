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
class PdfGenerateMONADJob5
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
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];
        $photo_col=181;

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

        $card_serial_no="";
        $log_serial_no = 1;
        //$cardDetails=$this->getNextCardNo('monad');
        //$card_serial_no=$cardDetails->next_serial_no;
        $generated_documents=0;  
        foreach ($studentDataOrg as $studentData) {
         
         /*if($card_serial_no>999999&&$previewPdf!=1){
            echo "<h5>Your card series ended...!</h5>";
            exit;
         }*/
        
        $high_res_bg="Monad_University_Background.jpg"; //MonadUniversity_With data MONAD_BG.jpg
        $low_res_bg="MONAD_BG.jpg";
         $pdfBig->AddPage();
         $pdfBig->SetFont($arialNarrowB, '', 8, '', false);
        //set background image
          $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$high_res_bg;   

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
        //$pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        //$pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo

        $pdf->AddPage();        
        $print_serial_no = $this->nextPrintSerial();
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;

        if($previewPdf!=1){
        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        }
        $pdf->setPageMark();
        /*
        if($previewPdf!=1){            
            $x= 182;
            $y = 43;
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
        */
        //$pdf->SetFont($arialNarrowB, '', 9, '', false);
        //$pdfBig->SetFont($arialNarrowB, '', 9, '', false);        
        //images
        if($studentData[$photo_col]!=''){
            //path of photos
            $profile_path_org = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[$photo_col];        
            $path_info = pathinfo($profile_path_org);   
            $file_name = $path_info['filename'];
            $ext = $path_info['extension'];
            $uv_location = public_path()."/".$subdomain[0]."/backend/templates/100/".$file_name.'_uv.'.$ext;        
            if(!file_exists($uv_location)){  
                copy($profile_path_org, $uv_location);
            }
            if($ext == 'png'){
                $im = imagecreatefrompng($uv_location);
                if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0))
                {          
                    imagepng($im, $uv_location);
                    imagedestroy($im);
                }        
            }else if($ext == 'jpeg' || $ext == 'jpg'){
                $im = imagecreatefromjpeg($uv_location);       
                if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0))
                {          
                    imagejpeg($im, $uv_location);
                    imagedestroy($im);
                }        
            }
            $photox = 115;
            $photoy = 79;
            $photoWidth = 13;
            $photoHeight = 16;
            //$pdf->image($uv_location,$photox,$photoy,$photoWidth,$photoHeight,"",'','L',true,3600);          
            $pdfBig->image($uv_location,$photox,$photoy,$photoWidth,$photoHeight,"",'','L',true,3600); 
            $pdfBig->setPageMark();
            
            //set profile image   
            $profilex = 174.3;
            $profiley = 60;
            $profileWidth = 23;
            $profileHeight = 28;
            $pdf->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
            $pdfBig->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);  
            $pdf->setPageMark();
            $pdfBig->setPageMark();
        }
        $uvlogo_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\UV logo.png';
        $uvlogo_x = 98;
        $uvlogo_y = 228;
        $uvlogo_Width = 23;
        $uvlogo_Height = 28;
        //$pdf->image($uvlogo_path,$uvlogo_x,$uvlogo_y,$uvlogo_Width,$uvlogo_Height,"",'','L',true,3600);   
        $pdfBig->image($uvlogo_path,$uvlogo_x,$uvlogo_y,$uvlogo_Width,$uvlogo_Height,"",'','L',true,3600);
        $pdfBig->setPageMark();    

        $uvsign_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\UV Sign.png';
        $uvsign_x = 178; // 133
        $uvsign_y = 233; // 257
        $uvsign_Width = 25; // 16
        $uvsign_Height = 20; // 11
        //$pdf->image($uvsign_path,$uvsign_x,$uvsign_y,$uvsign_Width,$uvsign_Height,"",'','L',true,3600);          
        $pdfBig->image($uvsign_path,$uvsign_x,$uvsign_y,$uvsign_Width,$uvsign_Height,"",'','L',true,3600);
        $pdfBig->setPageMark();        
        
        //Table's Titles
        $pdf->SetXY(13, 96);
        $pdf->SetFont($arial, '', 11, '', false); 
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);  
        //1st 5 columns
        $pdf->MultiCell(30, 11, '', 1, 'C', 0, 0);
        $pdf->MultiCell(98, 11, '',1, 'L', 0, 0);
        $pdf->MultiCell(16, 11, '', 1, 'C', 0, 0);     
        $pdf->MultiCell(20, 11, '', 1, 'C', 0, 0);     
        $pdf->MultiCell(20, 11, '', 1, 'C', 0, 0);   
        
        $pdf->SetXY(13, 107);     
        $pdf->MultiCell(30, 10, '','LR', 'L', 0, 0);
        $pdf->MultiCell(98, 10, '','LRT', 'L', 0, 0);
        $pdf->MultiCell(16, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(20, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(20, 10, '', 'LRT', 'C', 0, 0);

        $pdf->SetFont($arialb, '', 10, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(18, 97.5);
        $pdf->MultiCell(20, 0, 'SUBJECT<br>CODE', 0, 'C', 0, 0, '', '', true, 0, true);
        $pdf->SetXY(68, 97.5);
        $pdf->Cell(0, 0, 'NAME OF THE SUBJECT', 0, false, 'L');    
        $pdf->SetXY(141.5, 97.5);
        $pdf->Cell(0, 0, 'CREDIT', 0, false, 'L');
        $pdf->SetXY(156.5, 97.5);
        $pdf->MultiCell(22, 0, 'GRADE<br>OBTAINED', 0, 'C', 0, 0, '', '', true, 0, true);
        $pdf->SetXY(176.5, 97.5);   
        $pdf->MultiCell(22, 0, 'CREDIT<br>OBTAINED', 0, 'C', 0, 0, '', '', true, 0, true);          
        
        //Table's Titles
        $pdfBig->SetXY(13, 96);
        $pdfBig->SetFont($arial, '', 11, '', false); 
        $pdfBig->SetFillColor(255, 255, 255);
        $pdfBig->SetTextColor(0, 0, 0);  
        //1st 5 columns
        $pdfBig->MultiCell(30, 11, '', 1, 'C', 0, 0);
        $pdfBig->MultiCell(98, 11, '',1, 'L', 0, 0);
        $pdfBig->MultiCell(16, 11, '', 1, 'C', 0, 0);     
        $pdfBig->MultiCell(20, 11, '', 1, 'C', 0, 0);     
        $pdfBig->MultiCell(20, 11, '', 1, 'C', 0, 0);  
        
        $pdfBig->SetXY(13, 107);     
        $pdfBig->MultiCell(30, 10, '','LR', 'L', 0, 0);
        $pdfBig->MultiCell(98, 10, '','LRT', 'L', 0, 0);
        $pdfBig->MultiCell(16, 10, '', 'LRT', 'C', 0, 0);
        $pdfBig->MultiCell(20, 10, '', 'LRT', 'C', 0, 0);
        $pdfBig->MultiCell(20, 10, '', 'LRT', 'C', 0, 0);

        $pdfBig->SetFont($arialb, '', 10, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->SetXY(18, 97.5);
        $pdfBig->MultiCell(20, 0, 'SUBJECT<br>CODE', 0, 'C', 0, 0, '', '', true, 0, true);
        $pdfBig->SetXY(68, 97.5);
        $pdfBig->Cell(0, 0, 'NAME OF THE SUBJECT', 0, false, 'L');    
        $pdfBig->SetXY(141.5, 97.5);
        $pdfBig->Cell(0, 0, 'CREDIT', 0, false, 'L');
        $pdfBig->SetXY(156.5, 97.5);
        $pdfBig->MultiCell(22, 0, 'GRADE<br>OBTAINED', 0, 'C', 0, 0, '', '', true, 0, true);
        $pdfBig->SetXY(176.5, 97.5);   
        $pdfBig->MultiCell(22, 0, 'CREDIT<br>OBTAINED', 0, 'C', 0, 0, '', '', true, 0, true);         
        
        $title1_x = 14.3;        
        $title1_colonx = 47.7;        
        $left_title1_y = 59.3;        
        $left_title2_y = 67.6;
        $left_title3_y = 76.4;
        $left_title4_y = 84.8;
        $left_str_x = 52.7;
        $title2_x = 113.3;
        $title2_colonx = 142.3;
        $right_str_x = 147;
        $title_font_size = '11';        
        $str_font_size = '11';       
        
        $enrollment_no = $studentData[1];
        $roll_no = $studentData[2];
        $candidate_name = $studentData[3];
        $father_name = $studentData[4];
        $programme_name = $studentData[5];        
        $semester = $studentData[6];
        $session1 = $studentData[7];
        $session2 = $studentData[179];
        //$IssueDate = $studentData[164];
        $date_of_issue = $studentData[182];
        $cgpa = $studentData[183];
        //Start pdf
        //Static Title
        $pdf->SetFont($arialb, '', 14, '', false);  
        
        // start invisible data 
        //$pdf->SetTextColor(255, 255, 0);
        //$pdf->SetXY($title1_x, $left_title1_y-7);
        //$pdf->Cell(0, 0, $enrollment_no, 0, false, 'L');
        // end invisible data
        
        $pdf->SetFont($arial, '', $title_font_size, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($title1_x, $left_title1_y);
        $pdf->Cell(0, 0, 'Enrollment No.', 0, false, 'L');
        $pdf->SetXY($title1_colonx, $left_title1_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');        
        
        $pdf->SetXY($title1_x, $left_title2_y);
        $pdf->Cell(0, 0, "Candidate's Name", 0, false, 'L');
        $pdf->SetXY($title1_colonx, $left_title2_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');
        
        $pdf->SetXY($title1_x, $left_title3_y);
        $pdf->Cell(0, 0, "Father's Name", 0, false, 'L');
        $pdf->SetXY($title1_colonx, $left_title3_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');
        
        $pdf->SetXY($title1_x, $left_title4_y);
        $pdf->Cell(0, 0, "Programme", 0, false, 'L');
        $pdf->SetXY($title1_colonx, $left_title4_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');        
        
        $pdf->SetXY($title2_x, $left_title1_y);
        $pdf->Cell(0, 0, 'Roll No.', 0, false, 'L');
        $pdf->SetXY($title2_colonx, $left_title1_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');
        
        $pdf->SetXY($title2_x, $left_title2_y-1);
        $pdf->Cell(0, 0, 'Year / Semester', 0, false, 'L');
        $pdf->SetXY($title2_colonx, $left_title2_y-1);
        $pdf->Cell(0, 0, ':', 0, false, 'L');
        
        $pdf->SetXY($title2_x, $left_title3_y-2.5);
        $pdf->Cell(0, 0, 'Session', 0, false, 'L');
        $pdf->SetXY($title2_colonx, $left_title3_y-2.5);
        $pdf->Cell(0, 0, ':', 0, false, 'L');
        
        //Dynamic values
        $pdf->SetFont($arialb, '', $str_font_size, '', false);        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($left_str_x, $left_title1_y);
        $pdf->Cell(0, 0, $enrollment_no, 0, false, 'L');
        $pdf->SetXY($left_str_x, $left_title2_y);
        $pdf->Cell(0, 0, $candidate_name, 0, false, 'L');
        $pdf->SetXY($left_str_x, $left_title3_y);
        $pdf->Cell(0, 0, $father_name, 0, false, 'L');
        $pdf->SetXY($left_str_x, $left_title4_y);
        //$pdf->Cell(0, 0, $programme_name, 0, false, 'L');
        $pdf->MultiCell(120, 10, $programme_name, 0, 'L', 0, 0);
        $pdf->SetXY($right_str_x, $left_title1_y);
        $pdf->Cell(0, 0, $roll_no, 0, false, 'L');
        $pdf->SetXY($right_str_x, $left_title2_y-1);
        $pdf->Cell(0, 0, $semester, 0, false, 'L');
        $pdf->SetXY($right_str_x, $left_title3_y-2.4);
        $pdf->Cell(0, 0, $session1, 0, false, 'L');
        $pdf->SetXY(127, $left_title3_y+3.5);
        $pdf->Cell(40.5, 0, $session2, 0, false, 'R');      
        //End pdf
        
        //Start pdfBig
        //Static Title
        $pdfBig->SetFont($arialb, '', 14, '', false); 
        
        // start invisible data
        $pdfBig->SetTextColor(255, 255, 0);        
        $pdfBig->SetXY($title1_x, $left_title1_y-7);
        $pdfBig->Cell(0, 0, $enrollment_no, 0, false, 'L');
        // end invisible data
        
        $pdfBig->SetFont($arial, '', $title_font_size, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->SetXY($title1_x, $left_title1_y);
        $pdfBig->Cell(0, 0, 'Enrollment No.', 0, false, 'L');
        $pdfBig->SetXY($title1_colonx, $left_title1_y);
        $pdfBig->Cell(0, 0, ':', 0, false, 'L');        
        
        $pdfBig->SetXY($title1_x, $left_title2_y);
        $pdfBig->Cell(0, 0, "Candidate's Name", 0, false, 'L');
        $pdfBig->SetXY($title1_colonx, $left_title2_y);
        $pdfBig->Cell(0, 0, ':', 0, false, 'L');
        
        $pdfBig->SetXY($title1_x, $left_title3_y);
        $pdfBig->Cell(0, 0, "Father's Name", 0, false, 'L');
        $pdfBig->SetXY($title1_colonx, $left_title3_y);
        $pdfBig->Cell(0, 0, ':', 0, false, 'L');
        
        $pdfBig->SetXY($title1_x, $left_title4_y);
        $pdfBig->Cell(0, 0, "Programme", 0, false, 'L');
        $pdfBig->SetXY($title1_colonx, $left_title4_y);
        $pdfBig->Cell(0, 0, ':', 0, false, 'L');        
        
        $pdfBig->SetXY($title2_x, $left_title1_y);
        $pdfBig->Cell(0, 0, 'Roll No.', 0, false, 'L');
        $pdfBig->SetXY($title2_colonx, $left_title1_y);
        $pdfBig->Cell(0, 0, ':', 0, false, 'L');
        
        $pdfBig->SetXY($title2_x, $left_title2_y-1);
        $pdfBig->Cell(0, 0, 'Year / Semester', 0, false, 'L');
        $pdfBig->SetXY($title2_colonx, $left_title2_y-1);
        $pdfBig->Cell(0, 0, ':', 0, false, 'L');
        
        $pdfBig->SetXY($title2_x, $left_title3_y-2.5);
        $pdfBig->Cell(0, 0, 'Session', 0, false, 'L');
        $pdfBig->SetXY($title2_colonx, $left_title3_y-2.5);
        $pdfBig->Cell(0, 0, ':', 0, false, 'L');
        
        //Dynamic values
        $pdfBig->SetFont($arialb, '', $str_font_size, '', false);        
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->SetXY($left_str_x, $left_title1_y);
        $pdfBig->Cell(0, 0, $enrollment_no, 0, false, 'L');
        $pdfBig->SetXY($left_str_x, $left_title2_y);
        $pdfBig->Cell(0, 0, $candidate_name, 0, false, 'L');
        $pdfBig->SetXY($left_str_x, $left_title3_y);
        $pdfBig->Cell(0, 0, $father_name, 0, false, 'L');
        $pdfBig->SetXY($left_str_x, $left_title4_y);
        //$pdfBig->Cell(0, 0, $programme_name, 0, false, 'L');
        $pdfBig->MultiCell(120, 10, $programme_name, 0, 'L', 0, 0);
        $pdfBig->SetXY($right_str_x, $left_title1_y);
        $pdfBig->Cell(0, 0, $roll_no, 0, false, 'L');
        $pdfBig->SetXY($right_str_x, $left_title2_y-1);
        $pdfBig->Cell(0, 0, $semester, 0, false, 'L');
        $pdfBig->SetXY($right_str_x, $left_title3_y-2.4);
        $pdfBig->Cell(0, 0, $session1, 0, false, 'L');
        $pdfBig->SetXY(127, $left_title3_y+3.5);
        $pdfBig->Cell(40.5, 0, $session2, 0, false, 'R');        
        //End pdfBig        
  
         

        $subjectData = array_slice($studentData, 8, 168); //start subject and end subject 
        $subjectsArr=array_chunk($subjectData, 14); //fields of one subject
        //print_r($subjectsArr);      exit;  
        
        $count_upto=count($subjectsArr);        
        //Count total subjects entered
        $actual_subj_count=0;
        for ($z=0; $z < $count_upto ; $z++) {
            if(!empty($subjectsArr[$z][0])){
                $actual_subj_count=$actual_subj_count+1;
            }
        }
        
        $strtd="";
        $lineCount=1;
        $total_rows_remain=$count_upto-$actual_subj_count;
        $subj_y=108;
        $cell_height=9;
        for ($i=0; $i < $actual_subj_count ; $i++) { 
            if($count_upto != $i+1){
                $cell_border='LR';  
                $cell_border_sub='R'; 
                $cell_border_empty='L';               
            }else{
                $cell_border='LRB';  
                $cell_border_sub='RB'; 
                $cell_border_empty='LB';
            }
            $numlines_pdf = $pdf->getNumLines($subjectsArr[$i][0], 98);
            $numlines_pdfBig = $pdfBig->getNumLines($subjectsArr[$i][0], 98);
            
            $pdf->SetXY(13, $subj_y);
            $pdf->SetFont($arial, '', 10, '', false);            
            $pdf->MultiCell(30, $cell_height, trim($subjectsArr[$i][0]), $cell_border, 'L', 0, 0);    
            if($numlines_pdf>2){
                $pdf->SetFont($arial, '', 9, '', false); 
            }
            $pdf->MultiCell(98, $cell_height, trim($subjectsArr[$i][1]), $cell_border, 'L', 0, 0);
            $pdf->SetFont($arial, '', 10, '', false); 
            $pdf->MultiCell(16, $cell_height, trim($subjectsArr[$i][13]), $cell_border, 'C', 0, 0);
            $pdf->MultiCell(20, $cell_height, trim($subjectsArr[$i][12]), $cell_border, 'C', 0, 0);
            $pdf->MultiCell(20, $cell_height, trim($subjectsArr[$i][13]), $cell_border, 'C', 0, 0);            
                        
            $pdfBig->SetXY(13, $subj_y);
            $pdfBig->SetFont($arial, '', 10, '', false);  
            $pdfBig->MultiCell(30, $cell_height, trim($subjectsArr[$i][0]),$cell_border, 'L', 0, 0);  
            if($numlines_pdfBig>2){
                $pdfBig->SetFont($arial, '', 9, '', false); 
            }
            $pdfBig->MultiCell(98, $cell_height, trim($subjectsArr[$i][1]), $cell_border, 'L', 0, 0);
            $pdfBig->SetFont($arial, '', 10, '', false); 
            $pdfBig->MultiCell(16, $cell_height, trim($subjectsArr[$i][13]), $cell_border, 'C', 0, 0);
            //$pdfBig->MultiCell(20, $cell_height, trim($subjectsArr[$i][12]), $cell_border, 'C', 0, 0);
            $pdfBig->MultiCell(20, $cell_height, trim($subjectsArr[$i][12]).'<br><span style="color:yellow;">'.trim($subjectsArr[$i][12]).'</span>', $cell_border, 'C', 0, 0, '', '', true, 0, true);
            $pdfBig->MultiCell(20, $cell_height, trim($subjectsArr[$i][13]), $cell_border, 'C', 0, 0);
            
            $subj_y=$subj_y+9;    
            $lineCount=$lineCount+1;            
        }
        
        if($count_upto != $actual_subj_count){
            for ($t=1; $t <= $total_rows_remain ; $t++){                
                if($total_rows_remain != $t){
                    $cell_border='LR';  
                    $cell_border_sub='R'; 
                    $cell_border_empty='L'; 
                    //$cell_height=10; 
					if($t==12){$cell_height=9.5;}else{$cell_height=10;}
                    $pdf->SetXY(13, $subj_y);
                    $pdfBig->SetXY(13, $subj_y);
                }else{
                    $cell_border='LRB';  
                    $cell_border_sub='RB'; 
                    $cell_border_empty='LB'; 
                    $cell_height=1;  //1
                    $pdf->SetXY(13, $subj_y-3);                    
                    $pdfBig->SetXY(13, $subj_y-3);                    
                } 
                
                $pdf->MultiCell(30, $cell_height, '', $cell_border, 'C', 0, 0);
                $pdf->MultiCell(98, $cell_height, '', $cell_border, 'C', 0, 0);
                $pdf->MultiCell(16, $cell_height, '', $cell_border, 'C', 0, 0);
                $pdf->MultiCell(20, $cell_height, '', $cell_border, 'C', 0, 0);
                $pdf->MultiCell(20, $cell_height, '', $cell_border, 'C', 0, 0);                
                
                $pdfBig->MultiCell(30, $cell_height, '', $cell_border, 'C', 0, 0);
                $pdfBig->MultiCell(98, $cell_height, '', $cell_border, 'C', 0, 0);
                $pdfBig->MultiCell(16, $cell_height, '', $cell_border, 'C', 0, 0);
                $pdfBig->MultiCell(20, $cell_height, '', $cell_border, 'C', 0, 0);
                $pdfBig->MultiCell(20, $cell_height, '', $cell_border, 'C', 0, 0);
                $subj_y=$subj_y+10;         
            }
			
        }
        
            //$pdf->SetXY(13, $subj_y);
			if($actual_subj_count==12){
				$pdf->SetXY(13, $subj_y);
				$pdfBig->SetXY(13, $subj_y);
			}else{
				$pdf->SetXY(13, $subj_y-8.5);
				$pdfBig->SetXY(13, $subj_y-8.5);
			}	
            $pdf->SetFont($arialb, '', 10, '', false);    
            $pdf->MultiCell(30, 6, '' ,1, 'L', 0, 0);
            //$pdf->setCellPaddings( $left = '', $top = '1', $right = '5', $bottom = '');
            $pdf->MultiCell(98, 6, '<span style="font-size:1em;">Total</span>', 1, 'R', 0, 0, '', '', true, 0, true);
            //$pdf->setCellPaddings( $left = '1', $top = '', $right = '0', $bottom = '');
            $pdf->MultiCell(16, 6, '<span style="font-size:1em;">'.$studentData[180].'</span>', 1, 'C', 0, 0, '', '', true, 0, true);
            $pdf->MultiCell(20, 6, '', 1, 'C', 0, 0); 
            $pdf->MultiCell(20, 6, '<span style="font-size:1em;">'.$studentData[180].'</span>', 1, 'C', 0, 0, '', '', true, 0, true); 
                        
			//$pdfBig->SetXY(13, $subj_y);    
            $pdfBig->SetFont($arialb, '', 10, '', false);    
            $pdfBig->MultiCell(30, 6, '' ,1, 'L', 0, 0);
            //$pdfBig->setCellPaddings( $left = '', $top = '1', $right = '5', $bottom = '');
            $pdfBig->MultiCell(98, 6, '<span style="font-size:1em;">Total</span>', 1, 'R', 0, 0, '', '', true, 0, true);
            //$pdfBig->setCellPaddings( $left = '1', $top = '', $right = '0', $bottom = '');
            $pdfBig->MultiCell(16, 6, '<span style="font-size:1em;">'.$studentData[180].'</span>', 1, 'C', 0, 0, '', '', true, 0, true);
            $pdfBig->MultiCell(20, 6, '', 1, 'C', 0, 0); 
            $pdfBig->MultiCell(20, 6, '<span style="font-size:1em;">'.$studentData[180].'</span>', 1, 'C', 0, 0, '', '', true, 0, true);        
        
//pdf        
        //Table 2
        $pdf->SetXY(13, 223);
        $pdf->SetFont($arialb, '', 10, '', false); 
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);  
        $pdf->MultiCell(40, 6, "Carry Over (if any)", 1, 'C', 0, 0);
        $pdf->MultiCell(84, 6, 'CREDITS OBTAINED / TOTAL CREDITS',1, 'C', 0, 0);
        $pdf->MultiCell(20, 6, 'SGPA', 1, 'C', 0, 0);     
        $pdf->MultiCell(20, 6, 'CGPA', 1, 'C', 0, 0);     
        $pdf->MultiCell(20, 6, 'Result', 1, 'C', 0, 0);
        $pdf->SetXY(13, 229);
        $pdf->SetFont($arial, '', 10, '', false); 
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);  
        $pdf->MultiCell(40, 6, "Subject(s) Code", 1, 'C', 0, 0);
        $pdf->MultiCell(84, 6, $studentData[180] ."/". $studentData[180], 1, 'C', 0, 0);
        $pdf->MultiCell(20, 6, number_format((float)$studentData[178], 2, '.', ''), 1, 'C', 0, 0);     
        $pdf->MultiCell(20, 6, number_format((float)$cgpa, 2, '.', ''), 1, 'C', 0, 0);     
        $pdf->MultiCell(20, 6, 'PASS', 1, 'C', 0, 0);
        //Table 3
        $pdf->SetXY(13, 236);
        $pdf->SetFont($arial, '', 10, '', false); 
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);  
        $pdf->MultiCell(24, 14.4, "Percentage\nof Marks\nObtained", 1, 'C', 0, 0);
        $pdf->setCellPaddings( $left = '', $top = '2', $right = '', $bottom = '');
        $pdf->MultiCell(24, 8, '90.00  100','LTR', 'C', 0, 0);
        $pdf->MultiCell(24, 8, '80.00  89.99', 1, 'C', 0, 0);     
        $pdf->MultiCell(24, 8, '70.00  79.99', 1, 'C', 0, 0);     
        $pdf->MultiCell(24, 8, '60.00  69.99', 1, 'C', 0, 0);
        $pdf->MultiCell(24, 8, '50.00  59.99', 1, 'C', 0, 0);
        $pdf->MultiCell(22, 8, "Less than 50", 1, 'C', 0, 0);
        $pdf->MultiCell(18, 8, 'Absent', 1, 'C', 0, 0);
        $pdf->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
        $pdf->SetXY(13, 244);
        $pdf->SetFont($arial, '', 10, '', false); 
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);  
        $pdf->MultiCell(24, 6.4, "", 0, 'C', 0, 0);
        $pdf->MultiCell(24, 6.4, 'O',1, 'C', 0, 0);
        $pdf->MultiCell(24, 6.4, 'A', 1, 'C', 0, 0);     
        $pdf->MultiCell(24, 6.4, 'B', 1, 'C', 0, 0);     
        $pdf->MultiCell(24, 6.4, 'C', 1, 'C', 0, 0);
        $pdf->MultiCell(24, 6.4, 'D', 1, 'C', 0, 0);
        $pdf->MultiCell(22, 6.4, "F", 1, 'C', 0, 0);
        $pdf->MultiCell(18, 6.4, 'AB', 1, 'C', 0, 0);
        $pdf->SetXY(13, 250.3);
        $pdf->SetFont($arial, '', 10, '', false); 
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);  
        $pdf->MultiCell(24, 6.3, "Grade Point", 'LRB', 'C', 0, 0);
        $pdf->MultiCell(24, 6.3, '10','LRB', 'C', 0, 0);
        $pdf->MultiCell(24, 6.3, '9', 'LRB', 'C', 0, 0);     
        $pdf->MultiCell(24, 6.3, '8', 'LRB', 'C', 0, 0);     
        $pdf->MultiCell(24, 6.3, '7', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(24, 6.3, '6', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(22, 6.3, "0", 'LRB', 'C', 0, 0);
        $pdf->MultiCell(18, 6.3, '0', 'LRB', 'C', 0, 0);
        
        $pdf->SetFont($arialb, '', 10, '', false);
        $pdf->SetXY(13, 257);          
        $pdf->MultiCell(0, 0, 'Date of Issue : <span style="color:blue;">'.$date_of_issue.'</span>', 0, 'L', 0, 0, '', '', true, 0, true);
        
        // $pdf->SetFont($arialb, '', 10, '', false);
        // $pdf->SetXY(13, 257);          
        // $pdf->MultiCell(30, 4, "Issue Date", 0, 'L', 0, 0); 

        
//pdfBig        
        //Table 2
        $pdfBig->SetXY(13, 223);
        $pdfBig->SetFont($arialb, '', 10, '', false); 
        $pdfBig->SetFillColor(255, 255, 255);
        $pdfBig->SetTextColor(0, 0, 0);  
        $pdfBig->MultiCell(40, 6, "Carry Over (if any)", 1, 'C', 0, 0);
        $pdfBig->MultiCell(84, 6, 'CREDITS OBTAINED / TOTAL CREDITS',1, 'C', 0, 0);
        $pdfBig->MultiCell(20, 6, 'SGPA', 1, 'C', 0, 0);     
        $pdfBig->MultiCell(20, 6, 'CGPA', 1, 'C', 0, 0);     
        $pdfBig->MultiCell(20, 6, 'Result', 1, 'C', 0, 0);
        $pdfBig->SetXY(13, 229);
        $pdfBig->SetFont($arial, '', 10, '', false); 
        $pdfBig->SetFillColor(255, 255, 255);
        $pdfBig->SetTextColor(0, 0, 0);  
        $pdfBig->MultiCell(40, 6, "Subject(s) Code", 1, 'C', 0, 0);
        $pdfBig->MultiCell(84, 6, $studentData[180] ."/". $studentData[180], 1, 'C', 0, 0);
        $pdfBig->MultiCell(20, 6, number_format((float)$studentData[178], 2, '.', ''), 1, 'C', 0, 0);     
        $pdfBig->MultiCell(20, 6, '', 1, 'C', 0, 0);     
        $pdfBig->MultiCell(20, 6, 'PASS', 1, 'C', 0, 0);
        //Table 3
        $pdfBig->SetXY(13, 236);
        $pdfBig->SetFont($arial, '', 9.5, '', false); 
        $pdfBig->SetFillColor(255, 255, 255);
        $pdfBig->SetTextColor(0, 0, 0);  
        $pdfBig->MultiCell(24, 14.4, "Percentage\nof Marks\nObtained", 1, 'C', 0, 0);
        $pdfBig->setCellPaddings( $left = '', $top = '2', $right = '', $bottom = '');
        $pdfBig->MultiCell(24, 8, '90.00  100','LTR', 'C', 0, 0);
        $pdfBig->MultiCell(24, 8, '80.00  89.99', 1, 'C', 0, 0);     
        $pdfBig->MultiCell(24, 8, '70.00  79.99', 1, 'C', 0, 0);     
        $pdfBig->MultiCell(24, 8, '60.00  69.99', 1, 'C', 0, 0);
        $pdfBig->MultiCell(24, 8, '50.00  59.99', 1, 'C', 0, 0);
        $pdfBig->MultiCell(22, 8, "Less than 50", 1, 'C', 0, 0);
        $pdfBig->MultiCell(18, 8, 'Absent', 1, 'C', 0, 0);
        $pdfBig->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
        $pdfBig->SetXY(13, 244);
        $pdfBig->SetFont($arial, '', 10, '', false); 
        $pdfBig->SetFillColor(255, 255, 255);
        $pdfBig->SetTextColor(0, 0, 0);  
        $pdfBig->MultiCell(24, 6.4, "", 0, 'C', 0, 0);
        $pdfBig->MultiCell(24, 6.4, 'O',1, 'C', 0, 0);
        $pdfBig->MultiCell(24, 6.4, 'A', 1, 'C', 0, 0);     
        $pdfBig->MultiCell(24, 6.4, 'B', 1, 'C', 0, 0);     
        $pdfBig->MultiCell(24, 6.4, 'C', 1, 'C', 0, 0);
        $pdfBig->MultiCell(24, 6.4, 'D', 1, 'C', 0, 0);
        $pdfBig->MultiCell(22, 6.4, "F", 1, 'C', 0, 0);
        $pdfBig->MultiCell(18, 6.4, 'AB', 1, 'C', 0, 0);
		$pdfBig->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
        $pdfBig->SetXY(13, 250.3);
        $pdfBig->SetFont($arial, '', 10, '', false); 
        $pdfBig->SetFillColor(255, 255, 255);
        $pdfBig->SetTextColor(0, 0, 0);  
        $pdfBig->MultiCell(24, 6.3, "Grade Point", 'LRB', 'C', 0, 0);
        $pdfBig->MultiCell(24, 6.3, '10','LRB', 'C', 0, 0);
        $pdfBig->MultiCell(24, 6.3, '9', 'LRB', 'C', 0, 0);     
        $pdfBig->MultiCell(24, 6.3, '8', 'LRB', 'C', 0, 0);     
        $pdfBig->MultiCell(24, 6.3, '7', 'LRB', 'C', 0, 0);
        $pdfBig->MultiCell(24, 6.3, '6', 'LRB', 'C', 0, 0);
        $pdfBig->MultiCell(22, 6.3, "0", 'LRB', 'C', 0, 0);
        $pdfBig->MultiCell(18, 6.3, '0', 'LRB', 'C', 0, 0);    

        $pdfBig->SetFont($arialb, '', 10, '', false);
        $pdfBig->SetXY(13, 257);          
        $pdfBig->MultiCell(0, 0, 'Date of Issue : <span style="color:blue;">'.$date_of_issue.'</span>', 0, 'L', 0, 0, '', '', true, 0, true);   

        // Ghost image
        $nameOrg=$studentData[3];
        $ghost_font_size = '13';
        $ghostImagex = 116;
        $ghostImagey = 275.5;
        $ghostImageWidth = 55;//68
        $ghostImageHeight = 9.8;
        $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
        $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        $serial_no=$GUID=$studentData[0];
        //qr code    
        $dt = date("_ymdHis");
        $str=$GUID.$dt;
        $codeContents =$encryptedString = strtoupper(md5($str));
        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 175;
        $qrCodey = 12;
        $qrCodeWidth =23;
        $qrCodeHeight = 23;
        \QrCode::backgroundColor(255, 255, 0)            
            ->format('png')        
            ->size(500)    
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);        
        $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);        
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
        
        $barcodex = 9;
        $barcodey = 274;
        $barcodeWidth = 60;
        $barodeHeight = 10;
        $pdf->SetAlpha(1);
        $pdf->write1DBarcode($print_serial_no, 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');
        $pdfBig->SetAlpha(1);
        $pdfBig->write1DBarcode($print_serial_no, 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');
        
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); 
        
        $microlinestr=$str;
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(173, 35.6);        
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');    
            
        $pdfBig->SetFont($arialb, '', 1.2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->StartTransform();
        $pdfBig->SetXY(173, 35.6);        
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

            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'B.Ed. 1st Year',$admin_id,$card_serial_no="");

            //$card_serial_no=$card_serial_no+1;
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
        //$this->updateCardNo('monad',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
       }
       $msg = '';
        
        $file_name =  str_replace("/", "_",'monad'.date("Ymdhms")).'.pdf';
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


       $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        if($previewPdf!=1){
     

            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="monad";
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                // with sandbox
                
                $result = SbExceUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            }else{
                // without sandbox
                $result = ExcelUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            } 
            //file transfer to monad server
            //\Storage::disk('mftp')->put('printable_pdf/'.$file_name, public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            $this->testUpload('printable_pdf/'.$file_name, public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name); 
         if($subdomain[0]=="monad"){
        
        $monad_pdf_url = \Config::get('constant.monad_base_url').'printable_pdf/'.$file_name;    
        
        $msg = "<b>Click <a href='".$monad_pdf_url."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
        }else{
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name;
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
        }    
       /* $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name;
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";*/
        }else{
          

        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$file_name);
        @unlink($filename);
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name;
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name."' class='downloadpdf download' target='_blank'>Here</a> to download file<b>";
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

        //copy($file1, $file2);        
        //$aws_qr = \File::copy($file2,$pdfActualPath);
        
		$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;
		$output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName; 
		CoreHelper::compressPdfFile($source,$output);
        //file transfer to monad server
        //\Storage::disk('mftp')->put('pdf_file/'.$certName, $pdfActualPath);     
        $this->testUpload('pdf_file/'.$certName, $pdfActualPath); 
        
        //@unlink($file2);
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
