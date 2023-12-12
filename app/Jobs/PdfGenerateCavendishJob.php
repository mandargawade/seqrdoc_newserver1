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

class PdfGenerateCavendishJob
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
        ini_set('memory_limit', '4096M');



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
 
        $pdfBig = new TCPDF('P', 'mm', array('215', '280'), true, 'UTF-8', false);
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
        $K100 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K100.ttf', 'TrueTypeUnicode', '', 96);
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
        //$cardDetails=$this->getNextCardNo('CAVENDISH-C');
        //$card_serial_no=$cardDetails->next_serial_no;
        
        $preview_serial_no=1;
        //$card_serial_no="";
        $log_serial_no = 1;
        $cardDetails=$this->getNextCardNo('CAVENDISH-C');
        $card_serial_no=$cardDetails->next_serial_no;

        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\RAJ_RISHI__Bg_new.jpg'; 
        $fontEBPath = public_path() . '\\' . $subdomain[0] . '\backend\canvas\fonts\E-13B_0.php';
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

        $signature_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\Vice Chancellor.png';

        $generated_documents=0;  
        // foreach ($studentDataOrg as $studentData) 
        // {
            // $card_serial_no = '';
            //For Custom Loader
             $startTimeLoader =  date('Y-m-d H:i:s');
         
             $pdfBig->AddPage();
            
             //set background image
               
             if($previewPdf==1){
                if($previewWithoutBg!=1){
                    $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
                }
             }
            $pdfBig->setPageMark();

            $pdf = new TCPDF('P', 'mm', array('215', '280'), true, 'UTF-8', false);
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
            //$template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\RAJ_RISHI_Bg_new.jpg';
            if($previewPdf!=1){
                $pdf->Image($template_img_generate, 0, 0, '215', '180', "JPG", '', 'R', true);

            }
            $pdf->setPageMark();

            // $print_serial_no = '';
            $print_serial_no = $this->nextPrintSerial();

            // print_r($studentDataOrg);
            // die();

            $certificateTopName = $studentDataOrg[0];
            $certificateAddress = $studentDataOrg[1];
            $certificateSchool = $studentDataOrg[2];
            $certificateStm = $studentDataOrg[3];
            $certificateName = strtoupper($studentDataOrg[4]);
            $certificateGender = $studentDataOrg[5];
            $certificateDOB = $studentDataOrg[6];
            $certificateNationality = $studentDataOrg[7];
            $certificateRegNo = $studentDataOrg[8];
            $certificateProgramme = $studentDataOrg[9];
            $certSchool = $studentDataOrg[10];
            $certificateYearEntry = $studentDataOrg[11];
            $certificateAward = $studentDataOrg[12];
            $certificateAward1 = $studentDataOrg[13];
            $foundationCourseCode1 = $studentDataOrg[14];
            $foundationCourseCode2 = $studentDataOrg[15];
            $foundationCourseCode3 = $studentDataOrg[16];
            $foundationCourseName1 = $studentDataOrg[17];
            $foundationCourseName2 = $studentDataOrg[18];
            $foundationCourseName3 = $studentDataOrg[19];
            $foundationGrade1 = strtoupper($studentDataOrg[20]);
            $foundationGrade2 = strtoupper($studentDataOrg[21]);
            $foundationGrade3 = strtoupper($studentDataOrg[22]);
            $foundationRemark1 = $studentDataOrg[23];
            $foundationRemark2 = $studentDataOrg[24];
            $foundationRemark3 = $studentDataOrg[25];
            $year1CourseCode1 = $studentDataOrg[26];
            $year1CourseCode2 = $studentDataOrg[27];
            $year1CourseCode3 = $studentDataOrg[28];
            $year1CourseCode4 = $studentDataOrg[29];
            $year1CourseCode5 = $studentDataOrg[30];
            $year1CourseName1 = $studentDataOrg[31];
            $year1CourseName2 = $studentDataOrg[32];
            $year1CourseName3 = $studentDataOrg[33];
            $year1CourseName4 = $studentDataOrg[34];
            $year1CourseName5 = $studentDataOrg[35];
            $year1Grade1 = strtoupper($studentDataOrg[36]);
            $year1Grade2 = strtoupper($studentDataOrg[37]);
            $year1Grade3 = strtoupper($studentDataOrg[38]);
            $year1Grade4 = strtoupper($studentDataOrg[39]);
            $year1Grade5 = strtoupper($studentDataOrg[40]);
            $year1Remark1 = $studentDataOrg[41];
            $year1Remark2 = $studentDataOrg[42];
            $year1Remark3 = $studentDataOrg[43];
            $year1Remark4 = $studentDataOrg[44];
            $year1Remark5 = $studentDataOrg[45];
            $year2CourseCode1 = $studentDataOrg[46];
            $year2CourseCode2 = $studentDataOrg[47];
            $year2CourseCode3 = $studentDataOrg[48];
            $year2CourseCode4 = $studentDataOrg[49];
            $year2CourseCode5 = $studentDataOrg[50];
            $year2CourseCode6 = $studentDataOrg[51];
            $year2CourseName1 = $studentDataOrg[52];
            $year2CourseName2 = $studentDataOrg[53];
            $year2CourseName3 = $studentDataOrg[54];
            $year2CourseName4 = $studentDataOrg[55];
            $year2CourseName5 = $studentDataOrg[56];
            $year2CourseName6 = $studentDataOrg[57];
            $year2Grade1 = strtoupper($studentDataOrg[58]);
            $year2Grade2 = strtoupper($studentDataOrg[59]);
            $year2Grade3 = strtoupper($studentDataOrg[60]);
            $year2Grade4 = strtoupper($studentDataOrg[61]);
            $year2Grade5 = strtoupper($studentDataOrg[62]);
            $year2Grade6 = strtoupper($studentDataOrg[63]);
            $year2Remark1 = $studentDataOrg[64];
            $year2Remark2 = $studentDataOrg[65];
            $year2Remark3 = $studentDataOrg[66];
            $year2Remark4 = $studentDataOrg[67];
            $year2Remark5 = $studentDataOrg[68];
            $year2Remark6 = $studentDataOrg[69];
            $year3CourseCode1 = $studentDataOrg[70];
            $year3CourseCode2 = $studentDataOrg[71];
            $year3CourseCode3 = $studentDataOrg[72];
            $year3CourseCode4 = $studentDataOrg[73];
            $year3CourseCode5 = $studentDataOrg[74];
            $year3CourseName1 = $studentDataOrg[75];
            $year3CourseName2 = $studentDataOrg[76];
            $year3CourseName3 = $studentDataOrg[77];
            $year3CourseName4 = $studentDataOrg[78];
            $year3CourseName5 = $studentDataOrg[79];
            $year3Grade1 = strtoupper($studentDataOrg[80]);
            $year3Grade2 = strtoupper($studentDataOrg[81]);
            $year3Grade3 = strtoupper($studentDataOrg[82]);
            $year3Grade4 = strtoupper($studentDataOrg[83]);
            $year3Grade5 = strtoupper($studentDataOrg[84]);
            $year3Remark1 = $studentDataOrg[85];
            $year3Remark2 = $studentDataOrg[86];
            $year3Remark3 = $studentDataOrg[87];
            $year3Remark4 = $studentDataOrg[88];
            $year3Remark5 = $studentDataOrg[89];
            $year4CourseCode1 = $studentDataOrg[90];
            $year4CourseCode2 = $studentDataOrg[91];
            $year4CourseCode3 = $studentDataOrg[92];
            $year4CourseCode4 = $studentDataOrg[93];
            $year4CourseName1 = $studentDataOrg[94];
            $year4CourseName2 = $studentDataOrg[95];
            $year4CourseName3 = $studentDataOrg[96];
            $year4CourseName4 = $studentDataOrg[97];
            $year4Grade1 = strtoupper($studentDataOrg[98]);
            $year4Grade2 = strtoupper($studentDataOrg[99]);
            $year4Grade3 = strtoupper($studentDataOrg[100]);
            $year4Grade4 = strtoupper($studentDataOrg[101]);
            $year4Remark1 = $studentDataOrg[102];
            $year4Remark2 = $studentDataOrg[103];
            $year4Remark3 = $studentDataOrg[104];
            $year4Remark4 = $studentDataOrg[105];
            $year5CourseCode1 = $studentDataOrg[106];
            $year5CourseCode2 = $studentDataOrg[107];
            $year5CourseCode3 = $studentDataOrg[108];
            $year5CourseCode4 = $studentDataOrg[109];
            $year5CourseCode5 = $studentDataOrg[110];
            $year5CourseName1 = $studentDataOrg[111];
            $year5CourseName2 = $studentDataOrg[112];
            $year5CourseName3 = $studentDataOrg[113];
            $year5CourseName4 = $studentDataOrg[114];
            $year5CourseName5 = $studentDataOrg[115];
            $year5Grade1 = $studentDataOrg[116];
            $year5Grade2 = $studentDataOrg[117];
            $year5Grade3 = $studentDataOrg[118];
            $year5Grade4 = $studentDataOrg[119];
            $year5Grade5 = $studentDataOrg[120];
            $year5Remark1 = $studentDataOrg[121];
            $year5Remark2 = $studentDataOrg[122];
            $year5Remark3 = $studentDataOrg[123];
            $year5Remark4 = $studentDataOrg[124];
            $year5Remark5 = $studentDataOrg[125];
            $year6CourseCode1 = $studentDataOrg[126];
            $year6CourseCode2 = $studentDataOrg[127];
            $year6CourseCode3 = $studentDataOrg[128];
            $year6CourseCode4 = $studentDataOrg[129];
            $year6CourseName1 = $studentDataOrg[130];
            $year6CourseName2 = $studentDataOrg[131];
            $year6CourseName3 = $studentDataOrg[132];
            $year6CourseName4 = $studentDataOrg[133];
            $year6Grade1 = $studentDataOrg[134];
            $year6Grade2 = $studentDataOrg[135];
            $year6Grade3 = $studentDataOrg[136];
            $year6Grade4 = $studentDataOrg[137];
            $year6Remark1 = $studentDataOrg[138];
            $year6Remark2 = $studentDataOrg[139];
            $year6Remark3 = $studentDataOrg[140];
            $year6Remark4 = $studentDataOrg[141];



            // Certificate Data 
            // for testing data
            // $pdf = new TCPDF('P', 'mm', array('215', '280'), true, 'UTF-8', false);
            // $pdf->SetCreator(PDF_CREATOR);
            // $pdf->SetAuthor('TCPDF');
            // $pdf->SetTitle('Certificate');
            // $pdf->SetSubject('');

            // // remove default header/footer
            // $pdf->setPrintHeader(false);
            // $pdf->setPrintFooter(false);
            // $pdf->SetAutoPageBreak(false, 0);
            
            // $pdf->AddPage();
            // $pdf->SetMargins(20, 0, 20, false);
            //set background image
            // //$template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\RAJ_RISHI_Bg_new.jpg';
            // if($previewPdf!=1){
            //     $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

            // }
            // $pdf->setPageMark();
            $pdfBig->SetTextColor(0,0,0);
            $pdf->SetTextColor(0,0,0);

            $xStart= 21;
            $pageWidth = 178;
            $pdfBig->SetFont($times, 'B', 12, '', false);
            $pdfBig->SetXY($xStart, 11);
            $pdfBig->SetTextColor(200,180,216);
            $pdfBig->MultiCell($pageWidth, 0, 'CAVENDISH', 0, 'C', 0, 0, '', '', false);

            $pdf->SetFont($times, 'B', 12, '', false);
            $pdf->SetXY($xStart, 11);
            $pdf->SetTextColor(200,180,216);
            $pdf->MultiCell($pageWidth, 0, 'CAVENDISH', 0, 'C', 0, 0, '', '', false);

            

            $pdfBig->SetXY(177, 12);
            $pdfBig->Image('cavendish/image.PNG', '', '', 22, 29, '', '', 'T', false, 300, '', false, false, 1, false, false, false);

            $pdf->SetXY(177, 12);
            $pdf->Image('cavendish/image.PNG', '', '', 22, 29, '', '', 'T', false, 300, '', false, false, 1, false, false, false);


            $pdfBig->SetFont($times, 'B', 12, '', false);
            $pdfBig->SetXY($xStart, 16);
            $pdfBig->SetTextColor(24,57,106);
            $pdfBig->Cell($pageWidth, 0, "UNIVERSITY", 0, false, 'C');

            $pdf->SetFont($times, 'B', 12, '', false);
            $pdf->SetXY($xStart, 16);
            $pdf->SetTextColor(24,57,106);
            $pdf->Cell($pageWidth, 0, "UNIVERSITY", 0, false, 'C');


            $pdfBig->SetFont($times, '', 13, '', false);
            $pdfBig->SetXY($xStart, 21);
            $pdfBig->SetTextColor(24,57,106);
            $pdfBig->Cell($pageWidth, 0, "Z A M B I A", 0, false, 'C');

            $pdf->SetFont($times, '', 13, '', false);
            $pdf->SetXY($xStart, 21);
            $pdf->SetTextColor(24,57,106);
            $pdf->Cell($pageWidth, 0, "Z A M B I A", 0, false, 'C');


            $pdfBig->SetFont($times, 'I', 7.5, '', false);
            $pdfBig->SetXY($xStart, 26);
            $pdfBig->SetTextColor(24,57,106);
            $pdfBig->Cell($pageWidth, 0, $certificateTopName, 0, false, 'C');

            $pdf->SetFont($times, 'I', 7.5, '', false);
            $pdf->SetXY($xStart, 26);
            $pdf->SetTextColor(24,57,106);
            $pdf->Cell($pageWidth, 0, $certificateTopName, 0, false, 'C');


            $pdfBig->SetFont($times, '', 7, '', false);
            $pdfBig->SetXY($xStart, 29);
            $pdfBig->SetTextColor(24,57,106);
            $pdfBig->Cell($pageWidth, 0, $certificateAddress, 0, false, 'C');

            $pdf->SetFont($times, '', 7, '', false);
            $pdf->SetXY($xStart, 29);
            $pdf->SetTextColor(24,57,106);
            $pdf->Cell($pageWidth, 0, $certificateAddress, 0, false, 'C');


            $pdfBig->SetFont($times, 'B', 11, '', false);
            $pdfBig->SetXY($xStart, 32);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->Cell($pageWidth, 0, 'OFFICE OF THE ACADEMIC REGISTER', 0, false, 'C');

            $pdf->SetFont($times, 'B', 11, '', false);
            $pdf->SetXY($xStart, 32);
            $pdf->SetTextColor(0,0,0);
            $pdf->Cell($pageWidth, 0, 'OFFICE OF THE ACADEMIC REGISTER', 0, false, 'C');


            $pdfBig->SetFont($times, 'B', 11, '', false);
            $pdfBig->SetXY($xStart, 36);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->Cell($pageWidth, 0, 'ACADEMIC TRANSCRIPT', 0, false, 'C');

            $pdf->SetFont($times, 'B', 11, '', false);
            $pdf->SetXY($xStart, 36);
            $pdf->SetTextColor(0,0,0);
            $pdf->Cell($pageWidth, 0, 'ACADEMIC TRANSCRIPT', 0, false, 'C');


            $pdfBig->setCellPaddings( $left = '0', $top = '0.5', $right = '', $bottom = '');
            $pdf->setCellPaddings( $left = '0', $top = '0.5', $right = '', $bottom = '');

            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY($xStart, 42);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->Cell(30, 0, 'Name', 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY($xStart, 42);
            $pdf->SetTextColor(0,0,0);
            $pdf->Cell(30, 0, 'Name', 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(41, 42);
            $pdfBig->Cell(0, 0, $certificateName, 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(41, 42);
            $pdf->Cell(0, 0, $certificateName, 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY($xStart, 46);
            $pdfBig->Cell(30, 0, 'Gender', 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY($xStart, 46);
            $pdf->Cell(30, 0, 'Gender', 0, false, 'L');

            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(41, 46);
            $pdfBig->Cell(0, 0, $certificateGender, 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(41, 46);
            $pdf->Cell(0, 0, $certificateGender, 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY($xStart, 49);
            $pdfBig->Cell(30, 0, 'Date of Birth', 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY($xStart, 49);
            $pdf->Cell(30, 0, 'Date of Birth', 0, false, 'L');

            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(41, 49);
            $pdfBig->Cell(0, 0, $certificateDOB, 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(41, 49);
            $pdf->Cell(0, 0, $certificateDOB, 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY($xStart, 52);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->Cell(30, 0, 'Nationality', 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY($xStart, 52);
            $pdf->SetTextColor(0,0,0);
            $pdf->Cell(30, 0, 'Nationality', 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(41, 52);
            $pdfBig->Cell(0, 0, $certificateNationality, 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(41, 52);
            $pdf->Cell(0, 0, $certificateNationality, 0, false, 'L');

            // second part right top heading
            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(110, 42);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->Cell(30, 0, 'Student No.', 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(110, 42);
            $pdf->SetTextColor(0,0,0);
            $pdf->Cell(30, 0, 'Student No.', 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(132, 42);
            $pdfBig->Cell(0, 0, $certificateRegNo, 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(132, 42);
            $pdf->Cell(0, 0, $certificateRegNo, 0, false, 'L');



            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(110, 46);
            $pdfBig->Cell(30, 0, 'Programme', 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(110, 46);
            $pdf->Cell(30, 0, 'Programme', 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(132, 46);
            $pdfBig->Cell(0, 0, $certificateProgramme, 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(132, 46);
            $pdf->Cell(0, 0, $certificateProgramme, 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(110, 49);
            $pdfBig->Cell(30, 0, 'School', 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(110, 49);
            $pdf->Cell(30, 0, 'School', 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(132, 49);
            $pdfBig->Cell(0, 0, $certificateSchool, 0, false, 'L');


            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(132, 49);
            $pdf->Cell(0, 0, $certificateSchool, 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(110, 52);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->Cell(30, 0, 'Year of Entry', 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(110, 52);
            $pdf->SetTextColor(0,0,0);
            $pdf->Cell(30, 0, 'Year of Entry', 0, false, 'L');


            $pdfBig->SetFont($Arial, '', 9, '', false);
            $pdfBig->SetXY(132, 52);
            $pdfBig->Cell(0, 0, $certificateYearEntry, 0, false, 'L');

            $pdf->SetFont($Arial, '', 9, '', false);
            $pdf->SetXY(132, 52);
            $pdf->Cell(0, 0, $certificateYearEntry, 0, false, 'L');


            // start foundation year table
            $tableY = 56;
            $pdfBig->SetFont($Arial, 'B', 7, '', false);
            $pdfBig->SetXY($xStart, $tableY);
            $pdfBig->SetTextColor(255,255,255);
            $pdfBig->SetFillColor(24,57,106);
            $pdfBig->MultiCell($pageWidth, 4, 'FOUNDATION YEAR', '', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 7, '', false);
            $pdf->SetXY($xStart, $tableY);
            $pdf->SetTextColor(255,255,255);
            $pdf->SetFillColor(24,57,106);
            $pdf->MultiCell($pageWidth, 4, 'FOUNDATION YEAR', '', 'C', 1, 0);



            $tableY = $tableY+4;
            $pdfBig->SetFont($Arial, 'B', 7, '', false);
            $pdfBig->SetXY($xStart, $tableY);
            $pdfBig->SetFillColor(216, 204, 201 );
            $pdfBig->MultiCell($pageWidth, 4.5, '', '', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 7, '', false);
            $pdf->SetXY($xStart, $tableY);
            $pdf->SetFillColor(216, 204, 201 );
            $pdf->MultiCell($pageWidth, 4.5, '', '', 'C', 1, 0);


            $tableY = $tableY+4.5;

            $tableX = 21;
            $pdfBig->setCellPaddings( $left = '1', $top = '0.5', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->SetFillColor(255,255,255);
            $pdfBig->MultiCell(19, 7, 'Module Code', 'TBRL', 'L', 1, 0);
            
            $pdf->setCellPaddings( $left = '1', $top = '0.5', $right = '', $bottom = '');
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetTextColor(0,0,0);
            $pdf->SetFillColor(255,255,255);
            $pdf->MultiCell(19, 7, 'Module Code', 'TBRL', 'L', 1, 0);

            $tableX = $tableX +19;
            
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(116, 7, 'Module Name', 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(116, 7, 'Module Name', 'TBRL', 'L', 1, 0);

            $tableX = $tableX +116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(19, 7, 'Grade', 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(19, 7, 'Grade', 'TBRL', 'C', 1, 0);


            $tableX = $tableX +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(24, 7, 'Remark', 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(24, 7, 'Remark', 'TBRL', 'C', 1, 0);


            $foundationX = 21;
            $foundationY = $tableY +7;
            $tableHeight= 4;
            $pdfBig->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(19, $tableHeight, $foundationCourseCode1, 'TBRL', 'L', 1, 0);

            $pdf->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(19, $tableHeight, $foundationCourseCode1, 'TBRL', 'L', 1, 0);

            $foundationX = $foundationX +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(116, $tableHeight, $foundationCourseName1, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(116, $tableHeight, $foundationCourseName1, 'TBRL', 'L', 1, 0);
            $foundationX = $foundationX +116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(19, $tableHeight,  $foundationGrade1, 'TBRL', 'C', 1, 0);
            
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(19, $tableHeight,  $foundationGrade1, 'TBRL', 'C', 1, 0);

            $foundationX = $foundationX +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(24, $tableHeight, $foundationRemark1, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(24, $tableHeight, $foundationRemark1, 'TBRL', 'L', 1, 0);

            

            $foundationX = 21;
            $foundationY = $foundationY +$tableHeight;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(19, $tableHeight, $foundationCourseCode2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(19, $tableHeight, $foundationCourseCode2, 'TBRL', 'L', 1, 0);

            $foundationX = $foundationX +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(116, $tableHeight, $foundationCourseName2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(116, $tableHeight, $foundationCourseName2, 'TBRL', 'L', 1, 0);

            $foundationX = $foundationX +116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(19, $tableHeight, $foundationGrade2, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(19, $tableHeight, $foundationGrade2, 'TBRL', 'C', 1, 0);

            $foundationX = $foundationX +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(24, $tableHeight, $foundationRemark2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(24, $tableHeight, $foundationRemark2, 'TBRL', 'L', 1, 0);
            

            $foundationX = 21;
            $foundationY = $foundationY +$tableHeight;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(19, $tableHeight, $foundationCourseCode3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(19, $tableHeight, $foundationCourseCode3, 'TBRL', 'L', 1, 0);
            $foundationX = $foundationX +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(116, $tableHeight, $foundationCourseName3, 'TBRL', 'L', 1, 0);
            
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(116, $tableHeight, $foundationCourseName3, 'TBRL', 'L', 1, 0);

            $foundationX = $foundationX +116;


            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(19, $tableHeight, $foundationGrade3, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(19, $tableHeight, $foundationGrade3, 'TBRL', 'C', 1, 0);

            $foundationX = $foundationX +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($foundationX, $foundationY);
            $pdfBig->MultiCell(24, $tableHeight, $foundationRemark3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($foundationX, $foundationY);
            $pdf->MultiCell(24, $tableHeight, $foundationRemark3, 'TBRL', 'L', 1, 0);

            $foundationY = $foundationY +$tableHeight;

            $foundationX = 21;
            for($i=0; $i<4; $i++) {

                $pdfBig->SetFont($Arial, 'B', 7, '', false);
                $pdfBig->SetXY($foundationX, $foundationY);
                $pdfBig->MultiCell(19, $tableHeight, '', 'TBRL', 'L', 1, 0);

                $pdf->SetFont($Arial, 'B', 7, '', false);
                $pdf->SetXY($foundationX, $foundationY);
                $pdf->MultiCell(19, $tableHeight, '', 'TBRL', 'L', 1, 0);
                $foundationX = $foundationX+19;

                $pdfBig->SetFont($Arial, 'B', 7, '', false);
                $pdfBig->SetXY($foundationX, $foundationY);
                $pdfBig->MultiCell(116, $tableHeight,'', 'TBRL', 'L', 1, 0);
                
                $pdf->SetFont($Arial, 'B', 7, '', false);
                $pdf->SetXY($foundationX, $foundationY);
                $pdf->MultiCell(116, $tableHeight,'', 'TBRL', 'L', 1, 0);
                $foundationX = $foundationX+116;

                $pdfBig->SetFont($Arial, 'B', 7, '', false);
                $pdfBig->SetXY($foundationX, $foundationY);
                $pdfBig->MultiCell(19, $tableHeight, '', 'TBRL', 'C', 1, 0);

                $pdf->SetFont($Arial, 'B', 7, '', false);
                $pdf->SetXY($foundationX, $foundationY);
                $pdf->MultiCell(19, $tableHeight, '', 'TBRL', 'C', 1, 0);
                $foundationX = $foundationX+19;

                $pdfBig->SetFont($Arial, 'B', 7, '', false);
                $pdfBig->SetXY($foundationX, $foundationY);
                $pdfBig->MultiCell(24, $tableHeight, '', 'TBRL', 'C', 1, 0);

                $pdf->SetFont($Arial, 'B', 7, '', false);
                $pdf->SetXY($foundationX, $foundationY);
                $pdf->MultiCell(24, $tableHeight, '', 'TBRL', 'C', 1, 0);
                $foundationY = $foundationY +$tableHeight;
                $foundationX = 21;
            }
            // end foundation year table

            // start year one table
            $tableX = 21;
            $tableY = $foundationY;
            $pdfBig->SetFont($Arial, 'B', 7, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetTextColor(255,255,255);
            $pdfBig->SetFillColor(19,57,106);
            $pdfBig->MultiCell($pageWidth, 4, 'YEAR ONE', '', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 7, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetTextColor(255,255,255);
            $pdf->SetFillColor(19,57,106);
            $pdf->MultiCell($pageWidth, 4, 'YEAR ONE', '', 'C', 1, 0);


            $tableY = $tableY+4;
            $pdfBig->SetFont($Arial, 'B', 7, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetFillColor(216, 204, 201 );
            $pdfBig->MultiCell($pageWidth, 4.5, '', '', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 7, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetFillColor(216, 204, 201 );
            $pdf->MultiCell($pageWidth, 4.5, '', '', 'C', 1, 0);


            $tableY = $tableY+4.5;
            $pdfBig->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->SetFillColor(255,255,255);
            $pdfBig->MultiCell(19, 7, 'Module Code', 'TBRL', 'L', 1, 0);

            $pdf->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetTextColor(0,0,0);
            $pdf->SetFillColor(255,255,255);
            $pdf->MultiCell(19, 7, 'Module Code', 'TBRL', 'L', 1, 0);
            $tableX = $tableX + 19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(116, 7, 'Module Name', 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(116, 7, 'Module Name', 'TBRL', 'L', 1, 0);
            $tableX = $tableX+116;


            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(19, 7, 'Grade', 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(19, 7, 'Grade', 'TBRL', 'C', 1, 0);

            $tableX = $tableX+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(24, 7, 'Remark', 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(24, 7, 'Remark', 'TBRL', 'C', 1, 0);

            
            $year1X= 21;
            $year1Y = $tableY +7;
            $pdfBig->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight, $year1CourseCode1, 'TBRL', 'L', 1, 0);

            $pdf->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight, $year1CourseCode1, 'TBRL', 'L', 1, 0);

            $year1X = $year1X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(116, $tableHeight, $year1CourseName1, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(116, $tableHeight, $year1CourseName1, 'TBRL', 'L', 1, 0);
            $year1X = $year1X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight,  $year1Grade1, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight,  $year1Grade1, 'TBRL', 'C', 1, 0);
            $year1X = $year1X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(24, $tableHeight, $year1Remark1, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(24, $tableHeight, $year1Remark1, 'TBRL', 'L', 1, 0);
            $year1X =21;
            $year1Y = $year1Y +$tableHeight;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight, $year1CourseCode2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight, $year1CourseCode2, 'TBRL', 'L', 1, 0);
            $year1X = $year1X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(116, $tableHeight, $year1CourseName2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(116, $tableHeight, $year1CourseName2, 'TBRL', 'L', 1, 0);
            $year1X = $year1X +116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight, $year1Grade2, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight, $year1Grade2, 'TBRL', 'C', 1, 0);
            $year1X = $year1X +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(24, $tableHeight, $year1Remark2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(24, $tableHeight, $year1Remark2, 'TBRL', 'L', 1, 0);
            $year1Y = $year1Y +$tableHeight;

            $year1X = 21;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight, $year1CourseCode3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight, $year1CourseCode3, 'TBRL', 'L', 1, 0);
            $year1X = $year1X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(116, $tableHeight, $year1CourseName3, 'TBRL', 'L', 1, 0);
            
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(116, $tableHeight, $year1CourseName3, 'TBRL', 'L', 1, 0);

            $year1X = $year1X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight, $year1Grade3, 'TBRL', 'C', 1, 0);
            
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight, $year1Grade3, 'TBRL', 'C', 1, 0);
            $year1X = $year1X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(24, $tableHeight, $year1Remark3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(24, $tableHeight, $year1Remark3, 'TBRL', 'L', 1, 0);

            $year1X = 21;
            $year1Y = $year1Y +$tableHeight;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight, $year1CourseCode4, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight, $year1CourseCode4, 'TBRL', 'L', 1, 0);
            $year1X = $year1X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(116, $tableHeight, $year1CourseName4, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(116, $tableHeight, $year1CourseName4, 'TBRL', 'L', 1, 0);
            $year1X = $year1X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight, $year1Grade4, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight, $year1Grade4, 'TBRL', 'C', 1, 0);
            $year1X = $year1X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(24, $tableHeight, $year1Remark4, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(24, $tableHeight, $year1Remark4, 'TBRL', 'L', 1, 0);

            $year1X = 21;
            $year1Y = $year1Y +$tableHeight;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight, $year1CourseCode5, 'TBRL', 'L', 1, 0);
            
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight, $year1CourseCode5, 'TBRL', 'L', 1, 0);
            $year1X = $year1X+19;


            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(116, $tableHeight, $year1CourseName5, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(116, $tableHeight, $year1CourseName5, 'TBRL', 'L', 1, 0);
            $year1X = $year1X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(19, $tableHeight, $year1Grade5, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(19, $tableHeight, $year1Grade5, 'TBRL', 'C', 1, 0);
            $year1X = $year1X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year1X, $year1Y);
            $pdfBig->MultiCell(24, $tableHeight, $year1Remark5, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year1X, $year1Y);
            $pdf->MultiCell(24, $tableHeight, $year1Remark5, 'TBRL', 'L', 1, 0);
            $year1Y = $year1Y +$tableHeight;

            $year1X = 21;


            for($i=0; $i<2; $i++) {

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year1X, $year1Y);
                $pdfBig->MultiCell(19, $tableHeight, '', 'TBRL', 'L', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year1X, $year1Y);
                $pdf->MultiCell(19, $tableHeight, '', 'TBRL', 'L', 1, 0);
                $year1X = $year1X+19;

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year1X, $year1Y);
                $pdfBig->MultiCell(116, $tableHeight,'', 'TBRL', 'L', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year1X, $year1Y);
                $pdf->MultiCell(116, $tableHeight,'', 'TBRL', 'L', 1, 0);
                $year1X = $year1X+116;

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year1X, $year1Y);
                $pdfBig->MultiCell(19, $tableHeight, '', 'TBRL', 'C', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year1X, $year1Y);
                $pdf->MultiCell(19, $tableHeight, '', 'TBRL', 'C', 1, 0);
                $year1X = $year1X+19;

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year1X, $year1Y);
                $pdfBig->MultiCell(24, $tableHeight, '', 'TBRL', 'C', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year1X, $year1Y);
                $pdf->MultiCell(24, $tableHeight, '', 'TBRL', 'C', 1, 0);
                $year1X = 21;
                $year1Y = $year1Y +$tableHeight;
            }
            // end year one table



            // start year two table
            $tableX = 21;
            $tableY = $year1Y;
            $pdfBig->SetFont($Arial, 'B', 7, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetTextColor(255,255,255);
            $pdfBig->SetFillColor(19,57,106);
            $pdfBig->MultiCell($pageWidth, 4, 'YEAR TWO', '', 'C', 1, 0);
            
            $pdf->SetFont($Arial, 'B', 7, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetTextColor(255,255,255);
            $pdf->SetFillColor(19,57,106);
            $pdf->MultiCell($pageWidth, 4, 'YEAR TWO', '', 'C', 1, 0);
            $tableY = $tableY+4;

            $pdfBig->SetFont($Arial, 'B', 7, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetFillColor(216, 204, 201 );
            $pdfBig->MultiCell($pageWidth, 4.5, '', '', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 7, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetFillColor(216, 204, 201 );
            $pdf->MultiCell($pageWidth, 4.5, '', '', 'C', 1, 0);

            $tableY = $tableY+4.5;
            $pdfBig->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->SetFillColor(255,255,255);
            $pdfBig->MultiCell(19, 7, 'Module Code', 'TBRL', 'L', 1, 0);

            $pdf->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetTextColor(0,0,0);
            $pdf->SetFillColor(255,255,255);
            $pdf->MultiCell(19, 7, 'Module Code', 'TBRL', 'L', 1, 0);
            $tableX = $tableX + 19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(116, 7, 'Module Name', 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(116, 7, 'Module Name', 'TBRL', 'L', 1, 0);
            $tableX = $tableX+116;


            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(19, 7, 'Grade', 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(19, 7, 'Grade', 'TBRL', 'C', 1, 0);
            $tableX = $tableX+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(24, 7, 'Remark', 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(24, 7, 'Remark', 'TBRL', 'C', 1, 0);
            $year2X= 21;
            
            $year2Y = $tableY +7;
            $pdfBig->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2CourseCode1, 'TBRL', 'L', 1, 0);

            $pdf->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2CourseCode1, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(116, $tableHeight, $year2CourseName1, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(116, $tableHeight, $year2CourseName1, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight,  $year2Grade1, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight,  $year2Grade1, 'TBRL', 'C', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(24, $tableHeight, $year2Remark1, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(24, $tableHeight, $year2Remark1, 'TBRL', 'L', 1, 0);

            $year2X =21;
            $year2Y = $year2Y +$tableHeight;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2CourseCode2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2CourseCode2, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(116, $tableHeight, $year2CourseName2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(116, $tableHeight, $year2CourseName2, 'TBRL', 'L', 1, 0);
            $year2X = $year2X +116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2Grade2, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2Grade2, 'TBRL', 'C', 1, 0);
            $year2X = $year2X +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(24, $tableHeight, $year2Remark2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(24, $tableHeight, $year2Remark2, 'TBRL', 'L', 1, 0);
            $year2Y = $year2Y +$tableHeight;

            $year2X = 21;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2CourseCode3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2CourseCode3, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(116, $tableHeight, $year2CourseName3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(116, $tableHeight, $year2CourseName3, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2Grade3, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2Grade3, 'TBRL', 'C', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(24, $tableHeight, $year2Remark3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(24, $tableHeight, $year2Remark3, 'TBRL', 'L', 1, 0);

            $year2X = 21;
            $year2Y = $year2Y +$tableHeight;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2CourseCode4, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2CourseCode4, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(116, $tableHeight, $year2CourseName4, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(116, $tableHeight, $year2CourseName4, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2Grade4, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2Grade4, 'TBRL', 'C', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(24, $tableHeight, $year2Remark4, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(24, $tableHeight, $year2Remark4, 'TBRL', 'L', 1, 0);

            $year2X = 21;
            $year2Y = $year2Y +$tableHeight;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2CourseCode5, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2CourseCode5, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(116, $tableHeight, $year2CourseName5, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(116, $tableHeight, $year2CourseName5, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2Grade5, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2Grade5, 'TBRL', 'C', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(24, $tableHeight, $year2Remark5, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(24, $tableHeight, $year2Remark5, 'TBRL', 'L', 1, 0);

            $year2X = 21;
            $year2Y = $year2Y +$tableHeight;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2CourseCode6, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2CourseCode6, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(116, $tableHeight, $year2CourseName6, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(116, $tableHeight, $year2CourseName6, 'TBRL', 'L', 1, 0);
            $year2X = $year2X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(19, $tableHeight, $year2Grade6, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(19, $tableHeight, $year2Grade6, 'TBRL', 'C', 1, 0);
            $year2X = $year2X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year2X, $year2Y);
            $pdfBig->MultiCell(24, $tableHeight, $year2Remark6, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year2X, $year2Y);
            $pdf->MultiCell(24, $tableHeight, $year2Remark6, 'TBRL', 'L', 1, 0);


            $year2X = 21;
            $year2Y = $year2Y +$tableHeight;

            for($i=0; $i<1; $i++) {

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year2X, $year2Y);
                $pdfBig->MultiCell(19, $tableHeight, '', 'TBRL', 'L', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year2X, $year2Y);
                $pdf->MultiCell(19, $tableHeight, '', 'TBRL', 'L', 1, 0);
                $year2X = $year2X+19;

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year2X, $year2Y);
                $pdfBig->MultiCell(116, $tableHeight,'', 'TBRL', 'L', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year2X, $year2Y);
                $pdf->MultiCell(116, $tableHeight,'', 'TBRL', 'L', 1, 0);
                $year2X = $year2X+116;

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year2X, $year2Y);
                $pdfBig->MultiCell(19, $tableHeight, '', 'TBRL', 'C', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year2X, $year2Y);
                $pdf->MultiCell(19, $tableHeight, '', 'TBRL', 'C', 1, 0);
                $year2X = $year2X+19;

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year2X, $year2Y);
                $pdfBig->MultiCell(24, $tableHeight, '', 'TBRL', 'C', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year2X, $year2Y);
                $pdf->MultiCell(24, $tableHeight, '', 'TBRL', 'C', 1, 0);
                $year2X = 21;
                $year2Y = $year2Y +$tableHeight;
            }
            // end year two table



            // start year three table
            $tableX = 21;
            $tableY = $year2Y;
            $pdfBig->SetFont($Arial, 'B', 7, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetTextColor(255,255,255);
            $pdfBig->SetFillColor(19,57,106);
            $pdfBig->MultiCell($pageWidth, 4, 'YEAR THREE', '', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 7, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetTextColor(255,255,255);
            $pdf->SetFillColor(19,57,106);
            $pdf->MultiCell($pageWidth, 4, 'YEAR THREE', '', 'C', 1, 0);

            $tableY = $tableY+4;
            $pdfBig->SetFont($Arial, 'B', 7, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetFillColor(216, 204, 201 );
            $pdfBig->MultiCell($pageWidth, 4.5, '', '', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 7, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetFillColor(216, 204, 201 );
            $pdf->MultiCell($pageWidth, 4.5, '', '', 'C', 1, 0);

            $tableY = $tableY+4.5;
            $pdfBig->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->SetFillColor(255,255,255);
            $pdfBig->MultiCell(19, 7, 'Module Code', 'TBRL', 'L', 1, 0);

            $pdf->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->SetTextColor(0,0,0);
            $pdf->SetFillColor(255,255,255);
            $pdf->MultiCell(19, 7, 'Module Code', 'TBRL', 'L', 1, 0);
            $tableX = $tableX + 19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(116, 7, 'Module Name', 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(116, 7, 'Module Name', 'TBRL', 'L', 1, 0);
            $tableX = $tableX+116;


            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(19, 7, 'Grade', 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(19, 7, 'Grade', 'TBRL', 'C', 1, 0);
            $tableX = $tableX+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($tableX, $tableY);
            $pdfBig->MultiCell(24, 7, 'Remark', 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($tableX, $tableY);
            $pdf->MultiCell(24, 7, 'Remark', 'TBRL', 'C', 1, 0);
            
            $year3X= 21;
            $year3Y = $tableY +7;
            $pdfBig->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight, $year3CourseCode1, 'TBRL', 'L', 1, 0);

            $pdf->setCellPaddings( $left = '', $top = '0.5', $right = '', $bottom = '');
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight, $year3CourseCode1, 'TBRL', 'L', 1, 0);
            $year3X = $year3X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(116, $tableHeight, $year3CourseName1, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(116, $tableHeight, $year3CourseName1, 'TBRL', 'L', 1, 0);
            $year3X = $year3X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight,  $year3Grade1, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight,  $year3Grade1, 'TBRL', 'C', 1, 0);
            $year3X = $year3X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(24, $tableHeight, $year3Remark1, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(24, $tableHeight, $year3Remark1, 'TBRL', 'L', 1, 0);

            $year3X =21;
            $year3Y = $year3Y +$tableHeight;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight, $year3CourseCode2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight, $year3CourseCode2, 'TBRL', 'L', 1, 0);
            $year3X = $year3X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(116, $tableHeight, $year3CourseName2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(116, $tableHeight, $year3CourseName2, 'TBRL', 'L', 1, 0);
            $year3X = $year3X +116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight, $year3Grade2, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight, $year3Grade2, 'TBRL', 'C', 1, 0);
            $year3X = $year3X +19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(24, $tableHeight, $year3Remark2, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(24, $tableHeight, $year3Remark2, 'TBRL', 'L', 1, 0);
            $year3Y = $year3Y +$tableHeight;

            $year3X = 21;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight, $year3CourseCode3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight, $year3CourseCode3, 'TBRL', 'L', 1, 0);
            $year3X = $year3X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(116, $tableHeight, $year3CourseName3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(116, $tableHeight, $year3CourseName3, 'TBRL', 'L', 1, 0);
            $year3X = $year3X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight, $year3Grade3, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight, $year3Grade3, 'TBRL', 'C', 1, 0);
            $year3X = $year3X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(24, $tableHeight, $year3Remark3, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(24, $tableHeight, $year3Remark3, 'TBRL', 'L', 1, 0);

            $year3X = 21;
            $year3Y = $year3Y +$tableHeight;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight, $year3CourseCode4, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight, $year3CourseCode4, 'TBRL', 'L', 1, 0);
            $year3X = $year3X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(116, $tableHeight, $year3CourseName4, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(116, $tableHeight, $year3CourseName4, 'TBRL', 'L', 1, 0);
            $year3X = $year3X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight, $year3Grade4, 'TBRL', 'C', 1, 0);
            
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight, $year3Grade4, 'TBRL', 'C', 1, 0);
            $year3X = $year3X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(24, $tableHeight, $year3Remark4, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(24, $tableHeight, $year3Remark4, 'TBRL', 'L', 1, 0);

            $year3X = 21;
            $year3Y = $year3Y +$tableHeight;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight, $year3CourseCode5, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight, $year3CourseCode5, 'TBRL', 'L', 1, 0);
            $year3X = $year3X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(116, $tableHeight, $year3CourseName5, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(116, $tableHeight, $year3CourseName5, 'TBRL', 'L', 1, 0);
            $year3X = $year3X+116;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(19, $tableHeight, $year3Grade5, 'TBRL', 'C', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(19, $tableHeight, $year3Grade5, 'TBRL', 'C', 1, 0);
            $year3X = $year3X+19;

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($year3X, $year3Y);
            $pdfBig->MultiCell(24, $tableHeight, $year3Remark5, 'TBRL', 'L', 1, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($year3X, $year3Y);
            $pdf->MultiCell(24, $tableHeight, $year3Remark5, 'TBRL', 'L', 1, 0);


            $year3X = 21;
            $year3Y = $year3Y +$tableHeight;

            for($i=0; $i<4; $i++) {

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year3X, $year3Y);
                $pdfBig->MultiCell(19, $tableHeight, '', 'TBRL', 'L', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year3X, $year3Y);
                $pdf->MultiCell(19, $tableHeight, '', 'TBRL', 'L', 1, 0);
                $year3X = $year3X+19;

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year3X, $year3Y);
                $pdfBig->MultiCell(116, $tableHeight,'', 'TBRL', 'L', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year3X, $year3Y);
                $pdf->MultiCell(116, $tableHeight,'', 'TBRL', 'L', 1, 0);
                $year3X = $year3X+116;

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year3X, $year3Y);
                $pdfBig->MultiCell(19, $tableHeight, '', 'TBRL', 'C', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year3X, $year3Y);
                $pdf->MultiCell(19, $tableHeight, '', 'TBRL', 'C', 1, 0);
                $year3X = $year3X+19;

                $pdfBig->SetFont($Arial, 'B', 6, '', false);
                $pdfBig->SetXY($year3X, $year3Y);
                $pdfBig->MultiCell(24, $tableHeight, '', 'TBRL', 'C', 1, 0);

                $pdf->SetFont($Arial, 'B', 6, '', false);
                $pdf->SetXY($year3X, $year3Y);
                $pdf->MultiCell(24, $tableHeight, '', 'TBRL', 'C', 1, 0);
                $year3X = 21;
                $year3Y = $year3Y +$tableHeight;
            }
            // end year three table

            $bottomSecY = $year3Y-0.5;
            // echo $year3Y+3.6;
            $pdfBig->setCellPaddings( $left = '0', $top = '0.5', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, '', 6, '', false);
            $pdfBig->SetXY($xStart, $bottomSecY);
            $pdfBig->MultiCell(11, 0, 'AWARD : ', '', 'L', 0, 0);

            $pdf->setCellPaddings( $left = '0', $top = '0.5', $right = '', $bottom = '');
            $pdf->SetFont($Arial, '', 6, '', false);
            $pdf->SetXY($xStart, $bottomSecY);
            $pdf->MultiCell(11, 0, 'AWARD : ', '', 'L', 0, 0);


            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($xStart+10, $bottomSecY);
            $pdfBig->MultiCell(121, 0, $certificateAward, '', 'L', 0, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($xStart+10, $bottomSecY);
            $pdf->MultiCell(121, 0, $certificateAward, '', 'L', 0, 0);

            $pdfBig->SetFont($Arial, '', 6, '', false);
            $pdfBig->SetXY($xStart+120, $bottomSecY);
            $pdfBig->MultiCell(30, 0, 'DATE OF COMPLETION :', '', 'L', 0, 0);

            $pdf->SetFont($Arial, '', 6, '', false);
            $pdf->SetXY($xStart+120, $bottomSecY);
            $pdf->MultiCell(30, 0, 'DATE OF COMPLETION :', '', 'L', 0, 0);

            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($xStart+145, $bottomSecY);
            $pdfBig->MultiCell(30, 0, '15th October, 2022', '', 'L', 0, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($xStart+145, $bottomSecY);
            $pdf->MultiCell(30, 0, '15th October, 2022', '', 'L', 0, 0);


            $pdfBig->setCellPaddings( $left = '0', $top = '0.2', $right = '', $bottom = '');
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($xStart, $bottomSecY+6);
            $pdfBig->SetTextColor(255,255,255);
            $pdfBig->SetFillColor(19,57,106);
            $pdfBig->MultiCell($pageWidth, 3, 'The medium of instruction is English. For key to grafes and remarks,see overleaf.', '', 'C', 1, 0);

            $pdf->setCellPaddings( $left = '0', $top = '0.2', $right = '', $bottom = '');
            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($xStart, $bottomSecY+6);
            $pdf->SetTextColor(255,255,255);
            $pdf->SetFillColor(19,57,106);
            $pdf->MultiCell($pageWidth, 3, 'The medium of instruction is English. For key to grafes and remarks,see overleaf.', '', 'C', 1, 0);

            $bottomSecY =$bottomSecY+6;

            $y01 = $bottomSecY+13;
            $pdfBig->SetFont($Arial, '', 6, '', false);
            $pdfBig->SetXY($xStart, $y01);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->MultiCell(65, 0, '______________________________________________________', '', 'L', 0, 0);

            $pdf->SetFont($Arial, '', 6, '', false);
            $pdf->SetXY($xStart, $y01);
            $pdf->SetTextColor(0,0,0);
            $pdf->MultiCell(65, 0, '______________________________________________________', '', 'L', 0, 0);

            $y02 = $bottomSecY+16;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($xStart, $y02);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->MultiCell(65, 0, 'Dean, School of Medicine', '', 'C', 0, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($xStart, $y02);
            $pdf->SetTextColor(0,0,0);
            $pdf->MultiCell(65, 0, 'Dean, School of Medicine', '', 'C', 0, 0);

            $y03 = $bottomSecY+19;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY($xStart, $y03);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->MultiCell(65, 0, 'CAVENDISH UNIVERSITY ZAMBIA', '', 'C', 0, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY($xStart, $y03);
            $pdf->SetTextColor(0,0,0);
            $pdf->MultiCell(65, 0, 'CAVENDISH UNIVERSITY ZAMBIA', '', 'C', 0, 0);


            $y04 = $bottomSecY+14;
            $pdfBig->SetFont($Arial, '', 6, '', false);
            $pdfBig->SetXY(135, $y04);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->MultiCell(65, 0, '______________________________________________________', '', 'R', 0, 0);

            $pdf->SetFont($Arial, '', 6, '', false);
            $pdf->SetXY(135, $y04);
            $pdf->SetTextColor(0,0,0);
            $pdf->MultiCell(65, 0, '______________________________________________________', '', 'R', 0, 0);



            $bY1 = $bottomSecY+17;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY(135, $bY1);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->MultiCell(65, 0, 'Academic Registrar', '', 'C', 0, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY(135, $bY1);
            $pdf->SetTextColor(0,0,0);
            $pdf->MultiCell(65, 0, 'Academic Registrar', '', 'C', 0, 0);


            $bY2 = $bottomSecY+20;
            $pdfBig->SetFont($Arial, 'B', 6, '', false);
            $pdfBig->SetXY(135, $bY2);
            $pdfBig->SetTextColor(0,0,0);
            $pdfBig->MultiCell(65, 0, 'CAVENDISH UNIVERSITY ZAMBIA', '', 'C', 0, 0);

            $pdf->SetFont($Arial, 'B', 6, '', false);
            $pdf->SetXY(135, $bY2);
            $pdf->SetTextColor(0,0,0);
            $pdf->MultiCell(65, 0, 'CAVENDISH UNIVERSITY ZAMBIA', '', 'C', 0, 0);


            $bY3= $bottomSecY+22;
            $pdfBig->SetFont($Arial, '', 7, '', false);
            $pdfBig->SetXY($xStart, $bY3);
            $pdfBig->SetTextColor(161,150,148);
            $pdfBig->MultiCell($pageWidth, 0, 'To verify document check Control Code at:', '', 'C', 0, 0);

            $pdf->SetFont($Arial, '', 7, '', false);
            $pdf->SetXY($xStart, $bY3);
            $pdf->SetTextColor(161,150,148);
            $pdf->MultiCell($pageWidth, 0, 'To verify document check Control Code at:', '', 'C', 0, 0);

            $bY4=$bottomSecY+24;
            $pdfBig->SetFont($Arial, '', 7, '', false);
            $pdfBig->SetXY($xStart, $bY4);
            $pdfBig->SetTextColor(161,150,148);
            $pdfBig->MultiCell($pageWidth, 0, 'http://www.cavendishza.org/verify', '', 'C', 0, 0);

            $pdf->SetFont($Arial, '', 7, '', false);
            $pdf->SetXY($xStart, $bY4);
            $pdf->SetTextColor(161,150,148);
            $pdf->MultiCell($pageWidth, 0, 'http://www.cavendishza.org/verify', '', 'C', 0, 0);


            $bY5 = $bottomSecY+28;
            $pdfBig->SetFont($Arial, '', 7, '', false);
            $pdfBig->SetXY($xStart, $bY5);
            $pdfBig->SetTextColor(161,150,148);
            $pdfBig->MultiCell($pageWidth, 0, 'THIS TRANSCRIPT IS NOT VALID IT DOES NOT BEAR THE OFFICIAL SEAL OR IF IT HAS ANY ALTERNATIONS', '', 'C', 0, 0);

            $pdf->SetFont($Arial, '', 7, '', false);
            $pdf->SetXY($xStart, $bY5);
            $pdf->SetTextColor(161,150,148);
            $pdf->MultiCell($pageWidth, 0, 'THIS TRANSCRIPT IS NOT VALID IT DOES NOT BEAR THE OFFICIAL SEAL OR IF IT HAS ANY ALTERNATIONS', '', 'C', 0, 0);


            $dt = date("_ymdHis");

            $GUID=str_replace('-','',$certificateRegNo) ;
            $serial_no=str_replace('-','',$certificateRegNo);
            
            // $card_serial_no = str_replace('-','',$certificateRegNo);

            $GUID = $GUID.$dt;
            $serial_no = $serial_no; 
            // $serial_no=$GUID=$studentData[0];


            //qr code    
            // $str=$dt;
            // strtoupper(md5($serial_no.$dt))

            $str=$serial_no.$dt;
            // $codeContents = $QR_Output."\n\n".strtoupper(md5($str));
            $codeContents = strtoupper(md5($str));
            $encryptedString = strtoupper(md5($str));
            
            $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
            $qrCodex = 8;
            $qrCodey = 258;
            $qrCodeWidth =20;
            $qrCodeHeight = 20;
            \QrCode::size(75.5)
                ->backgroundColor(255, 255, 0)
                ->format('png')
                ->generate($codeContents, $qr_code_path);

            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

            $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);


            // print_r($certificateRegNo[0]);
            // die();

            if($previewPdf!=1){

                $certName = str_replace("/", "_", $GUID) .".pdf";
                $dt = date("_ymdHis");
                // $certName = $dt .".pdf";
                $myPath = public_path().'/backend/temp_pdf_file';

                $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;
                // print_r($certName);
                // die();
                $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');
                $this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id);

                $username = $admin_id['username'];
                date_default_timezone_set('Asia/Kolkata');

                $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
                $date = date('Y-m-d H:i:s').PHP_EOL;
                $print_datetime = date("Y-m-d H:i:s");
                

                $print_count = $this->getPrintCount($serial_no);
                $printer_name = /*'HP 1020';*/$printer_name;

                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'CAVENDISH-C',$admin_id,$card_serial_no);
                $card_serial_no=$card_serial_no+1;
            }
            $card_serial_no=$card_serial_no+1;
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
        // }

        //if($previewPdf!=1){
            //$this->updateCardNo('CAVENDISH-C',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
        //}
        if($previewPdf!=1){
            $this->updateCardNo('CAVENDISH-C',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
        }
        $msg = '';
        $file_name =  str_replace("/", "_",'CAVENDISH-C'.date("Ymdhms")).'.pdf';
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

            // $no_of_records = count($studentDataOrg);
            $no_of_records = 1;
            $user = $admin_id['username'];
            $template_name="CAVENDISH-C";
            // if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            //     // with sandbox
            //     $result = SbExceUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            // }else{
            //     // without sandbox
            //     $result = ExcelUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'pdf_file'=>$file_name,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
            // } 
            CoreHelper::sandboxingDB($systemConfig,$template_name,$excelfile,$file_name,$user,$no_of_records,$auth_site_id);

            $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
            $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
            $pdf_url=$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name;
            $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download visible data file.";
        }else{
            
            $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/preview/'.$file_name);
            @unlink($filename);

            // $no_of_records = count($studentDataOrg);
            $no_of_records = 1;
            $user = $admin_id['username'];
            $template_name="CAVENDISH-C";

            // add sandboxing code
            CoreHelper::sandboxingDB($systemConfig,$template_name,$excelfile,$file_name,$user,$no_of_records,$auth_site_id);

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
		
        /*copy($file1, $file2);        
        $aws_qr = \File::copy($file2,$pdfActualPath);            
        @unlink($file2);*/
		$source=\Config::get('constant.directoryPathBackward')."\\backend\\temp_pdf_file\\".$certName;
		$output=\Config::get('constant.directoryPathBackward').$subdomain[0]."\\backend\\pdf_file\\".$certName;
		CoreHelper::compressPdfFile($source,$output);
        @unlink($file1);

        // Rohit Changes 18/05/2023
        $outputFile = 'public/'.$subdomain[0]."\\backend\\pdf_file\\".$certName;
        //$movedfolder = 'public/'.$subdomain[0]."/backend/pdf_file";

        // awsS3Instances
        $awsS3Instances = \Config::get('constant.awsS3Instances');
        
        if(in_array($subdomain[0], $awsS3Instances)) {
            CoreHelper::awsUpload($output,$outputFile,$serial_no,$certName);
        }
        // rohit changes 18/05/2023


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

        $newSerialNo =  $serial_no.$dt;


        if($systemConfig['sandboxing'] == 1){
            $resultu = SbStudentTable::where('serial_no','T-'.$newSerialNo)->update(['status'=>'0']);
            // Insert the new record
        
            $result = SbStudentTable::create(['serial_no'=>'T-'.$newSerialNo,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }else{
            $resultu = StudentTable::where('serial_no',"".$newSerialNo)->update(['status'=>'0']);
            // Insert the new record
        
            $result = StudentTable::create(['serial_no'=>$newSerialNo,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>2]);
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
        if (file_exists($tmpname)) {
         unlink($tmpname);
        }
        mkdir($tmpname, 0777);
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
