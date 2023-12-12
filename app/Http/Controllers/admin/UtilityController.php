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
//use Illuminate\Support\Facades\Storage;
use App\Helpers\CoreHelper;
use Helper;
use App\Jobs\ValidateExcelUtilityJob;
use App\Jobs\PdfGenerateUtilityRotakJob;
use App\Jobs\PdfGenerateUtilityNIITJob;
use App\Jobs\PdfGenerateUtilityNIITJob2;
use App\Jobs\PdfGenerateUtilityNIITJobDegreeCertificate;
use App\Utility\GibberishAES;
use setasign\Fpdi\Fpdi;
//use mikehaertl\pdftk\Pdf;


class UtilityController extends Controller
{
    public function index(Request $request)
    {
       //echo "success";
       return view('admin.utility.index');
    }

    public function showTemplates(Request $request)
    {
       //echo "success";
       return view('admin.utility.index');
    }


     public function validateExcel(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
        $template_id=100;
        $dropdown_template_id = $request['template_id'];
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
       
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
         //check file is uploaded or not
        if($request->hasFile('field_file')&&$request->hasFile('field_file_pdf')){
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
                
                $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile);

                $new_excel_path=public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile;
                
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel1 = $objReader->load($fullpath);
                $sheet1 = $objPHPExcel1->getSheet(0);
                $highestColumn1 = $sheet1->getHighestColumn();
                $highestRow1 = $sheet1->getHighestDataRow();
                $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
                 //For checking certificate limit updated by Mandar
                $recordToGenerate=$highestRow1-1;
               
                 $excelData=array('rowData1'=>$rowData1,'auth_site_id'=>$auth_site_id,'dropdown_template_id'=>$dropdown_template_id);
               
                $response = $this->dispatch(new ValidateExcelUtilityJob($excelData));
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
            if (file_exists($new_excel_path)) {
                unlink($new_excel_path);
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
            return response()->json(['success'=>false,'message'=>'Excel or PDF File not found!']);
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
        /* 1=Basic */
        $previewPdf = array($request['previewPdf'],$request['previewWithoutBg']);

        //check file is uploaded or not
        if($request->hasFile('field_file')&&$request->hasFile('field_file_pdf')){
            //check extension
            $file_name = $request['field_file']->getClientOriginalName();
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);

            //excel file name
            $excelfile =  date("YmdHis") . "_" . $file_name;
            $target_path = public_path().'/backend/canvas/dummy_images/'.$template_id;
            $fullpath = $target_path.'/'.$excelfile;

            //check extension
            $file_name_pdf = $request['field_file_pdf']->getClientOriginalName();
            $ext_pdf = pathinfo($file_name_pdf, PATHINFO_EXTENSION);

            //excel file name
            $excelfile_pdf =  date("YmdHis") . "_" . $file_name_pdf;
            $target_path_pdf = public_path().'/backend/canvas/dummy_images/'.$template_id;
            $fullpath_pdf = $target_path_pdf.'/'.$excelfile_pdf;

            if(!is_dir($target_path)){
                
                            mkdir($target_path, 0777);
                        }

            if($request['field_file']->move($target_path,$excelfile)&&$request['field_file_pdf']->move($target_path_pdf,$excelfile_pdf)){
                //get excel file data
                
                if($ext == 'xlsx' || $ext == 'Xlsx'){
                    $inputFileType = 'Xlsx';
                }
                else{
                    $inputFileType = 'Xls';
                }
                $auth_site_id=Auth::guard('admin')->user()->site_id;

                $aws_excel = \File::copy($fullpath,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile);
                
                $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                /**  Load $inputFileName to a Spreadsheet Object  **/
                $objPHPExcel1 = $objReader->load($fullpath);
                $sheet1 = $objPHPExcel1->getSheet(0);
                $highestColumn1 = $sheet1->getHighestColumn();
                $highestRow1 = $sheet1->getHighestDataRow();
                $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);           

                $aws_excel = \File::copy($fullpath_pdf,public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id.'/'.$excelfile_pdf);
                
            }else{
            return response()->json(['success'=>false,'message'=>'Excel or PDF File not uploaded!']);
            }                                   
        }
        else{
            return response()->json(['success'=>false,'message'=>'Excel or PDF File not found!']);
        } 
        unset($rowData1[0]);
        $rowData1=array_values($rowData1);        
        //store ghost image 
        //$tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();
        
        $pdfData=array('studentDataOrg'=>$rowData1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'excelfile_pdf'=>$excelfile_pdf,'loader_token'=>$loader_token, 'subj_col' => '', 'subj_start'=> '', 'subj_end'=> ''); //For Custom Loader
        
        if($dropdown_template_id==1){
            $link = $this->dispatch(new PdfGenerateUtilityRotakJob($pdfData));
        }
        elseif($dropdown_template_id==2){
            $link = $this->dispatch(new PdfGenerateUtilityNIITJob($pdfData));
        }elseif($dropdown_template_id==3){
            $link = $this->dispatch(new PdfGenerateUtilityNIITJob2($pdfData));
        }elseif($dropdown_template_id==4){
            $link = $this->dispatch(new PdfGenerateUtilityNIITJobDegreeCertificate($pdfData));
        }


        return response()->json(['success'=>true,'message'=>'Certificates generated successfully.','link'=>$link]);
    }


    public function rotak(){

        $ghostImgArr=[];

        //Excel Data Fet
        $inputFileType="Xlsx";
        $fullpath=public_path().'/demo/NIIT/NIITExcel.xlsx';
        $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        /**  Load $inputFileName to a Spreadsheet Object  **/
        $objPHPExcel1 = $objReader->load($fullpath);
        $sheet1 = $objPHPExcel1->getSheet(0);
        $highestColumn1 = $sheet1->getHighestColumn();
        $highestRow1 = $sheet1->getHighestDataRow();
        $excelData = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);

        // initiate FPDI
        $pdf = new Fpdi('P', 'mm', array('228.6', '315.5'));
        //add a page
        $pdf->AddPage();
        
        try {
          $pageCount=  $pdf->setSourceFile(public_path()."/demo/utility/Binder1.pdf");

        } catch (\Exception $exception) {
         
            exec('pdftk '.public_path()."/demo/utility/Binder1.pdf output ".public_path()."/demo/utility/Binder1_unc.pdf".' uncompress');
           $pageCount= $pdf->setSourceFile(public_path()."/demo/utility/Binder1_unc.pdf");
 
        }
      
        

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {

        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');

        //Read each line of excel data
        $name=$excelData[$pageNo-1][0];    
        if(!empty($name)){
        /*echo $name;
        echo "<br>";*/
            
        $tplIdx = $pdf->importPage($pageNo);
        
        if($pageNo!=1){
            $size = $pdf->getTemplateSize($tplIdx);  
        //    print_r($size);
          //  exit; 
            // create a page (landscape or portrait depending on the imported page size)
            $pdf->AddPage('P', array($size[0], $size[1])); 
        }
        
        
       
        $pdf->useTemplate($tplIdx, null, null, 228.6, 317.5, true);
        
        /************Ghost Image Start***********************************/
        $ghost_font_size = '11';
        $ghostImagex = 35;//81.5;//75.5
        $ghostImagey = 303;
        
        $name=preg_replace("/[^A-Za-z0-9 ]/", '', $name);
        $name=strtoupper($name);
        $name = str_replace(' ','',$name);
        $name=substr($name, 0, 12);
        $strLen=strLen($name);
        $widthPerChar=5.6; //7.33 for font 13 Sikkim
       //  $widthPerChar=6.1; //7.33 for font 13 Sikkim
        $ghostImageWidth = $widthPerChar*$strLen;//44
        $ghostImageHeight = 8;
        

       // $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        if(!array_key_exists($name, $ghostImgArr))
        {
            $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size);
            $ghostImgArr[$name] = $w;   
        }
        else{
            $w = $ghostImgArr[$name];
        }

        //echo $w;
         //echo "<br>";
         $ghostImagex = $ghostImagex -(($strLen*5/2));
       //echo "<br>";
       // NIIT SIKKIM END
         $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

        

        CoreHelper::rrmdir($tmpDir);

        /*********Ghost Image End**********************/


        /***Yellow Patch**/
        $name=preg_replace("/[^A-Za-z0-9 ]/", '', $name);
        $name=strtoupper($name);
        $qrCodex=135;
        $qrCodey=303;
        $qrCodeHeight=9;
        $len = strlen($name);
        $totalWidth=0;
        for($i = 0; $i < $len; $i++) {
            $value = $name[$i];
            $qr_code_path = public_path().'\\backend\canvas\yellowpatchimages\/'.$value.'.png';
             
            list($width, $height) = getimagesize($qr_code_path);
            //$width;
            //exit;
            $qrCodeWidth=$width*0.05 ;
            $totalWidth=$totalWidth+$qrCodeWidth;
           //echo "<br>";
           // $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
            //$qrCodex=$qrCodex+$qrCodeWidth;
        }

         $qrCodex=228.6-(10+$totalWidth);
        // exit;
         for($i = 0; $i < $len; $i++) {
            $value = $name[$i];
            $qr_code_path = public_path().'\\backend\canvas\yellowpatchimages\/'.$value.'.png';
             
            list($width, $height) = getimagesize($qr_code_path);
            //$width;
            //exit;
            $qrCodeWidth=$width*0.05 ;
            
           //echo "<br>";
            $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
            $qrCodex=$qrCodex+$qrCodeWidth;
        }
        /***Yellow Patch End**/

        }
        }
        //echo $a;//67.5//7
      

         $pdf->Output();    


    }


        public function NIIT(){

        $ghostImgArr=[];

        // initiate FPDI
       $pdf = new Fpdi('P', 'mm', array('205', '315'));
        //add a page
       $pdf->AddPage();
        
        try {
          $pageCount=  $pdf->setSourceFile(public_path()."/demo/utility/NIIT-25.02.2023.pdf");

        } catch (\Exception $exception) {
         
            exec('pdftk '.public_path()."/demo/utility/NIIT-25.02.2023.pdf output ".public_path()."/demo/utility/NIIT-25.02.2023_unc.pdf".' uncompress');
           $pageCount= $pdf->setSourceFile(public_path()."/demo/utility/NIIT-25.02.2023_unc.pdf");
 

        }
      

        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $tplIdx = $pdf->importPage($pageNo);
        // add a page
        /*$pdf->AddPage();
       */
        if($pageNo!=1){
         $size = $pdf->getTemplateSize($tplIdx);   
             // create a page (landscape or portrait depending on the imported page size)
       /* if ($size[0] > $size[1]) {
            $pdf->AddPage('L', array($size[0], $size[1]));
        } else {*/
            $pdf->AddPage('P', array($size[0], $size[1])); 
        //} 
           
        }
        
        
        $pdf->useTemplate($tplIdx, null, null, 205, 315, true);
         // get the size of the imported page
        
        $ghost_font_size = '10';
        $ghostImagex = 102.5;//81.5;//75.5
        $ghostImagey = 294.5;
        
    
        if($pageNo % 2 == 0){
              $name='MANDARHANSGA'; 
        }
        else{
             $name='SAGAR';
        }

        $name=substr($name, 0, 12);
        $strLen=strLen($name);
        $widthPerChar=5.6; //7.33 for font 13 Sikkim
        $ghostImageWidth = $widthPerChar*$strLen;//44
        $ghostImageHeight = 8;
        $name = str_replace(' ','',$name);
        if(!array_key_exists($name, $ghostImgArr))
        {
            $w = $this->createGhostImageNIIT($tmpDir, $name ,$ghost_font_size,$name);
            $ghostImgArr[$name] = $w;   
        }
        else{
            $w = $ghostImgArr[$name];
        }

        $ghostImagex = $ghostImagex -(($strLen*5/2));
        $pdf->Image("$tmpDir/" . $name.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

        }
        CoreHelper::rrmdir($tmpDir);
        $pdf->Output();    


    }


     public function testData(){

      //require_once('vendor/autoload.php');

        // initiate FPDI
       $pdf = new Fpdi('P', 'mm', array('205', '315'));
        //add a page
       $pdf->AddPage();
        //set the source file
        //$pdf->setSourceFile(public_path()."/demo/utility/NIIT.pdf");
        
        try {
            $pdf->setSourceFile(public_path()."/demo/utility/NIIT.pdf");

        } catch (\Exception $exception) {
            //echo $exception;
            /*if (aBoolFunctionToDetectThisParticularException($exception)) {
                exec('pdftk '.public_path()."/demo/utility/NIIT.pdf".' output '.public_path()."/demo/utility/NIIT_unc.pdf".' uncompress');
                $pdf->setSourceFile(public_path()."/demo/utility/NIIT_unc.pdf");
            } else {
                throw $exception;
            }*/
           // echo 'pdftk '.public_path()."/demo/utility/NIIT.pdf".' output '.public_path()."/demo/utility/NIIT_unc.pdf".' uncompress';
          //  echo "s";
              exec('pdftk '.public_path()."/demo/utility/NIIT.pdf output ".public_path()."/demo/utility/NIIT_unc.pdf".' uncompress');
             $pdf->setSourceFile(public_path()."/demo/utility/NIIT_unc.pdf");
        //  echo get_current_user();
/*             exec('pdftk '.public_path()."/demo/utility/NIIT.pdf".' output '.public_path()."/demo/utility/NIIT_unc.pdf".' uncompress 2>&1',$output);
var_dump($output);
$pdf = new Pdf(public_path()."/demo/utility/NIIT.pdf", [
    'command' => 'C:\Program Files (x86)\PDFtk Server\bin\pdftk.exe',
    // or on most Windows systems:
    // 'command' => 'C:\Program Files (x86)\PDFtk\bin\pdftk.exe',
    'useExec' => true,  // May help on Windows systems if execution fails
]);*/
         //   exit;
  }


/*$pdf = new Pdf(public_path()."/demo/utility/NIIT.pdf", [
    'command' => 'C:\Program Files (x86)\PDFtk Server\bin\pdftk.exe',
    // or on most Windows systems:
    // 'command' => 'C:\Program Files (x86)\PDFtk\bin\pdftk.exe',
    'useExec' => true,  // May help on Windows systems if execution fails
]);*/

/*$pdf = new Pdf(public_path()."/demo/utility/NIIT.pdf");
$value="Uncompress";
$result = $pdf->allow('AllFeatures')      // Change permissions
    ->Uncompress($value)          // Compress/Uncompress
    ->saveAs('new.pdf');
if ($result === false) {
    echo $error = $pdf->getError();
}*/

//echo $error = $pdf->getError();

      
        $ghostImgArr=[];
        // import page 1
        $tplId = $pdf->importPage(1);
        // use the imported page and place it at point 10,10 with a width of 100 mm
        $pdf->useTemplate($tplId);

         $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');

         //NIIT SIKKIM START
        $ghost_font_size = '10';
        $ghostImagex = 75.5;
        $ghostImagey = 294.5;
        
        $name='MANDARHANSGA';
        $name=substr($name, 0, 12);
        $strLen=strLen($name);
        $widthPerChar=5.6; //7.33 for font 13 Sikkim
       //  $widthPerChar=6.1; //7.33 for font 13 Sikkim
        $ghostImageWidth = $widthPerChar*$strLen;//44
        $ghostImageHeight = 8;
        $name = str_replace(' ','',$name);

        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        if(!array_key_exists($name, $ghostImgArr))
        {
            $w = $this->createGhostImageNIIT($tmpDir, $name ,$ghost_font_size,$name);
            $ghostImgArr[$name] = $w;   
        }
        else{
            $w = $ghostImgArr[$name];
        }
       // NIIT SIKKIM END
         $pdf->Image("$tmpDir/" . $name.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        //$pdf->Image(public_path()."/demo/utility/B190015CE.png", 10, 10, 100,20);

        //delete temp dir 26-04-2022 
        CoreHelper::rrmdir($tmpDir);
        $pdf->Output();    


    }






    public function createTemp($path){
        //create ghost image folder
        $tmp = date("ymdHis");
        // print_r($path);
        // dd($tmp);
        $tmpname = tempnam($path, $tmp);
        //unlink($tmpname);
        //mkdir($tmpname);
        if (file_exists($tmpname)) {
         unlink($tmpname);
        }
        mkdir($tmpname, 0777);
        return $tmpname;
    }



     public function createGhostImageNIIT($tmpDir, $name = "",$font_size,$customName="")
    {
        if($name == "")
            return;
        $name = strtoupper($name);
        // Create character image
        $font_size=10;
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
                "Z" => array(17710, 680),
                "0" => array(18310, 725),
                "1" => array(19035, 725),
                "2" => array(19760, 725),
                "3" => array(20485, 725),
                "4" => array(21210, 725),
                "5" => array(21935, 725),
                "6" => array(22660, 725),
                "7" => array(23385, 725),
                "8" => array(24110, 725),
                "9" => array(24835, 630)
                
            );
            

            $filename = public_path()."/backend/canvas/ghost_images/7_1.png";//F10_H5_W180//F10_RND_latest //F10_H5_W180_TWO_IMG
//echo 'convert '.public_path().'/backend/canvas/ghost_images/7.png '.public_path().'/backend/canvas/ghost_images/out.png';
           // exec('convert '.public_path().'/backend/canvas/ghost_images/7.png '.public_path().'/backend/canvas/ghost_images/out.png');
            $charsImage = imagecreatefrompng($filename);
            
           // imagealphablending($charsImage, false);
           // imagesavealpha($charsImage, true);
            //$filename = public_path()."/backend/canvas/ghost_images/F10_RND_latest.png";//F10_H5_W180
            //$charsImage2 = imagecreatefrompng($filename);
            //imagealphablending($charsImage2, false);
            //imagesavealpha($charsImage2, true);
            
            $size = getimagesize($filename);
            // Create Backgoround image
            $filename   = public_path()."/backend/canvas/ghost_images/alpha_GHOST.png";//alpha_GHOST
            $bgImage = imagecreatefrompng($filename);

            $currentX = 0;
            $len = strlen($name);
            
            for($i = 0; $i < $len; $i++) {
                $value = $name[$i];
                if(!array_key_exists($value, $AlphaPosArray))
                    continue;
                $X = $AlphaPosArray[$value][0];
                $W = $AlphaPosArray[$value][1];
               // imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 70);
                 imagecopymerge($bgImage, $charsImage, $currentX, 0, $X, 0, $W, $size[1], 100);
                $currentX += $W;
            }
            
           

            $rect = array("x" => 0, "y" => 0, "width" => $currentX, "height" => $size[1]);
             $im = imagecrop($bgImage, $rect);

            //imagepng($im, "$tmpDir/" . $name."".$font_size.".png");
             imagepng($im, "$tmpDir/" . $customName.".png");
            
            imagedestroy($bgImage);
            imagedestroy($charsImage);
            return round((5 * $currentX)/ $size[1]);

        
    }

    public function CreateMessage($tmpDir, $name = "",$font_size)
    {
        if($name == "")
            return;
        $name = strtoupper($name);
        // Create character image
        if($font_size == 15){


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
                "Z" => array(20827, 876),
                "0" => array(21703, 850),
                "1" => array(22553, 850),
                "2" => array(23403, 850),
                "3" => array(24253, 850),
                "4" => array(25103, 850),
                "5" => array(25953, 850),
                "6" => array(26803, 850),
                "7" => array(27653, 850),
                "8" => array(28503, 850),
                "9" => array(29353, 635)
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
                "Z" => array(20842, 792),
                "0" => array(21634, 850),
                "1" => array(22484, 850),
                "2" => array(23334, 850),
                "3" => array(24184, 850),
                "4" => array(25034, 850),
                "5" => array(25884, 850),
                "6" => array(26734, 850),
                "7" => array(27584, 850),
                "8" => array(28434, 850),
                "9" => array(29284, 700)
            
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
                "Z" => array(17710, 680),
                "0" => array(18310, 725),
                "1" => array(19035, 725),
                "2" => array(19760, 725),
                "3" => array(20485, 725),
                "4" => array(21210, 725),
                "5" => array(21935, 725),
                "6" => array(22660, 725),
                "7" => array(23385, 725),
                "8" => array(24110, 725),
                "9" => array(24835, 630)
                
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
                "Z" => array(20867, 848),
                "0" => array(21715, 850),
                "1" => array(22565, 850),
                "2" => array(23415, 850),
                "3" => array(24265, 850),//24265
                "4" => array(25115, 850),
                "5" => array(25695, 850),
                "6" => array(26645, 850),
                /*"6" => array(26815, 850),*/
                "7" => array(27665, 850),
                "8" => array(28515, 850),
                "9" => array(29365, 610)
            
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

        }else if($font_size == 13){

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
                "Z" => array(20880, 800),
                "0" => array(21680, 850),
                "1" => array(22530, 850),
                "2" => array(23380, 850),
                "3" => array(24230, 850),
                "4" => array(25080, 850),
                "5" => array(25930, 850),
                "6" => array(26780, 850),
                "7" => array(27630, 850),
                "8" => array(28480, 850),
                "9" => array(29330, 670)
            
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

        }else if($font_size == 14){

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
                "Z" => array(80884, 808),
                "0" => array(21692, 825),
                "1" => array(22517, 825),
                "2" => array(23342, 825),
                "3" => array(24167, 825),
                "4" => array(24992, 825),
                "5" => array(25817, 825),
                "6" => array(26642, 825),
                "7" => array(27467, 825),
                "8" => array(28292, 825),
                "9" => array(29117, 825)
            
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
