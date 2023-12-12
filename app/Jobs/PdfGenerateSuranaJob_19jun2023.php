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
class PdfGenerateSuranaJob
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

	function negate($image)
	{
		if(function_exists('imagefilter'))
		{
		return imagefilter($image, IMG_FILTER_NEGATE);
		}
		for($x = 0; $x < imagesx($image); ++$x)
		{
			for($y = 0; $y < imagesy($image); ++$y)
			{
			$index = imagecolorat($image, $x, $y);
			$rgb = imagecolorsforindex($index);
			$color = imagecolorallocate($image, 255 - $rgb['red'], 255 - $rgb['green'], 255 - $rgb['blue']);
			imagesetpixel($im, $x, $y, $color);
			}
		}
		return(true);
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
        
		$GrandTotal_CT_Max_key=$pdf_data['GrandTotal_CT_Max_key'];
		$GrandTotal_CT_Min_key=$pdf_data['GrandTotal_CT_Min_key'];
		$GrandTotal_CT_Sec_key=$pdf_data['GrandTotal_CT_Sec_key'];
		$GrandTotal_GP_key=$pdf_data['GrandTotal_GP_key'];
		$GrandTotal_CA_key=$pdf_data['GrandTotal_CA_key'];
		$GrandTotal_CP_key=$pdf_data['GrandTotal_CP_key'];
		$SR_SGPA_key=$pdf_data['SR_SGPA_key'];
		$SR_Alpha_signed_Grade_key=$pdf_data['SR_Alpha_signed_Grade_key'];
		$SR_Credits_Earned_key=$pdf_data['SR_Credits_Earned_key'];
		$SR_semester_percentage_Of_Marks_key=$pdf_data['SR_semester_percentage_Of_Marks_key'];
		$SR_Class_description_key=$pdf_data['SR_Class_description_key'];
		$PR_SGPA_key=$pdf_data['PR_SGPA_key'];
		$PR_Alpha_signed_Grade_key=$pdf_data['PR_Alpha_signed_Grade_key'];
		$PR_Credits_Earned_key=$pdf_data['PR_Credits_Earned_key'];
		$PR_semester_percentage_Of_Marks_key=$pdf_data['PR_semester_percentage_Of_Marks_key'];
		$PR_Class_description_key=$pdf_data['PR_Class_description_key'];
		$Date_key=$pdf_data['Date_key'];
		$Photo_key=$pdf_data['Photo_key'];
		$subj_col = $pdf_data['subj_col'];
		$subj_start = $pdf_data['subj_start'];
		$subj_end = $pdf_data['subj_end'];       
		$template_id=$pdf_data['template_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];
        
        $first_sheet=$pdf_data['studentDataOrg']; // get first worksheet rows
        //$second_sheet=$pdf_data['subjectsMark']; // get second worksheet rows
        $total_unique_records=count($first_sheet);
        $last_row=$total_unique_records+1;
        //$course_count = array_count_values(array_column($second_sheet, '0'));
        //$max_course_count = (max($course_count)); 
        
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
        $pdfBig->SetTitle('Grade Card');
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

        //$card_serial_no="";
		$preview_serial_no=1;
        $log_serial_no = 1;
        $cardDetails=$this->getNextCardNo('BcomGC');
        $card_serial_no=$cardDetails->next_serial_no;
        $generated_documents=0;  
        foreach ($studentDataOrg as $studentData) {
         
            if($card_serial_no>999999 && $previewPdf!=1){
                //echo "<h5>Your grade card series ended...!</h5>";
                //exit;
            }
            //For Custom Loader
            $startTimeLoader =  date('Y-m-d H:i:s'); 
            $high_res_bg="surana_blank.jpeg"; 
            $low_res_bg="surana_blank.jpeg";
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
                $date_noy = 40;
                $date_nostr = ''; //'DRAFT '.date('d-m-Y H:i:s');
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
			$pdf->setCellPaddings( $left = '', $top = '', $right = '', $bottom = '');			
            $print_serial_no = $this->nextPrintSerial();
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;

            if($previewPdf!=1){
                $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
            }
            $pdf->setPageMark();
            
            //if($previewPdf!=1){ 
			if($previewPdf == 1){ 	
                $x= 176;
                $y = 47.2;
                $font_size=12;
                
                //if($previewPdf!=1){
					//$str = str_pad($card_serial_no, 6, '0', STR_PAD_LEFT);
				//}else{
					$str = str_pad($preview_serial_no, 6, '0', STR_PAD_LEFT);	
				//}				
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
            }
            
            //images
            if($studentData[$Photo_key]!=''){
                //path of photos
                $profile_path_org = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[$Photo_key];        
                $path_info = pathinfo($profile_path_org);   
                $file_name = $path_info['filename'];
                $ext = $path_info['extension'];
                $uv_location = public_path()."/".$subdomain[0]."/backend/templates/100/".$file_name.'_uv.'.$ext;        
                if(!file_exists($uv_location)){  
                    copy($profile_path_org, $uv_location);
                }
                if($ext == 'png'){
                    $im = imagecreatefrompng($profile_path_org);
					imagefilter($im, IMG_FILTER_GRAYSCALE);
					imagefilter($im, IMG_FILTER_NEGATE);
					imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0);	
					imagejpeg($im, $uv_location);
					imagedestroy($im);
					/*$im = imagecreatefrompng($uv_location);
                    if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0))
                    {          
                        imagepng($im, $uv_location);
                        imagedestroy($im);
                    }*/    
                }else if($ext == 'jpeg' || $ext == 'jpg'){
                    $im = imagecreatefromjpeg($profile_path_org);
					imagefilter($im, IMG_FILTER_GRAYSCALE);
					imagefilter($im, IMG_FILTER_NEGATE);
					imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0);	
					imagejpeg($im, $uv_location);
					imagedestroy($im);	
					/*$im = imagecreatefromjpeg($uv_location);       
                    if($im && imagefilter($im, IMG_FILTER_COLORIZE, 255, 255, 0))
                    {          
                        imagejpeg($im, $uv_location);
                        imagedestroy($im);
                    } */     
					/*if($im && $this->negate($im))
					{
						//echo 'Image successfully converted to negative colors.';						
						imagejpeg($im, $uv_location, 100);
						imagedestroy($im);
						
					} */		
                }
                $photox = 174;
                $photoy = 14;
                $photoWidth = 20;
                $photoHeight = 23;
                //$pdf->image($uv_location,$photox,$photoy,$photoWidth,$photoHeight,"",'','L',true,3600);          
                $pdfBig->image($uv_location,$photox,$photoy,$photoWidth,$photoHeight,"",'','L',true,3600); 
                $pdfBig->setPageMark();
                
                //set profile image   
                $profilex = 174;
                $profiley = 48;
                $profileWidth = 20;
                $profileHeight = 25;
                $pdf->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
                $pdfBig->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);  
                $pdf->setPageMark();
                $pdfBig->setPageMark();
            }
            
            //start pdfBig
            $pdfBig->SetXY(15, 75.5);
            $pdfBig->SetFont($arialNarrowB, '', 8, '', false); 
            $pdfBig->SetFillColor(255, 255, 255);
            $pdfBig->SetTextColor(0, 0, 0);  
                        
            $pdfBig->SetXY(15, 75.5);     
            $pdfBig->MultiCell(5, 114.5, '','LRTB', 'L', 0, 0); //SL
            $pdfBig->MultiCell(62, 114.5, '','LRTB', 'L', 0, 0); //COURSE/CODE
            $pdfBig->MultiCell(74, 114.5, '', 'LRTB', 'C', 0, 0); //Marks
            $pdfBig->MultiCell(7, 114.5, '', 'LRTB', 'C', 0, 0); //GRADE POINTS
            $pdfBig->MultiCell(6, 114.5, '', 'LRTB', 'C', 0, 0); //CREDITS ASSIGNED
            $pdfBig->MultiCell(7, 114.5, '', 'LRTB', 'C', 0, 0); //CREDIT POINTS
            $pdfBig->MultiCell(19, 114.5, '', 'LRTB', 'C', 0, 0); //REMARKS
			    
            $pdfBig->MultiCell(6, 0, 'Sl.<br>No.', 0, 'L', 0, 0, 15, 83, true, 0, true); //SL
            $pdfBig->MultiCell(62, 0, 'COURSE CODE/TITLE', 0, 'L', 0, 0, 39, 85, true, 0, true); //COURSE/CODE
            $pdfBig->MultiCell(74, 0, 'MARKS', 0, 'L', 0, 0, 114, 76.5, true, 0, true); //Marks
			$pdfBig->MultiCell(74, 0, 'REMARKS', 0, 'L', 0, 0, 178, 85, true, 0, true); //REMARKS
			
			$pdfBig->MultiCell(27, 0, 'THEORY/PRACTICAL/<br>PROJECT<br>SEE', 0, 'C', 0, 0, 82, 82, true, 0, true); //
			$pdfBig->MultiCell(20.5, 0, 'IA', 0, 'C', 0, 0, 108.5, 85, true, 0, true); //
			$pdfBig->MultiCell(27, 0, 'COURSE TOTAL', 0, 'C', 0, 0, 129, 85, true, 0, true); //
			
			$pdfBig->SetXY(158, 98);
			$pdfBig->StartTransform();
			$pdfBig->Rotate(90);
			$pdfBig->Cell(0, 0, 'GRADE POINTS*', 0, false, 'L');
			$pdfBig->StopTransform(); 
			$pdfBig->SetXY(164, 100);
			$pdfBig->StartTransform();
			$pdfBig->Rotate(90);
			$pdfBig->Cell(0, 0, 'CREDITS ASSIGNED', 0, false, 'L');
			$pdfBig->StopTransform();
			$pdfBig->SetXY(170, 98);
			$pdfBig->StartTransform();
			$pdfBig->Rotate(90);
			$pdfBig->Cell(0, 0, 'CREDIT POINTS', 0, false, 'L');
			$pdfBig->StopTransform(); 
            			
			$pdfBig->SetXY(15, 99.5);     
            $pdfBig->MultiCell(180, 0, '','T', 'L', 0, 0);
			
			$pdfBig->SetXY(82, 81);
			$pdfBig->MultiCell(26.5, 104.5, '','TR', 'L', 0, 0); //TPP See
			$pdfBig->MultiCell(20.5, 104.5, '','TR', 'L', 0, 0); //IA
			$pdfBig->MultiCell(27, 104.5, '','TR', 'L', 0, 0); //Course Total
			
			$pdfBig->SetXY(82, 93);
			$pdfBig->MultiCell(8, 97, '','TR', 'L', 0, 0); //TPP Max
			$pdfBig->MultiCell(6, 97, '','TR', 'L', 0, 0); //Min
			$pdfBig->MultiCell(12.5, 88, '','T', 'L', 0, 0); //Secured			
			$pdfBig->MultiCell(7.5, 0, 'MAX',0, 'L', 0, 0, 82.5, 94.5, true, 0, true); //Max
			$pdfBig->MultiCell(6.3, 0, 'MIN',0, 'L', 0, 0, 90, 94.5, true, 0, true); //Min
			$pdfBig->MultiCell(14, 0, 'SECURED',0, 'L', 0, 0, 95.5, 94.5, true, 0, true); //Secured			
			
			$pdfBig->SetXY(108.5, 93);
			$pdfBig->MultiCell(8, 97, '','LTR', 'L', 0, 0); //IA Max
			$pdfBig->MultiCell(12.5, 97, '','TR', 'L', 0, 0); //Secured
			$pdfBig->MultiCell(7.5, 0, 'MAX',0, 'L', 0, 0, 109, 94.5, true, 0, true); //Max
			$pdfBig->MultiCell(14, 0, 'SECURED',0, 'L', 0, 0, 116.2, 94.5, true, 0, true); //Secured
			
			$pdfBig->SetXY(129, 93);
			$pdfBig->MultiCell(8, 97, '','TR', 'L', 0, 0); //Course Total Max
			$pdfBig->MultiCell(6, 97, '','TR', 'L', 0, 0); //Min
			$pdfBig->MultiCell(13, 97, '','TR', 'L', 0, 0); //Secured
			$pdfBig->MultiCell(7.5, 0, 'MAX',0, 'L', 0, 0, 129.5, 94.5, true, 0, true); //Max
			$pdfBig->MultiCell(6.3, 0, 'MIN',0, 'L', 0, 0, 137, 94.5, true, 0, true); //Min
			$pdfBig->MultiCell(14, 0, 'SECURED',0, 'L', 0, 0, 143, 94.5, true, 0, true); //Secured
			//end pdfBig
            //start pdf
            $pdf->SetXY(15, 75.5);
            $pdf->SetFont($arialNarrowB, '', 8, '', false); 
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0, 0, 0);  
                        
            $pdf->SetXY(15, 75.5);     
            $pdf->MultiCell(5, 114.5, '','LRTB', 'L', 0, 0); //SL
            $pdf->MultiCell(62, 114.5, '','LRTB', 'L', 0, 0); //COURSE/CODE
            $pdf->MultiCell(74, 114.5, '', 'LRTB', 'C', 0, 0); //Marks
            $pdf->MultiCell(7, 114.5, '', 'LRTB', 'C', 0, 0); //GRADE POINTS
            $pdf->MultiCell(6, 114.5, '', 'LRTB', 'C', 0, 0); //CREDITS ASSIGNED
            $pdf->MultiCell(7, 114.5, '', 'LRTB', 'C', 0, 0); //CREDIT POINTS
            $pdf->MultiCell(19, 114.5, '', 'LRTB', 'C', 0, 0); //REMARKS
			    
            $pdf->MultiCell(6, 0, 'Sl.<br>No.', 0, 'L', 0, 0, 15, 83, true, 0, true); //SL
            $pdf->MultiCell(62, 0, 'COURSE CODE/TITLE', 0, 'L', 0, 0, 39, 85, true, 0, true); //COURSE/CODE
            $pdf->MultiCell(74, 0, 'MARKS', 0, 'L', 0, 0, 114, 76.5, true, 0, true); //Marks
			$pdf->MultiCell(74, 0, 'REMARKS', 0, 'L', 0, 0, 178, 85, true, 0, true); //REMARKS
			
			$pdf->MultiCell(27, 0, 'THEORY/PRACTICAL/<br>PROJECT<br>SEE', 0, 'C', 0, 0, 82, 82, true, 0, true); //
			$pdf->MultiCell(20.5, 0, 'IA', 0, 'C', 0, 0, 108.5, 85, true, 0, true); //
			$pdf->MultiCell(27, 0, 'COURSE TOTAL', 0, 'C', 0, 0, 129, 85, true, 0, true); //
			
			$pdf->SetXY(158, 98);
			$pdf->StartTransform();
			$pdf->Rotate(90);
			$pdf->Cell(0, 0, 'GRADE POINTS*', 0, false, 'L');
			$pdf->StopTransform(); 
			$pdf->SetXY(164, 100);
			$pdf->StartTransform();
			$pdf->Rotate(90);
			$pdf->Cell(0, 0, 'CREDITS ASSIGNED', 0, false, 'L');
			$pdf->StopTransform();
			$pdf->SetXY(170, 98);
			$pdf->StartTransform();
			$pdf->Rotate(90);
			$pdf->Cell(0, 0, 'CREDIT POINTS', 0, false, 'L');
			$pdf->StopTransform(); 
            			
			$pdf->SetXY(15, 99.5);     
            $pdf->MultiCell(180, 0, '','T', 'L', 0, 0);
			
			$pdf->SetXY(82, 81);
			$pdf->MultiCell(26.5, 104.5, '','TR', 'L', 0, 0); //TPP See
			$pdf->MultiCell(20.5, 104.5, '','TR', 'L', 0, 0); //IA
			$pdf->MultiCell(27, 104.5, '','TR', 'L', 0, 0); //Course Total
			
			$pdf->SetXY(82, 93);
			$pdf->MultiCell(8, 97, '','TR', 'L', 0, 0); //TPP Max
			$pdf->MultiCell(6, 97, '','TR', 'L', 0, 0); //Min
			$pdf->MultiCell(12.5, 88, '','T', 'L', 0, 0); //Secured			
			$pdf->MultiCell(7.5, 0, 'MAX',0, 'L', 0, 0, 82.5, 94.5, true, 0, true); //Max
			$pdf->MultiCell(6.3, 0, 'MIN',0, 'L', 0, 0, 90, 94.5, true, 0, true); //Min
			$pdf->MultiCell(14, 0, 'SECURED',0, 'L', 0, 0, 95.5, 94.5, true, 0, true); //Secured			
			
			$pdf->SetXY(108.5, 93);
			$pdf->MultiCell(8, 97, '','LTR', 'L', 0, 0); //IA Max
			$pdf->MultiCell(12.5, 97, '','TR', 'L', 0, 0); //Secured
			$pdf->MultiCell(7.5, 0, 'MAX',0, 'L', 0, 0, 109, 94.5, true, 0, true); //Max
			$pdf->MultiCell(14, 0, 'SECURED',0, 'L', 0, 0, 116.2, 94.5, true, 0, true); //Secured
			
			$pdf->SetXY(129, 93);
			$pdf->MultiCell(8, 97, '','TR', 'L', 0, 0); //Course Total Max
			$pdf->MultiCell(6, 97, '','TR', 'L', 0, 0); //Min
			$pdf->MultiCell(13, 97, '','TR', 'L', 0, 0); //Secured
			$pdf->MultiCell(7.5, 0, 'MAX',0, 'L', 0, 0, 129.5, 94.5, true, 0, true); //Max
			$pdf->MultiCell(6.3, 0, 'MIN',0, 'L', 0, 0, 137, 94.5, true, 0, true); //Min
			$pdf->MultiCell(14, 0, 'SECURED',0, 'L', 0, 0, 143, 94.5, true, 0, true); //Secured
			//end pdf			
			
            
            $title1_x = 15; 
            $left_title1_y = 49;  
            
            $unique_id = $studentData[0];
            $candidate_name = $studentData[1];
            $Degree = $studentData[2];
            $Registration_No = $studentData[3];        
            $Scheme = $studentData[4];         
            $Month_Year_of_Examination = $studentData[5];        
            $Sem = $studentData[6];
            $MC_NO = $studentData[7];
			$GrandTotal_CT_Max=$studentData[$GrandTotal_CT_Max_key];
			$GrandTotal_CT_Min=$studentData[$GrandTotal_CT_Min_key];
			$GrandTotal_CT_Sec=$studentData[$GrandTotal_CT_Sec_key];
			$GrandTotal_GP=$studentData[$GrandTotal_GP_key];
			$GrandTotal_CA=$studentData[$GrandTotal_CA_key];
			$GrandTotal_CP=$studentData[$GrandTotal_CP_key];
			$SR_SGPA=$studentData[$SR_SGPA_key];
			$SR_Alpha_signed_Grade=$studentData[$SR_Alpha_signed_Grade_key];
			$SR_Credits_Earned=$studentData[$SR_Credits_Earned_key];
			$SR_semester_percentage_Of_Marks=$studentData[$SR_semester_percentage_Of_Marks_key];
			$SR_Class_description=$studentData[$SR_Class_description_key];
			$PR_SGPA=$studentData[$PR_SGPA_key];
			$PR_Alpha_signed_Grade=$studentData[$PR_Alpha_signed_Grade_key];
			$PR_Credits_Earned=$studentData[$PR_Credits_Earned_key];
			$PR_semester_percentage_Of_Marks=$studentData[$PR_semester_percentage_Of_Marks_key];
			$PR_Class_description=$studentData[$PR_Class_description_key];
			$Date=$studentData[$Date_key];
			$Photo=$studentData[$Photo_key];		
            //Microline
            $str=strtoupper($candidate_name.$Registration_No.$Degree);							
			$str = preg_replace('/\s+/', '', $str); 
			$textArray = imagettfbbox(10, 0, public_path().'/backend/fonts/Arialb.ttf', $str);
			$strWidth = ($textArray[2] - $textArray[0]);
			$strHeight = $textArray[6] - $textArray[1] / 10;
			$width=170;
			$latestWidth = round($width*3.7795280352161);
			 //Updated by Mandar
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
			$arrayEnrollment = substr($microlinestrRep,0,$microlinestrCharReq);	
			
			
            
            //Start pdfBig                
            // start invisible data
            $pdfBig->SetFont($arial, '', 9, '', false); 
            $pdfBig->SetTextColor(255, 255, 0);        
            $pdfBig->SetXY($title1_x, 42);
            $pdfBig->Cell(0, 0, $candidate_name, 0, false, 'L');
            // end invisible data        
            $pdfBig->SetFont($arial, '', 9, '', false);
            $pdfBig->SetTextColor(0, 0, 0);            
            $pdfBig->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
			$pdfBig->MultiCell(154, 6, 'Name: <b>'.$candidate_name.'</b>', 1, 'L', 0, 0, $title1_x, $left_title1_y, true, 0, true);
			$pdfBig->MultiCell(154, 6, 'Degree: <b>'.$Degree.'</b>', 1, 'L', 0, 0, $title1_x, $pdfBig->GetY()+6, true, 0, true);
			$pdfBig->MultiCell(98, 6, 'Registration No.: <b>'.$Registration_No.'</b>', 'LTB', 'L', 0, 0, $title1_x, $pdfBig->GetY()+6, true, 0, true);
			$pdfBig->MultiCell(56, 6, 'Scheme: <b>'.$Scheme.'</b>', 'BR', 'L', 0, 0, 113, $pdfBig->GetY(), true, 0, true);
			$pdfBig->MultiCell(98, 6, 'Months & Year of Examination: <b>'.$Month_Year_of_Examination.'</b>', 'LTB', 'L', 0, 0, $title1_x, $pdfBig->GetY()+6, true, 0, true);
			$pdfBig->MultiCell(56, 6, 'Sem: <b>'.$Sem.'</b>', 'BR', 'L', 0, 0, 113, $pdfBig->GetY(), true, 0, true);
			$pdfBig->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');            
            //End pdfBig 
			
			//Start pdf
            $pdf->SetFont($arial, '', 9, '', false);
            $pdf->SetTextColor(0, 0, 0);            
            $pdf->setCellPaddings( $left = '', $top = '1', $right = '', $bottom = '');
			$pdf->MultiCell(154, 6, 'Name: <b>'.$candidate_name.'</b>', 1, 'L', 0, 0, $title1_x, $left_title1_y, true, 0, true);
			$pdf->MultiCell(154, 6, 'Degree: <b>'.$Degree.'</b>', 1, 'L', 0, 0, $title1_x, $pdf->GetY()+6, true, 0, true);
			$pdf->MultiCell(98, 6, 'Registration No.: <b>'.$Registration_No.'</b>', 'LTB', 'L', 0, 0, $title1_x, $pdf->GetY()+6, true, 0, true);
			$pdf->MultiCell(56, 6, 'Scheme: <b>'.$Scheme.'</b>', 'BR', 'L', 0, 0, 113, $pdf->GetY(), true, 0, true);
			$pdf->MultiCell(98, 6, 'Months & Year of Examination: <b>'.$Month_Year_of_Examination.'</b>', 'LTB', 'L', 0, 0, $title1_x, $pdf->GetY()+6, true, 0, true);
			$pdf->MultiCell(56, 6, 'Sem: <b>'.$Sem.'</b>', 'BR', 'L', 0, 0, 113, $pdf->GetY(), true, 0, true);
			$pdf->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');            
            //End pdf 
			
            $subjectData = array_slice($studentData, $subj_start, $subj_end);
            $subjectsArr=array_chunk($subjectData, $subj_col);  
            $pdf->SetFont($arialNarrow, '', 8, '', false); 
            $pdfBig->SetFont($arialNarrow, '', 8, '', false); 
            //print_r($subjectsArr);
			$subj_y=100; 
            foreach ($subjectsArr as $subjectDatas){
                $SL_No=$subjectDatas[0]; 
                $Course_code=$subjectDatas[1];
                $TP_Max=$subjectDatas[2];
                $TP_Min=$subjectDatas[3];
                $TP_Sec=$subjectDatas[4];
                $IA_Max=$subjectDatas[5];
                $IA_Sec=$subjectDatas[6]; 
                $CT_Max=$subjectDatas[7]; 
                $CT_Min=$subjectDatas[8]; 
				$CT_Sec=$subjectDatas[9];
                $Grade_Points=$subjectDatas[10]; 
                $credits_Assigned=$subjectDatas[11]; 
                $Credit_Points=$subjectDatas[12]; 
                $Remark=$subjectDatas[13]; 				
                
                $pdfBig->MultiCell(5, 11, $SL_No, 0, 'C', 0, 0, 15, $subj_y);
                $pdfBig->MultiCell(62, 11, $Course_code,0, 'L', 0, 0, 20, $subj_y);
                $pdfBig->MultiCell(8, 11, $TP_Max,0, 'C', 0, 0, 82, $subj_y);
                $pdfBig->MultiCell(6, 11, $TP_Min,0, 'C', 0, 0, 90, $subj_y);
                $pdfBig->MultiCell(12.5, 11, $TP_Sec,0, 'C', 0, 0, 96, $subj_y);				
				$pdfBig->MultiCell(8, 11, $IA_Max,0, 'C', 0, 0, 108.5, $subj_y);
				$pdfBig->MultiCell(12.5, 11, $IA_Sec,0, 'C', 0, 0, 116, $subj_y);				
				$pdfBig->MultiCell(8, 11, $CT_Max,0, 'C', 0, 0, 129, $subj_y);
                $pdfBig->MultiCell(6, 11, $CT_Min,0, 'C', 0, 0, 137, $subj_y);
                $pdfBig->MultiCell(13, 11, $CT_Sec,0, 'C', 0, 0, 142, $subj_y);				
				$pdfBig->MultiCell(7, 11, $Grade_Points,0, 'C', 0, 0, 156.5, $subj_y);
				$pdfBig->MultiCell(6, 11, $credits_Assigned,0, 'C', 0, 0, 162, $subj_y);
				$pdfBig->MultiCell(7, 11, $Credit_Points,0, 'C', 0, 0, 169, $subj_y);
				$pdfBig->MultiCell(19, 11, $Remark,0, 'C', 0, 0, 176, $subj_y);

                $pdf->MultiCell(5, 11, $SL_No, 0, 'C', 0, 0, 15, $subj_y);
                $pdf->MultiCell(62, 11, $Course_code,0, 'L', 0, 0, 20, $subj_y);
                $pdf->MultiCell(8, 11, $TP_Max,0, 'C', 0, 0, 82, $subj_y);
                $pdf->MultiCell(6, 11, $TP_Min,0, 'C', 0, 0, 90, $subj_y);
                $pdf->MultiCell(12.5, 11, $TP_Sec,0, 'C', 0, 0, 96, $subj_y);				
				$pdf->MultiCell(8, 11, $IA_Max,0, 'C', 0, 0, 108.5, $subj_y);
				$pdf->MultiCell(12.5, 11, $IA_Sec,0, 'C', 0, 0, 116, $subj_y);				
				$pdf->MultiCell(8, 11, $CT_Max,0, 'C', 0, 0, 129, $subj_y);
                $pdf->MultiCell(6, 11, $CT_Min,0, 'C', 0, 0, 137, $subj_y);
                $pdf->MultiCell(13, 11, $CT_Sec,0, 'C', 0, 0, 142, $subj_y);				
				$pdf->MultiCell(7, 11, $Grade_Points,0, 'C', 0, 0, 156.5, $subj_y);
				$pdf->MultiCell(6, 11, $credits_Assigned,0, 'C', 0, 0, 162, $subj_y);
				$pdf->MultiCell(7, 11, $Credit_Points,0, 'C', 0, 0, 169, $subj_y);
				$pdf->MultiCell(19, 11, $Remark,0, 'C', 0, 0, 176, $subj_y);  				
                
                $subj_y=$subj_y+7;
            }
			//start pdfBig
			$pdfBig->SetFont($arialNarrowB, '', 8, '', false); 
            $pdfBig->SetXY(15, 186);
			$pdfBig->MultiCell(5, 11, '', 0, 'L', 0, 0);
			$pdfBig->MultiCell(62, 11, 'GRAND TOTAL',0, 'R', 0, 0);
			$pdfBig->MultiCell(8, 11, '',0, 'C', 0, 0);
			$pdfBig->MultiCell(6, 11, '',0, 'C', 0, 0);
			$pdfBig->MultiCell(12.5, 11, '',0, 'C', 0, 0);				
			$pdfBig->MultiCell(8, 11, '',0, 'C', 0, 0);
			$pdfBig->MultiCell(12.5, 11, '',0, 'C', 0, 0);				
			$pdfBig->MultiCell(8, 11, $GrandTotal_CT_Max,0, 'C', 0, 0);
			$pdfBig->MultiCell(6, 11, $GrandTotal_CT_Min,0, 'C', 0, 0);
			$pdfBig->MultiCell(13, 11, $GrandTotal_CT_Sec,0, 'C', 0, 0);				
			$pdfBig->MultiCell(7, 11, $GrandTotal_GP,0, 'C', 0, 0);
			$pdfBig->MultiCell(6, 11, $GrandTotal_CA,0, 'C', 0, 0);
			$pdfBig->MultiCell(7, 11, $GrandTotal_CP,0, 'C', 0, 0);
			$pdfBig->MultiCell(19, 11, '',0, 'C', 0, 0); 
			
			$pdfBig->SetXY(15, 185.5);     
            $pdfBig->MultiCell(180, 0, '','T', 'L', 0, 0);
			
			$pdfBig->SetFont($arialNarrowB, '', 8, '', false); 
			$pdfBig->SetXY(15, 192); 
			$pdfBig->Cell(180, 0, 'GRAND TOTAL MARKS SECURED '.$this->getNumberWords($GrandTotal_CT_Sec), 'LRB', $ln=0, 'L', 0, '', 0, false, 'C', 'C');
			
			$pdfBig->SetXY(15, 195); 
			$pdfBig->MultiCell(180, 0, 'SEMESTER RESULTS', 0, 'C', 0, 0);
			$pdfBig->SetXY(15, 213); 
			$pdfBig->MultiCell(180, 0, 'PROGRAM RESULTS', 0, 'C', 0, 0);			
			$pdfBig->setCellPaddings( $left = '', $top = '0.8', $right = '', $bottom = '');	
			$pdfBig->SetXY(15, 199.5); 
			$pdfBig->MultiCell(36, 6, 'SGPA', 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, 'Alpha-Sign Grade', 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, 'Credits Earned', 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, 'Semester % age of Marks', 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, 'Class Description', 1, 'C', 0, 0);
			$pdfBig->SetXY(15, 205.5); 
			$pdfBig->MultiCell(36, 6, $SR_SGPA, 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, $SR_Alpha_signed_Grade, 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, $SR_Credits_Earned, 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, $SR_semester_percentage_Of_Marks, 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, $SR_Class_description, 1, 'C', 0, 0);
			$pdfBig->SetXY(15, 217.5); 
			$pdfBig->MultiCell(36, 6, 'CGPA', 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, 'Alpha-Sign Grade', 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, 'Credits Earned', 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, 'Semester % age of Marks', 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, 'Class Description', 1, 'C', 0, 0);
			$pdfBig->SetXY(15, 223.5); 
			$pdfBig->MultiCell(36, 6, $PR_SGPA, 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, $PR_Alpha_signed_Grade, 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, $PR_Credits_Earned, 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, $PR_semester_percentage_Of_Marks, 1, 'C', 0, 0);
			$pdfBig->MultiCell(36, 6, $PR_Class_description, 1, 'C', 0, 0);
			
			$pdfBig->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
			$pdfBig->SetFont($arialNarrowB, '', 7.7, '', false); 
			$pdfBig->SetXY(14, 231); 
			$pdfBig->MultiCell(180, 0, '* See Overleaf, MINIMUM FOR PASS IS 35% IN THEORY/PRACTICAL EXAMINATION AND 40% IN AGGREGATE INCLUDING INTERNAL ASSESSMENT IN EACH PAPER.', 0, 'L', 0, 0);	
            
			$pdfBig->SetXY(14, 242);
			$pdfBig->MultiCell(180, 0, 'Date: '.$Date, 0, 'L', 0, 0);
			
			$pdfBig->SetTextColor(192,192,192);
			$pdfBig->SetFont($arialb, '', 10, '', false);
			$pdfBig->SetXY(14, 236);
			$pdfBig->Cell(180, 0, $arrayEnrollment, 0, false, 'L');			
			$pdfBig->SetTextColor(0,0,0);
			//end pdfBig
			//start pdf
			$pdf->SetFont($arialNarrowB, '', 8, '', false); 
            $pdf->SetXY(15, 186);
			$pdf->MultiCell(5, 11, '', 0, 'L', 0, 0);
			$pdf->MultiCell(62, 11, 'GRAND TOTAL',0, 'R', 0, 0);
			$pdf->MultiCell(8, 11, '',0, 'C', 0, 0);
			$pdf->MultiCell(6, 11, '',0, 'C', 0, 0);
			$pdf->MultiCell(12.5, 11, '',0, 'C', 0, 0);				
			$pdf->MultiCell(8, 11, '',0, 'C', 0, 0);
			$pdf->MultiCell(12.5, 11, '',0, 'C', 0, 0);				
			$pdf->MultiCell(8, 11, $GrandTotal_CT_Max,0, 'C', 0, 0);
			$pdf->MultiCell(6, 11, $GrandTotal_CT_Min,0, 'C', 0, 0);
			$pdf->MultiCell(13, 11, $GrandTotal_CT_Sec,0, 'C', 0, 0);				
			$pdf->MultiCell(7, 11, $GrandTotal_GP,0, 'C', 0, 0);
			$pdf->MultiCell(6, 11, $GrandTotal_CA,0, 'C', 0, 0);
			$pdf->MultiCell(7, 11, $GrandTotal_CP,0, 'C', 0, 0);
			$pdf->MultiCell(19, 11, '',0, 'C', 0, 0); 
			
			$pdf->SetXY(15, 185.5);     
            $pdf->MultiCell(180, 0, '','T', 'L', 0, 0);
			
			$pdf->SetFont($arialNarrowB, '', 8, '', false); 
			$pdf->SetXY(15, 192); 
			$pdf->Cell(180, 0, 'GRAND TOTAL MARKS SECURED '.$this->getNumberWords($GrandTotal_CT_Sec), 'LRB', $ln=0, 'L', 0, '', 0, false, 'C', 'C');
			
			$pdf->SetXY(15, 195); 
			$pdf->MultiCell(180, 0, 'SEMESTER RESULTS', 0, 'C', 0, 0);
			$pdf->SetXY(15, 213); 
			$pdf->MultiCell(180, 0, 'PROGRAM RESULTS', 0, 'C', 0, 0);			
			$pdf->setCellPaddings( $left = '', $top = '0.8', $right = '', $bottom = '');	
			$pdf->SetXY(15, 199.5); 
			$pdf->MultiCell(36, 6, 'SGPA', 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, 'Alpha-Sign Grade', 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, 'Credits Earned', 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, 'Semester % age of Marks', 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, 'Class Description', 1, 'C', 0, 0);
			$pdf->SetXY(15, 205.5); 
			$pdf->MultiCell(36, 6, $SR_SGPA, 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, $SR_Alpha_signed_Grade, 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, $SR_Credits_Earned, 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, $SR_semester_percentage_Of_Marks, 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, $SR_Class_description, 1, 'C', 0, 0);
			$pdf->SetXY(15, 217.5); 
			$pdf->MultiCell(36, 6, 'CGPA', 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, 'Alpha-Sign Grade', 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, 'Credits Earned', 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, 'Semester % age of Marks', 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, 'Class Description', 1, 'C', 0, 0);
			$pdf->SetXY(15, 223.5); 
			$pdf->MultiCell(36, 6, $PR_SGPA, 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, $PR_Alpha_signed_Grade, 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, $PR_Credits_Earned, 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, $PR_semester_percentage_Of_Marks, 1, 'C', 0, 0);
			$pdf->MultiCell(36, 6, $PR_Class_description, 1, 'C', 0, 0);
			
			$pdf->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
			$pdf->SetFont($arialNarrowB, '', 7.7, '', false); 
			$pdf->SetXY(14, 231); 
			$pdf->MultiCell(180, 0, '* See Overleaf, MINIMUM FOR PASS IS 35% IN THEORY/PRACTICAL EXAMINATION AND 40% IN AGGREGATE INCLUDING INTERNAL ASSESSMENT IN EACH PAPER.', 0, 'L', 0, 0);	
            
			$pdf->SetXY(14, 242);
			$pdf->MultiCell(180, 0, 'Date: '.$Date, 0, 'L', 0, 0);	
			
			$pdf->SetTextColor(192,192,192);
			$pdf->SetFont($arialb, '', 10, '', false);
			$pdf->SetXY(14, 236);
			$pdf->Cell(180, 0, $arrayEnrollment, 0, false, 'L');			
			$pdf->SetTextColor(0,0,0);              
            //end pdf
            
            /*$VerifiedBy = public_path().'\\'.$subdomain[0].'\backend\canvas\images\VerifiedBy.png';
            $uvsign_x = 66; 
            $uvsign_y = 253; 
            $uvsign_Width = 22; 
            $uvsign_Height = 16; 
            //$pdf->image($VerifiedBy,$uvsign_x,$uvsign_y,$uvsign_Width,$uvsign_Height,"",'','L',true,3600);          
            //$pdfBig->image($VerifiedBy,$uvsign_x,$uvsign_y,$uvsign_Width,$uvsign_Height,"",'','L',true,3600);
            //$pdfBig->setPageMark(); 
            $pdfBig->SetXY(118, 258);
            $pdfBig->MultiCell(42, 2, '', 'T', 'L', 0, 0);
            $pdfBig->SetXY(129, 259);
            $pdfBig->SetFont($arialb, '', 10, '', false);
            $pdfBig->MultiCell(25, 5, 'Principal', 0, 'L', 0, 0);

            $COE = public_path().'\\'.$subdomain[0].'\backend\canvas\images\COE.png';
            $COE_x = 120;
            $COE_y = 253;
            $COE_Width = 26;
            $COE_Height = 16;
            //$pdf->image($COE,$COE_x,$COE_y,$COE_Width,$COE_Height,"",'','L',true,3600);   
            //$pdfBig->image($COE,$COE_x,$COE_y,$COE_Width,$COE_Height,"",'','L',true,3600);
            //$pdfBig->setPageMark(); 
            $pdfBig->SetXY(45, 258);
            $pdfBig->MultiCell(50, 2, '', 'T', 'L', 0, 0);
            $pdfBig->SetXY(46.5, 259);
            $pdfBig->SetFont($arialb, '', 10, '', false);
            $pdfBig->MultiCell(55, 5, 'Controller of Examinations', 0, 'L', 0, 0); */			
			
			// Ghost image 
            $nameOrg=$studentData[1];
            $ghost_font_size = '13';
            $ghostImagex = 45;
            $ghostImagey = 264;
            //$ghostImageWidth = 55;//68
            $ghostImageHeight = 9.8;
            $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
            $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
            $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
            $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
            
			/*$ghost_font_size = '12';
            $ghostImagex = 45;
            $ghostImagey = 264;
            $ghostImageWidth = 39.405983333;
            $ghostImageHeight = 8;
            $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
            $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
			$pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
            $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);*/
			$pdf->setPageMark(); 
            $pdfBig->setPageMark(); 
			$serial_no=$GUID=$studentData[0];
            //qr code    
            $dt = date("_ymdHis");
            $str=$GUID.$dt;
            
			$encryptedString = strtoupper(md5($str));
			$codeContents = "Name: ".$candidate_name."\nRegistration No.: ".$Registration_No."\n\n".$encryptedString;
			
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 16.5;
            $qrCodey = 255.5;
            $qrCodeWidth =19;
            $qrCodeHeight = 19;
            /*\QrCode::backgroundColor(255, 255, 0)            
                ->format('png')        
                ->size(500)    
                ->generate($codeContents, $qr_code_path);*/
            $ecc = 'L';
            $pixel_Size = 1;
            $frame_Size = 1;  
            \PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);   
            $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);       
            $pdf->setPageMark(); 
            $pdfBig->setPageMark(); 
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
                'text' => true,
                'font' => 'helvetica',
                'fontsize' => 8,
                'stretchtext' => 4
            );              
            
            $barcodex = 116;
            $barcodey = 263;
            $barcodeWidth = 47;
            $barodeHeight = 13;
            $pdf->SetAlpha(1);
            $pdf->write1DBarcode($print_serial_no, 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');
            $pdfBig->SetAlpha(1);
            $pdfBig->write1DBarcode($print_serial_no, 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');
            
            $str = $nameOrg;
            $str = strtoupper(preg_replace('/\s+/', '', $str)); 
            
            $microlinestr=$str;
            $pdf->SetFont($arialb, '', 2, '', false);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(16.5, 253.5);        
            $pdf->Cell(19, 0, $microlinestr, 0, false, 'C');    
                
            $pdfBig->SetFont($arialb, '', 2, '', false);
            $pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY(16.5, 253.5);        
            $pdfBig->Cell(19, 0, $microlinestr, 0, false, 'C'); 

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

                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'BcomGC',$admin_id,$card_serial_no);

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
        $this->updateCardNo('BcomGC',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
       }
       $msg = '';
        
        $file_name =  str_replace("/", "_",'BcomGC'.date("Ymdhms")).'.pdf';
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


       $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        if($previewPdf!=1){
     

            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="BcomGC";
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
		$output=\Config::get('constant.directoryPathBackward')."\\".$subdomain[0]."\\backend\\pdf_file\\".$certName; 
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
        
        $result = StudentTable::create(['serial_no'=>''.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>2]);
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

	function getNumberWords(float $number)
	{
		$decimal = round($number - ($no = floor($number)), 2) * 100;
		$hundred = null;
		$digits_length = strlen($no);
		$i = 0;
		$str = array();
		$words = array(0 => '', 1 => 'one', 2 => 'two',
			3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
			7 => 'seven', 8 => 'eight', 9 => 'nine',
			10 => 'ten', 11 => 'eleven', 12 => 'twelve',
			13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
			16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
			19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
			40 => 'forty', 50 => 'fifty', 60 => 'sixty',
			70 => 'seventy', 80 => 'eighty', 90 => 'ninety');
		$digits = array('', 'hundred','thousand','lakh', 'crore');
		while( $i < $digits_length ) {
			$divider = ($i == 2) ? 10 : 100;
			$number = floor($no % $divider);
			$no = floor($no / $divider);
			$i += $divider == 10 ? 1 : 2;
			if ($number) {
				$plural = (($counter = count($str)) && $number > 9) ? 's' : null;
				$hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
				$str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
			} else $str[] = null;
		}
		$NumberWords = implode('', array_reverse($str));
		$result=($NumberWords ? $NumberWords : '');
		return strtoupper($result);
	}	
  
}
