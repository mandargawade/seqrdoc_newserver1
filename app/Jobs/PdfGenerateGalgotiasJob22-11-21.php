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
class PdfGenerateGalgotiasJob
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
        $previewPdf=$pdf_data['previewPdf'];
        $excelfile=$pdf_data['excelfile'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];

        $template_data =array("id"=>100,"template_name"=>"Degree Certificate");
        $fetch_degree_data =$studentDataOrg;
        $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $fetch_degree_array=$studentDataOrg;
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        
        // Set the environment variable for GD
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


        for($excel_row = 0; $excel_row < count($fetch_degree_array); $excel_row++)
        {   

            //profile photo
                $extension = '';
                if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpg.jpg')){
                    $extension = '.jpg.jpg';
                  
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpeg.jpg')){
                    $extension = '.jpeg.jpg';

                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.png.jpg')){
                    $extension = '.png.jpg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpg')){
                    $extension = '.jpg';
                }
                else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.jpeg')){
                    $extension = '.jpeg';
                }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].'.png')){
                    $extension = '.png';
                }
        
                if(!empty($extension)){
                   $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][2].$extension;
                }else{
                     if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpg.jpg')){
                        $extension = '.jpg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpeg.jpg')){
                        $extension = '.jpeg.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.png.jpg')){
                        $extension = '.png.jpg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpg')){
                        $extension = '.jpg';
                    }
                    else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.jpeg')){
                        $extension = '.jpeg';
                    }else if(file_exists(public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].'.png')){
                        $extension = '.png';
                    }

                    if(!empty($extension)){
                    $profile_path = public_path().'/'.$subdomain[0].'/backend/DC_Photo/'.$fetch_degree_array[$excel_row][1].$extension;
                    }else{
                     $profile_path ='';  
                    }
                }
            

            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

            \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');
            
            $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

            $pdf->SetCreator('Security Software Solutions');
            $pdf->SetAuthor('SeQR Docs');
            $pdf->SetTitle('Passing Certificate');
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
                

                  if($fetch_degree_array[$excel_row][11] == 'Diploma in Mechanical Engineering'||$fetch_degree_array[$excel_row][11] == 'Diploma in Mechanical Engineering'){
                    $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DIP_Background_lite - 3.jpg';
                }elseif($fetch_degree_array[$excel_row][11] =='Diploma in PHARMACY'){
                    $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DIP_Background_lite_2.jpg';
                }else{
                    $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DIP_Background_lite.jpg';
                }
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

            if($previewPdf==1){
                if($previewWithoutBg!=1){
                $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
                }
                 $date_font_size = '11';
                $date_nox = 35;
                $date_noy = 30;
                $date_nostr = 'PREVIEW '.date('d-m-Y H:i:s');
                $pdfBig->SetFont($arialb, '', $date_font_size, '', false);
                $pdfBig->SetTextColor(192,192,192);
                $pdfBig->SetXY($date_nox, $date_noy);
                $pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetFont($arial, '', 8, '', false);

            }
            $pdfBig->setPageMark();

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


                $dt = date("_ymdHis");
                //qr code    
                //name // enrollment // degree // branch // cgpa // guid
                if($fetch_degree_array[$excel_row][10] == 'NU'){
                     $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$fetch_degree_array[$excel_row][17]."\n\n".md5(trim($fetch_degree_array[$excel_row][0]).$dt);
                }else{
                    if(is_float($fetch_degree_array[$excel_row][6])){
                        $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
                    }else{
                        $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
                    }
                    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
                        $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]).$dt);
                    }
                    else if($fetch_degree_array[$excel_row][10] == 'DO'){
                        $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]).$dt);
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

                if(!empty($profile_path)){
                
                $profilex = 181;
                $profiley = 19.8;
                $profileWidth = 22.2;
                
                $profileHeight = 26.6;
                $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
                }

                //invisible data
                $invisible_font_size = '10';

                $invisible_degreex = 7.3;
                $invisible_degreey = 48.3;
                $invisible_degreestr = trim($fetch_degree_array[$excel_row][11]);
                $pdfBig->SetOverprint(true, true, 0);
                // $pdfBig->SetTextColor(0,0,48,0, false, '');
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

                    if($fetch_degree_array[$excel_row][11]=='INTEGRATED BACHELOR OF BUSINESS ADMINISRATION - BACHELOR OF LAW (HONOURS)'){
                        $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                        $pdf->SetTextColor(0,92,88,7,false,'');
                        $pdf->SetXY(10, $degree_namey);
                        $pdf->Cell(190, 0, 'INTEGRATED BACHELOR OF BUSINESS ADMINISRATION -', 0, false, 'C');

                        $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                        $pdf->SetTextColor(0,92,88,7,false,'');
                        $pdf->SetXY(10, $degree_namey+5);
                        $pdf->Cell(190, 0, 'BACHELOR OF LAW (HONOURS)', 0, false, 'C');


                        $pdfBig->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                        $pdfBig->SetTextColor(0,92,88,7,false,'');
                        $pdfBig->SetXY(10, $degree_namey);
                        $pdfBig->Cell(190, 0, 'INTEGRATED BACHELOR OF BUSINESS ADMINISRATION -', 0, false, 'C');

                        $pdfBig->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                        $pdfBig->SetTextColor(0,92,88,7,false,'');
                        $pdfBig->SetXY(10, $degree_namey+5);
                        $pdfBig->Cell(190, 0, 'BACHELOR OF LAW (HONOURS)', 0, false, 'C');
                    }else{ 
                    $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                    $pdf->SetTextColor(0,92,88,7,false,'');
                    $pdf->SetXY(10, $degree_namey);
                    $pdf->Cell(190, 0, $degree_namestr, 0, false, 'C');


                    $pdfBig->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                    $pdfBig->SetTextColor(0,92,88,7,false,'');
                    $pdfBig->SetXY(10, $degree_namey);
                    $pdfBig->Cell(190, 0, $degree_namestr, 0, false, 'C');
                    }
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

          

                $microlinestr = preg_replace('/\s+/', '', $microlinestr); //added by Mandar
                $textArray = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlinestr);
                $strWidth = ($textArray[2] - $textArray[0]);
                $strHeight = $textArray[6] - $textArray[1] / 1.4;
                
              
                 $latestWidth = 553;
                
                //Updated by Mandar
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
                $array = substr($microlinestrRep,0,$microlinestrCharReq);
                $wd = '';
                $last_width = 0;
                $message = array();
                $pdf->SetFont($arialb, 'B', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0, 100);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $pdf->SetXY(36.8, 144);
                    $pdf->SetXY(36.8, 146.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.8, 145.5);
                }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                    $pdf->SetXY(36.8, 143.5);
                }
                $pdf->Cell(0, 0, $array, 0, false, 'L');

                $pdf->StopTransform();

                $pdfBig->SetFont($arialb, 'B', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0, 100);
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


                $microlineEnrollment = preg_replace('/\s+/', '', $microlineEnrollment); 
                $textArrayEnrollment = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlineEnrollment);
                $strWidthEnrollment = ($textArrayEnrollment[2] - $textArrayEnrollment[0]);
                $strHeightEnrollment = $textArrayEnrollment[6] - $textArrayEnrollment[1] / 1.4;

                    $latestWidthEnrollment = 595;
               
                //Updated by Mandar
                $microlineEnrollmentstrLength=strlen($microlineEnrollment);

                //width per character
                $microlineEnrollmentcharacterWd =$strWidthEnrollment/$microlineEnrollmentstrLength;

                //Required no of characters required in string to match width
                $microlineEnrollmentCharReq=$latestWidthEnrollment/$microlineEnrollmentcharacterWd;
                $microlineEnrollmentCharReq=round($microlineEnrollmentCharReq);

                //No of time string should repeated
                 $repeatemicrolineEnrollmentCount=$latestWidthEnrollment/$strWidthEnrollment;
                 $repeatemicrolineEnrollmentCount=round($repeatemicrolineEnrollmentCount)+1;

                //Repeatation of string 
                 $microlineEnrollmentstrRep = str_repeat($microlineEnrollment, $repeatemicrolineEnrollmentCount);
                
                //Cut string in required characters (final string)
                $arrayEnrollment = substr($microlineEnrollmentstrRep,0,$microlineEnrollmentCharReq);

                $wdEnrollment = '';
                $last_widthEnrollment = 0;
                $messageEnrollment = array();
       
                $pdf->SetFont($arialb, 'B', 1.2, '', false);
                $pdf->SetTextColor(0, 0, 0, 100);
                $pdf->StartTransform();
                if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $pdf->SetXY(36.4, 219);
                    $pdf->SetXY(36.4, 216.6);
                }
                else if($fetch_degree_array[$excel_row][10] == 'DO'){
                    $pdf->SetXY(36.4, 216);
                }
                
                $pdf->Cell(0, 0, $arrayEnrollment, 0, false, 'L');

                $pdf->StopTransform();

                $pdfBig->SetFont($arialb, 'B', 1.2, '', false);
                $pdfBig->SetTextColor(0, 0, 0, 100);
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
                
                if($fetch_degree_array[$excel_row][11] == 'Diploma in Mechanical Engineering'||$fetch_degree_array[$excel_row][11] == 'Diploma in Mechanical Engineering'){
                    $str = 'rhu o"khZ; fMIyksek ikBîØe '; 
                }elseif($fetch_degree_array[$excel_row][11] =='Diploma in PHARMACY'){
                   $str = 'nks o"khZ; fMIyksek ikBîØe '; 
                }else{
                   $str = 'nks o"khZ; fMIyksek ikBîØe '; 
                }
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
                $pdfBig->SetFont($arialb, 'B', $footer_name_font_size, '', false);
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

                        $pdf->SetTextColor(0,0,0,7,false,'');
                        $pdf->SetFont($arialb, '', $repeat_font_size, '', false);
                        $pdf->StartTransform();
                        $pdf->SetXY($repeatx, $name_repeaty);
                        $pdf->Cell(0, 0, $name_repeatstr, 0, false, 'L');
                        $pdf->StopTransform();


                        $pdfBig->SetTextColor(0,0,0,7,false,'');
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

                $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp/temp');
                
                $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
                   
                $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

                $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

            
    if($previewPdf!=1){ 
            
            $certName = str_replace("/", "_", $serial_no) .".pdf";
            
            $myPath = public_path().'/backend/temp_pdf_file'; 
            
            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

            $this->addCertificate($serial_no, $certName, $dt,100,$admin_id);

            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            $print_datetime = date("Y-m-d H:i:s");
            

            $print_count = $this->getPrintCount($serial_no);
            $printer_name = /*'HP 1020';*/$printer_name;
        
            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'Degree Certificate',$admin_id);
            
          }

        }


        $msg = '';
        
        $file_name = date("Ymdhms").'.pdf';
       
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
       
        $pdfBig->output($filename,'F');

        
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
        $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
        if($previewPdf==1){
            $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name;
            $filePath =public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$file_name;
        }else{
            $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name;
            $filePath =public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name;
             $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name=$template_data['template_name'];
            if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                // with sandbox
                
                $result = SbExceUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            }else{
                // without sandbox
                $result = ExcelUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            } 
        }
        
        $aws_qr = \File::copy($filename,$filePath);
        @unlink($filename);

     
        return $pdf_url;

    }


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
    $key = strtoupper(md5($serial_no)); 
    $codeContents = $key;
    $fileName = $key.'.png'; 

    $urlRelativeFilePath = 'qr/'.$fileName; 


    $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
    $auth_site_id=Auth::guard('admin')->user()->site_id;
    $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);

}

public function getPrintCount($serial_no)
{
    $auth_site_id=Auth::guard('admin')->user()->site_id;
    $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
    $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();

    return $numCount + 1;
}

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

public function createTemp($path){
        //create ghost image folder
    $tmp = date("ymdHis");

    $tmpname = tempnam($path, $tmp);
    unlink($tmpname);
    mkdir($tmpname);
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
