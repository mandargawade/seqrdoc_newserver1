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
class PdfGenerateSdmMbbsIIIResitJob
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
        $photo_col=132;
        
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
			$pdfBig->setCellPaddings( $left = '', $top = '', $right = '', $bottom = '');
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
			$pdf->setCellPaddings( $left = '', $top = '', $right = '', $bottom = '');				
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
			$pdfBig->SetFont($MyriadProRegular, '', 9, '', false);		
			
			$pdfBig->SetXY($left_str_x, 92);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');	
			$pdfBig->MultiCell(180, 5, 'UNIVERSITY EXAMINATION (UE)', 'LRTB', 'C', 0, 0);
			
			$pdfBig->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');
			$pdfBig->SetFont($arialNarrow, '', 2, '', false);
			$pdfBig->SetXY($left_str_x, 97);	
			$pdfBig->MultiCell(180, 13, '', 'LRTB', 'C', 0, 0);	//Entire Table
			$pdfBig->SetXY(69, 106);	
			$pdfBig->MultiCell(111, 0, '', 'T', 'C', 0, 0);	//UIT HR Line
			$pdfBig->SetXY($left_str_x, 110);	
			$pdfBig->MultiCell(180, 0, '', 'T', 'C', 0, 0);	//SSUITR HR Line
			
			$pdfBig->SetXY(24.5, 97);	
			$pdfBig->MultiCell(44.5, 13, '', 'LR', 'C', 0, 0);  //Subject
			$pdfBig->MultiCell(27, 13, '', 'LR', 'C', 0, 0); //Theory
			$pdfBig->MultiCell(27, 13, '', 'LR', 'C', 0, 0); //UE
			$pdfBig->MultiCell(27, 13, '', 'R', 'C', 0, 0); //IA
			$pdfBig->MultiCell(30, 13, '', 'R', 'C', 0, 0); //Total
			$pdfBig->MultiCell(15, 9, '', 'L', 'C', 0, 0); //Remark
			
			$pdfBig->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdfBig->SetXY(14.5, 102);
			$pdfBig->MultiCell(12.5, 5, 'SL. NO.', 0, 'L', 0, 0);
			$pdfBig->SetXY(25, 102);
			$pdfBig->MultiCell(44.5, 5, 'SUBJECT', 0, 'C', 0, 0);
			$pdfBig->SetXY(69, 100);
			$pdfBig->MultiCell(27, 5, 'THEORY', 0, 'C', 0, 0);
			$pdfBig->SetXY(96, 98);
			$pdfBig->MultiCell(27, 5, 'THEORY AGGREGATE', 0, 'C', 0, 0);
			$pdfBig->SetXY(123, 100);
			$pdfBig->MultiCell(27, 5, 'PRACTICAL + VIVA', 0, 'C', 0, 0);
			$pdfBig->SetXY(150, 100);
			$pdfBig->MultiCell(30, 5, 'TOTAL', 0, 'C', 0, 0);
			$pdfBig->SetXY(180, 102);
			$pdfBig->MultiCell(15, 5, 'REMARK', 0, 'C', 0, 0);
			
			$pdfBig->SetXY(69, 106);
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 0, 'C', 0, 0);	
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 0, 'C', 0, 0);	
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9.5, 4, '', 0, 'C', 0, 0);	
			$pdfBig->MultiCell(9.5, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(10, 4, '', 'R', 'C', 0, 0);
			$pdfBig->MultiCell(9, 4, '', 0, 'C', 0, 0);		
						
			
			/******End pdfBig ******/ 
			
			/******Start pdf ******/ 
			
			$pdf->SetFont($MyriadProRegular, '', 9, '', false);		
			
			$pdf->SetXY($left_str_x, 92);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');	
			$pdf->MultiCell(180, 5, 'UNIVERSITY EXAMINATION (UE)', 'LRTB', 'C', 0, 0);
			
			$pdf->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');
			$pdf->SetFont($arialNarrow, '', 2, '', false);
			$pdf->SetXY($left_str_x, 97);	
			$pdf->MultiCell(180, 13, '', 'LRTB', 'C', 0, 0);	//Entire Table
			$pdf->SetXY(69, 106);	
			$pdf->MultiCell(126, 0, '', 'T', 'C', 0, 0);	//UIT HR Line
			$pdf->SetXY($left_str_x, 110);	
			$pdf->MultiCell(180, 0, '', 'T', 'C', 0, 0);	//SSUITR HR Line
			
			$pdf->SetXY(24.5, 97);	
			$pdf->MultiCell(44.5, 13, '', 'LR', 'C', 0, 0);  //Subject
			$pdf->MultiCell(27, 13, '', 'LR', 'C', 0, 0); //Theory
			$pdf->MultiCell(27, 13, '', 'LR', 'C', 0, 0); //UE
			$pdf->MultiCell(27, 13, '', 'R', 'C', 0, 0); //IA
			$pdf->MultiCell(30, 13, '', 'R', 'C', 0, 0); //Total
			$pdf->MultiCell(15, 9, '', 'L', 'C', 0, 0); //Remark
			
			$pdf->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdf->SetXY(14.5, 102);
			$pdf->MultiCell(12.5, 5, 'SL. NO.', 0, 'L', 0, 0);
			$pdf->SetXY(25, 102);
			$pdf->MultiCell(44.5, 5, 'SUBJECT', 0, 'C', 0, 0);
			$pdf->SetXY(69, 100);
			$pdf->MultiCell(27, 5, 'THEORY', 0, 'C', 0, 0);
			$pdf->SetXY(96, 98);
			$pdf->MultiCell(27, 5, 'THEORY AGGREGATE', 0, 'C', 0, 0);
			$pdf->SetXY(123, 100);
			$pdf->MultiCell(27, 5, 'PRACTICAL + VIVA', 0, 'C', 0, 0);
			$pdf->SetXY(150, 100);
			$pdf->MultiCell(30, 5, 'TOTAL', 0, 'C', 0, 0);
			$pdf->SetXY(180, 102);
			$pdf->MultiCell(15, 5, 'REMARK', 0, 'C', 0, 0);
			
			$pdf->SetXY(69, 106);
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 0, 'C', 0, 0);	
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 0, 'C', 0, 0);	
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9.5, 4, '', 0, 'C', 0, 0);	
			$pdf->MultiCell(9.5, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(10, 4, '', 'R', 'C', 0, 0);
			$pdf->MultiCell(9, 4, '', 0, 'C', 0, 0);
			/******End pdf ******/ 
			
			$unique_id = trim($studentData[0]);
			$mc_no = trim($studentData[0]);
			$candidate_name = strtoupper(trim($studentData[1]));
			$ureg_no = strtoupper(trim($studentData[2]));			
			$year = trim($studentData[3]);
			$specilisation = strtoupper(trim($studentData[4]));
			$year_examination = strtoupper(trim($studentData[5]));
			
			$Sr_no1 = trim($studentData[6]);			
			$Subject1 = trim($studentData[7]);
			$T_Max1 = trim($studentData[8]);
			$T_Min1 = trim($studentData[9]);
			$T_OBT1 = trim($studentData[10]);			
			$TA_Max1 = trim($studentData[11]);
			$TA_Min1 = trim($studentData[12]);
			$TA_OBT1 = trim($studentData[13]);
			$PV_MAX1 = trim($studentData[14]);
			$PV_MIN1 = trim($studentData[15]);
			$PV_OBT1 = trim($studentData[16]);
			$TOTAL_MAX1 = trim($studentData[17]);
			$TOTAL_MIN1 = trim($studentData[18]);
			$TOTAL_OBT1 = trim($studentData[19]);
			$REMARK1 = trim($studentData[20]);
			
			$Sr_no2 = trim($studentData[21]);
			$Subject2 = trim($studentData[22]);
			$T_Max2 = trim($studentData[23]);
			$T_Min2 = trim($studentData[24]);
			$T_OBT2 = trim($studentData[25]);
			$TA_Max2 = trim($studentData[26]);
			$TA_Min2 = trim($studentData[27]);
			$TA_OBT2 = trim($studentData[28]);
			$PV_MAX2 = trim($studentData[29]);
			$PV_MIN2 = trim($studentData[30]);
			$PV_OBT2 = trim($studentData[31]);
			$TOTAL_MAX2 = trim($studentData[32]);
			$TOTAL_MIN2 = trim($studentData[33]);
			$TOTAL_OBT2 = trim($studentData[34]);
			$REMARK2 = trim($studentData[35]);
			
			$Sr_no3 = trim($studentData[36]);			
			$Subject3 = trim($studentData[37]);
			$T_Max3 = trim($studentData[38]);
			$T_Min3 = trim($studentData[39]);
			$T_OBT3 = trim($studentData[40]);			
			$TA_Max3 = trim($studentData[41]);
			$TA_Min3 = trim($studentData[42]);
			$TA_OBT3 = trim($studentData[43]);
			$PV_MAX3 = trim($studentData[44]);
			$PV_MIN3 = trim($studentData[45]);
			$PV_OBT3 = trim($studentData[46]);
			$TOTAL_MAX3 = trim($studentData[47]);
			$TOTAL_MIN3 = trim($studentData[48]);
			$TOTAL_OBT3 = trim($studentData[49]);
			$REMARK3 = trim($studentData[50]);
			
			$Sr_no4 = trim($studentData[51]);
			$Subject4 = trim($studentData[52]);
			$T_Max4 = trim($studentData[53]);
			$T_Min4 = trim($studentData[54]);
			$T_OBT4 = trim($studentData[55]);
			$Sr_no5 = trim($studentData[56]);			
			$Subject5 = trim($studentData[57]);
			$T_Max5 = trim($studentData[58]);
			$T_Min5 = trim($studentData[59]);
			$T_OBT5 = trim($studentData[60]);
			$TA_Max5 = trim($studentData[61]);
			$TA_Min5 = trim($studentData[62]);
			$TA_OBT5 = trim($studentData[63]);
			$PV_MAX5 = trim($studentData[64]);
			$PV_MIN5 = trim($studentData[65]);
			$PV_OBT5 = trim($studentData[66]);
			$TOTAL_MAX5 = trim($studentData[67]);
			$TOTAL_MIN5 = trim($studentData[68]);
			$TOTAL_OBT5 = trim($studentData[69]);
			$REMARK5 = trim($studentData[70]);
			
			$Total_UE_MAX = trim($studentData[71]);
			$Total_UE_MIN = trim($studentData[72]);
			$Total_UE_OBT = trim($studentData[73]);
			$Total_IA_MAX = trim($studentData[74]);
			$Total_IA_MIN = trim($studentData[75]);
			$Total_IA_OBT = trim($studentData[76]);			
			$Total_UEIA_MAX = trim($studentData[77]);
			$Total_UEIA_MIN = trim($studentData[78]);
			$Total_UEIA_OBT = trim($studentData[79]);
			$Grand_Total_In_Words = trim($studentData[80]);
			$Percentage = trim($studentData[81]);
			$Result = strtoupper(trim($studentData[82]));
			
			//INTERNAL ASSESSMENT(IA)
			$IA_SL_no1 = strtoupper(trim($studentData[83]));
			$IA_Subject_no1 = strtoupper(trim($studentData[84]));
			$IA_T_Max1 = strtoupper(trim($studentData[85]));
			$IA_T_Min1 = strtoupper(trim($studentData[86]));
			$IA_T_OBT1 = strtoupper(trim($studentData[87]));
			$IA_P_Max1 = strtoupper(trim($studentData[88]));
			$IA_P_Min1 = strtoupper(trim($studentData[89]));
			$IA_P_OBT1 = strtoupper(trim($studentData[90]));
			$IA_TO_MAX1 = strtoupper(trim($studentData[91]));
			$IA_TO_MIN1 = strtoupper(trim($studentData[92]));
			$IA_TO_OBT1 = strtoupper(trim($studentData[93]));
			
			$IA_SL_no2 = strtoupper(trim($studentData[94]));
			$IA_Subject_no2 = strtoupper(trim($studentData[95]));
			$IA_T_Max2 = strtoupper(trim($studentData[96]));
			$IA_T_Min2 = strtoupper(trim($studentData[97]));
			$IA_T_OBT2 = strtoupper(trim($studentData[98]));
			$IA_P_Max2 = strtoupper(trim($studentData[99]));
			$IA_P_Min2 = strtoupper(trim($studentData[100]));
			$IA_P_OBT2 = strtoupper(trim($studentData[101]));
			$IA_TO_MAX2 = strtoupper(trim($studentData[102]));
			$IA_TO_MIN2 = strtoupper(trim($studentData[103]));
			$IA_TO_OBT2 = strtoupper(trim($studentData[104]));
			
			$IA_SL_no3 = strtoupper(trim($studentData[105]));
			$IA_Subject_no3 = strtoupper(trim($studentData[106]));
			$IA_T_Max3 = strtoupper(trim($studentData[107]));
			$IA_T_Min3 = strtoupper(trim($studentData[108]));
			$IA_T_OBT3 = strtoupper(trim($studentData[109]));
			$IA_P_Max3 = strtoupper(trim($studentData[110]));
			$IA_P_Min3 = strtoupper(trim($studentData[111]));
			$IA_P_OBT3 = strtoupper(trim($studentData[112]));
			$IA_TO_MAX3 = strtoupper(trim($studentData[113]));
			$IA_TO_MIN3 = strtoupper(trim($studentData[114]));
			$IA_TO_OBT3 = strtoupper(trim($studentData[115]));
			
			$IA_SL_no4 = strtoupper(trim($studentData[116]));
			$IA_Subject_no4 = strtoupper(trim($studentData[117]));
			$IA_T_Max4 = strtoupper(trim($studentData[118]));
			$IA_T_Min4 = strtoupper(trim($studentData[119]));
			$IA_T_OBT4 = strtoupper(trim($studentData[120]));
			$IA_P_Max4 = strtoupper(trim($studentData[121]));
			$IA_P_Min4 = strtoupper(trim($studentData[122]));
			$IA_P_OBT4 = strtoupper(trim($studentData[123]));
			$IA_TO_MAX4 = strtoupper(trim($studentData[124]));
			$IA_TO_MIN4 = strtoupper(trim($studentData[125]));
			$IA_TO_OBT4 = strtoupper(trim($studentData[126]));
			
			$ELIGIBILITY_FOR_UE = strtoupper(trim($studentData[127]));
			$GRAND_TOTAL_MAX = strtoupper(trim($studentData[128]));
			$GRAND_TOTAL_MIN = strtoupper(trim($studentData[129]));
			$GRAND_TOTAL_OBT = strtoupper(trim($studentData[130]));			
			$Date = trim($studentData[131]);
			$batch_of_qr = trim($studentData[133]);
			$aadhar_number_qr = trim($studentData[134]);
			$DOB = trim($studentData[135]);			
			$course = strtoupper(trim($studentData[136]));            
            
			$QR_Output="Batch Of: $batch_of_qr\nName of the Student: $candidate_name\nAadhar Number: $aadhar_number_qr\nCourse: $course\nYear: $year\nDate Of Birth: $DOB";            
			$footer_note="SDM College of Medical Sciences and Hospital, Sattur, Dharwad – 580009, Karnataka, India";
			
			$pdfBig->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdfBig->SetXY(69, 106.5);
			$pdfBig->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'OBT', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'OBT', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdfBig->MultiCell(9, 0, 'OBT', 0, 'C', 0, 0);
			$pdfBig->MultiCell(10, 0, 'MAX', 0, 'C', 0, 0);
			$pdfBig->MultiCell(10, 0, 'MIN', 0, 'C', 0, 0);
			$pdfBig->MultiCell(10, 0, 'OBT', 0, 'C', 0, 0);	
			
			$pdf->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdf->SetXY(69, 106.5);
			$pdf->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'OBT', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'OBT', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'MAX', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'MIN', 0, 'C', 0, 0);
			$pdf->MultiCell(9, 0, 'OBT', 0, 'C', 0, 0);
			$pdf->MultiCell(10, 0, 'MAX', 0, 'C', 0, 0);
			$pdf->MultiCell(10, 0, 'MIN', 0, 'C', 0, 0);
			$pdf->MultiCell(10, 0, 'OBT', 0, 'C', 0, 0);	
			
			
			/******Start pdfBig ******/  			
            
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
			$pdfBig->SetFont($MyriadProRegular, '', 15, '', false);
			$pdfBig->SetXY($title1_x, 55.5);
			$pdfBig->Cell(188, 0, $specilisation, 0, false, 'C');
			$pdfBig->SetFont($MinionProBold, '', 12, '', false);			
			$pdfBig->SetXY($title1_x, 65.8);
			$pdfBig->Cell(188, 0, $year_examination, 0, false, 'C');			
			
			/*if($specilisation==""){
				$pdfBig->SetFont($MyriadProRegular, '', 12, '', false);
				$pdfBig->SetXY($title1_x, 59.5);
				$pdfBig->Cell(188, 0, $year, 0, false, 'C');
				$pdfBig->SetFont($MinionProBold, '', 12, '', false);			
				$pdfBig->SetXY($title1_x, 65.8);
				$pdfBig->Cell(188, 0, $year_examination, 0, false, 'C');
			}else{				
				$pdfBig->SetFont($MinionProBold, '', 12, '', false);			
				$pdfBig->SetXY($title1_x, 59.5);
				$pdfBig->Cell(188, 0, $specilisation, 0, false, 'C');				
				$pdfBig->SetFont($MyriadProRegular, '', 12, '', false);
				$pdfBig->SetXY($title1_x, 65.8);
				$pdfBig->Cell(188, 0, $year, 0, false, 'C');
				$pdfBig->SetFont($MinionProBold, '', 12, '', false);			
				$pdfBig->SetXY($title1_x, 71.8);
				$pdfBig->Cell(188, 0, $year_examination, 0, false, 'C');	
			}*/
						
			$pdfBig->SetFont($MyriadProItalic, 'I', 12, '', false);
            $pdfBig->SetXY($title1_x, 80);
            $pdfBig->Cell(0, 0, 'NAME OF THE STUDENT:', 0, false, 'L');
            $pdfBig->SetXY($title1_x, 86);
            $pdfBig->Cell(0, 0, "UNIVERSITY REG. NO.:", 0, false, 'L');
            $pdfBig->SetXY($title1_x, $left_title3_y);
			
			$pdfBig->SetFont($MyriadProBoldItalic, 'BI', 12, '', false);
            $pdfBig->SetXY(56, 80);
            $pdfBig->Cell(0, 0, $candidate_name, 0, false, 'L');
			$pdfBig->SetXY(51, 86);
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
			$pdf->SetFont($MyriadProRegular, '', 15, '', false);
			$pdf->SetXY($title1_x, 55.5);
			$pdf->Cell(188, 0, $specilisation, 0, false, 'C');
			$pdf->SetFont($MinionProBold, '', 12, '', false);			
			$pdf->SetXY($title1_x, 65.8);
			$pdf->Cell(188, 0, $year_examination, 0, false, 'C');			
			
			/*if($specilisation==""){
				$pdf->SetFont($MyriadProRegular, '', 12, '', false);
				$pdf->SetXY($title1_x, 59.5);
				$pdf->Cell(188, 0, $year, 0, false, 'C');
				$pdf->SetFont($MinionProBold, '', 12, '', false);			
				$pdf->SetXY($title1_x, 65.8);
				$pdf->Cell(188, 0, $year_examination, 0, false, 'C');
			}else{				
				$pdf->SetFont($MinionProBold, '', 12, '', false);			
				$pdf->SetXY($title1_x, 59.5);
				$pdf->Cell(188, 0, $specilisation, 0, false, 'C');				
				$pdf->SetFont($MyriadProRegular, '', 12, '', false);
				$pdf->SetXY($title1_x, 65.8);
				$pdf->Cell(188, 0, $year, 0, false, 'C');
				$pdf->SetFont($MinionProBold, '', 12, '', false);			
				$pdf->SetXY($title1_x, 71.8);
				$pdf->Cell(188, 0, $year_examination, 0, false, 'C');	
			}*/
						
			$pdf->SetFont($MyriadProItalic, 'I', 12, '', false);
            $pdf->SetXY($title1_x, 80);
            $pdf->Cell(0, 0, 'NAME OF THE STUDENT:', 0, false, 'L');
            $pdf->SetXY($title1_x, 86);
            $pdf->Cell(0, 0, "UNIVERSITY REG. NO.:", 0, false, 'L');
            $pdf->SetXY($title1_x, $left_title3_y);
			
			$pdf->SetFont($MyriadProBoldItalic, 'BI', 12, '', false);
            $pdf->SetXY(56, 80);
            $pdf->Cell(0, 0, $candidate_name, 0, false, 'L');
			$pdf->SetXY(51, 86);
            $pdf->Cell(0, 0, $ureg_no, 0, false, 'L');		
			
			
			/*Subject*/
			$subj_y=110;			
			
			$pdfBig->SetFont($MyriadProRegular, '', 7.5, '', false);					
			$pdf->SetFont($MyriadProRegular, '', 7.5, '', false);					
			
			/******Start pdfBig ******/ 
			//Subject 1
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(9.5, 5, '1', 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(44.5, 5, $Subject1, 'LR', 'L', 0, 0);					
			$pdfBig->MultiCell(9, 5, $T_Max1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_Min1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_OBT1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $TA_Max1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $TA_Min1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $TA_OBT1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $PV_MAX1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $PV_MIN1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $PV_OBT1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $TOTAL_MAX1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $TOTAL_MIN1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $TOTAL_OBT1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(15, 5, $REMARK1, 'LR', 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(9.5, 5, '1', 'LR', 'C', 0, 0);
			$pdf->MultiCell(44.5, 5, $Subject1, 'LR', 'L', 0, 0);					
			$pdf->MultiCell(9, 5, $T_Max1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_Min1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_OBT1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $TA_Max1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $TA_Min1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $TA_OBT1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $PV_MAX1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $PV_MIN1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $PV_OBT1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $TOTAL_MAX1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $TOTAL_MIN1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $TOTAL_OBT1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(15, 5, $REMARK1, 'LR', 'C', 0, 0);
			//Subject 2			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(9.5, 5, '2', 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(44.5, 5, $Subject2, 'LR', 'L', 0, 0);					
			$pdfBig->MultiCell(9, 5, $T_Max2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_Min2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_OBT2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $TA_Max2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $TA_Min2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $TA_OBT2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $PV_MAX2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $PV_MIN2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $PV_OBT2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $TOTAL_MAX2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $TOTAL_MIN2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $TOTAL_OBT2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(15, 5, $REMARK2, 'LR', 'C', 0, 0);
			$pdfBig->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(9.5, 5, '2', 'LR', 'C', 0, 0);
			$pdf->MultiCell(44.5, 5, $Subject2, 'LR', 'L', 0, 0);					
			$pdf->MultiCell(9, 5, $T_Max2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_Min2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_OBT2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $TA_Max2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $TA_Min2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $TA_OBT2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $PV_MAX2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $PV_MIN2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $PV_OBT2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $TOTAL_MAX2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $TOTAL_MIN2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $TOTAL_OBT2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(15, 5, $REMARK2, 'LR', 'C', 0, 0);
			$pdf->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');
			
			//Subject 3 
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(9.5, 5, '3', 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(44.5, 5, $Subject3, 'LR', 'L', 0, 0);					
			$pdfBig->MultiCell(9, 5, $T_Max3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_Min3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_OBT3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $TA_Max3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $TA_Min3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $TA_OBT3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $PV_MAX3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $PV_MIN3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $PV_OBT3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $TOTAL_MAX3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $TOTAL_MIN3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $TOTAL_OBT3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(15, 5, $REMARK3, 'LR', 'C', 0, 0);
			$pdfBig->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(9.5, 5, '3', 'LR', 'C', 0, 0);
			$pdf->MultiCell(44.5, 5, $Subject3, 'LR', 'L', 0, 0);					
			$pdf->MultiCell(9, 5, $T_Max3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_Min3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_OBT3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $TA_Max3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $TA_Min3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $TA_OBT3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $PV_MAX3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $PV_MIN3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $PV_OBT3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $TOTAL_MAX3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $TOTAL_MIN3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $TOTAL_OBT3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(15, 5, $REMARK3, 'LR', 'C', 0, 0);
			$pdf->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');
			
			//Subject 4 & 5		
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 3, $right = '', $bottom = '');
			$pdfBig->MultiCell(9.5, 10, '4', 'LRB', 'C', 0, 0);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(44.5, 5, $Subject4, 0, 'L', 0, 0);					
			$pdfBig->MultiCell(9, 5, $T_Max4, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_Min4, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_OBT4, 'LR', 'C', 0, 0);
			$pdfBig->setCellPaddings( $left = '', $top = 3, $right = '', $bottom = '');
			$pdfBig->MultiCell(9, 10, $TA_Max5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(9, 10, $TA_Min5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(9, 10, $TA_OBT5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(9, 10, $PV_MAX5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(9, 10, $PV_MIN5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(9, 10, $PV_OBT5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 10, $TOTAL_MAX5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 10, $TOTAL_MIN5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 10, $TOTAL_OBT5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(15, 10, $REMARK5, 'LRB', 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 3, $right = '', $bottom = '');
			$pdf->MultiCell(9.5, 10, '4', 'LRB', 'C', 0, 0);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(44.5, 5, $Subject4, 0, 'L', 0, 0);					
			$pdf->MultiCell(9, 5, $T_Max4, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_Min4, 'LR', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_OBT4, 'LR', 'C', 0, 0);
			$pdf->setCellPaddings( $left = '', $top = 3, $right = '', $bottom = '');
			$pdf->MultiCell(9, 10, $TA_Max5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(9, 10, $TA_Min5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(9, 10, $TA_OBT5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(9, 10, $PV_MAX5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(9, 10, $PV_MIN5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(9, 10, $PV_OBT5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 10, $TOTAL_MAX5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 10, $TOTAL_MIN5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 10, $TOTAL_OBT5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(15, 10, $REMARK5, 'LRB', 'C', 0, 0);
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY(24.5, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(44.5, 5, $Subject5, 'B', 'L', 0, 0);					
			$pdfBig->MultiCell(9, 5, $T_Max5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_Min5, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(9, 5, $T_OBT5, 'LRB', 'C', 0, 0);	
			$pdfBig->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');	

			$pdf->SetXY(24.5, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(44.5, 5, $Subject5, 'B', 'L', 0, 0);					
			$pdf->MultiCell(9, 5, $T_Max5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_Min5, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(9, 5, $T_OBT5, 'LRB', 'C', 0, 0);	
			$pdf->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');		
			
			$pdfBig->SetFont($MyriadProBold, 'B', 9, '', false);
			$pdf->SetFont($MyriadProBold, 'B', 9, '', false);
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(135, 5, 'TOTAL (UE): ', 'L', 'R', 0, 0);					
			$pdfBig->MultiCell(10, 5, $Total_UE_MAX, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $Total_UE_MIN, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $Total_UE_OBT, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(15, 5, '', 'R', 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(135, 5, 'TOTAL (UE): ', 'L', 'R', 0, 0);					
			$pdf->MultiCell(10, 5, $Total_UE_MAX, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $Total_UE_MIN, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $Total_UE_OBT, 'LR', 'C', 0, 0);
			$pdf->MultiCell(15, 5, '', 'R', 'C', 0, 0);
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(135, 5, 'TOTAL (IA): ', 'LB', 'R', 0, 0);					
			$pdfBig->MultiCell(10, 5, $Total_IA_MAX, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $Total_IA_MIN, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $Total_IA_OBT, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(15, 5, '', 'R', 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(135, 5, 'TOTAL (IA): ', 'LB', 'R', 0, 0);					
			$pdf->MultiCell(10, 5, $Total_IA_MAX, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $Total_IA_MIN, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $Total_IA_OBT, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(15, 5, '', 'R', 'C', 0, 0);
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(135, 5, 'GRAND TOTAL (UE+IA): ', 'LB', 'R', 0, 0);					
			$pdfBig->MultiCell(10, 5, $Total_UEIA_MAX, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $Total_UEIA_MIN, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $Total_UEIA_OBT, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(15, 5, '', 'RB', 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(135, 5, 'GRAND TOTAL (UE+IA): ', 'LB', 'R', 0, 0);					
			$pdf->MultiCell(10, 5, $Total_UEIA_MAX, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $Total_UEIA_MIN, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $Total_UEIA_OBT, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(15, 5, '', 'RB', 'C', 0, 0);
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(135, 5, 'GRAND TOTAL IN WORDS (UE+IA): ', 'L', 'R', 0, 0);
			$pdfBig->MultiCell(45, 5, $Grand_Total_In_Words, 'LR', 'L', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(135, 5, 'GRAND TOTAL IN WORDS (UE+IA): ', 'L', 'R', 0, 0);
			$pdf->MultiCell(45, 5, $Grand_Total_In_Words, 'LR', 'L', 0, 0);
			//PERCENTAGE
			/*$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(135, 5, '', 'L', 'R', 0, 0);
			$pdfBig->MultiCell(45, 5, '', 'LR', 'L', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(135, 5, '', 'L', 'R', 0, 0);
			$pdf->MultiCell(45, 5, '', 'LR', 'L', 0, 0);*/
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(135, 5, 'RESULT: ', 'LB', 'R', 0, 0);
			$pdfBig->MultiCell(45, 5, $Result, 'LRB', 'L', 0, 0);
			$pdfBig->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(135, 5, 'RESULT: ', 'LB', 'R', 0, 0);
			$pdf->MultiCell(45, 5, $Result, 'LRB', 'L', 0, 0);
			$pdf->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');
			
			/*IA start*/
			$pdfBig->SetFont($MyriadProRegular, '', 9, '', false);	
			$pdf->SetFont($MyriadProRegular, '', 9, '', false);	
			$subj_y=$pdfBig->GetY(); 
			$pdfBig->SetXY($left_str_x, $subj_y+7.5);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');	
			$pdfBig->MultiCell(180, 5, 'INTERNAL ASSESSMENT (IA)', 'LRTB', 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y+7.5);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');	
			$pdf->MultiCell(180, 5, 'INTERNAL ASSESSMENT (IA)', 'LRTB', 'C', 0, 0);
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 3, $right = '', $bottom = '');	
			$pdfBig->MultiCell(12, 10, 'SL. NO.', 1, 'L', 0, 0);
			$pdfBig->MultiCell(60, 10, 'SUBJECT', 1, 'C', 0, 0);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');	
			$pdfBig->MultiCell(30, 10, 'THEORY', 1, 'C', 0, 0);
			$pdfBig->MultiCell(30, 10, 'PRACTICAL', 1, 'C', 0, 0);
			$pdfBig->MultiCell(30, 10, 'TOTAL', 1, 'C', 0, 0);
			$pdfBig->MultiCell(18, 10, 'ELIGIBILITY FOR UE', 1, 'C', 0, 0, '', '', true, 0, true);
			
			$pdf->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 3, $right = '', $bottom = '');	
			$pdf->MultiCell(12, 10, 'SL. NO.', 1, 'L', 0, 0);
			$pdf->MultiCell(60, 10, 'SUBJECT', 1, 'C', 0, 0);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');	
			$pdf->MultiCell(30, 10, 'THEORY', 1, 'C', 0, 0);
			$pdf->MultiCell(30, 10, 'PRACTICAL', 1, 'C', 0, 0);
			$pdf->MultiCell(30, 10, 'TOTAL', 1, 'C', 0, 0);
			$pdf->MultiCell(18, 10, 'ELIGIBILITY FOR UE', 1, 'C', 0, 0, '', '', true, 0, true);
			
			$subj_y=$pdfBig->GetY()+10;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->MultiCell(12, 5, $IA_SL_no1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(60, 5, $IA_Subject_no1, 'LR', 'L', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_Max1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_Min1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_OBT1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_Max1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_Min1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_OBT1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_MAX1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_MIN1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_OBT1, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(18, 5, $ELIGIBILITY_FOR_UE, 'LR', 'C', 0, 0);
			// $pdfBig->setCellPaddings( $left = '', $top = 7.5, $right = '', $bottom = '');
			// $pdfBig->MultiCell(18, 25.1, $ELIGIBILITY_FOR_UE, 1, 'C', 0, 0);
			// $pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->MultiCell(12, 5, $IA_SL_no1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(60, 5, $IA_Subject_no1, 'LR', 'L', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_Max1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_Min1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_OBT1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_Max1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_Min1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_OBT1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_MAX1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_MIN1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_OBT1, 'LR', 'C', 0, 0);
			$pdf->MultiCell(18, 5, $ELIGIBILITY_FOR_UE, 'LR', 'C', 0, 0);
			// $pdf->setCellPaddings( $left = '', $top = 7.5, $right = '', $bottom = '');
			// $pdf->MultiCell(18, 25.1, $ELIGIBILITY_FOR_UE, 1, 'C', 0, 0);
			// $pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->MultiCell(12, 5, $IA_SL_no2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(60, 5, $IA_Subject_no2, 'LR', 'L', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_Max2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_Min2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_OBT2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_Max2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_Min2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_OBT2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_MAX2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_MIN2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_OBT2, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(18, 5, $ELIGIBILITY_FOR_UE, 'LR', 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->MultiCell(12, 5, $IA_SL_no2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(60, 5, $IA_Subject_no2, 'LR', 'L', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_Max2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_Min2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_OBT2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_Max2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_Min2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_OBT2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_MAX2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_MIN2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_OBT2, 'LR', 'C', 0, 0);
			$pdf->MultiCell(18, 5, $ELIGIBILITY_FOR_UE, 'LR', 'C', 0, 0);
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->MultiCell(12, 5, $IA_SL_no3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(60, 5, $IA_Subject_no3, 'LR', 'L', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_Max3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_Min3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_OBT3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_Max3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_Min3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_OBT3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_MAX3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_MIN3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_OBT3, 'LR', 'C', 0, 0);
			$pdfBig->MultiCell(18, 5, $ELIGIBILITY_FOR_UE, 'LR', 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->MultiCell(12, 5, $IA_SL_no3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(60, 5, $IA_Subject_no3, 'LR', 'L', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_Max3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_Min3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_OBT3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_Max3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_Min3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_OBT3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_MAX3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_MIN3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_OBT3, 'LR', 'C', 0, 0);
			$pdf->MultiCell(18, 5, $ELIGIBILITY_FOR_UE, 'LR', 'C', 0, 0);
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->MultiCell(12, 5, $IA_SL_no4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(60, 5, $IA_Subject_no4, 'LRB', 'L', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_Max4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_Min4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_T_OBT4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_Max4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_Min4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_P_OBT4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_MAX4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_MIN4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $IA_TO_OBT4, 'LRB', 'C', 0, 0);
			$pdfBig->MultiCell(18, 5, $ELIGIBILITY_FOR_UE, 'LR', 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->MultiCell(12, 5, $IA_SL_no4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(60, 5, $IA_Subject_no4, 'LRB', 'L', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_Max4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_Min4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_T_OBT4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_Max4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_Min4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_P_OBT4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_MAX4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_MIN4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(10, 5, $IA_TO_OBT4, 'LRB', 'C', 0, 0);
			$pdf->MultiCell(18, 5, $ELIGIBILITY_FOR_UE, 'LR', 'C', 0, 0);
			
			
			$subj_y=$pdfBig->GetY()+5;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->SetFont($MyriadProBold, 'B', 9, '', false);
			$pdfBig->MultiCell(132, 5, 'GRAND TOTAL: ', 1, 'R', 0, 0);
			$pdfBig->MultiCell(10, 5, $GRAND_TOTAL_MAX, 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $GRAND_TOTAL_MIN, 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 5, $GRAND_TOTAL_OBT, 1, 'C', 0, 0);
			$pdfBig->MultiCell(18, 5, '', 1, 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->SetFont($MyriadProBold, 'B', 9, '', false);
			$pdf->MultiCell(132, 5, 'GRAND TOTAL: ', 1, 'R', 0, 0);
			$pdf->MultiCell(10, 5, $GRAND_TOTAL_MAX, 1, 'C', 0, 0);
			$pdf->MultiCell(10, 5, $GRAND_TOTAL_MIN, 1, 'C', 0, 0);
			$pdf->MultiCell(10, 5, $GRAND_TOTAL_OBT, 1, 'C', 0, 0);
			$pdf->MultiCell(18, 5, '', 1, 'C', 0, 0);
			
			/*IA End*/
			$notes="<span style='font-size:18;'>•</span> Minimum Marks for Eligibility to appear for University Examinations: Internal Assessment 50% combined in theory and practical (not less than 40%<br>&nbsp;&nbsp;in each).
			<br><span style='font-size:18;'>•</span> University Examinations: Minimum marks to pass: 50% marks separately in Theory and practical + viva voce.
			<br><span style='font-size:18;'>•</span> University Theory Examination: Minimum 40% marks in each paper with a minimum 50% of marks in aggregate (both papers together) to pass in<br>&nbsp;&nbsp;each subject.
			<br><span style='font-size:18;'>•</span> University practical exam (Including viva voce): Minimum 50% in each subject.
			";			
			$subj_y=$pdfBig->GetY()+7;
			$pdfBig->SetFont($MyriadProItalic, 'I', 8.5, '', false);
			$pdfBig->SetXY($left_str_x, $subj_y);			
			$pdfBig->MultiCell(180, 0, $notes, 1, 'L', 0, 0, '', '', true, 0, true);
			
			$pdf->SetFont($MyriadProItalic, 'I', 8.5, '', false);
			$pdf->SetXY($left_str_x, $subj_y);			
			$pdf->MultiCell(180, 0, $notes, 1, 'L', 0, 0, '', '', true, 0, true);
			
			$subj_y=$pdfBig->GetY()+23.7;
			$pdfBig->SetFont($MyriadProRegular, '', 8.5, '', false);	
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->setCellPaddings( $left = '', $top = 2.3, $right = '', $bottom = '');
			$pdfBig->MultiCell(36.5, 9, '% Marks (First Attempt)', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(20.5, 9, '75% & More', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->MultiCell(44, 9, '65% &amp; More but<br>Less than 75%', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(40.5, 9, '50% &amp; More but<br>Less than 65%', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdfBig->MultiCell(38.5, 9, 'Pass in More than<br>one attempt', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			
			$pdf->SetFont($MyriadProRegular, '', 8.5, '', false);	
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->setCellPaddings( $left = '', $top = 2.3, $right = '', $bottom = '');
			$pdf->MultiCell(36.5, 9, '% Marks (First Attempt)', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(20.5, 9, '75% & More', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->MultiCell(44, 9, '65% &amp; More but<br>Less than 75%', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(40.5, 9, '50% &amp; More but<br>Less than 65%', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdf->MultiCell(38.5, 9, 'Pass in More than<br>one attempt', 1, 'C', 0, 0, '', '', true, 0, true);
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			
			$subj_y=$pdfBig->GetY()+9;
			$pdfBig->SetXY($left_str_x, $subj_y);
			$pdfBig->MultiCell(36.5, 5, 'Class', 1, 'C', 0, 0);
			$pdfBig->MultiCell(20.5, 5, 'Distinction', 1, 'C', 0, 0);
			$pdfBig->MultiCell(44, 5, 'First Class', 1, 'C', 0, 0);
			$pdfBig->MultiCell(40.5, 5, 'Second Class', 1, 'C', 0, 0);
			$pdfBig->MultiCell(38.5, 5, 'Pass only', 1, 'C', 0, 0);
			
			$pdf->SetXY($left_str_x, $subj_y);
			$pdf->MultiCell(36.5, 5, 'Class', 1, 'C', 0, 0);
			$pdf->MultiCell(20.5, 5, 'Distinction', 1, 'C', 0, 0);
			$pdf->MultiCell(44, 5, 'First Class', 1, 'C', 0, 0);
			$pdf->MultiCell(40.5, 5, 'Second Class', 1, 'C', 0, 0);
			$pdf->MultiCell(38.5, 5, 'Pass only', 1, 'C', 0, 0);
			
			//Max Min Obt - IA
			$pdfBig->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdfBig->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdfBig->SetXY(87, 172.7);
			$pdfBig->MultiCell(10, 4, 'MAX', 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 4, 'MIN', 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 4, 'OBT', 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 4, 'MAX', 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 4, 'MIN', 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 4, 'OBT', 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 4, 'MAX', 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 4, 'MIN', 1, 'C', 0, 0);
			$pdfBig->MultiCell(10, 4, 'OBT', 1, 'C', 0, 0);
			
			$pdf->setCellPaddings( $left = '', $top = 1, $right = '', $bottom = '');
			$pdf->SetFont($MyriadProRegular, '', 8.5, '', false);
			$pdf->SetXY(87, 172.7);
			$pdf->MultiCell(10, 4, 'MAX', 1, 'C', 0, 0);
			$pdf->MultiCell(10, 4, 'MIN', 1, 'C', 0, 0);
			$pdf->MultiCell(10, 4, 'OBT', 1, 'C', 0, 0);
			$pdf->MultiCell(10, 4, 'MAX', 1, 'C', 0, 0);
			$pdf->MultiCell(10, 4, 'MIN', 1, 'C', 0, 0);
			$pdf->MultiCell(10, 4, 'OBT', 1, 'C', 0, 0);
			$pdf->MultiCell(10, 4, 'MAX', 1, 'C', 0, 0);
			$pdf->MultiCell(10, 4, 'MIN', 1, 'C', 0, 0);
			$pdf->MultiCell(10, 4, 'OBT', 1, 'C', 0, 0);

			$pdfBig->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');	
			$pdf->setCellPaddings( $left = '', $top = 0, $right = '', $bottom = '');	
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
			$pdfBig->MultiCell(92, 0, $footer_note,0, 'C', 0, 0);
			
			
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
			$pdf->MultiCell(92, 0, $footer_note,0, 'C', 0, 0);
			
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
