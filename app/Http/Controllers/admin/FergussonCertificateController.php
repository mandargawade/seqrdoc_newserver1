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
use App\Imports\TemplateMapImport;
use App\Imports\TemplateMasterImport;
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
use App\models\SbPrintingDetail;
use App\models\ExcelUploadHistory;
use App\Helpers\CoreHelper;
use Helper;
class FergussonCertificateController extends Controller
{
    public function index(Request $request)
    {
       return view('admin.ferguson.index');
    }

    public function uploadpage(){
    
      return view('admin.ferguson.index');
    }

     public function validateExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
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
         
                
                $objPHPExcel2 = $objReader->load($fullpath);
                $sheet2 = $objPHPExcel2->getSheet(1);
                $highestColumn2 = $sheet2->getHighestColumn();
                $highestRow2 = $sheet2->getHighestDataRow();
                $rowData2 = $sheet2->rangeToArray('A1:' . $highestColumn2 . $highestRow2, NULL, TRUE, FALSE);

               
                $rowData1[0] = array_filter($rowData1[0]);
                
                $columnsCount1=count($rowData1[0]);
                
                 $columnsCount2=count($rowData2[0]);
                if($columnsCount1==25&&$columnsCount2==12){

                    $columnArr=array('Seat_No','Exam_Prog','Student_Name','PRN','UID_No','Mother_Name','Programme','Year','Special Subject','Sem1_SGPA','Sem2_SGPA','Sem3_SGPA','Sem4_SGPA','Sem5_SGPA','Sem6_SGPA','CGPA','Grade','Result','Acc_Earned_Cred','ESC_Earned_Cred','Tot_Earned_Cred','Date','Total Marks','BlobFileName','QRCodeUrl');
                        $mismatchColArr=array_diff($rowData1[0], $columnArr);
                        
                        if(count($mismatchColArr)>0){
                            
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1 : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                        }


                    $columnArr=array('PRN','Sem_Number','Sub_Code','Sub_Name','INT','EXT','TOT','Sub_Cred','Earned_Cred','Grade_Point','Credit_Point','Grade');
                        $mismatchColArr=array_diff($rowData2[0], $columnArr);
                        
                        if(count($mismatchColArr)>0){
                            
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet2 : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                        }




                }elseif($columnsCount1!=25){
                    return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of sheet1 do not matched!']);
                }else{
                    return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of sheet2 do not matched!']);
                }

                $ab = array_count_values($rowData1[0]);

                $duplicate_columns = '';
                foreach ($ab as $key => $value) {
                    
                    if($value > 1){

                        if($duplicate_columns != ''){

                            $duplicate_columns .= ", ".$key;
                        }else{
                            
                            $duplicate_columns .= $key;
                        }
                    }
                }

                if($duplicate_columns != ''){
                    // Excel has more than 1 column having same name. i.e. <column name>
                    $message = Array('success'=>false,'type' => 'error', 'message' => 'Excel has more than 1 column having same name. i.e. : '.$duplicate_columns);
                    return json_encode($message);
                }
               
                $rowData2[0] = array_filter($rowData2[0]);
                
                $ab = array_count_values($rowData2[0]);

                $duplicate_columns = '';
                foreach ($ab as $key => $value) {
                    
                    if($value > 1){

                        if($duplicate_columns != ''){

                            $duplicate_columns .= ", ".$key;
                        }else{
                            
                            $duplicate_columns .= $key;
                        }
                    }
                }

                if($duplicate_columns != ''){
                    // Excel has more than 1 column having same name. i.e. <column name>
                    $message = Array('success'=>false,'type' => 'error', 'message' => 'Excel has more than 1 column having same name. i.e. : '.$duplicate_columns);
                    return json_encode($message);
                }

                unset($rowData1[0]);
                $rowData1=array_values($rowData1);
                $blobArr=array();
                

                $sandboxCheck = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                $old_rows = 0;
                $new_rows = 0;
                foreach ($rowData1 as $key1 => $value1) {
                    $blobFile = pathinfo($value1[23]);
                    $serial_no=$blobFile['filename'];
                    array_push($blobArr, $serial_no);
                    if($sandboxCheck['sandboxing'] == 1){
                       
                        $studentTableCounts = SbStudentTable::where('serial_no',$serial_no)->where('site_id',$auth_site_id)->count();
                        
                        $studentTablePrefixCounts = SbStudentTable::where('serial_no','T-'.$serial_no)->where('site_id',$auth_site_id)->count();
                        if($studentTableCounts > 0){
                            $old_rows += 1;
                        }else if($studentTablePrefixCounts){
                            
                            $old_rows += 1;
                        }else{
                            $new_rows += 1;
                        }

                    }else{
                        $studentTableCounts = StudentTable::where('serial_no',$serial_no)->where('site_id',$auth_site_id)->count();
                        if($studentTableCounts > 0){
                            $old_rows += 1;
                        }else{
                            $new_rows += 1;
                        }
                    }   
                }
               
                $mismatchArr = array_intersect($blobArr, array_unique(array_diff_key($blobArr, array_unique($blobArr))));
                      
                    if(count($mismatchArr)>0){
                    return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : BlobFileName contains following duplicate values : '.implode(',', $mismatchArr)]);
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
               
                
                $objPHPExcel2 = $objReader->load($fullpath);
                $sheet2 = $objPHPExcel2->getSheet(1);
                $highestColumn2 = $sheet2->getHighestColumn();
                $highestRow2 = $sheet2->getHighestDataRow();
                $rowData2 = $sheet2->rangeToArray('A1:' . $highestColumn2 . $highestRow2, NULL, TRUE, FALSE);
               

                 }
               
            
                       
        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }

     
        unset($rowData1[0]);
        unset($rowData2[0]);
        $rowData1=array_values($rowData1);
        $rowData2=array_values($rowData2);
        //store ghost image
       // $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();

        $no_of_records = count($rowData1);
        $datetime = date("Y-m-d H:i:s");
        $user = $admin_id['username'];
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $template_name="Fergusson-GC";
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            // with sandbox
            
            $result = SbExceUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
        }else{
            // without sandbox
            $result = ExcelUploadHistory::create(['template_name'=>$template_name,'excel_sheet_name'=>$excelfile,'user'=>$user,'no_of_records'=>$no_of_records,'site_id'=>$auth_site_id]);
        } 
        
        $link=$this->certificateGenerate($rowData1,$rowData2,$template_id,$previewPdf);
        return response()->json(['success'=>true,'message'=>'Certificates generated successfully.','link'=>$link]);
    }


    public function certificateGenerate($studentDataOrg,$subjectsDataOrg,$template_id,$previewPdf){

        $previewWithoutBg=$previewPdf[1];
        $previewPdf=$previewPdf[0];
        $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
        
        $printer_name = $systemConfig['printer_name'];

        //Separate students subjects
        $subjectsArr = array();
        foreach ($subjectsDataOrg as $element) {
            $subjectsArr[$element[0]][] = $element;
        }


        $ghostImgArr = array();
        $pdfBig = new TCPDF('L', 'mm', array('297', '420'), true, 'UTF-8', false);
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
        $arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $arialb = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrow = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arialn.TTF', 'TrueTypeUnicode', '', 96);

        $log_serial_no = 1;
       
        $cardDetails=$this->getNextCardNo('Fergusson-GC');
        $card_serial_no=$cardDetails->next_serial_no;
        foreach ($studentDataOrg as $studentData) {
         

         $pdfBig->AddPage();
        
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\FC BG Page inner.jpg';
        
        if($previewPdf==1){
            if($previewWithoutBg!=1){
                $pdfBig->Image($template_img_generate, 0, 0, '420', '297', "JPG", '', 'R', true);
            }
        

        $date_font_size = '11';
        $date_nox = 13;
        $date_noy = 36;
        $date_nostr = 'DRAFT '.date('d-m-Y H:i:s');
        $pdfBig->SetFont($arialNarrowB, '', $date_font_size, '', false);
        $pdfBig->SetTextColor(192,192,192);
        $pdfBig->SetXY($date_nox, $date_noy);
        $pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        }
        $pdfBig->setPageMark();


            $subjectsData=$subjectsArr[$studentData[3]];
            //Separate semesters 
            $subjects = array();
            foreach ($subjectsData as $element) {
                $subjects[$element[1]][] = $element;
            }
            ksort($subjects);
 

        $ghostImgArr = array();
        $pdf = new TCPDF('L', 'mm', array('297', '420'), true, 'UTF-8', false);
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
         $print_serial_no = $this->nextPrintSerial();
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\FC BG Page inner.jpg';
        if($previewPdf!=1){
        $pdf->Image($template_img_generate, 0, 0, '420', '297', "JPG", '', 'R', true);
        }
        $pdf->setPageMark();

        if($previewPdf!=1){
        $fontEBPath=public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\E-13B_0.php';
        $pdf->AddFont('E-13B_0', '', $fontEBPath);
        $pdfBig->AddFont('E-13B_0', '', $fontEBPath);
         //set enrollment no
        $card_serial_no_font_size = '13';
        $card_serial_nox= 172.5;
        $card_serial_noy = 34;
        $pdf->SetFont('E-13B_0', '', $card_serial_no_font_size, '', false);
        $pdf->SetXY($card_serial_nox, $card_serial_noy);
        $pdf->Cell(23.5, 0, $card_serial_no, 0, false, 'R');

        $pdfBig->SetFont('E-13B_0', '', $card_serial_no_font_size, '', false);
        $pdfBig->SetXY($card_serial_nox, $card_serial_noy);
        $pdfBig->Cell(23.5, 0, $card_serial_no, 0, false, 'R');
        }

        $pdf->SetFont($arialNarrow, '', 11, '', false);
        $pdfBig->SetFont($arialNarrow, '', 11, '', false);


        $page_no_font_size = '9';
        $page_no_x= 185;
        $page_no_y = 40.5;
        $page_no_str = 'PAGE 1';
        $pdf->SetFont($arialNarrow, '', $page_no_font_size, '', false);
        $pdf->SetXY($page_no_x, $page_no_y);
        $pdf->Cell(0, 0, $page_no_str, 0, false, '');

        $pdfBig->SetFont($arialNarrow, '', $page_no_font_size, '', false);
        $pdfBig->SetXY($page_no_x, $page_no_y);
        $pdfBig->Cell(0, 0, $page_no_str, 0, false, '');

        $page_no_font_size = '9';
        $page_no_x= 394;
        $page_no_y = 40.5;
        $page_no_str = 'PAGE 2';
        $pdf->SetFont($arialNarrow, '', $page_no_font_size, '', false);
        $pdf->SetXY($page_no_x, $page_no_y);
        $pdf->Cell(0, 0, $page_no_str, 0, false, '');

        $pdfBig->SetFont($arialNarrow, '', $page_no_font_size, '', false);
        $pdfBig->SetXY($page_no_x, $page_no_y);
        $pdfBig->Cell(0, 0, $page_no_str, 0, false, '');


        $semRomanArr=array(1=>"I",2=>"II",3=>"III",4=>"IV",5=>"V",6=>"VI");

 
        $fontSizeCource=8;


        $htmlStudentL = <<<EOD
<style>
 td{
 
  font-size:8px; 
 border-top: 0px solid black;
    border-bottom: 0px solid black;
}
#table1 {
    
  border-collapse: collapse;
}
</style>
<table id="table1" cellspacing="0" cellpadding="2" border="0.1" width="46.4%" rules="rows">
   <tr>
    <td colspan="2"> Student Name :  <b>{$studentData[2]} </b></td>
    <td> Mother's Name :  <b>{$studentData[5]} </b></td>
   </tr>
   <tr>
    <td style="width:14.50%;"> Academic Programme :</td>
    <td style="width:52.27%;"><b>{$studentData[6]}</b></td>
    <td style="width:33.33%;"> Year : <b>{$studentData[7]}</b></td>
   </tr>
   <tr>
    <td style="width:33.34%;"> Permanent Reg . No : <b>{$studentData[3]}</b></td>
    <td style="text-align:center;width:33.33%;"> Seat Number : <b>{$studentData[0]}</b></td>
    <td style="width:33.33%;"> UID. No : <b>{$studentData[4]}</b></td>
   </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='46', $htmlStudentL, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y='46', $htmlStudentL, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$linesLoopMaxL=42; //41
$linesLoopMaxR=77; //81

if(strlen($studentData[6])>67){
    $offset=3; 
    $lineOffset=1;
    $linesLoopMaxR=76; //80
}else{
    $offset=0;
    $lineOffset=0;
}

$htmlSpecSub = <<<EOD
<style>
 td{
 
  font-size:8px; 

}
#table111 {
    
  border-collapse: collapse;
}
</style>
<table id="table111" cellspacing="0" cellpadding="2" border="0" width="46.4%">
  <tr>
    <td>Special Subject : <b>{$studentData[8]}</b></td>
    </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y=61.7+$offset, $htmlSpecSub, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y=61.7+$offset, $htmlSpecSub, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

        $html = <<<EOD
<style>
 td{
 
  font-size:8px; 
 border-top: 0px solid black;
    border-bottom: 0px solid black;
}
#table11 {
    
  border-collapse: collapse;
}
#block_container
{
    text-align:center;
}
#bloc1, #bloc2
{
    display:inline;
}
</style>
<table id="table11" cellspacing="0" cellpadding="2" border="0.1" width="98.5%" rules="rows">
 <tr>
    <td colspan="2"> Student Name :  <b>{$studentData[2]} </b></td>
    <td> Mother's Name :  <b>{$studentData[5]} </b></td>
   </tr>
   <tr>
    <td style="width:14.50%;"> Academic Programme :</td>
    <td style="width:52.27%;"><b>{$studentData[6]}</b></td>
    <td style="width:33.33%;"> Year : <b>{$studentData[7]}</b></td>
   </tr>
   <tr>
    <td style="width:33.34%;"> Permanent Reg . No : <b>{$studentData[3]}</b></td>
    <td style="text-align:center;width:33.33%;"> Seat Number : <b>{$studentData[0]}</b></td>
    <td style="width:33.33%;"> UID. No : <b>{$studentData[4]}</b></td>
   </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='222', $y='46', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='222', $y='46', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);



if($lineOffset==1){
$lineCount=2;
}else{
$lineCount=0;
}
$strtdL="";
$strtdR="";

$specialSubArr=array();
foreach ($subjects as $key => $semesterData) {

    if($key==5){

        $lineCountPrev=$lineCount;
        
       
            for ($p=$lineCountPrev; $p < 42; $p++) { 
                 $strtdL .= '<tr>
                            <td style="width:11%;text-align:center;"></td>
                            <td style="width:39%;text-align:left;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            </tr>';
            }
        

        $lineCount=42; //47

    }


    if($lineCount<($linesLoopMaxL)){    
      $strtdL .= '<tr>
    <td style="width:11%;text-align:right;"><b>SEM '.$semRomanArr[$key].'</b></td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>';
    }else{
       $strtdR .= '<tr>
    <td style="width:11%;text-align:right;"><b>SEM '.$semRomanArr[$key].'</b></td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>'; 
    }
    $lineCount=$lineCount+1;

    foreach ($semesterData as $readSubject) {


        if($readSubject[4]==="-"&&$readSubject[5]==="-"&&$readSubject[6]==="-"&&$readSubject[10]==="-"){
            array_push($specialSubArr, $readSubject);
            
        }else{
        if(strlen($readSubject[3])>47){
            $lineCount=$lineCount+1.64;
        }else{
            $lineCount=$lineCount+1;
        }

        if($lineCount<($linesLoopMaxL)){
         $strtdL .= '<tr>
                    <td style="width:11%;text-align:right;"><b>'.$readSubject[2].'</b></td>
                    <td style="width:39%;text-align:left;"><b>'.$readSubject[3].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[4].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[5].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[6].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[7].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[8].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[9].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[10].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[11].'</b></td>
                   </tr>';
        }else{

         $strtdR .= '<tr>
                    <td style="width:11%;text-align:right;"><b>'.$readSubject[2].'</b></td>
                    <td style="width:39%;text-align:left;"><b>'.$readSubject[3].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[4].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[5].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[6].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[7].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[8].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[9].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[10].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[11].'</b></td>
                   </tr>';
        }

    }

    }
}


 
 
for ($z=$lineCount; $z < 90; $z++) { 
 
 if($lineCount<($linesLoopMaxL)){
     $strtdL .= '<tr>
    <td style="width:11%;text-align:center;"></td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>';
    
 }elseif($lineCount>($linesLoopMaxL-1)&&$lineCount<$linesLoopMaxR){
     $strtdR .= '<tr>
    <td style="width:11%;text-align:center;"></td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>';
 }
$lineCount=$lineCount+1;
}

     $html = <<<EOD
<style>
 th{

  font-size:7px; 
}
 td{

  font-size:{$fontSizeCource}px;
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
<table id="table2" class="t-table borderspace" cellspacing="0" cellpadding="2" border="0.1" width="46.4%">
  <tr>
    <th style="width:11%;text-align:center;">Subject Code</th>
    <th style="width:39%;text-align:center;">Subject Name</th>
    <th style="width:5%;text-align:center;">CE</th>
    <th style="width:5%;text-align:center;">ESE</th>
    <th style="width:7%;text-align:center;">TOT</th>
    <th style="width:7%;text-align:center;">Subject Credits</th>
    <th style="width:7%;text-align:center;">Earn Credits</th>
    <th style="width:7%;text-align:center;">Grade Point</th>
    <th style="width:7%;text-align:center;">Credit Points</th>
    <th style="width:5%;text-align:center;">Grade</th>
   </tr>
    {$strtdL}
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y=67.5+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y=67.5+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

 

     $html = <<<EOD
<style>
 th{

  font-size:7px; 
}
 td{

  font-size:{$fontSizeCource}px;
   border-left: 0px solid black;
    border-right: 0px solid black; 
}
#table3 {
  
    height: 400px;
  
  border-collapse: collapse;
}

</style>
<table id="table3" cellspacing="0" cellpadding="2" border="0.1" width="98.5%">
  <tr>
    <th style="width:11%;text-align:center;">Subject Code</th>
    <th style="width:39%;text-align:center;">Subject Name</th>
    <th style="width:5%;text-align:center;">CE</th>
    <th style="width:5%;text-align:center;">ESE</th>
    <th style="width:7%;text-align:center;">TOT</th>
    <th style="width:7%;text-align:center;">Subject Credits</th>
    <th style="width:7%;text-align:center;">Earn Credits</th>
    <th style="width:7%;text-align:center;">Grade Point</th>
    <th style="width:7%;text-align:center;">Credit Points</th>
    <th style="width:5%;text-align:center;">Grade</th>
   </tr>
    {$strtdR}
  </table>
EOD;

$pdf->writeHTMLCell($w=0, $h=0, $x='222', $y=64+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='222', $y=64+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

 

     $html = <<<EOD
<style>
 th{

  font-size:9px; 
}
 td{

  font-size:7.5px;
   
}
#table4 {
  
    height: 400px;
  
  border-collapse: collapse;
}


</style>
<table id="table4" cellspacing="0" cellpadding="2" border="0.1" width="98.5%">
    <tr>
    <td style="width:12%;text-align:center;">SGPA (I) : <b>{$studentData[9]}</b></td>
    <td style="width:12%;text-align:left;">SGPA (II) : <b>{$studentData[10]}</b></td>
    <td style="width:13%;text-align:center;">SGPA (III) : <b>{$studentData[11]}</b></td>
    <td style="width:13%;text-align:center;">SGPA (IV) : <b>{$studentData[12]}</b></td>
    <td style="width:13%;text-align:center;">SGPA (V) : <b>{$studentData[13]}</b></td>
    <td style="width:13%;text-align:center;">SGPA (VI) : <b>{$studentData[14]}</b></td>
    <td style="width:12%;text-align:center;">CGPA : <b>{$studentData[15]}</b></td>
   <td style="width:12%;text-align:center;">Grade : <b>{$studentData[16]}</b></td>
    </tr>
    <tr>
     <td style="width:35%;text-align:left;" colspan="3">Academic Earned Credits : <b>{$studentData[18]}</b></td>
    <td style="width:37%;text-align:left;" colspan="3">Environment and Skill Course Earned Credits : <b>{$studentData[19]}</b></td>
    <td style="width:28%;text-align:left;" colspan="2">Total Earned Credits : <b>{$studentData[20]}</b></td>
    </tr>
    <tr>
     <td style="width:30%;text-align:left;" colspan="2">Marks : <b>{$studentData[22]}</b></td>
    <td style="width:35%;text-align:left;" colspan="3">Result : <b>{$studentData[17]}</b></td>
    <td style="width:35%;text-align:left;" colspan="3">Date : <b>{$studentData[21]}</b></td>
    </tr>
  </table>
EOD;

$pdf->writeHTMLCell($w=0, $h=0, $x='222', $y='248.5', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='222', $y='248.5', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
 
        $nameOrg=$studentData[2];
        // Ghost image
        $ghost_font_size = '13';
        $ghostImagex = 257.2;
        $ghostImagey = 277;
        $ghostImageWidth = 55;//68
        $ghostImageHeight = 9.8;
        $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
        

        $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
   
        $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');

        $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        $pdfBig->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        //qr code    
        $dt = date("_ymdHis");
        $blobFile = pathinfo($studentData[23]);

        $serial_no=$GUID=$blobFile['filename'];
        $str= $GUID;
        $encryptedString = strtoupper(md5($str));
        $codeContents = $studentData[24];

        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 388.5;
        $qrCodey = 14;
        $qrCodeWidth =19.3;
        $qrCodeHeight = 19.3;
                
        \QrCode::size(75.6)
            
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        $pdfBig->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        

        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str));
        $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', $str);
        $strWidth = ($textArray[2] - $textArray[0]);
        $strHeight = $textArray[6] - $textArray[1] / 1.4;
        
        $width=2;
        $latestWidth = round($width*3.7795280352161);

         
        $microlinestr=$str;
        $wd = '';
        $last_width = 0;
        $pdf->SetFont($arialb, '', 1.2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();
        $pdf->SetXY(389, 32);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

        $pdfBig->SetFont($arialb, '', 1.2, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        $pdfBig->StartTransform();
        $pdfBig->SetXY(389, 32);
        $pdfBig->Cell(0, 0, $microlinestr, 0, false, 'C');
        

        //Page 2 backside
        $pdf->AddPage();
        $pdfBig->AddPage();
        
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\FC BG Page Outer.jpg';
    
        if($previewPdf!=1){
        $pdf->Image($template_img_generate, 0, 0, '420', '297', "JPG", '', 'R', true);
        }
        $pdf->setPageMark();
        if($previewPdf==1){
         if($previewWithoutBg!=1){
                $pdfBig->Image($template_img_generate, 0, 0, '420', '297', "JPG", '', 'R', true);
            }

        }
        $pdfBig->setPageMark();
        
        if($previewPdf==1){
             $date_font_size = '11';
        $date_nox = 13;
        $date_noy = 36;
        $date_nostr = 'DRAFT '.date('d-m-Y H:i:s');
        $pdfBig->SetFont($arialNarrowB, '', $date_font_size, '', false);
        $pdfBig->SetTextColor(192,192,192);
        $pdfBig->SetXY($date_nox, $date_noy);
        $pdfBig->Cell(0, 0, $date_nostr, 0, false, 'L');
        $pdfBig->SetTextColor(0,0,0,100,false,'');
        }
         $headingStr="ADDITIONAL CREDITS COURSES";
         $pdf->SetFont($arialb, '', 11, '', false);
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->SetXY(70, 33);
        $pdf->Cell(0, 0, $headingStr, 0, false, 'L');//182
        $pdfBig->SetFont($arialb, '', 11, '', false);
        $pdfBig->SetTextColor(0, 0, 0);
        
        $pdfBig->SetXY(70, 33);
        $pdfBig->Cell(0, 0, $headingStr, 0, false, 'L');


        $page_no_font_size = '9';
        $page_no_x= 185;
        $page_no_y = 39;
        $page_no_str = 'PAGE 3';
        $pdf->SetFont($arialNarrow, '', $page_no_font_size, '', false);
        $pdf->SetXY($page_no_x, $page_no_y);
        $pdf->Cell(0, 0, $page_no_str, 0, false, '');
        $pdf->SetFont($arialNarrow, '', 8, '', false);
        $pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='44', $htmlStudentL, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

        $pdfBig->SetFont($arialNarrow, '', $page_no_font_size, '', false);
        $pdfBig->SetXY($page_no_x, $page_no_y);
        $pdfBig->Cell(0, 0, $page_no_str, 0, false, '');
        $pdfBig->SetFont($arialNarrow, '', 8, '', false);
        $pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y='44', $htmlStudentL, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

         $strtd='';      

        if($lineOffset==1){
        $lineCount=1;
        }else{
           $lineCount=0; 
        }
 foreach ($specialSubArr as $readSubject) {
    if(strlen($readSubject[3])>47){
            $lineCount=$lineCount+1.64;
        }else{
            $lineCount=$lineCount+1;
        }
   $strtd .= '<tr>
                    <td style="width:11%;text-align:right;"><b>'.$readSubject[2].'</b></td>
                    <td style="width:39%;text-align:left;"><b>'.$readSubject[3].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[4].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[5].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[6].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[7].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[8].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[9].'</b></td>
                    <td style="width:7%;text-align:center;"><b>'.$readSubject[10].'</b></td>
                    <td style="width:5%;text-align:center;"><b>'.$readSubject[11].'</b></td>
                   </tr>';
}

for ($p=$lineCount; $p < 18; $p++) { 
                 $strtd .= '<tr>
                            <td style="width:11%;text-align:center;"></td>
                            <td style="width:39%;text-align:left;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:7%;text-align:center;"></td>
                            <td style="width:5%;text-align:center;"></td>
                            </tr>';
            }

     $html = <<<EOD
<style>
 th{

  font-size:7px; 
}
 td{

  font-size:8px;
   border-left: 0px solid black;
    border-right: 0px solid black; 
}
#table5 {
  
    height: 400px;
  
  border-collapse: collapse;
}
#table5 td:empty {
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
<table id="table5" class="t-table borderspace" cellspacing="0" cellpadding="2" border="0.1" width="46.4%">
  <tr>
    <th style="width:11%;text-align:right;">Subject Code</th>
    <th style="width:39%;text-align:center;">Subject Name</th>
    <th style="width:5%;text-align:center;">CE</th>
    <th style="width:5%;text-align:center;">ESE</th>
    <th style="width:7%;text-align:center;">TOT</th>
    <th style="width:7%;text-align:center;">Subject Credits</th>
    <th style="width:7%;text-align:center;">Earn Credits</th>
    <th style="width:7%;text-align:center;">Grade Point</th>
    <th style="width:7%;text-align:center;">Credit Points</th>
    <th style="width:5%;text-align:center;">Grade</th>
   </tr>   
    {$strtd}
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y=62+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
$pdfBig->writeHTMLCell($w=0, $h=0, $x='12', $y=62+$offset, $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
        
        if($previewPdf!=1){
         $certName = str_replace("/", "_", $GUID) .".pdf";
            
            $myPath = public_path().'/backend/temp_pdf_file';
            $dt = date("_ymdHis");

            $fileVerificationPath=$myPath . DIRECTORY_SEPARATOR . $certName;

            $pdf->output($myPath . DIRECTORY_SEPARATOR . $certName, 'F');


            $student_table_id =  $this->addCertificate($serial_no, $certName, $dt,$template_id,$admin_id,'FC\GC\\');

            $username = $admin_id['username'];
            date_default_timezone_set('Asia/Kolkata');

            $content = "#".$log_serial_no." serial No :".$serial_no.PHP_EOL;
            $date = date('Y-m-d H:i:s').PHP_EOL;
            $print_datetime = date("Y-m-d H:i:s");
            

            $print_count = $this->getPrintCount($serial_no);
            $printer_name = /*'HP 1020';*/$printer_name;
            $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $serial_no,'Fergusson-GC',$admin_id,$card_serial_no,$student_table_id);
            $card_serial_no=$card_serial_no+1;
            }
            
       } 

       if($previewPdf!=1){
        $this->updateCardNo('Fergusson-GC',$card_serial_no-$cardDetails->starting_serial_no,$card_serial_no);
       }
       $msg = '';
        
        $file_name =  str_replace("/", "_",'Fergusson-GC'.date("Ymdhms")).'.pdf';
        
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();


        $filename = public_path().'/backend/tcpdf/examples/'.$file_name;
        
        $pdfBig->output($filename,'F');

        //delete temp dir 26-04-2022 
        CoreHelper::rrmdir($tmpDir);

        $aws_qr = \File::copy($filename,public_path().'/'.$subdomain[0].'/backend/tcpdf/examples/'.$file_name);
        @unlink($filename);

         
                            $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
                            $path = $protocol.'://'.$subdomain[0].'.'.$subdomain[1].'.com/';
                            $msg = "<b>Click <a href='".$path.$subdomain[0]."/backend/tcpdf/examples/".$file_name."'class='downloadpdf' download target='_blank'>Here</a> to download file<b>";
         
        return $msg;

    }

    public function pdfGenerate(){


        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $ghostImgArr = array();
        $pdf = new TCPDF('L', 'mm', array('297', '420'), true, 'UTF-8', false);
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
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $krutidev100 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K100.TTF', 'TrueTypeUnicode', '', 96); 
        $krutidev101 = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\K101.TTF', 'TrueTypeUnicode', '', 96);
        $HindiDegreeBold = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\KRUTI_DEV_100__BOLD.TTF', 'TrueTypeUnicode', '', 96); 
        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);
        $arialNarrow = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\arialn.TTF', 'TrueTypeUnicode', '', 96);

     


        $pdf->AddPage();
        
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\FC BG Page inner.jpg';

        $pdf->Image($template_img_generate, 0, 0, '420', '297', "JPG", '', 'R', true);
        $pdf->setPageMark();

        $fontEBPath=public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\E-13B_0.php';
        $pdf->AddFont('E-13B_0', '', $fontEBPath);
        
         //set enrollment no
        $card_serial_no_font_size = '13';
        $card_serial_nox= 172.5;
        $card_serial_noy = 34;
        $pdf->SetFont('E-13B_0', '', $card_serial_no_font_size, '', false);
        $pdf->SetXY($card_serial_nox, $card_serial_noy);
        $pdf->Cell(23.5, 0, '012345', 0, false, 'R');

        $pdf->SetFont($arialNarrow, '', 11, '', false);

        $date_font_size = '11';
        $date_nox = 13;
        $date_noy = 36;
        $date_nostr = 'DRAFT '.date('d-m-Y H:i:s');
        $pdf->SetFont($arialNarrowB, '', $date_font_size, '', false);
        $pdf->SetTextColor(192,192,192);
        $pdf->SetXY($date_nox, $date_noy);
        $pdf->Cell(0, 0, $date_nostr, 0, false, 'L');
        $pdf->SetTextColor(0,0,0,100,false,'');

        $page_no_font_size = '9';
        $page_no_x= 185;
        $page_no_y = 40.5;
        $page_no_str = 'PAGE 1';
        $pdf->SetFont($arialNarrow, '', $page_no_font_size, '', false);
        $pdf->SetXY($page_no_x, $page_no_y);
        $pdf->Cell(0, 0, $page_no_str, 0, false, '');

        $page_no_font_size = '9';
        $page_no_x= 394;
        $page_no_y = 40.5;
        $page_no_str = 'PAGE 2';
        $pdf->SetFont($arialNarrow, '', $page_no_font_size, '', false);
        $pdf->SetXY($page_no_x, $page_no_y);
        $pdf->Cell(0, 0, $page_no_str, 0, false, '');


        $htmlStudentL = <<<EOD
<style>
 td{
 
  font-size:8px; 
 border-top: 0px solid black;
    border-bottom: 0px solid black;
}
#table1 {
    
  border-collapse: collapse;
}
</style>
<table id="table1" cellspacing="0" cellpadding="2" border="0.1" width="46.4%" rules="rows">
  <tr>
    <td colspan="2">  Student Name :  <b>ANUSHKA ANAND ANARSE</b></td>
    <td>  Mother's Name :  <b>ANKITA </b></td>
   </tr>
   <tr>
    <td colspan="2">  Academic Programme : <b>BACHELOR OF SCIENCE</b></td>
    <td>  Year : <b>APRIL 2020</b></td>
   </tr>
   <tr>
    <td>  Permanent Reg . No : <b>1234567890</b></td>
    <td style="text-align:center">  Seat Number : <b>00001</b></td>
    <td> UID. No : <b>BSC203200</b></td>
   </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='46', $htmlStudentL, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

        $html = <<<EOD
<style>
 td{
 
  font-size:8px; 
 border-top: 0px solid black;
    border-bottom: 0px solid black;
}
#table11 {
    
  border-collapse: collapse;
}
</style>
<table id="table11" cellspacing="0" cellpadding="2" border="0.1" width="98.5%" rules="rows">
  <tr>
    <td colspan="2">  Student Name :  <b>ANUSHKA ANAND ANARSE</b></td>
    <td>  Mother's Name :  <b>ANKITA </b></td>
   </tr>
   <tr>
    <td colspan="2">  Academic Programme : <b>BACHELOR OF SCIENCE</b></td>
    <td>  Year : <b>APRIL 2020</b></td>
   </tr>
   <tr>
    <td>  Permanent Reg . No : <b>1234567890</b></td>
    <td style="text-align:center">  Seat Number : <b>00001</b></td>
    <td> UID. No : <b>BSC203200</b></td>
   </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='222', $y='46', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);



$strtd="";
for ($i=0; $i <41 ; $i++) { //25 
  if($i!=6&&$i!=12&&$i!=17){
    $strtd .= '<tr>
    <td style="width:11%;text-align:right;"><b>STS2401</b>&nbsp;&nbsp;</td>
    <td style="width:39%;text-align:left;"><b>STATISTICAL AND INFERENTIAL METHODS </b></td>
    <td style="width:5%;text-align:center;"><b>31</b></td>
    <td style="width:5%;text-align:center;"><b>43</b></td>
    <td style="width:7%;text-align:center;"><b>74</b></td>
    <td style="width:7%;text-align:center;"><b>3</b></td>
    <td style="width:7%;text-align:center;"><b>3</b></td>
    <td style="width:7%;text-align:center;"><b>7</b></td>
    <td style="width:7%;text-align:center;"><b>24</b></td>
    <td style="width:5%;text-align:center;"><b>A</b></td>
   </tr>';

 }else{
    
    if($i==6){
      $sem="II";
    }elseif($i==12){
      $sem="III";
    }elseif($i==17){
      $sem="IV";
    }
    
 
  $strtd .= '<tr>
    <td style="width:11%;text-align:right;"><b>SEM '.$sem.'</b>&nbsp;&nbsp;</td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>';
 }
}

     $html = <<<EOD
<style>
 th{

  font-size:7px; 
}
 td{

  font-size:8px;
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
<table id="table2" class="t-table borderspace" cellspacing="0" cellpadding="2" border="0.1" width="46.4%">
  <tr>
    <th style="width:11%;text-align:right;">Subject Code</th>
    <th style="width:39%;text-align:center;">Subject Name</th>
    <th style="width:5%;text-align:center;">CE</th>
    <th style="width:5%;text-align:center;">ESE</th>
    <th style="width:7%;text-align:center;">TOT</th>
    <th style="width:7%;text-align:center;">Subject Credits</th>
    <th style="width:7%;text-align:center;">Earn Credits</th>
    <th style="width:7%;text-align:center;">Grade Point</th>
    <th style="width:7%;text-align:center;">Credit Points</th>
    <th style="width:5%;text-align:center;">Grade</th>
   </tr>
   <tr>
    <td style="width:11%;text-align:right;"><b>SEM I</b>&nbsp;&nbsp;</td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>
   <tr>
    <td style="width:11%;text-align:right;"><b>STS2401</b>&nbsp;&nbsp;</td>
    <td style="width:39%;text-align:left;"><b>STATISTICAL AND INFERENTIAL METHODS </b></td>
    <td style="width:5%;text-align:center;"><b>31</b></td>
    <td style="width:5%;text-align:center;"><b>43</b></td>
    <td style="width:7%;text-align:center;"><b>74</b></td>
    <td style="width:7%;text-align:center;"><b>3</b></td>
    <td style="width:7%;text-align:center;"><b>3</b></td>
    <td style="width:7%;text-align:center;"><b>7</b></td>
    <td style="width:7%;text-align:center;"><b>24</b></td>
    <td style="width:5%;text-align:center;"><b>A</b></td>
   </tr>
   
    {$strtd}
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='64', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

$strtd="";
for ($i=0; $i <35 ; $i++) { //17
  if($i!=8){
    $strtd .= '<tr>
    <td style="width:11%;text-align:right;"><b>STS2401</b>&nbsp;&nbsp;</td>
    <td style="width:39%;text-align:left;"><b>STATISTICAL AND INFERENTIAL METHODS</b></td>
    <td style="width:5%;text-align:center;"><b>31</b></td>
    <td style="width:5%;text-align:center;"><b>43</b></td>
    <td style="width:7%;text-align:center;"><b>74</b></td>
    <td style="width:7%;text-align:center;"><b>3</b></td>
    <td style="width:7%;text-align:center;"><b>3</b></td>
    <td style="width:7%;text-align:center;"><b>7</b></td>
    <td style="width:7%;text-align:center;"><b>24</b></td>
    <td style="width:5%;text-align:center;"><b>A</b></td>
   </tr>';

 }else{
 
    $sem="VI";
 
  $strtd .= '<tr>
    <td style="width:11%;text-align:right;"><b>SEM '.$sem.'</b>&nbsp;&nbsp;</td>
    <td style="width:39%;text-align:left;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:7%;text-align:center;"></td>
    <td style="width:5%;text-align:center;"></td>
    </tr>';
 }
} 

     $html = <<<EOD
<style>
 th{

  font-size:7px; 
}
 td{

  font-size:8px;
   border-left: 0px solid black;
    border-right: 0px solid black; 
}
#table3 {
  
    height: 400px;
  
  border-collapse: collapse;
}

</style>
<table id="table3" cellspacing="0" cellpadding="2" border="0.1" width="98.5%">
  <tr>
    <th style="width:11%;text-align:right;">Subject Code</th>
    <th style="width:39%;text-align:center;">Subject Name</th>
    <th style="width:5%;text-align:center;">CE</th>
    <th style="width:5%;text-align:center;">ESE</th>
    <th style="width:7%;text-align:center;">TOT</th>
    <th style="width:7%;text-align:center;">Subject Credits</th>
    <th style="width:7%;text-align:center;">Earn Credits</th>
    <th style="width:7%;text-align:center;">Grade Point</th>
    <th style="width:7%;text-align:center;">Credit Points</th>
    <th style="width:5%;text-align:center;">Grade</th>
   </tr>

    {$strtd}
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='222', $y='64', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

 

     $html = <<<EOD
<style>
 th{

  font-size:9px; 
}
 td{

  font-size:7.5px;
   
}
#table4 {
  
    height: 400px;
  
  border-collapse: collapse;
}


</style>
<table id="table4" cellspacing="0" cellpadding="2" border="0.1" width="98.5%">
    <tr>
    <td style="width:12%;text-align:center;">SGPA (I) : <b>31.00</b></td>
    <td style="width:12%;text-align:left;">SGPA (II) : <b>31.00</b></td>
    <td style="width:13%;text-align:center;">SGPA (III) : <b>31.00</b></td>
    <td style="width:13%;text-align:center;">SGPA (IV) : <b>31.00</b></td>
    <td style="width:13%;text-align:center;">SGPA (V) : <b>31.00</b></td>
    <td style="width:13%;text-align:center;">SGPA (VI) : <b>31.00</b></td>
    <td style="width:12%;text-align:center;">CGPA : <b>31.00</b></td>
   <td style="width:12%;text-align:center;">Grade : <b>A+</b></td>
    </tr>
    <tr>
     <td style="width:35%;text-align:left;" colspan="3">Extra Earned Credits : <b>31.00</b></td>
    <td style="width:37%;text-align:left;" colspan="3">Academic Earned Credits : <b>31.00</b></td>
    <td style="width:28%;text-align:left;" colspan="2">Total Earned Credits : <b>31.00</b></td>
    </tr>
    <tr>
     <td style="width:30%;text-align:left;" colspan="2">Total Marks : <b>4793 / 6200</b></td>
    <td style="width:35%;text-align:left;" colspan="3">Result : <b>Pass</b></td>
    <td style="width:35%;text-align:left;" colspan="3">Date : <b>S 10 March 2020</b></td>
    </tr>
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='222', $y='248.5', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
     
 
        $nameOrg="ANUSHKA ANAND ANARSE";
// Ghost image
                $ghost_font_size = '13';
                $ghostImagex = 257.2;
                $ghostImagey = 277;
                $ghostImageWidth = 55;//68
                $ghostImageHeight = 9.8;
                $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
                

                $tmpDir = $this->createTemp(public_path().'/backend/canvas/ghost_images/temp');
                
                    $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
                    

                $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        //qr code    
        $dt = date("_ymdHis");
        $str="TEST0001";
        $encryptedString = strtoupper(md5($str));
        $codeContents = "https://doc.deccansociety.org/Doc/GetDoc?filePath=7GAvqnwkJgMqlDQ0T/qRWpojRJgrMNTO8F0NxN4/Q1g=";

        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 388.5;
        $qrCodey = 14;
        $qrCodeWidth =19.3;
        $qrCodeHeight = 19.3;
                
        \QrCode::size(75.6)
            
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        

                            $str = $nameOrg;
                            $str = strtoupper(preg_replace('/\s+/', '', $str)); 
                            $textArray = imagettfbbox(1.4, 0, public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', $str);
                            $strWidth = ($textArray[2] - $textArray[0]);
                            $strHeight = $textArray[6] - $textArray[1] / 1.4;
                            
                             $width=2;
                            $latestWidth = round($width*3.7795280352161);

                             
                            $microlinestr=$str;
                    

                            $wd = '';
                            $last_width = 0;
                            $pdf->SetFont($arialNarrowB, '', 1.2, '', false);
                            $pdf->SetTextColor(0, 0, 0);
                            $pdf->StartTransform();
                            // $pdf->SetXY(36.8, 146.6);
                            $pdf->SetXY(389, 32);
                            
                            $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

            

        //Page 2 backside
        $pdf->AddPage();
        
        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\FC BG Page Outer.jpg';
   
        $pdf->Image($template_img_generate, 0, 0, '420', '297', "JPG", '', 'R', true);
        $pdf->setPageMark();
        
         $headingStr="ADDITIONAL CREDITS COURSES";
         $pdf->SetFont($arialb, '', 11, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->StartTransform();

        
        $pdf->Cell(182, 52.5, $headingStr, 0, false, 'C');

        $page_no_font_size = '9';
        $page_no_x= 185;
        $page_no_y = 39;
        $page_no_str = 'PAGE 3';
        $pdf->SetFont($arialNarrow, '', $page_no_font_size, '', false);
        $pdf->SetXY($page_no_x, $page_no_y);
        $pdf->Cell(0, 0, $page_no_str, 0, false, '');

        $pdf->SetFont($arialNarrow, '', 8, '', false);
        $pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='44', $htmlStudentL, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);

       

        $strtd="";
for ($i=0; $i <18 ; $i++) { //10
    $strtd .= '<tr>
    <td style="width:11%;text-align:right;"><b>STS2401</b>&nbsp;&nbsp;</td>
    <td style="width:39%;text-align:left;"><b>STATISTICAL AND INFERENTIAL METHODS </b></td>
    <td style="width:5%;text-align:center;"><b>31</b></td>
    <td style="width:5%;text-align:center;"><b>43</b></td>
    <td style="width:7%;text-align:center;"><b>74</b></td>
    <td style="width:7%;text-align:center;"><b>3</b></td>
    <td style="width:7%;text-align:center;"><b>3</b></td>
    <td style="width:7%;text-align:center;"><b>7</b></td>
    <td style="width:7%;text-align:center;"><b>24</b></td>
    <td style="width:5%;text-align:center;"><b>A</b></td>
   </tr>';
}

     $html = <<<EOD
<style>
 th{

  font-size:7px; 
}
 td{

  font-size:8px;
   border-left: 0px solid black;
    border-right: 0px solid black; 
}
#table5 {
  
    height: 400px;
  
  border-collapse: collapse;
}
#table5 td:empty {
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
<table id="table5" class="t-table borderspace" cellspacing="0" cellpadding="2" border="0.1" width="46.4%">
  <tr>
    <th style="width:11%;text-align:right;">Subject Code</th>
    <th style="width:39%;text-align:center;">Subject Name</th>
    <th style="width:5%;text-align:center;">CE</th>
    <th style="width:5%;text-align:center;">ESE</th>
    <th style="width:7%;text-align:center;">TOT</th>
    <th style="width:7%;text-align:center;">Subject Credits</th>
    <th style="width:7%;text-align:center;">Earn Credits</th>
    <th style="width:7%;text-align:center;">Grade Point</th>
    <th style="width:7%;text-align:center;">Credit Points</th>
    <th style="width:5%;text-align:center;">Grade</th>
   </tr>   
    {$strtd}
  </table>
EOD;
$pdf->writeHTMLCell($w=0, $h=0, $x='12', $y='62', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);



        $pdf->Output('sample.pdf', 'I');  

        //delete temp dir 26-04-2022 
        CoreHelper::rrmdir($tmpDir);
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

        
        CoreHelper::uploadBlob($pdfActualPath,$blob.$certName);

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
        return $result['id'];
    }

    public function getPrintCount($serial_no)
    {
        $auth_site_id=Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        
        return $numCount + 1;
    }

    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$card_serial_no,$student_table_id)
    {
        
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $systemConfig = SystemConfig::where('site_id',$auth_site_id)->first();

        if($systemConfig['sandboxing'] == 1){
        $result =  SbPrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>'T-'.$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }else{
        $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$sr_no,'template_name'=>$template_name,'card_serial_no'=>$card_serial_no,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1,'student_table_id'=>$student_table_id]);    
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
