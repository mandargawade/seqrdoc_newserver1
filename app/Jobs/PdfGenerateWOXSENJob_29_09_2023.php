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
class PdfGenerateWOXSENJob
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

        if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
            $admin_id=$pdf_data['admin_id'];
        }else{
            $admin_id = \Auth::guard('admin')->user()->toArray();  
        }
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        
        $printer_name = $systemConfig['printer_name'];

       // dd($printer_name);

        //Separate students subjects
        //data processing
        /*$subjectsArr = array();
        foreach ($subjectsDataOrg as $element) {
            $subjectsArr[$element[0]][] = $element;
        }*/
        //data processing

        $ghostImgArr = array();
        $pdfBig = new TCPDF('P', 'mm', array('360', '240'), true, 'UTF-8', false);
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

        //set fonts
        $Arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $ArialB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);

        $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);

        $log_serial_no = 1;
        //$cardDetails=$this->getNextCardNo('WOXSEN-D'); not needed for this
        //$card_serial_no=$cardDetails->next_serial_no; not needed for this
        //$card_serial_no = '';
        $generated_documents=0;  
        foreach ($studentDataOrg as $studentData) {
            
            //For Custom Loader
            $startTimeLoader =  date('Y-m-d H:i:s');

            $pdfBig->AddPage();

            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\Woxsen University_Degee Certificate_Template.jpg';

            if($previewPdf==1){
                if($previewWithoutBg!=1){
                    $pdfBig->Image($template_img_generate, 0, 0, '240', '360', "JPG", '', 'R', true);
                }
            }
            $pdfBig->setPageMark();

            $pdf = new TCPDF('P', 'mm', array('360', '240'), true, 'UTF-8', false);
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
            $pdf->AddPage();
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\Woxsen University_Degee Certificate_Template.jpg';
            if($previewPdf!=1){
                $pdf->Image($template_img_generate, 0, 0, '240', '360', "JPG", '', 'R', true);
            }
            $pdf->setPageMark();


            $profile_path_org = '';
                //Indiviual Student ROW from SHEET 1

            //EN ROLL NO
            $x = 14.5;
            $y = 13;
            $pdfBig->SetFont($Arial, '', 10, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "Roll No. :", 0, false, 'L');

            $pdf->SetFont($Arial, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Roll No. :", 0, false, 'L');

            //EN ROLL NO
            $x = 29.5;
            $y = 12.5;
            $pdfBig->SetFont($ArialB, '', 12, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[1], 0, false, 'L');

            $pdf->SetFont($ArialB, '', 12, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[1], 0, false, 'L');

            //QR CODE
            $str=$studentData[0];
            $codeContents =$encryptedString = strtoupper(md5($str));
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 14.5;
            $qrCodey = 18;
            $qrCodeWidth =27;
            $qrCodeHeight = 27;
            \QrCode::size(75.6)
                ->backgroundColor(255, 255, 0)
                ->format('png')
                ->generate($codeContents, $qr_code_path);

            $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

            //Serial No
            $x = 188.5; //158.5
            $y = 12.5;
            $pdfBig->SetFont($Arial, '', 12, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "Serial No. :", 0, false, 'L');

            $pdf->SetFont($Arial, '', 12, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Serial No. :", 0, false, 'L');    

            //Serial Number
            $x = 210; //180 old
            $y = 16;
            $font_size=10;
            $str = $studentData[6];
            $strArr = str_split($str);

            $x_org=$x;
            $y_org=$y;
            $font_size_org=$font_size;
            $i =0;
            $j=0;
            $y=$y+1.5;
            $z=0;
            foreach ($strArr as $character) {
                        $pdfBig->SetFont($Arial,0, $font_size, '', false);
                        $pdfBig->SetXY($x, $y+$z);

                        $pdf->SetFont($Arial,0, $font_size, '', false);
                        $pdf->SetXY($x, $y+$z);
                        $j=$j+0.1;
                        $z=$z+0.1;
                        $x=$x+0.4;
                        $pdfBig->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');

                        $pdf->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');
                        
                        $i++;
                        $x=$x+2.2+$j; 
                        $font_size=$font_size+1; 
           }

            $pdfBig->SetTextColor(255,255,0);
            //invisible Data
            //$x = 14;
            $x = 13;
            $y = 85; 
            $invisible_namestr = strtoupper($studentData[2]); //STUDENT NAME
            $pdfBig->SetFont($Arial, 0, 8, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $invisible_namestr, 0, false, 'L');//$studentData[0]

            $x = 13;
            $y = 88; 
            $invisible_namestr = $studentData[1]; //STUDENT NAME
            $pdfBig->SetFont($Arial, 0, 8, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $invisible_namestr, 0, false, 'L');  

            $pdfBig->SetTextColor(0,0,0);

            //Student Profile Picture    
            $profile_path_org_jpg = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[0].'.jpg';
            $profile_path_org_jpeg = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[0].'.jpeg';
            $profile_path_org_png = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[0].'.png';
            if(file_exists($profile_path_org_jpg)){
                $profile_path_org = $profile_path_org_jpg;
            }else if(file_exists($profile_path_org_jpeg)){
                $profile_path_org = $profile_path_org_jpeg;
            }else if(file_exists($profile_path_org_png)){
                $profile_path_org = $profile_path_org_png;
            }
            
            if(file_exists($profile_path_org)) {        
                
                $profilex = 95;
                $profiley = 80;
                $profileWidth = 50;
                $profileHeight = 59;
                $pdfBig->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                $pdf->image($profile_path_org,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
            }
            
        //$x = 20;
        $x = 10;
        $y = 155;   
        $pdfBig->SetFont($timesNewRomanI, '', 25, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, "The Academic Council of Woxsen University", 0, false, 'C');
        
        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "The Academic Council of Woxsen University", 0, false, 'C');

        $y = 165;   
        $pdfBig->SetFont($timesNewRomanI, '', 25, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, "certifies that", 0, false, 'C');

        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "certifies that", 0, false, 'C');

        $x = 10;
        $y = 177;
        $nameOrg = '';
        $Totalchar = (int)strlen($studentData[2]);


        /*for($i=1; $i < 80; $i++){
            $nameOrg .= $studentData[1].' '.$studentData[0];
        }*/

        $nameOrg = str_repeat($studentData[2].' '.$studentData[1], 50);
        
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $microlinestr=$str;
        $pdfBig->SetFont($Arial, '', 2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, substr($microlinestr,0,351), 0, false, 'C');

        $pdf->SetFont($Arial, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, substr($microlinestr,0,351), 0, false, 'C'); 

        $y = 190;
        //dd(strlen($studentData[1]));
        $Totalchar = (int)strlen($studentData[2]);
        if($Totalchar > 30){
            $pdfBig->SetFont($timesNewRomanB, '', 25, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[2], 0, false, 'C');

            $pdf->SetFont($timesNewRomanB, '', 25, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[2], 0, false, 'C');
        }else{

            $pdfBig->SetFont($timesNewRomanB, '', 35, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[2], 0, false, 'C');

            $pdf->SetFont($timesNewRomanB, '', 35, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[2], 0, false, 'C');
        }

        $y = 210;   
        $pdfBig->SetFont($timesNewRomanI, '', 25, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, "has fulfilled all the requirements stipulated by", 0, false, 'C');

        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "has fulfilled all the requirements stipulated by", 0, false, 'C');

        $y = 225;   
        $pdfBig->SetFont($timesNewRomanI, '', 25, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, "the Examination Board and has been", 0, false, 'C');

        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "the Examination Board and has been", 0, false, 'C');

        $y = 240;   
        $pdfBig->SetFont($timesNewRomanI, '', 25, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, "awarded the degree of", 0, false, 'C');

        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "awarded the degree of", 0, false, 'C');

        $programName  = explode(' ',$studentData[3]);
        $part1 = '';
        $part2 = '';
        foreach($programName as $key => $val){
            if($key < 3){
                $part1 .= $val." ";
            }else{
                $part2 .= $val." ";
            }
        }


        $y = 255;
        $Totalchar = (int)strlen($studentData[3]);
        if($Totalchar > 30){   
            $pdfBig->SetFont($timesNewRomanB, '', 30, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->cell(0, 0, trim($part1), 0, false, 'C');

            $pdf->SetFont($timesNewRomanB, '', 30, '', false);
            $pdf->SetXY($x, $y);
            $pdf->cell(0, 0, trim($part1), 0, false, 'C');

            $y = 269;   
            $pdfBig->SetFont($timesNewRomanB, '', 30, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, trim($part2), 0, false, 'C');

            $pdf->SetFont($timesNewRomanB, '', 30, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, trim($part2), 0, false, 'C');

        }else{

            $pdfBig->SetFont($timesNewRomanB, '', 30, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->cell(0, 0, trim($part1), 0, false, 'C');

            $pdf->SetFont($timesNewRomanB, '', 30, '', false);
            $pdf->SetXY($x, $y);
            $pdf->cell(0, 0, trim($part1), 0, false, 'C');

            $y = 269;   
            $pdfBig->SetFont($timesNewRomanB, '', 30, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, trim($part2), 0, false, 'C');

            $pdf->SetFont($timesNewRomanB, '', 30, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, trim($part2), 0, false, 'C');
        }
        $y = 285;
        $nameOrg = '';
        for($i=0; $i < 7; $i++){
          $nameOrg .= $studentData[3];  
        } 
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $microlinestr=$str;
        $pdfBig->SetFont($Arial, '', 2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, $microlinestr, 0, false, 'C');

        $pdf->SetFont($Arial, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

        //left size bottom cornor
        $x = 18;
        //$y = 323.5;
        $y = 320.5; 
        $nameOrg='Woxsen University Woxsen University Woxsen University Woxsen University Woxsen University Woxsen University';
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $microlinestr=$str;
        $pdfBig->SetFont($Arial, '', 2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, $microlinestr, 0, false, 'L');

        $pdf->SetFont($Arial, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'L');

        
		$Dean = public_path().'\\'.$subdomain[0].'\backend\canvas\images\Dean.png';
		$Dean_x = 19;
		$Dean_y = 308;
		$Dean_Width = 44.979166667;
		$Dean_Height = 10.31875;
		$pdfBig->image($Dean,$Dean_x,$Dean_y,$Dean_Width,$Dean_Height,"",'','L',true,3600);
		$pdfBig->setPageMark();	

		$VC = public_path().'\\'.$subdomain[0].'\backend\canvas\images\VC.png';
		$VC_x = 178;
		$VC_y = 305;
		$VC_Width = 39.6875;
		$VC_Height = 14.552083333;
		$pdfBig->image($VC,$VC_x,$VC_y,$VC_Width,$VC_Height,"",'','L',true,3600);
		$pdfBig->setPageMark();			
		
		
		$x = 32;
        //$y = 324; 
        $y = 321;  
        $pdfBig->SetFont($timesNewRomanB, '', 17, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, "Dean", 0, false, 'L');

        $pdf->SetFont($timesNewRomanB, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Dean", 0, false, 'L');

        $x = 17;
        //$y = 330;
        $y = 327;    
        $pdfBig->SetFont($timesNewRoman, '', 17, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, "School of Business", 0, false, 'L');

        $pdf->SetFont($timesNewRoman, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "School of Business", 0, false, 'L');

        $ghost_font_size = '13';
        $ghostImagex = 14;
        $ghostImagey = 337;
        //$ghostImageWidth = 55;//68
        //$ghostImageHeight = 8;
        $ghostImageWidth = 39.405983333;
        $ghostImageHeight = 10;
        $name = substr(str_replace(' ','',strtoupper($studentData[2])), 0, 6);
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
        //$pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        //$pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);


        
        //right
        $x = 168; //135
        $y = 320.5; 
        $nameOrg='Woxsen University Woxsen University Woxsen University Woxsen University Woxsen University Woxsen University';
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $microlinestr=$str;
        $pdfBig->SetFont($Arial, '', 2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, $microlinestr, 0, false, 'C');

        $pdf->SetFont($Arial, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

        $x = 177; //147
        $y = 321;   
        $pdfBig->SetFont($timesNewRomanB, '', 17, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, "Vice-Chancellor", 0, false, 'L');

        $pdf->SetFont($timesNewRomanB, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Vice-Chancellor", 0, false, 'L');

        $x = 176; //144.5
        $y = 327;   
        $pdfBig->SetFont($timesNewRoman, '', 17, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, "Woxsen University", 0, false, 'L');

        $pdf->SetFont($timesNewRoman, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Woxsen University", 0, false, 'L');


        $x = 10;
        $y = 332;   
        $pdfBig->SetFont($timesNewRomanB, '', 17, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, $studentData[4].",", 0, false, 'C');

        $pdf->SetFont($timesNewRomanB, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, $studentData[4].",", 0, false, 'C');


        $x = 10;
        $y = 338;   //278
        $pdfBig->SetFont($timesNewRoman, '', 17, '', false);
        $pdfBig->SetXY($x, $y);
        $pdfBig->Cell(0, 0, $studentData[5], 0, false, 'C');

        $pdf->SetFont($timesNewRoman, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, $studentData[5], 0, false, 'C');   
                

        //Indiviual Student ROW from SHEET 1

        

            $serial_no=$GUID=$studentData[0];
            $student_name = $studentData[2];
            if($previewPdf!=1){
				//$certName = preg_replace('/[^a-zA-Z0-9]/', '_', $GUID).".pdf";
                $certName = str_replace("/", "_", $GUID) .".pdf";
				//$certName = preg_replace('/[^a-zA-Z0-9.]/', '_', $certName);
                
                $myPath = public_path().'/backend/temp_pdf_file';

                $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;

                $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

                 $this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,$student_name);

                $username = $admin_id['username'];
                date_default_timezone_set('Asia/Kolkata');

                $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
                $date = date('Y-m-d H:i:s').PHP_EOL;
                $print_datetime = date("Y-m-d H:i:s");
                

                $print_count = $this->getPrintCount($serial_no);
                $printer_name = /*'HP 1020';*/$printer_name;
                $print_serial_no = '';

                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'WOXSEN-D',$admin_id,$card_serial_no);
                //$card_serial_no=$card_serial_no+1;
            }

        //TRSNCRIPT RIGHT SIDE PAGE DATA

            $generated_documents++;

            if(isset($pdf_data['generation_from'])&&$pdf_data['generation_from']=='API'){
                $updated=date('Y-m-d H:i:s');
                ThirdPartyRequests::where('id',$pdf_data['request_id'])->update(['generated_documents'=>$generated_documents,"updated_at"=>$updated]);
            }else{
                  //For Custom loader calculation
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
            //loop through all terms of student 
            //delete temp dir 26-04-2022 
            CoreHelper::rrmdir($tmpDir);
        } //foreach indiviual student

    
        $msg = '';

        $file_name =  str_replace("/", "_",'WOXSEN_'.date("Ymdhms")).'.pdf';
        
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        $pdfBig->output($filename,'F');

        if($previewPdf!=1){
            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);
            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="WOXSEN-D";
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
            $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
        }else{
          

            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$file_name);
            @unlink($filename);
            $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
            $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
            $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name;
            $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
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

	public function TestCompressPDF($inputFile,$outputFile,$pdfSetting='') {
		/*$inputfile:temp_pdf_file, $outputFile:pdf_file*/		
		if($pdfSetting == ''){
			$pdfSetting='ebook';
		}else{
			$pdfSetting=$pdfSetting;
		}
		$domain = \Request::getHost();        
        $subdomain = explode('.', $domain);   
		$unique_name = date('dmYHis').'_'.uniqid().'.pdf';
		$input_filename = public_path().'/backend/temp_pdf_file/'.$unique_name;				
		copy($inputFile, $input_filename);
		$output_filename = public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$unique_name;		
        //$output = exec('"C:/Program Files/gs/gs9.56.1/bin/gswin64c.exe" -r120 -dSAFER -dQUIET -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dPDFSETTINGS=/'.$pdfSetting.' -dCompatibilityLevel=1.4 -sOutputFile='.$outputFile.' '.$inputFile);
        $output = exec('"C:/Program Files/gs/gs9.56.1/bin/gswin64c.exe" -r120 -dSAFER -dQUIET -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dPDFSETTINGS=/'.$pdfSetting.' -dCompatibilityLevel=1.4 -sOutputFile='.$output_filename.' '.$input_filename);
        rename($output_filename,$outputFile); //old,new		
		unlink($input_filename);
		//unlink($output_filename);
		return $output;	
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

    public function createTemp($path)
    {
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

    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,$student_name)
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
		//$this->TestCompressPDF($source,$output);
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
        
        $result = SbStudentTable::create(['serial_no'=>'T-'.$serial_no,'student_name' =>$student_name,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }else{
        //dd($serial_no);strval(    
        $resultu = StudentTable::where('serial_no',strval($serial_no))->update(['status'=>'0']);
        //dd('reach');
        // Insert the new record
        
        $result = StudentTable::create(['serial_no'=>$serial_no,'student_name' =>$student_name ,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>2]);
        }
        
    }

    public function getNextCardNo($template_name)
    { 
        $auth_site_id=Auth::guard('admin')->user()->site_id;

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
            // dd($rect);
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
}
