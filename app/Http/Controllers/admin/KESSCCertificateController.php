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
use App\Jobs\ValidateExcelKESSCJob;
use App\Jobs\PdfGenerateKESSCJob;
use App\Jobs\PdfGenerateKESSCJob2;
use App\Jobs\PdfGenerateKESSCJob3;
class KESSCCertificateController extends Controller
{
    public function index(Request $request)
    {
       return view('admin.kessc.index');
    }

    public function uploadpage(){

      return view('admin.kessc.index');
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

    


        $pdf->AddPage();
        
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\KESSC_Shroff_grade_BG.jpg';
        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        $pdf->setPageMark();
        $fontEBPath=public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\E-13B_0.php';
        $pdf->AddFont('E-13B_0', '', $fontEBPath);
        
        $x= 175.5;
        $y = 39.5;
        $font_size=11;
        $str = '000001';
        $strArr = str_split($str);
                   $x_org=$x;
                   $y_org=$y;
                   $font_size_org=$font_size;
                   $i =0;
                   $j=0;
                   $y=$y+4.5;
                   $z=0;
                   foreach ($strArr as $character) {
                        $pdf->SetFont($arialNarrowB,0, $font_size, '', false);

                        $pdf->SetXY($x, $y+$z);
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
                       $pdf->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');
                        $i++;
                        $x=$x+2.2+$j; 
                        if($i>2){
                           
                         $font_size=$font_size+1.7;   
                        }
                   }

        $pdf->SetFont($arialNarrowB, '', 10, '', false);
        $pdf->SetXY(15, 46);
        $pdf->Cell(0, 0, "Convocation Id: 99999", 0, false, 'L');       

        $pdf->SetFont($arialNarrowB, '', 9, '', false);
        $html = <<<EOD
<style>
 td{
 
  font-size:9px; 
 border-top: 0px solid black;
    border-bottom: 0px solid black;
}
#table1 {
    
  border-collapse: collapse;
}
</style>
<table id="table1" cellspacing="0" cellpadding="2" border="0.1" width="86%" rules="rows">
   <tr>
    <td colspan="2"> Name of the Learner :  BAGARIA RAHUL NAYAN</td>
   </tr>
   <tr>
    <td style="width:12%;"> Programme :</td>
    <td style="width:88%;">BACHELOR OF COMMERCE IN ENVIRONMENT SCIENCE</td>
   </tr>
   <tr>
    <td style="width:50%;"> Semester : I</td>
    <td style="width:50%;"> Month and Year of Examination : DECEMBER - 2020</td>
   </tr>
   <tr>
    <td style="width:50%;"> Roll No.: 1</td>
    <td style="width:50%;"> PRN/Reg.No.: 1001</td>
   </tr>
   <tr>
    <td style="width:50%;"> Seat No.: 101</td>
    <td style="width:50%;"> UID No.: SC25001</td>
   </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='51.1', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

    //set profile image
    $profile_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\kes_prof.jpeg';
    $profilex = 174.5;
    $profiley = 51.3;
    $profileWidth = 23;
    $profileHeight = 26;
    $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);


$strtd="";

$strtd .= '<tr>
    <td style="width:11%;text-align:center;"><b>19UBEM001</b></td>
    <td style="width:29%;text-align:left;"><b>BUISNESS COMMUNICATION</b></td>
    <td style="width:6%;text-align:center;"><b>16</b></td>
    <td style="width:7%;text-align:center;"><b></b></td>
    <td style="width:6%;text-align:center;"><b>55</b></td>
    <td style="width:7%;text-align:center;"><b>55</b></td>
    <td style="width:5%;text-align:center;"><b>100</b></td>
    <td style="width:5%;text-align:center;"><b>A</b></td>
    <td style="width:6%;text-align:center;"><b>3</b></td>
    <td style="width:6%;text-align:center;"><b>8</b></td>
    <td style="width:6%;text-align:center;"><b>5</b></td>
    <td style="width:6%;text-align:center;"><b>8.50</b></td>
   </tr>';
$strtd .= '<tr>
    <td style="width:11%;text-align:center;"><b>19UBEM002</b></td>
    <td style="width:29%;text-align:left;"><b>ENVIRONMENTAL STUDIES AND GOODS AND SERVICE TAX - III</b></td>
    <td style="width:6%;text-align:center;"><b>16</b></td>
    <td style="width:7%;text-align:center;"><b></b></td>
    <td style="width:6%;text-align:center;"><b>55</b></td>
    <td style="width:7%;text-align:center;"><b>55</b></td>
    <td style="width:5%;text-align:center;"><b>100</b></td>
    <td style="width:5%;text-align:center;"><b>B</b></td>
    <td style="width:6%;text-align:center;"><b>3</b></td>
    <td style="width:6%;text-align:center;"><b>8</b></td>
    <td style="width:6%;text-align:center;"><b>5</b></td>
    <td style="width:6%;text-align:center;"><b>8.50</b></td>
   </tr>';
$strtd .= '<tr>
    <td style="width:11%;text-align:center;"><b>19UBEM003</b></td>
    <td style="width:29%;text-align:left;"><b>INTRO TO ENVIRONMENT MANAGEMENT</b></td>
    <td style="width:6%;text-align:center;"><b>16</b></td>
    <td style="width:7%;text-align:center;"><b></b></td>
    <td style="width:6%;text-align:center;"><b>55</b></td>
    <td style="width:7%;text-align:center;"><b>55</b></td>
    <td style="width:5%;text-align:center;"><b>100</b></td>
    <td style="width:5%;text-align:center;"><b>C</b></td>
    <td style="width:6%;text-align:center;"><b>3</b></td>
    <td style="width:6%;text-align:center;"><b>8</b></td>
    <td style="width:6%;text-align:center;"><b>5</b></td>
    <td style="width:6%;text-align:center;"><b>8.50</b></td>
   </tr>';
for ($i=4; $i <18 ; $i++) { 
  
    $strtd .= '<tr>
    <td style="width:11%;text-align:center;"><b>19UBEM00'.$i.'</b></td>
    <td style="width:29%;text-align:left;"><b>ORGANIZATIONAL BEHAVIOUR</b></td>
    <td style="width:6%;text-align:center;"><b>16</b></td>
    <td style="width:7%;text-align:center;"><b></b></td>
    <td style="width:6%;text-align:center;"><b>55</b></td>
    <td style="width:7%;text-align:center;"><b>55</b></td>
    <td style="width:5%;text-align:center;"><b>100</b></td>
    <td style="width:5%;text-align:center;"><b>A+</b></td>
    <td style="width:6%;text-align:center;"><b>3</b></td>
    <td style="width:6%;text-align:center;"><b>8</b></td>
    <td style="width:6%;text-align:center;"><b>5</b></td>
    <td style="width:6%;text-align:center;"><b>8.50</b></td>
   </tr>';

 }

 $strtd .= '<tr>
    <th style="width:11%;text-align:center;"></th>
    <th style="width:29%;text-align:center;">Total</th>
    <th style="width:6%;text-align:center;"><b>175</b></th>
    <th style="width:7%;text-align:center;"><b></b></th>
    <th style="width:6%;text-align:center;"><b>261</b></th>
    <th style="width:7%;text-align:center;"><b>261</b></th>
    <th style="width:5%;text-align:center;"><b>600</b></th>
    <th style="width:5%;text-align:center;"><b></b></th>
    <th style="width:6%;text-align:center;"><b>171</b></th>
    <th style="width:6%;text-align:center;"><b>8</b></th>
    <th style="width:6%;text-align:center;"><b>115</b></th>
    <th style="width:6%;text-align:center;"><b>8.50</b></th>
   </tr>';
     $html = <<<EOD
<style>
 th{

  font-size:9px; 
}
 td{

  font-size:9px;
   border-left: 0px solid black;
    border-right: 0px solid black; 
}
#table2 {
  
    height: 400px;
  
  border-collapse: collapse;
}
#table2 td:empty {
    height:400px;
  border-left: 0;
  border-right: 0;
}
.borderspace{
    border-spacing: -1.5px;
    border-collapse: separate;
    width:99%;
   display: block;
   padding-left:2px;

}



</style>
<table id="table2" class="t-table borderspace" cellspacing="0" cellpadding="2" border="0.1" width="99%">
  <tr>
    <th style="width:11%;text-align:center;">Course Code</th>
    <th style="width:29%;text-align:center;">Course Title</th>
    <th style="width:6%;text-align:center;">Internal <span style="font-size:8px;">Min: 16 Max: 40</span></th>
    <th style="width:7%;text-align:center;">Practical <span style="font-size:8px;">Min: 20 Max: 50</span></th>
    <th style="width:6%;text-align:center;">Theory <span style="font-size:8px;">Min: 24 Max: 60</span></th>
    <th style="width:7%;text-align:center;">Overall Marks</th>
    <th style="width:5%;text-align:center;">Max. Marks</th>
    <th style="width:5%;text-align:center;">Grade</th>
    <th style="width:6%;text-align:center;">Grade Points (G)</th>
    <th style="width:6%;text-align:center;">Course Credit (C)</th>
    <th style="width:6%;text-align:center;">(C X G)</th>
    <th style="width:6%;text-align:center;">SGPI = ΣCG / ΣC</th>
   </tr>
   
    {$strtd}
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='79', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);


 

     $html = <<<EOD
<style>

th{

  font-size:9px;
   border-top: 0px solid black;
    border-bottom: 0px solid black; 
}
 td{

  font-size:9px;
   
}
#table4 {
  
    height: 400px;
  
  border-collapse: collapse;
}


</style>
<table id="table4" cellspacing="0" cellpadding="2" border="0.1" width="99%">
    <tr>
    <th colspan="2" style="width:20%;text-align:left;">Remark : Successful</th>
    <th style="width:22%;text-align:left;">Grade : A</th>
    <th style="width:20%;text-align:left;">Credit Earned : 20</th>
    <th style="width:13%;text-align:left;">SGPI : 8.55</th>
    <th style="width:25%;text-align:left;"></th>
    </tr>
    <tr>
      <td style="width:16%;text-align:left;">Credit Earned</td>
    <td style="width:14%;text-align:left;">Sem I : <b>1.00</b></td>
    <td style="width:14%;text-align:left;">Sem II : <b>1.00</b></td>
    <td style="width:14%;text-align:left;">Sem III : <b>1.00</b></td>
    <td style="width:14%;text-align:left;">Sem IV : <b>1.00</b></td>
    <td style="width:14%;text-align:center;font-weight:normal;">Overall CGPI</td>
    <td style="width:14%;text-align:center;"><b>6.85</b></td>
    </tr>
    <tr>
      <td style="width:16%;text-align:left;">Grade Earned</td>
    <td style="width:14%;text-align:left;">Sem I : <b>A</b></td>
    <td style="width:14%;text-align:left;">Sem II : <b>A</b></td>
    <td style="width:14%;text-align:left;">Sem III : <b>A</b></td>
    <td style="width:14%;text-align:left;">Sem IV : <b>A</b></td>
    <td style="width:14%;text-align:center;font-weight:normal;">Overall Grade</td>
    <td style="width:14%;text-align:center;"><b>A</b></td>
    </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='199', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
     

     $html = <<<EOD
<style>
 

th{

  font-size:9px; 
}
 td{

  font-size:9px;
   /*border-left: 0px solid black;
    border-right: 0px solid black; */ 
}
#table5 {
  
    height: 400px;
  
  border-collapse: collapse;
}
#table2 td:empty {
    height:400px;
  border-left: 0;
  border-right: 0;
}
.borderspace{
    border-spacing: -1.5px;
    border-collapse: separate;
    width:99%;
   display: block;
   padding-left:2px;

}
  
  .no-border-right {
    /*border-top: 0px solid black;
    border-bottom: 0px solid black; */
  }
</style>
<table id="table5" cellspacing="0" cellpadding="2" border="0" width="99%">
    <tr>
    <th style="width:9%;text-align:center;"></th>
    <th colspan="2" style="width:54%;text-align:center;"></th>
    <th style="width:12%;text-align:center;"></th>
    <th colspan="2" style="width:25%;text-align:center;"></th>
    </tr>
    <tr>
    <td style="width:9%;text-align:center;"></td>
    <td colspan="2" style="width:54%;text-align:left;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:13%;text-align:center;"></td>
    </tr>
   <tr>
    <td style="width:9%;text-align:center;"></td>
    <td colspan="2" style="width:54%;text-align:left;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:13%;text-align:center;"></td>
    </tr> 
    <tr>
    <td style="width:9%;text-align:center;"></td>
    <td colspan="2" style="width:54%;text-align:left;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:13%;text-align:center;"></td>
    </tr>
    <tr>
    <td style="width:9%;text-align:center;"></td>
    <td colspan="2" style="width:54%;text-align:left;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:13%;text-align:center;"></td>
    </tr>
    <tr>
    <td style="width:9%;text-align:center;"></td>
    <td colspan="2" style="width:54%;text-align:left;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:13%;text-align:center;"></td>
    </tr>
    <tr>
    <th style="width:20%;text-align:left;" class="no-border-right">Place :</th>
    <th style="width:31%;text-align:center;" class="no-border-right">Date : 04/01/2020</th>
    <th style="width:12%;text-align:center;"></th>
    <th style="width:12%;text-align:center;"></th>
    <td style="width:12%;text-align:center;"></td>
    <td style="width:13%;text-align:center;"></td>
    </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='213', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

        
        // Ghost image
        $nameOrg="BAGARIA RAHUL NAYAN";
        $ghost_font_size = '13';
        $ghostImagex = 143;
        $ghostImagey = 275.5;
        $ghostImageWidth = 55;//68
        $ghostImageHeight = 9.8;
        $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
        $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

        //qr code    
        $dt = date("_ymdHis");
        $str="TEST0001";
        $codeContents =$encryptedString = strtoupper(md5($str));
        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 180;
        $qrCodey = 254;
        $qrCodeWidth =19.3;
        $qrCodeHeight = 19.3;
        \QrCode::size(75.6)
            ->backgroundColor(255, 255, 0)
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        

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
        $pdf->SetXY(180, 271.5);
        
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

         
      
        $pdf->Output('sample.pdf', 'I');
        //delete temp dir 26-04-2022 
        CoreHelper::rrmdir($tmpDir);  
    }

   
    


    
    

     public function validateExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
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
                $response = $this->dispatch(new ValidateExcelKESSCJob($excelData));

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
            
            //For custom Loader
            $jsonArr=array();
            $jsonArr['token'] = time();
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

        
        $pdfData=array('studentDataOrg'=>$rowData1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token);         
        //For Custom Loader

        if($dropdown_template_id==1){
            $link = $this->dispatch(new PdfGenerateKESSCJob($pdfData));
        }
        elseif($dropdown_template_id==2){
            $link = $this->dispatch(new PdfGenerateKESSCJob2($pdfData));
        }
		elseif($dropdown_template_id==3){
            $link = $this->dispatch(new PdfGenerateKESSCJob3($pdfData));
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
