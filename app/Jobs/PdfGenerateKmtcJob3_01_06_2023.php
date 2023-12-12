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
class PdfGenerateKmtcJob3
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
        $yearOne=$pdf_data['yearOne'];
        $yearTwo=$pdf_data['yearTwo'];
        $yearThree=$pdf_data['yearThree'];
        $FQE=$pdf_data['FQE'];
        $template_id=$pdf_data['template_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];
        //$photo_col=155;        
        
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
        if($previewPdf==1){
            if($previewWithoutBg!=1){
                $show_bg='Yes';
            }else{
                $show_bg='No';
            }
        }
          
        //$pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
        $pdfBig = new PDF_MC_Table('P', 'mm', array('210', '297'), true, 'UTF-8', false);
        $pdfBig->SetCreator(PDF_CREATOR);
        $pdfBig->SetAuthor('TCPDF');
        $pdfBig->SetTitle('Certificate');
        $pdfBig->SetSubject('');

        // remove default header/footer
        //$pdfBig->setPrintHeader(false);
        //$pdfBig->setPrintFooter(false);        
        //$pdfBig->setHtmlHeader('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="3">&nbsp;</td></tr><tr><td width="47%">&nbsp;</td><td width="48%" align="right">KMTC/QP-08/ATR</td><td width="3%"></td></tr></table>', $subdomain[0], $show_bg);
        //$pdfBig->setHtmlFooter('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td width="2%"></td><td width="48%">For: CHIEF EXECUTIVE OFFICER</td><td width="48%" align="right">Serial Number: </td><td width="2%"></td></tr></table>');
        //$pdfBig->SetAutoPageBreak(true, 22); 
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
        $cnt=0;
        foreach ($studentDataOrg as $studentData) {
            $high_res_bg="KMTC_seqr_blank.jpg"; //KMTC_seqr.jpg
            $low_res_bg="KMTC_seqr_blank.jpg";             
            $pdfBig->setHtmlHeader('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="3">&nbsp;</td></tr><tr><td width="2%"></td><td width="47%">Serial Number: '.$studentData[10].'</td><td width="48%" align="right">KMTC/QP-08/ATR</td><td width="3%"></td></tr></table>', $subdomain[0], $show_bg);
            $pdfBig->setHtmlFooter('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="4">&nbsp;</td></tr><tr><td width="2%"></td><td width="48%">For: REGISTRAR</td><td width="48%" align="right"></td><td width="2%"></td></tr></table>');
            $pdfBig->SetAutoPageBreak(true, 30);          
            $cnt+=1;
            $pdfBig->AddPage();
            $pdfBig->SetFont($arialNarrowB, '', 8, '', false);
            
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$high_res_bg;
            if($previewPdf==1){
                if($previewWithoutBg!=1){
                    //$pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
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
            //$pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);   
            $pdf = new PDF_MC_Table('P', 'mm', array('210', '297'), true, 'UTF-8', false); 
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('TCPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');

            // remove default header/footer
            //$pdf->setPrintHeader(false);
            //$pdf->setPrintFooter(false);        
            $pdf->setHtmlHeader('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td colspan="4">&nbsp;</td></tr><tr><td colspan="3">&nbsp;</td></tr><tr><td width="2%"></td><td width="47%">Serial Number: '.$studentData[10].'</td><td width="48%" align="right">KMTC/QP-08/ATR</td><td width="3%"></td></tr></table>', $subdomain[0], 'Yes');
            $pdf->setHtmlFooter('<table cellspacing="0" cellpadding="1" border="0" width="100%"><tr><td width="2%"></td><td width="48%">For: REGISTRAR</td><td width="48%" align="right"></td><td width="2%"></td></tr></table>');
            $pdf->SetAutoPageBreak(true, 30);            
            $pdf->AddPage();        
            $print_serial_no = $this->nextPrintSerial();
            
            //path of photos
            /*$profile_path_org = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[155];
            $path_info = pathinfo($profile_path_org);                            
            $file_name = $path_info['filename'];
            $ext = $path_info['extension'];
            $uv_location = public_path()."/".$subdomain[0]."/backend/templates/100/".$file_name.'_uv.'.$ext;
            
            if(!file_exists($uv_location)){  
            copy($profile_path_org, $uv_location);
            }*/
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$low_res_bg;
            if($previewPdf!=1){
                //$pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
            }
            $pdf->setPageMark();       
            
            $title1_x = 14.3;        
            $title1_colonx = 47.7;        
            $left_title1_y = 65;        
            $left_title2_y = 71;
            $left_title3_y = 77;
            $left_title4_y = 84;
            $left_title5_y = 91;
            $left_str_x = 52.7;
            $title2_x = 113.3;
            $title2_colonx = 142.3;
            $right_str_x = 147;
            $title_font_size = '11';        
            $str_font_size = '11';
            $xval=13; //Table x position    
            
            $UniqueSerialNo = $studentData[0];
            $candidate_name = $studentData[1];
            $college_no = $studentData[2];
            $programme_name = $studentData[3]; 
            $faculty = $studentData[4];
            $department = $studentData[5];        
            $completion = $studentData[6];
            $duration = $studentData[7];
            $admission = $studentData[8];
            $graduation = $studentData[9];
            $SerialNo = $studentData[10];
            $issue_date = $studentData[11];
            $SerialNoStr = "Serial Number: ".$studentData[10];
            $CEO="For: REGISTRAR";
            
            //Start pdf
            //Static Title
            $pdf->SetFont($arialb, '', 13, '', false); 
            
            $serial_no=$GUID=$studentData[0];
            //qr code    
            $dt = date("_ymdHis");
            $str=$GUID.$dt;
            $codeContents =$encryptedString = strtoupper(md5($str));
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 13; //175
            $qrCodey = 12;
            $qrCodeWidth =23;
            $qrCodeHeight = 23;
            \QrCode::backgroundColor(255, 255, 0)            
                ->format('png')        
                ->size(500)    
                ->generate($codeContents, $qr_code_path);

            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);        
            $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
            $pdf->setPageMark();        
            $pdfBig->setPageMark();        
            
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            //$pdf->SetXY(160, 20);
            //$pdf->Cell(0, 0, 'KMTC/QP-08/ATR', 0, false, 'L'); 
            $pdf->SetXY(160, 27);
            $pdf->Cell(0, 0, $issue_date, 0, false, 'L');    
                    
            $pdf->SetFont($arialb, '', $title_font_size, '', false);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($title1_x, $left_title1_y);
            $pdf->Cell(0, 0, 'Name:', 0, false, 'L');
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            $pdf->SetXY(28, $left_title1_y);
            $pdf->Cell(0, 0, $candidate_name, 0, false, 'L');        
            
            $prog_numlines_pdf = $pdf->getNumLines($programme_name, 160);
            $pdf->SetFont($arialb, '', $title_font_size, '', false);
            $pdf->SetXY($title1_x, $left_title2_y);
            $pdf->Cell(0, 0, "Programme:", 0, false, 'L');
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            if($prog_numlines_pdf>1){
                $pdf->SetFont($arial, '', 10, '', false); 
            }        
            $pdf->SetXY(38, $left_title2_y);
            $pdf->MultiCell(160, 10, $programme_name,0, 'L', 0, 0);         
            
            $pdf->SetFont($arialb, '', $title_font_size, '', false);
            $pdf->SetXY($title1_x, $left_title3_y);
            $pdf->Cell(0, 0, "Faculty:", 0, false, 'L');
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            $pdf->SetXY(30, $left_title3_y);
            $pdf->Cell(0, 0, $faculty, 0, false, 'L');
            
            $pdf->SetFont($arialb, '', $title_font_size, '', false);
            $pdf->SetXY($title1_x, $left_title4_y);
            $pdf->Cell(0, 0, "Department:", 0, false, 'L');
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            $pdf->SetXY(38, $left_title4_y);
            $pdf->Cell(0, 0, $department, 0, false, 'L');
            
            $pdf->SetFont($arialb, '', $title_font_size, '', false);
            $pdf->SetXY($title1_x, $left_title5_y);
            $pdf->Cell(0, 0, "Completion:", 0, false, 'L');
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            $pdf->SetXY(38, $left_title5_y);
            $pdf->Cell(0, 0, $completion, 0, false, 'L');
                
            
            $pdf->SetFont($arialb, '', $title_font_size, '', false);
            $pdf->SetXY($title2_x, $left_title1_y);
            $pdf->Cell(0, 0, 'College Number:', 0, false, 'L');
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            $pdf->SetXY(146, $left_title1_y);
            $pdf->Cell(0, 0, $college_no, 0, false, 'L');
                    
            $pdf->SetFont($arialb, '', $title_font_size, '', false);
            $pdf->SetXY($title2_x, $left_title3_y);
            $pdf->Cell(0, 0, 'Duration:', 0, false, 'L');
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            $pdf->SetXY(131, $left_title3_y);
            $pdf->Cell(0, 0, $duration, 0, false, 'L');
            
            $pdf->SetFont($arialb, '', $title_font_size, '', false);
            $pdf->SetXY($title2_x, $left_title4_y);
            $pdf->Cell(0, 0, 'Admission:', 0, false, 'L');
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            $pdf->SetXY(136, $left_title4_y);
            $pdf->Cell(0, 0, $admission, 0, false, 'L');
            
            $pdf->SetFont($arialb, '', $title_font_size, '', false);
            $pdf->SetXY($title2_x, $left_title5_y);
            $pdf->Cell(0, 0, 'Graduation:', 0, false, 'L');
            $pdf->SetFont($arial, '', $title_font_size, '', false);
            $pdf->SetXY(136, $left_title5_y);
            $pdf->Cell(0, 0, $graduation, 0, false, 'L');        
            
            //End pdf
            
            //Start pdfBig
            //Static Title
            $pdfBig->SetFont($arial, '', 13, '', false); 
            
            // start invisible data
            //$pdfBig->SetTextColor(255, 255, 0);        
            //$pdfBig->SetXY($title1_x, $left_title1_y-7);
            //$pdfBig->Cell(0, 0, $enrollment_no, 0, false, 'L');
            // end invisible data
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            //$pdfBig->SetXY(160, 20);
            //$pdfBig->Cell(0, 0, 'KMTC/QP-08/ATR', 0, false, 'L');  
            $pdfBig->SetXY(160, 27);
            $pdfBig->Cell(0, 0, $issue_date, 0, false, 'L');
            
            
            
            $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
            $pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY($title1_x, $left_title1_y);
            $pdfBig->Cell(0, 0, 'Name:', 0, false, 'L');
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            $pdfBig->SetXY(28, $left_title1_y);
            $pdfBig->Cell(0, 0, $candidate_name, 0, false, 'L');        
            
            $prog_numlines_pdfBig = $pdfBig->getNumLines($programme_name, 160);
            $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
            $pdfBig->SetXY($title1_x, $left_title2_y);
            $pdfBig->Cell(0, 0, "Programme:", 0, false, 'L');
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            if($prog_numlines_pdfBig>1){
                $pdfBig->SetFont($arial, '', 10, '', false); 
            }        
            $pdfBig->SetXY(38, $left_title2_y);
            $pdfBig->MultiCell(160, 10, $programme_name,0, 'L', 0, 0);         
            
            $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
            $pdfBig->SetXY($title1_x, $left_title3_y);
            $pdfBig->Cell(0, 0, "Faculty:", 0, false, 'L');
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            $pdfBig->SetXY(30, $left_title3_y);
            $pdfBig->Cell(0, 0, $faculty, 0, false, 'L');
            
            $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
            $pdfBig->SetXY($title1_x, $left_title4_y);
            $pdfBig->Cell(0, 0, "Department:", 0, false, 'L');
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            $pdfBig->SetXY(38, $left_title4_y);
            $pdfBig->Cell(0, 0, $department, 0, false, 'L');
            
            $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
            $pdfBig->SetXY($title1_x, $left_title5_y);
            $pdfBig->Cell(0, 0, "Completion:", 0, false, 'L');
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            $pdfBig->SetXY(38, $left_title5_y);
            $pdfBig->Cell(0, 0, $completion, 0, false, 'L');            
            
            $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
            $pdfBig->SetXY($title2_x, $left_title1_y);
            $pdfBig->Cell(0, 0, 'College Number:', 0, false, 'L');
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            $pdfBig->SetXY(146, $left_title1_y);
            $pdfBig->Cell(0, 0, $college_no, 0, false, 'L');
                    
            $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
            $pdfBig->SetXY($title2_x, $left_title3_y);
            $pdfBig->Cell(0, 0, 'Duration:', 0, false, 'L');
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            $pdfBig->SetXY(131, $left_title3_y);
            $pdfBig->Cell(0, 0, $duration, 0, false, 'L');
            
            $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
            $pdfBig->SetXY($title2_x, $left_title4_y);
            $pdfBig->Cell(0, 0, 'Admission:', 0, false, 'L');
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            $pdfBig->SetXY(136, $left_title4_y);
            $pdfBig->Cell(0, 0, $admission, 0, false, 'L');
            
            $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
            $pdfBig->SetXY($title2_x, $left_title5_y);
            $pdfBig->Cell(0, 0, 'Graduation:', 0, false, 'L');
            $pdfBig->SetFont($arial, '', $title_font_size, '', false);
            $pdfBig->SetXY(136, $left_title5_y);
            $pdfBig->Cell(0, 0, $graduation, 0, false, 'L');        
            //End pdfBig 
            
            $pdf->SetFont($arial, '', 12, '', false);
            $pdfBig->SetFont($arial, '', 12, '', false);
            
            //Year 1 Sem 1
            $y1_sem1_year_title=$yearOne[$generated_documents][1].": ".$yearOne[$generated_documents][2];
            $y1_sem1_acadmic_year="Academic Year ".$yearOne[$generated_documents][3];
            $y1_sem2_year_title=$yearOne[$generated_documents][94].": ".$yearOne[$generated_documents][95];
            $y1_sem2_acadmic_year="Academic Year ".$yearOne[$generated_documents][96];
            $y1_current="Current: ".$yearOne[$generated_documents][187];
            $y1_cumulative="Cumulative: ".$yearOne[$generated_documents][188];
            $y1_sem1_subjectData = array_slice($yearOne[$generated_documents], 4, 90);
            $y1_sem1_subjectsArr=array_chunk($y1_sem1_subjectData, 6);
            $y1_sem1_count_upto=count($y1_sem1_subjectsArr);        
            //Count total subjects entered
            $y1_sem1_actual_subj_count=0;
            for ($z=0; $z < $y1_sem1_count_upto; $z++) {
                if(!empty($y1_sem1_subjectsArr[$z][0])){
                    $y1_sem1_actual_subj_count=$y1_sem1_actual_subj_count+1;
                }
            }  
            $y1_sem2_subjectData = array_slice($yearOne[$generated_documents], 97, 90);        
            $y1_sem2_subjectsArr=array_chunk($y1_sem2_subjectData, 6);
            $y1_sem2_count_upto=count($y1_sem2_subjectsArr);        
            //Count total subjects entered
            $y1_sem2_actual_subj_count=0;
            for ($z=0; $z < $y1_sem2_count_upto; $z++) {
                if(!empty($y1_sem2_subjectsArr[$z][0])){
                    $y1_sem2_actual_subj_count=$y1_sem2_actual_subj_count+1;
                }
            }                  
            if($y1_sem1_actual_subj_count > 0){
                
                $pdf->SetWidths(array(70,70)); 
                $pdfBig->SetWidths(array(70,70));
                
                $pdf->SetFont($arialb, '', $title_font_size, '', false);
                $pdf->Row(array('', ''), array('L','L'), 15, '', 0);
                $pdf->Row(array('', ''), array('L','L'), 15, '', 0);
                $pdf->Row(array($y1_sem1_year_title, $y1_sem1_acadmic_year), array('L','L'), 15, '', 0);  
                
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
                $pdfBig->Row(array('', ''), array('L','L'), 15, '', 0);
                $pdfBig->Row(array('', ''), array('L','L'), 15, '', 0);
                $pdfBig->Row(array($y1_sem1_year_title, $y1_sem1_acadmic_year), array('L','L'), 15, '', 0);  
                        
                $pdf->SetWidths(array(23,81,20,20,20,20)); 
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                //Table's Titles
                $pdf->SetFont($arialb, '', 11, '', false); 
                $pdf->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval, '', 1);    
                
                $pdfBig->SetFont($arialb, '', 11, '', false);                
                $pdfBig->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval, '', 1);  
                
                $pdf->SetWidths(array(23,81,20,20,20,20));
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                srand(microtime()*1000000);
                for ($i=0; $i < $y1_sem1_actual_subj_count; $i++) {            
                    $cell_border='LTRB'; 
                    $numlines_pdf = $pdf->getNumLines($y1_sem1_subjectsArr[$i][1], 81);
                    $numlines_pdfBig = $pdfBig->getNumLines($y1_sem1_subjectsArr[$i][1], 81);
                    
                    if($numlines_pdf>1){ $subject_length='long'; }else{$subject_length='';}
                    $pdf->SetFont($arial, '', 10, '', false); 
                    $pdf->Row(array(trim($y1_sem1_subjectsArr[$i][0]), trim($y1_sem1_subjectsArr[$i][1]), trim($y1_sem1_subjectsArr[$i][2]), trim($y1_sem1_subjectsArr[$i][3]), trim($y1_sem1_subjectsArr[$i][4]), trim($y1_sem1_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, '', 1);                                
                    
                    if($numlines_pdfBig>1){ $subject_length='long'; }else{$subject_length='';}
                    $pdfBig->SetFont($arial, '', 10, '', false); 
                    $pdfBig->Row(array(trim($y1_sem1_subjectsArr[$i][0]), trim($y1_sem1_subjectsArr[$i][1]), trim($y1_sem1_subjectsArr[$i][2]), trim($y1_sem1_subjectsArr[$i][3]), trim($y1_sem1_subjectsArr[$i][4]), trim($y1_sem1_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                }
            }    
            //Year 1 Sem 2                  
            
            if($y1_sem2_actual_subj_count > 0){
                $pdf->SetWidths(array(70,70)); 
                $pdfBig->SetWidths(array(70,70));
                
                $pdf->SetFont($arialb, '', $title_font_size, '', false);
                $pdf->Row(array('', ''), array('L','L'), 15, '', 0);
                $pdf->Row(array($y1_sem2_year_title, $y1_sem2_acadmic_year), array('L','L'), 15, '', 0);  
                
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
                $pdfBig->Row(array('', ''), array('L','L'), 15, '', 0);
                $pdfBig->Row(array($y1_sem2_year_title, $y1_sem2_acadmic_year), array('L','L'), 15, '', 0);  
                        
                $pdf->SetWidths(array(23,81,20,20,20,20)); 
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                //Table's Titles
                $pdf->SetFont($arialb, '', 11, '', false); 
                $pdf->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval, '', 1);    
                
                $pdfBig->SetFont($arialb, '', 11, '', false);                
                $pdfBig->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval, '', 1);  
                
                $pdf->SetWidths(array(23,81,20,20,20,20));
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                //srand(microtime()*1000000);
                for ($i=0; $i < $y1_sem2_actual_subj_count ; $i++) {            
                    $numlines_pdf = $pdf->getNumLines($y1_sem2_subjectsArr[$i][1], 81);
                    $numlines_pdfBig = $pdfBig->getNumLines($y1_sem2_subjectsArr[$i][1], 81);
                    $pdf->SetFont($arial, '', 10, '', false); 
                    if($numlines_pdf>1){ $subject_length='long'; }else{$subject_length='';}
                    $pdf->Row(array(trim($y1_sem2_subjectsArr[$i][0]), trim($y1_sem2_subjectsArr[$i][1]), trim($y1_sem2_subjectsArr[$i][2]), trim($y1_sem2_subjectsArr[$i][3]), trim($y1_sem2_subjectsArr[$i][4]), trim($y1_sem2_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                    
                    if($numlines_pdfBig>1){ $subject_length='long'; }else{$subject_length='';}    
                    $pdfBig->SetFont($arial, '', 10, '', false);
                    $pdfBig->Row(array(trim($y1_sem2_subjectsArr[$i][0]), trim($y1_sem2_subjectsArr[$i][1]), trim($y1_sem2_subjectsArr[$i][2]), trim($y1_sem2_subjectsArr[$i][3]), trim($y1_sem2_subjectsArr[$i][4]), trim($y1_sem2_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                            
                }
                
                $pdf->SetWidths(array(35,35,35)); 
                $pdfBig->SetWidths(array(35,35,35));        
                $pdf->SetFont($arialb, '', $title_font_size, '', false); 
                $pdf->Row(array('Averages', $y1_current, $y1_cumulative), array('L','L','L'), $xval, '', 0);                 
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);  
                $pdfBig->Row(array('Averages', $y1_current, $y1_cumulative), array('L','L','L'), $xval, '', 0); 
            }
            
                    
            //Year 2 Sem 1
            $y2_sem1_year_title=$yearTwo[$generated_documents][1].": ".$yearTwo[$generated_documents][2];
            $y2_sem1_acadmic_year="Academic Year ".$yearTwo[$generated_documents][3];
            $y2_sem2_year_title=$yearTwo[$generated_documents][94].": ".$yearTwo[$generated_documents][95];
            $y2_sem2_acadmic_year="Academic Year ".$yearTwo[$generated_documents][96];
            $y2_current="Current: ".$yearTwo[$generated_documents][187];
            $y2_cumulative="Cumulative: ".$yearTwo[$generated_documents][188];
            //print_r("<br>".$y2_sem1_year_title."|".$y2_sem1_acadmic_year."|".$y2_sem2_year_title."|".$y2_sem2_acadmic_year);
            $y2_sem1_subjectData = array_slice($yearTwo[$generated_documents], 4, 90);
            $y2_sem1_subjectsArr=array_chunk($y2_sem1_subjectData, 6);
            $y2_sem1_count_upto=count($y2_sem1_subjectsArr);        
            //Count total subjects entered
            $y2_sem1_actual_subj_count=0;
            for ($z=0; $z < $y2_sem1_count_upto; $z++) {
                if(!empty($y2_sem1_subjectsArr[$z][0])){
                    $y2_sem1_actual_subj_count=$y2_sem1_actual_subj_count+1;
                }
            }        
            $y2_sem2_subjectData = array_slice($yearTwo[$generated_documents], 97, 90);        
            $y2_sem2_subjectsArr=array_chunk($y2_sem2_subjectData, 6);
            $y2_sem2_count_upto=count($y2_sem2_subjectsArr);        
            //Count total subjects entered
            $y2_sem2_actual_subj_count=0;
            for ($z=0; $z < $y2_sem2_count_upto; $z++) {
                if(!empty($y2_sem2_subjectsArr[$z][0])){
                    $y2_sem2_actual_subj_count=$y2_sem2_actual_subj_count+1;
                }
            }            
            if($y2_sem1_actual_subj_count > 0){
                $pdf->SetWidths(array(70,70)); 
                $pdfBig->SetWidths(array(70,70));
                
                $pdf->SetFont($arialb, '', $title_font_size, '', false);
                $pdf->Row(array('', ''), array('L','L'), 15,'', 0);
                $pdf->Row(array($y2_sem1_year_title, $y2_sem1_acadmic_year), array('L','L'), 15,'', 0);  
                
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
                $pdfBig->Row(array('', ''), array('L','L'), 15,'', 0);
                $pdfBig->Row(array($y2_sem1_year_title, $y2_sem1_acadmic_year), array('L','L'), 15,'', 0); 
                
                $pdf->SetWidths(array(23,81,20,20,20,20)); 
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                //Table's Titles
                $pdf->SetFont($arialb, '', 11, '', false); 
                $pdf->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval,'', 1);    
                
                $pdfBig->SetFont($arialb, '', 11, '', false);                
                $pdfBig->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval,'', 1);  
                
                $pdf->SetWidths(array(23,81,20,20,20,20));
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                for ($i=0; $i < $y2_sem1_actual_subj_count ; $i++) {            
                    $cell_border='LTRB'; 
                    $numlines_pdf = $pdf->getNumLines($y2_sem1_subjectsArr[$i][1], 81);
                    $numlines_pdfBig = $pdfBig->getNumLines($y2_sem1_subjectsArr[$i][1], 81);
                    
                    if($numlines_pdf>1){ $subject_length='long'; }else{$subject_length='';}
                    $pdf->SetFont($arial, '', 10, '', false); 
                    $pdf->Row(array(trim($y2_sem1_subjectsArr[$i][0]), trim($y2_sem1_subjectsArr[$i][1]), trim($y2_sem1_subjectsArr[$i][2]), trim($y2_sem1_subjectsArr[$i][3]), trim($y2_sem1_subjectsArr[$i][4]), trim($y2_sem1_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                    
                    if($numlines_pdfBig>1){ $subject_length='long'; }else{$subject_length='';}
                    $pdfBig->SetFont($arial, '', 10, '', false);            
                    $pdfBig->Row(array(trim($y2_sem1_subjectsArr[$i][0]), trim($y2_sem1_subjectsArr[$i][1]), trim($y2_sem1_subjectsArr[$i][2]), trim($y2_sem1_subjectsArr[$i][3]), trim($y2_sem1_subjectsArr[$i][4]), trim($y2_sem1_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                        
                }
            }
            //Year 2 Sem 2            
            
            if($y2_sem2_actual_subj_count > 0){
                $pdf->SetWidths(array(70,70)); 
                $pdfBig->SetWidths(array(70,70));
                
                $pdf->SetFont($arialb, '', $title_font_size, '', false);
                $pdf->Row(array('', ''), array('L','L'), 15,'', 0);
                $pdf->Row(array($y2_sem2_year_title, $y2_sem2_acadmic_year), array('L','L'), 15,'', 0);  
                
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
                $pdfBig->Row(array('', ''), array('L','L'), 15,'', 0);
                $pdfBig->Row(array($y2_sem2_year_title, $y2_sem2_acadmic_year), array('L','L'), 15,'', 0);  
                        
                $pdf->SetWidths(array(23,81,20,20,20,20)); 
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                //Table's Titles
                $pdf->SetFont($arialb, '', 11, '', false); 
                $pdf->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval,'', 1);    
                
                $pdfBig->SetFont($arialb, '', 11, '', false);                
                $pdfBig->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval,'', 1);
                
                $pdf->SetWidths(array(23,81,20,20,20,20));
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                for ($i=0; $i < $y2_sem2_actual_subj_count ; $i++) {            
                    $numlines_pdf = $pdf->getNumLines($y2_sem2_subjectsArr[$i][1], 81);
                    $numlines_pdfBig = $pdfBig->getNumLines($y2_sem2_subjectsArr[$i][1], 81);      
                    if($numlines_pdf>1){ $subject_length='long'; }else{$subject_length='';}          
                    $pdf->SetFont($arial, '', 10, '', false); 
                    $pdf->Row(array(trim($y2_sem2_subjectsArr[$i][0]), trim($y2_sem2_subjectsArr[$i][1]), trim($y2_sem2_subjectsArr[$i][2]), trim($y2_sem2_subjectsArr[$i][3]), trim($y2_sem2_subjectsArr[$i][4]), trim($y2_sem2_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                                
                    if($numlines_pdfBig>1){ $subject_length='long'; }else{$subject_length='';}
                    $pdfBig->SetFont($arial, '', 10, '', false);            
                    $pdfBig->Row(array(trim($y2_sem2_subjectsArr[$i][0]), trim($y2_sem2_subjectsArr[$i][1]), trim($y2_sem2_subjectsArr[$i][2]), trim($y2_sem2_subjectsArr[$i][3]), trim($y2_sem2_subjectsArr[$i][4]), trim($y2_sem2_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                         
                }
                
                $pdf->SetWidths(array(35,35,35)); 
                $pdfBig->SetWidths(array(35,35,35));        
                $pdf->SetFont($arialb, '', $title_font_size, '', false);            
                $pdf->Row(array('Averages', $y2_current, $y1_cumulative), array('L','L','L'), $xval,'', 0);                 
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);        
                $pdfBig->Row(array('Averages', $y2_current, $y1_cumulative), array('L','L','L'), $xval,'', 0); 
            }
            
            //Year 3 Sem 1
            $y3_sem1_year_title=$yearThree[$generated_documents][1].": ".$yearThree[$generated_documents][2];
            $y3_sem1_acadmic_year="Academic Year ".$yearThree[$generated_documents][3];
            $y3_sem2_year_title=$yearThree[$generated_documents][94].": ".$yearThree[$generated_documents][95];
            $y3_sem2_acadmic_year="Academic Year ".$yearThree[$generated_documents][96];
            $y3_current="Current: ".$yearThree[$generated_documents][187];
            $y3_cumulative="Cumulative: ".$yearThree[$generated_documents][188];
            
            $y3_sem1_subjectData = array_slice($yearThree[$generated_documents], 4, 90);
            $y3_sem1_subjectsArr=array_chunk($y3_sem1_subjectData, 6);
            $y3_sem1_count_upto=count($y3_sem1_subjectsArr);        
            //Count total subjects entered
            $y3_sem1_actual_subj_count=0;
            for ($z=0; $z < $y3_sem1_count_upto; $z++) {
                if(!empty($y3_sem1_subjectsArr[$z][0])){
                    $y3_sem1_actual_subj_count=$y3_sem1_actual_subj_count+1;
                }
            }        
            $y3_sem2_subjectData = array_slice($yearThree[$generated_documents], 97, 90);        
            $y3_sem2_subjectsArr=array_chunk($y3_sem2_subjectData, 6);
            $y3_sem2_count_upto=count($y3_sem2_subjectsArr);        
            //Count total subjects entered
            $y3_sem2_actual_subj_count=0;
            for ($z=0; $z < $y3_sem2_count_upto; $z++) {
                if(!empty($y3_sem2_subjectsArr[$z][0])){
                    $y3_sem2_actual_subj_count=$y3_sem2_actual_subj_count+1;
                }
            }       
            
            if($y3_sem1_actual_subj_count > 0){
                $pdf->SetWidths(array(70,70)); 
                $pdfBig->SetWidths(array(70,70));
                
                $pdf->SetFont($arialb, '', $title_font_size, '', false);
                $pdf->Row(array('', ''), array('L','L'), 15,'', 0);
                $pdf->Row(array($y3_sem1_year_title, $y3_sem1_acadmic_year), array('L','L'), 15,'', 0);  
                
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
                $pdfBig->Row(array('', ''), array('L','L'), 15,'', 0);
                $pdfBig->Row(array($y3_sem1_year_title, $y3_sem1_acadmic_year), array('L','L'), 15,'', 0);  
                        
                //Table's Titles
                $pdf->SetWidths(array(23,81,20,20,20,20)); 
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                //1st 6 columns
                
                $pdf->SetFont($arialb, '', 11, '', false); 
                $pdf->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval,'', 1);    
                
                $pdfBig->SetFont($arialb, '', 11, '', false);                
                $pdfBig->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval,'', 1);

                $pdf->SetWidths(array(23,81,20,20,20,20));
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                for ($i=0; $i < $y3_sem1_actual_subj_count ; $i++) {            
                    $numlines_pdf = $pdf->getNumLines($y3_sem1_subjectsArr[$i][1], 81);
                    $numlines_pdfBig = $pdfBig->getNumLines($y3_sem1_subjectsArr[$i][1], 81);
                    
                    if($numlines_pdf>1){ $subject_length='long'; }else{$subject_length='';}
                    $pdf->SetFont($arial, '', 10, '', false); 
                    $pdf->Row(array(trim($y3_sem1_subjectsArr[$i][0]), trim($y3_sem1_subjectsArr[$i][1]), trim($y3_sem1_subjectsArr[$i][2]), trim($y3_sem1_subjectsArr[$i][3]), trim($y3_sem1_subjectsArr[$i][4]), trim($y3_sem1_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                    
                    if($numlines_pdfBig>1){ $subject_length='long'; }else{$subject_length='';}
                    $pdfBig->SetFont($arial, '', 10, '', false);                   
                    $pdfBig->Row(array(trim($y3_sem1_subjectsArr[$i][0]), trim($y3_sem1_subjectsArr[$i][1]), trim($y3_sem1_subjectsArr[$i][2]), trim($y3_sem1_subjectsArr[$i][3]), trim($y3_sem1_subjectsArr[$i][4]), trim($y3_sem1_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                        
                }
            }
            
            //Year 3 Sem 2
            if($y3_sem2_actual_subj_count > 0){
                $pdf->SetWidths(array(70,70)); 
                $pdfBig->SetWidths(array(70,70));
                
                $pdf->SetFont($arialb, '', $title_font_size, '', false);
                $pdf->Row(array('', ''), array('L','L'), 15,'', 0);
                $pdf->Row(array($y3_sem2_year_title, $y3_sem2_acadmic_year), array('L','L'), 15,'', 0);  
                
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
                $pdfBig->Row(array('', ''), array('L','L'), 15,'', 0);
                $pdfBig->Row(array($y3_sem2_year_title, $y3_sem2_acadmic_year), array('L','L'), 15,'', 0);  
                        
                //Table's Titles
                $pdf->SetWidths(array(23,81,20,20,20,20)); 
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                
                $pdf->SetFont($arialb, '', 11, '', false); 
                $pdf->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval,'', 1);    
                
                $pdfBig->SetFont($arialb, '', 11, '', false);                
                $pdfBig->Row(array('Code', 'Module Name', 'Hours', 'Credits', 'Score (%)','Grade'), array('L','L','C','C','C','C'), $xval,'', 1);
                
                $pdf->SetWidths(array(23,81,20,20,20,20));
                $pdfBig->SetWidths(array(23,81,20,20,20,20));
                for ($i=0; $i < $y3_sem2_actual_subj_count ; $i++) {            
                    $numlines_pdf = $pdf->getNumLines($y3_sem2_subjectsArr[$i][1], 81);
                    $numlines_pdfBig = $pdfBig->getNumLines($y3_sem2_subjectsArr[$i][1], 81);

                    if($numlines_pdf>1){ $subject_length='long'; }else{$subject_length='';}    
                    $pdf->SetFont($arial, '', 10, '', false); 
                    $pdf->Row(array(trim($y3_sem2_subjectsArr[$i][0]), trim($y3_sem2_subjectsArr[$i][1]), trim($y3_sem2_subjectsArr[$i][2]), trim($y3_sem2_subjectsArr[$i][3]), trim($y3_sem2_subjectsArr[$i][4]), trim($y3_sem2_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                                
                    if($numlines_pdfBig>1){ $subject_length='long'; }else{$subject_length='';}  
                    $pdfBig->SetFont($arial, '', 10, '', false); 
                    $pdfBig->Row(array(trim($y3_sem2_subjectsArr[$i][0]), trim($y3_sem2_subjectsArr[$i][1]), trim($y3_sem2_subjectsArr[$i][2]), trim($y3_sem2_subjectsArr[$i][3]), trim($y3_sem2_subjectsArr[$i][4]), trim($y3_sem2_subjectsArr[$i][5])), array('L','L','C','C','C','C'), $xval, $subject_length, 1);
                         
                }        
                $pdf->SetWidths(array(35,35,35)); 
                $pdfBig->SetWidths(array(35,35,35));        
                $pdf->SetFont($arialb, '', $title_font_size, '', false);            
                $pdf->Row(array('Averages', $y3_current, $y3_cumulative), array('L','L','L'), $xval,'', 0);                 
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);        
                $pdfBig->Row(array('Averages', $y3_current, $y3_cumulative), array('L','L','L'), $xval,'', 0); 
            }
            //FQE
            $fqe_subjectData = array_slice($FQE[$generated_documents], 1, 30);
            $fqe_subjectsArr=array_chunk($fqe_subjectData, 3);
            $fqe_count_upto=count($fqe_subjectsArr);   
            //Count total subjects entered
            $fqe_actual_subj_count=0;
            for ($z=0; $z < $fqe_count_upto; $z++) {
                if(!empty($fqe_subjectsArr[$z][0])){
                    $fqe_actual_subj_count=$fqe_actual_subj_count+1;
                }
            }   
            if($fqe_actual_subj_count > 0){    
                $pdf->SetWidths(array(180)); 
                $pdfBig->SetWidths(array(180));
                
                $pdf->SetFont($arialb, '', $title_font_size, '', false);
                $pdf->Row(array(''), array('L'), 15,'', 0);
                $pdf->Row(array("FINAL QUALIFYING EXAMINATION"), array('L'), 15,'', 0);  
                
                $pdfBig->SetFont($arialb, '', $title_font_size, '', false);
                $pdfBig->Row(array(''), array('L'), 15,'', 0);
                $pdfBig->Row(array("FINAL QUALIFYING EXAMINATION"), array('L'), 15,'', 0);         
                
                //Table's Titles
                $pdf->SetWidths(array(136,24,24)); 
                $pdfBig->SetWidths(array(136,24,24));
                
                $pdf->SetFont($arialb, '', 11, '', false); 
                $pdf->Row(array('Papers', 'Score (%)','Grade'), array('L','C','C'), $xval,'', 1);    
                
                $pdfBig->SetFont($arialb, '', 11, '', false);                
                $pdfBig->Row(array('Papers', 'Score (%)','Grade'), array('L','C','C'), $xval,'', 1);    
                
                $pdf->SetWidths(array(136,24,24)); 
                $pdfBig->SetWidths(array(136,24,24));
                for ($i=0; $i < $fqe_actual_subj_count ; $i++) {            
                    
                    $pdf->SetFont($arial, '', 10, '', false); 
                    $pdf->Row(array(trim($fqe_subjectsArr[$i][0]), trim($fqe_subjectsArr[$i][1]),trim($fqe_subjectsArr[$i][2])), array('L','C','C'), $xval,'', 1);   
                    
                    $pdfBig->SetFont($arial, '', 10, '', false);
                    $pdfBig->Row(array(trim($fqe_subjectsArr[$i][0]), trim($fqe_subjectsArr[$i][1]),trim($fqe_subjectsArr[$i][2])), array('L','C','C'), $xval,'', 1);
                           
                }        
            }
            
            
            // Ghost image
            /*
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
            */
            
            //1D Barcode
            /*$style1D = array(
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
            */
            /*
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
            */
            if($previewPdf!=1){

             $certName = str_replace("/", "_", $GUID) .".pdf";
                
                $myPath = public_path().'/backend/temp_pdf_file';

                $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;
                //$pdf->SetCompression(true);
                $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

                 $this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id);

                $username = $admin_id['username'];
                date_default_timezone_set('Asia/Kolkata');

                $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
                $date = date('Y-m-d H:i:s').PHP_EOL;
                $print_datetime = date("Y-m-d H:i:s");
                

                $print_count = $this->getPrintCount($serial_no);
                $printer_name = /*'HP 1020';*/$printer_name;

                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'Basic',$admin_id,$card_serial_no="");

                //$card_serial_no=$card_serial_no+1;
                }

                 $generated_documents++;

                  if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
                    $updated=date('Y-m-d H:i:s');
                    ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['generated_documents'=>$generated_documents,"updated_at"=>$updated]);
                }
                
                
        } 

       if($previewPdf!=1){
        //$this->updateCardNo('monad',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
       }
       $msg = '';
        
        $file_name =  str_replace("/", "_",'kmtc'.date("Ymdhms")).'.pdf';
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


       $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        if($previewPdf!=1){    

            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="kmtc";
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
        $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."' class='downloadpdf download' target='_blank'>Here</a> to download file<b>";       

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

        copy($file1, $file2);
        

        /***** Uploaded verification pdf to s3 bucket ******/
        if($subdomain[0]=='test'){
  
            //$pyscript = \Config::get('constant.pathCompressScript'); 
            $source=\Config::get('constant.directoryPathBackward')."\\backend\\pdf_file\\".$certName;//$file2
            $output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName;
            //$cmd = "$pyscript $source $output 2>&1";
            //exec($cmd, $output, $return);
            CoreHelper::compressPdfFile($source,$output);
           // print_r($output);

            //Upload file to s3 bucket
            $aws_qr = \Storage::disk('s3')->put('public/'.$subdomain[0].'/backend/pdf_file/'.$certName, file_get_contents($pdfActualPath), 'public');
            $filename1 = \Storage::disk('s3')->url($certName);

            //Unlink file from server 
            @unlink($pdfActualPath);
                 
        }else{
            //$aws_qr = \File::copy($file2,$pdfActualPath);
			//$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;
			$source=\Config::get('constant.directoryPathBackward')."\\backend\\pdf_file\\".$certName;//$file2
			$output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName; 
			CoreHelper::compressPdfFile($source,$output);
        }
        
        //file transfer to monad server
        //\Storage::disk('mftp')->put('pdf_file/'.$certName, $pdfActualPath);     
        //$this->testUpload('pdf_file/'.$certName, $pdfActualPath);         
            
        @unlink($file2);
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

class PDF_MC_Table extends TCPDF
{
    var $widths;
    var $aligns;

    function SetWidths($w)
    {
        //Set the array of column widths
        $this->widths=$w;
    }

    function SetAligns($a)
    {
        //Set the array of column alignments
        $this->aligns=$a;
    }

    function Row($data,$cell_align,$xval,$subject_length='',$cell_border=0)
    {
        //Calculate the height of the row
        $nb=0;
        for($i=0;$i<count($data);$i++)
            $nb=max($nb,$this->NbLines($this->widths[$i],$data[$i]));
        //$h=5*$nb;
        if($subject_length == 'long'){$h=12;}else{$h=6;}
        //Issue a page break first if needed
        $this->CheckPageBreaks($h,$xval);
       
        //Draw the cells of the row
        for($i=0;$i<count($data);$i++)
        {
            $w=$this->widths[$i];
            $a=isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';        
            //Save the current position
            $x=$this->GetX();
            $y=$this->GetY();        
            //Draw the border
            if($cell_border == 1){
                $this->Rect($x,$y,$w,$h);
            }
            //Print the text
            //$this->MultiCell($w,5,$data[$i],0,$a);
            $this->MultiCell($w,6,$data[$i],0,$cell_align[$i]);
            //Put the position to the right of the cell
            $this->SetXY($x+$w,$y);
        }
        //Go to the next line
        $this->Ln($h);
    }

    function CheckPageBreaks($h,$xval)
    {
        //If the height h would cause an overflow, add a new page immediately
        if($this->GetY()+$h>$this->PageBreakTrigger)        
            $this->AddPage($this->CurOrientation);
            $this->SetTopMargin(65);
            $y=$this->GetY();
            $this->SetXY($xval,$y);
    }

    function NbLines($w,$txt)
    {
        //Computes the number of lines a MultiCell of width w will take
        $cw=&$this->CurrentFont['cw'];
        if($w==0)
            $w=$this->w-$this->rMargin-$this->x;
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',$txt);
        $nb=strlen($s);
        if($nb>0 and $s[$nb-1]=="\n")
            $nb--;
        $sep=-1;
        $i=0;
        $j=0;
        $l=0;
        $nl=1;
        while($i<$nb)
        {
            $c=$s[$i];
            if($c=="\n")
            {
                $i++;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep=$i;
            $l+=$cw[$c];
            if($l>$wmax)
            {
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                }
                else
                    $i=$sep+1;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }
    var $htmlHeader;
    var $htmlFooter;
    var $subdomain;
    var $show_bg;
   
    public function setHtmlHeader($htmlHeader, $subdomain=0, $show_bg) {
        $this->htmlHeader = $htmlHeader;
        $this->subdomain = $subdomain;
        $this->show_bg = $show_bg;
    }
    
    public function Header() {
        // get the current page break margin
        $bMargin = $this->getBreakMargin();
        // get current auto-page-break mode
        $auto_page_break = $this->AutoPageBreak;
        // disable auto-page-break
        $this->SetAutoPageBreak(false, 0);     
        // set background image        
        if($this->show_bg=='Yes'){
            $img_file = public_path().'\\'.$this->subdomain.'\backend\canvas\bg_images\\KMTC_seqr_blank.jpg';
            $this->Image($img_file, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        }
        $this->SetFont('Arial','',11);
        $this->writeHTMLCell(
            $w = 0, $h = 0, $x = '', $y = '',
            $this->htmlHeader, $border = 0, $ln = 1, $fill = 0,
            $reseth = true, $align = 'top', $autopadding = true);
        // restore auto-page-break status
        $this->SetAutoPageBreak($auto_page_break, $bMargin);
        $this->setPageMark();
    }   
    
    public function setHtmlFooter($htmlFooter) {
        $this->htmlFooter = $htmlFooter;
    }
    
    public function Footer()
    {
        // Position at 30mm from bottom
        $this->SetY(-30);
        // Arial italic 8
        $this->SetFont('Arial','B',10);
        $this->writeHTMLCell(
            $w = 0, $h = 0, $x = '', $y = '',
            $this->htmlFooter, $border = 0, $ln = 1, $fill = 0,
            $reseth = true, $align = 'top', $autopadding = true);          
    }
    
}
