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

class PdfGenerateRRMUJob
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

        /*echo "<pre>";
        print_r($studentDataOrg);
        exit;*/

        
        if(isset($pdf_data['generation_from']) && $pdf_data['generation_from']=='API'){
        
            $admin_id=$pdf_data['admin_id'];
        }else{
            $admin_id = \Auth::guard('admin')->user()->toArray();  
        }
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        $printer_name = $systemConfig['printer_name'];
 
        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
        $pdfBig->SetCreator(PDF_CREATOR);
        $pdfBig->SetAuthor('TCPDF');
        $pdfBig->SetTitle('Certificate');
        $pdfBig->SetSubject('');

        // remove default header/footer
        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);


        //set fonts
        $Times_New_Roman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold-Italic.ttf', 'TrueTypeUnicode', '', 96);
        $K101 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K101.ttf', 'TrueTypeUnicode', '', 96);
        $Kruti_Dev_730k = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Kruti Dev 730k.ttf', 'TrueTypeUnicode', '', 96);
        $MICR_B10 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\MICR-B10.ttf', 'TrueTypeUnicode', '', 96);
        $OLD_ENG1 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\OLD_ENG1.ttf', 'TrueTypeUnicode', '', 96);
        $OLD_ENGL = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\OLD_ENGL.ttf', 'TrueTypeUnicode', '', 96);
        $times = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\times.ttf', 'TrueTypeUnicode', '', 96);
        $timesbd = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesbd.ttf', 'TrueTypeUnicode', '', 96);
        $timesbi = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesbi.ttf', 'TrueTypeUnicode', '', 96);
        $timesi = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesi.ttf', 'TrueTypeUnicode', '', 96);
        $Arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $ArialB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $arialNarrow = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arialn.ttf', 'TrueTypeUnicode', '', 96);


        $log_serial_no = 1;
        //$cardDetails=$this->getNextCardNo('RRMU-C');
        //$card_serial_no=$cardDetails->next_serial_no;
        
        $generated_documents=0;  
        foreach ($studentDataOrg as $studentData) 
        {
            $card_serial_no = $studentData[3];
            //For Custom Loader
             $startTimeLoader =  date('Y-m-d H:i:s');
         
             $pdfBig->AddPage();
            
             //set background image
             $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\RAJ_RISHI_Bg.jpg';   
             if($previewPdf==1){
                if($previewWithoutBg!=1){
                    $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
                }
             }
            $pdfBig->setPageMark();
         
            $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('TCPDF');
            $pdf->SetTitle('Certificate');
            $pdf->SetSubject('');

            // remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);
            
            $pdf->AddPage();
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\RAJ_RISHI_Bg.jpg';
            if($previewPdf!=1){
                $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

            }
            $pdf->setPageMark();

            //$print_serial_no = $this->nextPrintSerial();
            $print_serial_no = '';
            
            $pdfBig->SetTextColor(0,0,0);
            $pdf->SetTextColor(0,0,0);

            if(strtoupper($studentData[5]) == 'PASS'){
                $statictext = "Class";
            }else{
                $statictext = "Division";
            }

            //EN ROLL NO
            $x = 12;
            $y = 11;   
            $pdf->SetFont($times, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Roll No.: ", 0, false, 'L');

            $pdfBig->SetFont($times, '', 10, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "Roll No.: ", 0, false, 'L');

            //EN ROLL NO
            $x = 27;
            $y = 11;  
            $fontEBPath = public_path() . '\\' . $subdomain[0] . '\backend\canvas\fonts\E-13B_0.php';
            $pdf->AddFont('E-13B_0', '', $fontEBPath);
            $pdf->SetFont('E-13B_0', '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[1], 0, false, 'L');

            $pdfBig->AddFont('E-13B_0', '', $fontEBPath);
            $pdfBig->SetFont('E-13B_0', '', 10, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[1], 0, false, 'L');

            //Serial NO
            $x = 165;
            $y = 11;   
            $pdf->SetFont($times, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Serial No. :", 0, false, 'L');

            $pdfBig->SetFont($times, '', 10, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "Serial No. :", 0, false, 'L');

            $x = 182;
            $y = 11;   
            $pdf->SetFont($times, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[3], 0, false, 'L');

            $pdfBig->SetFont($times, '', 10, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[3], 0, false, 'L');

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

            $barcodex = 166;
            $barcodey = 17;
            $barcodeWidth = 30;
            $barodeHeight = 5;

            $pdf->SetAlpha(1);
            // Barcode CODE_39 - Roll no 
            $pdf->write1DBarcode($studentData[1], 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

            $pdfBig->SetAlpha(1);
            $pdfBig->write1DBarcode($studentData[1], 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');

            //EN ROLL NO
            $x = 12;
            $y = 16;   
            $pdf->SetFont($times, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Enroll No.: ", 0, false, 'L');

            $pdfBig->SetFont($times, '', 10, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "Enroll No.: ", 0, false, 'L');

            //EN ROLL NO
            $x = 30;
            $y = 16;   
            $pdf->SetFont($arialNarrow, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[2], 0, false, 'L');

            $pdfBig->SetFont($arialNarrow, '', 10, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[2], 0, false, 'L');

            //------------------------------------

            $y_add = 5; 
            $y_min = 5;

            $x = 10;
            $y = 88-$y_min;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "We, the Chancellor, the Vice- Chancellor and Members of the Board of", 0, false, 'C');

            $pdfBig->SetFont($times, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "We, the Chancellor, the Vice- Chancellor and Members of the Board of", 0, false, 'C');

            $x = 26;
            $y = 97.5-$y_min;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Management of ", 0, false, 'L');

            $pdfBig->SetFont($times, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "Management of ", 0, false, 'L');

            $x = 65;   
            $pdf->SetFont($timesbd, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Raj Rishi Bhartrihari Matsya University, Alwar', 0, false, 'L');

            $pdfBig->SetFont($timesbd, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, 'Raj Rishi Bhartrihari Matsya University, Alwar', 0, false, 'L');

            $x = 10;
            $y = 106.5-$y_min;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "certify that", 0, false, 'C');

            $pdfBig->SetFont($times, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "certify that", 0, false, 'C');

            $name = $studentData[4];

            $x = 10;
            $y = 116-$y_min;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($ArialB, '', 20, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $name, 0, false, 'C');

            $pdfBig->SetTextColor(237, 50, 55);   
            $pdfBig->SetFont($ArialB, '', 20, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $name, 0, false, 'C');

            
            $x = 10;
            $y = 126.5-$y_min;
            $pdf->SetTextColor(0,0,0);  
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "having passed the University Examination held in the year 2020 with", 0, false, 'C');

            $pdfBig->SetTextColor(0,0,0);  
            $pdfBig->SetFont($times, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "having passed the University Examination held in the year 2020 with", 0, false, 'C');

            $x = 10;
            $y = 136.5-$y_min;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[5]." ".$statictext, 0, false, 'C');

            $pdfBig->SetFont($times, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[5]." ".$statictext, 0, false, 'C');

            $x = 10;
            $y = 145-$y_min;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "has been conferred the degree of", 0, false, 'C');

            $pdfBig->SetFont($times, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "has been conferred the degree of", 0, false, 'C');


            $x = 10;
            $y = 155-$y_min;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($OLD_ENGL, '', 30, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[6], 0, false, 'C');

            $pdfBig->SetTextColor(237, 50, 55);   
            $pdfBig->SetFont($OLD_ENGL, '', 30, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[6], 0, false, 'C');

            $x = 10;
            $y = 161;
            $pdf->SetTextColor(0, 0, 0);   
            $pdf->SetFont($times, '', 15, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[7], 0, false, 'C');

            $pdfBig->SetTextColor(0, 0, 0);   
            $pdfBig->SetFont($times, '', 15, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[7], 0, false, 'C');

            
            $x = 10;
            $y = 170;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'jkt _f"k HkrZ`gfj eRL; fo’ofo|ky;] vyoj', 0, false, 'C');

            $pdfBig->SetTextColor(0,0,0);   
            $pdfBig->SetFont($K101, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, 'jkt _f"k HkrZ`gfj eRL; fo’ofo|ky;] vyoj', 0, false, 'C');

            $x = 10;
            $y = 179.5;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'dqykf/kifr]dqyifr rFkk izcaU/k e.My ds lnL; izekf.kr djrs gSa fd', 0, false, 'C');

            $pdfBig->SetTextColor(0,0,0);   
            $pdfBig->SetFont($K101, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, 'dqykf/kifr]dqyifr rFkk izcaU/k e.My ds lnL; izekf.kr djrs gSa fd', 0, false, 'C');

            $x = 10;
            $y = 188;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($Kruti_Dev_730k, '', 28, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[8], 0, false, 'C');

            $pdfBig->SetTextColor(237, 50, 55);   
            $pdfBig->SetFont($Kruti_Dev_730k, '', 28, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[8], 0, false, 'C');


            $x = 40;
            $y = 203;
            $pdf->SetTextColor(0,0,0);     
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "dks bl fo’ofo|ky; dh lu", 0, false, 'L');

            $pdfBig->SetTextColor(0,0,0);     
            $pdfBig->SetFont($K101, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "dks bl fo’ofo|ky; dh lu", 0, false, 'L');

            $x = 105;   
            $pdf->SetFont($Arial, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, " 2020 ", 0, false, 'L');

            $pdfBig->SetFont($Arial, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, " 2020 ", 0, false, 'L');

            $x = 122.5;
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "esa vk;ksftr ijh{kk esa", 0, false, 'L');

            $pdfBig->SetFont($K101, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "esa vk;ksftr ijh{kk esa", 0, false, 'L');

            $x = 10;
            $y = 212;   
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[9]." Js.kh mÙkh.kZ gksus ij", 0, false, 'C');

            $pdfBig->SetFont($K101, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[9]." Js.kh mÙkh.kZ gksus ij", 0, false, 'C');

            $x = 10;
            $y = 219;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($Kruti_Dev_730k, '', 28, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[10], 0, false, 'C');

            $pdfBig->SetTextColor(237, 50, 55);   
            $pdfBig->SetFont($Kruti_Dev_730k, '', 28, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[10], 0, false, 'C');

            $x = 10;
            $y = 230;
            $pdf->SetTextColor(0, 0, 0);   
            $pdf->SetFont($K101, '', 15, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $studentData[11], 0, false, 'C');

            $pdfBig->SetTextColor(0, 0, 0);   
            $pdfBig->SetFont($K101, '', 15, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, $studentData[11], 0, false, 'C');
            

            $x = 10;
            $y = 233+$y_add;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "dh mikf/k iznku dh xbZ A", 0, false, 'C');

            $pdfBig->SetTextColor(0,0,0);   
            $pdfBig->SetFont($K101, '', 17, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "dh mikf/k iznku dh xbZ A", 0, false, 'C');

            $x = 20;
            $y = 262;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($Arial, '', 12, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Place: Alwar", 0, false, 'L');

            $pdfBig->SetTextColor(0,0,0);   
            $pdfBig->SetFont($Arial, '', 12, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "Place: Alwar", 0, false, 'L');

            $x = 20;
            $y = 268;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($Arial, '', 12, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Dated : 28/03/2022", 0, false, 'L');

            $pdfBig->SetTextColor(0,0,0);   
            $pdfBig->SetFont($Arial, '', 12, '', false);
            $pdfBig->SetXY($x, $y);
            $pdfBig->Cell(0, 0, "Dated : 28/03/2022", 0, false, 'L');

            $nameOrg = $studentData[4];
            $ghost_font_size = '12';
            $ghostImagex = 21;
            $ghostImagey = 277;
            $ghostImageWidth = 39.405983333;
            $ghostImageHeight = 8;
            $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
            $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');

            $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);

            $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);



            $str= $studentData[1].", ".$studentData[4].", ".$studentData[6]." ".$studentData[7]." , ".$studentData[12].", ".$studentData[5]." ".$statictext.", 2020";
            $codeContents = $str;
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$studentData[1].'.png';
            $qrCodex = 95;
            $qrCodey = 250;
            $qrCodeWidth =20;
            $qrCodeHeight = 20;
            \QrCode::size(75.6)
                ->backgroundColor(255, 255, 0)
                ->format('png')
                ->generate($codeContents, $qr_code_path);

            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

            $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

            $x = 10;
            $y = 272;
            $microlinestr=$studentData[4];
            $pdf->SetFont($Arial, '', 1, '', false);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($x, $y);        
            $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

            $pdfBig->SetFont($Arial, '', 1, '', false);
            $pdfBig->SetTextColor(0, 0, 0);
            $pdfBig->SetXY($x, $y);        
            $pdfBig->Cell(0, 0, $microlinestr, 0, false, 'C');

            $pdf->AddSpotColor('Clear', 67.3, 31.2, 0, 20.8); 
            $pdfBig->AddSpotColor('Clear', 67.3, 31.2, 0, 20.8); 

            $x = 12;
            $y = 273;
            $namestr = $studentData[4];
            $pdf->SetOverprint(true, true, 0);
            $pdf->SetFont($ArialB, '', 10, '', false);
            $pdf->SetTextSpotColor('Clear', 100);
            $pdf->SetXY($x, $y);
            $pdf->StartTransform();
            $pdf->Rotate(90);
            $pdf->Cell(0, 0, $namestr, 0, false, 'L');
            $pdf->StopTransform();
            $pdf->SetOverprint(false, false, 0);

            $pdfBig->SetOverprint(true, true, 0);
            $pdfBig->SetFont($ArialB, '', 10, '', false);
            $pdfBig->SetTextSpotColor('Clear', 100);
            $pdfBig->SetXY($x, $y);
            $pdfBig->StartTransform();
            $pdfBig->Rotate(90);
            $pdfBig->Cell(0, 0, $namestr, 0, false, 'L');
            $pdfBig->StopTransform();
            $pdfBig->SetOverprint(false, false, 0);

            $serial_no=$GUID=$studentData[0];
          
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

                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'RRMU-C',$admin_id,$card_serial_no);
                //$card_serial_no=$card_serial_no+1;
            }
            //$card_serial_no=$card_serial_no+1;
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
        }

        //if($previewPdf!=1){
            //$this->updateCardNo('RRMU-C',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
        //}
        $msg = '';
        $file_name =  str_replace("/", "_",'RRMU-C'.date("Ymdhms")).'.pdf';
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;

        //$file_name_inv='INV_'.$file_name;
        //$filenameInvisible = public_path().'/backend/tcpdf/examples/'.$file_name_inv;
        
        $pdfBig->output($filename,'F');

        if($previewPdf!=1){
            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
            @unlink($filename);

            /*$aws_qr = \File::copy($filenameInvisible,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name_inv);
            @unlink($filenameInvisible);*/

            $no_of_records = count($studentDataOrg);
            $user = $admin_id['username'];
            $template_name="RRMU-C";
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
            $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download visible data file.";
        }else{
            
            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$file_name);
            @unlink($filename);
            $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
            $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
            $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name;
            $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/preview/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
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
        
        $aws_qr = \File::copy($file2,$pdfActualPath);
            
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
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>'T-'.$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }else{
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);    
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
        unlink($tmpname);
        mkdir($tmpname);
        return $tmpname;
    }

    /*public function CreateMessage($tmpDir, $name = "",$font_size,$print_color) // handled for font_size 13 only
    {
        if($name == "")
            return;
        $name = strtoupper($name);

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
      
            $filename = public_path()."/backend/canvas/ghost_images/green/F13_H10_W360.png";
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
    }*/

    public function CreateMessage($tmpDir, $name = "",$font_size,$print_color) // handled for font_size 12 only
    {
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
                
            $filename = public_path()."/backend/canvas/ghost_images/green/F12_H8_W288.png";
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
    }



    /*public function CreateMessage($tmpDir, $name = "",$font_size,$print_color)
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
    }*/

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
