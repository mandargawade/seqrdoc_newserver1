<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use App\models\SuperAdmin;
use Session,TCPDF,TCPDF_FONTS,Auth,DB;
use App\Http\Requests\ExcelValidationRequest;
use App\Http\Requests\MappingDatabaseRequest;
use App\Http\Requests\TemplateMapRequest;
use App\Http\Requests\TemplateMasterRequest;
use App\Imports\TemplateMapImport
;use App\Imports\TemplateMasterImport;
use App\Jobs\PDFGenerateJob;
use App\models\BackgroundTemplateMaster;
use App\Events\BarcodeImageEvent;
use App\Events\TemplateEvent;
use App\models\FontMaster;
use App\models\FieldMaster;
use App\models\User;
use App\models\StudentTable;
use App\models\SbStudentTable;
use Maatwebsite\Excel\Facades\Excel;
use App\models\SystemConfig;
use App\Jobs\PreviewPDFGenerateJob;
use App\Exports\TemplateMasterExport;
use Storage;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\Config;
use App\models\PrintingDetail;
use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;

use App\Helpers\CoreHelper;
use Helper;
use App\Jobs\ValidateExcelCavendishSOMJob;
use App\Jobs\ValidateExcelCavendishBITJob;
use App\Jobs\ValidateExcelCavendishAESSJob;
use App\Jobs\PdfGenerateCavendishSOMJob;
use App\Jobs\PdfGenerateCavendishBITJob;
use App\Jobs\PdfGenerateCavendishAESSJob;

class CavendishCertificateController extends Controller
{
    public function index(Request $request)
    {
       return view('admin.cavendish.index');
    }

    public function uploadpage(){

      return view('admin.cavendish.index');
    }

    /*public function pdfGenerate(){
        $domain = \Request::getHost();
        
        $subdomain = explode('.', $domain);
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


        $pdf->AddPage();
        
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\RAJ_RISHI_Bg.jpg';

        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        $pdf->setPageMark();
        $fontEBPath=public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\E-13B_0.php';

            //EN ROLL NO
            $x = 12;
            $y = 11;   
            $pdf->SetFont($times, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Roll No.: ", 0, false, 'L');


            //EN ROLL NO
            $x = 27;
            $y = 11;  
            $fontEBPath = public_path() . '\\' . $subdomain[0] . '\backend\canvas\fonts\E-13B_0.php';
            $pdf->AddFont('E-13B_0', '', $fontEBPath);
            $pdf->SetFont('E-13B_0', '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, '1234567', 0, false, 'L');


            //Serial NO
            $x = 165;
            $y = 11;   
            $pdf->SetFont($times, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Serial No. :", 0, false, 'L');


            $x = 182;
            $y = 11;   
            $pdf->SetFont($times, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, '220201234', 0, false, 'L');


            $barcodex = 166;
            $barcodey = 17;
            $barcodeWidth = 30;
            $barodeHeight = 5;

            $pdf->SetAlpha(1);
            // Barcode CODE_39 - Roll no 
            $pdf->write1DBarcode('1234567', 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');
           

            //EN ROLL NO
            $x = 12;
            $y = 16;   
            $pdf->SetFont($times, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Enroll No.: ", 0, false, 'L');


            //EN ROLL NO
            $x = 30;
            $y = 16;   
            $pdf->SetFont($arialNarrow, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, '2022/SSSL002', 0, false, 'L');


            $x = 10;
            $y = 88;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "We, the Chancellor, the Vice- Chancellor and Members of the Board of", 0, false, 'C');


            $x = 26;
            $y = 97.5;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Management of ", 0, false, 'L');


            $x = 65;   
            $pdf->SetFont($timesbd, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Raj Rishi Bhartrihari Matsya University, Alwar', 0, false, 'L');

            $x = 10;
            $y = 106.5;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "certify that", 0, false, 'C');


            $name = 'Rahul Gupta';

            $x = 10;
            $y = 116;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($ArialB, '', 20, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, $name, 0, false, 'C');

            
            $x = 10;
            $y = 126.5;
            $pdf->SetTextColor(0,0,0);  
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "having passed the University Examination held in the year 2020 with", 0, false, 'C');

            $x = 10;
            $y = 136.5;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "First Division", 0, false, 'C');


            $x = 10;
            $y = 145;   
            $pdf->SetFont($times, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "has been conferred the degree of", 0, false, 'C');


            $x = 10;
            $y = 155;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($OLD_ENGL, '', 30, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Bachelor of Commerce', 0, false, 'C');

            
            $x = 10;
            $y = 170;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'jkt _f"k HkrZ`gfj eRL; fo’ofo|ky;] vyoj', 0, false, 'C');


            $x = 10;
            $y = 179.5;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'dqykf/kifr]dqyifr rFkk izcaU/k e.My ds lnL; izekf.kr djrs gSa fd', 0, false, 'C');



            $x = 10;
            $y = 188;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($Kruti_Dev_730k, '', 28, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'jkgqy xqIrk', 0, false, 'C');


            $x = 40;
            $y = 203;
            $pdf->SetTextColor(0,0,0);     
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "dks bl fo’ofo|ky; dh lu", 0, false, 'L');

            $x = 105;   
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, " 2020 ", 0, false, 'L');


            $x = 122.5;
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "esa vk;ksftr ijh{kk e", 0, false, 'L');


            $x = 10;
            $y = 212;   
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "izFke Js.kh mÙkh.kZ gksus ij", 0, false, 'C');


            $x = 10;
            $y = 219;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($Kruti_Dev_730k, '', 28, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'okf.kT; Lukrd', 0, false, 'C');
            
            $x = 10;
            $y = 233;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($K101, '', 17, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "dh mikf/k iznku dh xbZ A", 0, false, 'C');

            $x = 20;
            $y = 262;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($Arial, '', 12, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Place: Alwar", 0, false, 'L');

            $x = 20;
            $y = 268;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($Arial, '', 12, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Dated : 07/02/2022", 0, false, 'L');

            
            $nameOrg = $name;
            $ghost_font_size = '12';
            $ghostImagex = 21;
            $ghostImagey = 275;
            //$ghostImageWidth = 39.405983333;
            $ghostImageHeight = 8;
            $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
            $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp_cavendish');
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');

            $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);

            
            $str="DATA";
            $codeContents = $str;
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 95;
            $qrCodey = 250;
            $qrCodeWidth =20;
            $qrCodeHeight = 20;
            \QrCode::size(75.6)
                ->backgroundColor(255, 255, 0)
                ->format('png')
                ->generate($codeContents, $qr_code_path);

            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

            $pdf->AddSpotColor('clear', 67.3, 31.2, 0, 20.8); 
 
            $x = 12;
            $y = 273;
            $namestr = 'Rahul Gupta';
            $pdf->SetOverprint(true, true, 0);
            $pdf->SetFont($ArialB, '', 10, '', false);
            $pdf->SetTextSpotColor('clear', 100);
            $pdf->SetXY($x, $y);
            $pdf->StartTransform();
            $pdf->Rotate(90);
            $pdf->Cell(0, 0, $namestr, 0, false, 'L');
            $pdf->StopTransform();
            $pdf->SetOverprint(false, false, 0);

            $pdf->Output('sample.pdf', 'I');
        
    }*/

    /*public function pdfGenerate(){
        $domain = \Request::getHost();
        
        $subdomain = explode('.', $domain);
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


        $pdf->AddPage();
        
        //set background image
        //$template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\RAJ_RISHI_Bg_new.jpg';

        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        $pdf->setPageMark();
        $fontEBPath=public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\E-13B_0.php';

            //EN ROLL NO
            $x = 12;
            $y = 11;   
            $pdf->SetFont($Arial, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Roll No.: ", 0, false, 'L');


            //EN ROLL NO
            $x = 27;
            $y = 11;  
            $fontEBPath = public_path() . '\\' . $subdomain[0] . '\backend\canvas\fonts\E-13B_0.php';
            $pdf->AddFont('E-13B_0', '', $fontEBPath);
            $pdf->SetFont('E-13B_0', '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, '1234567', 0, false, 'L');


            //Serial NO
            $x = 162;
            $y = 11;   
            $pdf->SetFont($Arial, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Serial No. :", 0, false, 'L');


            $x = 179;
            $y = 11;   
            $pdf->SetFont($Arial, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, '220201234', 0, false, 'L');


            $barcodex = 166;
            $barcodey = 17;
            $barcodeWidth = 30;
            $barodeHeight = 5;

            $pdf->SetAlpha(1);
            // Barcode CODE_39 - Roll no 
            $pdf->write1DBarcode('1234567', 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');
           

            //EN ROLL NO
            $x = 12;
            $y = 16;   
            $pdf->SetFont($Arial, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Enroll No.: ", 0, false, 'L');


            //EN ROLL NO
            $x = 30;
            $y = 16;   
            $pdf->SetFont($Arial, '', 10, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, '2018/153972', 0, false, 'L');


            $x = 10;
            $y = 85;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($OLD_ENGL, '', 35.5, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Certificate of Merit', 0, false, 'C');

            $x = 10;
            $y = 102;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 16, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'It is certify that', 0, false, 'C');

            $x = 10;
            $y = 112;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($ArialB, '', 20, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Heena Sharma', 0, false, 'C');

            $x = 10;
            $y = 116;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 14, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Mr./Ms. ...............................................................................................', 0, false, 'C');

            $x = 10;
            $y = 125;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($ArialB, '', 20, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Sunil Bhagwati', 0, false, 'C');

            $x = 10;
            $y = 129;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 14, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'D/o, S/o ...............................................................................................', 0, false, 'C');


            $x = 10;
            $y = 138;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 16, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'secured first rank in the', 0, false, 'C');

            $x = 10;
            $y = 146;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($OLD_ENGL, '', 32, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Master of Commerce-', 0, false, 'C');

            $x = 10;
            $y = 155;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($OLD_ENGL, '', 32, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Accountancy and Business Statistics', 0, false, 'C');

            $x = 130;
            $y = 167;//170
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($ArialB, '', 20, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, '2020', 0, false, 'L');

            $x = 10;
            $y = 169.5;//170
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 16, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Degree Examination the year.......................', 0, false, 'C');

            $x = 10;
            $y = 178.5; //179
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 16, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'He/She is the topper in the above said complete degree examination.', 0, false, 'C');

            $x = 10;
            $y = 187.5;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 16, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'It is also certified that', 0, false, 'C');

            $x = 10;
            $y = 200;
            $pdf->SetTextColor(237, 50, 55);   
            $pdf->SetFont($ArialB, '', 20, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Heena Sharma', 0, false, 'C');

            $x = 10;
            $y = 204;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 14, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Mr./Ms. ...............................................................................................', 0, false, 'C');


            $x = 10;
            $y = 212.5;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 16, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'is the first rank holder among all the students of all the', 0, false, 'C');


            $x = 10;
            $y = 220.5;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 16, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'constituent colleges of the University.', 0, false, 'C');

            $x = 10;
            $y = 228.5;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 16, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'Who appeared for the same examination and also completed', 0, false, 'C');


            $x = 10;
            $y = 236.5;
            $pdf->SetTextColor(62,64,149);   
            $pdf->SetFont($times, '', 16, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, 'the course with in the stipulated time.', 0, false, 'C');



            $x = 16.5;
            $y = 262;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($times, '', 12, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Place: Alwar", 0, false, 'L');

            $x = 16.5;
            $y = 268;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($times, '', 12, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Date of Issued : 28/03/2022", 0, false, 'L');


            $x = 164;
            $y = 268;
            $pdf->SetTextColor(0,0,0);   
            $pdf->SetFont($times, '', 12, '', false);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 0, "Registrar", 0, false, 'L');

            
            $nameOrg = 'Rahul Gupta';
            $ghost_font_size = '12';
            $ghostImagex = 17.5;
            $ghostImagey = 277;
            $ghostImageWidth = 39.405983333;
            $ghostImageHeight = 8;
            $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
            $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');

            $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $w, $ghostImageHeight, "PNG", '', 'L', true, 3600);
            
            $str="DATA";
            $codeContents = $str;
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 95;
            $qrCodey = 250;
            $qrCodeWidth =20;
            $qrCodeHeight = 20;
            \QrCode::size(75.6)
                ->backgroundColor(255, 255, 0)
                ->format('png')
                ->generate($codeContents, $qr_code_path);

            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

            $pdf->AddSpotColor('Clear', 67.3, 31.2, 0, 20.8); 
 
            $x = 10.5;
            $y = 285;
            $namestr = 'Rahul Gupta';
            $pdf->SetOverprint(true, true, 0);
            $pdf->SetFont($ArialB, '', 10, '', false);
            $pdf->SetTextSpotColor('Clear', 100);
            $pdf->SetXY($x, $y);
            $pdf->StartTransform();
            $pdf->Rotate(90);
            $pdf->Cell(0, 0, $namestr, 0, false, 'L');
            $pdf->StopTransform();
            $pdf->SetOverprint(false, false, 0);

            $pdf->Output('sample.pdf', 'I');
        
    }*/

    public function validateExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        ini_set('memory_limit', '4096M');
        $template_id=100;
        $dropdown_template_id = $request['template_id'];
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();


        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
         //check file is uploaded or not
        if($request->hasFile('field_file')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'/backend/canvas/dummy_images/'.$template_id;
            $fullpath = $target_path.'/'.$excelfile;

            if(!is_dir($target_path)){   
                mkdir($target_path, 0777);
            }

            if($request['field_file']->move($target_path,$excelfile)){
                //get excel file data
                
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Csv';
                }
                else{
                    $inputFileType = 'Csv';
                }
                $auth_site_id=Auth::guard('admin')->user()->site_id;

                $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    if($systemConfig['sandboxing'] == 1){
                        $sandbox_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                        //if directory not exist make directory
                        if(!is_dir($sandbox_directory)){
                
                            mkdir($sandbox_directory, 0777);
                        }

                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                        
                    }else{
                        
                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                    }
                }
                else{

                      $sandbox_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                    //if directory not exist make directory
                    if(!is_dir($sandbox_directory)){
            
                        mkdir($sandbox_directory, 0777);
                    }

                    if($systemConfig['sandboxing'] == 1){

                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile);
                        
                    }else{
                        
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile);
                        
                    }

                    //dd($fullpath);
                }

                if($dropdown_template_id == 1 || $dropdown_template_id == 4)
                {
                    $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                    /**  Load $inputFileName to a Spreadsheet Object  **/
                    $objPHPExcel1 = $objReader->load($fullpath);
                    $sheet1 = $objPHPExcel1->getSheet(0);
                    $highestColumn1 = $sheet1->getHighestColumn();
                    $highestRow1 = $sheet1->getHighestDataRow();
                    $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                    //dd($rowData1);
                    //For checking certificate limit updated by Mandar
                    $recordToGenerate=$highestRow1-1;
                    $checkStatus = CoreHelper::checkMaxCertificateLimit($recordToGenerate);
                    if(!$checkStatus['status']){
                      return response()->json($checkStatus);
                    }
                     $excelData=array('rowData1'=>$rowData1,'auth_site_id'=>$auth_site_id,'dropdown_template_id'=>$dropdown_template_id);

                    //dd($excelData);
                    $response = $this->dispatch(new ValidateExcelCavendishSOMJob($excelData));

                    $responseData =$response->getData();
                }else if ($dropdown_template_id == 2) {

                    $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                    /**  Load $inputFileName to a Spreadsheet Object  **/
                    $objPHPExcel1 = $objReader->load($fullpath);
                    $sheet1 = $objPHPExcel1->getSheet(0);
                    $highestColumn1 = $sheet1->getHighestColumn();
                    $highestRow1 = $sheet1->getHighestDataRow();
                    $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                    //dd($rowData1);
                    //For checking certificate limit updated by Mandar
                    $recordToGenerate=$highestRow1-1;
                    $checkStatus = CoreHelper::checkMaxCertificateLimit($recordToGenerate);
                    if(!$checkStatus['status']){
                      return response()->json($checkStatus);
                    }
                     $excelData=array('rowData1'=>$rowData1,'auth_site_id'=>$auth_site_id,'dropdown_template_id'=>$dropdown_template_id);

                    //dd($excelData);
                    $response = $this->dispatch(new ValidateExcelCavendishBITJob($excelData));

                    $responseData =$response->getData();
                } else if ($dropdown_template_id == 3) {

                    $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                    /**  Load $inputFileName to a Spreadsheet Object  **/
                    $objPHPExcel1 = $objReader->load($fullpath);
                    $sheet1 = $objPHPExcel1->getSheet(0);
                    $highestColumn1 = $sheet1->getHighestColumn();
                    $highestRow1 = $sheet1->getHighestDataRow();
                    $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                    //dd($rowData1);
                    //For checking certificate limit updated by Mandar
                    $recordToGenerate=$highestRow1-1;
                    $checkStatus = CoreHelper::checkMaxCertificateLimit($recordToGenerate);
                    if(!$checkStatus['status']){
                      return response()->json($checkStatus);
                    }
                     $excelData=array('rowData1'=>$rowData1,'auth_site_id'=>$auth_site_id,'dropdown_template_id'=>$dropdown_template_id);

                    //dd($excelData);
                    $response = $this->dispatch(new ValidateExcelCavendishAESSJob($excelData));

                    $responseData =$response->getData();
                }

                
               
                if($responseData->success){
                    $old_rows=$responseData->old_rows;
                    $new_rows=$responseData->new_rows;
                }else{
                   return $response;
                }
               
            }

            
            if (file_exists($fullpath)) {
                unlink($fullpath);
            }
            

            //For custom Loader
			$randstr=CoreHelper::genRandomStr(5);
            $jsonArr=array();
            $jsonArr['token'] = $randstr.'_'.time();
            $jsonArr['status'] ='200';
            $jsonArr['message'] ='Pdf generation started...';
            $jsonArr['recordsToGenerate'] =$recordToGenerate;
            $jsonArr['generatedCertificates'] =0;
            $jsonArr['pendingCertificates'] =$recordToGenerate;
            $jsonArr['timePerCertificate'] =0;
            $jsonArr['isGenerationCompleted'] =0;
            $jsonArr['totalSecondsForGeneration'] =0;
            $loaderData=CoreHelper::createLoaderJson($jsonArr,1);

        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows,'loaderFile'=>$loaderData['fileName'],'loader_token'=>$loaderData['loader_token']]);

        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }


    }

    public function uploadfile(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        ini_set('memory_limit', '4096M');
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        
        //For custom loader
        $loader_token=$request['loader_token'];

        $template_id = 100;
        $dropdown_template_id = $request['template_id'];
        $previewPdf = array($request['previewPdf'],$request['previewWithoutBg']);

        //check file is uploaded or not
        if($request->hasFile('field_file')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'/backend/canvas/dummy_images/'.$template_id;
            $fullpath = $target_path.'/'.$excelfile;

            if(!is_dir($target_path)){
                mkdir($target_path, 0777);
            }

            if($request['field_file']->move($target_path,$excelfile)){
                //get excel file data
                
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                if($ext == 'csv' || $ext == 'Csv'){
                    $inputFileType = 'Csv';
                }
                else{
                    $inputFileType = 'Csv';
                }
                $auth_site_id=Auth::guard('admin')->user()->site_id;

                $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                if($get_file_aws_local_flag->file_aws_local == '1'){
                    if($systemConfig['sandboxing'] == 1){
                        $sandbox_directory = \Config::get('constant.amazone_path').$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                        //if directory not exist make directory
                        if(!is_dir($sandbox_directory)){
                
                            mkdir($sandbox_directory, 0777);
                        }

                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                        
                    }else{
                        
                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                    }
                }
                else{

                      $sandbox_directory = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/';
                    //if directory not exist make directory
                    if(!is_dir($sandbox_directory)){
            
                        mkdir($sandbox_directory, 0777);
                    }

                    if($systemConfig['sandboxing'] == 1){
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile);
                    }else{
                        $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile);
                        
                    }
                }
                    $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                    /**  Load $inputFileName to a Spreadsheet Object  **/
                    $objPHPExcel1 = $objReader->load($fullpath);
                    $sheet1 = $objPHPExcel1->getSheet(0);
                    $highestColumn1 = $sheet1->getHighestColumn();
                    $highestRow1 = $sheet1->getHighestDataRow();
                    
                    if($dropdown_template_id==1 || $dropdown_template_id==4){
                        $records = $this->getArrayFromSOMExcel($sheet1);
                    } else if($dropdown_template_id==2 || $dropdown_template_id==3) {
                        $records = $this->getArrayFromBIT_AESS_Excel($sheet1);
                    }


                    $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
            }       
        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }

     
        unset($rowData1[0]);
        $rowData1=array_values($rowData1);
        //store ghost image
        //$tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();

        
        $pdfData=array('studentDataOrg'=>$records,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token);         
        // $pdfData1=array('studentDataOrg'=>$records1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token);         
        //For Custom Loader

        /*print_r($pdfData);
        exit;*/
        // print_r($dropdown_template_id);
        // die();
        
        if($dropdown_template_id==1 || $dropdown_template_id==4){
            $link = $this->dispatch(new PdfGenerateCavendishSOMJob($pdfData));
        }else if($dropdown_template_id==2){
            $link = $this->dispatch(new PdfGenerateCavendishBITJob($pdfData));
        }else if($dropdown_template_id==3){
            $link = $this->dispatch(new PdfGenerateCavendishAESSJob($pdfData));
        }
        return response()->json(['success'=>true,'message'=>'Certificates generated successfully.','link'=>$link]);
    }

 
    public function addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,$blob)
    {

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        $pdfActualPath=public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName;

        
        copy($file1, $file2);
        
        $aws_qr = \File::copy($file2,$pdfActualPath);
                
            
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

        if($systemConfig['sandboxing'] == 1){
        $resultu = SbStudentTable::where('serial_no','T-'.$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = SbStudentTable::create(['serial_no'=>'T-'.$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }else{
        $resultu = StudentTable::where('serial_no',$serial_no)->update(['status'=>'0']);
        // Insert the new record
        
        $result = StudentTable::create(['serial_no'=>$serial_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }
        
    }

    public function getPrintCount($serial_no)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    }

    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$card_serial_no)
    {
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        $auth_site_id=Auth::guard('admin')->user()->site_id;

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
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 10), UNSIGNED)), 0) AS next_num "
                . "FROM printing_details WHERE SUBSTR(print_serial_no, 1, 9) = '$current_year'");
        //get next num
        $maxNum = $result[0]->next_num + 1;
        
        return $current_year . $maxNum;
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
        $auth_site_id=Auth::guard('admin')->user()->site_id;

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


    function getArrayFromSOMExcel($sheet1){

        $records = array();
        // Certificate top name
        $certificateTopName = $sheet1->rangeToArray('A4', NULL, TRUE, FALSE);
        $certificateTopName = $certificateTopName[0][0];
        $records['certificateTopName'] = $certificateTopName;
        
        // Certificate address
        $certificateAddress = $sheet1->rangeToArray('A5', NULL, TRUE, FALSE);
        $certificateAddress = $certificateAddress[0][0];
        $records['certificateAddress'] = $certificateAddress;

        // Certificate school
        $certificateSchool = $sheet1->rangeToArray('A7', NULL, TRUE, FALSE);
        $certificateSchool = $certificateSchool[0][0];
        $records['certificateSchool'] = $certificateSchool;

        // Certificate statement of results
        $certificateStm = $sheet1->rangeToArray('A9', NULL, TRUE, FALSE);
        $certificateStm = $certificateStm[0][0];
        $records['certificateStm'] = $certificateStm;

        // Certificate name
        $certificateName = $sheet1->rangeToArray('B11', NULL, TRUE, FALSE);
        $certificateName = $certificateName[0][0];
        $records['certificateName'] = $certificateName;

        // Certificate gender
        $certificateGender = $sheet1->rangeToArray('B12', NULL, TRUE, FALSE);
        $certificateGender = $certificateGender[0][0];
        $records['certificateGender'] = $certificateGender;

        // Certificate DOB
        $certificateDOB = $sheet1->rangeToArray('B13', NULL, TRUE, FALSE);
        $certificateDOB = $certificateDOB[0][0];
        $records['certificateDOB'] = $certificateDOB;

        // Certificate Nationality
        $certificateNationality = $sheet1->rangeToArray('B14', NULL, TRUE, FALSE);
        $certificateNationality = $certificateNationality[0][0];
        $records['certificateNationality'] = $certificateNationality;

        // Certificate Reg No
        $certificateRegNo = $sheet1->rangeToArray('G11', NULL, TRUE, FALSE);
        $certificateRegNo = $certificateRegNo[0][0];
        $records['certificateRegNo'] = $certificateRegNo;

        // Certificate Programme
        $certificateProgramme = $sheet1->rangeToArray('G12', NULL, TRUE, FALSE);
        $certificateProgramme = $certificateProgramme[0][0];
        $records['certificateProgramme'] = $certificateProgramme;

        // Cert School
        $certSchool = $sheet1->rangeToArray('G13', NULL, TRUE, FALSE);
        $certSchool = $certSchool[0][0];
        $records['certSchool'] = $certSchool;

        // Certificate year of entry
        $certificateYearEntry = $sheet1->rangeToArray('G14', NULL, TRUE, FALSE);
        $certificateYearEntry = $certificateYearEntry[0][0];
        $records['certificateYearEntry'] = $certificateYearEntry;

        // Certificate award
        $certificateAward = $sheet1->rangeToArray('A17', NULL, TRUE, FALSE);
        $certificateAward = $certificateAward[0][0];
        $records['certificateAward'] = $certificateAward;

        // Certificate award1
        $certificateAward1 = $sheet1->rangeToArray('A16', NULL, TRUE, FALSE);
        $certificateAward1 = $certificateAward1[0][0];
        $records['certificateAward1'] = $certificateAward1;

        // Foundation year data //
        // Certificate Foundation courseCode1
        $foundationCourseCode1 = $sheet1->rangeToArray('A23', NULL, TRUE, FALSE);
        $foundationCourseCode1 = $foundationCourseCode1[0][0];
        $records['foundationCourseCode1'] = $foundationCourseCode1;

        // Certificate Foundation courseCode2
        $foundationCourseCode2 = $sheet1->rangeToArray('A24', NULL, TRUE, FALSE);
        $foundationCourseCode2 = $foundationCourseCode2[0][0];
        $records['foundationCourseCode2'] = $foundationCourseCode2;

        // Certificate Foundation courseCode3
        $foundationCourseCode3 = $sheet1->rangeToArray('A25', NULL, TRUE, FALSE);
        $foundationCourseCode3 = $foundationCourseCode3[0][0];
        $records['foundationCourseCode3'] = $foundationCourseCode3;


        // Certificate Foundation courseName1
        $foundationCourseName1 = $sheet1->rangeToArray('B23', NULL, TRUE, FALSE);
        $foundationCourseName1 = $foundationCourseName1[0][0];
        $records['foundationCourseName1'] = $foundationCourseName1;

        // Certificate Foundation courseName2
        $foundationCourseName2 = $sheet1->rangeToArray('B24', NULL, TRUE, FALSE);
        $foundationCourseName2 = $foundationCourseName2[0][0];
        $records['foundationCourseName2'] = $foundationCourseName2;

        // Certificate Foundation courseName3
        $foundationCourseName3 = $sheet1->rangeToArray('B25', NULL, TRUE, FALSE);
        $foundationCourseName3 = $foundationCourseName3[0][0];
        $records['foundationCourseName3'] = $foundationCourseName3;

        // Certificate Foundation Grade1
        $foundationGrade1 = $sheet1->rangeToArray('H23', NULL, TRUE, FALSE);
        $foundationGrade1 = $foundationGrade1[0][0];
        $records['foundationGrade1'] = $foundationGrade1;

        // Certificate Foundation Grade2
        $foundationGrade2 = $sheet1->rangeToArray('H24', NULL, TRUE, FALSE);
        $foundationGrade2 = $foundationGrade2[0][0];
        $records['foundationGrade2'] = $foundationGrade2;

        // Certificate Foundation Grade3
        $foundationGrade3 = $sheet1->rangeToArray('H25', NULL, TRUE, FALSE);
        $foundationGrade3 = $foundationGrade3[0][0];
        $records['foundationGrade3'] = $foundationGrade3;

        // Certificate Foundation Remark1
        $foundationRemark1 = $sheet1->rangeToArray('I23', NULL, TRUE, FALSE);
        $foundationRemark1 = $foundationRemark1[0][0];
        $records['foundationRemark1'] = $foundationRemark1;

        // Certificate Foundation Remark2
        $foundationRemark2 = $sheet1->rangeToArray('I24', NULL, TRUE, FALSE);
        $foundationRemark2 = $foundationRemark2[0][0];
        $records['foundationRemark2'] = $foundationRemark2;

        // Certificate Foundation Remark3
        $foundationRemark3 = $sheet1->rangeToArray('I25', NULL, TRUE, FALSE);
        $foundationRemark3 = $foundationRemark3[0][0];
        $records['foundationRemark3'] = $foundationRemark3;
        // Foundation year data //


        // Year1 data //
        // Certificate Year 1 courseCode1
        $year1CourseCode1 = $sheet1->rangeToArray('A33', NULL, TRUE, FALSE);
        $year1CourseCode1 = $year1CourseCode1[0][0];
        $records['year1CourseCode1'] = $year1CourseCode1;

        // Certificate Year 1 courseCode2
        $year1CourseCode2 = $sheet1->rangeToArray('A34', NULL, TRUE, FALSE);
        $year1CourseCode2 = $year1CourseCode2[0][0];
        $records['year1CourseCode2'] = $year1CourseCode2;

        // Certificate Year 1 courseCode3
        $year1CourseCode3 = $sheet1->rangeToArray('A35', NULL, TRUE, FALSE);
        $year1CourseCode3 = $year1CourseCode3[0][0];
        $records['year1CourseCode3'] = $year1CourseCode3;

        // Certificate Year 1 courseCode4
        $year1CourseCode4 = $sheet1->rangeToArray('A36', NULL, TRUE, FALSE);
        $year1CourseCode4 = $year1CourseCode4[0][0];
        $records['year1CourseCode4'] = $year1CourseCode4;

        // Certificate Year 1 courseCode5
        $year1CourseCode5 = $sheet1->rangeToArray('A37', NULL, TRUE, FALSE);
        $year1CourseCode5 = $year1CourseCode5[0][0];
        $records['year1CourseCode5'] = $year1CourseCode5;



        // Certificate Year 1 courseName1
        $year1CourseName1 = $sheet1->rangeToArray('B33', NULL, TRUE, FALSE);
        $year1CourseName1 = $year1CourseName1[0][0];
        $records['year1CourseName1'] = $year1CourseName1;

        // Certificate Year 1 courseName2
        $year1CourseName2 = $sheet1->rangeToArray('B34', NULL, TRUE, FALSE);
        $year1CourseName2 = $year1CourseName2[0][0];
        $records['year1CourseName2'] = $year1CourseName2;

        // Certificate Year 1 courseName3
        $year1CourseName3 = $sheet1->rangeToArray('B35', NULL, TRUE, FALSE);
        $year1CourseName3 = $year1CourseName3[0][0];
        $records['year1CourseName3'] = $year1CourseName3;

        // Certificate Year 1 courseName4
        $year1CourseName4 = $sheet1->rangeToArray('B36', NULL, TRUE, FALSE);
        $year1CourseName4 = $year1CourseName4[0][0];
        $records['year1CourseName4'] = $year1CourseName4;

        // Certificate Year 1 courseName5
        $year1CourseName5 = $sheet1->rangeToArray('B37', NULL, TRUE, FALSE);
        $year1CourseName5 = $year1CourseName5[0][0];
        $records['year1CourseName5'] = $year1CourseName5;


        // Certificate Year 1 Grade1
        $year1Grade1 = $sheet1->rangeToArray('H33', NULL, TRUE, FALSE);
        $year1Grade1 = $year1Grade1[0][0];
        $records['year1Grade1'] = $year1Grade1;

        // Certificate Year 1 Grade2
        $year1Grade2 = $sheet1->rangeToArray('H34', NULL, TRUE, FALSE);
        $year1Grade2 = $year1Grade2[0][0];
        $records['year1Grade2'] = $year1Grade2;

        // Certificate Year 1 Grade3
        $year1Grade3 = $sheet1->rangeToArray('H35', NULL, TRUE, FALSE);
        $year1Grade3 = $year1Grade3[0][0];
        $records['year1Grade3'] = $year1Grade3;

        // Certificate Year 1 Grade4
        $year1Grade4 = $sheet1->rangeToArray('H36', NULL, TRUE, FALSE);
        $year1Grade4 = $year1Grade4[0][0];
        $records['year1Grade4'] = $year1Grade4;

        // Certificate Year 1 Grade5
        $year1Grade5 = $sheet1->rangeToArray('H37', NULL, TRUE, FALSE);
        $year1Grade5 = $year1Grade5[0][0];
        $records['year1Grade5'] = $year1Grade5;


        // Certificate Year 1 Remark1
        $year1Remark1 = $sheet1->rangeToArray('I33', NULL, TRUE, FALSE);
        $year1Remark1 = $year1Remark1[0][0];
        $records['year1Remark1'] = $year1Remark1;

        // Certificate Year 1 Remark2
        $year1Remark2 = $sheet1->rangeToArray('I34', NULL, TRUE, FALSE);
        $year1Remark2 = $year1Remark2[0][0];
        $records['year1Remark2'] = $year1Remark2;

        // Certificate Year 1 Remark3
        $year1Remark3 = $sheet1->rangeToArray('I35', NULL, TRUE, FALSE);
        $year1Remark3 = $year1Remark3[0][0];
        $records['year1Remark3'] = $year1Remark3;

        // Certificate Year 1 Remark4
        $year1Remark4 = $sheet1->rangeToArray('I36', NULL, TRUE, FALSE);
        $year1Remark4 = $year1Remark4[0][0];
        $records['year1Remark4'] = $year1Remark4;

        // Certificate Year 1 Remark5
        $year1Remark5 = $sheet1->rangeToArray('I37', NULL, TRUE, FALSE);
        $year1Remark5 = $year1Remark5[0][0];
        $records['year1Remark5'] = $year1Remark5;
        // Year 1 data //



        // Year2 data //
        // Certificate Year 2 courseCode1
        $year2CourseCode1 = $sheet1->rangeToArray('A43', NULL, TRUE, FALSE);
        $year2CourseCode1 = $year2CourseCode1[0][0];
        $records['year2CourseCode1'] = $year2CourseCode1;

        // Certificate Year 2 courseCode2
        $year2CourseCode2 = $sheet1->rangeToArray('A44', NULL, TRUE, FALSE);
        $year2CourseCode2 = $year2CourseCode2[0][0];
        $records['year2CourseCode2'] = $year2CourseCode2;

        // Certificate Year 2 courseCode3
        $year2CourseCode3 = $sheet1->rangeToArray('A45', NULL, TRUE, FALSE);
        $year2CourseCode3 = $year2CourseCode3[0][0];
        $records['year2CourseCode3'] = $year2CourseCode3;

        // Certificate Year 2 courseCode4
        $year2CourseCode4 = $sheet1->rangeToArray('A46', NULL, TRUE, FALSE);
        $year2CourseCode4 = $year2CourseCode4[0][0];
        $records['year2CourseCode4'] = $year2CourseCode4;

        // Certificate Year 2 courseCode5
        $year2CourseCode5 = $sheet1->rangeToArray('A47', NULL, TRUE, FALSE);
        $year2CourseCode5 = $year2CourseCode5[0][0];
        $records['year2CourseCode5'] = $year2CourseCode5;

        // Certificate Year 2 courseCode6
        $year2CourseCode6 = $sheet1->rangeToArray('A48', NULL, TRUE, FALSE);
        $year2CourseCode6 = $year2CourseCode6[0][0];
        $records['year2CourseCode6'] = $year2CourseCode6;


        // Certificate Year 2 courseName1
        $year2CourseName1 = $sheet1->rangeToArray('B43', NULL, TRUE, FALSE);
        $year2CourseName1 = $year2CourseName1[0][0];
        $records['year2CourseName1'] = $year2CourseName1;

        // Certificate Year 2 courseName2
        $year2CourseName2 = $sheet1->rangeToArray('B44', NULL, TRUE, FALSE);
        $year2CourseName2 = $year2CourseName2[0][0];
        $records['year2CourseName2'] = $year2CourseName2;

        // Certificate Year 2 courseName3
        $year2CourseName3 = $sheet1->rangeToArray('B45', NULL, TRUE, FALSE);
        $year2CourseName3 = $year2CourseName3[0][0];
        $records['year2CourseName3'] = $year2CourseName3;

        // Certificate Year 2 courseName4
        $year2CourseName4 = $sheet1->rangeToArray('B46', NULL, TRUE, FALSE);
        $year2CourseName4 = $year2CourseName4[0][0];
        $records['year2CourseName4'] = $year2CourseName4;

        // Certificate Year 2 courseName5
        $year2CourseName5 = $sheet1->rangeToArray('B47', NULL, TRUE, FALSE);
        $year2CourseName5 = $year2CourseName5[0][0];
        $records['year2CourseName5'] = $year2CourseName5;

        // Certificate Year 2 courseName6
        $year2CourseName6 = $sheet1->rangeToArray('B48', NULL, TRUE, FALSE);
        $year2CourseName6 = $year2CourseName6[0][0];
        $records['year2CourseName6'] = $year2CourseName6;


        // Certificate Year 2 Grade1
        $year2Grade1 = $sheet1->rangeToArray('H43', NULL, TRUE, FALSE);
        $year2Grade1 = $year2Grade1[0][0];
        $records['year2Grade1'] = $year2Grade1;

        // Certificate Year 2 Grade2
        $year2Grade2 = $sheet1->rangeToArray('H44', NULL, TRUE, FALSE);
        $year2Grade2 = $year2Grade2[0][0];
        $records['year2Grade2'] = $year2Grade2;

        // Certificate Year 2 Grade3
        $year2Grade3 = $sheet1->rangeToArray('H45', NULL, TRUE, FALSE);
        $year2Grade3 = $year2Grade3[0][0];
        $records['year2Grade3'] = $year2Grade3;

        // Certificate Year 2 Grade4
        $year2Grade4 = $sheet1->rangeToArray('H46', NULL, TRUE, FALSE);
        $year2Grade4 = $year2Grade4[0][0];
        $records['year2Grade4'] = $year2Grade4;

        // Certificate Year 2 Grade5
        $year2Grade5 = $sheet1->rangeToArray('H47', NULL, TRUE, FALSE);
        $year2Grade5 = $year2Grade5[0][0];
        $records['year2Grade5'] = $year2Grade5;

        // Certificate Year 2 Grade6
        $year2Grade6 = $sheet1->rangeToArray('H48', NULL, TRUE, FALSE);
        $year2Grade6 = $year2Grade6[0][0];
        $records['year2Grade6'] = $year2Grade6;


        // Certificate Year 2 Remark1
        $year2Remark1 = $sheet1->rangeToArray('I43', NULL, TRUE, FALSE);
        $year2Remark1 = $year2Remark1[0][0];
        $records['year2Remark1'] = $year2Remark1;

        // Certificate Year 2 Remark2
        $year2Remark2 = $sheet1->rangeToArray('I44', NULL, TRUE, FALSE);
        $year2Remark2 = $year2Remark2[0][0];
        $records['year2Remark2'] = $year2Remark2;

        // Certificate Year 2 Remark3
        $year2Remark3 = $sheet1->rangeToArray('I45', NULL, TRUE, FALSE);
        $year2Remark3 = $year2Remark3[0][0];
        $records['year2Remark3'] = $year2Remark3;

        // Certificate Year 2 Remark4
        $year2Remark4 = $sheet1->rangeToArray('I46', NULL, TRUE, FALSE);
        $year2Remark4 = $year2Remark4[0][0];
        $records['year2Remark4'] = $year2Remark4;

        // Certificate Year 2 Remark5
        $year2Remark5 = $sheet1->rangeToArray('I47', NULL, TRUE, FALSE);
        $year2Remark5 = $year2Remark5[0][0];
        $records['year2Remark5'] = $year2Remark5;

        // Certificate Year 2 Remark6
        $year2Remark6 = $sheet1->rangeToArray('I48', NULL, TRUE, FALSE);
        $year2Remark6 = $year2Remark6[0][0];
        $records['year2Remark6'] = $year2Remark6;

        // Year2 data //



        // Year3 data //
        // Certificate Year 3 courseCode1
        $year3CourseCode1 = $sheet1->rangeToArray('A53', NULL, TRUE, FALSE);
        $year3CourseCode1 = $year3CourseCode1[0][0];
        $records['year3CourseCode1'] = $year3CourseCode1;

        // Certificate Year 3 courseCode2
        $year3CourseCode2 = $sheet1->rangeToArray('A54', NULL, TRUE, FALSE);
        $year3CourseCode2 = $year3CourseCode2[0][0];
        $records['year3CourseCode2'] = $year3CourseCode2;

        // Certificate Year 3 courseCode3
        $year3CourseCode3 = $sheet1->rangeToArray('A55', NULL, TRUE, FALSE);
        $year3CourseCode3 = $year3CourseCode3[0][0];
        $records['year3CourseCode3'] = $year3CourseCode3;

        // Certificate Year 3 courseCode4
        $year3CourseCode4 = $sheet1->rangeToArray('A56', NULL, TRUE, FALSE);
        $year3CourseCode4 = $year3CourseCode4[0][0];
        $records['year3CourseCode4'] = $year3CourseCode4;

        // Certificate Year 3 courseCode5
        $year3CourseCode5 = $sheet1->rangeToArray('A57', NULL, TRUE, FALSE);
        $year3CourseCode5 = $year3CourseCode5[0][0];
        $records['year3CourseCode5'] = $year3CourseCode5;


        // Certificate Year 3 courseName1
        $year3CourseName1 = $sheet1->rangeToArray('B53', NULL, TRUE, FALSE);
        $year3CourseName1 = $year3CourseName1[0][0];
        $records['year3CourseName1'] = $year3CourseName1;

        // Certificate Year 3 courseName2
        $year3CourseName2 = $sheet1->rangeToArray('B54', NULL, TRUE, FALSE);
        $year3CourseName2 = $year3CourseName2[0][0];
        $records['year3CourseName2'] = $year3CourseName2;

        // Certificate Year 3 courseName3
        $year3CourseName3 = $sheet1->rangeToArray('B55', NULL, TRUE, FALSE);
        $year3CourseName3 = $year3CourseName3[0][0];
        $records['year3CourseName3'] = $year3CourseName3;

        // Certificate Year 3 courseName4
        $year3CourseName4 = $sheet1->rangeToArray('B56', NULL, TRUE, FALSE);
        $year3CourseName4 = $year3CourseName4[0][0];
        $records['year3CourseName4'] = $year3CourseName4;

        // Certificate Year 3 courseName5
        $year3CourseName5 = $sheet1->rangeToArray('B57', NULL, TRUE, FALSE);
        $year3CourseName5 = $year3CourseName5[0][0];
        $records['year3CourseName5'] = $year3CourseName5;


        // Certificate Year 3 Grade1
        $year3Grade1 = $sheet1->rangeToArray('H53', NULL, TRUE, FALSE);
        $year3Grade1 = $year3Grade1[0][0];
        $records['year3Grade1'] = $year3Grade1;

        // Certificate Year 3 Grade2
        $year3Grade2 = $sheet1->rangeToArray('H54', NULL, TRUE, FALSE);
        $year3Grade2 = $year3Grade2[0][0];
        $records['year3Grade2'] = $year3Grade2;

        // Certificate Year 3 Grade3
        $year3Grade3 = $sheet1->rangeToArray('H55', NULL, TRUE, FALSE);
        $year3Grade3 = $year3Grade3[0][0];
        $records['year3Grade3'] = $year3Grade3;

        // Certificate Year 3 Grade4
        $year3Grade4 = $sheet1->rangeToArray('H56', NULL, TRUE, FALSE);
        $year3Grade4 = $year3Grade4[0][0];
        $records['year3Grade4'] = $year3Grade4;

        // Certificate Year 3 Grade5
        $year3Grade5 = $sheet1->rangeToArray('H57', NULL, TRUE, FALSE);
        $year3Grade5 = $year3Grade5[0][0];
        $records['year3Grade5'] = $year3Grade5;


        // Certificate Year 3 Remark1
        $year3Remark1 = $sheet1->rangeToArray('I53', NULL, TRUE, FALSE);
        $year3Remark1 = $year3Remark1[0][0];
        $records['year3Remark1'] = $year3Remark1;

        // Certificate Year 3 Remark2
        $year3Remark2 = $sheet1->rangeToArray('I54', NULL, TRUE, FALSE);
        $year3Remark2 = $year3Remark2[0][0];
        $records['year3Remark2'] = $year3Remark2;

        // Certificate Year 3 Remark3
        $year3Remark3 = $sheet1->rangeToArray('I55', NULL, TRUE, FALSE);
        $year3Remark3 = $year3Remark3[0][0];
        $records['year3Remark3'] = $year3Remark3;

        // Certificate Year 3 Remark4
        $year3Remark4 = $sheet1->rangeToArray('I56', NULL, TRUE, FALSE);
        $year3Remark4 = $year3Remark4[0][0];
        $records['year3Remark4'] = $year3Remark4;

        // Certificate Year 3 Remark5
        $year3Remark5 = $sheet1->rangeToArray('I57', NULL, TRUE, FALSE);
        $year3Remark5 = $year3Remark5[0][0];
        $records['year3Remark5'] = $year3Remark5;

        // Year3 data //



        // Year4 data //
        // Certificate Year 4 courseCode1
        $year4CourseCode1 = $sheet1->rangeToArray('A67', NULL, TRUE, FALSE);
        $year4CourseCode1 = $year4CourseCode1[0][0];
        $records['year4CourseCode1'] = $year4CourseCode1;

        // Certificate Year 4 courseCode2
        $year4CourseCode2 = $sheet1->rangeToArray('A68', NULL, TRUE, FALSE);
        $year4CourseCode2 = $year4CourseCode2[0][0];
        $records['year4CourseCode2'] = $year4CourseCode2;

        // Certificate Year 4 courseCode3
        $year4CourseCode3 = $sheet1->rangeToArray('A69', NULL, TRUE, FALSE);
        $year4CourseCode3 = $year4CourseCode3[0][0];
        $records['year4CourseCode3'] = $year4CourseCode3;

        // Certificate Year 4 courseCode4
        $year4CourseCode4 = $sheet1->rangeToArray('A70', NULL, TRUE, FALSE);
        $year4CourseCode4 = $year4CourseCode4[0][0];
        $records['year4CourseCode4'] = $year4CourseCode4;


        // Certificate Year 4 courseName1
        $year4CourseName1 = $sheet1->rangeToArray('B67', NULL, TRUE, FALSE);
        $year4CourseName1 = $year4CourseName1[0][0];
        $records['year4CourseName1'] = $year4CourseName1;

        // Certificate Year 4 courseName2
        $year4CourseName2 = $sheet1->rangeToArray('B68', NULL, TRUE, FALSE);
        $year4CourseName2 = $year4CourseName2[0][0];
        $records['year4CourseName2'] = $year4CourseName2;

        // Certificate Year 4 courseName3
        $year4CourseName3 = $sheet1->rangeToArray('B69', NULL, TRUE, FALSE);
        $year4CourseName3 = $year4CourseName3[0][0];
        $records['year4CourseName3'] = $year4CourseName3;

        // Certificate Year 4 courseName4
        $year4CourseName4 = $sheet1->rangeToArray('B70', NULL, TRUE, FALSE);
        $year4CourseName4 = $year4CourseName4[0][0];
        $records['year4CourseName4'] = $year4CourseName4;


        // Certificate Year 4 Grade1
        $year4Grade1 = $sheet1->rangeToArray('H67', NULL, TRUE, FALSE);
        $year4Grade1 = $year4Grade1[0][0];
        $records['year4Grade1'] = $year4Grade1;

        // Certificate Year 4 Grade2
        $year4Grade2 = $sheet1->rangeToArray('H68', NULL, TRUE, FALSE);
        $year4Grade2 = $year4Grade2[0][0];
        $records['year4Grade2'] = $year4Grade2;

        // Certificate Year 4 Grade3
        $year4Grade3 = $sheet1->rangeToArray('H69', NULL, TRUE, FALSE);
        $year4Grade3 = $year4Grade3[0][0];
        $records['year4Grade3'] = $year4Grade3;

        // Certificate Year 4 Grade4
        $year4Grade4 = $sheet1->rangeToArray('H70', NULL, TRUE, FALSE);
        $year4Grade4 = $year4Grade4[0][0];
        $records['year4Grade4'] = $year4Grade4;


        // Certificate Year 4 Remark1
        $year4Remark1 = $sheet1->rangeToArray('I67', NULL, TRUE, FALSE);
        $year4Remark1 = $year4Remark1[0][0];
        $records['year4Remark1'] = $year4Remark1;

        // Certificate Year 4 Remark2
        $year4Remark2 = $sheet1->rangeToArray('I68', NULL, TRUE, FALSE);
        $year4Remark2 = $year4Remark2[0][0];
        $records['year4Remark2'] = $year4Remark2;

        // Certificate Year 4 Remark3
        $year4Remark3 = $sheet1->rangeToArray('I69', NULL, TRUE, FALSE);
        $year4Remark3 = $year4Remark3[0][0];
        $records['year4Remark3'] = $year4Remark3;

        // Certificate Year 4 Remark4
        $year4Remark4 = $sheet1->rangeToArray('I70', NULL, TRUE, FALSE);
        $year4Remark4 = $year4Remark4[0][0];
        $records['year4Remark4'] = $year4Remark4;

        // Year4 data //



        // Year5 data //
        // Certificate Year 5 courseCode1
        $year5CourseCode1 = $sheet1->rangeToArray('A77', NULL, TRUE, FALSE);
        $year5CourseCode1 = $year5CourseCode1[0][0];
        $records['year5CourseCode1'] = $year5CourseCode1;

        // Certificate Year 5 courseCode2
        $year5CourseCode2 = $sheet1->rangeToArray('A78', NULL, TRUE, FALSE);
        $year5CourseCode2 = $year5CourseCode2[0][0];
        $records['year5CourseCode2'] = $year5CourseCode2;

        // Certificate Year 5 courseCode3
        $year5CourseCode3 = $sheet1->rangeToArray('A79', NULL, TRUE, FALSE);
        $year5CourseCode3 = $year5CourseCode3[0][0];
        $records['year5CourseCode3'] = $year5CourseCode3;

        // Certificate Year 5 courseCode4
        $year5CourseCode4 = $sheet1->rangeToArray('A80', NULL, TRUE, FALSE);
        $year5CourseCode4 = $year5CourseCode4[0][0];
        $records['year5CourseCode4'] = $year5CourseCode4;

        // Certificate Year 5 courseCode5
        $year5CourseCode5 = $sheet1->rangeToArray('A81', NULL, TRUE, FALSE);
        $year5CourseCode5 = $year5CourseCode5[0][0];
        $records['year5CourseCode5'] = $year5CourseCode5;


        // Certificate Year 5 courseName1
        $year5CourseName1 = $sheet1->rangeToArray('B77', NULL, TRUE, FALSE);
        $year5CourseName1 = $year5CourseName1[0][0];
        $records['year5CourseName1'] = $year5CourseName1;

        // Certificate Year 5 courseName2
        $year5CourseName2 = $sheet1->rangeToArray('B78', NULL, TRUE, FALSE);
        $year5CourseName2 = $year5CourseName2[0][0];
        $records['year5CourseName2'] = $year5CourseName2;

        // Certificate Year 5 courseName3
        $year5CourseName3 = $sheet1->rangeToArray('B79', NULL, TRUE, FALSE);
        $year5CourseName3 = $year5CourseName3[0][0];
        $records['year5CourseName3'] = $year5CourseName3;

        // Certificate Year 5 courseName4
        $year5CourseName4 = $sheet1->rangeToArray('B80', NULL, TRUE, FALSE);
        $year5CourseName4 = $year5CourseName4[0][0];
        $records['year5CourseName4'] = $year5CourseName4;

        // Certificate Year 5 courseName5
        $year5CourseName5 = $sheet1->rangeToArray('B81', NULL, TRUE, FALSE);
        $year5CourseName5 = $year5CourseName5[0][0];
        $records['year5CourseName5'] = $year5CourseName5;



        // Certificate Year 5 Grade1
        $year5Grade1 = $sheet1->rangeToArray('H77', NULL, TRUE, FALSE);
        $year5Grade1 = $year5Grade1[0][0];
        $records['year5Grade1'] = $year5Grade1;

        // Certificate Year 5 Grade2
        $year5Grade2 = $sheet1->rangeToArray('H78', NULL, TRUE, FALSE);
        $year5Grade2 = $year5Grade2[0][0];
        $records['year5Grade2'] = $year5Grade2;

        // Certificate Year 5 Grade3
        $year5Grade3 = $sheet1->rangeToArray('H79', NULL, TRUE, FALSE);
        $year5Grade3 = $year5Grade3[0][0];
        $records['year5Grade3'] = $year5Grade3;

        // Certificate Year 5 Grade4
        $year5Grade4 = $sheet1->rangeToArray('H80', NULL, TRUE, FALSE);
        $year5Grade4 = $year5Grade4[0][0];
        $records['year5Grade4'] = $year5Grade4;

        // Certificate Year 5 Grade5
        $year5Grade5 = $sheet1->rangeToArray('H81', NULL, TRUE, FALSE);
        $year5Grade5 = $year5Grade5[0][0];
        $records['year5Grade5'] = $year5Grade5;


        // Certificate Year 5 Remark1
        $year5Remark1 = $sheet1->rangeToArray('I77', NULL, TRUE, FALSE);
        $year5Remark1 = $year5Remark1[0][0];    
        $records['year5Remark1'] = $year5Remark1;

        // Certificate Year 5 Remark2
        $year5Remark2 = $sheet1->rangeToArray('I78', NULL, TRUE, FALSE);
        $year5Remark2 = $year5Remark2[0][0];
        $records['year5Remark2'] = $year5Remark2;

        // Certificate Year 5 Remark3
        $year5Remark3 = $sheet1->rangeToArray('I79', NULL, TRUE, FALSE);
        $year5Remark3 = $year5Remark3[0][0];
        $records['year5Remark3'] = $year5Remark3;

        // Certificate Year 5 Remark4
        $year5Remark4 = $sheet1->rangeToArray('I80', NULL, TRUE, FALSE);
        $year5Remark4 = $year5Remark4[0][0];
        $records['year5Remark4'] = $year5Remark4;

        // Certificate Year 5 Remark5
        $year5Remark5 = $sheet1->rangeToArray('I81', NULL, TRUE, FALSE);
        $year5Remark5 = $year5Remark5[0][0];
        $records['year5Remark5'] = $year5Remark5;

        // Year5 data //



        // Year6 data //
        // Certificate Year 6 courseCode1
        $year6CourseCode1 = $sheet1->rangeToArray('A87', NULL, TRUE, FALSE);
        $year6CourseCode1 = $year6CourseCode1[0][0];
        $records['year6CourseCode1'] = $year6CourseCode1;

        // Certificate Year 6 courseCode2
        $year6CourseCode2 = $sheet1->rangeToArray('A88', NULL, TRUE, FALSE);
        $year6CourseCode2 = $year6CourseCode2[0][0];
        $records['year6CourseCode2'] = $year6CourseCode2;

        // Certificate Year 6 courseCode3
        $year6CourseCode3 = $sheet1->rangeToArray('A89', NULL, TRUE, FALSE);
        $year6CourseCode3 = $year6CourseCode3[0][0];
        $records['year6CourseCode3'] = $year6CourseCode3;

        // Certificate Year 6 courseCode4
        $year6CourseCode4 = $sheet1->rangeToArray('A90', NULL, TRUE, FALSE);
        $year6CourseCode4 = $year6CourseCode4[0][0];
        $records['year6CourseCode4'] = $year6CourseCode4;



        // Certificate Year 6 courseName1
        $year6CourseName1 = $sheet1->rangeToArray('B87', NULL, TRUE, FALSE);
        $year6CourseName1 = $year6CourseName1[0][0];
        $records['year6CourseName1'] = $year6CourseName1;

        // Certificate Year 6 courseName2
        $year6CourseName2 = $sheet1->rangeToArray('B88', NULL, TRUE, FALSE);
        $year6CourseName2 = $year6CourseName2[0][0];
        $records['year6CourseName2'] = $year6CourseName2;

        // Certificate Year 6 courseName3
        $year6CourseName3 = $sheet1->rangeToArray('B89', NULL, TRUE, FALSE);
        $year6CourseName3 = $year6CourseName3[0][0];
        $records['year6CourseName3'] = $year6CourseName3;

        // Certificate Year 6 courseName4
        $year6CourseName4 = $sheet1->rangeToArray('B90', NULL, TRUE, FALSE);
        $year6CourseName4 = $year6CourseName4[0][0];
        $records['year6CourseName4'] = $year6CourseName4;


        // Certificate Year 6 Grade1
        $year6Grade1 = $sheet1->rangeToArray('H87', NULL, TRUE, FALSE);
        $year6Grade1 = $year6Grade1[0][0];
        $records['year6Grade1'] = $year6Grade1;

        // Certificate Year 6 Grade2
        $year6Grade2 = $sheet1->rangeToArray('H88', NULL, TRUE, FALSE);
        $year6Grade2 = $year6Grade2[0][0];
        $records['year6Grade2'] = $year6Grade2;

        // Certificate Year 6 Grade3
        $year6Grade3 = $sheet1->rangeToArray('H89', NULL, TRUE, FALSE);
        $year6Grade3 = $year6Grade3[0][0];
        $records['year6Grade3'] = $year6Grade3;

        // Certificate Year 6 Grade4
        $year6Grade4 = $sheet1->rangeToArray('H90', NULL, TRUE, FALSE);
        $year6Grade4 = $year6Grade4[0][0];
        $records['year6Grade4'] = $year6Grade4;


        // Certificate Year 6 Remark1
        $year6Remark1 = $sheet1->rangeToArray('I87', NULL, TRUE, FALSE);
        $year6Remark1 = $year6Remark1[0][0];    
        $records['year6Remark1'] = $year6Remark1;

        // Certificate Year 6 Remark2
        $year6Remark2 = $sheet1->rangeToArray('I88', NULL, TRUE, FALSE);
        $year6Remark2 = $year6Remark2[0][0];
        $records['year6Remark2'] = $year6Remark2;

        // Certificate Year 6 Remark3
        $year6Remark3 = $sheet1->rangeToArray('I89', NULL, TRUE, FALSE);
        $year6Remark3 = $year6Remark3[0][0];
        $records['year6Remark3'] = $year6Remark3;

        // Certificate Year 6 Remark4
        $year6Remark4 = $sheet1->rangeToArray('I90', NULL, TRUE, FALSE);
        $year6Remark4 = $year6Remark4[0][0];
        $records['year6Remark4'] = $year6Remark4;

        return $records;       
    }


    public function getArrayFromBIT_AESS_Excel($sheet1) {
        $records1 = array();
        // Certificate top name
        $certificateTopName = $sheet1->rangeToArray('A4', NULL, TRUE, FALSE);
        $certificateTopName = $certificateTopName[0][0];
        $records1['certificateTopName'] = $certificateTopName;

        // Certificate address
        $certificateAddress = $sheet1->rangeToArray('A5', NULL, TRUE, FALSE);
        $certificateAddress = $certificateAddress[0][0];
        $records1['certificateAddress'] = $certificateAddress;

        // Certificate school
        $certificateSchool = $sheet1->rangeToArray('A7', NULL, TRUE, FALSE);
        $certificateSchool = $certificateSchool[0][0];
        $records1['certificateSchool'] = $certificateSchool;

        // Certificate statement of results
        $certificateStm = $sheet1->rangeToArray('A9', NULL, TRUE, FALSE);
        $certificateStm = $certificateStm[0][0];
        $records1['certificateStm'] = $certificateStm;

        // Certificate name
        $certificateName = $sheet1->rangeToArray('B11', NULL, TRUE, FALSE);
        $certificateName = $certificateName[0][0];
        $records1['certificateName'] = $certificateName;

        // Certificate gender
        $certificateGender = $sheet1->rangeToArray('B12', NULL, TRUE, FALSE);
        $certificateGender = $certificateGender[0][0];
        $records1['certificateGender'] = $certificateGender;

        // Certificate DOB
        $certificateDOB = $sheet1->rangeToArray('B13', NULL, TRUE, FALSE);
        $certificateDOB = $certificateDOB[0][0];
        $records1['certificateDOB'] = $certificateDOB;

        // Certificate Nationality
        $certificateNationality = $sheet1->rangeToArray('B14', NULL, TRUE, FALSE);
        $certificateNationality = $certificateNationality[0][0];
        $records1['certificateNationality'] = $certificateNationality;

        // Certificate Reg No
        $certificateRegNo = $sheet1->rangeToArray('G11', NULL, TRUE, FALSE);
        $certificateRegNo = $certificateRegNo[0][0];
        $records1['certificateRegNo'] = $certificateRegNo;

        // Certificate Programme
        $certificateProgramme = $sheet1->rangeToArray('G12', NULL, TRUE, FALSE);
        $certificateProgramme = $certificateProgramme[0][0];
        $records1['certificateProgramme'] = $certificateProgramme;

        // Cert School
        $certSchool = $sheet1->rangeToArray('G13', NULL, TRUE, FALSE);
        $certSchool = $certSchool[0][0];
        $records1['certSchool'] = $certSchool;

        // Certificate year of entry
        $certificateYearEntry = $sheet1->rangeToArray('G14', NULL, TRUE, FALSE);
        $certificateYearEntry = $certificateYearEntry[0][0];
        $records1['certificateYearEntry'] = $certificateYearEntry;

        // Certificate award
        $certificateAward = $sheet1->rangeToArray('A17', NULL, TRUE, FALSE);
        $certificateAward = $certificateAward[0][0];
        $records1['certificateAward'] = $certificateAward;

        // Certificate award1
        $certificateAward1 = $sheet1->rangeToArray('A16', NULL, TRUE, FALSE);
        $certificateAward1 = $certificateAward1[0][0];
        $records1['certificateAward1'] = $certificateAward1;


        // Year 1 Sem 1 data //
        // Certificate Year 1 Sem 1 courseCode1
        $year1Sem1CourseCode1 = $sheet1->rangeToArray('A23', NULL, TRUE, FALSE);
        $year1Sem1CourseCode1 = $year1Sem1CourseCode1[0][0];
        $records1['year1Sem1CourseCode1'] = $year1Sem1CourseCode1;

        // Certificate Year 1 Sem 1 courseCode2
        $year1Sem1CourseCode2 = $sheet1->rangeToArray('A24', NULL, TRUE, FALSE);
        $year1Sem1CourseCode2 = $year1Sem1CourseCode2[0][0];
        $records1['year1Sem1CourseCode2'] = $year1Sem1CourseCode2;

        // Certificate Year 1 Sem 1 courseCode3
        $year1Sem1CourseCode3 = $sheet1->rangeToArray('A25', NULL, TRUE, FALSE);
        $year1Sem1CourseCode3 = $year1Sem1CourseCode3[0][0];
        $records1['year1Sem1CourseCode3'] = $year1Sem1CourseCode3;

        // Certificate Year 1 Sem 1 courseCode4
        $year1Sem1CourseCode4 = $sheet1->rangeToArray('A26', NULL, TRUE, FALSE);
        $year1Sem1CourseCode4 = $year1Sem1CourseCode4[0][0];
        $records1['year1Sem1CourseCode4'] = $year1Sem1CourseCode4;

        // Certificate Year 1 Sem 1 courseCode5
        $year1Sem1CourseCode5 = $sheet1->rangeToArray('A27', NULL, TRUE, FALSE);
        $year1Sem1CourseCode5 = $year1Sem1CourseCode5[0][0];
        $records1['year1Sem1CourseCode5'] = $year1Sem1CourseCode5;

        // Certificate Year 1 Sem 1 courseName1
        $year1Sem1CourseName1 = $sheet1->rangeToArray('B23', NULL, TRUE, FALSE);
        $year1Sem1CourseName1 = $year1Sem1CourseName1[0][0];
        $records1['year1Sem1CourseName1'] = $year1Sem1CourseName1;

        // Certificate Year 1 Sem 1 courseName2
        $year1Sem1CourseName2 = $sheet1->rangeToArray('B24', NULL, TRUE, FALSE);
        $year1Sem1CourseName2 = $year1Sem1CourseName2[0][0];
        $records1['year1Sem1CourseName2'] = $year1Sem1CourseName2;

        // Certificate Year 1 Sem 1 courseName3
        $year1Sem1CourseName3 = $sheet1->rangeToArray('B25', NULL, TRUE, FALSE);
        $year1Sem1CourseName3 = $year1Sem1CourseName3[0][0];
        $records1['year1Sem1CourseName3'] = $year1Sem1CourseName3;

        // Certificate Year 1 Sem 1 courseName4
        $year1Sem1CourseName4 = $sheet1->rangeToArray('B26', NULL, TRUE, FALSE);
        $year1Sem1CourseName4 = $year1Sem1CourseName4[0][0];
        $records1['year1Sem1CourseName4'] = $year1Sem1CourseName4;

        // Certificate Year 1 Sem 1 courseName5
        $year1Sem1CourseName5 = $sheet1->rangeToArray('B27', NULL, TRUE, FALSE);
        $year1Sem1CourseName5 = $year1Sem1CourseName5[0][0];
        $records1['year1Sem1CourseName5'] = $year1Sem1CourseName5;


        // Certificate Year 1 Sem 1 Grade1
        $year1Sem1Grade1 = $sheet1->rangeToArray('D23', NULL, TRUE, FALSE);
        $year1Sem1Grade1 = $year1Sem1Grade1[0][0];
        $records1['year1Sem1Grade1'] = $year1Sem1Grade1;

        // Certificate Year 1 Sem 1 Grade2
        $year1Sem1Grade2 = $sheet1->rangeToArray('D24', NULL, TRUE, FALSE);
        $year1Sem1Grade2 = $year1Sem1Grade2[0][0];
        $records1['year1Sem1Grade2'] = $year1Sem1Grade2;

        // Certificate Year 1 Sem 1 Grade3
        $year1Sem1Grade3 = $sheet1->rangeToArray('D25', NULL, TRUE, FALSE);
        $year1Sem1Grade3 = $year1Sem1Grade3[0][0];
        $records1['year1Sem1Grade3'] = $year1Sem1Grade3;

        // Certificate Year 1 Sem 1 Grade4
        $year1Sem1Grade4 = $sheet1->rangeToArray('D26', NULL, TRUE, FALSE);
        $year1Sem1Grade4 = $year1Sem1Grade4[0][0];
        $records1['year1Sem1Grade4'] = $year1Sem1Grade4;

        // Certificate Year 1 Sem 1 Grade5
        $year1Sem1Grade5 = $sheet1->rangeToArray('D27', NULL, TRUE, FALSE);
        $year1Sem1Grade5 = $year1Sem1Grade5[0][0];
        $records1['year1Sem1Grade5'] = $year1Sem1Grade5;


        // Certificate Year 1 Sem 1 Remark1
        $year1Sem1Remark1 = $sheet1->rangeToArray('E23', NULL, TRUE, FALSE);
        $year1Sem1Remark1 = $year1Sem1Remark1[0][0];
        $records1['year1Sem1Remark1'] = $year1Sem1Remark1;

        // Certificate Year 1 Sem 1 Remark2
        $year1Sem1Remark2 = $sheet1->rangeToArray('E24', NULL, TRUE, FALSE);
        $year1Sem1Remark2 = $year1Sem1Remark2[0][0];
        $records1['year1Sem1Remark2'] = $year1Sem1Remark2;

        // Certificate Year 1 Sem 1 Remark3
        $year1Sem1Remark3 = $sheet1->rangeToArray('E25', NULL, TRUE, FALSE);
        $year1Sem1Remark3 = $year1Sem1Remark3[0][0];
        $records1['year1Sem1Remark3'] = $year1Sem1Remark3;

        // Certificate Year 1 Sem 1 Remark4
        $year1Sem1Remark4 = $sheet1->rangeToArray('E26', NULL, TRUE, FALSE);
        $year1Sem1Remark4 = $year1Sem1Remark4[0][0];
        $records1['year1Sem1Remark4'] = $year1Sem1Remark4;

        // Certificate Year 1 Sem 1 Remark5
        $year1Sem1Remark5 = $sheet1->rangeToArray('E27', NULL, TRUE, FALSE);
        $year1Sem1Remark5 = $year1Sem1Remark5[0][0];
        $records1['year1Sem1Remark5'] = $year1Sem1Remark5;
        // Year 1 Sem 1 data //



        // Year 1 Sem 2 data //
        // Certificate Year 1 Sem 2 courseCode1
        $year1Sem2CourseCode1 = $sheet1->rangeToArray('F23', NULL, TRUE, FALSE);
        $year1Sem2CourseCode1 = $year1Sem2CourseCode1[0][0];
        $records1['year1Sem2CourseCode1'] = $year1Sem2CourseCode1;

        // Certificate Year 1 Sem 2 courseCode2
        $year1Sem2CourseCode2 = $sheet1->rangeToArray('F24', NULL, TRUE, FALSE);
        $year1Sem2CourseCode2 = $year1Sem2CourseCode2[0][0];
        $records1['year1Sem2CourseCode2'] = $year1Sem2CourseCode2;

        // Certificate Year 1 Sem 2 courseCode3
        $year1Sem2CourseCode3 = $sheet1->rangeToArray('F25', NULL, TRUE, FALSE);
        $year1Sem2CourseCode3 = $year1Sem2CourseCode3[0][0];
        $records1['year1Sem2CourseCode3'] = $year1Sem2CourseCode3;

        // Certificate Year 1 Sem 2 courseCode4
        $year1Sem2CourseCode4 = $sheet1->rangeToArray('F26', NULL, TRUE, FALSE);
        $year1Sem2CourseCode4 = $year1Sem2CourseCode4[0][0];
        $records1['year1Sem2CourseCode4'] = $year1Sem2CourseCode4;

        // Certificate Year 1 Sem 2 courseCode5
        $year1Sem2CourseCode5 = $sheet1->rangeToArray('F27', NULL, TRUE, FALSE);
        $year1Sem2CourseCode5 = $year1Sem2CourseCode5[0][0];
        $records1['year1Sem2CourseCode5'] = $year1Sem2CourseCode5;

        // Certificate Year 1 Sem 2 courseName1
        $year1Sem2CourseName1 = $sheet1->rangeToArray('G23', NULL, TRUE, FALSE);
        $year1Sem2CourseName1 = $year1Sem2CourseName1[0][0];
        $records1['year1Sem2CourseName1'] = $year1Sem2CourseName1;

        // Certificate Year 1 Sem 2 courseName2
        $year1Sem2CourseName2 = $sheet1->rangeToArray('G24', NULL, TRUE, FALSE);
        $year1Sem2CourseName2 = $year1Sem2CourseName2[0][0];
        $records1['year1Sem2CourseName2'] = $year1Sem2CourseName2;

        // Certificate Year 1 Sem 2 courseName3
        $year1Sem2CourseName3 = $sheet1->rangeToArray('G25', NULL, TRUE, FALSE);
        $year1Sem2CourseName3 = $year1Sem2CourseName3[0][0];
        $records1['year1Sem2CourseName3'] = $year1Sem2CourseName3;

        // Certificate Year 1 Sem 2 courseName4
        $year1Sem2CourseName4 = $sheet1->rangeToArray('G26', NULL, TRUE, FALSE);
        $year1Sem2CourseName4 = $year1Sem2CourseName4[0][0];
        $records1['year1Sem2CourseName4'] = $year1Sem2CourseName4;

        // Certificate Year 1 Sem 2 courseName5
        $year1Sem2CourseName5 = $sheet1->rangeToArray('G27', NULL, TRUE, FALSE);
        $year1Sem2CourseName5 = $year1Sem2CourseName5[0][0];
        $records1['year1Sem2CourseName5'] = $year1Sem2CourseName5;

        // Certificate Year 1 Sem 2 Grade1
        $year1Sem2Grade1 = $sheet1->rangeToArray('I23', NULL, TRUE, FALSE);
        $year1Sem2Grade1 = $year1Sem2Grade1[0][0];
        $records1['year1Sem2Grade1'] = $year1Sem2Grade1;

        // Certificate Year 1 Sem 2 Grade2
        $year1Sem2Grade2 = $sheet1->rangeToArray('I24', NULL, TRUE, FALSE);
        $year1Sem2Grade2 = $year1Sem2Grade2[0][0];
        $records1['year1Sem2Grade2'] = $year1Sem2Grade2;

        // Certificate Year 1 Sem 2 Grade3
        $year1Sem2Grade3 = $sheet1->rangeToArray('I25', NULL, TRUE, FALSE);
        $year1Sem2Grade3 = $year1Sem2Grade3[0][0];
        $records1['year1Sem2Grade3'] = $year1Sem2Grade3;

        // Certificate Year 1 Sem 2 Grade4
        $year1Sem2Grade4 = $sheet1->rangeToArray('I26', NULL, TRUE, FALSE);
        $year1Sem2Grade4 = $year1Sem2Grade4[0][0];
        $records1['year1Sem2Grade4'] = $year1Sem2Grade4;

        // Certificate Year 1 Sem 2 Grade5
        $year1Sem2Grade5 = $sheet1->rangeToArray('I27', NULL, TRUE, FALSE);
        $year1Sem2Grade5 = $year1Sem2Grade5[0][0];
        $records1['year1Sem2Grade5'] = $year1Sem2Grade5;

        // Certificate Year 1 Sem 2 Remark1
        $year1Sem2Remark1 = $sheet1->rangeToArray('J23', NULL, TRUE, FALSE);
        $year1Sem2Remark1 = $year1Sem2Remark1[0][0];
        $records1['year1Sem2Remark1'] = $year1Sem2Remark1;

        // Certificate Year 1 Sem 2 Remark2
        $year1Sem2Remark2 = $sheet1->rangeToArray('J24', NULL, TRUE, FALSE);
        $year1Sem2Remark2 = $year1Sem2Remark2[0][0];
        $records1['year1Sem2Remark2'] = $year1Sem2Remark2;

        // Certificate Year 1 Sem 2 Remark3
        $year1Sem2Remark3 = $sheet1->rangeToArray('J25', NULL, TRUE, FALSE);
        $year1Sem2Remark3 = $year1Sem2Remark3[0][0];
        $records1['year1Sem2Remark3'] = $year1Sem2Remark3;

        // Certificate Year 1 Sem 2 Remark4
        $year1Sem2Remark4 = $sheet1->rangeToArray('J26', NULL, TRUE, FALSE);
        $year1Sem2Remark4 = $year1Sem2Remark4[0][0];
        $records1['year1Sem2Remark4'] = $year1Sem2Remark4;

        // Certificate Year 1 Sem 2 Remark5
        $year1Sem2Remark5 = $sheet1->rangeToArray('J27', NULL, TRUE, FALSE);
        $year1Sem2Remark5 = $year1Sem2Remark5[0][0];
        $records1['year1Sem2Remark5'] = $year1Sem2Remark5;
        // Year 1 Sem 2 data //




        // Year 2 Sem 1 data //
        // Certificate Year 2 Sem 1 courseCode1
        $year2Sem1CourseCode1 = $sheet1->rangeToArray('A34', NULL, TRUE, FALSE);
        $year2Sem1CourseCode1 = $year2Sem1CourseCode1[0][0];
        $records1['year2Sem1CourseCode1'] = $year2Sem1CourseCode1;

        // Certificate Year 2 Sem 1 courseCode2
        $year2Sem1CourseCode2 = $sheet1->rangeToArray('A35', NULL, TRUE, FALSE);
        $year2Sem1CourseCode2 = $year2Sem1CourseCode2[0][0];
        $records1['year2Sem1CourseCode2'] = $year2Sem1CourseCode2;

        // Certificate Year 2 Sem 1 courseCode3
        $year2Sem1CourseCode3 = $sheet1->rangeToArray('A36', NULL, TRUE, FALSE);
        $year2Sem1CourseCode3 = $year2Sem1CourseCode3[0][0];
        $records1['year2Sem1CourseCode3'] = $year2Sem1CourseCode3;

        // Certificate Year 2 Sem 1 courseCode4
        $year2Sem1CourseCode4 = $sheet1->rangeToArray('A37', NULL, TRUE, FALSE);
        $year2Sem1CourseCode4 = $year2Sem1CourseCode4[0][0];
        $records1['year2Sem1CourseCode4'] = $year2Sem1CourseCode4;

        // Certificate Year 2 Sem 1 courseCode5
        $year2Sem1CourseCode5 = $sheet1->rangeToArray('A38', NULL, TRUE, FALSE);
        $year2Sem1CourseCode5 = $year2Sem1CourseCode5[0][0];
        $records1['year2Sem1CourseCode5'] = $year2Sem1CourseCode5;

        // Certificate Year 2 Sem 1 courseName1
        $year2Sem1CourseName1 = $sheet1->rangeToArray('B34', NULL, TRUE, FALSE);
        $year2Sem1CourseName1 = $year2Sem1CourseName1[0][0];
        $records1['year2Sem1CourseName1'] = $year2Sem1CourseName1;

        // Certificate Year 2 Sem 1 courseName2
        $year2Sem1CourseName2 = $sheet1->rangeToArray('B35', NULL, TRUE, FALSE);
        $year2Sem1CourseName2 = $year2Sem1CourseName2[0][0];
        $records1['year2Sem1CourseName2'] = $year2Sem1CourseName2;

        // Certificate Year 2 Sem 1 courseName3
        $year2Sem1CourseName3 = $sheet1->rangeToArray('B36', NULL, TRUE, FALSE);
        $year2Sem1CourseName3 = $year2Sem1CourseName3[0][0];
        $records1['year2Sem1CourseName3'] = $year2Sem1CourseName3;

        // Certificate Year 2 Sem 1 courseName4
        $year2Sem1CourseName4 = $sheet1->rangeToArray('B37', NULL, TRUE, FALSE);
        $year2Sem1CourseName4 = $year2Sem1CourseName4[0][0];
        $records1['year2Sem1CourseName4'] = $year2Sem1CourseName4;

        // Certificate Year 2 Sem 1 courseName5
        $year2Sem1CourseName5 = $sheet1->rangeToArray('B38', NULL, TRUE, FALSE);
        $year2Sem1CourseName5 = $year2Sem1CourseName5[0][0];
        $records1['year2Sem1CourseName5'] = $year2Sem1CourseName5;


        // Certificate Year 2 Sem 1 Grade1
        $year2Sem1Grade1 = $sheet1->rangeToArray('D34', NULL, TRUE, FALSE);
        $year2Sem1Grade1 = $year2Sem1Grade1[0][0];
        $records1['year2Sem1Grade1'] = $year2Sem1Grade1;

        // Certificate Year 2 Sem 1 Grade2
        $year2Sem1Grade2 = $sheet1->rangeToArray('D35', NULL, TRUE, FALSE);
        $year2Sem1Grade2 = $year2Sem1Grade2[0][0];
        $records1['year2Sem1Grade2'] = $year2Sem1Grade2;

        // Certificate Year 2 Sem 1 Grade3
        $year2Sem1Grade3 = $sheet1->rangeToArray('D36', NULL, TRUE, FALSE);
        $year2Sem1Grade3 = $year2Sem1Grade3[0][0];
        $records1['year2Sem1Grade3'] = $year2Sem1Grade3;

        // Certificate Year 2 Sem 1 Grade4
        $year2Sem1Grade4 = $sheet1->rangeToArray('D37', NULL, TRUE, FALSE);
        $year2Sem1Grade4 = $year2Sem1Grade4[0][0];
        $records1['year2Sem1Grade4'] = $year2Sem1Grade4;

        // Certificate Year 2 Sem 1 Grade5
        $year2Sem1Grade5 = $sheet1->rangeToArray('D38', NULL, TRUE, FALSE);
        $year2Sem1Grade5 = $year2Sem1Grade5[0][0];
        $records1['year2Sem1Grade5'] = $year2Sem1Grade5;


        // Certificate Year 2 Sem 1 Remark1
        $year2Sem1Remark1 = $sheet1->rangeToArray('E34', NULL, TRUE, FALSE);
        $year2Sem1Remark1 = $year2Sem1Remark1[0][0];
        $records1['year2Sem1Remark1'] = $year2Sem1Remark1;

        // Certificate Year 2 Sem 1 Remark2
        $year2Sem1Remark2 = $sheet1->rangeToArray('E35', NULL, TRUE, FALSE);
        $year2Sem1Remark2 = $year2Sem1Remark2[0][0];
        $records1['year2Sem1Remark2'] = $year2Sem1Remark2;

        // Certificate Year 2 Sem 1 Remark3
        $year2Sem1Remark3 = $sheet1->rangeToArray('E36', NULL, TRUE, FALSE);
        $year2Sem1Remark3 = $year2Sem1Remark3[0][0];
        $records1['year2Sem1Remark3'] = $year2Sem1Remark3;

        // Certificate Year 2 Sem 1 Remark4
        $year2Sem1Remark4 = $sheet1->rangeToArray('E37', NULL, TRUE, FALSE);
        $year2Sem1Remark4 = $year2Sem1Remark4[0][0];
        $records1['year2Sem1Remark4'] = $year2Sem1Remark4;

        // Certificate Year 2 Sem 1 Remark5
        $year2Sem1Remark5 = $sheet1->rangeToArray('E38', NULL, TRUE, FALSE);
        $year2Sem1Remark5 = $year2Sem1Remark5[0][0];
        $records1['year2Sem1Remark5'] = $year2Sem1Remark5;
        // Year 2 Sem 1 data //


        
        // Year 2 Sem 2 data //
        // Certificate Year 2 Sem 2 courseCode1
        $year2Sem2CourseCode1 = $sheet1->rangeToArray('F34', NULL, TRUE, FALSE);
        $year2Sem2CourseCode1 = $year2Sem2CourseCode1[0][0];
        $records1['year2Sem2CourseCode1'] = $year2Sem2CourseCode1;

        // Certificate Year 2 Sem 2 courseCode2
        $year2Sem2CourseCode2 = $sheet1->rangeToArray('F35', NULL, TRUE, FALSE);
        $year2Sem2CourseCode2 = $year2Sem2CourseCode2[0][0];
        $records1['year2Sem2CourseCode2'] = $year2Sem2CourseCode2;

        // Certificate Year 2 Sem 2 courseCode3
        $year2Sem2CourseCode3 = $sheet1->rangeToArray('F36', NULL, TRUE, FALSE);
        $year2Sem2CourseCode3 = $year2Sem2CourseCode3[0][0];
        $records1['year2Sem2CourseCode3'] = $year2Sem2CourseCode3;

        // Certificate Year 2 Sem 2 courseCode4
        $year2Sem2CourseCode4 = $sheet1->rangeToArray('F37', NULL, TRUE, FALSE);
        $year2Sem2CourseCode4 = $year2Sem2CourseCode4[0][0];
        $records1['year2Sem2CourseCode4'] = $year2Sem2CourseCode4;

        // Certificate Year 2 Sem 2 courseCode5
        $year2Sem2CourseCode5 = $sheet1->rangeToArray('F38', NULL, TRUE, FALSE);
        $year2Sem2CourseCode5 = $year2Sem2CourseCode5[0][0];
        $records1['year2Sem2CourseCode5'] = $year2Sem2CourseCode5;

        // Certificate Year 2 Sem 2 courseName1
        $year2Sem2CourseName1 = $sheet1->rangeToArray('G34', NULL, TRUE, FALSE);
        $year2Sem2CourseName1 = $year2Sem2CourseName1[0][0];
        $records1['year2Sem2CourseName1'] = $year2Sem2CourseName1;

        // Certificate Year 2 Sem 2 courseName2
        $year2Sem2CourseName2 = $sheet1->rangeToArray('G35', NULL, TRUE, FALSE);
        $year2Sem2CourseName2 = $year2Sem2CourseName2[0][0];
        $records1['year2Sem2CourseName2'] = $year2Sem2CourseName2;

        // Certificate Year 2 Sem 2 courseName3
        $year2Sem2CourseName3 = $sheet1->rangeToArray('G36', NULL, TRUE, FALSE);
        $year2Sem2CourseName3 = $year2Sem2CourseName3[0][0];
        $records1['year2Sem2CourseName3'] = $year2Sem2CourseName3;

        // Certificate Year 2 Sem 2 courseName4
        $year2Sem2CourseName4 = $sheet1->rangeToArray('G37', NULL, TRUE, FALSE);
        $year2Sem2CourseName4 = $year2Sem2CourseName4[0][0];
        $records1['year2Sem2CourseName4'] = $year2Sem2CourseName4;

        // Certificate Year 2 Sem 2 courseName5
        $year2Sem2CourseName5 = $sheet1->rangeToArray('G38', NULL, TRUE, FALSE);
        $year2Sem2CourseName5 = $year2Sem2CourseName5[0][0];
        $records1['year2Sem2CourseName5'] = $year2Sem2CourseName5;

        // Certificate Year 2 Sem 2 Grade1
        $year2Sem2Grade1 = $sheet1->rangeToArray('I34', NULL, TRUE, FALSE);
        $year2Sem2Grade1 = $year2Sem2Grade1[0][0];
        $records1['year2Sem2Grade1'] = $year2Sem2Grade1;

        // Certificate Year 2 Sem 2 Grade2
        $year2Sem2Grade2 = $sheet1->rangeToArray('I35', NULL, TRUE, FALSE);
        $year2Sem2Grade2 = $year2Sem2Grade2[0][0];
        $records1['year2Sem2Grade2'] = $year2Sem2Grade2;

        // Certificate Year 2 Sem 2 Grade3
        $year2Sem2Grade3 = $sheet1->rangeToArray('I36', NULL, TRUE, FALSE);
        $year2Sem2Grade3 = $year2Sem2Grade3[0][0];
        $records1['year2Sem2Grade3'] = $year2Sem2Grade3;

        // Certificate Year 2 Sem 2 Grade4
        $year2Sem2Grade4 = $sheet1->rangeToArray('I37', NULL, TRUE, FALSE);
        $year2Sem2Grade4 = $year2Sem2Grade4[0][0];
        $records1['year2Sem2Grade4'] = $year2Sem2Grade4;

        // Certificate Year 2 Sem 2 Grade5
        $year2Sem2Grade5 = $sheet1->rangeToArray('I38', NULL, TRUE, FALSE);
        $year2Sem2Grade5 = $year2Sem2Grade5[0][0];
        $records1['year2Sem2Grade5'] = $year2Sem2Grade5;

        // Certificate Year 2 Sem 2 Remark1
        $year2Sem2Remark1 = $sheet1->rangeToArray('J34', NULL, TRUE, FALSE);
        $year2Sem2Remark1 = $year2Sem2Remark1[0][0];
        $records1['year2Sem2Remark1'] = $year2Sem2Remark1;

        // Certificate Year 2 Sem 2 Remark2
        $year2Sem2Remark2 = $sheet1->rangeToArray('J35', NULL, TRUE, FALSE);
        $year2Sem2Remark2 = $year2Sem2Remark2[0][0];
        $records1['year2Sem2Remark2'] = $year2Sem2Remark2;

        // Certificate Year 2 Sem 2 Remark3
        $year2Sem2Remark3 = $sheet1->rangeToArray('J36', NULL, TRUE, FALSE);
        $year2Sem2Remark3 = $year2Sem2Remark3[0][0];
        $records1['year2Sem2Remark3'] = $year2Sem2Remark3;

        // Certificate Year 2 Sem 2 Remark4
        $year2Sem2Remark4 = $sheet1->rangeToArray('J37', NULL, TRUE, FALSE);
        $year2Sem2Remark4 = $year2Sem2Remark4[0][0];
        $records1['year2Sem2Remark4'] = $year2Sem2Remark4;

        // Certificate Year 2 Sem 2 Remark5
        $year2Sem2Remark5 = $sheet1->rangeToArray('J38', NULL, TRUE, FALSE);
        $year2Sem2Remark5 = $year2Sem2Remark5[0][0];
        $records1['year2Sem2Remark5'] = $year2Sem2Remark5;
        // Year 2 Sem 2 data //





        // Year 3 Sem 1 data //
        // Certificate Year 3 Sem 1 courseCode1
        $year3Sem1CourseCode1 = $sheet1->rangeToArray('A45', NULL, TRUE, FALSE);
        $year3Sem1CourseCode1 = $year3Sem1CourseCode1[0][0];
        $records1['year3Sem1CourseCode1'] = $year3Sem1CourseCode1;

        // Certificate Year 3 Sem 1 courseCode2
        $year3Sem1CourseCode2 = $sheet1->rangeToArray('A46', NULL, TRUE, FALSE);
        $year3Sem1CourseCode2 = $year3Sem1CourseCode2[0][0];
        $records1['year3Sem1CourseCode2'] = $year3Sem1CourseCode2;

        // Certificate Year 3 Sem 1 courseCode3
        $year3Sem1CourseCode3 = $sheet1->rangeToArray('A47', NULL, TRUE, FALSE);
        $year3Sem1CourseCode3 = $year3Sem1CourseCode3[0][0];
        $records1['year3Sem1CourseCode3'] = $year3Sem1CourseCode3;

        // Certificate Year 3 Sem 1 courseCode4
        $year3Sem1CourseCode4 = $sheet1->rangeToArray('A48', NULL, TRUE, FALSE);
        $year3Sem1CourseCode4 = $year3Sem1CourseCode4[0][0];
        $records1['year3Sem1CourseCode4'] = $year3Sem1CourseCode4;

        // Certificate Year 3 Sem 1 courseCode5
        $year3Sem1CourseCode5 = $sheet1->rangeToArray('A49', NULL, TRUE, FALSE);
        $year3Sem1CourseCode5 = $year3Sem1CourseCode5[0][0];
        $records1['year3Sem1CourseCode5'] = $year3Sem1CourseCode5;

        // Certificate Year 3 Sem 1 courseName1
        $year3Sem1CourseName1 = $sheet1->rangeToArray('B45', NULL, TRUE, FALSE);
        $year3Sem1CourseName1 = $year3Sem1CourseName1[0][0];
        $records1['year3Sem1CourseName1'] = $year3Sem1CourseName1;

        // Certificate Year 3 Sem 1 courseName2
        $year3Sem1CourseName2 = $sheet1->rangeToArray('B46', NULL, TRUE, FALSE);
        $year3Sem1CourseName2 = $year3Sem1CourseName2[0][0];
        $records1['year3Sem1CourseName2'] = $year3Sem1CourseName2;

        // Certificate Year 3 Sem 1 courseName3
        $year3Sem1CourseName3 = $sheet1->rangeToArray('B47', NULL, TRUE, FALSE);
        $year3Sem1CourseName3 = $year3Sem1CourseName3[0][0];
        $records1['year3Sem1CourseName3'] = $year3Sem1CourseName3;

        // Certificate Year 3 Sem 1 courseName4
        $year3Sem1CourseName4 = $sheet1->rangeToArray('B48', NULL, TRUE, FALSE);
        $year3Sem1CourseName4 = $year3Sem1CourseName4[0][0];
        $records1['year3Sem1CourseName4'] = $year3Sem1CourseName4;

        // Certificate Year 3 Sem 1 courseName5
        $year3Sem1CourseName5 = $sheet1->rangeToArray('B49', NULL, TRUE, FALSE);
        $year3Sem1CourseName5 = $year3Sem1CourseName5[0][0];
        $records1['year3Sem1CourseName5'] = $year3Sem1CourseName5;


        // Certificate Year 3 Sem 1 Grade1
        $year3Sem1Grade1 = $sheet1->rangeToArray('D45', NULL, TRUE, FALSE);
        $year3Sem1Grade1 = $year3Sem1Grade1[0][0];
        $records1['year3Sem1Grade1'] = $year3Sem1Grade1;

        // Certificate Year 3 Sem 1 Grade2
        $year3Sem1Grade2 = $sheet1->rangeToArray('D46', NULL, TRUE, FALSE);
        $year3Sem1Grade2 = $year3Sem1Grade2[0][0];
        $records1['year3Sem1Grade2'] = $year3Sem1Grade2;

        // Certificate Year 3 Sem 1 Grade3
        $year3Sem1Grade3 = $sheet1->rangeToArray('D47', NULL, TRUE, FALSE);
        $year3Sem1Grade3 = $year3Sem1Grade3[0][0];
        $records1['year3Sem1Grade3'] = $year3Sem1Grade3;

        // Certificate Year 3 Sem 1 Grade4
        $year3Sem1Grade4 = $sheet1->rangeToArray('D48', NULL, TRUE, FALSE);
        $year3Sem1Grade4 = $year3Sem1Grade4[0][0];
        $records1['year3Sem1Grade4'] = $year3Sem1Grade4;

        // Certificate Year 3 Sem 1 Grade5
        $year3Sem1Grade5 = $sheet1->rangeToArray('D49', NULL, TRUE, FALSE);
        $year3Sem1Grade5 = $year3Sem1Grade5[0][0];
        $records1['year3Sem1Grade5'] = $year3Sem1Grade5;


        // Certificate Year 3 Sem 1 Remark1
        $year3Sem1Remark1 = $sheet1->rangeToArray('E45', NULL, TRUE, FALSE);
        $year3Sem1Remark1 = $year3Sem1Remark1[0][0];
        $records1['year3Sem1Remark1'] = $year3Sem1Remark1;

        // Certificate Year 3 Sem 1 Remark2
        $year3Sem1Remark2 = $sheet1->rangeToArray('E46', NULL, TRUE, FALSE);
        $year3Sem1Remark2 = $year3Sem1Remark2[0][0];
        $records1['year3Sem1Remark2'] = $year3Sem1Remark2;

        // Certificate Year 3 Sem 1 Remark3
        $year3Sem1Remark3 = $sheet1->rangeToArray('E47', NULL, TRUE, FALSE);
        $year3Sem1Remark3 = $year3Sem1Remark3[0][0];
        $records1['year3Sem1Remark3'] = $year3Sem1Remark3;

        // Certificate Year 3 Sem 1 Remark4
        $year3Sem1Remark4 = $sheet1->rangeToArray('E48', NULL, TRUE, FALSE);
        $year3Sem1Remark4 = $year3Sem1Remark4[0][0];
        $records1['year3Sem1Remark4'] = $year3Sem1Remark4;

        // Certificate Year 3 Sem 1 Remark5
        $year3Sem1Remark5 = $sheet1->rangeToArray('E49', NULL, TRUE, FALSE);
        $year3Sem1Remark5 = $year3Sem1Remark5[0][0];
        $records1['year3Sem1Remark5'] = $year3Sem1Remark5;
        // Year 3 Sem 1 data //


        
        // Year 3 Sem 2 data //
        // Certificate Year 3 Sem 2 courseCode1
        $year3Sem2CourseCode1 = $sheet1->rangeToArray('F45', NULL, TRUE, FALSE);
        $year3Sem2CourseCode1 = $year3Sem2CourseCode1[0][0];
        $records1['year3Sem2CourseCode1'] = $year3Sem2CourseCode1;

        // Certificate Year 3 Sem 2 courseCode2
        $year3Sem2CourseCode2 = $sheet1->rangeToArray('F46', NULL, TRUE, FALSE);
        $year3Sem2CourseCode2 = $year3Sem2CourseCode2[0][0];
        $records1['year3Sem2CourseCode2'] = $year3Sem2CourseCode2;

        // Certificate Year 3 Sem 2 courseCode3
        $year3Sem2CourseCode3 = $sheet1->rangeToArray('F47', NULL, TRUE, FALSE);
        $year3Sem2CourseCode3 = $year3Sem2CourseCode3[0][0];
        $records1['year3Sem2CourseCode3'] = $year3Sem2CourseCode3;

        // Certificate Year 3 Sem 2 courseCode4
        $year3Sem2CourseCode4 = $sheet1->rangeToArray('F48', NULL, TRUE, FALSE);
        $year3Sem2CourseCode4 = $year3Sem2CourseCode4[0][0];
        $records1['year3Sem2CourseCode4'] = $year3Sem2CourseCode4;

        // Certificate Year 3 Sem 2 courseCode5
        $year3Sem2CourseCode5 = $sheet1->rangeToArray('F49', NULL, TRUE, FALSE);
        $year3Sem2CourseCode5 = $year3Sem2CourseCode5[0][0];
        $records1['year3Sem2CourseCode5'] = $year3Sem2CourseCode5;

        // Certificate Year 3 Sem 2 courseName1
        $year3Sem2CourseName1 = $sheet1->rangeToArray('G45', NULL, TRUE, FALSE);
        $year3Sem2CourseName1 = $year3Sem2CourseName1[0][0];
        $records1['year3Sem2CourseName1'] = $year3Sem2CourseName1;

        // Certificate Year 3 Sem 2 courseName2
        $year3Sem2CourseName2 = $sheet1->rangeToArray('G46', NULL, TRUE, FALSE);
        $year3Sem2CourseName2 = $year3Sem2CourseName2[0][0];
        $records1['year3Sem2CourseName2'] = $year3Sem2CourseName2;

        // Certificate Year 3 Sem 2 courseName3
        $year3Sem2CourseName3 = $sheet1->rangeToArray('G47', NULL, TRUE, FALSE);
        $year3Sem2CourseName3 = $year3Sem2CourseName3[0][0];
        $records1['year3Sem2CourseName3'] = $year3Sem2CourseName3;

        // Certificate Year 3 Sem 2 courseName4
        $year3Sem2CourseName4 = $sheet1->rangeToArray('G48', NULL, TRUE, FALSE);
        $year3Sem2CourseName4 = $year3Sem2CourseName4[0][0];
        $records1['year3Sem2CourseName4'] = $year3Sem2CourseName4;

        // Certificate Year 3 Sem 2 courseName5
        $year3Sem2CourseName5 = $sheet1->rangeToArray('G49', NULL, TRUE, FALSE);
        $year3Sem2CourseName5 = $year3Sem2CourseName5[0][0];
        $records1['year3Sem2CourseName5'] = $year3Sem2CourseName5;

        // Certificate Year 3 Sem 2 Grade1
        $year3Sem2Grade1 = $sheet1->rangeToArray('I45', NULL, TRUE, FALSE);
        $year3Sem2Grade1 = $year3Sem2Grade1[0][0];
        $records1['year3Sem2Grade1'] = $year3Sem2Grade1;

        // Certificate Year 3 Sem 2 Grade2
        $year3Sem2Grade2 = $sheet1->rangeToArray('I46', NULL, TRUE, FALSE);
        $year3Sem2Grade2 = $year3Sem2Grade2[0][0];
        $records1['year3Sem2Grade2'] = $year3Sem2Grade2;

        // Certificate Year 3 Sem 2 Grade3
        $year3Sem2Grade3 = $sheet1->rangeToArray('I47', NULL, TRUE, FALSE);
        $year3Sem2Grade3 = $year3Sem2Grade3[0][0];
        $records1['year3Sem2Grade3'] = $year3Sem2Grade3;

        // Certificate Year 3 Sem 2 Grade4
        $year3Sem2Grade4 = $sheet1->rangeToArray('I48', NULL, TRUE, FALSE);
        $year3Sem2Grade4 = $year3Sem2Grade4[0][0];
        $records1['year3Sem2Grade4'] = $year3Sem2Grade4;

        // Certificate Year 3 Sem 2 Grade5
        $year3Sem2Grade5 = $sheet1->rangeToArray('I49', NULL, TRUE, FALSE);
        $year3Sem2Grade5 = $year3Sem2Grade5[0][0];
        $records1['year3Sem2Grade5'] = $year3Sem2Grade5;

        // Certificate Year 3 Sem 2 Remark1
        $year3Sem2Remark1 = $sheet1->rangeToArray('J45', NULL, TRUE, FALSE);
        $year3Sem2Remark1 = $year3Sem2Remark1[0][0];
        $records1['year3Sem2Remark1'] = $year3Sem2Remark1;

        // Certificate Year 3 Sem 2 Remark2
        $year3Sem2Remark2 = $sheet1->rangeToArray('J46', NULL, TRUE, FALSE);
        $year3Sem2Remark2 = $year3Sem2Remark2[0][0];
        $records1['year3Sem2Remark2'] = $year3Sem2Remark2;

        // Certificate Year 3 Sem 2 Remark3
        $year3Sem2Remark3 = $sheet1->rangeToArray('J47', NULL, TRUE, FALSE);
        $year3Sem2Remark3 = $year3Sem2Remark3[0][0];
        $records1['year3Sem2Remark3'] = $year3Sem2Remark3;

        // Certificate Year 3 Sem 2 Remark4
        $year3Sem2Remark4 = $sheet1->rangeToArray('J48', NULL, TRUE, FALSE);
        $year3Sem2Remark4 = $year3Sem2Remark4[0][0];
        $records1['year3Sem2Remark4'] = $year3Sem2Remark4;

        // Certificate Year 3 Sem 2 Remark5
        $year3Sem2Remark5 = $sheet1->rangeToArray('J49', NULL, TRUE, FALSE);
        $year3Sem2Remark5 = $year3Sem2Remark5[0][0];
        $records1['year3Sem2Remark5'] = $year3Sem2Remark5;
        // Year 3 Sem 2 data //





        // Year 4 Sem 1 data //
        // Certificate Year 4 Sem 1 courseCode1
        $year4Sem1CourseCode1 = $sheet1->rangeToArray('A56', NULL, TRUE, FALSE);
        $year4Sem1CourseCode1 = $year4Sem1CourseCode1[0][0];
        $records1['year4Sem1CourseCode1'] = $year4Sem1CourseCode1;

        // Certificate Year 4 Sem 1 courseCode2
        $year4Sem1CourseCode2 = $sheet1->rangeToArray('A57', NULL, TRUE, FALSE);
        $year4Sem1CourseCode2 = $year4Sem1CourseCode2[0][0];
        $records1['year4Sem1CourseCode2'] = $year4Sem1CourseCode2;

        // Certificate Year 4 Sem 1 courseCode3
        $year4Sem1CourseCode3 = $sheet1->rangeToArray('A58', NULL, TRUE, FALSE);
        $year4Sem1CourseCode3 = $year4Sem1CourseCode3[0][0];
        $records1['year4Sem1CourseCode3'] = $year4Sem1CourseCode3;

        // Certificate Year 4 Sem 1 courseCode4
        $year4Sem1CourseCode4 = $sheet1->rangeToArray('A59', NULL, TRUE, FALSE);
        $year4Sem1CourseCode4 = $year4Sem1CourseCode4[0][0];
        $records1['year4Sem1CourseCode4'] = $year4Sem1CourseCode4;

        // Certificate Year 4 Sem 1 courseCode5
        $year4Sem1CourseCode5 = $sheet1->rangeToArray('A60', NULL, TRUE, FALSE);
        $year4Sem1CourseCode5 = $year4Sem1CourseCode5[0][0];
        $records1['year4Sem1CourseCode5'] = $year4Sem1CourseCode5;

        // Certificate Year 4 Sem 1 courseName1
        $year4Sem1CourseName1 = $sheet1->rangeToArray('B56', NULL, TRUE, FALSE);
        $year4Sem1CourseName1 = $year4Sem1CourseName1[0][0];
        $records1['year4Sem1CourseName1'] = $year4Sem1CourseName1;

        // Certificate Year 4 Sem 1 courseName2
        $year4Sem1CourseName2 = $sheet1->rangeToArray('B57', NULL, TRUE, FALSE);
        $year4Sem1CourseName2 = $year4Sem1CourseName2[0][0];
        $records1['year4Sem1CourseName2'] = $year4Sem1CourseName2;

        // Certificate Year 4 Sem 1 courseName3
        $year4Sem1CourseName3 = $sheet1->rangeToArray('B58', NULL, TRUE, FALSE);
        $year4Sem1CourseName3 = $year4Sem1CourseName3[0][0];
        $records1['year4Sem1CourseName3'] = $year4Sem1CourseName3;

        // Certificate Year 4 Sem 1 courseName4
        $year4Sem1CourseName4 = $sheet1->rangeToArray('B59', NULL, TRUE, FALSE);
        $year4Sem1CourseName4 = $year4Sem1CourseName4[0][0];
        $records1['year4Sem1CourseName4'] = $year4Sem1CourseName4;

        // Certificate Year 4 Sem 1 courseName5
        $year4Sem1CourseName5 = $sheet1->rangeToArray('B60', NULL, TRUE, FALSE);
        $year4Sem1CourseName5 = $year4Sem1CourseName5[0][0];
        $records1['year4Sem1CourseName5'] = $year4Sem1CourseName5;


        // Certificate Year 4 Sem 1 Grade1
        $year4Sem1Grade1 = $sheet1->rangeToArray('D56', NULL, TRUE, FALSE);
        $year4Sem1Grade1 = $year4Sem1Grade1[0][0];
        $records1['year4Sem1Grade1'] = $year4Sem1Grade1;

        // Certificate Year 4 Sem 1 Grade2
        $year4Sem1Grade2 = $sheet1->rangeToArray('D57', NULL, TRUE, FALSE);
        $year4Sem1Grade2 = $year4Sem1Grade2[0][0];
        $records1['year4Sem1Grade2'] = $year4Sem1Grade2;

        // Certificate Year 4 Sem 1 Grade3
        $year4Sem1Grade3 = $sheet1->rangeToArray('D58', NULL, TRUE, FALSE);
        $year4Sem1Grade3 = $year4Sem1Grade3[0][0];
        $records1['year4Sem1Grade3'] = $year4Sem1Grade3;

        // Certificate Year 4 Sem 1 Grade4
        $year4Sem1Grade4 = $sheet1->rangeToArray('D59', NULL, TRUE, FALSE);
        $year4Sem1Grade4 = $year4Sem1Grade4[0][0];
        $records1['year4Sem1Grade4'] = $year4Sem1Grade4;

        // Certificate Year 4 Sem 1 Grade5
        $year4Sem1Grade5 = $sheet1->rangeToArray('D60', NULL, TRUE, FALSE);
        $year4Sem1Grade5 = $year4Sem1Grade5[0][0];
        $records1['year4Sem1Grade5'] = $year4Sem1Grade5;


        // Certificate Year 4 Sem 1 Remark1
        $year4Sem1Remark1 = $sheet1->rangeToArray('E56', NULL, TRUE, FALSE);
        $year4Sem1Remark1 = $year4Sem1Remark1[0][0];
        $records1['year4Sem1Remark1'] = $year4Sem1Remark1;

        // Certificate Year 4 Sem 1 Remark2
        $year4Sem1Remark2 = $sheet1->rangeToArray('E57', NULL, TRUE, FALSE);
        $year4Sem1Remark2 = $year4Sem1Remark2[0][0];
        $records1['year4Sem1Remark2'] = $year4Sem1Remark2;

        // Certificate Year 4 Sem 1 Remark3
        $year4Sem1Remark3 = $sheet1->rangeToArray('E58', NULL, TRUE, FALSE);
        $year4Sem1Remark3 = $year4Sem1Remark3[0][0];
        $records1['year4Sem1Remark3'] = $year4Sem1Remark3;

        // Certificate Year 4 Sem 1 Remark4
        $year4Sem1Remark4 = $sheet1->rangeToArray('E59', NULL, TRUE, FALSE);
        $year4Sem1Remark4 = $year4Sem1Remark4[0][0];
        $records1['year4Sem1Remark4'] = $year4Sem1Remark4;

        // Certificate Year 4 Sem 1 Remark5
        $year4Sem1Remark5 = $sheet1->rangeToArray('E60', NULL, TRUE, FALSE);
        $year4Sem1Remark5 = $year4Sem1Remark5[0][0];
        $records1['year4Sem1Remark5'] = $year4Sem1Remark5;
        // Year 4 Sem 1 data //


        
        // Year 4 Sem 2 data //
        // Certificate Year 4 Sem 2 courseCode1
        $year4Sem2CourseCode1 = $sheet1->rangeToArray('F56', NULL, TRUE, FALSE);
        $year4Sem2CourseCode1 = $year4Sem2CourseCode1[0][0];
        $records1['year4Sem2CourseCode1'] = $year4Sem2CourseCode1;

        // Certificate Year 4 Sem 2 courseCode2
        $year4Sem2CourseCode2 = $sheet1->rangeToArray('F57', NULL, TRUE, FALSE);
        $year4Sem2CourseCode2 = $year4Sem2CourseCode2[0][0];
        $records1['year4Sem2CourseCode2'] = $year4Sem2CourseCode2;

        // Certificate Year 4 Sem 2 courseCode3
        $year4Sem2CourseCode3 = $sheet1->rangeToArray('F58', NULL, TRUE, FALSE);
        $year4Sem2CourseCode3 = $year4Sem2CourseCode3[0][0];
        $records1['year4Sem2CourseCode3'] = $year4Sem2CourseCode3;

        // Certificate Year 4 Sem 2 courseCode4
        $year4Sem2CourseCode4 = $sheet1->rangeToArray('F59', NULL, TRUE, FALSE);
        $year4Sem2CourseCode4 = $year4Sem2CourseCode4[0][0];
        $records1['year4Sem2CourseCode4'] = $year4Sem2CourseCode4;

        // Certificate Year 4 Sem 2 courseCode5
        $year4Sem2CourseCode5 = $sheet1->rangeToArray('F60', NULL, TRUE, FALSE);
        $year4Sem2CourseCode5 = $year4Sem2CourseCode5[0][0];
        $records1['year4Sem2CourseCode5'] = $year4Sem2CourseCode5;

        // Certificate Year 4 Sem 2 courseName1
        $year4Sem2CourseName1 = $sheet1->rangeToArray('G56', NULL, TRUE, FALSE);
        $year4Sem2CourseName1 = $year4Sem2CourseName1[0][0];
        $records1['year4Sem2CourseName1'] = $year4Sem2CourseName1;

        // Certificate Year 4 Sem 2 courseName2
        $year4Sem2CourseName2 = $sheet1->rangeToArray('G57', NULL, TRUE, FALSE);
        $year4Sem2CourseName2 = $year4Sem2CourseName2[0][0];
        $records1['year4Sem2CourseName2'] = $year4Sem2CourseName2;

        // Certificate Year 4 Sem 2 courseName3
        $year4Sem2CourseName3 = $sheet1->rangeToArray('G58', NULL, TRUE, FALSE);
        $year4Sem2CourseName3 = $year4Sem2CourseName3[0][0];
        $records1['year4Sem2CourseName3'] = $year4Sem2CourseName3;

        // Certificate Year 4 Sem 2 courseName4
        $year4Sem2CourseName4 = $sheet1->rangeToArray('G59', NULL, TRUE, FALSE);
        $year4Sem2CourseName4 = $year4Sem2CourseName4[0][0];
        $records1['year4Sem2CourseName4'] = $year4Sem2CourseName4;

        // Certificate Year 4 Sem 2 courseName5
        $year4Sem2CourseName5 = $sheet1->rangeToArray('G60', NULL, TRUE, FALSE);
        $year4Sem2CourseName5 = $year4Sem2CourseName5[0][0];
        $records1['year4Sem2CourseName5'] = $year4Sem2CourseName5;

        // Certificate Year 4 Sem 2 Grade1
        $year4Sem2Grade1 = $sheet1->rangeToArray('I56', NULL, TRUE, FALSE);
        $year4Sem2Grade1 = $year4Sem2Grade1[0][0];
        $records1['year4Sem2Grade1'] = $year4Sem2Grade1;

        // Certificate Year 4 Sem 2 Grade2
        $year4Sem2Grade2 = $sheet1->rangeToArray('I57', NULL, TRUE, FALSE);
        $year4Sem2Grade2 = $year4Sem2Grade2[0][0];
        $records1['year4Sem2Grade2'] = $year4Sem2Grade2;

        // Certificate Year 4 Sem 2 Grade3
        $year4Sem2Grade3 = $sheet1->rangeToArray('I58', NULL, TRUE, FALSE);
        $year4Sem2Grade3 = $year4Sem2Grade3[0][0];
        $records1['year4Sem2Grade3'] = $year4Sem2Grade3;

        // Certificate Year 4 Sem 2 Grade4
        $year4Sem2Grade4 = $sheet1->rangeToArray('I59', NULL, TRUE, FALSE);
        $year4Sem2Grade4 = $year4Sem2Grade4[0][0];
        $records1['year4Sem2Grade4'] = $year4Sem2Grade4;

        // Certificate Year 4 Sem 2 Grade5
        $year4Sem2Grade5 = $sheet1->rangeToArray('I60', NULL, TRUE, FALSE);
        $year4Sem2Grade5 = $year4Sem2Grade5[0][0];
        $records1['year4Sem2Grade5'] = $year4Sem2Grade5;

        // Certificate Year 4 Sem 2 Remark1
        $year4Sem2Remark1 = $sheet1->rangeToArray('J56', NULL, TRUE, FALSE);
        $year4Sem2Remark1 = $year4Sem2Remark1[0][0];
        $records1['year4Sem2Remark1'] = $year4Sem2Remark1;

        // Certificate Year 4 Sem 2 Remark2
        $year4Sem2Remark2 = $sheet1->rangeToArray('J57', NULL, TRUE, FALSE);
        $year4Sem2Remark2 = $year4Sem2Remark2[0][0];
        $records1['year4Sem2Remark2'] = $year4Sem2Remark2;

        // Certificate Year 4 Sem 2 Remark3
        $year4Sem2Remark3 = $sheet1->rangeToArray('J58', NULL, TRUE, FALSE);
        $year4Sem2Remark3 = $year4Sem2Remark3[0][0];
        $records1['year4Sem2Remark3'] = $year4Sem2Remark3;

        // Certificate Year 4 Sem 2 Remark4
        $year4Sem2Remark4 = $sheet1->rangeToArray('J59', NULL, TRUE, FALSE);
        $year4Sem2Remark4 = $year4Sem2Remark4[0][0];
        $records1['year4Sem2Remark4'] = $year4Sem2Remark4;

        // Certificate Year 4 Sem 2 Remark5
        $year4Sem2Remark5 = $sheet1->rangeToArray('J60', NULL, TRUE, FALSE);
        $year4Sem2Remark5 = $year4Sem2Remark5[0][0];
        $records1['year4Sem2Remark5'] = $year4Sem2Remark5;
        // Year 4 Sem 2 data //

        return $records1;
    }

}
