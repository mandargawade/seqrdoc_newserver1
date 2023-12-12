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
class PdfGenerateSdmBptJob
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
		$GT_Max_key=$pdf_data['GT_Max_key'];
		$GT_Min_key=$pdf_data['GT_Min_key'];
		$GT_Sec_key=$pdf_data['GT_Sec_key'];
		$Grand_Total_In_words_key=$pdf_data['Grand_Total_In_words_key'];
		$Percentage_key=$pdf_data['Percentage_key'];
		$Result_key=$pdf_data['Result_key'];
		$Date_key=$pdf_data['Date_key'];
		$Student_image_key=$pdf_data['Student_image_key'];
		$Batch_of_key=$pdf_data['Batch_of_key'];
		$Aadhar_No_key=$pdf_data['Aadhar_No_key'];
		$DOB_key=$pdf_data['DOB_key'];
		$course_key=$pdf_data['course_key'];
		$subj_col= $pdf_data['subj_col'];
		$subj_start=$pdf_data['subj_start'];
		$subj_end=$pdf_data['subj_end'];
        $template_id=$pdf_data['template_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];
        $photo_col=$Student_image_key;
        
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
        $verdana = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\verdana.ttf', 'TrueTypeUnicode', '', 96);

        $MyriadProRegular = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\MyriadProRegular.ttf', 'TrueTypeUnicode', '', 96);
        $MyriadProBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\MyriadProBold.ttf', 'TrueTypeUnicode', '', 96);
        $MyriadProItalic = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\MyriadProItalic.ttf', 'TrueTypeUnicode', '', 96);
        $MyriadProBoldItalic = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\MyriadProBoldItalic.ttf', 'TrueTypeUnicode', '', 96);
        $MinionProRegular = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\MinionProRegular.ttf', 'TrueTypeUnicode', '', 96);
        $MinionProBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\MinionProBold.ttf', 'TrueTypeUnicode', '', 96);

        $preview_serial_no=1;
		//$card_serial_no="";
        $log_serial_no = 1;
        $cardDetails=$this->getNextCardNo('sdmGC');
        $card_serial_no=$cardDetails->next_serial_no;
        $generated_documents=0;  //for custom loader
        foreach ($studentDataOrg as $studentData) {
         
			if($card_serial_no>999999&&$previewPdf!=1){
				echo "<h5>Your card series ended...!</h5>";
				exit;
			}
            //For Custom Loader
            $startTimeLoader =  date('Y-m-d H:i:s');        
			$high_res_bg="SDM_University_BG.jpg"; // SDM_University_BG.jpg, SDM_University.jpg
			$low_res_bg="SDM_University_BG.jpg";
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
				$date_noy = 35;
				$date_nostr = 'DRAFT '.date('d-m-Y H:i:s');
				$pdfBig->SetFont($arialb, '', $date_font_size, '', false);
				$pdfBig->SetTextColor(192,192,192);
				$pdfBig->SetXY($date_nox, $date_noy);
				//$pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
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
			
            //if($previewPdf!=1){            
                /*$x= 160;
                $y = 12;
                $font_size=12;
                if($previewPdf!=1){
					$str = str_pad($card_serial_no, 6, '0', STR_PAD_LEFT);
				}else{
					$str = str_pad($preview_serial_no, 6, '0', STR_PAD_LEFT);	
				}
                $strArr = str_split($str);
                $x_org=$x;
                $y_org=$y;
                $font_size_org=$font_size;
				
				$pdfBig->SetFont($arialb,'', 9, '', false);
				$pdfBig->SetXY($x, $y);
				$pdfBig->MultiCell(40, 0, '<span style="font-size:9; font-family:'.$arialb.';">Serial No. </span><span style="font-size:10; font-family:'.$verdana.';">'.$str.'</span>', 0, 'R', 0, 0, '', '', true, 0, true);
				
				$pdf->SetFont($arialb,'', 9, '', false);
				$pdf->SetXY($x, $y);
				$pdf->MultiCell(40, 0, '<span style="font-size:9; font-family:'.$arialb.';">Serial No. </span><span style="font-size:10; font-family:'.$verdana.';">'.$str.'</span>', 0, 'R', 0, 0, '', '', true, 0, true);
				*/
			//}
			 
			//images
			if($studentData[$photo_col]!=''){
				//path of photos
				$profile_path_org = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[$photo_col];  
				//set profile image   
				$profilex = 15; //169.3
				$profiley = 52.3;
				$profileWidth = 20.3;
				$profileHeight = 25.3;
				$pdf->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
				$pdfBig->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);  
				$pdf->setPageMark();
				$pdfBig->setPageMark();
			}
			$title1_x = 14; 
            $left_str_x = 15;
			
			/******Start pdfBig ******/ 
			$pdfBig->SetFont($arialNarrow, '', 2, '', false);			
			$pdfBig->SetXY($left_str_x, 97);	
			$pdfBig->MultiCell(180, 13, '', 'LRTB', 'C', 0, 0);	//Entire Table
			$pdfBig->SetXY(89.5, 106);	
			$pdfBig->MultiCell(105.5, 0, '', 'T', 'C', 0, 0);	
			$pdfBig->SetXY($left_str_x, 110);	
			$pdfBig->MultiCell(180, 0, '', 'T', 'C', 0, 0);	
			
			$pdfBig->SetXY(24.5, 97);	
			$pdfBig->MultiCell(65, 13, '', 'LR', 'C', 0, 0);  //Subject
			$pdfBig->SetXY(89.5, 97);	
			$pdfBig->MultiCell(20, 13, '', 'L', 'C', 0, 0); //UE
			$pdfBig->SetXY(108, 97);
			$pdfBig->MultiCell(22, 13, '', 'R', 'C', 0, 0); //Viva
			$pdfBig->SetXY(125, 97);
			$pdfBig->MultiCell(24, 13, '', 'R', 'C', 0, 0); //IA
			$pdfBig->SetXY(145, 97);	
			$pdfBig->MultiCell(27, 13, '', 'R', 'C', 0, 0); //Total
			$pdfBig->SetXY(172, 97);	
			$pdfBig->MultiCell(23, 9, '', 'L', 'C', 0, 0); //Remark
			
			$pdfBig->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdfBig->SetXY(14.5, 100);
			$pdfBig->MultiCell(12.5, 5, 'SL. NO.', 0, 'L', 0, 0);
			$pdfBig->SetXY(35, 100);
			$pdfBig->MultiCell(40.3, 5, 'SUBJECT', 0, 'C', 0, 0);
			$pdfBig->SetXY(88.5, 97.5);
			$pdfBig->MultiCell(40.5, 5, 'UNIVERSITY EXAMINATION', 0, 'C', 0, 0);
			$pdfBig->SetFont($MyriadProRegular, '', 6.5, '', false);
			$pdfBig->SetXY(88.5, 102);
			$pdfBig->MultiCell(22, 5, 'THEORY/PRACTICAL', 0, 'C', 0, 0);			
			$pdfBig->SetXY(110, 102);
			$pdfBig->MultiCell(20, 5, 'VIVA', 0, 'C', 0, 0);
			$pdfBig->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdfBig->SetXY(127.5, 97.5);
			$pdfBig->MultiCell(24, 5, 'INTERNAL ASSESSMENT', 0, 'C', 0, 0);
			$pdfBig->SetXY(147, 100);
			$pdfBig->MultiCell(27, 5, 'TOTAL', 0, 'C', 0, 0);
			$pdfBig->SetXY(172, 100);
			$pdfBig->MultiCell(23, 5, 'REMARK', 0, 'C', 0, 0);
			
			
			$pdfBig->SetXY(89.5, 101);	
			$pdfBig->MultiCell(40.5, 0, '', 'T', 'C', 0, 0); //HR Line
			$pdfBig->SetXY(89.5, 101);	
			$pdfBig->MultiCell(20, 9.5, '', 'R', 'C', 0, 0); //VR Line
			
			$pdfBig->SetXY(99.5, 106);
			$pdfBig->MultiCell(20.2, 4, '', 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(19.8, 4, '', 'R', 'C', 0, 0);
			$pdfBig->SetXY(156.7, 106);
			$pdfBig->MultiCell(7.7, 4, '', 'LR', 'C', 0, 0);			
			
			$pdfBig->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdfBig->SetXY(90, 106.5);
			$pdfBig->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdfBig->SetXY(110, 106.5);
			$pdfBig->MultiCell(10, 0, 'MAX', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdfBig->SetXY(131, 106.5);
			$pdfBig->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdfBig->SetXY(148.5, 106.5);
			$pdfBig->MultiCell(8, 0, 'MAX', 0, 'C', 0, 0);
			$pdfBig->MultiCell(8, 0, 'MIN', 0, 'C', 0, 0);
			$pdfBig->MultiCell(8, 0, 'SEC', 0, 'C', 0, 0);
			/******End pdfBig ******/ 
			
			/******Start pdf ******/ 
			
			$pdf->SetFont($arialNarrow, '', 2, '', false);			
			$pdf->SetXY($left_str_x, 97);	
			$pdf->MultiCell(180, 13, '', 'LRTB', 'C', 0, 0);	//Entire Table
			$pdf->SetXY(89.5, 106);	
			$pdf->MultiCell(105.5, 0, '', 'T', 'C', 0, 0);	
			$pdf->SetXY($left_str_x, 110);	
			$pdf->MultiCell(180, 0, '', 'T', 'C', 0, 0);	
			
			$pdf->SetXY(24.5, 97);	
			$pdf->MultiCell(65, 13, '', 'LR', 'C', 0, 0);  //Subject
			$pdf->SetXY(89.5, 97);	
			$pdf->MultiCell(20, 13, '', 'L', 'C', 0, 0); //UE
			$pdf->SetXY(108, 97);
			$pdf->MultiCell(22, 13, '', 'R', 'C', 0, 0); //Viva
			$pdf->SetXY(125, 97);
			$pdf->MultiCell(24, 13, '', 'R', 'C', 0, 0); //IA
			$pdf->SetXY(145, 97);	
			$pdf->MultiCell(27, 13, '', 'R', 'C', 0, 0); //Total
			$pdf->SetXY(172, 97);	
			$pdf->MultiCell(23, 9, '', 'L', 'C', 0, 0); //Remark
			
			$pdf->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdf->SetXY(14.5, 100);
			$pdf->MultiCell(12.5, 5, 'SL. NO.', 0, 'L', 0, 0);
			$pdf->SetXY(35, 100);
			$pdf->MultiCell(40.3, 5, 'SUBJECT', 0, 'C', 0, 0);
			$pdf->SetXY(88.5, 97.5);
			$pdf->MultiCell(40.5, 5, 'UNIVERSITY EXAMINATION', 0, 'C', 0, 0);
			$pdf->SetFont($MyriadProRegular, '', 6.5, '', false);
			$pdf->SetXY(88.5, 102);
			$pdf->MultiCell(22, 5, 'THEORY/PRACTICAL', 0, 'C', 0, 0);			
			$pdf->SetXY(110, 102);
			$pdf->MultiCell(20, 5, 'VIVA', 0, 'C', 0, 0);
			$pdf->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdf->SetXY(127.5, 97.5);
			$pdf->MultiCell(24, 5, 'INTERNAL ASSESSMENT', 0, 'C', 0, 0);
			$pdf->SetXY(147, 100);
			$pdf->MultiCell(27, 5, 'TOTAL', 0, 'C', 0, 0);
			$pdf->SetXY(172, 100);
			$pdf->MultiCell(23, 5, 'REMARK', 0, 'C', 0, 0);
			
			
			$pdf->SetXY(89.5, 101);	
			$pdf->MultiCell(40.5, 0, '', 'T', 'C', 0, 0); //HR Line
			$pdf->SetXY(89.5, 101);	
			$pdf->MultiCell(20, 9.5, '', 'R', 'C', 0, 0); //VR Line
			
			$pdf->SetXY(99.5, 106);
			$pdf->MultiCell(20.2, 4, '', 'LR', 'C', 0, 0);
			$pdf->MultiCell(19.8, 4, '', 'R', 'C', 0, 0);
			$pdf->SetXY(156.7, 106);
			$pdf->MultiCell(7.7, 4, '', 'LR', 'C', 0, 0);			
			
			$pdf->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdf->SetXY(90, 106.5);
			$pdf->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdf->SetXY(110, 106.5);
			$pdf->MultiCell(10, 0, 'MAX', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdf->SetXY(131, 106.5);
			$pdf->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdf->SetXY(148.5, 106.5);
			$pdf->MultiCell(8, 0, 'MAX', 0, 'C', 0, 0);
			$pdf->MultiCell(8, 0, 'MIN', 0, 'C', 0, 0);
			$pdf->MultiCell(8, 0, 'SEC', 0, 'C', 0, 0);
			
			/******End pdf ******/ 
			
			$unique_id = trim($studentData[0]);
			$mc_no = trim($studentData[0]);
			$year = trim($studentData[1]);
			$year_examination = strtoupper(trim($studentData[2]));
			$candidate_name = strtoupper(trim($studentData[3]));
			$ureg_no = strtoupper(trim($studentData[4]));
			$Grand_Total_Max = trim($studentData[$GT_Max_key]);			
			$Grand_Total_Min = trim($studentData[$GT_Min_key]);
			$Grand_Total_Sec = trim($studentData[$GT_Sec_key]);
			$Grand_Total_In_Words = trim($studentData[$Grand_Total_In_words_key]);
			$Percentage = trim($studentData[$Percentage_key]);
			$Result = strtoupper(trim($studentData[$Result_key]));
			$Date = trim($studentData[$Date_key]);
			$batch_of_qr = trim($studentData[$Batch_of_key]);
			$aadhar_number_qr = trim($studentData[$Aadhar_No_key]);
			$DOB = trim($studentData[$DOB_key]);			
			$course = strtoupper(trim($studentData[$course_key]));
            $subjectData = array_slice($studentData, $subj_start, $subj_end);
            $subjectsArr=array_chunk($subjectData, $subj_col);  
            $pdf->SetFont($arial, '', 10, '', false); 
            $pdfBig->SetFont($arial, '', 10, '', false); 
			$QR_Output="Batch Of: $batch_of_qr\nName of the Student: $candidate_name\nAadhar Number: $aadhar_number_qr\nCourse: $course\nYear: $year\nDate Of Birth: $DOB";            
			/******Start pdfBig ******/  
			
            // start invisible data
            /*$pdfBig->SetFont($MyriadProBold, '', 12, '', false); 
            $pdfBig->SetTextColor(255, 255, 0);        
            $pdfBig->SetXY($title1_x, 78);
            $pdfBig->Cell(0, 0, $candidate_name, 0, false, 'L');
			$pdfBig->SetXY(157, 162.5);	
			$pdfBig->MultiCell(50, 6, $Percentage, 0, 'L', 0, 0);*/ 
            // end invisible data 
			/*pdfBig*/
			$pdfBig->SetXY(160, 45); //160, 37
			$pdfBig->SetTextColor(0, 0, 0);
			$pdfBig->SetFont($MyriadProBold, 'B', 9, '', false);	
			$pdfBig->MultiCell(52, 0, 'MC No: ', 0, 'L', 0, 0);
			$pdfBig->SetXY(171, 45);
			$pdfBig->SetFont($MyriadProRegular, '', 9, '', false);	
			$pdfBig->MultiCell(52, 0, $mc_no, 0, 'L', 0, 0);
			
			$pdfBig->SetFont($MyriadProBold, 'B', 15, '', false);
            $pdfBig->SetTextColor(0, 0, 0);
			$pdfBig->SetXY($title1_x, 45);
			$pdfBig->Cell(188, 0, "STATEMENT OF MARKS", 0, false, 'C');            
			//$pdfBig->SetFont($MyriadProRegular, '', 15, '', false);
			//$pdfBig->SetXY($title1_x, 52.5);
			//$pdfBig->Cell(188, 0, "BPT", 0, false, 'C');
			$pdfBig->SetFont($MyriadProRegular, '', 15, '', false);
			$pdfBig->SetXY($title1_x, 55.5);
			$pdfBig->Cell(188, 0, $year, 0, false, 'C');
			$pdfBig->SetFont($MinionProBold, '', 12, '', false);			
			$pdfBig->SetXY($title1_x, 65.8);
			$pdfBig->Cell(188, 0, $year_examination, 0, false, 'C');
						
			$pdfBig->SetFont($MyriadProItalic, 'I', 12, '', false);
            $pdfBig->SetXY($title1_x, 82);
            $pdfBig->Cell(0, 0, 'Name of the Student:', 0, false, 'L');
            $pdfBig->SetXY($title1_x, 88);
            $pdfBig->Cell(0, 0, "University Reg. No.:", 0, false, 'L');
            $pdfBig->SetXY($title1_x, $left_title3_y);
			
			$pdfBig->SetFont($MyriadProBoldItalic, 'BI', 12, '', false);
            $pdfBig->SetXY(51, 82);
            $pdfBig->Cell(0, 0, $candidate_name, 0, false, 'L');
			$pdfBig->SetXY(48, 88);
            $pdfBig->Cell(0, 0, $ureg_no, 0, false, 'L');            			
			
			/*pdf*/
			$pdf->SetXY(160, 45); //160, 37
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont($MyriadProBold, 'B', 9, '', false);	
			$pdf->MultiCell(52, 0, 'MC No: ', 0, 'L', 0, 0);
			$pdf->SetXY(171, 45);
			$pdf->SetFont($MyriadProRegular, '', 9, '', false);	
			$pdf->MultiCell(52, 0, $mc_no, 0, 'L', 0, 0);
			
			$pdf->SetFont($MyriadProBold, 'B', 15, '', false);
            $pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($title1_x, 45);
			$pdf->Cell(188, 0, "STATEMENT OF MARKS", 0, false, 'C');            
			//$pdf->SetFont($MyriadProRegular, '', 15, '', false);
			//$pdf->SetXY($title1_x, 52.5);
			//$pdf->Cell(188, 0, "BPT", 0, false, 'C');
			$pdf->SetFont($MyriadProRegular, '', 15, '', false);
			$pdf->SetXY($title1_x, 55.5);
			$pdf->Cell(188, 0, $year, 0, false, 'C');
			$pdf->SetFont($MinionProBold, '', 12, '', false);			
			$pdf->SetXY($title1_x, 65.8);
			$pdf->Cell(188, 0, $year_examination, 0, false, 'C');
						
			$pdf->SetFont($MyriadProItalic, 'I', 12, '', false);
            $pdf->SetXY($title1_x, 82);
            $pdf->Cell(0, 0, 'Name of the Student:', 0, false, 'L');
            $pdf->SetXY($title1_x, 88);
            $pdf->Cell(0, 0, "University Reg. No.:", 0, false, 'L');
            $pdf->SetXY($title1_x, $left_title3_y);
			
			$pdf->SetFont($MyriadProBoldItalic, 'BI', 12, '', false);
            $pdf->SetXY(51, 82);
            $pdf->Cell(0, 0, $candidate_name, 0, false, 'L');
			$pdf->SetXY(48, 88);
            $pdf->Cell(0, 0, $ureg_no, 0, false, 'L'); 			
			
			/*Subject*/
			$subj_y=110;			
			foreach ($subjectsArr as $subjectDatas){
				$th_pr_val = strtoupper(trim($subjectDatas[0])); //TH|PR, TH, PR	
				$Sr_no = trim($subjectDatas[1]);	
				$Subject_Name = strtoupper(trim($subjectDatas[2]));	
				$UE_Max_TH = trim($subjectDatas[3]);	
				$UE_Sec_TH = trim($subjectDatas[4]);	
				$V_Max_TH = trim($subjectDatas[5]);	
				$V_Sec_TH = trim($subjectDatas[6]);	
				$IA_Max_TH = trim($subjectDatas[7]);	
				$IA_Sec_TH = trim($subjectDatas[8]);	
				$T_Max_TH = trim($subjectDatas[9]);	
				$T_Min_TH = trim($subjectDatas[10]);	
				$T_Sec_TH = trim($subjectDatas[11]);	
				$Remark_TH = strtoupper(trim($subjectDatas[12]));	
				$UE_Max_PR = trim($subjectDatas[13]);	
				$UE_Sec_PR = trim($subjectDatas[14]);	
				$V_Max_PR = trim($subjectDatas[15]);	
				$V_Sec_PR = trim($subjectDatas[16]);	
				$IA_Max_PR = trim($subjectDatas[17]);	
				$IA_Sec_PR = trim($subjectDatas[18]);	
				$T_Max_PR = trim($subjectDatas[19]);	
				$T_Min_PR = trim($subjectDatas[20]);	
				$T_Sec_PR = trim($subjectDatas[21]);	
				$Remark_PR = strtoupper(trim($subjectDatas[22]));
				/*start pdfbig line*/
				// store current object
				$pdfBig->startTransaction();
				$pdfBig->SetFont($MyriadProRegular, '', 10, '', false);	 				
				// get the number of lines
				$lines = $pdfBig->MultiCell(57, 0, $Subject_Name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0);
				// restore previous object
				$pdfBig=$pdfBig->rollbackTransaction();		
				if ($th_pr_val == 'TH|PR') {
					if($lines>2){
						$th_height = 13.5;
						$th_height_half = 6.75;
					}else{
						$th_height = 10;
						$th_height_half = 5;
					}
				}else{
					if($lines>2){
						$th_height = 13.5;
						$th_height_half = 6.75;
					}else{
						if($lines>1){
							$th_height = 10;
							if ($th_pr_val == 'TH|PR') {
								$th_height_half = 5;
							}else{
								$th_height_half = 10;
							}
						}else{
							$th_height = 5;
							$th_height_half = 5;
						}
					}
				}	
				/*end pdfbig line*/	
				/*start pdf line*/
				// store current object
				$pdf->startTransaction();
				$pdf->SetFont($MyriadProRegular, '', 10, '', false);	 				
				// get the number of lines
				$lines_pdf = $pdf->MultiCell(57, 0, $Subject_Name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0);
				// restore previous object
				$pdf=$pdf->rollbackTransaction();		
				if ($th_pr_val == 'TH|PR') {
					if($lines_pdf>2){
						$th_height2 = 13.5;
						$th_height_half2 = 6.75;
					}else{
						$th_height2 = 10;
						$th_height_half2 = 5;
					}
				}else{
					if($lines_pdf>2){
						$th_height2 = 13.5;
						$th_height_half2 = 6.75;
					}else{
						if($lines_pdf>1){
							$th_height2 = 10;
							if ($th_pr_val == 'TH|PR') {
								$th_height_half2 = 5;
							}else{
								$th_height_half2 = 10;
							}
						}else{
							$th_height2 = 5;
							$th_height_half2 = 5;
						}
					}
				}	
				/*end pdf line*/					
							
				$pdfBig->SetFont($MyriadProRegular, '', 10, '', false);					
				$pdf->SetFont($MyriadProRegular, '', 10, '', false);					
				if ($th_pr_val == 'TH|PR') {
					//pdfBig
					$pdfBig->SetXY($left_str_x, $subj_y);
					$pdfBig->MultiCell(9.5, $th_height, $Sr_no, 1, 'C', 0, 0);
					$pdfBig->MultiCell(57, $th_height, $Subject_Name, 1, 'L', 0, 0);
					$pdfBig->SetXY(81.5, $subj_y);
					$pdfBig->MultiCell(8, $th_height_half, 'TH', 1, 'C', 0, 0); 
					$pdfBig->MultiCell(10, $th_height_half, $UE_Max_TH, 1, 'C', 0, 0);
					$pdfBig->MultiCell(10, $th_height_half, $UE_Sec_TH, 1, 'C', 0, 0);
					$pdfBig->MultiCell(10.3, $th_height_half, $V_Max_TH, 1, 'C', 0, 0);
					$pdfBig->MultiCell(10.2, $th_height_half, $V_Sec_TH, 1, 'C', 0, 0);
					$pdfBig->MultiCell(9.5, $th_height_half, $IA_Max_TH, 1, 'C', 0, 0);
					$pdfBig->MultiCell(9.5, $th_height_half, $IA_Sec_TH, 1, 'C', 0, 0);
					$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Max_TH, 1, 'C', 0, 0);
					$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Min_TH, 1, 'C', 0, 0);
					$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Sec_TH, 1, 'C', 0, 0);
					$pdfBig->MultiCell(23, $th_height_half, $Remark_TH, 1, 'C', 0, 0);
					
					$pdfBig->SetXY(81.5, $subj_y+$th_height_half);					
					$pdfBig->MultiCell(8, $th_height_half, 'PR', 1, 'C', 0, 0); 
					$pdfBig->MultiCell(10, $th_height_half, $UE_Max_PR, 1, 'C', 0, 0);
					$pdfBig->MultiCell(10, $th_height_half, $UE_Sec_PR, 1, 'C', 0, 0);					
					$pdfBig->MultiCell(10.3, $th_height_half, $V_Max_PR, 1, 'C', 0, 0);
					$pdfBig->MultiCell(10.2, $th_height_half, $V_Sec_PR, 1, 'C', 0, 0);
					$pdfBig->MultiCell(9.5, $th_height_half, $IA_Max_PR, 1, 'C', 0, 0);
					$pdfBig->MultiCell(9.5, $th_height_half, $IA_Sec_PR, 1, 'C', 0, 0);
					$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Max_PR, 1, 'C', 0, 0);
					$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Min_PR, 1, 'C', 0, 0);
					$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Sec_PR, 1, 'C', 0, 0);
					$pdfBig->MultiCell(23, $th_height_half, $Remark_PR, 1, 'C', 0, 0);
					//pdf
					$pdf->SetXY($left_str_x, $subj_y);
					$pdf->MultiCell(9.5, $th_height2, $Sr_no, 1, 'C', 0, 0);
					$pdf->MultiCell(57, $th_height2, $Subject_Name, 1, 'L', 0, 0);
					$pdf->SetXY(81.5, $subj_y);
					$pdf->MultiCell(8, $th_height_half2, 'TH', 1, 'C', 0, 0); 
					$pdf->MultiCell(10, $th_height_half2, $UE_Max_TH, 1, 'C', 0, 0);
					$pdf->MultiCell(10, $th_height_half2, $UE_Sec_TH, 1, 'C', 0, 0);
					$pdf->MultiCell(10.3, $th_height_half2, $V_Max_TH, 1, 'C', 0, 0);
					$pdf->MultiCell(10.2, $th_height_half2, $V_Sec_TH, 1, 'C', 0, 0);
					$pdf->MultiCell(9.5, $th_height_half2, $IA_Max_TH, 1, 'C', 0, 0);
					$pdf->MultiCell(9.5, $th_height_half2, $IA_Sec_TH, 1, 'C', 0, 0);
					$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Max_TH, 1, 'C', 0, 0);
					$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Min_TH, 1, 'C', 0, 0);
					$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Sec_TH, 1, 'C', 0, 0);
					$pdf->MultiCell(23, $th_height_half2, $Remark_TH, 1, 'C', 0, 0);
					
					$pdf->SetXY(81.5, $subj_y+$th_height_half2);					
					$pdf->MultiCell(8, $th_height_half2, 'PR', 1, 'C', 0, 0); 
					$pdf->MultiCell(10, $th_height_half2, $UE_Max_PR, 1, 'C', 0, 0);
					$pdf->MultiCell(10, $th_height_half2, $UE_Sec_PR, 1, 'C', 0, 0);					
					$pdf->MultiCell(10.3, $th_height_half2, $V_Max_PR, 1, 'C', 0, 0);
					$pdf->MultiCell(10.2, $th_height_half2, $V_Sec_PR, 1, 'C', 0, 0);
					$pdf->MultiCell(9.5, $th_height_half2, $IA_Max_PR, 1, 'C', 0, 0);
					$pdf->MultiCell(9.5, $th_height_half2, $IA_Sec_PR, 1, 'C', 0, 0);
					$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Max_PR, 1, 'C', 0, 0);
					$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Min_PR, 1, 'C', 0, 0);
					$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Sec_PR, 1, 'C', 0, 0);
					$pdf->MultiCell(23, $th_height_half2, $Remark_PR, 1, 'C', 0, 0);					
					
				}else{
					$pdfBig->SetXY($left_str_x, $subj_y);
					$pdfBig->MultiCell(9.5, $th_height, $Sr_no, 1, 'C', 0, 0);
					$pdfBig->MultiCell(57, $th_height, $Subject_Name, 1, 'L', 0, 0);					
					$pdfBig->SetXY(81.5, $subj_y);
					
					$pdf->SetXY($left_str_x, $subj_y);
					$pdf->MultiCell(9.5, $th_height2, $Sr_no, 1, 'C', 0, 0);
					$pdf->MultiCell(57, $th_height2, $Subject_Name, 1, 'L', 0, 0);					
					$pdf->SetXY(81.5, $subj_y);
					if ($th_pr_val == 'TH') {
						$pdfBig->MultiCell(8, $th_height_half, 'TH', 1, 'C', 0, 0); 
						$pdfBig->MultiCell(10, $th_height_half, $UE_Max_TH, 1, 'C', 0, 0);
						$pdfBig->MultiCell(10, $th_height_half, $UE_Sec_TH, 1, 'C', 0, 0);
						$pdfBig->MultiCell(10.3, $th_height_half, $V_Max_TH, 1, 'C', 0, 0);
						$pdfBig->MultiCell(10.2, $th_height_half, $V_Sec_TH, 1, 'C', 0, 0);
						$pdfBig->MultiCell(9.5, $th_height_half, $IA_Max_TH, 1, 'C', 0, 0);
						$pdfBig->MultiCell(9.5, $th_height_half, $IA_Sec_TH, 1, 'C', 0, 0);
						$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Max_TH, 1, 'C', 0, 0);
						$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Min_TH, 1, 'C', 0, 0);
						$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Sec_TH, 1, 'C', 0, 0);
						$pdfBig->MultiCell(23, $th_height_half, $Remark_TH, 1, 'C', 0, 0);
						
						$pdf->MultiCell(8, $th_height_half2, 'TH', 1, 'C', 0, 0); 
						$pdf->MultiCell(10, $th_height_half2, $UE_Max_TH, 1, 'C', 0, 0);
						$pdf->MultiCell(10, $th_height_half2, $UE_Sec_TH, 1, 'C', 0, 0);
						$pdf->MultiCell(10.3, $th_height_half2, $V_Max_TH, 1, 'C', 0, 0);
						$pdf->MultiCell(10.2, $th_height_half2, $V_Sec_TH, 1, 'C', 0, 0);
						$pdf->MultiCell(9.5, $th_height_half2, $IA_Max_TH, 1, 'C', 0, 0);
						$pdf->MultiCell(9.5, $th_height_half2, $IA_Sec_TH, 1, 'C', 0, 0);
						$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Max_TH, 1, 'C', 0, 0);
						$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Min_TH, 1, 'C', 0, 0);
						$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Sec_TH, 1, 'C', 0, 0);
						$pdf->MultiCell(23, $th_height_half2, $Remark_TH, 1, 'C', 0, 0);
					}
					if ($th_pr_val == 'PR') {
						$pdfBig->MultiCell(8, $th_height_half, 'PR', 1, 'C', 0, 0); 
						$pdfBig->MultiCell(10, $th_height_half, $UE_Max_PR, 1, 'C', 0, 0);
						$pdfBig->MultiCell(10, $th_height_half, $UE_Sec_PR, 1, 'C', 0, 0);					
						$pdfBig->MultiCell(10.3, $th_height_half, $V_Max_PR, 1, 'C', 0, 0);
						$pdfBig->MultiCell(10.2, $th_height_half, $V_Sec_PR, 1, 'C', 0, 0);
						$pdfBig->MultiCell(9.5, $th_height_half, $IA_Max_PR, 1, 'C', 0, 0);
						$pdfBig->MultiCell(9.5, $th_height_half, $IA_Sec_PR, 1, 'C', 0, 0);
						$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Max_PR, 1, 'C', 0, 0);
						$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Min_PR, 1, 'C', 0, 0);
						$pdfBig->MultiCell(7.666666666666667, $th_height_half, $T_Sec_PR, 1, 'C', 0, 0);
						$pdfBig->MultiCell(23, $th_height_half, $Remark_PR, 1, 'C', 0, 0);
						
						$pdf->MultiCell(8, $th_height_half2, 'PR', 1, 'C', 0, 0); 
						$pdf->MultiCell(10, $th_height_half2, $UE_Max_PR, 1, 'C', 0, 0);
						$pdf->MultiCell(10, $th_height_half2, $UE_Sec_PR, 1, 'C', 0, 0);					
						$pdf->MultiCell(10.3, $th_height_half2, $V_Max_PR, 1, 'C', 0, 0);
						$pdf->MultiCell(10.2, $th_height_half2, $V_Sec_PR, 1, 'C', 0, 0);
						$pdf->MultiCell(9.5, $th_height_half2, $IA_Max_PR, 1, 'C', 0, 0);
						$pdf->MultiCell(9.5, $th_height_half2, $IA_Sec_PR, 1, 'C', 0, 0);
						$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Max_PR, 1, 'C', 0, 0);
						$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Min_PR, 1, 'C', 0, 0);
						$pdf->MultiCell(7.666666666666667, $th_height_half2, $T_Sec_PR, 1, 'C', 0, 0);
						$pdf->MultiCell(23, $th_height_half2, $Remark_PR, 1, 'C', 0, 0);
					}
				}
                $subj_y=$subj_y+$th_height;
            }
			
			/******Start pdfBig ******/ 
			/*Grand Total*/
			$pdfBig->SetFont($MyriadProBold, 'B', 10, '', false);
			$pdfBig->SetXY($left_str_x, $subj_y);	
			$pdfBig->MultiCell(134, 5, 'GRAND TOTAL:', 'LRB', 'R', 0, 0); 
			$pdfBig->SetFont($MyriadProRegular, '', 10, '', false);
			$pdfBig->MultiCell(7.666666666666667, 5, $Grand_Total_Max, 1, 'C', 0, 0); 
			$pdfBig->MultiCell(7.666666666666667, 5, $Grand_Total_Min, 1, 'C', 0, 0); 
			$pdfBig->MultiCell(7.666666666666667, 5, $Grand_Total_Sec, 1, 'C', 0, 0); 
			$pdfBig->MultiCell(23, 5, '', 1, 'C', 0, 0); 
			$pdfBig->SetFont($MyriadProBold, 'B', 10, '', false);
			$pdfBig->SetXY($left_str_x, $subj_y+5);	
			$pdfBig->MultiCell(134, 5, 'GRAND TOTAL IN WORDS:', 'LR', 'R', 0, 0); 
			$pdfBig->MultiCell(46, 5, $Grand_Total_In_Words, 'R', 'L', 0, 0);
			$pdfBig->SetXY($left_str_x, $subj_y+10);	
			$pdfBig->MultiCell(134, 5, 'PERCENTAGE:', 'LR', 'R', 0, 0); 
			$pdfBig->MultiCell(46, 5, $Percentage, 'R', 'L', 0, 0);
			$pdfBig->SetXY($left_str_x, $subj_y+15);	
			$pdfBig->MultiCell(134, 5, 'RESULT:', 'LRB', 'R', 0, 0); 
			$pdfBig->MultiCell(46, 5, $Result, 'LRB', 'L', 0, 0);
						
			$pdfBig->SetXY(14, $subj_y+21.5);
			$pdfBig->SetFont($MyriadProItalic, 'I', 10, '', false);
			$pdfBig->MultiCell(0, 0, 'Min. Marks for Eligibility to appear for University Examination: 35% in each Theory and Practical Internal Assessment separately.<br>Min. Marks to Pass: 50% aggregate of each theory (Theory + IA + Viva) and 50% aggregate in each Practical (Practical +IA).', 0, 'L', 0, 0, '', '', true, 0, true);			
			
			$subj_y=$pdfBig->GetY();
			/*2nd box start*/
			$pdfBig->SetFont($arialNarrow, '', 2, '', false);
			$pdfBig->SetXY($left_str_x, $subj_y+10);//191	
			$pdfBig->MultiCell(180, 18, '', 'LRTB', 'C', 0, 0);	//Entire Table Border
			$pdfBig->SetXY($left_str_x, $subj_y+19);	
			$pdfBig->MultiCell(180, 1, '', 'T', 'C', 0, 0); //2nd horizontal line
			$pdfBig->SetXY(51.5, $subj_y+10);//191
			$pdfBig->MultiCell(25.5, 18, '', 'LR', 'C', 0, 0);
			$pdfBig->SetXY(77, $subj_y+10);//191
			$pdfBig->MultiCell(39, 18, '', 'LR', 'C', 0, 0); //2 & 3 verticle line
			$pdfBig->SetXY(116, $subj_y+10);//191
			$pdfBig->MultiCell(39.5, 18, '', 'LR', 'C', 0, 0); //4 & 5 verticle line
			
			$pdfBig->SetFont($MyriadProRegular, '', 10, '', false);
			$pdfBig->SetTextColor(0, 0, 0);
			$pdfBig->SetXY(15.5, $subj_y+13);
			$pdfBig->MultiCell(36.5, 18, '% Marks (First Attempt)', 0, 'L', 0, 0);
			$pdfBig->SetXY(55, $subj_y+13);
			$pdfBig->MultiCell(36.5, 18, '75% & More', 0, 'L', 0, 0);
			$pdfBig->SetXY(81, $subj_y+10.5);
			$pdfBig->MultiCell(30, 18, '65% & More but Less than 75%', 0, 'C', 0, 0);
			$pdfBig->SetXY(120, $subj_y+10.5);
			$pdfBig->MultiCell(30, 18, '50% & More but Less than 65%', 0, 'C', 0, 0);
			$pdfBig->SetXY(160, $subj_y+10.5);
			$pdfBig->MultiCell(30, 18, 'Pass in More than one attempt', 0, 'C', 0, 0);
			
			$pdfBig->SetXY($left_str_x, $subj_y+21.5);
			$pdfBig->MultiCell(36.5, 5, 'Class', 0, 'C', 0, 0);
			$pdfBig->MultiCell(25.5, 5, 'Distinction', 0, 'C', 0, 0);
			$pdfBig->MultiCell(39, 5, 'First Class', 0, 'C', 0, 0);
			$pdfBig->MultiCell(39.5, 5, 'Second Class', 0, 'C', 0, 0);
			$pdfBig->MultiCell(39.5, 5, 'Pass only', 0, 'C', 0, 0);
			
			/*3rd box end*/
			$pdfBig->SetXY(144, 270.5);
			$pdfBig->SetFont($arialNarrow, '', 1, '', false);
			$pdfBig->MultiCell(47.5, 1, '', 'T', 'C', 0, 0);
			$pdfBig->SetFont($MyriadProRegular, '', 10, '', false);
			$pdfBig->SetXY(135, 271);
			$pdfBig->MultiCell(0, 0, 'Controller of Examinations', 0, 'C', 0, 0);
			
			$pdfBig->SetXY(12.3, 269.2);
			$pdfBig->SetFont($MyriadProRegular, '', 10, '', false);	
			$pdfBig->MultiCell(0, 0, 'Date: ',0, 'L', 0, 0);
			$pdfBig->SetXY(21, 269.2);
			$pdfBig->SetFont($MyriadProRegular, '', 10, '', false);
			$pdfBig->MultiCell(0, 0, $Date,0, 'L', 0, 0);
			
			$pdfBig->SetFont($MyriadProRegular, '', 7, '', false);
			$pdfBig->SetXY(51.5, 283);
			$pdfBig->MultiCell(92, 0, "SDM College of Physiotherapy, Sattur, Dharwad–580009, Karnataka, India",0, 'C', 0, 0);
			
			/*Verification Data*/
			/*$pdfBig->SetFont($MyriadProItalic, 'I', 12, '', false);
			$pdfBig->SetXY($title1_x+1, 202);
			$pdfBig->MultiCell(125.5, 7, 'Batch Of:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$batch_of_qr.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY($title1_x+1, 208);
			$pdfBig->MultiCell(125.5, 7, 'Name of the Student:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$candidate_name.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY($title1_x+1, 214);
			$pdfBig->MultiCell(125.5, 7, 'Aadhar Number:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$aadhar_number_qr.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY($title1_x+1, 220);
			$pdfBig->MultiCell(125.5, 7, 'Course:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$course.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY($title1_x+1, 226);
			$pdfBig->MultiCell(125.5, 7, 'Year:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$year.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdfBig->SetXY($title1_x+1, 232);
			$pdfBig->MultiCell(125.5, 7, 'Date Of Birth:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$DOB.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			*/

			/******End pdfBig ******/
			
			/******Start pdf ******/
			/*Grand Total*/
			$pdf->SetFont($MyriadProBold, 'B', 10, '', false);
			$pdf->SetXY($left_str_x, $subj_y);	
			$pdf->MultiCell(134, 5, 'GRAND TOTAL:', 'LRB', 'R', 0, 0); 
			$pdf->SetFont($MyriadProRegular, '', 10, '', false);
			$pdf->MultiCell(7.666666666666667, 5, $Grand_Total_Max, 1, 'C', 0, 0); 
			$pdf->MultiCell(7.666666666666667, 5, $Grand_Total_Min, 1, 'C', 0, 0); 
			$pdf->MultiCell(7.666666666666667, 5, $Grand_Total_Sec, 1, 'C', 0, 0); 
			$pdf->MultiCell(23, 5, '', 1, 'C', 0, 0); 
			$pdf->SetFont($MyriadProBold, 'B', 10, '', false);
			$pdf->SetXY($left_str_x, $subj_y+5);	
			$pdf->MultiCell(134, 5, 'GRAND TOTAL IN WORDS:', 'LR', 'R', 0, 0); 
			$pdf->MultiCell(46, 5, $Grand_Total_In_Words, 'R', 'L', 0, 0);
			$pdf->SetXY($left_str_x, $subj_y+10);	
			$pdf->MultiCell(134, 5, 'PERCENTAGE:', 'LR', 'R', 0, 0); 
			$pdf->MultiCell(46, 5, $Percentage, 'R', 'L', 0, 0);
			$pdf->SetXY($left_str_x, $subj_y+15);	
			$pdf->MultiCell(134, 5, 'RESULT:', 'LRB', 'R', 0, 0); 
			$pdf->MultiCell(46, 5, $Result, 'LRB', 'L', 0, 0);
						
			$pdf->SetXY(14, $subj_y+21.5);
			$pdf->SetFont($MyriadProItalic, 'I', 10, '', false);
			$pdf->MultiCell(0, 0, 'Min. Marks for Eligibility to appear for University Examination: 35% in each Theory and Practical Internal Assessment separately.<br>Min. Marks to Pass: 50% aggregate of each theory (Theory + IA + Viva) and 50% aggregate in each Practical (Practical +IA).', 0, 'L', 0, 0, '', '', true, 0, true);			
			
			$subj_y=$pdf->GetY();
			/*2nd box start*/
			$pdf->SetFont($arialNarrow, '', 2, '', false);
			$pdf->SetXY($left_str_x, $subj_y+10);//191	
			$pdf->MultiCell(180, 18, '', 'LRTB', 'C', 0, 0);	//Entire Table Border
			$pdf->SetXY($left_str_x, $subj_y+19);	
			$pdf->MultiCell(180, 1, '', 'T', 'C', 0, 0); //2nd horizontal line
			$pdf->SetXY(51.5, $subj_y+10);//191
			$pdf->MultiCell(25.5, 18, '', 'LR', 'C', 0, 0);
			$pdf->SetXY(77, $subj_y+10);//191
			$pdf->MultiCell(39, 18, '', 'LR', 'C', 0, 0); //2 & 3 verticle line
			$pdf->SetXY(116, $subj_y+10);//191
			$pdf->MultiCell(39.5, 18, '', 'LR', 'C', 0, 0); //4 & 5 verticle line
			
			$pdf->SetFont($MyriadProRegular, '', 10, '', false);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY(15.5, $subj_y+13);
			$pdf->MultiCell(36.5, 18, '% Marks (First Attempt)', 0, 'L', 0, 0);
			$pdf->SetXY(55, $subj_y+13);
			$pdf->MultiCell(36.5, 18, '75% & More', 0, 'L', 0, 0);
			$pdf->SetXY(81, $subj_y+10.5);
			$pdf->MultiCell(30, 18, '65% & More but Less than 75%', 0, 'C', 0, 0);
			$pdf->SetXY(120, $subj_y+10.5);
			$pdf->MultiCell(30, 18, '50% & More but Less than 65%', 0, 'C', 0, 0);
			$pdf->SetXY(160, $subj_y+10.5);
			$pdf->MultiCell(30, 18, 'Pass in More than one attempt', 0, 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y+21.5);
			$pdf->MultiCell(36.5, 5, 'Class', 0, 'C', 0, 0);
			$pdf->MultiCell(25.5, 5, 'Distinction', 0, 'C', 0, 0);
			$pdf->MultiCell(39, 5, 'First Class', 0, 'C', 0, 0);
			$pdf->MultiCell(39.5, 5, 'Second Class', 0, 'C', 0, 0);
			$pdf->MultiCell(39.5, 5, 'Pass only', 0, 'C', 0, 0);
			
			/*3rd box end*/
			$pdf->SetXY(144, 270.5);
			$pdf->SetFont($arialNarrow, '', 1, '', false);
			$pdf->MultiCell(47.5, 1, '', 'T', 'C', 0, 0);
			$pdf->SetFont($MyriadProRegular, '', 10, '', false);
			$pdf->SetXY(135, 271);
			$pdf->MultiCell(0, 0, 'Controller of Examinations', 0, 'C', 0, 0);
			
			$pdf->SetXY(12.3, 269.2);
			$pdf->SetFont($MyriadProRegular, '', 10, '', false);	
			$pdf->MultiCell(0, 0, 'Date: ',0, 'L', 0, 0);
			$pdf->SetXY(21, 269.2);
			$pdf->SetFont($MyriadProRegular, '', 10, '', false);
			$pdf->MultiCell(0, 0, $Date,0, 'L', 0, 0);
			
			$pdf->SetFont($MyriadProRegular, '', 7, '', false);
			$pdf->SetXY(51.5, 283);
			$pdf->MultiCell(92, 0, "SDM College of Physiotherapy, Sattur, Dharwad–580009, Karnataka, India",0, 'C', 0, 0);
			
			/*Verification Data*/
			$pdf->SetFont($MyriadProItalic, 'I', 12, '', false);
			$pdf->SetXY($title1_x+1, 202);
			$pdf->MultiCell(125.5, 7, 'Batch Of:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$batch_of_qr.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->SetXY($title1_x+1, 208);
			$pdf->MultiCell(125.5, 7, 'Name of the Student:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$candidate_name.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->SetXY($title1_x+1, 214);
			$pdf->MultiCell(125.5, 7, 'Aadhar Number:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$aadhar_number_qr.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->SetXY($title1_x+1, 220);
			$pdf->MultiCell(125.5, 7, 'Course:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$course.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->SetXY($title1_x+1, 226);
			$pdf->MultiCell(125.5, 7, 'Year:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$year.'</span>', 0, "L", 0, 0, '', '', true, 0, true);
			$pdf->SetXY($title1_x+1, 232);
			$pdf->MultiCell(125.5, 7, 'Date Of Birth:&nbsp;&nbsp;<span style="font-family:'.$MyriadProBoldItalic.';">'.$DOB.'</span>', 0, "L", 0, 0, '', '', true, 0, true);			
			/******End pdf ******/
			
			// Ghost image
			$nameOrg=$candidate_name;
			/*$ghost_font_size = '13';
			$ghostImagex = 140;
			$ghostImagey = 276;
			$ghostImageWidth = 55;//68
			$ghostImageHeight = 9.8;
			$name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
			$tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
			$w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
			//$pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);			
			$pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);*/
			$ghost_font_size = '12';
            $ghostImagex = 144;
            $ghostImagey = 276.3;
            $ghostImageWidth = 39.405983333;
            $ghostImageHeight = 8;
            $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
            $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');			
            $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
			$pdfBig->setPageMark();
			$pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
			$pdf->setPageMark();
			
			$serial_no=$GUID=$studentData[0];
			//qr code    
			$dt = date("_ymdHis");
			$str=$GUID.$dt;
			$codeContents = $QR_Output."\n\n".strtoupper(md5($str));
			$encryptedString = strtoupper(md5($str));
			$qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
			$qrCodex = 176; //
			$qrCodey = 52; //
			$qrCodeWidth =21;
			$qrCodeHeight = 21;
			$ecc = 'L';
			$pixel_Size = 1;
			$frame_Size = 1;  
			\PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
			$pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
			$pdfBig->setPageMark();			
			$pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);   
			$pdf->setPageMark(); 						
			
            $COE = public_path().'\\'.$subdomain[0].'\backend\canvas\images\sdm_coe.png';
            $COE_x = 150;
            $COE_y = 243;
            $COE_Width = 39.6875;
            $COE_Height = 28.045833333;
            $pdfBig->image($COE,$COE_x,$COE_y,$COE_Width,$COE_Height,"",'','L',true,3600);
            $pdfBig->setPageMark();
			$pdf->image($COE,$COE_x,$COE_y,$COE_Width,$COE_Height,"",'','L',true,3600);
            $pdf->setPageMark();
			
			//1D Barcode
			$style1Da = array(
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
			$barcodex = 145;
			$barcodey = 65;
			$barcodeWidth = 52;
			$barodeHeight = 13;
			//$pdf->SetAlpha(1);
			//$pdf->write1DBarcode(trim($print_serial_no), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1Da, 'N');
			//$pdfBig->SetAlpha(1);
			//$pdfBig->write1DBarcode(trim($print_serial_no), 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1Da, 'N');
			
			
			$str = $nameOrg;
			$str = strtoupper(preg_replace('/\s+/', '', $str)); 			
			$microlinestr=$str;
			$pdf->SetFont($timesb, '', 1.3, '', false);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY(176.5, 73);        
			$pdf->Cell(20, 0, $microlinestr, 0, false, 'C');    
			
			$pdfBig->SetFont($timesb, '', 1.3, '', false);
			$pdfBig->SetTextColor(0, 0, 0);
			$pdfBig->SetXY(176.5, 73);        
			$pdfBig->Cell(20, 0, $microlinestr, 0, false, 'C'); 
			
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

				$this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'sdmGC',$admin_id,$card_serial_no);
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
			$this->updateCardNo('sdmGC',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
        }
        $msg = '';
        
        $file_name =  str_replace("/", "_",'sdmGC'.date("Ymdhms")).'.pdf';        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;        
        $pdfBig->output($filename,'F');
        if($previewPdf!=1){
            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="sdmGC";
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
