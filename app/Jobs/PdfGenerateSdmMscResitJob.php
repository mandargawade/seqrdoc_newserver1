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
class PdfGenerateSdmMscResitJob{
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
		$T_CREDITS_CR = $pdf_data['T_CREDITS_CR'];
        $T_CREDITS_POINT = $pdf_data['T_CREDITS_POINT'];
        $SEMESTER_CR = $pdf_data['SEMESTER_CR'];
        $CUMULATIVE_CR = $pdf_data['CUMULATIVE_CR'];
        $SGPA = $pdf_data['SGPA'];
        $CGPA = $pdf_data['CGPA'];
        $PERCENTAGE = $pdf_data['PERCENTAGE'];
        $CGPA_GRADE = $pdf_data['CGPA_GRADE'];
        $PERCENTAGE_GRADE = $pdf_data['PERCENTAGE_GRADE'];
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
        $cardDetails=$this->getNextCardNo('sdmMSC');
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
            
            // Start of PDF
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
            $pdf->AddSpotColor('Spot Light Yellow', 0, 0, 100, 0);

            $pdf->AddPage();
            $pdf->SetFont($arialNarrowB, '', 8, '', false);

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

                $pdf->SetFont($arialb, '', $date_font_size, '', false);
				$pdf->SetTextColor(192,192,192);
				$pdf->SetXY($date_nox, $date_noy);
				//$pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
				$pdfBig->SetTextColor(0,0,0,100,false,'');
				$pdfBig->SetFont($arialNarrowB, '', 9, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
				$pdf->SetFont($arialNarrowB, '', 9, '', false);
			}
			$pdfBig->setPageMark();
            $pdf->setPageMark();

			$ghostImgArr = array();
			$print_serial_no = $this->nextPrintSerial();
			//set background image
			$template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;
			if($previewPdf!=1){
			    $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
			}
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
            // start invisible data
                // $pdfBig->SetFont($MyriadProBold, '', 12, '', false); 
                // $pdfBig->SetTextColor(255, 255, 0);        
                // $pdfBig->SetXY($title1_x, 78);
                // $pdfBig->Cell(0, 0, $candidate_name, 0, false, 'L');
                // $pdfBig->SetXY(157, 162.5);	
                // $pdfBig->MultiCell(50, 6, $Percentage, 0, 'L', 0, 0);
            // end invisible data 
            $pdfBig->SetTextColor(0, 0, 0); 
            $pdf->SetTextColor(0, 0, 0);   
			/******Start pdfBig ******/ 
			$pdfBig->SetFont($arialNarrow, '', 2, '', false);			
			$pdfBig->SetXY($left_str_x, 97);	
			$pdfBig->MultiCell(180.5, 13, '', 'LRTB', 'C', 0, 0);	//Entire Table
			$pdfBig->SetXY(62.5, 106);	
			$pdfBig->MultiCell(80, 0, '', 'T', 'L', 0, 0);	//UIT HR Line
			$pdfBig->SetXY($left_str_x, 110);	
			$pdfBig->MultiCell(180, 0, '', 'T', 'C', 0, 0);	//SSUITR HR Line

			$pdfBig->SetXY(24.5, 97);	
            $pdfBig->MultiCell(13, 13, '', 'LR', 'C', 0, 0);  //Subject Code
            $pdfBig->MultiCell(25, 13, '', 'LR', 'C', 0, 0);  //Subject           
			$pdfBig->MultiCell(26.5, 13, '', 'LR', 'C', 0, 0); //UE
			$pdfBig->MultiCell(27, 13, '', 'R', 'C', 0, 0); //IA
			$pdfBig->MultiCell(26.9, 13, '', 'R', 'C', 0, 0); //Total
            $pdfBig->MultiCell(10, 13, '', 'R', 'C', 0, 0); //GRADE  GR
			$pdfBig->MultiCell(10, 13, '', 'R', 'C', 0, 0); //GRADE  POINT  GP
            $pdfBig->MultiCell(11, 13, '', 'R', 'C', 0, 0); //CREDITS  CR
			$pdfBig->MultiCell(11, 13, '', 'R', 'C', 0, 0); //CREDIT  POINT 
			$pdfBig->MultiCell(18, 9, '', 'L', 'C', 0, 0); //Remark
			
			$pdfBig->SetFont($MyriadProRegular, '', 7.5, '', false);
			$pdfBig->SetXY(14.5, 102);
			$pdfBig->MultiCell(12, 5, 'SL. NO.', 0, 'L', 0, 0);
            $pdfBig->SetXY(23.8, 100);
            $pdfBig->MultiCell(13, 5, 'SUBJECT CODE', 0, 'C', 0, 0);
			$pdfBig->SetXY(35.8, 102);
			$pdfBig->MultiCell(25, 5, 'SUBJECT', 0, 'C', 0, 0);
			$pdfBig->SetXY(62, 98);
			$pdfBig->MultiCell(25, 5, 'UNIVERSITY EXAMINATION', 0, 'C', 0, 0);
			$pdfBig->SetXY(90, 98);
			$pdfBig->MultiCell(25, 5, 'INTERNAL ASSESSMENT', 0, 'C', 0, 0);
            $pdfBig->SetXY(115, 100);
			$pdfBig->MultiCell(25, 5, 'TOTAL', 0, 'C', 0, 0);
            $pdfBig->SetXY(142.8, 98);
			$pdfBig->MultiCell(10, 5, 'GRADE  GR', 0, 'C', 0, 0);
            $pdfBig->SetXY(152.8, 98);
			$pdfBig->MultiCell(10, 5, 'GRADE  POINT  GP', 0, 'C', 0, 0);
            $pdfBig->SetXY(162.8, 98);
			$pdfBig->MultiCell(12, 5, 'CREDITS  CR', 0, 'C', 0, 0);
            $pdfBig->SetXY(174, 98);
			$pdfBig->MultiCell(10, 5, 'CREDIT  POINT', 0, 'C', 0, 0);
			$pdfBig->SetXY(181, 100);
			$pdfBig->MultiCell(18, 5, 'REMARK', 0, 'C', 0, 0);

			$pdfBig->SetXY(62, 106);
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 0, 'C', 0, 0);	
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 0, 'C', 0, 0);	
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 0, 'C', 0, 0);

            $pdf->SetFont($arialNarrow, '', 2, '', false);			
			$pdf->SetXY($left_str_x, 97);	
			$pdf->MultiCell(180.5, 13, '', 'LRTB', 'C', 0, 0);	//Entire Table
			$pdf->SetXY(62.5, 106);	
			$pdf->MultiCell(80, 0, '', 'T', 'C', 0, 0);	//UIT HR Line
			$pdf->SetXY($left_str_x, 110);	
			$pdf->MultiCell(180, 0, '', 'T', 'C', 0, 0);	//SSUITR HR Line
			$pdf->SetXY(24.5, 97);	
            $pdf->MultiCell(13, 13, '', 'LR', 'C', 0, 0);  //Subject Code
            $pdf->MultiCell(25, 13, '', 'LR', 'C', 0, 0);  //Subject           
			$pdf->MultiCell(26.5, 13, '', 'LR', 'C', 0, 0); //UE
			$pdf->MultiCell(27, 13, '', 'R', 'C', 0, 0); //IA
			$pdf->MultiCell(26.9, 13, '', 'R', 'C', 0, 0); //Total
            $pdf->MultiCell(10, 13, '', 'R', 'C', 0, 0); //GRADE  GR
			$pdf->MultiCell(10, 13, '', 'R', 'C', 0, 0); //GRADE  POINT  GP
            $pdf->MultiCell(11, 13, '', 'R', 'C', 0, 0); //CREDITS  CR
			$pdf->MultiCell(11, 13, '', 'R', 'C', 0, 0); //CREDIT  POINT 
			$pdf->MultiCell(18, 9, '', 'L', 'C', 0, 0); //Remark
			$pdf->SetFont($MyriadProRegular, '', 7.5, '', false);
			$pdf->SetXY(14.5, 102);
			$pdf->MultiCell(12, 5, 'SL. NO.', 0, 'L', 0, 0);
            $pdf->SetXY(23.8, 102);
            $pdf->MultiCell(13, 5, 'SUBJECT CODE', 0, 'C', 0, 0);
			$pdf->SetXY(35.8, 102);
			$pdf->MultiCell(25, 5, 'SUBJECT', 0, 'C', 0, 0);
			$pdf->SetXY(62, 98);
			$pdf->MultiCell(25, 5, 'UNIVERSITY EXAMINATION', 0, 'C', 0, 0);
			$pdf->SetXY(90, 98);
			$pdf->MultiCell(25, 5, 'INTERNAL ASSESSMENT', 0, 'C', 0, 0);
            $pdf->SetXY(115, 100);
			$pdf->MultiCell(25, 5, 'TOTAL', 0, 'C', 0, 0);
            $pdf->SetXY(142.8, 98);
			$pdf->MultiCell(10, 5, 'GRADE  GR', 0, 'C', 0, 0);
            $pdf->SetXY(152.8, 98);
			$pdf->MultiCell(10, 5, 'GRADE  POINT  GP', 0, 'C', 0, 0);
            $pdf->SetXY(162.8, 98);
			$pdf->MultiCell(12, 5, 'CREDITS  CR', 0, 'C', 0, 0);
            $pdf->SetXY(174, 98);
			$pdf->MultiCell(10, 5, 'CREDIT  POINT', 0, 'C', 0, 0);
			$pdf->SetXY(181, 100);
			$pdf->MultiCell(18, 5, 'REMARK', 0, 'C', 0, 0);
			$pdf->SetXY(62, 106);
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 0, 'C', 0, 0);	
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 0, 'C', 0, 0);	
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 0, 'C', 0, 0);
			
			$unique_id = trim($studentData[0]);
			$mc_no = trim($studentData[0]);
			$year = trim($studentData[3]);
			$year_examination = strtoupper(trim($studentData[5]));
			$candidate_name = strtoupper(trim($studentData[1]));
			$ureg_no = strtoupper(trim($studentData[2]));
			$specilisation = trim($studentData[4]);

            $t_credits = trim($studentData[$T_CREDITS_CR]);
            $t_credits_point = trim($studentData[$T_CREDITS_POINT]);
            $sem_cr = trim($studentData[$SEMESTER_CR]);
            $cum_cr = trim($studentData[$CUMULATIVE_CR]);
            $sgpa = trim($studentData[$SGPA]); 
            $cgpa = trim($studentData[$CGPA]); 
            $cgpa_grade = trim($studentData[$CGPA_GRADE]); 
            $Percentage_grade = trim($studentData[$PERCENTAGE_GRADE]);
			$Percentage = trim($studentData[$PERCENTAGE]);
			$Result = strtoupper(trim($studentData[$Result_key]));
			$Date = trim($studentData[$Date_key]);
			$batch_of_qr = trim($studentData[$Batch_of_key]);
			$aadhar_number_qr = trim($studentData[$Aadhar_No_key]);
			$DOB = trim($studentData[$DOB_key]);			
			$course = strtoupper(trim($studentData[$course_key]));
            $subjectData = array_slice($studentData, $subj_start, $subj_end);			
            $subjectsArr=array_chunk($subjectData, $subj_col);  
            $pdfBig->SetFont($arial, '', 10, '', false); 
			$QR_Output="Batch Of: $batch_of_qr\nName of the Student: $candidate_name\nAadhar Number: $aadhar_number_qr\nCourse: $course\nYear: $year\nDate Of Birth: $DOB";            
			
			$pdfBig->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdfBig->SetXY(62, 106.5);
			$pdfBig->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0); //UA
			$pdfBig->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0); //IN
			$pdfBig->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);  //Total
			$pdfBig->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);	

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
			$pdfBig->Cell(188, 0, "GRADE CARD", 0, false, 'C');            
			$pdfBig->SetFont($MyriadProRegular, '', 15, '', false);
			$pdfBig->SetXY($title1_x, 52.5);
			$pdfBig->Cell(188, 0, $course, 0, false, 'C');

            $pdf->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdf->SetXY(62, 106.5);
			$pdf->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0); //UA
			$pdf->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0); //IN
			$pdf->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);  //Total
			$pdf->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'SEC', 0, 'C', 0, 0);	
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
			$pdf->Cell(188, 0, "GRADE CARD", 0, false, 'C');            
			$pdf->SetFont($MyriadProRegular, '', 15, '', false);
			$pdf->SetXY($title1_x, 52.5);
			$pdf->Cell(188, 0, $course, 0, false, 'C');


            // $pdf->SetXY($title1_x, 52.5);
            // $pdf->Cell(188, 0, $specilisation, 0, false, 'C');
            // $pdf->Cell(188, 0, $course, 0, false, 'C');

			// if($specilisation==""){
			// 	$pdfBig->SetFont($MyriadProRegular, '', 12, '', false);
			// 	$pdfBig->SetXY($title1_x, 59.5);
			// 	$pdfBig->Cell(188, 0, $year, 0, false, 'C');
			// 	$pdfBig->SetFont($MinionProBold, '', 12, '', false);			
			// 	$pdfBig->SetXY($title1_x, 65.8);
			// 	$pdfBig->Cell(188, 0, $year_examination, 0, false, 'C');
            //     $pdf->SetFont($MyriadProRegular, '', 12, '', false);
            //     $pdf->SetXY($title1_x, 59.5);
            //     $pdf->Cell(188, 0, $year, 0, false, 'C');
            //     $pdf->SetFont($MinionProBold, '', 12, '', false);			
            //     $pdf->SetXY($title1_x, 65.8);
            //     $pdf->Cell(188, 0, $year_examination, 0, false, 'C');
			// }else{				
				$pdfBig->SetFont($MinionProBold, '', 12, '', false);			
				$pdfBig->SetXY($title1_x, 52.5);
				$pdfBig->Cell(188, 0, $specilisation, 0, false, 'C');				
				$pdfBig->SetFont($MyriadProRegular, '', 12, '', false);
				$pdfBig->SetXY($title1_x, 58.8);
                $pdfBig->Cell(188, 0,"(CBCS SCHEME)", 0, false, 'C');				
                $pdfBig->SetXY($title1_x, 64.8);
				$pdfBig->Cell(188, 0, $year, 0, false, 'C');
				$pdfBig->SetFont($MinionProBold, '', 12, '', false);			
				$pdfBig->SetXY($title1_x, 71.8);
				$pdfBig->Cell(188, 0, $year_examination, 0, false, 'C');

                $pdf->SetFont($MinionProBold, '', 12, '', false);			
				$pdf->SetXY($title1_x, 52.5);
				$pdf->Cell(188, 0, $specilisation, 0, false, 'C');				
				$pdf->SetFont($MyriadProRegular, '', 12, '', false);
				$pdf->SetXY($title1_x, 58.8);
                $pdf->Cell(188, 0,"(CBCS SCHEME)", 0, false, 'C');				
                $pdf->SetXY($title1_x, 64.8);
				$pdf->Cell(188, 0, $year, 0, false, 'C');
				$pdf->SetFont($MinionProBold, '', 12, '', false);			
				$pdf->SetXY($title1_x, 71.8);
				$pdf->Cell(188, 0, $year_examination, 0, false, 'C');
			// }
						
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
				$Sr_no = trim($subjectDatas[0]);
                $Subject_Code = strtoupper(trim($subjectDatas[1]));		
				$Subject_Name = strtoupper(trim($subjectDatas[2]));
                $UE_MAX = trim($subjectDatas[3]);	
                $UE_MIN = trim($subjectDatas[4]);	
                $UE_SEC = trim($subjectDatas[5]);	
                $IA_MAX = trim($subjectDatas[6]);	
                $IA_MIN = trim($subjectDatas[7]);	
                $IA_SEC = trim($subjectDatas[8]);	
                $T_MAX = trim($subjectDatas[9]);	
                $T_MIN = trim($subjectDatas[10]);	
                $T_SEC = trim($subjectDatas[11]);	
                $GRADE = trim($subjectDatas[12]);	
                $GRADE_POINT = trim($subjectDatas[13]);	
                $CREDIT = trim($subjectDatas[14]);	
                $CREDIT_POINT = trim($subjectDatas[15]);	
				$Remark = strtoupper(trim($subjectDatas[16]));
              
				/*start pdfbig line*/
				// store current object
				$pdfBig->startTransaction();
				$pdfBig->SetFont($MyriadProRegular, '', 7.5, '', false);
                $pdf->startTransaction();
                $pdf->SetFont($MyriadProRegular, '', 7.5, '', false);	 				
				// get the number of lines
				$lines = $pdfBig->MultiCell(25, 0, $Subject_Name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0);
				$lines = $pdf->MultiCell(25, 0, $Subject_Name, 0, 'L', 0, 0, '', '', true, 0, false,true, 0);
                // restore previous object
				$pdfBig=$pdfBig->rollbackTransaction();	
                $pdf=$pdf->rollbackTransaction();		
				
				if($lines == 2){
					$th_height = 10;
					$th_height_half = 10;
				} else if($lines == 3){
					$th_height = 11;
					$th_height_half = 11;
				}else if($lines == 4){
					$th_height = 15;
					$th_height_half = 15;
                }else if($lines == 5){
					$th_height = 20;
					$th_height_half = 20;
                }else if($lines == 6){
					$th_height = 25;
					$th_height_half = 25;
                }else if($lines == 7){
					$th_height = 25;
					$th_height_half = 25;
                }
                else{
					$th_height = 8;
					$th_height_half = 8;
				}							
				$pdfBig->SetFont($MyriadProRegular, '', 7.5, '', false);	
                $pdfBig->SetXY($left_str_x, $subj_y);
                
                $pdfBig->MultiCell(9.5, $th_height, $Sr_no, 1, 'C', 0, 0);
			    $pdfBig->MultiCell(13, $th_height, $Subject_Code, 1, 'C', 0, 0);
                $pdfBig->MultiCell(25, $th_height, $Subject_Name, 1, 'L', 0, 0);					
                $pdfBig->SetXY(62.5, $subj_y);					
                $pdfBig->MultiCell(8.5, $th_height_half, $UE_MAX, 1, 'C', 0, 0);
                $pdfBig->MultiCell(9, $th_height_half, $UE_MIN, 1, 'C', 0, 0);
                $pdfBig->MultiCell(9, $th_height_half, $UE_SEC, 1, 'C', 0, 0);
                $pdfBig->MultiCell(9, $th_height_half, $IA_MAX, 1, 'C', 0, 0);
                $pdfBig->MultiCell(9, $th_height_half, $IA_MIN, 1, 'C', 0, 0);
                $pdfBig->MultiCell(9, $th_height_half, $IA_SEC, 1, 'C', 0, 0);
                $pdfBig->MultiCell(9, $th_height_half, $T_MAX, 1, 'C', 0, 0);
                $pdfBig->MultiCell(9, $th_height_half, $T_MIN, 1, 'C', 0, 0);
                $pdfBig->MultiCell(9, $th_height_half, $T_SEC, 1, 'C', 0, 0);
                $pdfBig->MultiCell(10, $th_height_half, $GRADE, 1, 'C', 0, 0);
                $pdfBig->MultiCell(10, $th_height_half, $GRADE_POINT, 1, 'C', 0, 0);
                $pdfBig->MultiCell(11, $th_height_half, $CREDIT, 1, 'C', 0, 0);
                $pdfBig->MultiCell(11, $th_height_half, $CREDIT_POINT, 1, 'C', 0, 0);	
                $pdfBig->MultiCell(10.5, $th_height_half, $Remark, 1, 'C', 0, 0);

                $pdf->SetFont($MyriadProRegular, '', 7.5, '', false);	
                $pdf->SetXY($left_str_x, $subj_y);
                $pdf->MultiCell(9.5, $th_height, $Sr_no, 1, 'C', 0, 0);
			    $pdf->MultiCell(13, $th_height, $Subject_Code, 1, 'C', 0, 0);
                $pdf->MultiCell(25, $th_height, $Subject_Name, 1, 'L', 0, 0);					
                $pdf->SetXY(62.5, $subj_y);					
                $pdf->MultiCell(8.5, $th_height_half, $UE_MAX, 1, 'C', 0, 0);
                $pdf->MultiCell(9, $th_height_half, $UE_MIN, 1, 'C', 0, 0);
                $pdf->MultiCell(9, $th_height_half, $UE_SEC, 1, 'C', 0, 0);
                $pdf->MultiCell(9, $th_height_half, $IA_MAX, 1, 'C', 0, 0);
                $pdf->MultiCell(9, $th_height_half, $IA_MIN, 1, 'C', 0, 0);
                $pdf->MultiCell(9, $th_height_half, $IA_SEC, 1, 'C', 0, 0);
                $pdf->MultiCell(9, $th_height_half, $T_MAX, 1, 'C', 0, 0);
                $pdf->MultiCell(9, $th_height_half, $T_MIN, 1, 'C', 0, 0);
                $pdf->MultiCell(9, $th_height_half, $T_SEC, 1, 'C', 0, 0);
                $pdf->MultiCell(10, $th_height_half, $GRADE, 1, 'C', 0, 0);
                $pdf->MultiCell(10, $th_height_half, $GRADE_POINT, 1, 'C', 0, 0);
                $pdf->MultiCell(11, $th_height_half, $CREDIT, 1, 'C', 0, 0);
                $pdf->MultiCell(11, $th_height_half, $CREDIT_POINT, 1, 'C', 0, 0);	
                $pdf->MultiCell(10.5, $th_height_half, $Remark, 1, 'C', 0, 0);
                $subj_y=$subj_y+$th_height;
            }

			/*Grand Total*/
			$pdfBig->SetFont($MyriadProBold, 'B', 10, '', false);
			$pdfBig->SetXY($left_str_x, $subj_y);	
			$pdfBig->MultiCell(148, 5, 'TOTAL', 'LRB', 'R', 0, 0); 
			$pdfBig->SetFont($MyriadProRegular, 'B', 10, '', false);
			$pdfBig->MultiCell(11, 5, $t_credits, 1, 'C', 0, 0); 
			$pdfBig->MultiCell(11, 5, $t_credits_point, 1, 'C', 0, 0); 
			$pdfBig->MultiCell(10.5, 5, $Result, 1, 'C', 0, 0);

            $pdf->SetFont($MyriadProBold, 'B', 10, '', false);
			$pdf->SetXY($left_str_x, $subj_y);	
			$pdf->MultiCell(148, 5, 'TOTAL', 'LRB', 'R', 0, 0); 
			$pdf->SetFont($MyriadProRegular, 'B', 10, '', false);
			$pdf->MultiCell(11, 5, $t_credits, 1, 'C', 0, 0); 
			$pdf->MultiCell(11, 5, $t_credits_point, 1, 'C', 0, 0); 
			$pdf->MultiCell(10.5, 5, $Result, 1, 'C', 0, 0);		
            
            $notes="<br><br><br>CGPA = PERCENTAGE <br>The value or the denominator for the 10 Point Scale System<br>to calculate the equivalent %= (CGPA X 100)/10";	
            $notes1="<table border='1' style='border:1px solid black;'>
                        <tr><td>SEMESTER CR</td><td></td><td>$sem_cr</td><td></td></tr>
                        <tr><td>CUMULATIVE CR</td><td></td><td>$cum_cr</td><td></td></tr>
                        <tr><td><b>SGPA</b></td><td></td><td><b>$sgpa</b></td><td></td></tr>
                        <tr><td><b>CGPA</b></td><td></td><td><b>$cgpa</b></td><td><b>$cgpa_grade</b></td></tr>                        
                    </table>";
                    // <tr><td><b>PERCENTAGE</b></td><td></td><td><b>$Percentage</b></td><td><b>$Percentage_grade</b></td></tr>
			$subj_y=$pdfBig->GetY()+9.5;
			$pdfBig->SetFont($MyriadProRegular, '', 9, '', false);
			$pdfBig->SetXY($left_str_x, $subj_y);			
			$pdfBig->MultiCell(90, 30, $notes, 0, 'L', 0, 0, '', '', true, 0, true);
            $pdfBig->SetXY(105, $subj_y);
            $pdfBig->MultiCell(90, 30, $notes1, 0, 'L', 0, 0, '', '', true, 0, true);

            $pdfBig->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
            $pdfBig->SetFont($MyriadProRegular, '', 8, '', false);    
            $pdfBig->SetXY($left_str_x, $subj_y+30);
			$pdfBig->MultiCell(20, 5, 'MARKS RANGE', 1, 'L', 0, 0);
			$pdfBig->MultiCell(20, 5, '> 90 -100', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, '> 80-90', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, '> 70-80', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, '> 60-70', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, '> 50-60', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, '50', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, '< 50', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, '-', 1, 'C', 0, 0);

            $pdfBig->SetXY($left_str_x, $subj_y+35.1);	
			$pdfBig->MultiCell(20, 5, 'LETTER GRADE', 1, 'L', 0, 0);
			$pdfBig->MultiCell(20, 5, 'O', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, 'A+', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, 'A', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, 'B+', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, 'B', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, 'P', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, 'F', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, 'AB', 1, 'C', 0, 0);

            
			$pdfBig->SetXY($left_str_x, $subj_y+40.1);
			$pdfBig->MultiCell(20, 5, 'GRADE POINT', 1, 'L', 0, 0);
			$pdfBig->MultiCell(20, 5, '10', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, '09', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, '08', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, '07', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, '06', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, '05', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, '0', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, '0', 1, 'C', 0, 0);

			$pdfBig->SetXY($left_str_x, $subj_y+45.1);
			$pdfBig->MultiCell(20, 5, 'REMARKS', 1, 'L', 0, 0);
			$pdfBig->MultiCell(20, 5, 'OUTSTANDING', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, 'EXCELLENT', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, 'EXCEPTIONAL', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20, 5, 'VERY GOOD', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, 'GOOD', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, 'PASS', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, 'FAIL', 1, 'C', 0, 0);
            $pdfBig->MultiCell(20, 5, 'ABSENT', 1, 'C', 0, 0);

            $subj_y=$pdf->GetY()+9.5;
			$pdf->SetFont($MyriadProRegular, '', 9, '', false);
			$pdf->SetXY($left_str_x, $subj_y);			
			$pdf->MultiCell(90, 30, $notes, 0, 'L', 0, 0, '', '', true, 0, true);
            $pdf->SetXY(105, $subj_y);
            $pdf->MultiCell(90, 30, $notes1, 0, 'L', 0, 0, '', '', true, 0, true);
            $pdf->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
            $pdf->SetFont($MyriadProRegular, '', 8, '', false);    
            $pdf->SetXY($left_str_x, $subj_y+30);
			$pdf->MultiCell(20, 5, 'MARKS RANGE', 1, 'L', 0, 0);
			$pdf->MultiCell(20, 5, '> 90 -100', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, '> 80-90', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, '> 70-80', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, '> 60-70', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, '> 50-60', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, '50', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, '< 50', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, '-', 1, 'C', 0, 0);
            $pdf->SetXY($left_str_x, $subj_y+35.1);	
			$pdf->MultiCell(20, 5, 'LETTER GRADE', 1, 'L', 0, 0);
			$pdf->MultiCell(20, 5, 'O', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, 'A+', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, 'A', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, 'B+', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, 'B', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, 'P', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, 'F', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, 'AB', 1, 'C', 0, 0);
			$pdf->SetXY($left_str_x, $subj_y+40.1);
			$pdf->MultiCell(20, 5, 'GRADE POINT', 1, 'L', 0, 0);
			$pdf->MultiCell(20, 5, '10', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, '09', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, '08', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, '07', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, '06', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, '05', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, '0', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, '0', 1, 'C', 0, 0);
			$pdf->SetXY($left_str_x, $subj_y+45.1);
			$pdf->MultiCell(20, 5, 'REMARKS', 1, 'L', 0, 0);
			$pdf->MultiCell(20, 5, 'OUTSTANDING', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, 'EXCELLENT', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, 'EXCEPTIONAL', 1, 'C', 0, 0);
			$pdf->MultiCell(20, 5, 'VERY GOOD', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, 'GOOD', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, 'PASS', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, 'FAIL', 1, 'C', 0, 0);
            $pdf->MultiCell(20, 5, 'ABSENT', 1, 'C', 0, 0);
            $pdfBig->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
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
			
			$pdfBig->SetFont($MyriadProRegular, '', 6.5, '', false);
			$pdfBig->SetXY(51.5, 283);
			$pdfBig->MultiCell(92, 0, "SDM Research Institute for Biomedical Sciences, Sattur, Dharwad – 580009, Karnataka, India",0, 'C', 0, 0);

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

            $pdf->SetFont($MyriadProRegular, '', 6.5, '', false);
            $pdf->SetXY(51.5, 283);
            $pdf->MultiCell(92, 0, "SDM Research Institute for Biomedical Sciences, Sattur, Dharwad – 580009, Karnataka, India",0, 'C', 0, 0);
			/******End pdfBig ******/
            
			
			// Ghost image
			$nameOrg=$candidate_name;
			// $ghost_font_size = '13';
			// $ghostImagex = 140;
			// $ghostImagey = 276;
			// $ghostImageWidth = 55;//68
			// $ghostImageHeight = 9.8;
			// $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
			// $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
			// $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
			// $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);			
			// $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
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
            // $COE = public_path().'\minosha\backend\canvas\bg_images\sdm_coe.png';
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
			// $pdf->SetFont($timesb, '', 1.3, '', false);
			// $pdf->SetTextColor(0, 0, 0);
			// $pdf->SetXY(176.5, 73);        
			// $pdf->Cell(20, 0, $microlinestr, 0, false, 'C');    
			
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
