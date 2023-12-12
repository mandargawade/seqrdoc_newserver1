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
use App\Jobs\ValidateExcelMONADJob;
use App\Jobs\PdfGenerateMONADJob;
use App\Jobs\PdfGenerateMONADJob2;
use App\Jobs\PdfGenerateMONADJob3;
use App\Jobs\PdfGenerateMONADJob4; 
use App\Jobs\PdfGenerateMONADJob5; 
use App\Jobs\PdfGenerateMONADCertificate; 
class MONADCertificateController extends Controller
{
    public function index(Request $request)
    {
       return view('admin.monad.index');
    }

    public function uploadpage(){
        $response=CoreHelper::checkMonadFtpStatus();
        
        $ftp_flag=$response['ftp_flag'];
        $ftpHost=$response['ftpHost'];
        return view('admin.monad.index',compact(['ftp_flag','ftpHost']));
    }


    public function pdfGenerate(){


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


        // add spot colors
        $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10);        // For Invisible
        $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo

        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrow = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arialn.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $nikosh = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nikosh.ttf', 'TrueTypeUnicode', '', 96);

        $pdf->AddPage(); 
        //set background image
        $bg_img="MONAD_BG.jpg"; //MONAD_BG, MonadUniversity_With data
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\\'.$bg_img;
        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'L',true,3600);
        $pdf->setPageMark();
        $fontEBPath=public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\E-13B_0.php';
        $pdf->AddFont('E-13B_0', '', $fontEBPath);
        
        //Table's Titles
        $pdf->SetXY(13, 96);
        $pdf->SetFont($arial, '', 11, '', false); 
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);     
        $pdf->MultiCell(68.4, 19, '',1, 'L', 0, 0);
        $pdf->MultiCell(7.2, 19, '', 1, 'C', 0, 0);
        $pdf->MultiCell(40, 19, '', 1, 'C', 0, 0);     
        $pdf->MultiCell(40, 19, '', 1, 'C', 0, 0);     
        $pdf->MultiCell(20, 19, '', 1, 'C', 0, 0);
        $pdf->MultiCell(8.4, 19, '', 1, 'C', 0, 0);    

        $title1_x = 14.3;        
        $title1_colonx = 47.7;        
        $left_title1_y = 59.3;        
        $left_title2_y = 67.6;
        $left_title3_y = 76.4;
        $left_title4_y = 84.8;
        $left_str_x = 52.7;
        $title2_x = 113.3;
        $title2_colonx = 142.3;
        $right_str_x = 147;
        $title_font_size = '11';        
        $str_font_size = '11';        

        $enrollment_no = '1601595';
        $candidate_name = 'HARISHANKAR RAY';
        $father_name = 'JUGUTLAL RAY';
        $programme_name = 'B.TECH.(CIVIL ENGG.)';
        $roll_no = '1614310009';
        $semester = '2 YEAR';
        $session1 = '2017-2018';
        $session2 = 'Sep 2018';
        //Static Title
        $pdf->SetFont($arialb, '', 14, '', false);   
        $pdf->SetTextColor(255, 255, 0);
        $pdf->SetXY($title1_x, $left_title1_y-7);
        $pdf->Cell(0, 0, 'xxxxxx', 0, false, 'L');
        $pdf->SetFont($arial, '', $title_font_size, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($title1_x, $left_title1_y);
        $pdf->Cell(0, 0, 'Enrollment No.', 0, false, 'L');
        $pdf->SetXY($title1_colonx, $left_title1_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');        

        $pdf->SetXY($title1_x, $left_title2_y);
        $pdf->Cell(0, 0, "Candidate's Name", 0, false, 'L');
        $pdf->SetXY($title1_colonx, $left_title2_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');

        $pdf->SetXY($title1_x, $left_title3_y);
        $pdf->Cell(0, 0, "Father's Name", 0, false, 'L');
        $pdf->SetXY($title1_colonx, $left_title3_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');

        $pdf->SetXY($title1_x, $left_title4_y);
        $pdf->Cell(0, 0, "Programme", 0, false, 'L');
        $pdf->SetXY($title1_colonx, $left_title4_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');        

        $pdf->SetXY($title2_x, $left_title1_y);
        $pdf->Cell(0, 0, 'Roll No.', 0, false, 'L');
        $pdf->SetXY($title2_colonx, $left_title1_y);
        $pdf->Cell(0, 0, ':', 0, false, 'L');

        $pdf->SetXY($title2_x, $left_title2_y-1);
        $pdf->Cell(0, 0, 'Year / Semester', 0, false, 'L');
        $pdf->SetXY($title2_colonx, $left_title2_y-1);
        $pdf->Cell(0, 0, ':', 0, false, 'L');

        $pdf->SetXY($title2_x, $left_title3_y-2.5);
        $pdf->Cell(0, 0, 'Session', 0, false, 'L');
        $pdf->SetXY($title2_colonx, $left_title3_y-2.5);
        $pdf->Cell(0, 0, ':', 0, false, 'L');

        //Dynamic values
        $pdf->SetFont($arialb, '', $str_font_size, '', false);        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($left_str_x, $left_title1_y);
        $pdf->Cell(0, 0, $enrollment_no, 0, false, 'L');
        $pdf->SetXY($left_str_x, $left_title2_y);
        $pdf->Cell(0, 0, $candidate_name, 0, false, 'L');
        $pdf->SetXY($left_str_x, $left_title3_y);
        $pdf->Cell(0, 0, $father_name, 0, false, 'L');
        $pdf->SetXY($left_str_x, $left_title4_y);
        $pdf->Cell(0, 0, $programme_name, 0, false, 'L');
        $pdf->SetXY($right_str_x, $left_title1_y);
        $pdf->Cell(0, 0, $roll_no, 0, false, 'L');
        $pdf->SetXY($right_str_x, $left_title2_y-1);
        $pdf->Cell(0, 0, $semester, 0, false, 'L');
        $pdf->SetXY($right_str_x, $left_title3_y-2.4);
        $pdf->Cell(0, 0, $session1, 0, false, 'L');
        $pdf->SetXY($right_str_x, $left_title3_y+3.5);
        $pdf->Cell(0, 0, $session2, 0, false, 'L');     
     
     
        $pdf->SetXY(88.6, 104);
        $pdf->MultiCell(20, 4.5, 'Theory', 1, 'C', 0, 0);     
        $pdf->MultiCell(20, 4.5, 'Practical', 1, 'C', 0, 0);
        $pdf->SetXY(88.6, 109.4);
        $pdf->MultiCell(10, 10, 'MM', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 10, 'MO', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 10, 'MM', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 10, 'MO', 'LR', 'C', 0, 0);

        $pdf->SetXY(128.6, 104);
        $pdf->MultiCell(20, 4.5, 'Theory', 1, 'C', 0, 0);     
        $pdf->MultiCell(20, 4.5, 'Practical', 1, 'C', 0, 0);
        $pdf->SetXY(128.6, 109.4);
        $pdf->MultiCell(10, 10, 'MM', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 10, 'MO', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 10, 'MM', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 10, 'MO', 'LR', 'C', 0, 0);

        $pdf->SetXY(168.6, 109.1);
        $pdf->MultiCell(10, 10, 'MM', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, 'MO', 'LRT', 'C', 0, 0);

        $pdf->SetXY(13, 115);
        $pdf->MultiCell(3, 10, '','LT', 'L', 0, 0);
        $pdf->MultiCell(65.4, 10, '','RT', 'L', 0, 0);
        $pdf->MultiCell(7.2, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(10, 10, '', 'LRT', 'C', 0, 0);
        $pdf->MultiCell(8.4, 10, '', 'LRT', 'C', 0, 0);     

        $pdf->SetFont($arialb, '', 10, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(82.3, 115);
        $pdf->StartTransform();
        $pdf->Rotate(90);
        $pdf->Cell(0, 0, '(C) Credit', 0, false, 'L');
        $pdf->StopTransform();
        $pdf->SetXY(190, 115);
        $pdf->StartTransform();
        $pdf->Rotate(90);
        $pdf->Cell(0, 0, '(P) Grade', 0, false, 'L');
        $pdf->StopTransform();        
        $pdf->SetXY(168.5, 102);
        $pdf->SetFont($arialb, '', 9, '', false);
        $pdf->Cell(0, 0, 'Grand Total', 0, false, 'L');
        $pdf->SetFont($arialb, '', 11, '', false);
        $pdf->SetXY(25, 103);
        $pdf->Cell(0, 0, 'Course Name/Code', 0, false, 'L');
        $pdf->SetFont($arialb, '', 10.5, '', false);
        $pdf->SetXY(89.5, 97.5);
        $pdf->Cell(0, 0, 'Internal Assessment', 0, false, 'L');
        $pdf->SetXY(129, 97.5);
        $pdf->Cell(0, 0, 'External Assessment', 0, false, 'L');     


        $pdf->SetFont($arial, '', 9, '', false); 
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);     
        $pdf->SetXY(13, 116);
        //$pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(255, 255, 0)));
        //$pdf->setCellPaddings( $left = '4', $top = '', $right = '', $bottom = '');
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '[BED-211] Creating an Inclusive School','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '4', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '30', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '20', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '70', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '32', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '100', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '52<br><span style="color:yellow;">52</span>', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, 'P', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 125);
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '[BED-212] Gender, School and Society','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '3', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '30', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '20', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '70', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '34', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '100', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '54<br><span style="color:yellow;">54</span>', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, 'P', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 133);
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '[BED-213] Knowledge, Language and Curriculum','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '3', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '30', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '20', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '70', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '40', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '100', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '60<br><span style="color:yellow;">60</span>', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, 'P', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 141);
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '[BED-214] Assessment for Learning','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '4', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '30', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '20', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '70', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '35', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '100', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '55<br><span style="color:yellow;">55</span>', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, 'P', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 149);
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '[BED-217] Environment Education','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '3', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '30', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '21', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '70', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '44', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '100', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '65<br><span style="color:yellow;">65</span>', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, 'P', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 157);
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '[BED-P-219] Viva-Voce Examination Based on Workshop','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '1', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '50', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '42', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '50', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '42<br><span style="color:yellow;">44</span>', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, 'P', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 165);
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '[BED-P-2110] Understanding of I.C.T','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '2', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '50', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '44', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '50', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '44<br><span style="color:yellow;">44</span>', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, 'P', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 173);
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '[BED-P-2111] School Internship','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '5', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '50', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '42', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '150', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '128', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '200', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '170<br><span style="color:yellow;">170</span>', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, 'P', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 181);
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, '', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 189);
        $pdf->MultiCell(3, 8, '','L', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '','R', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LR', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, '', 'LR', 'C', 0, 0);
        $pdf->SetXY(13, 197);
        $pdf->MultiCell(3, 8, '','LB', 'L', 0, 0);
        $pdf->MultiCell(65.4, 8, '','RB', 'L', 0, 0);
        $pdf->MultiCell(7.2, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0);
        $pdf->MultiCell(10, 8, '', 'LRB', 'C', 0, 0, '', '', true, 0, true);
        $pdf->MultiCell(8.4, 8, '', 'LRB', 'C', 0, 0);

        $pdf->SetFont($arial, '', 12, '', false);
        //set profile image
        $profile_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\monad_prof.jpg';
        $profilex = 174.3;
        $profiley = 60;
        $profileWidth = 23;
        $profileHeight = 28;
        $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

        $photo_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\uv_photo.png';
        $photox = 115;
        $photoy = 79;
        $photoWidth = 13;
        $photoHeight = 16;
        $pdf->image($photo_path,$photox,$photoy,$photoWidth,$photoHeight,"",'','L',true,3600);    

        $uvlogo_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\UV logo.png';
        $uvlogo_x = 98;
        $uvlogo_y = 228;
        $uvlogo_Width = 23;
        $uvlogo_Height = 28;
        $pdf->image($uvlogo_path,$uvlogo_x,$uvlogo_y,$uvlogo_Width,$uvlogo_Height,"",'','L',true,3600);   

        $uvsign_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\UV Sign.png';
        $uvsign_x = 178;
        $uvsign_y = 233;
        $uvsign_Width = 20;
        $uvsign_Height = 15;
        $pdf->image($uvsign_path,$uvsign_x,$uvsign_y,$uvsign_Width,$uvsign_Height,"",'','L',true,3600);  
        //Table 2
        /*$pdf->SetXY(13, 206);    
        $pdf->SetFont($arial, '', 10, '', false);    
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);  
        $pdf->MultiCell(68.4, 4, "700", 0, 'L', 0, 0);
        $pdf->MultiCell(7.2, 4, "",0, 'C', 0, 0);  
        $pdf->MultiCell(40, 4, "",0, 'C', 0, 0);      
        $pdf->MultiCell(10, 4, "5.65", 0, 'C', 0, 0);
        $pdf->MultiCell(10, 4, "2015", 0, 'C', 0, 0);
        $pdf->MultiCell(10, 4, "600", 0, 'C', 0, 0);
        $pdf->MultiCell(10, 4, "431", 0, 'C', 0, 0);
        $pdf->MultiCell(10, 4, "7.5", 0, 'C', 0, 0);
        $pdf->MultiCell(10, 4, "2016", 0, 'C', 0, 0);
        $pdf->MultiCell(8.4, 4, "650", 0, 'C', 0, 0); */       
        
        
        //Table 3
        /*$pdf->SetFont($arialb, '', 10, '', false);
        $pdf->SetXY(13, 211);          
        $pdf->MultiCell(35, 4, "Marks Obtained", 0, 'L', 0, 0);
        $pdf->MultiCell(40, 4, "484",0, 'L', 0, 0);
        $pdf->MultiCell(40, 4, "", 0, 'L', 0, 0);     
        $pdf->MultiCell(35, 4, "", 0, 'L', 0, 0);     
        $pdf->MultiCell(34, 4, "700", 0, 'L', 0, 0);
        $pdf->SetXY(13, 215);          
        $pdf->MultiCell(35, 4, "", 0, 'L', 0, 0);
        $pdf->MultiCell(40, 4, "5.40",0, 'L', 0, 0);
        $pdf->MultiCell(40, 4, "", 0, 'L', 0, 0);     
        $pdf->MultiCell(35, 4, "", 0, 'L', 0, 0);     
        $pdf->MultiCell(34, 4, "", 0, 'L', 0, 0);*/
        
        $pdf->SetFont($arialb, '', 10, '', false);
        $pdf->SetXY(13, 206);          
        $pdf->MultiCell(30, 4, "Marks Obtained", 0, 'L', 0, 0);
        $pdf->MultiCell(5, 4, ":",0, 'L', 0, 0);
        $pdf->MultiCell(50, 4, "484", 0, 'L', 0, 0);     
        $pdf->MultiCell(35, 4, "", 0, 'L', 0, 0);     
        $pdf->MultiCell(34, 4, "", 0, 'L', 0, 0);
        $pdf->SetXY(13, 211);          
        $pdf->MultiCell(30, 4, "Maximum Marks", 0, 'L', 0, 0);
        $pdf->MultiCell(5, 4, ":",0, 'L', 0, 0);
        $pdf->MultiCell(50, 4, "700", 0, 'L', 0, 0);     
        $pdf->MultiCell(35, 4, "", 0, 'L', 0, 0);     
        $pdf->MultiCell(34, 4, "", 0, 'L', 0, 0);
        $pdf->SetXY(13, 206);          
        $pdf->MultiCell(150, 4, "Result/SGPA  :", 0, 'R', 0, 0);  
        $pdf->MultiCell(15, 4, "7.09", 0, 'L', 0, 0);    
        
        
        //Table 4
        $pdf->SetFont($arial, '', 10, '', false);
        $pdf->SetXY(13, 217);          
        $pdf->MultiCell(34, 0, "Semester", 1, 'L', 0, 0);
        $pdf->MultiCell(13, 0, "I", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "II",1, 'C', 0, 0);   
        $pdf->MultiCell(13, 0, "III", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "IV", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "V", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "VI", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "VII", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "VIII", 1, 'C', 0, 0);
        $pdf->MultiCell(24, 0, "Total", 1, 'C', 0, 0);    
        $pdf->MultiCell(22, 0, "CGPA", 1, 'C', 0, 0);    
        $pdf->SetXY(13, 221.5);          
        $pdf->MultiCell(34, 0, "Maximum Marks", 1, 'L', 0, 0);
        $pdf->MultiCell(13, 0, "307", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "427",1, 'C', 0, 0);   
        $pdf->MultiCell(13, 0, "484", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "0", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "NA", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "2389", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "", 1, 'C', 0, 0);
        $pdf->MultiCell(24, 0, "", 1, 'C', 0, 0);   
        $pdf->setCellPaddings( $left = '', $top = '7', $right = '', $bottom = '');
        $pdf->MultiCell(22, 18.6, "6.34", 1, 'C', 0, 0);    
        $pdf->setCellPaddings( $left = '', $top = '0', $right = '', $bottom = '');
        $pdf->SetXY(13, 226.2);          
        $pdf->MultiCell(34, 0, "Marks Obtained", 1, 'L', 0, 0);
        $pdf->MultiCell(13, 0, "5.29", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "7.31",1, 'C', 0, 0);   
        $pdf->MultiCell(13, 0, "7.09", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "2015", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "600", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "431", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "7.5", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "2016", 1, 'C', 0, 0);
        $pdf->MultiCell(24, 0, "", 1, 'C', 0, 0);  
        $pdf->SetXY(13, 230.7);          
        $pdf->MultiCell(34, 0, "SGPA", 1, 'L', 0, 0);
        $pdf->MultiCell(13, 0, "2017", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "2017",1, 'C', 0, 0);   
        $pdf->MultiCell(13, 0, "2018", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "0", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "NA", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "", 1, 'C', 0, 0);
        $pdf->MultiCell(24, 0, "", 1, 'C', 0, 0);   
        $pdf->SetXY(13, 235.5);          
        $pdf->MultiCell(34, 0, "Passed In Year", 1, 'L', 0, 0);
        $pdf->MultiCell(13, 0, "600", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "700",1, 'C', 0, 0);   
        $pdf->MultiCell(13, 0, "0", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "0", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "3700", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "", 1, 'C', 0, 0);
        $pdf->MultiCell(13, 0, "", 1, 'C', 0, 0);
        $pdf->MultiCell(24, 0, "", 1, 'C', 0, 0);       
        
        $pdf->SetFont($arial, '', 10.2, '', false);   
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(13.3, 250.5);
        //$pdf->Cell(0, 0, 'Date of Issue', 0, false, 'L'); 
        
        // Ghost image
        $nameOrg="BAGARIA RAHUL NAYAN";
        $ghost_font_size = '13';
        $ghostImagex = 116;
        $ghostImagey = 275.5;
        $ghostImageWidth = 55;//68
        $ghostImageHeight = 9.8;
        $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
        $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

        //qr code    
        //qr code    
        $dt = date("_ymdHis");
        $str="TEST0001";
        $codeContents =$encryptedString = strtoupper(md5($dt));
        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 176;
        $qrCodey = 14.5;
        $qrCodeWidth =21;
        $qrCodeHeight = 21;
        $ecc = 'L';
        $pixel_Size = 2;
        $frame_Size = 1;  
        \PHPQRCode\QRcode::png($codeContents, $qr_code_path, $ecc, $pixel_Size, $frame_Size);
        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, 'png', '', true, false);     
        
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
        
        $barcodex = 9;
        $barcodey = 274;
        $barcodeWidth = 60;
        $barodeHeight = 10;
        $pdf->SetAlpha(1);
        $pdf->write1DBarcode('1819356', 'C39', $barcodex, $barcodey, $barcodeWidth, $barodeHeight, 0.4, $style1D, 'N');
        
        
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', $str);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        $width=2;
        $latestWidth = round($width*3.7795280352161);

         //Updated by Mandar
        $microlinestr=$str;
        $wd = '';
        $last_width = 0;
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(173, 35.5);   
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');
      
        $pdf->Output('sample.pdf', 'I');

        //delete temp dir 26-04-2022 
        CoreHelper::rrmdir($tmpDir);
    }    

     public function validateExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $template_id=100;
        $dropdown_template_id = $request['template_id'];
        /* 1=Basic, 2=B.Ed. 1st Year, 3=B.Ed. Final, 4=Grade Final, 5=Pharma */
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
                $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                 //For checking certificate limit updated by Mandar
                $recordToGenerate=$highestRow1-1;
                $checkStatus = CoreHelper::checkMaxCertificateLimit($recordToGenerate);
                if(!$checkStatus['status']){
                  return response()->json($checkStatus);
                }
                 $excelData=array('rowData1'=>$rowData1,'auth_site_id'=>$auth_site_id,'dropdown_template_id'=>$dropdown_template_id);
                $response = $this->dispatch(new ValidateExcelMONADJob($excelData));
                //print_r($response);
                $responseData =$response->getData();
               
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
            
        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);

        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }


    }

    public function uploadfile(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $template_id = 100;
        $dropdown_template_id = $request['template_id'];
        /* 1=Basic, 2=B.Ed. 1st Year, 3=B.Ed. Final, 4=Grade Final, 5=Pharma */
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

        
        $pdfData=array('studentDataOrg'=>$rowData1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile);
        /* 1=Basic, 2=B.Ed. 1st Year, 3=B.Ed. Final, 4=Grade Final, 5=Pharma */
        if($dropdown_template_id==1){
            $link = $this->dispatch(new PdfGenerateMONADJob($pdfData));
        }
        elseif($dropdown_template_id==2){
            $link = $this->dispatch(new PdfGenerateMONADJob2($pdfData));
        }
        elseif($dropdown_template_id==3){
            $link = $this->dispatch(new PdfGenerateMONADJob3($pdfData));
        }
        elseif($dropdown_template_id==4){
            $link = $this->dispatch(new PdfGenerateMONADJob4($pdfData));
        }     
        elseif($dropdown_template_id==5){
            $link = $this->dispatch(new PdfGenerateMONADJob5($pdfData));
        }
		elseif($dropdown_template_id==6){
            $link = $this->dispatch(new PdfGenerateMONADCertificate($pdfData));
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
