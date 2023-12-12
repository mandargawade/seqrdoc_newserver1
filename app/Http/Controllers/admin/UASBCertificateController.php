<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use Session,TCPDF,TCPDF_FONTS,Auth,DB,Helper;
use App\models\StudentTable;
use App\models\SuperAdmin;
use App\Http\Requests\ExcelValidationRequest;
use App\models\BackgroundTemplateMaster;
use App\models\SystemConfig;
use App\Jobs\DegreeCertificatePdfJob;
use App\models\Degree;
use App\models\Config;
use App\models\PrintingDetail;
use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;
use App\Jobs\ValidateExcelUASBJob;
use App\Jobs\PdfGenerateUASBUGPGJob;
use App\Jobs\PdfGenerateUASBGOLDJob;
use App\Helpers\CoreHelper;
use Helper;
use Storage;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
class UASBCertificateController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.uasb.index');
    }

    //validation of excel
    public function validateExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $template_type = $request['id'];
        $template_id=100;
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

            $excelData=array('rowData1'=>$rowData1,'template_type'=>$template_type,'auth_site_id'=>$auth_site_id);
            $response = $this->dispatch(new ValidateExcelUASBJob($excelData));

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

//upload excel for genrate certificate
public function uploadfile(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
       
    $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

    $domain = \Request::getHost();
    $subdomain = explode('.', $domain);
    $template_type = $request['id'];
    $template_id = 100;
    $previewPdf = array($request['previewPdf'],$request['previewWithoutBg']);
    $auth_site_id=Auth::guard('admin')->user()->site_id;
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
//$tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
$admin_id = \Auth::guard('admin')->user()->toArray();


$pdfData=array('studentDataOrg'=>$rowData1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'template_type'=>$template_type);
if($template_type=="Gold"){
$link = $this->dispatch(new PdfGenerateUASBGOLDJob($pdfData));
}else{
$link = $this->dispatch(new PdfGenerateUASBUGPGJob($pdfData));
}


return response()->json(['success'=>true,'message'=>'Certificates generated successfully.','link'=>$link]);
}

//genrate pdf of certificate
public function pdfGenerate(){


    $domain = \Request::getHost();
    $subdomain = explode('.', $domain);
    $ghostImgArr = array();
    $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

    $pdf->SetCreator('TCPDF');
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
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);

        $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);

        $nudi01e = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e.TTF', 'TrueTypeUnicode', '', 96);
        $nudi01eb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e b.TTF', 'TrueTypeUnicode', '', 96);


        $pdf->AddPage();

        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\UASB 2020 CONV certificate_Background.jpg';
        
        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        
      //profile photo
        $profile_path = public_path().'\\'.$subdomain[0].'\backend\DC_Photo\nobody.jpg';
        $profilex = 181.5;
        $profiley = 8.3;
        $profileWidth = 19.20;
        $profileHeight = 24;
        $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);



        //set serial No
        $serial_no_split = (string)('54-0001');
        $strArr = str_split($serial_no_split);
        $x_org=$x=16;
        $y_org=$y=37.5;
        $font_size=10;
        $width=100;
        $font_size_org=$font_size;
        $i =0;
        $j=0;
        $y=$y+4.5;
        $z=0;
        $serial_font_size = 10;
        foreach ($strArr as $character) {

         $pdf->SetFont($arial,'', $font_size, '', false);

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

  $pdf->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
  $i++;
  if($i==1){
    $x=$x+2+$j;
}else{
    $x=$x+1.8+$j;
}

if($i>2){

   $font_size=$font_size+1.4;   
}
}

         //set enrollment no
$enrollment_font_size = '9';
$enrollmentx= 183.2;
$enrollmenty = 37.5;
$enrollmentstr = 'BLH 0123';
$pdf->SetFont($arial, '', $enrollment_font_size, '', false);
$pdf->SetXY($enrollmentx, $enrollmenty);
$pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');


        //Template 05
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2="DºÁgÀ «eÁÕ£À ªÀÄvÀÄÛ vÀAvÀæeÁÕ£À";
        $str3=" ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ".$str2." ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";

        if(strlen($str)>145){

            $str=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹ì£À ªÉÄÃgÉUÉ";
            $strArr = explode( "\n", wordwrap( $str, 145));
            $z=0;

            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, 57);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

            if(count($strArr)>2){
                $z=10;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, 67);
                $pdf->Cell(0, 0, $strArr[2], 0, false, 'C');
            }

        }else{
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 57;
            $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹ì£À ªÉÄÃgÉUÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');
        }
        

        

        $strFontB = '30';
        $strFont = '18';
        $strY = 64;
        $str1="C©üµÉÃPï PÉ UËqÀ ";
        $str2="CªÀgÀ£ÀÄß";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY($result[0], $strY);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        
        $strFont = '23';
        $strX = 55;
        $strY = 75;
        $str="PÀÈ¶ «eÁÕ£ÀzÀ°è ¨ÁåZÀÄ®gï";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

       

        $strFont = '18';
        $strX = 85.1;
        $strY = 85.3;
        $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 95;
        $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 105;
        $str = "ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ 2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 115;
        $str = "28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        //Template 05
        $strFont = '19';
        $strX = 55;
        $strY = 155.5;
        $str = "By authority of the Board of Management and upon recommendation";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');


        $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        
        $str1="of the Board of Studies, Under Graduate, ";
        $str2="Agriculture";
        $str = "of the Board of Studies, Under Graduate,  ".$str2;

        if(strlen($str)>65){

            $str=$str." "."and the Academic Council";
            $strArr = explode( "\n", wordwrap( $str, 65));

            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

        }else{
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '19';
            $strX = 55;
            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');
        }


        

        $strFont = '19';
        $strX = 55;
        $strY = 180.7;
        $str = "ABHISHEK K GOWDA";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 188.7;
        $str = "has been admitted to the degree of";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 196.7;
        $str = "B. Tech. (Food Science & Technology)";
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 204.7;
        $str = "and is entitled to all rights and honours therto appertaining";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 212.7;
        $str = "witness the seal of the University and the signatures of its";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 220.7;
        $str = "Officers this 28  day of November 2020";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '13';
        $strX = 55;
        $strY = 219;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(92.7, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        //qr code    
        $dt = date("_ymdHis");
        $str="TEST0001";
        $encryptedString = strtoupper(md5($str));
        $codeContents = "SLC 5001"."\n"."ABHISHEK K GOWDA"."\n"."Bachelor of Science (Sericulture)"."\n\n".$encryptedString;

        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 17;
        $qrCodey = 234.8;
        $qrCodeWidth =19.3;
        $qrCodeHeight = 19.3;

        \QrCode::size(75.6)
            
        ->format('png')
        ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        

        $str = 'ABHISHEK K GOWDA';
                            $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
                            $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $str);
                            $strWidth = ($textArray[2] - $textArray[0]);
                            $strHeight = $textArray[6] - $textArray[1] / 1.4;
                            
                            $width=18;
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
                            $array = substr($microlinestrRep,0,$microlinestrCharReq);

                            $wd = '';
                            $last_width = 0;
                            $pdf->SetFont($arialb, '', 1.2, '', false);
                            $pdf->SetTextColor(0, 0, 0);
                            $pdf->StartTransform();
                            
                            $pdf->SetXY(17.6, 252.5);
                            
                            $pdf->Cell(0, 0, $array, 0, false, 'L');


        $pdf->Output('sample.pdf', 'I');  
    }

    public function databaseGenerate(){
        ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
        $template_data =array("id"=>5,"template_name"=>"Degree Certificate");
        
       
           $fetch_degree_data = (array)DB::select( DB::raw('SELECT *,CONCAT_WS(" ", faculty_kan, degree_kan) as word FROM `gold` WHERE type ="UG" AND (sr_no="54-1014") order by length(word) DESC LIMIT 1'));
           $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();

            //Uncomment only for gold 
           $cert_cnt =count($fetch_degree_data);

           $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        
        $printer_name = $systemConfig['printer_name'];

        $ghostImgArr = array();
        //set fonts
        //set fonts

        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arial.ttf', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);

        $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewroman.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromanb.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromanbi.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromani.TTF', 'TrueTypeUnicode', '', 96);

        $nudi01e = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e.TTF', 'TrueTypeUnicode', '', 96);
        $nudi01eb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e b.TTF', 'TrueTypeUnicode', '', 96);
        //exit;
        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);



        $log_serial_no = 1;


        for($excel_row = 0; $excel_row < count($fetch_degree_data); $excel_row++)
        {   

            $serial_no = trim($fetch_degree_data[$excel_row]['sr_no']);

           

    $fetch_degree_data[$excel_row]["id_number"]="PALB 7118";
            //Checking profile pic
    $path=public_path().'/'.$subdomain[0].'/backend/DC_Photo/';
    $file =trim($fetch_degree_data[$excel_row]["id_number"]); 
    $file0 = str_replace("/", " ", $fetch_degree_data[$excel_row]["id_number"]);
    $file1 = str_replace(" ","",$fetch_degree_data[$excel_row]["id_number"]);
    $file2 = $fetch_degree_data[$excel_row]["id_number"];
    if(file_exists($path.$file.".jpg")){
        $profile_path =$path.$file.".jpg";
    }elseif(file_exists($path.$file.".jpeg")){
        $profile_path =$path.$file.".jpeg";
    }elseif(file_exists($path.$file0.".jpg")){
        $profile_path =$path.$file0.".jpg";
    }elseif(file_exists($path.$file0.".jpeg")){
        $profile_path =$path.$file0.".jpeg";
    }elseif(file_exists($path.$file1.".jpg")){
        $profile_path =$path.$file1.".jpg";
    }elseif(file_exists($path.$file1.".jpeg")){
        $profile_path =$path.$file1.".jpeg";
    }elseif(file_exists($path.$file2.".jpg")){
        $profile_path =$path.$file2.".jpg";
    }elseif(file_exists($path.$file2.".jpeg")){
        $profile_path =$path.$file2.".jpeg";
    }else{
        $profile_path ='';

    }

    if(!empty($profile_path)){

        $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdf->SetCreator('TCPDF');
        $pdf->SetAuthor('TCPDF');
        $pdf->SetTitle('Certificate');
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
            
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\UASB 2020 CONV certificate_Background.jpg';

            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

            

            $pdfBig->AddPage();

            $pdfBig->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

       ;

            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            
            

            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

            //profile photo

        $profilex = 181.5;
        $profiley = 8.3;
        $profileWidth = 19.20;
        $profileHeight = 24;

        $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
        $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

        //set serial No
        $serial_no_split = (string)($serial_no);
        $strArr = str_split($serial_no_split);
        $x_org=$x=16;
        $y_org=$y=37.5;
        $font_size=10;
        $width=100;
        $font_size_org=$font_size;
        $i =0;
        $j=0;
        $y=$y+4.5;
        $z=0;
        $serial_font_size = 10;
        foreach ($strArr as $character) {
            if($character=="-"){
                $x=$x+1;
            }

            $pdf->SetFont($arial,'', $font_size, '', false);
            $pdf->SetXY($x, $y+$z);
            
            $pdfBig->SetFont($arial,'', $font_size, '', false);
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

      $pdf->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
      $pdfBig->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
      if($character=="-"){
        $x=$x+0.5;
    }
    $i++;
    if($i==1){
        $x=$x+2+$j;
    }else{
        $x=$x+1.8+$j;
    }

    if($i>2){

       $font_size=$font_size+1.4;   
   }
}

         //set enrollment no
$enrollment_font_size = '9';
$enrollmentx= 183.2;
if(strpos($fetch_degree_data[$excel_row]['id_number'], '/') !== false){
    $enrollmentx=172;
}
$enrollmenty = 37.5;
$enrollmentstr = $fetch_degree_data[$excel_row]['id_number'];
$pdf->SetFont($arial, '', $enrollment_font_size, '', false);
$pdf->SetXY($enrollmentx, $enrollmenty);
$pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');

$pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
$pdfBig->SetXY($enrollmentx, $enrollmenty);
$pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');


if(!isset($fetch_degree_data[$excel_row]['ugpg_eng'])){
    $fetch_degree_data[$excel_row]['ugpg_eng']="GOLD";
}
      
            //UG
       if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Under Graduate"){


           
        $minus=0;
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Technology (Agricultural Engineering)"){
            $minus=1.5;
        }
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2=$fetch_degree_data[$excel_row]['faculty_kan'];
        $str3=" ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ".$str2." ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";
        $z=0;
        if(strlen($str)>145){

            $strNew=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";

            if(strlen($strNew)<=290){

                $strArr = explode( "\n", wordwrap( $strNew, 145));

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

                $strY=57-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');
            }else{

                $strNew=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ";
                $strArr = explode( "\n", wordwrap( $strNew, 145));

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

                $strY=57-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');


                $str="¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
                if($minus>0){
                    $minus=$minus+1.5;
                }
                
                $strY=67-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $str, 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $str, 0, false, 'C');

                $z=10;
            }


        }else{
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 57-$minus;
            $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');
        }




        $strFontB = '30';
        $strFont = '18';
        $strY = 64-$minus;
        $str1=$fetch_degree_data[$excel_row]['name_kan']." ";
        $str2="CªÀgÀ£ÀÄß";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY($result[0], $strY+$z);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY($result[0], $strY+$z);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3+$z);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY+3+$z);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
        
        
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Sericulture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agriculture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agricultural Biotechnology)"){
            $strFont = '23';
            $strX = 55;
            $strY = 75+1.2-$minus;
            $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY+$z);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY+$z);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }

        if($minus>0){
            $minus=$minus+1;
        }

        if($z==0){

            if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Sericulture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agriculture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agricultural Biotechnology)"){

                $strFont = '18';
                $strFontB = '23';
                $strX = 55;
                $strY = 75+1.2-$minus;
                $str1=trim($fetch_degree_data[$excel_row]['degree_kan'])." ";
                $str2="¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";

                $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );


                $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[0], $strY+$z);
                $pdf->Cell(0, 0, $str1, 0, false, 'L');

                $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[0], $strY+$z);
                $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[1], $strY+$z+1);
                $pdf->Cell(0, 0, $str2, 0, false, 'L');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[1], $strY+$z+1);
                $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

                $minusConcat=10;
            }else{
                $strFont = '18';
                $strX = 85.1;
                $strY = 85.3+1.2-$minus;
                $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $str, 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $str, 0, false, 'C');

                $minusConcat=0;
            }

            $strFont = '18';
            $strX = 85.1;
            $strY = 95.3+1.2-$minus-$minusConcat;
            $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 105.3+1.2-$minus-$minusConcat;
            $str = "ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ 2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 115.3+1.2-$minus-$minusConcat;
            $str = "28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }else{



            $strFont = '18';
            $strX = 85.1;
            $strY = 95.3+1.2-$minus;
            $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            if($minus>0){
             $minus=$minus+2; 
         }

         $strFont = '18';
         $strX = 85.1;
         $strY = 95.3+1.2-$minus+$z;
        
         $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');

         if($minus>0){
             $minus=$minus+1.5; 
         }

         $strFont = '18';
         $strX = 85.1;
         $strY = 105.3+1.2-$minus+$z;
         $str = "UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');

         if($minus>0){
             $minus=$minus+1.5; 
         }

         $strFont = '18';
         $strX = 85.1;
         $strY = 115.3+1.2-$minus+$z;
         $str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');
     }

     $strFont = '19';
     $strX = 55;
     $strY = 155.5;
     $str = "By authority of the Board of Management and upon recommendation";
     $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
     $pdf->SetTextColor(0,0,0,100,false,'');
     $pdf->SetXY(11, $strY);
     $pdf->Cell(0, 0, $str, 0, false, 'C');

     $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
     $pdfBig->SetTextColor(0,0,0,100,false,'');
     $pdfBig->SetXY(11, $strY);
     $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        

        $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        
        $str1="of the Board of Studies, Under Graduate, Faculty of ";
        $str2=$fetch_degree_data[$excel_row]['faculty_eng'];
        $str = "of the Board of Studies, Under Graduate, Faculty of ".$str2;

        if(strlen($str)>65){

            $str=$str." "."and the Academic Council";
            $strArr = explode( "\n", wordwrap( $str, 65));

            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');

        }else{
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '19';
            $strX = 55;
            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');
        }

        $strFont = '22';//19
        $strX = 55;
        $strY = 180.7;
        $str = $fetch_degree_data[$excel_row]['name_eng'];
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 188.7;
        $str = "has been admitted to the degree of";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 196.7;
        $str = $fetch_degree_data[$excel_row]['degree_eng'];
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 204.7;
        $str = "and is entitled to all rights and honours thereto appertaining";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 212.7;
        $str = "witness the seal of the University and the signatures of its";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 220.7;
        $str = "Officers this 28  day of November 2020";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '13';
        $strX = 55;
        $strY = 219;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(92.7, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(92.7, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');
    }else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Post Graduate"){

            //Template 04
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ PÀÈ¶ «eÁÕ£À ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 57;
        $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFontB = '30';
        $strFont = '18';
        $strY = 64;
        $str1=$fetch_degree_data[$excel_row]['name_kan']." ";
        $str2="CªÀgÀ£ÀÄß";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY($result[0], $strY);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY($result[0], $strY);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY+3);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');


        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['spec_eng'])!="IN SOIL AND WATER ENGINEERING"){

            $strYDegKan=75+1.2;
            $strYSpecKan=85+1.2;

            $strFont = '20';
            $strX = 85.1;
            $strY = 75+1.2;
            $str = trim($fetch_degree_data[$excel_row]['degree_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strYDegKan);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strYDegKan);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

      
        $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['spec_kan']." ";
        $str2="CAVÃPÀj¸À¯ÁVzÉ";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strYSpecKan);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strYSpecKan);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
    }elseif(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['spec_eng'])=="IN SOIL AND WATER ENGINEERING"){
       $strYSpecKan=85+1.2;
       $strYDegKan=75+1.2;
       $strFont = '20';
       $strX = 85.1;
       $strY = 75+1.2;
       $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
       $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
       $pdf->SetTextColor(0,0,0,100,false,'');
       $pdf->SetXY(10, $strYDegKan);
       $pdf->Cell(0, 0, $str, 0, false, 'C');

       $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
       $pdfBig->SetTextColor(0,0,0,100,false,'');
       $pdfBig->SetXY(10, $strYDegKan);
       $pdfBig->Cell(0, 0, $str, 0, false, 'C');


       $strFontB = '20';
       $strFont = '20';
       $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
       $str2="CAVÃPÀj¸À¯ÁVzÉ";
       
       $result = $this->GetStringPositions(
        array(
            array($str1, $nudi01eb, '', $strFontB), 
            array($str2, $nudi01e, '', $strFont)
        ),$pdf
    );


       $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
       $pdf->SetTextColor(0,0,0,100,false,'');
       $pdf->SetXY($result[0], $strYSpecKan);
       $pdf->Cell(0, 0, $str1, 0, false, 'L');

       $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
       $pdf->SetTextColor(0,0,0,100,false,'');
       $pdfBig->SetXY($result[0], $strYSpecKan);
       $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

       $pdf->SetFont($nudi01e, '', $strFont, '', false);
       $pdf->SetTextColor(0,0,0,100,false,'');
       $pdf->SetXY($result[1], $strYSpecKan);
       $pdf->Cell(0, 0, $str2, 0, false, 'L');

       $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
       $pdfBig->SetTextColor(0,0,0,100,false,'');
       $pdfBig->SetXY($result[1], $strYSpecKan);
       $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
   }else{
    $strYSpecKan=85+1.2;
    $strYDegKan=75+1.2;
    $strFont = '20';
    $strX = 85.1;
    $strY = 75+1.2;
    $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
    $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(10, $strYDegKan);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(10, $strYDegKan);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');


    $strFontB = '20';
    $strFont = '20';
    $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
    $str2="CAVÃPÀj¸À¯ÁVzÉ";

    $result = $this->GetStringPositions(
        array(
            array($str1, $nudi01eb, '', $strFontB), 
            array($str2, $nudi01e, '', $strFont)
        ),$pdf
    );


    $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY($result[0], $strYSpecKan);
    $pdf->Cell(0, 0, $str1, 0, false, 'L');

    $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY($result[0], $strYSpecKan);
    $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

    $pdf->SetFont($nudi01e, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY($result[1], $strYSpecKan);
    $pdf->Cell(0, 0, $str2, 0, false, 'L');

    $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY($result[1], $strYSpecKan);
    $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
}


$strFont = '18';
$strX = 85.1;
$strY = 95.3+1.2;
$str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
$pdf->SetFont($nudi01e, '', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(10, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($nudi01e, '', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(10, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '18';
$strX = 85.1;
$strY = 105.3+1.2;
$str = "UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ";
$pdf->SetFont($nudi01e, '', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(10, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($nudi01e, '', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(10, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '18';
$strX = 85.1;
$strY = 115.3+1.2;
$str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
$pdf->SetFont($nudi01e, '', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(10, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($nudi01e, '', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(10, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 155.5;
$str = "By authority of the Board of Management and upon recommendation";
$pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 163.5;
$str = "of the Board of Studies (Post Graduate) of the Faculty of Agriculture";
$pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 170.5;
$str = "and the Academic Council";
$pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 178;
$str = $fetch_degree_data[$excel_row]['name_eng'];
$pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
$pdf->SetTextColor(0,99,82,7,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
$pdfBig->SetTextColor(0,99,82,7,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 185.5;
$str = "has been admitted to the degree of";
$pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 193;
$str = $fetch_degree_data[$excel_row]['degree_eng'];
$pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 200.5;
$str = $fetch_degree_data[$excel_row]['spec_eng'];
$pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 208;
$str = "and is entitled to all rights and honours thereto appertaining";
$pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 215.5;
$str = "witness the seal of the University and the signatures of its";
$pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '19';
$strX = 55;
$strY = 223;
$str = "Officers this 28  day of November 2020";
$pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(11, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'C');

$pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(11, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'C');

$strFont = '13';
$strX = 55;
$strY = 222;
$str = "th";
$pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
$pdf->SetTextColor(0,0,0,100,false,'');
$pdf->SetXY(92.7, $strY);
$pdf->Cell(0, 0, $str, 0, false, 'L');

$pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
$pdfBig->SetTextColor(0,0,0,100,false,'');
$pdfBig->SetXY(92.7, $strY);
$pdfBig->Cell(0, 0, $str, 0, false, 'L');

}else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="GOLD"){

        //Template 02
    if(trim($fetch_degree_data[$excel_row]['type'])=="UG"){    
        $strY = 44;
        if(trim($fetch_degree_data[$excel_row]['subtype'])!="1"){    
            $strFont = '30';
            $strX = 85.1;
            
            $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±À¹Û";
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strY = 57;
        }

        $strFont = '30';
        $strX = 86;
           
        $str =trim($fetch_degree_data[$excel_row]['name_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,92,88,7,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        if(strlen(trim($fetch_degree_data[$excel_row]['spec_kan']))>90){
            $strFont = '16.5';
                $strY = $strY+14.3;//69.3
            }else{
             $strFont = '20'; 
               $strY = $strY+13.3;//68.3
           }

           $strX = 93;

           $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
           $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY(10, $strY);
           $pdf->Cell(0, 0, $str, 0, false, 'C');

           $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY(10, $strY);
           $pdfBig->Cell(0, 0, $str, 0, false, 'C');


           $strFont = '18';
           $strX = 55;
            $strY =  $strY+10;//104.5
            $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');



            $strFont = '18';
            $strX = 13;
            $strY = $strY+10;
            $str = trim($fetch_degree_data[$excel_row]['year_degree']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(16.8, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(16.8, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $str = "£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(37.5, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(37.5, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 13;
            $str = trim($fetch_degree_data[$excel_row]['ogpa']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(125, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(125, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $strY=$strY-1;
            $str = "CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(137.5, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(137.5, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $strY = $strY+10.5;
            $str = "±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAPÀ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $str = "28.11.2020";
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(110.5, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(110.5, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $str = "gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(139, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(139, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $strY = $strY+8.5;
            $str ="PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '20';
            $strX = 55;
            $strY = $strY+8.5;
            $str = "¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }else{



        //PG & PHD
            $strY = 44;
            $strFont = '30';
            $strX = 85.1;
            $strY = 45;
            $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±À¹Û"; 
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '30';
            $strX = 85.1;
            $strY = 57;
            $str =trim($fetch_degree_data[$excel_row]['name_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,92,88,7,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,92,88,7,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');


            $str = trim($fetch_degree_data[$excel_row]['faculty_kan'])." ".trim($fetch_degree_data[$excel_row]['degree_kan']);

            if(strlen($str)>80){

           
                $strArr = explode( "\n", wordwrap( $str, 120));
         
         
         $strFont = '20';
         $strX = 85.1;
         $strY = 69.5;
         $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');


         $strFont = '20';
         $strFont1 = '18';
         $strY=80;    
         $str1=$strArr[1];
         $str2=" ".trim($fetch_degree_data[$excel_row]['year_degree']);
         $str3="£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
         $str4 = trim($fetch_degree_data[$excel_row]['ogpa']);
         $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFont), 
                array($str2, $nudi01eb, '', $strFont), 
                array($str3, $nudi01e, '', $strFont), 
                array($str4, $nudi01eb, '', $strFont)

            ),$pdf
        );


         $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[0], $strY);
         $pdf->Cell(0, 0, $str1, 0, false, 'L');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[0], $strY);
         $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

         $pdf->SetFont($nudi01eb, 'B', $strFont1, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[1], $strY);
         $pdf->Cell(0, 0, $str2, 0, false, 'L');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont1, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[1], $strY);
         $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

         $pdf->SetFont($nudi01eb, '', $strFont1, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[2], $strY);
         $pdf->Cell(0, 0, $str3, 0, false, 'L');

         $pdfBig->SetFont($nudi01e, '', $strFont1, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[2], $strY);
         $pdfBig->Cell(0, 0, $str3, 0, false, 'L');

         $pdf->SetFont($nudi01eb, 'B', $strFont1, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[3]-5, $strY);
         $pdf->Cell(0, 0, $str4, 0, false, 'L');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont1, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[3]-5, $strY);
         $pdfBig->Cell(0, 0, $str4, 0, false, 'L');

         $strFont = '18';
         $strY=89;    
         $str1="CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ ±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAPÀ";
         $str2 = " 28.11.2020";
         $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01e, '', $strFont), 
                array($str2, $nudi01eb, '', $strFont), 

            ),$pdf
        );

         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[0], $strY);
         $pdf->Cell(0, 0, $str1, 0, false, 'L');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[0], $strY);
         $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

         $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[1], $strY);
         $pdf->Cell(0, 0, $str2, 0, false, 'L');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[1], $strY);
         $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

         $strFont = '18';
         $strX = 55;
         $strY = 98;
         $str ="gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');

     }else{

        $strFont = '20';
        $strX = 85.1;
        $strY = 69.5;
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 13;
        $strY = 78;
        $str = trim($fetch_degree_data[$excel_row]['year_degree']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(16.8, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(16.8, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 78;
        $str = "£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(37.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(37.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 13;
        $str = trim($fetch_degree_data[$excel_row]['ogpa']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(125, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(125, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $str = "CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(137.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(137.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 89;
        $str = "±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAPÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $str = "28.11.2020";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(110.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(110.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $str = "gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(139, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(139, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 98;
        $str ="PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    }

        $strFont = '18';
        $strX = 55;
        $strY = 108;
        $str=trim($fetch_degree_data[$excel_row]['spec_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 117;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');
    }

    $strFont = '20';
    $strX = 55;
    $strY = 155;
    $str = "AWARD OF GOLD MEDAL";
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '20';
    $strX = 55;
    $strY = 165;
    $str = trim($fetch_degree_data[$excel_row]['name_eng']);
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,92,88,7,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,92,88,7,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '20';
    $strX = 55;
    $strY = 174;
    $str = "is awarded";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $str=trim($fetch_degree_data[$excel_row]['spec_eng']);
      
    $strArr = explode( "\n", wordwrap( $str, 58));
    $strFont = '18';
    $strX = 55;
    $strY = 183;
      
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

    $z=0;
    if(count($strArr)>1){
        $strFont = '18';
        $strX = 55;
        $strY = 192.5;
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');
    }else{
        $z=9;
    }
    $strFont = '17.9';
    $strX = 55;
    $strY = 201.5;
    $str = "at the 54   Convocation of UAS, Bangalore held on";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(20, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(20, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '13';
    $strX = 55;
    $strY = 199.5;
    $str = "th";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(42.6, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(42.6, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '16.9';
    $strX = 55;
    $strY = 201.2;
    $str = "28.11.2020";
    $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(151, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(151, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '17.9';
    $strX = 55;
    $strY = 201.2;
    $str = "for";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(180, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(180, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '17.9';
    $strX = 55;
    $strY = 210.7;
    $str = "having secured the highest OGPA of";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(32, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(32, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '16.9';
    $strX = 55;
    $strY = 210.7;
    $str = trim($fetch_degree_data[$excel_row]['ogpa']);
    $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(126.5, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(126.5, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '17.9';
    $strX = 55;
    $strY = 210.7;
    $str = "out of 10.00 in";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(140, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(140, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    if($fetch_degree_data[$excel_row]['type']!="UG"){
        $strFont = '17.9';
        $str=trim($fetch_degree_data[$excel_row]['degree_eng'])." ".trim($fetch_degree_data[$excel_row]['faculty_eng']);

        if(strlen($str)>70){
           $strArr = explode( "\n", wordwrap( $str, 65));

           $strX = 55;
           $strY = 219;
           $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY(10, $strY-$z);
           $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

           $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY(10, $strY-$z);
           $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

           $strY = 227;
           $str1=$strArr[1];
           $str2=" during the year ";
           $str3=trim($fetch_degree_data[$excel_row]['year_degree']);
           $result = $this->GetStringPositions(
            array(
                array($str1, $timesNewRomanBI, '', $strFont), 
                array($str2, $timesNewRomanI, '', $strFont),
                array($str3, $timesNewRomanBI, '', $strFont)
            ),$pdf
        );


           $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY($result[0], $strY-$z);
           $pdf->Cell(0, 0, $str1, 0, false, 'L');

           $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY($result[0], $strY-$z);
           $pdfBig->Cell(0, 0, $str1, 0, false, 'L');


           $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY($result[1], $strY-$z);
           $pdf->Cell(0, 0, $str2, 0, false, 'L');

           $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY($result[1], $strY-$z);
           $pdfBig->Cell(0, 0, $str2, 0, false, 'L'); 

           $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY($result[2], $strY-$z);
           $pdf->Cell(0, 0, $str3, 0, false, 'L');

           $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY($result[2], $strY-$z);
           $pdfBig->Cell(0, 0, $str3, 0, false, 'L'); 

       }else{
        $strFont = '17.9';
        $strX = 55;
        $strY = 219;
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFontB = '17.9';
        $strFont = '17.9';
        $strY = 227.5;

        $str1="during the year ";
        $str2=trim($fetch_degree_data[$excel_row]['year_degree']);
        $result = $this->GetStringPositions(
            array( 
                array($str1, $timesNewRomanI, '', $strFont),
                array($str2, $timesNewRomanBI, '', $strFontB)
            ),$pdf
        );


        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strY-$z);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strY-$z);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');


        $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY-$z);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFontB, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY-$z);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
    }
}else{

   $strFont = '17.9';
   $strX = 55;
   $strY = 219;
   $str = trim($fetch_degree_data[$excel_row]['degree_eng']);
   $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
   $pdf->SetTextColor(0,0,0,100,false,'');
   $pdf->SetXY(10, $strY-$z);
   $pdf->Cell(0, 0, $str, 0, false, 'C');

   $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
   $pdfBig->SetTextColor(0,0,0,100,false,'');
   $pdfBig->SetXY(10, $strY-$z);
   $pdfBig->Cell(0, 0, $str, 0, false, 'C');

   $strFontB = '17.9';
   $strFont = '17.9';
   $strY = 227.5;

   $str1="during the year ";
   $str2=trim($fetch_degree_data[$excel_row]['year_degree']);
   $result = $this->GetStringPositions(
    array( 
        array($str1, $timesNewRomanI, '', $strFont),
        array($str2, $timesNewRomanBI, '', $strFontB)
    ),$pdf
);


   $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
   $pdf->SetTextColor(0,0,0,100,false,'');
   $pdf->SetXY($result[0], $strY-$z);
   $pdf->Cell(0, 0, $str1, 0, false, 'L');

   $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
   $pdfBig->SetTextColor(0,0,0,100,false,'');
   $pdfBig->SetXY($result[0], $strY-$z);
   $pdfBig->Cell(0, 0, $str1, 0, false, 'L');


   $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
   $pdf->SetTextColor(0,0,0,100,false,'');
   $pdf->SetXY($result[1], $strY-$z);
   $pdf->Cell(0, 0, $str2, 0, false, 'L');

   $pdfBig->SetFont($timesNewRomanBI, '', $strFontB, '', false);
   $pdfBig->SetTextColor(0,0,0,100,false,'');
   $pdfBig->SetXY($result[1], $strY-$z);
   $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
}


}


         //qr code    
$dt = date("_ymdHis");
$str=$serial_no;
$encryptedString = strtoupper(md5($str));
$codeContents = $fetch_degree_data[$excel_row]['id_number']."\n".$fetch_degree_data[$excel_row]['name_eng']."\n".$fetch_degree_data[$excel_row]['degree_eng']."\n\n".$encryptedString;

$qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
$qrCodex = 17;
$qrCodey = 234.8;
$qrCodeWidth =19.3;
$qrCodeHeight = 19.3;

\QrCode::size(75.6)
            
->format('png')
->generate($codeContents, $qr_code_path);

$pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
$pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

        //micro line name

$str = $fetch_degree_data[$excel_row]['name_eng'];
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $str);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        $width=18;
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
        $array = substr($microlinestrRep,0,$microlinestrCharReq);

        $wd = '';
        $last_width = 0;
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(17.6, 253.5);
        $pdf->Cell(0, 0, $array, 0, false, 'L');
        
        $pdfBig->SetFont($arialb, '', 1.2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->StartTransform();
        $pdfBig->SetXY(17.6, 253.5);
        $pdfBig->Cell(0, 0, $array, 0, false, 'L');
        $pdfBig->StopTransform();        



        $certName = str_replace("/", "_", $serial_no) .".pdf";
        
        $myPath = public_path().'/backend/temp_pdf_file';
        $dt = date("_ymdHis");
          
        $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');


        $file1 = public_path().'/backend/temp_pdf_file/'.$certName;
        $file2 = public_path().'/backend/pdf_file/'.$certName;
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;


        copy($file1, $file2);
        
        $aws_qr = \File::copy($file2,public_path().'/'.$subdomain[0].'/backend/pdf_file/'.$certName);
                

        @unlink($file2);

      
        }else{
             

        }

    }


    $msg = '';
        
    $file_name =  str_replace("/", "_",'gold '.$cert_cnt).'.pdf';

    $auth_site_id=Auth::guard('admin')->user()->site_id;
    $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


    $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
    $pdfBig->output($filename,'F');

    $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
    @unlink($filename);

 
    exit();

}

public function dbUploadUgpg(){

    $template_data =array("id"=>5,"template_name"=>"Degree Certificate");

    $tableName ="ug_pg";
       //Query FOR UGPG 
         //Comment this only for GOLD
    $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT degree_eng, COUNT(sr_no) as sr_no_count FROM ug_pg WHERE ugpg_eng="POST Graduate" AND spec_eng="IN SOIL AND WATER ENGINEERING" AND STATUS="0" GROUP BY degree_eng ORDER BY sr_no_count ASC LIMIT 1') );


        //Comment this only for GOLD
    foreach ($fetch_degree_data_prog_spec as $value) {
     $degree_eng =$value->degree_eng;
     $cert_cnt =$value->sr_no_count;
           

            //Query FOR UGPG 
     $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM ug_pg WHERE degree_eng ="'.$value->degree_eng.'" AND status="0" AND ugpg_eng="POST Graduate" AND spec_eng="IN SOIL AND WATER ENGINEERING"'));

             //Query FOR GOLD 
     $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();

            //Uncomment only for gold 
     $cert_cnt =count($fetch_degree_data);

     $admin_id = \Auth::guard('admin')->user()->toArray();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        
        $printer_name = $systemConfig['printer_name'];

        $ghostImgArr = array();
        //set fonts
        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);

        $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewroman.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromanb.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromanbi.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\timesnewromani.TTF', 'TrueTypeUnicode', '', 96);

        $nudi01e = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e.TTF', 'TrueTypeUnicode', '', 96);
        $nudi01eb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e b.TTF', 'TrueTypeUnicode', '', 96);

        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);

        $log_serial_no = 1;


        for($excel_row = 0; $excel_row < count($fetch_degree_data); $excel_row++)
        {  
           $serial_no = trim($fetch_degree_data[$excel_row]['sr_no']); 

            //Checking profile pic
    $path=public_path().'/'.$subdomain[0].'/backend/DC_Photo/';
    $file =trim($fetch_degree_data[$excel_row]["id_number"]); 
    $file0 = str_replace("/", " ", $fetch_degree_data[$excel_row]["id_number"]);
    $file1 = str_replace(" ","",$fetch_degree_data[$excel_row]["id_number"]);
    $file2 = $fetch_degree_data[$excel_row]["id_number"];
    if(file_exists($path.$file.".jpg")){
        $profile_path =$path.$file.".jpg";
    }elseif(file_exists($path.$file.".jpeg")){
        $profile_path =$path.$file.".jpeg";
    }elseif(file_exists($path.$file0.".jpg")){
        $profile_path =$path.$file0.".jpg";
    }elseif(file_exists($path.$file0.".jpeg")){
        $profile_path =$path.$file0.".jpeg";
    }elseif(file_exists($path.$file1.".jpg")){
        $profile_path =$path.$file1.".jpg";
    }elseif(file_exists($path.$file1.".jpeg")){
        $profile_path =$path.$file1.".jpeg";
    }elseif(file_exists($path.$file2.".jpg")){
        $profile_path =$path.$file2.".jpg";
    }elseif(file_exists($path.$file2.".jpeg")){
        $profile_path =$path.$file2.".jpeg";
    }else{
        $profile_path ='';

    }

    if(!empty($profile_path)){

        $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdf->SetCreator('TCPDF');
        $pdf->SetAuthor('TCPDF');
        $pdf->SetTitle('Certificate');
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
            
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\UASB 2020 CONV certificate_Background.jpg';
      
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

            

            $pdfBig->AddPage();

            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
        


            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

            //profile photo

        $profilex = 181.5;
        $profiley = 8.3;
        $profileWidth = 19.20;
        $profileHeight = 24;
        
        $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
        $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

        //set serial No
        $serial_no_split = (string)($serial_no);
        $strArr = str_split($serial_no_split);
        $x_org=$x=16;
        $y_org=$y=37.5;
        $font_size=10;
        $width=100;
        $font_size_org=$font_size;
        $i =0;
        $j=0;
        $y=$y+4.5;
        $z=0;
        $serial_font_size = 10;
        foreach ($strArr as $character) {
            if($character=="-"){
                $x=$x+1;
            }

            $pdf->SetFont($arial,'', $font_size, '', false);
            $pdf->SetXY($x, $y+$z);
            
            $pdfBig->SetFont($arial,'', $font_size, '', false);
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

      $pdf->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
      $pdfBig->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
      if($character=="-"){
        $x=$x+0.5;
    }
    $i++;
    if($i==1){
        $x=$x+2+$j;
    }else{
        $x=$x+1.8+$j;
    }

    if($i>2){

       $font_size=$font_size+1.4;   
   }
}

         //set enrollment no
$enrollment_font_size = '9';
$enrollmentx= 183.2;
if(strpos($fetch_degree_data[$excel_row]['id_number'], '/') !== false){
    $enrollmentx=172;
}
$enrollmenty = 37.5;
$enrollmentstr = $fetch_degree_data[$excel_row]['id_number'];
$pdf->SetFont($arial, '', $enrollment_font_size, '', false);
$pdf->SetXY($enrollmentx, $enrollmenty);
$pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');

$pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
$pdfBig->SetXY($enrollmentx, $enrollmenty);
$pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');


if(!isset($fetch_degree_data[$excel_row]['ugpg_eng'])){
    $fetch_degree_data[$excel_row]['ugpg_eng']="GOLD";
}
    
            //UG
       if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Under Graduate"){

        $minus=0;
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Technology (Agricultural Engineering)"){
            $minus=1.5;
        }
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2=$fetch_degree_data[$excel_row]['faculty_kan'];
        $str3=" ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ".$str2." ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";
        $z=0;
        if(strlen($str)>145){

            $strNew=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";

            if(strlen($strNew)<=290){

                $strArr = explode( "\n", wordwrap( $strNew, 145));

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

                $strY=57-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');
            }else{

                $strNew=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ";
                $strArr = explode( "\n", wordwrap( $strNew, 145));

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

                $strY=57-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');


                $str="¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
                if($minus>0){
                    $minus=$minus+1.5;
                }
                
                $strY=67-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $str, 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $str, 0, false, 'C');

                $z=10;
            }


        }else{
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 57-$minus;
            $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');
        }




        $strFontB = '30';
        $strFont = '18';
        $strY = 64-$minus;
        $str1=$fetch_degree_data[$excel_row]['name_kan']." ";
        $str2="CªÀgÀ£ÀÄß";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY($result[0], $strY+$z);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY($result[0], $strY+$z);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3+$z);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY+3+$z);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
        
        
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Sericulture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agriculture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agricultural Biotechnology)"){
            $strFont = '23';
            $strX = 55;
            $strY = 75+1.2-$minus;
            $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY+$z);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY+$z);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }

        if($minus>0){
            $minus=$minus+1;
        }

        if($z==0){

            if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Sericulture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agriculture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agricultural Biotechnology)"){

                $strFont = '18';
                $strFontB = '23';
                $strX = 55;
                $strY = 75+1.2-$minus;
                $str1=trim($fetch_degree_data[$excel_row]['degree_kan'])." ";
                $str2="¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";

                $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );


                $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[0], $strY+$z);
                $pdf->Cell(0, 0, $str1, 0, false, 'L');

                $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[0], $strY+$z);
                $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[1], $strY+$z+1);
                $pdf->Cell(0, 0, $str2, 0, false, 'L');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[1], $strY+$z+1);
                $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

                $minusConcat=10;
            }else{
                $strFont = '18';
                $strX = 85.1;
                $strY = 85.3+1.2-$minus;
                $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $str, 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $str, 0, false, 'C');

                $minusConcat=0;
            }

            $strFont = '18';
            $strX = 85.1;
            $strY = 95.3+1.2-$minus-$minusConcat;
            $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 105.3+1.2-$minus-$minusConcat;
            $str = "ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ 2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 115.3+1.2-$minus-$minusConcat;
            $str = "28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }else{



            $strFont = '18';
            $strX = 85.1;
            $strY = 95.3+1.2-$minus;
            $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            if($minus>0){
             $minus=$minus+2; 
         }

         $strFont = '18';
         $strX = 85.1;
         $strY = 95.3+1.2-$minus+$z;
        
         $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');

         if($minus>0){
             $minus=$minus+1.5; 
         }

         $strFont = '18';
         $strX = 85.1;
         $strY = 105.3+1.2-$minus+$z;
         $str = "UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');

         if($minus>0){
             $minus=$minus+1.5; 
         }

         $strFont = '18';
         $strX = 85.1;
         $strY = 115.3+1.2-$minus+$z;
         $str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');
     }

     $strFont = '19';
     $strX = 55;
     $strY = 155.5;
     $str = "By authority of the Board of Management and upon recommendation";
     $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
     $pdf->SetTextColor(0,0,0,100,false,'');
     $pdf->SetXY(11, $strY);
     $pdf->Cell(0, 0, $str, 0, false, 'C');

     $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
     $pdfBig->SetTextColor(0,0,0,100,false,'');
     $pdfBig->SetXY(11, $strY);
     $pdfBig->Cell(0, 0, $str, 0, false, 'C');


        $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        
        $str1="of the Board of Studies, Under Graduate, Faculty of ";
        $str2=$fetch_degree_data[$excel_row]['faculty_eng'];
        $str = "of the Board of Studies, Under Graduate, Faculty of ".$str2;

        if(strlen($str)>65){

            $str=$str." "."and the Academic Council";
            $strArr = explode( "\n", wordwrap( $str, 65));

            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');

        }else{
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '19';
            $strX = 55;
            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');
        }

        $strFont = '22';//19
        $strX = 55;
        $strY = 180.7;
        $str = $fetch_degree_data[$excel_row]['name_eng'];
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 188.7;
        $str = "has been admitted to the degree of";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 196.7;
        $str = $fetch_degree_data[$excel_row]['degree_eng'];
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 204.7;
        $str = "and is entitled to all rights and honours thereto appertaining";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 212.7;
        $str = "witness the seal of the University and the signatures of its";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 220.7;
        $str = "Officers this 28  day of November 2020";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '13';
        $strX = 55;
        $strY = 219;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(92.7, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(92.7, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');
    }else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Post Graduate"){

            //Template 04
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ PÀÈ¶ «eÁÕ£À ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 57;
        $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFontB = '30';
        $strFont = '18';
        $strY = 64;
        $str1=$fetch_degree_data[$excel_row]['name_kan']." ";
        $str2="CªÀgÀ£ÀÄß";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY($result[0], $strY);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY($result[0], $strY);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY+3);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['spec_eng'])!="IN SOIL AND WATER ENGINEERING"){


            $strYDegKan=75+1.2;
            $strYSpecKan=85+1.2;

            $strFont = '20';
            $strX = 85.1;
            $strY = 75+1.2;
            $str = trim($fetch_degree_data[$excel_row]['degree_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strYDegKan);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strYDegKan);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');


        $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['spec_kan']." ";
        $str2="CAVÃPÀj¸À¯ÁVzÉ";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strYSpecKan);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strYSpecKan);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
    }elseif(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['spec_eng'])=="IN SOIL AND WATER ENGINEERING"){
       $strYSpecKan=85+1.2;
       $strYDegKan=75+1.2;
       $strFont = '20';
       $strX = 85.1;
       $strY = 75+1.2;
       $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
       $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
       $pdf->SetTextColor(0,0,0,100,false,'');
       $pdf->SetXY(10, $strYDegKan);
       $pdf->Cell(0, 0, $str, 0, false, 'C');

       $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
       $pdfBig->SetTextColor(0,0,0,100,false,'');
       $pdfBig->SetXY(10, $strYDegKan);
       $pdfBig->Cell(0, 0, $str, 0, false, 'C');


       $strFontB = '20';
       $strFont = '20';
       $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
       $str2="CAVÃPÀj¸À¯ÁVzÉ";
       
       $result = $this->GetStringPositions(
        array(
            array($str1, $nudi01eb, '', $strFontB), 
            array($str2, $nudi01e, '', $strFont)
        ),$pdf
    );


       $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
       $pdf->SetTextColor(0,0,0,100,false,'');
       $pdf->SetXY($result[0], $strYSpecKan);
       $pdf->Cell(0, 0, $str1, 0, false, 'L');

       $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
       $pdf->SetTextColor(0,0,0,100,false,'');
       $pdfBig->SetXY($result[0], $strYSpecKan);
       $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

       $pdf->SetFont($nudi01e, '', $strFont, '', false);
       $pdf->SetTextColor(0,0,0,100,false,'');
       $pdf->SetXY($result[1], $strYSpecKan);
       $pdf->Cell(0, 0, $str2, 0, false, 'L');

       $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
       $pdfBig->SetTextColor(0,0,0,100,false,'');
       $pdfBig->SetXY($result[1], $strYSpecKan);
       $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
   }else{
    $strYSpecKan=85+1.2;
    $strYDegKan=75+1.2;
    $strFont = '20';
    $strX = 85.1;
    $strY = 75+1.2;
    $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
    $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(10, $strYDegKan);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(10, $strYDegKan);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');



        $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
        $str2="CAVÃPÀj¸À¯ÁVzÉ";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strYSpecKan);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strYSpecKan);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
    }


    $strFont = '18';
    $strX = 85.1;
    $strY = 95.3+1.2;
    $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
    $pdf->SetFont($nudi01e, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(10, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(10, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '18';
    $strX = 85.1;
    $strY = 105.3+1.2;
    $str = "UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ";
    $pdf->SetFont($nudi01e, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(10, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(10, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '18';
    $strX = 85.1;
    $strY = 115.3+1.2;
    $str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
    $pdf->SetFont($nudi01e, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(10, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(10, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 155.5;
    $str = "By authority of the Board of Management and upon recommendation";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 163.5;
    $str = "of the Board of Studies (Post Graduate) of the Faculty of Agriculture";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 170.5;
    $str = "and the Academic Council";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 178;
    $str = $fetch_degree_data[$excel_row]['name_eng'];
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,99,82,7,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,99,82,7,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 185.5;
    $str = "has been admitted to the degree of";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 193;
    $str = $fetch_degree_data[$excel_row]['degree_eng'];
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 200.5;
    $str = $fetch_degree_data[$excel_row]['spec_eng'];
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 208;
    $str = "and is entitled to all rights and honours thereto appertaining";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 215.5;
    $str = "witness the seal of the University and the signatures of its";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 223;
    $str = "Officers this 28  day of November 2020";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '13';
    $strX = 55;
    $strY = 222;
    $str = "th";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(92.7, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(92.7, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

}else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="GOLD"){



          //Template 02
    $strY = 44;
    if(trim($fetch_degree_data[$excel_row]['subtype'])!="1"){    
        $strFont = '30';
        $strX = 85.1;
        
        $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±À¹Û";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strY = 57;
    }

    $strFont = '30';
    $strX = 86;
       // $strY = 56.5;
    $str =trim($fetch_degree_data[$excel_row]['name_kan']);
    $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
    $pdf->SetTextColor(0,92,88,7,false,'');
    $pdf->SetXY(10, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
    $pdfBig->SetTextColor(0,92,88,7,false,'');
    $pdfBig->SetXY(10, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    if(strlen(trim($fetch_degree_data[$excel_row]['spec_kan']))>90){
        $strFont = '16.5';
            $strY = $strY+14.3;//69.3
        }else{
         $strFont = '20'; 
           $strY = $strY+13.3;//68.3
       }

       $strX = 93;

       $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
       $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
       $pdf->SetTextColor(0,0,0,100,false,'');
       $pdf->SetXY(10, $strY);
       $pdf->Cell(0, 0, $str, 0, false, 'C');

       $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
       $pdfBig->SetTextColor(0,0,0,100,false,'');
       $pdfBig->SetXY(10, $strY);
       $pdfBig->Cell(0, 0, $str, 0, false, 'C');


       $strFont = '18';
       $strX = 55;
        $strY =  $strY+10;//104.5
        $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');



        $strFont = '18';
        $strX = 13;
        $strY = $strY+10;
        $str = trim($fetch_degree_data[$excel_row]['year_degree']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(16.8, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(16.8, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
       
        $str = "£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(37.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(37.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 13;
        
        $str = trim($fetch_degree_data[$excel_row]['ogpa']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(125, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(125, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        
        $strY=$strY-1;
        $str = "CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(137.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(137.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = $strY+10.5;
        $str = "±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAPÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        
        $str = "28.11.2020";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(110.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(110.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        
        $str = "gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(139, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(139, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = $strY+10;//97
        $str ="PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        if(trim($fetch_degree_data[$excel_row]['type'])=="UG"){
            $str = "¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        }else{
            $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        }
        $strFont = '20';
        $strX = 55;
        $strY = $strY+9;
        
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');


        $strFont = '20';
        $strX = 55;
        $strY = 155;
        $str = "AWARD OF GOLD MEDAL";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 165;
        $str = trim($fetch_degree_data[$excel_row]['name_eng']);
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,92,88,7,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 174;
        $str = "is awarded";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $str=trim($fetch_degree_data[$excel_row]['spec_eng']);
       
        $strArr = explode( "\n", wordwrap( $str, 58));
        $strFont = '18';
        $strX = 55;
        $strY = 183;
      //  $str = "Best Sportsman-cum-Scholar Award in Memory of Late Balachanda Nanaiah Ganapathy, Ex-student of UAS, Bangalore";
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

        $z=0;
        if(count($strArr)>1){
            $strFont = '18';
            $strX = 55;
            $strY = 192.5;
       
            $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');
        }else{
            $z=9;
        }
        $strFont = '17.9';
        $strX = 55;
        $strY = 201.5;
        $str = "at the 54   Convocation of UAS, Bangalore held on";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(20, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(20, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '13';
        $strX = 55;
        $strY = 199.5;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(42.6, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(42.6, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '16.9';
        $strX = 55;
        $strY = 201.2;
        $str = "28.11.2020";
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(151, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(151, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 201.2;
        $str = "for";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(180, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(180, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 210.7;
        $str = "having secured the highest OGPA of";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(32, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(32, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '16.9';
        $strX = 55;
        $strY = 210.7;
        $str = trim($fetch_degree_data[$excel_row]['ogpa']);
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(126.5, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(126.5, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 210.7;
        $str = "out of 10.00 in";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(140, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(140, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '17.9';
        $strX = 55;
        $strY = 219;
        $str = trim($fetch_degree_data[$excel_row]['degree_eng']);
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFontB = '17.9';
        $strFont = '17.9';
        $strY = 227.5;

       
        $str1="during the year ";
        $str2=trim($fetch_degree_data[$excel_row]['year_degree']);
        $result = $this->GetStringPositions(
            array( 
                array($str1, $timesNewRomanI, '', $strFont),
                array($str2, $timesNewRomanBI, '', $strFontB)
            ),$pdf
        );


        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strY-$z);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strY-$z);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');


        $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY-$z);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFontB, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY-$z);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

    }
         //qr code    
    $dt = date("_ymdHis");
    $str=$serial_no;
    $encryptedString = strtoupper(md5($str));
    $codeContents = $fetch_degree_data[$excel_row]['id_number']."\n".$fetch_degree_data[$excel_row]['name_eng']."\n".$fetch_degree_data[$excel_row]['degree_eng']."\n\n".$encryptedString;

    $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
    $qrCodex = 17;
    $qrCodey = 234.8;
    $qrCodeWidth =19.3;
    $qrCodeHeight = 19.3;

    \QrCode::size(75.6)
            
    ->format('png')
    ->generate($codeContents, $qr_code_path);

    $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
    $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

        //micro line name
    
    $str = $fetch_degree_data[$excel_row]['name_eng'];
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $str);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        $width=18;
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
        $array = substr($microlinestrRep,0,$microlinestrCharReq);

        $wd = '';
        $last_width = 0;
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(17.6, 253.5);
        $pdf->Cell(0, 0, $array, 0, false, 'L');
        
        $pdfBig->SetFont($arialb, '', 1.2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->StartTransform();
        $pdfBig->SetXY(17.6, 253.5);
        $pdfBig->Cell(0, 0, $array, 0, false, 'L');
        $pdfBig->StopTransform();        



        $certName = str_replace("/", "_", $serial_no) .".pdf";
 
        $myPath = public_path().'/backend/temp_pdf_file';
        $dt = date("_ymdHis");
            
        $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

        $this->addCertificate($serial_no, $certName, $dt,$template_data['id'],$admin_id);

        $username = $admin_id['username'];
        date_default_timezone_set('Asia/Kolkata');

        $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
        $date = date('Y-m-d H:i:s').PHP_EOL;
        $print_datetime = date("Y-m-d H:i:s");


        $print_count = $this->getPrintCount($serial_no);
        $printer_name = /*'HP 1020';*/$printer_name;
        $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_data['template_name'],$admin_id);

        DB::update( DB::raw('UPDATE '.$tableName.' SET status="1" WHERE sr_no ="'.$serial_no.'"'));
    }else{
       DB::update( DB::raw('UPDATE '.$tableName.' SET status="2" WHERE sr_no ="'.$serial_no.'"'));

   }

}


$msg = '';
 
       //File name for UGPG
$file_name =  str_replace("/", "_",'ug_pg_'.$degree_eng.' '.$cert_cnt).'.pdf';


$auth_site_id=Auth::guard('admin')->user()->site_id;
$systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


$filename = public_path().'/backend/tcpdf/examples/'.$file_name;
      
$pdfBig->output($filename,'F');

$aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
@unlink($filename);

      
}
            

}

//gold certificate
public function dbUploadGold(){

    $template_data =array("id"=>5,"template_name"=>"Degree Certificate");

       //Query FOR UGPG 
           $tableName ="gold";
             //Query FOR GOLD 
           $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM gold WHERE  status="0" sr_no="54-1014" AND type = "UG" limit 1' ));
           $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();

            //Uncomment only for gold 
           $cert_cnt =count($fetch_degree_data);

           $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        
        $printer_name = $systemConfig['printer_name'];

        $ghostImgArr = array();
        //set fonts
        //set fonts
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);

        $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);

        $nudi01e = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e.TTF', 'TrueTypeUnicode', '', 96);
        $nudi01eb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Nudi 01 e b.TTF', 'TrueTypeUnicode', '', 96);

        $pdfBig = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdfBig->setPrintHeader(false);
        $pdfBig->setPrintFooter(false);
        $pdfBig->SetAutoPageBreak(false, 0);


        $log_serial_no = 1;


        for($excel_row = 0; $excel_row < count($fetch_degree_data); $excel_row++)
        {   


           $serial_no = trim($fetch_degree_data[$excel_row]['sr_no']);

            //Checking profile pic
    $path=public_path().'/'.$subdomain[0].'/backend/DC_Photo/';
    $file =trim($fetch_degree_data[$excel_row]["id_number"]); 
    $file0 = str_replace("/", " ", $fetch_degree_data[$excel_row]["id_number"]);
    $file1 = str_replace(" ","",$fetch_degree_data[$excel_row]["id_number"]);
    $file2 = $fetch_degree_data[$excel_row]["id_number"];
    if(file_exists($path.$file.".jpg")){
        $profile_path =$path.$file.".jpg";
    }elseif(file_exists($path.$file.".jpeg")){
        $profile_path =$path.$file.".jpeg";
    }elseif(file_exists($path.$file0.".jpg")){
        $profile_path =$path.$file0.".jpg";
    }elseif(file_exists($path.$file0.".jpeg")){
        $profile_path =$path.$file0.".jpeg";
    }elseif(file_exists($path.$file1.".jpg")){
        $profile_path =$path.$file1.".jpg";
    }elseif(file_exists($path.$file1.".jpeg")){
        $profile_path =$path.$file1.".jpeg";
    }elseif(file_exists($path.$file2.".jpg")){
        $profile_path =$path.$file2.".jpg";
    }elseif(file_exists($path.$file2.".jpeg")){
        $profile_path =$path.$file2.".jpeg";
    }else{
        $profile_path ='';

    }

    if(!empty($profile_path)){

        $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

        $pdf->SetCreator('TCPDF');
        $pdf->SetAuthor('TCPDF');
        $pdf->SetTitle('Certificate');
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
            
            //set background image
            $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\UASB 2020 CONV certificate_Background.jpg';
       
            $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);

           

            $pdfBig->AddPage();

            $print_serial_no = $this->nextPrintSerial();

            $pdfBig->SetCreator('TCPDF');

            $pdf->SetTextColor(0, 0, 0, 100, false, '');
            


            $pdfBig->SetTextColor(0, 0, 0, 100, false, '');

            //profile photo

        $profilex = 181.5;
        $profiley = 8.3;
        $profileWidth = 19.20;
        $profileHeight = 24;
        
        $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
        $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

        //set serial No
        $serial_no_split = (string)($serial_no);
        $strArr = str_split($serial_no_split);
        $x_org=$x=16;
        $y_org=$y=37.5;
        $font_size=10;
        $width=100;
        $font_size_org=$font_size;
        $i =0;
        $j=0;
        $y=$y+4.5;
        $z=0;
        $serial_font_size = 10;
        foreach ($strArr as $character) {
            if($character=="-"){
                $x=$x+1;
            }

            $pdf->SetFont($arial,'', $font_size, '', false);
            $pdf->SetXY($x, $y+$z);
            
            $pdfBig->SetFont($arial,'', $font_size, '', false);
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

      $pdf->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
      $pdfBig->Cell($width, 0, $character, 0, $ln=0,  $text_align='L', 0, '', 0, false, 'B', 'B');
      if($character=="-"){
        $x=$x+0.5;
    }
    $i++;
    if($i==1){
        $x=$x+2+$j;
    }else{
        $x=$x+1.8+$j;
    }

    if($i>2){

       $font_size=$font_size+1.4;   
   }
}

         //set enrollment no
$enrollment_font_size = '9';
$enrollmentx= 183.2;
if(strpos($fetch_degree_data[$excel_row]['id_number'], '/') !== false){
    $enrollmentx=172;
}
$enrollmenty = 37.5;
$enrollmentstr = $fetch_degree_data[$excel_row]['id_number'];
$pdf->SetFont($arial, '', $enrollment_font_size, '', false);
$pdf->SetXY($enrollmentx, $enrollmenty);
$pdf->Cell(0, 0, $enrollmentstr, 0, false, 'L');

$pdfBig->SetFont($arial, '', $enrollment_font_size, '', false);
$pdfBig->SetXY($enrollmentx, $enrollmenty);
$pdfBig->Cell(0, 0, $enrollmentstr, 0, false, 'L');


if(!isset($fetch_degree_data[$excel_row]['ugpg_eng'])){
    $fetch_degree_data[$excel_row]['ugpg_eng']="GOLD";
}
            //UG
       if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Under Graduate"){

        $minus=0;
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Technology (Agricultural Engineering)"){
            $minus=1.5;
        }
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        
        $str1="ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ";
        $str2=$fetch_degree_data[$excel_row]['faculty_kan'];
        $str3=" ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ ".$str2." ±ÁSÉAiÀÄ ¸ÁßvÀPÀ";
        $z=0;
        if(strlen($str)>145){

            $strNew=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";

            if(strlen($strNew)<=290){

                $strArr = explode( "\n", wordwrap( $strNew, 145));

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

                $strY=57-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');
            }else{

                $strNew=$str." "."CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ";
                $strArr = explode( "\n", wordwrap( $strNew, 145));

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

                $strY=57-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');


                $str="¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
                if($minus>0){
                    $minus=$minus+1.5;
                }
                
                $strY=67-$minus;
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $str, 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $str, 0, false, 'C');

                $z=10;
            }


        }else{
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 57-$minus;
            $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');
        }




        $strFontB = '30';
        $strFont = '18';
        $strY = 64-$minus;
        $str1=$fetch_degree_data[$excel_row]['name_kan']." ";
        $str2="CªÀgÀ£ÀÄß";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY($result[0], $strY+$z);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY($result[0], $strY+$z);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3+$z);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY+3+$z);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
        
        
        if(trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Sericulture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agriculture)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="Bachelor of Science (Agricultural Biotechnology)"){
            $strFont = '23';
            $strX = 55;
            $strY = 75+1.2-$minus;
            $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY+$z);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY+$z);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }

        if($minus>0){
            $minus=$minus+1;
        }

        if($z==0){

            if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Sericulture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agriculture)"||trim($fetch_degree_data[$excel_row]['degree_eng'])=="Bachelor of Science (Agricultural Biotechnology)"){

                $strFont = '18';
                $strFontB = '23';
                $strX = 55;
                $strY = 75+1.2-$minus;
                $str1=trim($fetch_degree_data[$excel_row]['degree_kan'])." ";
                $str2="¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";

                $result = $this->GetStringPositions(
                    array(
                        array($str1, $nudi01eb, '', $strFontB), 
                        array($str2, $nudi01e, '', $strFont)
                    ),$pdf
                );


                $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[0], $strY+$z);
                $pdf->Cell(0, 0, $str1, 0, false, 'L');

                $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[0], $strY+$z);
                $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY($result[1], $strY+$z+1);
                $pdf->Cell(0, 0, $str2, 0, false, 'L');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY($result[1], $strY+$z+1);
                $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

                $minusConcat=10;
            }else{
                $strFont = '18';
                $strX = 85.1;
                $strY = 85.3+1.2-$minus;
                $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
                $pdf->SetFont($nudi01e, '', $strFont, '', false);
                $pdf->SetTextColor(0,0,0,100,false,'');
                $pdf->SetXY(10, $strY);
                $pdf->Cell(0, 0, $str, 0, false, 'C');

                $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
                $pdfBig->SetTextColor(0,0,0,100,false,'');
                $pdfBig->SetXY(10, $strY);
                $pdfBig->Cell(0, 0, $str, 0, false, 'C');

                $minusConcat=0;
            }

            $strFont = '18';
            $strX = 85.1;
            $strY = 95.3+1.2-$minus-$minusConcat;
            $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 105.3+1.2-$minus-$minusConcat;
            $str = "ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ 2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '18';
            $strX = 85.1;
            $strY = 115.3+1.2-$minus-$minusConcat;
            $str = "28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }else{



            $strFont = '18';
            $strX = 85.1;
            $strY = 95.3+1.2-$minus;
            $str = "¥ÀzÀ«UÉ CAVÃPÀj¸À¯ÁVzÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            if($minus>0){
             $minus=$minus+2; 
         }

         $strFont = '18';
         $strX = 85.1;
         $strY = 95.3+1.2-$minus+$z;
    
         $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');

         if($minus>0){
             $minus=$minus+1.5; 
         }

         $strFont = '18';
         $strX = 85.1;
         $strY = 105.3+1.2-$minus+$z;
         $str = "UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');

         if($minus>0){
             $minus=$minus+1.5; 
         }

         $strFont = '18';
         $strX = 85.1;
         $strY = 115.3+1.2-$minus+$z;
         $str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');
     }

     $strFont = '19';
     $strX = 55;
     $strY = 155.5;
     $str = "By authority of the Board of Management and upon recommendation";
     $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
     $pdf->SetTextColor(0,0,0,100,false,'');
     $pdf->SetXY(11, $strY);
     $pdf->Cell(0, 0, $str, 0, false, 'C');

     $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
     $pdfBig->SetTextColor(0,0,0,100,false,'');
     $pdfBig->SetXY(11, $strY);
     $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 163.7;
        
        $str1="of the Board of Studies, Under Graduate, Faculty of ";
        $str2=$fetch_degree_data[$excel_row]['faculty_eng'];
        $str = "of the Board of Studies, Under Graduate, Faculty of ".$str2;

        if(strlen($str)>65){

            $str=$str." "."and the Academic Council";
            $strArr = explode( "\n", wordwrap( $str, 65));

            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

            $strY = 172.7;
            $str = "and the Academic Council";
            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');

        }else{
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '19';
            $strX = 55;
            $strY = 172.7;
            $str = "and the Academic Council";
            $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');
        }

        $strFont = '22';//19
        $strX = 55;
        $strY = 180.7;
        $str = $fetch_degree_data[$excel_row]['name_eng'];
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 188.7;
        $str = "has been admitted to the degree of";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 196.7;
        $str = $fetch_degree_data[$excel_row]['degree_eng'];
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 204.7;
        $str = "and is entitled to all rights and honours thereto appertaining";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 212.7;
        $str = "witness the seal of the University and the signatures of its";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '19';
        $strX = 55;
        $strY = 220.7;
        $str = "Officers this 28  day of November 2020";
        $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '13';
        $strX = 55;
        $strY = 219;
        $str = "th";
        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(92.7, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(92.7, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');
    }else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="Post Graduate"){

            //Template 04
        $strFont = '18';
        $strX = 85.1;
        $strY = 48;
        $str = "ªÀåªÀ¸ÁÜ¥À£Á ªÀÄAqÀ½AiÀÄ C¢üPÁgÀzÀ£ÀéAiÀÄ ªÀÄvÀÄÛ PÀÈ¶ «eÁÕ£À ±ÁSÉAiÀÄ ¸ÁßvÀPÉÆÃvÀÛgÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 85.1;
        $strY = 57;
        $str = "CzsÀåAiÀÄ£À ªÀÄAqÀ½ ºÁUÀÆ «zÁå«µÀAiÀÄPÀ ¥ÀjµÀwÛ£À ²¥sÁgÀ¹£À ªÉÄÃgÉUÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFontB = '30';
        $strFont = '18';
        $strY = 64;
        $str1=$fetch_degree_data[$excel_row]['name_kan']." ";
        $str2="CªÀgÀ£ÀÄß";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,99,82,7,false,'');
        $pdf->SetXY($result[0], $strY);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdfBig->SetTextColor(0,99,82,7,false,'');
        $pdfBig->SetXY($result[0], $strY);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY+3);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY+3);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

        if(trim($fetch_degree_data[$excel_row]['degree_eng'])=="Master of Technology (Agricultural Engineering)"&&trim($fetch_degree_data[$excel_row]['degree_eng'])!="IN SOIL AND WATER ENGINEERING"){


            $strYDegKan=75+1.2;
            $strYSpecKan=85+1.2;

            $strFont = '20';
            $strX = 85.1;
            $strY = 75+1.2;
            $str = trim($fetch_degree_data[$excel_row]['degree_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strYDegKan);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strYDegKan);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');


        $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['spec_kan']." ";
        $str2="CAVÃPÀj¸À¯ÁVzÉ";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strYSpecKan);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strYSpecKan);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
    }else{
        $strYSpecKan=85+1.2;
        $strYDegKan=75+1.2;
        $strFont = '20';
        $strX = 85.1;
        $strY = 75+1.2;
        $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strYDegKan);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strYDegKan);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');



        $strFontB = '20';
        $strFont = '20';
        $str1=$fetch_degree_data[$excel_row]['degree_kan']." ";
        $str2="CAVÃPÀj¸À¯ÁVzÉ";

        $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFontB), 
                array($str2, $nudi01e, '', $strFont)
            ),$pdf
        );


        $pdf->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strYSpecKan);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strYSpecKan);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strYSpecKan);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
    }


    $strFont = '18';
    $strX = 85.1;
    $strY = 95.3+1.2;
    $str = "ªÀÄvÀÄÛ CzÀPÉÌ ¸ÀA§A¢ü¹zÀ J¯Áè ºÀPÀÄÌUÀ¼ÀÄ ªÀÄvÀÄÛ";
    $pdf->SetFont($nudi01e, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(10, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(10, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '18';
    $strX = 85.1;
    $strY = 105.3+1.2;
    $str = "UËgÀªÁzÀgÀUÀ½UÉ «±Àé«zÁå¤®AiÀÄzÀ ªÉÆºÀgÀÄ ºÁUÀÆ CzÀgÀ C¢üPÁjUÀ¼À ¸ÁQë¸À»AiÉÆA¢UÉ";
    $pdf->SetFont($nudi01e, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(10, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(10, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '18';
    $strX = 85.1;
    $strY = 115.3+1.2;
    $str = "2020£ÉÃ E¸À« £ÀªÉA§gï wAUÀ¼À 28£ÉÃ ¢£ÁAPÀ¢AzÀ CºÀðgÁVgÀÄvÁÛgÉ";
    $pdf->SetFont($nudi01e, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(10, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(10, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 155.5;
    $str = "By authority of the Board of Management and upon recommendation";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 163.5;
    $str = "of the Board of Studies (Post Graduate) of the Faculty of Agriculture";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 170.5;
    $str = "and the Academic Council";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 178;
    $str = $fetch_degree_data[$excel_row]['name_eng'];
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,99,82,7,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,99,82,7,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 185.5;
    $str = "has been admitted to the degree of";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 193;
    $str = $fetch_degree_data[$excel_row]['degree_eng'];
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 200.5;
    $str = $fetch_degree_data[$excel_row]['spec_eng'];
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 208;
    $str = "and is entitled to all rights and honours thereto appertaining";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 215.5;
    $str = "witness the seal of the University and the signatures of its";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '19';
    $strX = 55;
    $strY = 223;
    $str = "Officers this 28  day of November 2020";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '13';
    $strX = 55;
    $strY = 222;
    $str = "th";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(92.7, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(92.7, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

}else if(trim($fetch_degree_data[$excel_row]['ugpg_eng'])=="GOLD"){

        //Template 02
    if(trim($fetch_degree_data[$excel_row]['type'])=="UG"){    
        $strY = 44;
        if(trim($fetch_degree_data[$excel_row]['subtype'])!="1"){    
            $strFont = '30';
            $strX = 85.1;
            
            $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±À¹Û";
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strY = 57;
        }

        $strFont = '30';
        $strX = 86;
           // $strY = 56.5;
        $str =trim($fetch_degree_data[$excel_row]['name_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,92,88,7,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,92,88,7,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        if(strlen(trim($fetch_degree_data[$excel_row]['spec_kan']))>90){
            $strFont = '16.5';
                $strY = $strY+14.3;//69.3
            }else{
             $strFont = '20'; 
               $strY = $strY+13.3;//68.3
           }

           $strX = 93;

           $str = trim($fetch_degree_data[$excel_row]['spec_kan']);
           $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY(10, $strY);
           $pdf->Cell(0, 0, $str, 0, false, 'C');

           $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY(10, $strY);
           $pdfBig->Cell(0, 0, $str, 0, false, 'C');


           $strFont = '18';
           $strX = 55;
            $strY =  $strY+10;//104.5
            $str=trim($fetch_degree_data[$excel_row]['degree_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');



            $strFont = '18';
            $strX = 13;
            $strY = $strY+10;
            $str = trim($fetch_degree_data[$excel_row]['year_degree']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(16.8, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(16.8, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $str = "£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(37.5, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(37.5, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 13;
            $str = trim($fetch_degree_data[$excel_row]['ogpa']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(125, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(125, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $strY=$strY-1;
            $str = "CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(137.5, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(137.5, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $strY = $strY+10.5;
            $str = "±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAPÀ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(11, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(11, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $str = "28.11.2020";
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(110.5, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(110.5, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $str = "gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(139, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'L');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(139, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'L');

            $strFont = '18';
            $strX = 55;
            $strY = $strY+8.5;
            $str ="PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '20';
            $strX = 55;
            $strY = $strY+8.5;
            $str = "¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
            $pdf->SetFont($nudi01e, '', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        }else{



        //PG & PHD
            $strY = 44;
            $strFont = '30';
            $strX = 85.1;
            $strY = 45;
            $str = "a£ÀßzÀ ¥ÀzÀPÀ ¥Àæ±À¹Û";
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,0,0,100,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,0,0,100,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');

            $strFont = '30';
            $strX = 85.1;
            $strY = 57;
            $str =trim($fetch_degree_data[$excel_row]['name_kan']);
            $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdf->SetTextColor(0,92,88,7,false,'');
            $pdf->SetXY(10, $strY);
            $pdf->Cell(0, 0, $str, 0, false, 'C');

            $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
            $pdfBig->SetTextColor(0,92,88,7,false,'');
            $pdfBig->SetXY(10, $strY);
            $pdfBig->Cell(0, 0, $str, 0, false, 'C');


            $str = trim($fetch_degree_data[$excel_row]['faculty_kan'])." ".trim($fetch_degree_data[$excel_row]['degree_kan']);

            if(strlen($str)>120){

           
                $strArr = explode( "\n", wordwrap( $str, 120));
         
         $strFont = '20';
         $strX = 85.1;
         $strY = 69.5;
         $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');


         $strFont = '20';
         $strFont1 = '18';
         $strY=80;    
         $str1=$strArr[1];
         $str2=" ".trim($fetch_degree_data[$excel_row]['year_degree']);
         $str3="£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
         $str4 = trim($fetch_degree_data[$excel_row]['ogpa']);
         $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01eb, '', $strFont), 
                array($str2, $nudi01eb, '', $strFont), 
                array($str3, $nudi01e, '', $strFont), 
                array($str4, $nudi01eb, '', $strFont)

            ),$pdf
        );


         $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[0], $strY);
         $pdf->Cell(0, 0, $str1, 0, false, 'L');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[0], $strY);
         $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

         $pdf->SetFont($nudi01eb, 'B', $strFont1, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[1], $strY);
         $pdf->Cell(0, 0, $str2, 0, false, 'L');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont1, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[1], $strY);
         $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

         $pdf->SetFont($nudi01eb, '', $strFont1, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[2], $strY);
         $pdf->Cell(0, 0, $str3, 0, false, 'L');

         $pdfBig->SetFont($nudi01e, '', $strFont1, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[2], $strY);
         $pdfBig->Cell(0, 0, $str3, 0, false, 'L');

         $pdf->SetFont($nudi01eb, 'B', $strFont1, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[3]-5, $strY);
         $pdf->Cell(0, 0, $str4, 0, false, 'L');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont1, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[3]-5, $strY);
         $pdfBig->Cell(0, 0, $str4, 0, false, 'L');

         $strFont = '18';
         $strY=89;    
         $str1="CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ ±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAPÀ";
         $str2 = " 28.11.2020";
         $result = $this->GetStringPositions(
            array(
                array($str1, $nudi01e, '', $strFont), 
                array($str2, $nudi01eb, '', $strFont), 

            ),$pdf
        );

         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[0], $strY);
         $pdf->Cell(0, 0, $str1, 0, false, 'L');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[0], $strY);
         $pdfBig->Cell(0, 0, $str1, 0, false, 'L');

         $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY($result[1], $strY);
         $pdf->Cell(0, 0, $str2, 0, false, 'L');

         $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY($result[1], $strY);
         $pdfBig->Cell(0, 0, $str2, 0, false, 'L');

         $strFont = '18';
         $strX = 55;
         $strY = 98;
         $str ="gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
         $pdf->SetFont($nudi01e, '', $strFont, '', false);
         $pdf->SetTextColor(0,0,0,100,false,'');
         $pdf->SetXY(10, $strY);
         $pdf->Cell(0, 0, $str, 0, false, 'C');

         $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
         $pdfBig->SetTextColor(0,0,0,100,false,'');
         $pdfBig->SetXY(10, $strY);
         $pdfBig->Cell(0, 0, $str, 0, false, 'C');

     }else{

        $strFont = '20';
        $strX = 85.1;
        $strY = 69.5;
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '18';
        $strX = 13;
        $strY = 78;
        $str = trim($fetch_degree_data[$excel_row]['year_degree']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(16.8, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(16.8, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 78;
        $str = "£ÉÃ ¸Á°£À°è 10.00 CAPÀUÀ½UÉ ¸ÀgÁ¸Àj";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(37.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(37.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 13;
        $str = trim($fetch_degree_data[$excel_row]['ogpa']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(125, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(125, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $str = "CAPÀUÀ¼À£ÀÄß UÀ½¹ ¥ÀæxÀªÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(137.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(137.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 89;
        $str = "±ÉæÃAiÀiÁAQvÀgÁVgÀÄªÀÅzÀjAzÀ EªÀjUÉ ¢£ÁAPÀ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $str = "28.11.2020";
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(110.5, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(110.5, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $str = "gÀAzÀÄ dgÀÄVzÀ ¨ÉAUÀ¼ÀÆgÀÄ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(139, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'L');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(139, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'L');

        $strFont = '18';
        $strX = 55;
        $strY = 98;
        $str ="PÀÈ¶ «±Àé«zÁå¤®AiÀÄzÀ 54£ÉÃ WÀnPÉÆÃvÀìªÀzÀ°è";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    }
        

        $strFont = '18';
        $strX = 55;
        $strY = 108;
        $str=trim($fetch_degree_data[$excel_row]['spec_kan']);
        $pdf->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01eb, 'B', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFont = '20';
        $strX = 55;
        $strY = 117;
        $str = "¥Àæ±À¹ÛAiÀÄ£ÀÄß ¥ÀæzÁ£À ªÀiÁqÀ¯ÁVzÉ";
        $pdf->SetFont($nudi01e, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($nudi01e, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');
    }

    $strFont = '20';
    $strX = 55;
    $strY = 155;
    $str = "AWARD OF GOLD MEDAL";
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '20';
    $strX = 55;
    $strY = 165;
    $str = trim($fetch_degree_data[$excel_row]['name_eng']);
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,92,88,7,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,92,88,7,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $strFont = '20';
    $strX = 55;
    $strY = 174;
    $str = "is awarded";
    $pdf->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $str, 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanI, 'I', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $str, 0, false, 'C');

    $str=trim($fetch_degree_data[$excel_row]['spec_eng']);
       
    $strArr = explode( "\n", wordwrap( $str, 58));
    $strFont = '18';
    $strX = 55;
    $strY = 183;
      //  $str = "Best Sportsman-cum-Scholar Award in Memory of Late Balachanda Nanaiah Ganapathy, Ex-student of UAS, Bangalore";
    $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(11, $strY);
    $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

    $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(11, $strY);
    $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

    $z=0;
    if(count($strArr)>1){
        $strFont = '18';
        $strX = 55;
        $strY = 192.5;
       
        $pdf->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(11, $strY);
        $pdf->Cell(0, 0, $strArr[1], 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanB, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(11, $strY);
        $pdfBig->Cell(0, 0, $strArr[1], 0, false, 'C');
    }else{
        $z=9;
    }
    $strFont = '17.9';
    $strX = 55;
    $strY = 201.5;
    $str = "at the 54   Convocation of UAS, Bangalore held on";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(20, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(20, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '13';
    $strX = 55;
    $strY = 199.5;
    $str = "th";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(42.6, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(42.6, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '16.9';
    $strX = 55;
    $strY = 201.2;
    $str = "28.11.2020";
    $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(151, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(151, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '17.9';
    $strX = 55;
    $strY = 201.2;
    $str = "for";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(180, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(180, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '17.9';
    $strX = 55;
    $strY = 210.7;
    $str = "having secured the highest OGPA of";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(32, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(32, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '16.9';
    $strX = 55;
    $strY = 210.7;
    $str = trim($fetch_degree_data[$excel_row]['ogpa']);
    $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(126.5, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(126.5, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    $strFont = '17.9';
    $strX = 55;
    $strY = 210.7;
    $str = "out of 10.00 in";
    $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdf->SetTextColor(0,0,0,100,false,'');
    $pdf->SetXY(140, $strY-$z);
    $pdf->Cell(0, 0, $str, 0, false, 'L');

    $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
    $pdfBig->SetTextColor(0,0,0,100,false,'');
    $pdfBig->SetXY(140, $strY-$z);
    $pdfBig->Cell(0, 0, $str, 0, false, 'L');

    if($fetch_degree_data[$excel_row]['type']!="UG"){
        $strFont = '17.9';
        $str=trim($fetch_degree_data[$excel_row]['degree_eng'])." ".trim($fetch_degree_data[$excel_row]['faculty_eng']);

        if(strlen($str)>70){
           $strArr = explode( "\n", wordwrap( $str, 70));

           $strX = 55;
           $strY = 219;
            
           $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY(10, $strY-$z);
           $pdf->Cell(0, 0, $strArr[0], 0, false, 'C');

           $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY(10, $strY-$z);
           $pdfBig->Cell(0, 0, $strArr[0], 0, false, 'C');

           $strY = 227;
           $str1=$strArr[1];
           $str2=" during the year ";
           $str3=trim($fetch_degree_data[$excel_row]['year_degree']);
           $result = $this->GetStringPositions(
            array(
                array($str1, $timesNewRomanBI, '', $strFont), 
                array($str2, $timesNewRomanI, '', $strFont),
                array($str3, $timesNewRomanBI, '', $strFont)
            ),$pdf
        );


           $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY($result[0], $strY-$z);
           $pdf->Cell(0, 0, $str1, 0, false, 'L');

           $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY($result[0], $strY-$z);
           $pdfBig->Cell(0, 0, $str1, 0, false, 'L');


           $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY($result[1], $strY-$z);
           $pdf->Cell(0, 0, $str2, 0, false, 'L');

           $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY($result[1], $strY-$z);
           $pdfBig->Cell(0, 0, $str2, 0, false, 'L'); 

           $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdf->SetTextColor(0,0,0,100,false,'');
           $pdf->SetXY($result[2], $strY-$z);
           $pdf->Cell(0, 0, $str3, 0, false, 'L');

           $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
           $pdfBig->SetTextColor(0,0,0,100,false,'');
           $pdfBig->SetXY($result[2], $strY-$z);
           $pdfBig->Cell(0, 0, $str3, 0, false, 'L'); 

       }else{
        $strFont = '17.9';
        $strX = 55;
        $strY = 219;
        $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY(10, $strY-$z);
        $pdf->Cell(0, 0, $str, 0, false, 'C');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY(10, $strY-$z);
        $pdfBig->Cell(0, 0, $str, 0, false, 'C');

        $strFontB = '17.9';
        $strFont = '17.9';
        $strY = 227.5;

        $str1="during the year ";
        $str2=trim($fetch_degree_data[$excel_row]['year_degree']);
        $result = $this->GetStringPositions(
            array( 
                array($str1, $timesNewRomanI, '', $strFont),
                array($str2, $timesNewRomanBI, '', $strFontB)
            ),$pdf
        );


        $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[0], $strY-$z);
        $pdf->Cell(0, 0, $str1, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[0], $strY-$z);
        $pdfBig->Cell(0, 0, $str1, 0, false, 'L');


        $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
        $pdf->SetTextColor(0,0,0,100,false,'');
        $pdf->SetXY($result[1], $strY-$z);
        $pdf->Cell(0, 0, $str2, 0, false, 'L');

        $pdfBig->SetFont($timesNewRomanBI, '', $strFontB, '', false);
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        $pdfBig->SetXY($result[1], $strY-$z);
        $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
    }
}else{

   $strFont = '17.9';
   $strX = 55;
   $strY = 219;
   $str = trim($fetch_degree_data[$excel_row]['degree_eng']);
   $pdf->SetFont($timesNewRomanBI, '', $strFont, '', false);
   $pdf->SetTextColor(0,0,0,100,false,'');
   $pdf->SetXY(10, $strY-$z);
   $pdf->Cell(0, 0, $str, 0, false, 'C');

   $pdfBig->SetFont($timesNewRomanBI, '', $strFont, '', false);
   $pdfBig->SetTextColor(0,0,0,100,false,'');
   $pdfBig->SetXY(10, $strY-$z);
   $pdfBig->Cell(0, 0, $str, 0, false, 'C');

   $strFontB = '17.9';
   $strFont = '17.9';
   $strY = 227.5;

   $str1="during the year ";
   $str2=trim($fetch_degree_data[$excel_row]['year_degree']);
   $result = $this->GetStringPositions(
    array( 
        array($str1, $timesNewRomanI, '', $strFont),
        array($str2, $timesNewRomanBI, '', $strFontB)
    ),$pdf
);


   $pdf->SetFont($timesNewRomanI, '', $strFont, '', false);
   $pdf->SetTextColor(0,0,0,100,false,'');
   $pdf->SetXY($result[0], $strY-$z);
   $pdf->Cell(0, 0, $str1, 0, false, 'L');

   $pdfBig->SetFont($timesNewRomanI, '', $strFont, '', false);
   $pdfBig->SetTextColor(0,0,0,100,false,'');
   $pdfBig->SetXY($result[0], $strY-$z);
   $pdfBig->Cell(0, 0, $str1, 0, false, 'L');


   $pdf->SetFont($timesNewRomanBI, '', $strFontB, '', false);
   $pdf->SetTextColor(0,0,0,100,false,'');
   $pdf->SetXY($result[1], $strY-$z);
   $pdf->Cell(0, 0, $str2, 0, false, 'L');

   $pdfBig->SetFont($timesNewRomanBI, '', $strFontB, '', false);
   $pdfBig->SetTextColor(0,0,0,100,false,'');
   $pdfBig->SetXY($result[1], $strY-$z);
   $pdfBig->Cell(0, 0, $str2, 0, false, 'L');
}


}

         //qr code    
$dt = date("_ymdHis");
$str=$serial_no;
$encryptedString = strtoupper(md5($str));
$codeContents = $fetch_degree_data[$excel_row]['id_number']."\n".$fetch_degree_data[$excel_row]['name_eng']."\n".$fetch_degree_data[$excel_row]['degree_eng']."\n\n".$encryptedString;

$qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
$qrCodex = 17;
$qrCodey = 234.8;
$qrCodeWidth =19.3;
$qrCodeHeight = 19.3;

\QrCode::size(75.6)
            
->format('png')
->generate($codeContents, $qr_code_path);

$pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
$pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);

        //micro line name

$str = $fetch_degree_data[$excel_row]['name_eng'];
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial Bold.TTF', $str);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        $width=18;
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
        $array = substr($microlinestrRep,0,$microlinestrCharReq);

        $wd = '';
        $last_width = 0;
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(17.6, 253.5);
        $pdf->Cell(0, 0, $array, 0, false, 'L');
        
        $pdfBig->SetFont($arialb, '', 1.2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->StartTransform();
        $pdfBig->SetXY(17.6, 253.5);
        $pdfBig->Cell(0, 0, $array, 0, false, 'L');
        $pdfBig->StopTransform();        



        $certName = str_replace("/", "_", $serial_no) .".pdf";
            
        $myPath = public_path().'/backend/temp_pdf_file';
        $dt = date("_ymdHis");
            
        $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');


        $this->addCertificate($serial_no, $certName, $dt,$template_data['id'],$admin_id);

        $username = $admin_id['username'];
        date_default_timezone_set('Asia/Kolkata');

        $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
        $date = date('Y-m-d H:i:s').PHP_EOL;
        $print_datetime = date("Y-m-d H:i:s");


        $print_count = $this->getPrintCount($serial_no);
        $printer_name = /*'HP 1020';*/$printer_name;
        $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_data['template_name'],$admin_id);

        DB::update( DB::raw('UPDATE '.$tableName.' SET status="1" WHERE sr_no ="'.$serial_no.'"'));
    }else{
       DB::update( DB::raw('UPDATE '.$tableName.' SET status="2" WHERE sr_no ="'.$serial_no.'"'));

   }

}


$msg = '';
        

        //File name for GOLD
$file_name =  str_replace("/", "_",'gold '.$cert_cnt).'.pdf';

$auth_site_id=Auth::guard('admin')->user()->site_id;
$systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


$filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
$pdfBig->output($filename,'F');

$aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
@unlink($filename);

     
exit();

}

public function dbUploadfileTest(){
    $template_data = TemplateMaster::select('id','template_name')->where('id',1)->first()->toArray();
        

        //Test by Bhavin
    $fetch_degree_data_prog_spec = DB::select( DB::raw('SELECT DISTINCT CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) AS prog_spec, COUNT(guid) FROM gu_dc_2 WHERE STATUS="0" GROUP BY prog_spec ORDER BY COUNT(guid) ASC') );


     
    foreach ($fetch_degree_data_prog_spec as $value) {
     $progSpec =$value->prog_spec;
        
     $fetch_degree_data = (array)DB::select( DB::raw('SELECT * FROM gu_dc_2 WHERE CONCAT_WS(" ",TRIM(Programme_Name_E),TRIM(Specialization_E)) ="'.$value->prog_spec.'" AND status="0"'));
     $fetch_degree_data = collect($fetch_degree_data)->map(function($x){ return (array) $x; })->toArray();
          
     $fetch_degree_array=array();
     $admin_id = \Auth::guard('admin')->user()->toArray();
  
     foreach ($fetch_degree_data as $key => $value) {
        $fetch_degree_array[$key] = array_values($fetch_degree_data[$key]);
    }
        
    $domain = \Request::getHost();
    $subdomain = explode('.', $domain);

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

    print_r($fetch_degree_array);

    for($excel_row = 0; $excel_row < count($fetch_degree_array); $excel_row++)
    {   

                $path=public_path().'/'.$subdomain[0].'/backend/DC_Photo/';
                $file1 = str_replace(" ","",$row["id_number"]);
                $file2 = $row["id_number"];
                if(file_exists($path.$file1.".jpg")){

                }else{
                    $profile_path ='';
                }

if(!empty($profile_path)){

    \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/CE_Sign_'.$excel_row.'.png');

    \File::copy(public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign.png',public_path().'/'.$subdomain[0].'/galgotia_cutomImages/VC_Sign_'.$excel_row.'.png');

            
    $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);

    $pdf->SetCreator('TCPDF');
    $pdf->SetAuthor('TCPDF');
    $pdf->SetTitle('Certificate');
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
                $template_img_generate = public_path().'/'.$subdomain[0].'/backend/canvas/bg_images/DIP_Background_lite.jpg';
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



                //qr code    
                //name // enrollment // degree // branch // cgpa // guid
                
        if($fetch_degree_array[$excel_row][10] == 'NU'){
           $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$fetch_degree_array[$excel_row][17]."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
       }else{
        if(is_float($fetch_degree_array[$excel_row][6])){
            $cgpaFormat=number_format(trim($fetch_degree_array[$excel_row][6]),2);
        }else{
            $cgpaFormat=trim($fetch_degree_array[$excel_row][6]);
        }
        if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'){
            $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".trim($fetch_degree_array[$excel_row][13])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
        }
        else if($fetch_degree_array[$excel_row][10] == 'DO'){
            $codeContents = trim($fetch_degree_array[$excel_row][4])."\n".trim($fetch_degree_array[$excel_row][1])."\n".trim($fetch_degree_array[$excel_row][11])."\n".$cgpaFormat."\n\n".md5(trim($fetch_degree_array[$excel_row][0]));
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

               
                $profilex = 181;
                $profiley = 19.8;
                $profileWidth = 22.2;
                
                $profileHeight = 26.6;
                $pdf->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                $pdfBig->image($profile_path,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);

                //invisible data
                $invisible_font_size = '10';

                $invisible_degreex = 7.3;
                $invisible_degreey = 48.3;
                $invisible_degreestr = trim($fetch_degree_array[$excel_row][11]);
                $pdfBig->SetOverprint(true, true, 0);
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

                $pdf->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdf->SetTextColor(0,92,88,7,false,'');
                $pdf->SetXY(10, $degree_namey);
                $pdf->Cell(190, 0, $degree_namestr, 0, false, 'C');


                $pdfBig->SetFont($timesNewRomanBI, '', $degree_name_font_size, '', false);
                $pdfBig->SetTextColor(0,92,88,7,false,'');
                $pdfBig->SetXY(10, $degree_namey);
                $pdfBig->Cell(190, 0, $degree_namestr, 0, false, 'C');
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
            $textArray = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlinestr);
            $strWidth = ($textArray[2] - $textArray[0]);
            $strHeight = $textArray[6] - $textArray[1] / 1.4;

            if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                $latestWidth = 557;
            }
            else if($fetch_degree_array[$excel_row][10] == 'DO'){
                $latestWidth = 564;
            }
            $wd = '';
            $last_width = 0;
            $message = array();
            for($i=1;$i<=1000;$i++){

                if($i * $strWidth > $latestWidth){
                    $wd = $i * $strWidth;
                    $last_width =$wd - $strWidth;
                    $extraWidth = $latestWidth - $last_width;
                    $stringLength = strlen($microlinestr);
                    $extraCharacter = intval($stringLength * $extraWidth / $strWidth);
                    $message[$i]  = mb_substr($microlinestr, 0,$extraCharacter);
                    break;
                }
                $message[$i] = $microlinestr.'';
            }

            $horizontal_line = array();
            foreach ($message as $key => $value) {
                $horizontal_line[] = $value;
            }


            $string = implode(',', $horizontal_line);
            $array = str_replace(',', '', $string);
            $pdf->SetFont($arialb, '', 1.2, '', false);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->StartTransform();
            if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'NU'){
                    
                $pdf->SetXY(36.8, 146.6);
            }
            else if($fetch_degree_array[$excel_row][10] == 'DO'){
                $pdf->SetXY(36.8, 145.5);
            }else if ($fetch_degree_array[$excel_row][10] == 'DIP') {
                $pdf->SetXY(36.8, 143.5);
            }
            $pdf->Cell(0, 0, $array, 0, false, 'L');

            $pdf->StopTransform();


            $pdfBig->SetFont($arialb, '', 1.2, '', false);
            $pdfBig->SetTextColor(0, 0, 0);
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


    $textArrayEnrollment = imagettfbbox(1.4, 0, public_path().'/'.$subdomain[0].'/backend/canvas/fonts/Arial Bold.TTF', $microlineEnrollment);
    $strWidthEnrollment = ($textArrayEnrollment[2] - $textArrayEnrollment[0]);
    $strHeightEnrollment = $textArrayEnrollment[6] - $textArrayEnrollment[1] / 1.4;

    $latestWidthEnrollment = 627;
    $wdEnrollment = '';
    $last_widthEnrollment = 0;
    $messageEnrollment = array();
    for($i=1;$i<=1000;$i++){

        if($i * $strWidthEnrollment > $latestWidthEnrollment){
            $wdEnrollment = $i * $strWidthEnrollment;
            $last_widthEnrollment =$wdEnrollment - $strWidthEnrollment;
            $extraWidth = $latestWidthEnrollment - $last_widthEnrollment;
            $stringLength = strlen($microlineEnrollment);
            $extraCharacter = intval($stringLength * $extraWidth / $strWidthEnrollment);
            $messageEnrollment[$i]  = mb_substr($microlineEnrollment, 0,$extraCharacter);
            break;
        }
        $messageEnrollment[$i] = $microlineEnrollment.'';
    }

    $horizontal_lineEnrollment = array();
    foreach ($messageEnrollment as $key => $value) {
        $horizontal_lineEnrollment[] = $value;
    }

    $stringEnrollment = implode(',', $horizontal_lineEnrollment);
    $arrayEnrollment = str_replace(',', '', $stringEnrollment);

    $pdf->SetFont($arialb, '', 1.2, '', false);
    $pdf->SetTextColor(0, 0, 0);
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


    $pdfBig->SetFont($arialb, '', 1.2, '', false);
    $pdfBig->SetTextColor(0, 0, 0);
    $pdfBig->StartTransform();
    if($fetch_degree_array[$excel_row][10] == 'DB' || $fetch_degree_array[$excel_row][10] == 'INT' || $fetch_degree_array[$excel_row][10] == 'DIP'|| $fetch_degree_array[$excel_row][10] == 'NU'){
                    // $pdf->SetXY(36.4, 219);
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
    $str = 'rhu o"khZ; fMIyksek ikBîØe ';
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
$pdfBig->SetFont($arial, '', $footer_name_font_size, '', false);
                
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

$pdf->SetTextColor(0,0,0,5,false,'');
$pdf->SetFont($arialb, '', $repeat_font_size, '', false);
$pdf->StartTransform();
$pdf->SetXY($repeatx, $name_repeaty);
$pdf->Cell(0, 0, $name_repeatstr, 0, false, 'L');
$pdf->StopTransform();


$pdfBig->SetTextColor(0,0,0,5,false,'');
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

                $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
                $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
                    

                $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

                $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

         
                $certName = str_replace("/", "_", $serial_no) .".pdf";
            
                $myPath = public_path().'/backend/temp_pdf_file';
                $dt = date("_ymdHis");
            
            
                $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');

                $this->addCertificate($serial_no, $certName, $dt,$template_data['id'],$admin_id);

                $username = $admin_id['username'];
                date_default_timezone_set('Asia/Kolkata');

                $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
                $date = date('Y-m-d H:i:s').PHP_EOL;
                $print_datetime = date("Y-m-d H:i:s");


                $print_count = $this->getPrintCount($serial_no);
                $printer_name = /*'HP 1020';*/$printer_name;
                $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,$template_data['template_name'],$admin_id);

                Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'1']);
                //delete temp dir 26-04-2022 
                CoreHelper::rrmdir($tmpDir);
            }else{
               Degree::where('GUID',$fetch_degree_array[$excel_row][0])->update(['status'=>'0']);;

           }

       }


       $msg = '';

       $file_name =  str_replace("/", "_",'2019 '.$fetch_degree_array[0][11].' '.$fetch_degree_array[0][13].' '.$fetch_degree_array[0][10]).'.pdf';

       $auth_site_id=Auth::guard('admin')->user()->site_id;
       $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


       $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
       $pdfBig->output($filename,'F');

       $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
       @unlink($filename);


   }
            

}

//store student details
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

//get printcount
public function getPrintCount($serial_no)
{
    $auth_site_id=Auth::guard('admin')->user()->site_id;
    $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
    $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();

    return $numCount + 1;
}

//store printing details
public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id)
{
        
    $sts = 1;
    $datetime = date("Y-m-d H:i:s");
    $ses_id = $admin_id["id"];

    $auth_site_id=Auth::guard('admin')->user()->site_id;

    $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

    $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
}

//next serial no for printing details
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

//get financial year
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

//create ghost image folder
public function createTemp($path){
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

// Create character image
public function CreateMessage($tmpDir, $name = "",$font_size,$print_color)
{
    if($name == "")
        return;
    $name = strtoupper($name);
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
