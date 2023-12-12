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
class PdfGenerateMonadCertificate
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
        $photo_col=18;
		//print_r($studentDataOrg); exit();
        $first_sheet=$pdf_data['studentDataOrg']; // get first worksheet rows
        $total_unique_records=count($first_sheet);
        $last_row=$total_unique_records+1;
        
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
        $pdfBig = new \Mpdf\Mpdf(['orientation' => 'P', 'mode' => 'utf-8', 'format' => [250, 350], 'tempDir'=>storage_path('tempdir')]);
        $pdfBig->autoScriptToLang = true;
		$pdfBig->autoLangToFont = true;
		$pdfBig->SetCreator('seqr');
        $pdfBig->SetAuthor('MPDF');
        $pdfBig->SetTitle('Certificate');
        $pdfBig->SetSubject('');

        // remove default header/footer
        $pdfBig->setHeader(false);
        $pdfBig->setFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);

        // add spot colors
        $pdfBig->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        $pdfBig->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo
        $pdfBig->AddSpotColor('Spot Light Yellow', 0, 0, 100, 0);
        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ArialB.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrow = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arialn.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $times = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.ttf', 'TrueTypeUnicode', '', 96);
        $timesb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.ttf', 'TrueTypeUnicode', '', 96);
        $timesbi = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold-Italic.ttf', 'TrueTypeUnicode', '', 96);
        $timesi = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times New Roman Italic.ttf', 'TrueTypeUnicode', '', 96);
        $calibri = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Calibri.ttf', 'TrueTypeUnicode', '', 96);
        $calibrib = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Calibri Bold.ttf', 'TrueTypeUnicode', '', 96);
		$K011 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\KRDEV011.ttf', 'TrueTypeUnicode', '', 96);
		$K026 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Kruti Dev 026.ttf', 'TrueTypeUnicode', '', 96);
		$kokila = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\kokila_0.ttf', 'TrueTypeUnicode', '', 96);
		$kokilab = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\kokilab_0.ttf', 'TrueTypeUnicode', '', 96);
		$kokilabi = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\kokilabi.ttf', 'TrueTypeUnicode', '', 96);
		$kokilai = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\kokilai_0.ttf', 'TrueTypeUnicode', '', 96);
		$Mangal = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Akshar_Unicode.ttf', 'TrueTypeUnicode', '', 96);

        $preview_serial_no=1;
		$card_serial_no="";
        $log_serial_no = 1;
        //$cardDetails=$this->getNextCardNo('monadCertificate');
        //$card_serial_no=$cardDetails->next_serial_no;
        $generated_documents=0;  //for custom loader
		//$cell_border = array('B' => array('width' => 0.5, 'dash' => '2,2,2,2', 'phase' => 0, 'color' => array(0, 0, 0)));
		$cell_border = 1;
        foreach ($studentDataOrg as $studentData) {
			/*if($card_serial_no>999999&&$previewPdf!=1){
				echo "<h5>Your card series ended...!</h5>";
				exit;
			}*/
			//For Custom Loader
            $startTimeLoader =  date('Y-m-d H:i:s');    
			$high_res_bg="MonadCertificate_blank.jpg"; // MonadCertificate_blank.jpg, MonadCertificate_data.jpg
			$low_res_bg="MonadCertificate_blank.jpg";
			$pdfBig->AddPage();
			$pdfBig->SetFont($arialNarrowB, '', 8);
			//set background image
			$template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$high_res_bg;   

			if($previewPdf==1){
				if($previewWithoutBg!=1){
					$pdfBig->Image($template_img_generate, 0, 0, '250', '350', "JPG", '', true, false);
				}
				$date_font_size = '11';
				$date_nox = 13;
				$date_noy = 20;
				$date_nostr = ''; //'DRAFT '.date('d-m-Y H:i:s');
				$pdfBig->SetFont($arialb, '', $date_font_size);
				$pdfBig->SetTextColor(192,192,192);
				$pdfBig->SetXY($date_nox, $date_noy);
				$pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
				$pdfBig->SetTextColor(0,0,0,100,false,'');
				$pdfBig->SetFont($arialNarrowB, '', 9);
			}
			//$pdfBig->setPageMark();
			
			$pdf = new \Mpdf\Mpdf(['orientation' => 'P', 'mode' => 'utf-8', 'format' => [250, 350], 'tempDir'=>storage_path('tempdir')]); /* Verification PDF */
			$pdf->autoScriptToLang = true;
			$pdf->autoLangToFont = true;
			$pdf->SetCreator('seqr');
			$pdf->SetAuthor('MPDF');
			$pdf->SetTitle('Certificate');
			$pdf->SetSubject('');

			// remove default header/footer
			$pdf->setHeader(false);
			$pdf->setFooter(false);
			$pdf->SetAutoPageBreak(false, 0);	
			$pdf->AddPage();
			if($previewPdf!=1){
				$pdf->Image($template_img_generate, 0, 0, '250', '350', "JPG", '', true, false);
			}
			
			
			$Serial_No = trim($studentData[0]);
			$Roll_No = trim($studentData[1]);
			$Enrollment_No = trim($studentData[2]);
			$candidate_name = trim($studentData[3]);
			$hindi_candidate_name = trim($studentData[4]);
			$Father_Name = trim($studentData[5]);
			$Hindi_Father_Name = trim($studentData[6]);
			$Mother_Name = trim($studentData[7]);
			$Hindi_Mother_Name = trim($studentData[8]);
			$Exam_Year = trim($studentData[9]);
			$Hindi_Exam_Year = trim($studentData[10]);
			$Degree = trim($studentData[11]);
			$Hindi_Degree = trim($studentData[12]);
			$Degree_In = trim($studentData[13]);
			$Hindi_Degree_In = trim($studentData[14]);
			$Remark = trim($studentData[15]);
			$Hindi_Remark = trim($studentData[16]);
			$Date = trim($studentData[17]);
			$Photo = trim($studentData[18]);			
			
			$x= 300;
			$y = 20;
			$font_size=12;
			if($previewPdf!=1){
				$str = str_pad($card_serial_no, 8, '0', STR_PAD_LEFT);
			}else{
				$str = str_pad($preview_serial_no, 8, '0', STR_PAD_LEFT);	
			}		
			
			$pdfBig->SetFont('calibri', '', 12);
			$pdfBig->SetXY(16, 20);
			$pdfBig->MultiCell(0, 0, 'Roll No. '.$Roll_No, 0, 'L');	
			
			$pdfBig->SetFont('calibri',0, 12);
			$pdfBig->SetXY(191, 20);
			$pdfBig->MultiCell(0, 0, 'Serial No. '.$Serial_No, 0, 'L');
			
			$pdf->SetFont('calibri', '', 12);
			$pdf->SetXY(16, 20);
			$pdf->MultiCell(0, 0, 'Roll No. '.$Roll_No, 0, 'L');	
			
			$pdf->SetFont('calibri',0, 12);
			$pdf->SetXY(191, 20);
			$pdf->MultiCell(0, 0, 'Serial No. '.$Serial_No, 0, 'L');
			
			$ghostImgArr = array();
			$print_serial_no = $this->nextPrintSerial();
			//set background image
			$template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;
			
			if($Photo!=''){
				//path of photos
				$profile_path_org = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$Photo;
				//set profile image   
				$profilex = 95;
				$profiley = 113;
				$profileWidth = 26;
				$profileHeight = 30;
				$pdfBig->Image($profile_path_org, $profilex,$profiley,$profileWidth,$profileHeight, 'jpg', '', true, false);
				$pdf->Image($profile_path_org, $profilex,$profiley,$profileWidth,$profileHeight, 'jpg', '', true, false);
			}
			
			$uv_logo = public_path()."/".$subdomain[0]."/backend/canvas/bg_images/MonadUVLogo.png";
			$logox = 50;
			$logoy = 113;
			$logoWidth = 35;
			$logoHeight = 28;
			$pdfBig->image($uv_logo,$logox,$logoy,$logoWidth,$logoHeight, 'png', '', true, false); 
			//$pdf->image($uv_logo,$logox,$logoy,$logoWidth,$logoHeight, 'png', '', true, false); 
			
			$uv_name = public_path()."/".$subdomain[0]."/backend/canvas/bg_images/MonadUVName.png";
			$namex = 160;
			$namey = 118;
			$nameWidth = 32;
			$nameHeight = 20;
			$pdfBig->image($uv_name,$namex,$namey,$nameWidth,$nameHeight, 'png', '', true, false); 
			//$pdf->image($uv_name,$namex,$namey,$nameWidth,$nameHeight, 'png', '', true, false); 
			
				
            //Start pdfBig			
            //Static Title        
            // start invisible data
            //$pdfBig->SetFont($timesb, '', 11); 
            //$pdfBig->SetTextColor(255, 255, 0);
            //$pdfBig->SetXY(18, 130);
			//$pdfBig->MultiCell(261, 0, $candidate_name, 0, 'L', 0, 0);
            // end invisible data 
			
			$pdfBig->SetFont('calibri', '', 14);
			$pdfBig->SetXY(20.5, 100);
			$pdfBig->MultiCell(210, 0, 'Enrollment No. '.$Enrollment_No, 0, 'C', 0, 0);		
			
			/*******Hindi*******/
			$pdfBig->SetFont('eczar', '', 12);
            $pdfBig->SetTextColor(0, 0, 0);
			$pdfBig->SetXY(20, 163);
			$pdfBig->MultiCell(42, 0, 'प्रमाणित किया जाता है कि', 0, 'L'); 
			$pdfBig->SetFont('aparajita', '', 24);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetDash(1,1);
			$pdfBig->Line(61,165,230.5,165);
			$pdfBig->SetXY(60, 153);
			$pdfBig->MultiCell(169, 10, $hindi_candidate_name, 0, 'C');
			$pdfBig->SetFont('eczar', '', 12);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(20, 174);
			$pdfBig->MultiCell(42, 0, 'आत्मज / आत्मजा श्री.', 0, 'L', 0, 0);
			$pdfBig->SetFont('aparajita', '', 18);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetDash(1,1);
			$pdfBig->SetXY(57, 166); 
			$pdfBig->MultiCell(80, 10, $Hindi_Father_Name, 'B', 'C');
			$pdfBig->SetFont('eczar', '', 12);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(137, 174);
			$pdfBig->MultiCell(20, 0, 'एवं श्रीमती.', 0, 'L', 0, 0);
			$pdfBig->SetFont('aparajita', '', 18);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetXY(156, 166);
			$pdfBig->MultiCell(74, 10, $Hindi_Mother_Name, 'B', 'C');
			$pdfBig->SetFont('eczar', '', 12);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(20, 184);
			$pdfBig->MultiCell(46, 0, 'को इस विश्‍वविद्यालय से सन्', 0, 'L', 0, 0);
			$pdfBig->SetFont('aparajita', '', 18);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetXY(66, 176);
			$pdfBig->MultiCell(42, 10, $Exam_Year, 'B', 'C');
			$pdfBig->SetFont('eczar', '', 12);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(107, 184);
			$pdfBig->MultiCell(10, 0, ' की ', 0, 'L', 0, 0);
			$pdfBig->SetFont('aparajita', '', 18);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetXY(115, 176);
			$pdfBig->MultiCell(115, 10, $Hindi_Degree_In, 'B', 'C');
			$pdfBig->SetFont('eczar', '', 12);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(20, 194);
			$pdfBig->MultiCell(30, 0, 'पाठ्यक्रम परीक्षा में', 0, 'L', 0, 0);
			$pdfBig->SetFont('aparajita', '', 18);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetXY(50, 186);
			$pdfBig->MultiCell(154, 10, $Hindi_Degree, 'B', 'C'); 
			$pdfBig->SetFont('eczar', '', 12);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(204, 194);
			$pdfBig->MultiCell(27, 0, 'की सनद/उपाधि', 0, 'L', 0, 0);
			$pdfBig->SetFont('aparajita', '', 18);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetXY(21, 196);
			$pdfBig->MultiCell(169, 10, $Hindi_Remark, 'B', 'C');
			$pdfBig->SetFont('eczar', '', 12);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(191, 204);
			$pdfBig->MultiCell(40, 0, 'श्रेणी में प्रदत्त की गयी है।', 0, 'L', 0, 0);
			
			
			
			/*******English*******/
			$pdfBig->SetFont('timesi', '', 15);
            $pdfBig->SetTextColor(0, 0, 0);
			$pdfBig->SetXY(20.5, 227);
			$pdfBig->MultiCell(0, 0, 'This is to certify that', 0, 'L', 0, 0);			
			$pdfBig->SetFont('timesb', '', 20);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetDash(1,1);
			$pdfBig->SetXY(66, 219);
			$pdfBig->MultiCell(132, 10, $candidate_name, 'B', 'C');			
			$pdfBig->SetFont('timesi', '', 15);
			$pdfBig->SetTextColor(0, 0, 0);
			$pdfBig->SetXY(198, 227);
			$pdfBig->MultiCell(33, 0, 'Son/Daughter', 0, 'L', 0, 0);
			$pdfBig->SetFont('timesi', '', 15);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(20.5, 237);
			$pdfBig->MultiCell(20, 0, 'of Shri.', 0, 'L', 0, 0);
			$pdfBig->SetFont('timesb', '', 16);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetDash(1,1);
			$pdfBig->SetXY(38, 229);
			$pdfBig->MultiCell(103, 10, $Father_Name, 'B', 'C');
			$pdfBig->SetFont('timesi', '', 15);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(142, 237);
			$pdfBig->MultiCell(22, 0, 'and Smt.', 0, 'L', 0, 0);
			$pdfBig->SetFont('timesb', '', 16);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetXY(162, 229);
			$pdfBig->MultiCell(67, 10, $Mother_Name, 'B', 'C');
			$pdfBig->SetFont('timesi', '', 15);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(20.5, 248);
			$pdfBig->MultiCell(93, 0, 'has been conferred the Diploma/Degree of', 0, 'L', 0, 0);
			$pdfBig->SetFont('timesb', '', 16);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetXY(113, 240);
			$pdfBig->MultiCell(115, 10, $Degree, 'B', 'C');
			$pdfBig->SetFont('timesi', '', 15);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(20.5, 259);
			$pdfBig->MultiCell(14, 0, 'in the', 0, 'L', 0, 0);
			$pdfBig->SetFont('timesb', '', 16);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->SetDash(1,1);
			$pdfBig->SetXY(34, 251);
			$pdfBig->MultiCell(92, 10, $Degree_In, 'B', 'C');
			$pdfBig->SetFont('timesi', '', 15);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(126, 259);
			$pdfBig->MultiCell(50, 0, 'course examination of', 0, 'L', 0, 0);			
			$pdfBig->SetFont('timesb', '', 16);
			$pdfBig->SetTextColor(52, 57, 138);				
			$pdfBig->SetXY(175, 251);			
			$pdfBig->MultiCell(38, 10, $Exam_Year, 'B', 'C');
			$pdfBig->SetFont('timesi', '', 15);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(214, 259);
			$pdfBig->MultiCell(16, 0, 'of this', 0, 'L', 0, 0);
			$pdfBig->SetFont('timesi', '', 15);
            $pdfBig->SetXY(20.5, 269);
			$pdfBig->MultiCell(30, 0, 'University in', 0, 'L', 0, 0);
			$pdfBig->SetDash(1,1);
			//$pdfBig->Line(50,268,210,268);
			$pdfBig->SetFont('timesb', '', 16);
			$pdfBig->SetXY(50, 261);
			$pdfBig->SetTextColor(52, 57, 138);
			$pdfBig->MultiCell(158.5, 10, $Remark, 'B', 'C');
			$pdfBig->SetFont('timesi', '', 15);
			$pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(209, 269);
			$pdfBig->MultiCell(28, 0, 'Division.', 0, 'L');
			
			$pdfBig->SetFont('times', '', 17.5);	
			$pdfBig->SetXY(15, 312);
			$pdfBig->MultiCell(73.33333333333333, 10, 'Registrar',0, 'C');
			$pdfBig->SetXY(88, 312);
			$pdfBig->MultiCell(73.33333333333333, 10, 'Date: '.$Date,0, 'C');
			$pdfBig->SetXY(163, 312);
			$pdfBig->MultiCell(73.33333333333333, 10, 'Vice-Chancellor',0, 'C');
			
			//$pdf
			$pdf->SetFont('calibri', '', 14);
			$pdf->SetXY(20.5, 100);
			$pdf->MultiCell(210, 0, 'Enrollment No. '.$Enrollment_No, 0, 'C', 0, 0);		
			
			/*******Hindi*******/
			$pdf->SetFont('eczar', '', 12);
            $pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY(20, 163);
			$pdf->MultiCell(42, 0, 'प्रमाणित किया जाता है कि', 0, 'L'); 
			$pdf->SetFont('aparajita', '', 24);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetDash(1,1);
			$pdf->Line(61,165,230.5,165);
			$pdf->SetXY(60, 153);
			$pdf->MultiCell(169, 10, $hindi_candidate_name, 0, 'C');
			$pdf->SetFont('eczar', '', 12);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(20, 174);
			$pdf->MultiCell(42, 0, 'आत्मज / आत्मजा श्री.', 0, 'L', 0, 0);
			$pdf->SetFont('aparajita', '', 18);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetDash(1,1);
			$pdf->SetXY(57, 166); 
			$pdf->MultiCell(80, 10, $Hindi_Father_Name, 'B', 'C');
			$pdf->SetFont('eczar', '', 12);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(137, 174);
			$pdf->MultiCell(20, 0, 'एवं श्रीमती.', 0, 'L', 0, 0);
			$pdf->SetFont('aparajita', '', 18);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetXY(156, 166);
			$pdf->MultiCell(74, 10, $Hindi_Mother_Name, 'B', 'C');
			$pdf->SetFont('eczar', '', 12);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(20, 184);
			$pdf->MultiCell(46, 0, 'को इस विश्‍वविद्यालय से सन्', 0, 'L', 0, 0);
			$pdf->SetFont('aparajita', '', 18);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetXY(66, 176);
			$pdf->MultiCell(42, 10, $Exam_Year, 'B', 'C');
			$pdf->SetFont('eczar', '', 12);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(107, 184);
			$pdf->MultiCell(10, 0, ' की ', 0, 'L', 0, 0);
			$pdf->SetFont('aparajita', '', 18);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetXY(115, 176);
			$pdf->MultiCell(115, 10, $Hindi_Degree_In, 'B', 'C');
			$pdf->SetFont('eczar', '', 12);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(20, 194);
			$pdf->MultiCell(30, 0, 'पाठ्यक्रम परीक्षा में', 0, 'L', 0, 0);
			$pdf->SetFont('aparajita', '', 18);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetXY(50, 186);
			$pdf->MultiCell(154, 10, $Hindi_Degree, 'B', 'C'); 
			$pdf->SetFont('eczar', '', 12);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(204, 194);
			$pdf->MultiCell(27, 0, 'की सनद/उपाधि', 0, 'L', 0, 0);
			$pdf->SetFont('aparajita', '', 18);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetXY(21, 196);
			$pdf->MultiCell(169, 10, $Hindi_Remark, 'B', 'C');
			$pdf->SetFont('eczar', '', 12);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(191, 204);
			$pdf->MultiCell(40, 0, 'श्रेणी में प्रदत्त की गयी है।', 0, 'L', 0, 0);
			
			
			
			/*******English*******/
			$pdf->SetFont('timesi', '', 15);
            $pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY(20.5, 227);
			$pdf->MultiCell(0, 0, 'This is to certify that', 0, 'L', 0, 0);			
			$pdf->SetFont('timesb', '', 20);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetDash(1,1);
			$pdf->SetXY(66, 219);
			$pdf->MultiCell(132, 10, $candidate_name, 'B', 'C');			
			$pdf->SetFont('timesi', '', 15);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY(198, 227);
			$pdf->MultiCell(33, 0, 'Son/Daughter', 0, 'L', 0, 0);
			$pdf->SetFont('timesi', '', 15);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(20.5, 237);
			$pdf->MultiCell(20, 0, 'of Shri.', 0, 'L', 0, 0);
			$pdf->SetFont('timesb', '', 16);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetDash(1,1);
			$pdf->SetXY(38, 229);
			$pdf->MultiCell(103, 10, $Father_Name, 'B', 'C');
			$pdf->SetFont('timesi', '', 15);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(142, 237);
			$pdf->MultiCell(22, 0, 'and Smt.', 0, 'L', 0, 0);
			$pdf->SetFont('timesb', '', 16);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetXY(162, 229);
			$pdf->MultiCell(67, 10, $Mother_Name, 'B', 'C');
			$pdf->SetFont('timesi', '', 15);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(20.5, 248);
			$pdf->MultiCell(93, 0, 'has been conferred the Diploma/Degree of', 0, 'L', 0, 0);
			$pdf->SetFont('timesb', '', 16);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetXY(113, 240);
			$pdf->MultiCell(115, 10, $Degree, 'B', 'C');
			$pdf->SetFont('timesi', '', 15);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(20.5, 259);
			$pdf->MultiCell(14, 0, 'in the', 0, 'L', 0, 0);
			$pdf->SetFont('timesb', '', 16);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->SetDash(1,1);
			$pdf->SetXY(34, 251);
			$pdf->MultiCell(92, 10, $Degree_In, 'B', 'C');
			$pdf->SetFont('timesi', '', 15);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(126, 259);
			$pdf->MultiCell(50, 0, 'course examination of', 0, 'L', 0, 0);			
			$pdf->SetFont('timesb', '', 16);
			$pdf->SetTextColor(52, 57, 138);				
			$pdf->SetXY(175, 251);			
			$pdf->MultiCell(38, 10, $Exam_Year, 'B', 'C');
			$pdf->SetFont('timesi', '', 15);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(214, 259);
			$pdf->MultiCell(16, 0, 'of this', 0, 'L', 0, 0);
			$pdf->SetFont('timesi', '', 15);
            $pdf->SetXY(20.5, 269);
			$pdf->MultiCell(30, 0, 'University in', 0, 'L', 0, 0);
			$pdf->SetDash(1,1);
			//$pdf->Line(50,268,210,268);
			$pdf->SetFont('timesb', '', 16);
			$pdf->SetXY(50, 261);
			$pdf->SetTextColor(52, 57, 138);
			$pdf->MultiCell(158.5, 10, $Remark, 'B', 'C');
			$pdf->SetFont('timesi', '', 15);
			$pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(209, 269);
			$pdf->MultiCell(28, 0, 'Division.', 0, 'L');
			
			$pdf->SetFont('times', '', 17.5);	
			$pdf->SetXY(15, 312);
			$pdf->MultiCell(73.33333333333333, 10, 'Registrar',0, 'C');
			$pdf->SetXY(88, 312);
			$pdf->MultiCell(73.33333333333333, 10, 'Date: '.$Date,0, 'C');
			$pdf->SetXY(163, 312);
			$pdf->MultiCell(73.33333333333333, 10, 'Vice-Chancellor',0, 'C');			
			
			// Ghost image
			$nameOrg=$candidate_name;
			$ghost_font_size = '13';
			$ghostImagex = 24;
			$ghostImagey = 323;
			$ghostImageWidth = 55;
			$ghostImageHeight = 9.8;
			$name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
			$tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
			$w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
			$pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, 'png', '', true, false);
			$pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, 'png', '', true, false);
						
			$serial_no=$GUID=$studentData[0];
			//QR code    
            $dt = date("_ymdHis");
            $str=$GUID.$dt;
            $codeContents =$encryptedString = strtoupper(md5($str));
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 133;
            $qrCodey = 118;
            $qrCodeWidth =22;
            $qrCodeHeight = 22;
            $ecc = 'L';
            $pixel_Size = 1;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
            $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false); 
			
            $str = $nameOrg;
            $str = strtoupper(preg_replace('/\s+/', '', $str));             
            $microlinestr=$str;
            $pdfBig->SetFont('arialb', '', 2);
            $pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->StartTransform();
            $pdfBig->SetXY(133.8, 140.5);        
            $pdfBig->MultiCell(21, 0, $microlinestr, 0, 'C'); 

			$pdf->SetFont('arialb', '', 2);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->StartTransform();
            $pdf->SetXY(133.8, 140.5);        
            $pdf->MultiCell(21, 0, $microlinestr, 0, 'C'); 
			
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
			$barcodex = 92.6;
			$barcodey = 323;
			$barcodeWidth = 0.8;
			$barodeHeight = 0.9;
			$pdfBig->SetAlpha(1);
			$pdfBig->writeBarcode2(trim($print_serial_no), $barcodex, $barcodey, $barcodeWidth, $barodeHeight, false, false, 'C39');
			$pdfBig->SetFont('arial', '', 11);
            $pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->StartTransform();
            $pdfBig->SetXY(92.6, 332.5);        
            $pdfBig->MultiCell(74, 0, trim($print_serial_no), 0, 'C');
			
			$pdf->SetAlpha(1);
			$pdf->writeBarcode2(trim($print_serial_no), $barcodex, $barcodey, $barcodeWidth, $barodeHeight, false, false, 'C39');
			
			if($previewPdf!=1){

				$certName = str_replace("/", "_", $GUID) .".pdf";				
				$myPath = public_path().'/backend/temp_pdf_file';
				$fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;
				
				$pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F'); //Save $pdf

				$this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id);

				$username = $admin_id['username'];
				date_default_timezone_set('Asia/Kolkata');

				$content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
				$date = date('Y-m-d H:i:s').PHP_EOL;
				$print_datetime = date("Y-m-d H:i:s");				

				$print_count = $this->getPrintCount($serial_no);
				$printer_name = /*'HP 1020';*/$printer_name;

				$this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'Certificate',$admin_id,$card_serial_no="");
				//$card_serial_no=$card_serial_no+1;
			}

            $generated_documents++;

            if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
                $updated=date('Y-m-d H:i:s');
                ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['generated_documents'=>$generated_documents,"updated_at"=>$updated]);
            }else{
              //For Custom loader calculation
                //echo $generated_documents;
              /*$endTimeLoader = date('Y-m-d H:i:s');
              $time1 = new \DateTime($startTimeLoader);
              $time2 = new \DateTime($endTimeLoader);
              $interval = $time1->diff($time2);
              $interval = $interval->format('%s');

              $jsonArr=array();
              $jsonArr['token'] = $pdf_data['loader_token'];
              $jsonArr['generatedCertificates'] =$generated_documents;
              $jsonArr['timePerCertificate'] =$interval;
             
              $loaderData=CoreHelper::createLoaderJson($jsonArr,0); */
            }
            //delete temp dir 26-04-2022
        	CoreHelper::rrmdir($tmpDir);
        } 
        
        if($previewPdf!=1){
			//$this->updateCardNo('monad_certificate',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
        }
        $msg = '';
        
        $file_name =  str_replace("/", "_",'monad_certificate'.date("Ymdhms")).'.pdf';        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
		$filename = public_path().'/backend/tcpdf/examples/'.$file_name;        
        $pdfBig->output($filename,'F'); //Save pdfBig
        if($previewPdf!=1){
            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="monad_certificate";
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                // with sandbox                
                $result = SbExceUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            }else{
                // without sandbox
                $result = ExcelUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            }
            //file transfer to monad server
            $this->testUpload('printable_pdf/'.$file_name, public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name); 
			if($subdomain[0]=="monad" || $subdomain[0]=="test"){
				$monad_pdf_url = \Config::get('constant.monad_base_url').'printable_pdf/'.$file_name; 
				$msg = "<b>Click <a href='".$monad_pdf_url."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
			}else{
				$protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
				$path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
				$pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name;
				$msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf download' target='_blank'>Here</a> to download file<b>";
			}
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
