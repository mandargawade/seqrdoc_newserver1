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
class PdfGenerateAnuJob
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
        $subjectsMark=$pdf_data['subjectsMark'];
        $template_id=$pdf_data['template_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $points=$pdf_data['points'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];
        $photo_col=23;

        $first_sheet=$pdf_data['studentDataOrg']; // get first worksheet rows
        //print_r($second_sheet); exit;
		       
        
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
        $times = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.ttf', 'TrueTypeUnicode', '', 96);
        $timesb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.ttf', 'TrueTypeUnicode', '', 96);
        $trebuc = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\trebuc.ttf', 'TrueTypeUnicode', '', 96);
        $trebucb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\TREBUCBD.ttf', 'TrueTypeUnicode', '', 96);
        $trebuci = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Trebuchet-MS-Italic.ttf', 'TrueTypeUnicode', '', 96);
        $graduateR = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\graduate-regular.ttf', 'TrueTypeUnicode', '', 96);
        $poppinsR = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Poppins Regular 400.ttf', 'TrueTypeUnicode', '', 96);
        $poppinsM = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Poppins Medium 500.ttf', 'TrueTypeUnicode', '', 96);

        $preview_serial_no=1;
		//$card_serial_no="";
        $log_serial_no = 1;
        $cardDetails=$this->getNextCardNo('anuGC');
        $card_serial_no=$cardDetails->next_serial_no;
        $generated_documents=0;  //for custom loader
        foreach ($studentDataOrg as $studentData) {
         
			if($card_serial_no>999999&&$previewPdf!=1){
				echo "<h5>Your card series ended...!</h5>";
				exit;
			}
			//For Custom Loader
            $startTimeLoader =  date('Y-m-d H:i:s');    
			$high_res_bg="anu_gradecard_front.jpg"; // anu_gradecard_front
			$low_res_bg="anu_gradecard_front.jpg";
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
			$pdf->SetTitle('Grade Card');
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
			//$pdf->setPageMark();
			$pdf->setPageMark();
			//$pdfBig->setPageMark();
            //if($previewPdf!=1){            
                $x= 173;
                $y = 39.1;
                $font_size=12;
                if($previewPdf!=1){
					$str = str_pad($card_serial_no, 7, '0', STR_PAD_LEFT);
				}else{
					$str = str_pad($preview_serial_no, 7, '0', STR_PAD_LEFT);	
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
                    $pdf->SetFont($arialNarrow,0, $font_size, '', false);
                    $pdf->SetXY($x, $y+$z);

                    $pdfBig->SetFont($arialNarrow,0, $font_size, '', false);
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
                   //$pdf->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');
                   //$pdfBig->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');
                    $i++;
                    $x=$x+2.2+$j; 
                    if($i>2){
                     $font_size=$font_size+1.7;   
                    }
                }         
            //}
			
			//$pdf->SetFont($arialNarrowB, '', 9, '', false);
			//$pdfBig->SetFont($arialNarrowB, '', 9, '', false);        
			//images
			if($studentData[1]!=''){
				//path of photos
				$profile_path_org = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[1].'.jpg'; 
				//set profile image   
				$profilex = 170;
				$profiley = 46;
				$profileWidth = 27.18;
				$profileHeight = 35;
				// $profilex = 175.5;
				// $profiley = 62;
				// $profileWidth = 16;
				// $profileHeight = 22;
				$pdfBig->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);  
				$pdfBig->setPageMark();
				$pdf->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
				$pdf->setPageMark();
			}
           
            /*start pdfBig*/      
            //1st 6 columns            
            $pdfBig->SetXY(12, 83);   
            $pdfBig->MultiCell(22, 133, '','LRTB', 'L', 0, 0);
            $pdfBig->MultiCell(97.6, 133, '', 'LRTB', 'C', 0, 0);
            $pdfBig->MultiCell(16, 133, '', 'LRTB', 'C', 0, 0);
            $pdfBig->MultiCell(16, 133, '', 'LRTB', 'C', 0, 0);
            $pdfBig->MultiCell(16, 133, '', 'LRTB', 'C', 0, 0);
            $pdfBig->MultiCell(17.5, 133, '', 'LRTB', 'C', 0, 0);   

			$pdfBig->SetXY(12, 94);     
			$pdfBig->MultiCell(185, '', '','T', 'L', 0, 0);	

            $pdfBig->SetFont($trebucb, '', 10, '', false);            
			$pdfBig->SetTextColor(0, 0, 0);   
			$pdfBig->SetXY(12, 84);
            $pdfBig->MultiCell(22, 0, 'Course<br>Code', 0, 'C', 0, 0, '', '', true, 0, true);    
			$pdfBig->SetXY(34, 86);
			$pdfBig->MultiCell(97.6, 0, 'Course', 0, 'C', 0, 0, '', '', true, 0, true);
            $pdfBig->SetXY(131, 84);
			$pdfBig->MultiCell(18, 0, 'Credits<br>Enrolled', 0, 'C', 0, 0, '', '', true, 0, true);
            $pdfBig->SetXY(147.5, 84);
			$pdfBig->MultiCell(18, 0, 'Credits<br>Earned', 0, 'C', 0, 0, '', '', true, 0, true);  
            $pdfBig->SetXY(164, 86);
			$pdfBig->MultiCell(16, 0, 'Grade', 0, 'C', 0, 0, '', '', true, 0, true);    
            $pdfBig->SetXY(178, 84);
			$pdfBig->MultiCell(22, 0, 'Grade<br>Points', 0, 'C', 0, 0, '', '', true, 0, true);    
            /*end pdfBig*/
            /*start pdf*/      
            //1st 6 columns            
            $pdf->SetXY(12, 83);   
            $pdf->MultiCell(22, 133, '','LRTB', 'L', 0, 0);
            $pdf->MultiCell(97.6, 133, '', 'LRTB', 'C', 0, 0);
            $pdf->MultiCell(16, 133, '', 'LRTB', 'C', 0, 0);
            $pdf->MultiCell(16, 133, '', 'LRTB', 'C', 0, 0);
            $pdf->MultiCell(16, 133, '', 'LRTB', 'C', 0, 0);
            $pdf->MultiCell(17.5, 133, '', 'LRTB', 'C', 0, 0);   

			$pdf->SetXY(12, 94);     
			$pdf->MultiCell(185, '', '','T', 'L', 0, 0);	

            $pdf->SetFont($trebucb, '', 10, '', false);            
			$pdf->SetTextColor(0, 0, 0);   
			$pdf->SetXY(12, 84);
            $pdf->MultiCell(22, 0, 'Course<br>Code', 0, 'C', 0, 0, '', '', true, 0, true);    
			$pdf->SetXY(34, 86);
			$pdf->MultiCell(97.6, 0, 'Course', 0, 'C', 0, 0, '', '', true, 0, true);
            $pdf->SetXY(131, 84);
			$pdf->MultiCell(18, 0, 'Credits<br>Enrolled', 0, 'C', 0, 0, '', '', true, 0, true);
            $pdf->SetXY(147.5, 84);
			$pdf->MultiCell(18, 0, 'Credits<br>Earned', 0, 'C', 0, 0, '', '', true, 0, true);  
            $pdf->SetXY(164, 86);
			$pdf->MultiCell(16, 0, 'Grade', 0, 'C', 0, 0, '', '', true, 0, true);    
            $pdf->SetXY(178, 84);
			$pdf->MultiCell(22, 0, 'Grade<br>Points', 0, 'C', 0, 0, '', '', true, 0, true);    
            /*end pdf*/			
			
            $title1_x = 15.5; 
            $title1_colonx = 47.7;        
            $left_title1_y = 51.5;        
            $left_title2_y = 58.7;
            $left_title3_y = 65.8;
            $left_title4_y = 72.7;
            $left_title5_y = 79.7;
            $left_title6_y = 86.7;
            $left_title7_y = 93.7;
            $left_str_x = 29;
            $title2_x = 106;
            $title2_colonx = 142.3;
            $right_str_x = 145;
            $title_font_size = '10';        
            $str_font_size = '10';       
			
			$unique_id = trim($studentData[0]);
			$student_id = trim($studentData[1]);
			$first_name = trim($studentData[2]);
			$last_name = trim($studentData[3]);
			$Section = trim($studentData[4]);        
			$DOB = trim($studentData[5]);
			$Email = trim($studentData[6]);
			$candidate_name = trim($studentData[7]);
			$Batch = trim($studentData[8]);
			$Semester = trim($studentData[9]);
			$Programme = trim($studentData[10]);
			$Major = trim($studentData[11]);
			$RANK = trim($studentData[12]);
			$PRE_SEM_TotCoreCrEarn = trim($studentData[13]);
			$PRE_SEM_TotEleCrEarn = trim($studentData[14]);
			$TotCoreCrEarn = trim($studentData[15]);
			$TotEleCrEarn = trim($studentData[16]);
			$CoreCrEarn = trim($studentData[17]);
			$EleCrEarn = trim($studentData[18]);
			$PRE_SEM_Total_EarnCr = trim($studentData[19]);
			$PRE_SEM_Total_EarnCrPt = trim($studentData[20]);
			$TotEarnCr = trim($studentData[21]);
			$TotEarnCrPt = trim($studentData[22]);
			$SGPA = $studentData[23];
			$SGPA_Desc = $studentData[24];
			$CGPA = $studentData[25];
			$CGPA_Desc = $studentData[26];
			$EarnCrPt = $studentData[27];
			$EnrolCr = $studentData[28];
			$EarnCr = $studentData[29];
			$Session = $studentData[126];
			$QR_Output = $studentData[128];
			$issue_date = $studentData[130];
			$QR_Code_No = $studentData[131];
			$photo = $studentData[132];
			$name1 = $studentData[133];
			$designation1 = $studentData[134];
			$name2 = $studentData[135];
			$designation2 = $studentData[136];
			
			$pdfBig->startTransaction(); // store current object
			$pdfBig->SetFont($trebuc, '', $title_font_size, '', false); 
			$programme_lines = $pdfBig->MultiCell(55, 0, $Programme, 0, 'L', 0, 0, '', '', true, 0, false,true, 0); // get the number of lines
			$pdfBig=$pdfBig->rollbackTransaction();		// restore previous object	

			$pdf->startTransaction(); // store current object
			$pdf->SetFont($trebuc, '', $title_font_size, '', false); 
			$programme_lines_pdf = $pdf->MultiCell(55, 0, $Programme, 0, 'L', 0, 0, '', '', true, 0, false,true, 0); // get the number of lines
			$pdf=$pdf->rollbackTransaction();		// restore previous object	
                        
            /*start pdfBig*/
            
			$pdfBig->SetFont($trebuc, '', 20, '', false);
            $pdfBig->SetTextColor(66, 65, 65);
            $pdfBig->SetXY(12, 33);
			$pdfBig->MultiCell(185, 0, "Semester Academic Report", 0, "C", 0, 0, '', '', true, 0, true);
			$pdfBig->SetFont($trebucb, '', 15, '', false);
			$pdfBig->SetXY(12, 43);
			$pdfBig->MultiCell(185, 0, $Session, 0, "C", 0, 0, '', '', true, 0, true);
			
			$pdfBig->SetFont($trebucb, '', $title_font_size, '', false);
            $pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(12, 53);
			$pdfBig->MultiCell(32.5, 0, "Student Name:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(125.5, 0, '<span style="font-family:'.$trebuc.';">'.$candidate_name.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
            $pdfBig->SetXY(12, 58.5);
			$pdfBig->MultiCell(32.5, 0, "Student ID No:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(43, 0, '<span style="font-family:'.$trebuc.';">'.$student_id.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(27, 0, "Date of Birth :", 0, "R", 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(58, 0, '<span style="font-family:'.$trebuc.';">'.$DOB.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(12, 64.5);
			$pdfBig->MultiCell(32.5, 0, "Batch:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(43, 0, '<span style="font-family:'.$trebuc.';">'.$Batch.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(27, 0, "Programme:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(55, 0, '<span style="font-family:'.$trebuc.';">'.$Programme.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(12, 70.5);
            $pdfBig->MultiCell(32.5, 0, "Semester:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(43, 0, '<span style="font-family:'.$trebuc.';">'.$Semester.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			if($programme_lines > 1){
				$pdfBig->SetXY(87.5, 76.5);
				$pdfBig->MultiCell(27, 0, "Major:", 0, "R", 0, 0, '', '', true, 0, true);
				$pdfBig->MultiCell(58, 0, '<span style="font-family:'.$trebuc.';">'.$Major.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			}else{
				$pdfBig->MultiCell(27, 0, "Major:", 0, "R", 0, 0, '', '', true, 0, true);
				$pdfBig->MultiCell(58, 0, '<span style="font-family:'.$trebuc.';">'.$Major.'</span>', 0, "L", 0, 0, '', '', true, 0, true);	
			}
			
			$pdfBig->SetXY(12, 76.5);
			$pdfBig->MultiCell(32.5, 0, "Report Issue Date:", 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(125.5, 0, '<span style="font-family:'.$trebuc.';">'.$issue_date.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
            
            /*end pdfBig*/
            /*start pdf*/            
			$pdf->SetFont($trebuc, '', 20, '', false);
            $pdf->SetTextColor(66, 65, 65);
            $pdf->SetXY(12, 33);
			$pdf->MultiCell(185, 0, "Semester Academic Report", 0, "C", 0, 0, '', '', true, 0, true);
			$pdf->SetFont($trebucb, '', 15, '', false);
			$pdf->SetXY(12, 43);
			$pdf->MultiCell(185, 0, $Session, 0, "C", 0, 0, '', '', true, 0, true);
			
			$pdf->SetFont($trebucb, '', $title_font_size, '', false);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(12, 53);
			$pdf->MultiCell(32.5, 0, "Student Name:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(125.5, 0, '<span style="font-family:'.$trebuc.';">'.$candidate_name.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
            $pdf->SetXY(12, 58.5);
			$pdf->MultiCell(32.5, 0, "Student ID No:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(43, 0, '<span style="font-family:'.$trebuc.';">'.$student_id.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(27, 0, "Date of Birth :", 0, "R", 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(58, 0, '<span style="font-family:'.$trebuc.';">'.$DOB.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->SetXY(12, 64.5);
			$pdf->MultiCell(32.5, 0, "Batch:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(43, 0, '<span style="font-family:'.$trebuc.';">'.$Batch.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(27, 0, "Programme:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(55, 0, '<span style="font-family:'.$trebuc.';">'.$Programme.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->SetXY(12, 70.5);
            $pdf->MultiCell(32.5, 0, "Semester:", 0, "R", 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(43, 0, '<span style="font-family:'.$trebuc.';">'.$Semester.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			if($programme_lines_pdf > 1){
				$pdf->SetXY(87.5, 76.5);
				$pdf->MultiCell(27, 0, "Major:", 0, "R", 0, 0, '', '', true, 0, true);
				$pdf->MultiCell(58, 0, '<span style="font-family:'.$trebuc.';">'.$Major.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			}else{
				$pdf->MultiCell(27, 0, "Major:", 0, "R", 0, 0, '', '', true, 0, true);
				$pdf->MultiCell(58, 0, '<span style="font-family:'.$trebuc.';">'.$Major.'</span>', 0, "L", 0, 0, '', '', true, 0, true);	
			}
			$pdf->SetXY(12, 76.5);
			$pdf->MultiCell(32.5, 0, "Report Issue Date:", 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(125.5, 0, '<span style="font-family:'.$trebuc.';">'.$issue_date.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
            
            /*end pdf*/            
			
			/* get courses */
			$subjectData = array_slice($studentData, 30, 96); 
			$subjectsArr=array_chunk($subjectData, 6);
			$count_upto=count($subjectsArr);        
			//Count total subjects entered
			$actual_subj_count=0;
			for ($z=0; $z < $count_upto ; $z++) {
				if(!empty($subjectsArr[$z][0])){
					$actual_subj_count=$actual_subj_count+1;
				}
			}
			
            $pdf->SetFont($trebuc, '', 9, '', false); 
            $pdfBig->SetFont($trebuc, '', 9, '', false); 
            $subj_y=95;  
			for ($s=0; $s < $count_upto; $s++) {
				$Subject_Code=trim($subjectsArr[$s][0]);
				$Subject_Title=trim($subjectsArr[$s][1]);
				$Credits_Enrolled=trim($subjectsArr[$s][2]);
				$Credits_Earned=trim($subjectsArr[$s][3]);
				$Grade=trim($subjectsArr[$s][4]);
				$Grade_Points=trim($subjectsArr[$s][5]);				
				
                $pdfBig->SetXY(12.5, $subj_y);
                $pdfBig->MultiCell(21.5, 11, $Subject_Code, 0, 'R', 0, 0);
                $pdfBig->SetXY(35, $subj_y);
                $pdfBig->MultiCell(97, 11, $Subject_Title, 0, 'L', 0, 0);     
                $pdfBig->MultiCell(16, 11, $Credits_Enrolled, 0, 'C', 0, 0);     
                $pdfBig->MultiCell(16, 11, $Credits_Earned, 0, 'C', 0, 0);   
                $pdfBig->MultiCell(16, 11, $Grade, 0, 'C', 0, 0);   
                $pdfBig->MultiCell(16.5, 11, $Grade_Points, 0, 'C', 0, 0);

				$pdf->SetXY(12.5, $subj_y);
                $pdf->MultiCell(21.5, 11, $Subject_Code, 0, 'R', 0, 0);
                $pdf->SetXY(35, $subj_y);
                $pdf->MultiCell(97, 11, $Subject_Title, 0, 'L', 0, 0);     
                $pdf->MultiCell(16, 11, $Credits_Enrolled, 0, 'C', 0, 0);     
                $pdf->MultiCell(16, 11, $Credits_Earned, 0, 'C', 0, 0);   
                $pdf->MultiCell(16, 11, $Grade, 0, 'C', 0, 0);   
                $pdf->MultiCell(16.5, 11, $Grade_Points, 0, 'C', 0, 0);
				
                $subj_y=$subj_y+7.5;
			}
			/*start pdfBig*/
			$pdfBig->SetFont($trebuc, '', 10, '', false);            
			$pdfBig->SetTextColor(0, 0, 0);   
			$pdfBig->SetXY(12, 218);
			$pdfBig->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
            $pdfBig->MultiCell(92.5, 7, 'Semester Grade Point Average (SGPA)', "LTRB", 'C', 0, 0, '', '', true, 0, true);             
            $pdfBig->MultiCell(92.5, 7, 'Cumulative Grade Point Average (CGPA)', "TBR", 'C', 0, 0, '', '', true, 0, true); 
			$pdfBig->SetXY(12, 225);
			$pdfBig->setCellPaddings( $left = '', $top = '2.5', $right = '', $bottom = '');
			$pdfBig->SetFont($trebuc, '', 9.5, '', false);
			$pdfBig->MultiCell(26.5, 10, 'Earned Credits', "LTRB", 'C', 0, 0, '', '', true, 0, true); 
			$pdfBig->setCellPaddings( $left = '', $top = '1.5', $right = '', $bottom = '');
			$pdfBig->MultiCell(35.5, 10, 'Earned Credit Points<br><span style="font-size:7;font-family:'.$trebuc.';">Σ(Credit X Grade Points)</span>', "RB", 'C', 0, 0, '', '', true, 0, true);	
			$pdfBig->setCellPaddings( $left = '', $top = '2.5', $right = '', $bottom = '');
			$pdfBig->SetFont($trebucb, '', 9.5, '', false);
			$pdfBig->MultiCell(30.5, 10, 'SGPA', "RB", 'C', 0, 0, '', '', true, 0, true);	
			$pdfBig->setCellPaddings( $left = '', $top = '1.3', $right = '', $bottom = '');
			$pdfBig->SetFont($trebuc, '', 9.5, '', false);
			$pdfBig->MultiCell(31, 10, 'Total<br>Earned Credits', "LTRB", 'C', 0, 0, '', '', true, 0, true); 
			$pdfBig->MultiCell(30.5, 10, 'Total Earned<br>Credit Points', "RB", 'C', 0, 0, '', '', true, 0, true);
			$pdfBig->setCellPaddings( $left = '', $top = '2.5', $right = '', $bottom = '');	
			$pdfBig->SetFont($trebucb, '', 10, '', false);
			$pdfBig->MultiCell(31, 10, 'CGPA', "RB", 'C', 0, 0, '', '', true, 0, true);	
			$pdfBig->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
			
			$pdfBig->SetFont($trebuc, '', 9.5, '', false);            
			$pdfBig->SetTextColor(0, 0, 0);   
			$pdfBig->SetXY(12, 235);
			$pdfBig->setCellPaddings( $left = '', $top = '3', $right = '', $bottom = '');
            $pdfBig->MultiCell(26.5, 12, $EarnCr, "LTRB", 'C', 0, 0, '', '', true, 0, true);             
            $pdfBig->MultiCell(35.5, 12, $EarnCrPt, "TBR", 'C', 0, 0, '', '', true, 0, true); 
			if($SGPA_Desc != ''){
				$pdfBig->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
				$pdfBig->SetFont($trebucb, '', 9.5, '', false); 
				$pdfBig->MultiCell(30.5, 12, $SGPA."<br>".$SGPA_Desc, "RB", 'C', 0, 0, '', '', true, 0, true);
			}else{
				$pdfBig->SetFont($trebucb, '', 9.5, '', false); 
				$pdfBig->MultiCell(30.5, 12, $SGPA, "RB", 'C', 0, 0, '', '', true, 0, true);
			}			
			$pdfBig->setCellPaddings( $left = '', $top = '3', $right = '', $bottom = '');
			$pdfBig->SetFont($trebuc, '', 9.5, '', false); 
			$pdfBig->MultiCell(31, 12, $TotEarnCr, "LTRB", 'C', 0, 0, '', '', true, 0, true); 
			$pdfBig->MultiCell(30.5, 12, $TotEarnCrPt, "RB", 'C', 0, 0, '', '', true, 0, true);
			if($CGPA_Desc != ''){
				$pdfBig->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
				$pdfBig->SetFont($trebucb, '', 9.5, '', false); 
				$pdfBig->MultiCell(31, 12, $CGPA."<br>".$CGPA_Desc, "RB", 'C', 0, 0, '', '', true, 0, true);
			}else{
				$pdfBig->SetFont($trebucb, '', 9.5, '', false); 
				$pdfBig->MultiCell(31, 12, $CGPA, "RB", 'C', 0, 0, '', '', true, 0, true);
			}
			$pdfBig->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
			
			$pdfBig->SetFont($trebuc, '', 8, '', false);            
			$pdfBig->SetTextColor(0, 0, 0);   
			//left side
			$pdfBig->SetXY(44, 272);
			$pdfBig->Cell(0, 0, $name1, 0, false, 'L');
			$pdfBig->SetXY(44, 275.5);
			$pdfBig->Cell(0, 0, $designation1, 0, false, 'L');
			
			//right side
			$pdfBig->SetXY(132.5, 272);
			$pdfBig->Cell(0, 0, $name2, 0, false, 'L');
			$pdfBig->SetXY(132.5, 275.5);
			$pdfBig->Cell(0, 0, $designation2, 0, false, 'L');
			/*end pdfBig*/
			
			/*start pdf*/
			$pdf->SetFont($trebuc, '', 10, '', false);            
			$pdf->SetTextColor(0, 0, 0);   
			$pdf->SetXY(12, 218);
			$pdf->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
            $pdf->MultiCell(92.5, 7, 'Semester Grade Point Average (SGPA)', "LTRB", 'C', 0, 0, '', '', true, 0, true);             
            $pdf->MultiCell(92.5, 7, 'Cumulative Grade Point Average (CGPA)', "TBR", 'C', 0, 0, '', '', true, 0, true); 
			$pdf->SetXY(12, 225);
			$pdf->setCellPaddings( $left = '', $top = '2.5', $right = '', $bottom = '');
			$pdf->SetFont($trebuc, '', 9.5, '', false);
			$pdf->MultiCell(26.5, 10, 'Earned Credits', "LTRB", 'C', 0, 0, '', '', true, 0, true); 
			$pdf->setCellPaddings( $left = '', $top = '1.5', $right = '', $bottom = '');
			$pdf->MultiCell(35.5, 10, 'Earned Credit Points<br><span style="font-size:7;font-family:'.$trebuc.';">Σ(Credit X Grade Points)</span>', "RB", 'C', 0, 0, '', '', true, 0, true);	
			$pdf->setCellPaddings( $left = '', $top = '2.5', $right = '', $bottom = '');
			$pdf->SetFont($trebucb, '', 9.5, '', false);
			$pdf->MultiCell(30.5, 10, 'SGPA', "RB", 'C', 0, 0, '', '', true, 0, true);	
			$pdf->setCellPaddings( $left = '', $top = '1.3', $right = '', $bottom = '');
			$pdf->SetFont($trebuc, '', 9.5, '', false);
			$pdf->MultiCell(31, 10, 'Total<br>Earned Credits', "LTRB", 'C', 0, 0, '', '', true, 0, true); 
			$pdf->MultiCell(30.5, 10, 'Total Earned<br>Credit Points', "RB", 'C', 0, 0, '', '', true, 0, true);
			$pdf->setCellPaddings( $left = '', $top = '2.5', $right = '', $bottom = '');	
			$pdf->SetFont($trebucb, '', 10, '', false);
			$pdf->MultiCell(31, 10, 'CGPA', "RB", 'C', 0, 0, '', '', true, 0, true);	
			$pdf->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
			
			$pdf->SetFont($trebuc, '', 9.5, '', false);            
			$pdf->SetTextColor(0, 0, 0);   
			$pdf->SetXY(12, 235);
			$pdf->setCellPaddings( $left = '', $top = '3', $right = '', $bottom = '');
            $pdf->MultiCell(26.5, 12, $EarnCr, "LTRB", 'C', 0, 0, '', '', true, 0, true);             
            $pdf->MultiCell(35.5, 12, $EarnCrPt, "TBR", 'C', 0, 0, '', '', true, 0, true); 
			if($SGPA_Desc != ''){
				$pdf->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
				$pdf->SetFont($trebucb, '', 9.5, '', false); 
				$pdf->MultiCell(30.5, 12, $SGPA."<br>".$SGPA_Desc, "RB", 'C', 0, 0, '', '', true, 0, true);
			}else{
				$pdf->SetFont($trebucb, '', 9.5, '', false);
				$pdf->MultiCell(30.5, 12, $SGPA, "RB", 'C', 0, 0, '', '', true, 0, true);
			}			
			$pdf->setCellPaddings( $left = '', $top = '3', $right = '', $bottom = '');
			$pdf->SetFont($trebuc, '', 9.5, '', false); 
			$pdf->MultiCell(31, 12, $TotEarnCr, "LTRB", 'C', 0, 0, '', '', true, 0, true); 
			$pdf->MultiCell(30.5, 12, $TotEarnCrPt, "RB", 'C', 0, 0, '', '', true, 0, true);
			if($CGPA_Desc != ''){
				$pdf->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
				$pdf->SetFont($trebucb, '', 9.5, '', false); 
				$pdf->MultiCell(31, 12, $CGPA."<br>".$CGPA_Desc, "RB", 'C', 0, 0, '', '', true, 0, true);	
			}else{
				$pdf->SetFont($trebucb, '', 9.5, '', false);
				$pdf->MultiCell(31, 12, $CGPA, "RB", 'C', 0, 0, '', '', true, 0, true);
			}
			$pdf->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
			
			$pdf->SetFont($trebuc, '', 8, '', false);            
			$pdf->SetTextColor(0, 0, 0);   
			//left side
			$pdf->SetXY(44, 272);
			//$pdf->Cell(0, 0, $name1, 0, false, 'L');
			$pdf->SetXY(44, 275.5);
			$pdf->Cell(0, 0, $designation1, 0, false, 'L');
			
			//right side
			$pdf->SetXY(132.5, 272);
			//$pdf->Cell(0, 0, $name2, 0, false, 'L');
			$pdf->SetXY(132.5, 275.5);
			$pdf->Cell(0, 0, $designation2, 0, false, 'L');			
			/*end pdf*/			
			
			// Ghost image
			$nameOrg=$candidate_name;
			/*$ghost_font_size = '13';
			$ghostImagex = 70;
			$ghostImagey = 269.5;
			$ghostImageWidth = 55;//68
			$ghostImageHeight = 9.8;
			$name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
			$tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
			$pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);			
			$pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);*/
			/*$ghost_font_size = '12';
            $ghostImagex = 70;
            $ghostImagey = 269.5;
            $ghostImageWidth = 39.405983333;
            $ghostImageHeight = 8;
            $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
            $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
			$pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
            $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
			$pdf->setPageMark();
			$pdfBig->setPageMark();*/
			$serial_no=$GUID=$studentData[0];
			//qr code    
			$dt = date("_ymdHis");
			$str=$GUID.$dt;
			$codeContents = $QR_Output."\n\n".strtoupper(md5($str));
			$encryptedString = strtoupper(md5($str));
			$qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
			$qrCodex = 12; 
			$qrCodey = 255;
			$qrCodeWidth =21;
			$qrCodeHeight = 21;
			$ecc = 'L';
			$pixel_Size = 1;
			$frame_Size = 1;  
			\PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
			$pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);   
			$pdf->setPageMark(); 
			$pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
			$pdfBig->setPageMark(); 			
			
            //left side
			$sign1 = public_path().'\\'.$subdomain[0].'\backend\canvas\images\JasmineGohil.png';
            $sign1_x = 45;
            $sign1_y = 251;
            $sign1_Width = 20;
            $sign1_Height = 21.5;
            $pdfBig->image($sign1,$sign1_x,$sign1_y,$sign1_Width,$sign1_Height,"",'','L',true,3600);
            $pdfBig->setPageMark();
			//right side
			$sign2 = public_path().'\\'.$subdomain[0].'\backend\canvas\images\Dr_Sridhar_Reddy_Signature.png';
            $sign2_x = 131;
            $sign2_y = 260;
            $sign2_Width = 31.75;
            $sign2_Height = 9.79;
            $pdfBig->image($sign2,$sign2_x,$sign2_y,$sign2_Width,$sign2_Height,"",'','L',true,3600);
            $pdfBig->setPageMark();
			
			//1D Barcode
			/*$style1Da = array(
				'position' => '',
				'align' => 'C',
				'stretch' => true,
				'fitwidth' => true,
				'cellfitalign' => '',
				'border' => false,
				'hpadding' => 'auto',
				'vpadding' => 'auto',
				'fgcolor' => array(0,0,0),
				'bgcolor' => false, //array(255,255,255),
				'text' => true,
				'font' => 'helvetica',
				'fontsize' => 9,
				'stretchtext' => 7
			); 
			
			$barcodex = 142;
			$barcodey = 269;
			$barcodeWidth = 54;
			$barodeHeight = 13;
			$pdf->SetAlpha(1);
			$pdf->write1DBarcode(trim($print_serial_no), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1Da, 'N');
			$pdfBig->SetAlpha(1);
			$pdfBig->write1DBarcode(trim($print_serial_no), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1Da, 'N');
			//$pdfBig->SetFont($arial, '', 9, '', false);
			//$pdfBig->SetXY(142, 275);
            //$pdfBig->MultiCell(0, 0, trim($print_serial_no), 0, 'L', 0, 0);
			*/
			$str = $nameOrg;
			$str = strtoupper(preg_replace('/\s+/', '', $str)); 
						
			$pdfBig->SetFont($graduateR, '', 8, '', false);
			$pdfBig->SetTextColor(0, 0, 0);
			$pdfBig->SetXY(12, 275.5);        
			$pdfBig->Cell(21, 0, $QR_Code_No, 0, false, 'C'); 
			
			$pdf->SetFont($graduateR, '', 8, '', false);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY(12, 275.5);        
			$pdf->Cell(21, 0, $QR_Code_No, 0, false, 'C');
			
			/*Point Page Start*/
			$pdfBig->AddPage();
			$back_img = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\anu_gradecard_back.jpg';   

			if($previewPdf==1){
				if($previewWithoutBg!=1){
					$pdfBig->Image($back_img, 0, 0, '210', '297', "JPG", '', 'R', true);
				}
			}
			$pdfBig->setPageMark();			
			if($points=="4"){
				$grade_title='Grade Points: <span style="font-size:10;font-family:'.$trebuc.';">4 point grading system with corresponding numeric grade & letter grade as given below</span>';
				$grades_count = 13;
				$grades = array (
					array("96-100","A+","4.00","Distinguished"),
					array("91-95","A","3.80","Excellent"),
					array("86-90","A-","3.60","Very Good"),
					array("81-85","B+","3.40","Good"),
					array("76-80","B","3.20","High satisfactory"),
					array("71-75","B-","3.00","Above satisfactory"),
					array("66-70","C+","2.80","Satisfactory"),
					array("61-65","C","2.60","Less than satisfactory"),
					array("56-60","C-","2.40","Low satisfactory"),
					array("50-55","D","2.00","Poor"),
					array("Below 50","F","0.00","Fail"),
					array("Non-Credit","NC","--",""),
					array("Pass","P","--","")
				);	
				$cgpa_count = 5;
				$cgpa_class = array (
					array("GPA ≥ 3.50","Excellent"),
					array("3.50 > GPA ≥ 3.00","Very Good"),
					array("3.00 > GPA ≥ 2.50","Good"),
					array("2.50 > GPA ≥ 2.00","Above Average"),
					array("2.00 > GPA","Unsatisfactory")
				);	
				$list_count = 3;
				$list = array (
					array("●&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Percentage of marks scored = (GPA/4) * 100"),
					array("●&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;50% is passing criteria in each course."),
					array("●&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;MEDIUM OF INSTRUCTIONS: ENGLISH")
				);		
			}
			elseif($points=="10"){
				$grade_title='Grade Points: <span style="font-size:10;font-family:'.$trebuc.';">10 point grading system with corresponding numeric grade & letter grade as given below</span>';
				$grades_count = 9;
				$grades = array (
					array("91-100","A+","10.00"),
					array("81-90","A","9.00"),					
					array("71-80","B+","8.00"),
					array("61-70","B","7.00"),
					array("56-60","C+","6.00"),
					array("50-55","C","5.00"),
					array("G(Grace)","As per University norms","5.00"),
					array("Below 50","F","0.00"),
					array("Absent (AB)","AB","0.00")
				);	
				$cgpa_count = 4;
				$cgpa_class = array (
					array("7.5 and above","First class with honor"),
					array("6.5 to 7.49","First class"),
					array("5.0 to 6.49","Second class"),
					array("Below 5.0","Fail")
				);
				$list_count = 3;
				$list = array (
					array("●&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Percentage of marks scored = (CGPA Earned x 10)"),
					array("●&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;50% is passing criteria in each course."),
					array("●&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;MEDIUM OF INSTRUCTIONS: ENGLISH")
				);	
			}			
			$denote_count=6;
			$denote = array (
				array("(*)", "= Repeat Course"),
				array("#", "= Audit Course"),
				array("(E)", "= Elective Course"),
				array("(EM)", "= Elective Under Minor Course"),
				array("P", "= Pass"),
				array("NC", "= Non-Credit Course")
			);
			// $note="Note - The grades obtained in an audit course are not considered in the calculation of SGPA or CGPA however they are counted towards the total number of credits earned.";
			$note="Note: The grades obtained in an audit course are not considered in the calculation of SGPA or CGPA; however, they are counted towards the total number of credits earned.";
			
			$university_name='A N A N T&nbsp;&nbsp;&nbsp;N A T I O N A L&nbsp;&nbsp;&nbsp;U N I V E R S I T Y';
			$line2='Sanskardham Campus, Bopal - Ghuma - Sanand Road, Ahmedabad - 382115, Gujarat';
			$line3='Email: registrar@anu.edu.in | Website: www.anu.edu.in';
			
			$pdfBig->SetFont($trebucb, '', 10, '', false);
			$pdfBig->SetTextColor(0, 0, 0);
			$pdfBig->SetXY(12, 13);
            $pdfBig->MultiCell(0, 0, $grade_title, 0, 'L', 0, 0, '', '', true, 0, true);
			if($points=="4"){				
				$pdfBig->SetFont($trebucb, '', 10, '', false);
				$pdfBig->SetXY(55, 21);
				$pdfBig->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
				$pdfBig->MultiCell(22, 11, 'Numeric<br />Grade','LRTB', 'C', 0, 0, '', '', true, 0, true);
				$pdfBig->MultiCell(17, 11, 'Letter<br />Grade','LRTB',  'C', 0, 0, '', '', true, 0, true);
				$pdfBig->setCellPaddings( $left = '', $top = '1.5', $right = '', $bottom = '');
				$pdfBig->MultiCell(25, 11, 'Grade Point<br><span style="font-size:9;font-family:'.$trebucb.';">(4.0 pt scale)</span>','LRTB',  'C', 0, 0, '', '', true, 0, true);
				$pdfBig->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
				$pdfBig->MultiCell(40, 11, 'Descriptive<br />Performance','LRTB',  'L', 0, 0, '', '', true, 0, true);
				$pdfBig->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
				$pdfBig->SetFont($trebuc, '', 10, '', false);
				$y_start=32;	
				for ($grow = 0; $grow < $grades_count; $grow++) {					
					$pdfBig->SetXY(55, $y_start);
					$pdfBig->MultiCell(22, 5, $grades[$grow][0], 'LRTB', 'C', 0, 0);
					$pdfBig->MultiCell(17, 5, $grades[$grow][1], 'LRTB', 'C', 0, 0);
					$pdfBig->MultiCell(25, 5, $grades[$grow][2], 'LRTB', 'C', 0, 0);
					$pdfBig->MultiCell(40, 5, $grades[$grow][3], 'LRTB', 'L', 0, 0);
					$y_start=$y_start+5;
				}	
				
				$pdfBig->SetFont($trebucb, '', 10, '', false);
				$pdfBig->SetXY(55, $y_start+3);
				$pdfBig->MultiCell(50, 5, '','LRTB', 'R', 0, 0, '', '', true, 0, true);
				$pdfBig->MultiCell(54, 5, '','LRTB',  'L', 0, 0, '', '', true, 0, true);
				
				$pdfBig->SetXY(55, $y_start+3);
				$pdfBig->MultiCell(50, 5, 'CGPA',0, 'R', 0, 0, '', '', true, 0, true);
				$pdfBig->SetXY(106, $y_start+3);
				$pdfBig->MultiCell(54, 5, 'Award of Class',0,  'L', 0, 0, '', '', true, 0, true);
				
				$pdfBig->SetFont($trebuc, '', 10, '', false);
				$cy_start=$y_start+8;
				for ($crow = 0; $crow < $cgpa_count; $crow++) {
					$pdfBig->SetXY(55, $cy_start);
					$pdfBig->MultiCell(50, 5, '', 'LRTB', 'R', 0, 0);					
					$pdfBig->MultiCell(54, 5, '', 'LRTB', 'L', 0, 0);
					
					$pdfBig->SetXY(55, $cy_start);
					$pdfBig->MultiCell(49, 5, $cgpa_class[$crow][0], 0, 'R', 0, 0);					
					$pdfBig->SetXY(106, $cy_start);
					$pdfBig->MultiCell(54, 5, $cgpa_class[$crow][1], 0, 'L', 0, 0);
					$cy_start=$cy_start+5;
				}
							
			}
			elseif($points=="10"){
				$pdfBig->SetFont($trebucb, '', 10, '', false);
				$pdfBig->SetXY(55, 21);
				$pdfBig->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
				$pdfBig->MultiCell(29, 11, 'Numeric<br />Grade','LRTB', 'C', 0, 0, '', '', true, 0, true);
				$pdfBig->setCellPaddings( $left = '', $top = '2', $right = '', $bottom = '');
				$pdfBig->MultiCell(46, 11, 'Letter Grade','LRTB',  'C', 0, 0, '', '', true, 0, true);
				$pdfBig->setCellPaddings( $left = '', $top = '1.5', $right = '', $bottom = '');
				$pdfBig->MultiCell(29, 11, 'Grade Point<br><span style="font-size:9;font-family:'.$trebucb.';">(10 pt scale)</span>','LRTB',  'C', 0, 0, '', '', true, 0, true);
				$pdfBig->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');				
				$pdfBig->SetFont($trebuc, '', 10, '', false);
				$y_start=32;	
				for ($grow = 0; $grow < $grades_count; $grow++) {
					$pdfBig->SetXY(55, $y_start);
					$pdfBig->MultiCell(29, 5, $grades[$grow][0], 'LRTB', 'C', 0, 0);
					$pdfBig->MultiCell(46, 5, $grades[$grow][1], 'LRTB', 'C', 0, 0);
					$pdfBig->MultiCell(29, 5, $grades[$grow][2], 'LRTB', 'C', 0, 0);
					$y_start=$y_start+5;
				}	
				
				$pdfBig->SetFont($trebucb, '', 10, '', false);				
				$pdfBig->SetXY(55, $y_start+3);
				$pdfBig->MultiCell(50, 5, 'CGPA','LRTB', 'R', 0, 0, '', '', true, 0, true);
				$pdfBig->MultiCell(54, 5, 'Award of Class','LRTB',  'L', 0, 0, '', '', true, 0, true);				
				$pdfBig->SetFont($trebuc, '', 10, '', false);
				$cy_start=$y_start+8;
				for ($crow = 0; $crow < $cgpa_count; $crow++) {
					$pdfBig->SetXY(55, $cy_start);
					$pdfBig->MultiCell(50, 5, '', 'LRTB', 'R', 0, 0);					
					$pdfBig->MultiCell(54, 5, '', 'LRTB', 'L', 0, 0);
					
					$pdfBig->SetXY(55, $cy_start);
					$pdfBig->MultiCell(49, 5, $cgpa_class[$crow][0], 0, 'R', 0, 0);					
					$pdfBig->SetXY(106, $cy_start);
					$pdfBig->MultiCell(54, 5, $cgpa_class[$crow][1], 0, 'L', 0, 0);
					$cy_start=$cy_start+5;
				}				
			}
			
			$pdfBig->SetFont($trebuc, '', 10, '', false);
			$ly_start=$cy_start+5;
			for ($lrow = 0; $lrow < $list_count; $lrow++) {
				$pdfBig->SetXY(13, $ly_start);	
				$pdfBig->MultiCell(0, 7, $list[$lrow][0], 0, 'L', 0, 0, '', '', true, 0, true);
				$ly_start=$ly_start+9;
			}
			$pdfBig->SetFont($trebuc, '', 10, '', false);
			$dy_start=$ly_start+3;
			for ($drow = 0; $drow < $denote_count; $drow++) {
				$pdfBig->SetXY(20.5, $dy_start);	
				$pdfBig->MultiCell(15, 5, $denote[$drow][0], 0, 'L', 0, 0);
				$pdfBig->MultiCell(0, 5, $denote[$drow][1], 0, 'L', 0, 0);
				$dy_start=$dy_start+5;
			}
			$pdfBig->SetFont($trebuc, '', 10, '', false);
			$n_start=$dy_start+5;
			$pdfBig->SetXY(13, $n_start);
			$pdfBig->MultiCell(0, 0, $note, 0, 'L', 0, 0, '', '', true, 0, true);
			
			/*
			$pdfBig->SetFont($poppinsM, '', 13, '', false);
			$pdfBig->SetXY(9, 275);
			$pdfBig->MultiCell(0, 180, $university_name, 0, 'C', 0, 0, '', '', true, 0, true);
			$pdfBig->SetFont($poppinsR, '', 10, '', false);
			$pdfBig->SetXY(9, 281);
			$pdfBig->MultiCell(0, 180, $line2, 0, 'C', 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY(9, 286);
			$pdfBig->MultiCell(0, 180, $line3, 0, 'C', 0, 0, '', '', true, 0, true);
			*/
			
			/*Point Page End*/			
			
			if($previewPdf!=1){

				$certName = str_replace("/", "_", $GUID) .".pdf";
				$certName = str_replace(" ", "_", $certName) .".pdf";
				
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

				$this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'Memorandum',$admin_id,$card_serial_no);

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
            //delete temp dir 26-04-2022 
        	CoreHelper::rrmdir($tmpDir);	
        } 
        
       if($previewPdf!=1){
        $this->updateCardNo('anuGC',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
       }
       $msg = '';
        
        $file_name =  str_replace("/", "_",'anuGC'.date("Ymdhms")).'.pdf';
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


       $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        if($previewPdf!=1){
            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="anuGC";
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
			$msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf download' target='_blank'>Here</a> to download file<b>";
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

        /*copy($file1, $file2);        
        $aws_qr = \File::copy($file2,$pdfActualPath);
        @unlink($file2);*/
		$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;
		$output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName; 
		CoreHelper::compressPdfFile($source,$output);
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
        $resultu = StudentTable::where('serial_no',''.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>2]);
        }
        
    }
    
    public function testUpload($certName, $pdfActualPath)
    {
        // FTP server details
        $ftpHost = \Config::get('constant.anu_ftp_host');
        $ftpPort = \Config::get('constant.anu_ftp_port');
        $ftpUsername = \Config::get('constant.anu_ftp_username');
        $ftpPassword = \Config::get('constant.anu_ftp_pass');        
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
