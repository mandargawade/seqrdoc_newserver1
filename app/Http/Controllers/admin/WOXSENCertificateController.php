<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\models\TemplateMaster;
use App\models\SuperAdmin;
use Session, TCPDF, TCPDF_FONTS, Auth, DB;
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
use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;
//use Illuminate\Support\Facades\Storage;
use App\Helpers\CoreHelper;
use Helper;
use App\Jobs\ValidateExcelWOXSENSOMJob;
use App\Jobs\ValidateExcelWOXSENJob;
use App\Jobs\ValidateExcelWOXSENSOMsevenJob;
use App\Jobs\PdfGenerateWOXSENSOMJob;
use App\Jobs\PdfGenerateWOXSENJob;
use App\Jobs\PdfGenerateWOXSENSOMsevenJob;

class WOXSENCertificateController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.woxsen.index');
    }

    public function uploadpage()
    {
        return view('admin.woxsen.index');
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
                // dd('hi');
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
                        
                        if(!is_dir($sandbox_directory)){
                
                            mkdir($sandbox_directory, 0777);
                        }

                        $aws_excel = \Storage::disk('s3')->put($subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id.'/'.$excelfile, file_get_contents($fullpath), 'public');
                        $filename1 = \Storage::disk('s3')->url($excelfile);
                        // dd($aws_excel);
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


                if($dropdown_template_id == 1)
                {
                
                    $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                    //**  Load $inputFileName to a Spreadsheet Object
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

                    $objPHPExcel2 = $objReader->load($fullpath);
                    $sheet2 = $objPHPExcel2->getSheet(1);
                    $highestColumn2 = $sheet2->getHighestColumn();
                    $highestRow2 = $sheet2->getHighestDataRow();
                    $rowData2 = $sheet2->rangeToArray('A1:' . $highestColumn2 . $highestRow2, NULL, TRUE, FALSE);

                    $excelData=array('rowData1'=>$rowData1,'rowData2'=>$rowData2,'auth_site_id'=>$auth_site_id,'dropdown_template_id'=>$dropdown_template_id);

                    $response = $this->dispatch(new ValidateExcelWOXSENSOMJob($excelData));

                }elseif($dropdown_template_id == 2){
                    $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                    //**  Load $inputFileName to a Spreadsheet Object
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
                    
                    $response = $this->dispatch(new ValidateExcelWOXSENJob($excelData));
                }
				elseif($dropdown_template_id == 3)
                {
                
                    $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                    //**  Load $inputFileName to a Spreadsheet Object
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

                    $objPHPExcel2 = $objReader->load($fullpath);
                    $sheet2 = $objPHPExcel2->getSheet(1);
                    $highestColumn2 = $sheet2->getHighestColumn();
                    $highestRow2 = $sheet2->getHighestDataRow();
                    $rowData2 = $sheet2->rangeToArray('A1:' . $highestColumn2 . $highestRow2, NULL, TRUE, FALSE);

                    $excelData=array('rowData1'=>$rowData1,'rowData2'=>$rowData2,'auth_site_id'=>$auth_site_id,'dropdown_template_id'=>$dropdown_template_id);

                    $response = $this->dispatch(new ValidateExcelWOXSENSOMsevenJob($excelData));

                }

                $responseData =$response->getData();
                //print_r($responseData);
                if($responseData->success){
                    $old_rows=$responseData->old_rows;
                    $new_rows=$responseData->new_rows;
                }else{
                   return $response;
                }
              
            }

            //echo $fullpath;
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
            
        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows, 'loaderFile'=>$loaderData['fileName'],'loader_token'=>$loaderData['loader_token']]);

        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }
    }

    public function uploadfile(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal){
       // $start_time = microtime(true); 
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        //For custom loader
        $loader_token=$request['loader_token'];

        $template_id = 100;
        $dropdown_template_id = $request['template_id'];
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

                if($dropdown_template_id==1){
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

                    unset($rowData1[0]);
                    unset($rowData2[0]);
                    $rowData1=array_values($rowData1);
                    $rowData2=array_values($rowData2);
                    //store ghost image
                   // $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp/temp');
                    $admin_id = \Auth::guard('admin')->user()->toArray();
     
                    $pdfData=array('studentDataOrg'=>$rowData1,'subjectsDataOrg'=>$rowData2,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token);
                }else if($dropdown_template_id==2){

                    $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
                    /**  Load $inputFileName to a Spreadsheet Object  **/
                    $objPHPExcel1 = $objReader->load($fullpath);
                    $sheet1 = $objPHPExcel1->getSheet(0);
                    $highestColumn1 = $sheet1->getHighestColumn();
                    $highestRow1 = $sheet1->getHighestDataRow();
                    $rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);

                    unset($rowData1[0]);
                    $rowData1=array_values($rowData1);
                    //store ghost image
                   // $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp/temp');
                    $admin_id = \Auth::guard('admin')->user()->toArray();
     
                    $pdfData=array('studentDataOrg'=>$rowData1,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token);
                }elseif($dropdown_template_id==3){
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

                    unset($rowData1[0]);
                    unset($rowData2[0]);
                    $rowData1=array_values($rowData1);
                    $rowData2=array_values($rowData2);
                    //store ghost image
                   // $tmpDir = $this->createTemp(public_path().'/backend/images/ghosttemp/temp');
                    $admin_id = \Auth::guard('admin')->user()->toArray();
     
                    $pdfData=array('studentDataOrg'=>$rowData1,'subjectsDataOrg'=>$rowData2,'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token);
                }
               
            }            
        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        }

            //For Custom Loader
        if($dropdown_template_id==1){
            $link = $this->dispatch(new PdfGenerateWOXSENSOMJob($pdfData));
        }elseif($dropdown_template_id==2){
            $link = $this->dispatch(new PdfGenerateWOXSENJob($pdfData));
        }elseif($dropdown_template_id==3){
            $link = $this->dispatch(new PdfGenerateWOXSENSOMsevenJob($pdfData));
        }
        return response()->json(['success'=>true,'message'=>'Certificates generated successfully.','link'=>$link]);
    }


    public function pdfGenerate1(){

        $domain = \Request::getHost();

        $subdomain = explode('.', $domain);
        $ghostImgArr = array();
        $pdf = new TCPDF('P', 'mm', array('210', '297'), true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        // $pdf->SetCreator('TCPDF');
        $pdf->SetAuthor('TCPDF');
        $pdf->SetTitle('Certificate');
        $pdf->SetSubject('');


        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);

        // add spot colors
        $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10); // For Invisible
        $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo
       
        $Arial = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arial.TTF', 'TrueTypeUnicode', '', 96);
        $ArialB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Arialb.TTF', 'TrueTypeUnicode', '', 96);
        $ariali = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALI.TTF', 'TrueTypeUnicode', '', 96);

        $arialNarrowB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\ARIALNB.TTF', 'TrueTypeUnicode', '', 96);

        $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanB = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanBI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times-New-Roman-Bold-Italic.TTF', 'TrueTypeUnicode', '', 96);
        $timesNewRomanI = TCPDF_FONTS::addTTFfont(public_path().'\\'.$subdomain[0].'\backend\canvas\fonts\Times New Roman_I.TTF', 'TrueTypeUnicode', '', 96);
        
        $pdf->AddPage();

        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\Woxsen University_Degee Certificate_Template.jpg';

        $pdf->Image($template_img_generate, 0, 0, '210', '297', "JPG", '', 'R', true);
        $pdf->setPageMark();

        $fontEBPath = public_path() . '\\' . $subdomain[0] . '\backend\canvas\fonts\E-13B_0.php';
        $pdf->AddFont('E-13B_0', '', $fontEBPath);


        //EN ROLL NO
        $x = 14.5;
        $y = 13;   
        $pdf->SetFont($Arial, '', 10, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Roll No. :", 0, false, 'L');

        //EN ROLL NO
        $x = 29.5;
        $y = 12.5;   
        $pdf->SetFont($ArialB, '', 12, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "001", 0, false, 'L');

        $str="TEST0001";
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
        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);    
         
        //Serial No
        $x = 158.5;
        $y = 12.5;   
        $pdf->SetFont($Arial, '', 12, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Serial No. :", 0, false, 'L');    

        //Serial Number
        $x = 180;
        $y = 16;
        $font_size=10;
        $str = '123456';
        $strArr = str_split($str);

           $x_org=$x;
           $y_org=$y;
           $font_size_org=$font_size;
           $i =0;
           $j=0;
           $y=$y+1.5;
           $z=0;
           foreach ($strArr as $character) {
                        $pdf->SetFont($Arial,0, $font_size, '', false);
                        $pdf->SetXY($x, $y+$z);
                        $j=$j+0.1;
                        $z=$z+0.1;
                        $x=$x+0.4;  
                        $pdf->Cell(0, 0, $character, 0, $ln=0,  'L', 0, '', 0, false, 'B', 'B');
                        
                        $i++;
                        $x=$x+2.2+$j; 
                        $font_size=$font_size+1; 
           }

        $profile_path_org = public_path().'\\'.$subdomain[0].'\backend\canvas\images\Woxsen_University_Transcript.jpg';
        if(file_exists($profile_path_org)) {
            $path_info = pathinfo($profile_path_org);                            
            $file_name = $path_info['filename'];
            $ext = $path_info['extension'];
            $bw_location = public_path()."/".$subdomain[0]."/backend/canvas/images/".$file_name.'_bw.'.$ext;
            
            if(!file_exists($bw_location)){  
                copy($profile_path_org, $bw_location);
            }
        }
        
        if(file_exists($profile_path_org)) {        
            if($ext == 'png'){
                $im = imagecreatefrompng($bw_location);
                if($im && imagefilter($im, IMG_FILTER_GRAYSCALE))
                {          
                    imagepng($im, $bw_location);
                    imagedestroy($im);
                }
                
            }else if($ext == 'jpeg' || $ext == 'jpg'){
                $im = imagecreatefromjpeg($bw_location);       
                if($im && imagefilter($im, IMG_FILTER_GRAYSCALE))
                {          
                    imagejpeg($im, $bw_location);
                    imagedestroy($im);
                }        
            }
            $profilex = 80;
            $profiley = 68;
            $profileWidth = 50;
            $profileHeight = 59;
            $pdf->image($bw_location,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
        }


        //Serial No
        //$x = 20;
        $x = 10;
        $y = 135;   
        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "The Academic Council of Woxsen University", 0, false, 'C');

        $y = 145;   
        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "certifies that", 0, false, 'C');

        $x = 10;
        $y = 155; 
        $nameOrg='Dr Raul V. Rodriguez 001';
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $microlinestr=$str;
        $pdf->SetFont($Arial, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C'); 

        $y = 160;   
        $pdf->SetFont($timesNewRomanB, '', 35, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Dr Raul V. Rodriguez", 0, false, 'C');

        $y = 180;   
        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "has fulfilled all the requirements stipulated by", 0, false, 'C');

        $y = 190;   
        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "the Examination Board and has been", 0, false, 'C');

        $y = 200;   
        $pdf->SetFont($timesNewRomanI, '', 25, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "awarded the degree of", 0, false, 'C');

        $y = 215;   
        $pdf->SetFont($timesNewRomanB, '', 30, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Post Graduate", 0, false, 'C');

        $y = 225;   
        $pdf->SetFont($timesNewRomanB, '', 30, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Program for Experienced Professionals", 0, false, 'C');

        $y = 237; 
        $nameOrg='Post Graduate Program for Experienced Professionals';
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $microlinestr=$str;
        $pdf->SetFont($Arial, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

        //left size bottom cornor
        $x = 18;
        $y = 263.5; 
        $nameOrg='Woxsen University Woxsen University Woxsen University Woxsen University Woxsen University Woxsen University';
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $microlinestr=$str;
        $pdf->SetFont($Arial, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'L');

        $x = 32;
        $y = 264;   
        $pdf->SetFont($timesNewRomanB, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Dean", 0, false, 'L');

        $x = 17;
        $y = 270;   
        $pdf->SetFont($timesNewRoman, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "School of Business", 0, false, 'L');

        $ghost_font_size = '13';
        $ghostImagex = 14;
        $ghostImagey = 277;
        $ghostImageWidth = 55;//68
        $ghostImageHeight = 8;
        $name = substr(str_replace(' ','',strtoupper($nameOrg)), 0, 6);
        $tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $w = $this->CreateMessage($tmpDir, $name ,$ghost_font_size,'');
        $pdf->Image("$tmpDir/" . $name."".$ghost_font_size.".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);

        //right
        $x = 135;
        $y = 263.5; 
        $nameOrg='Woxsen University Woxsen University Woxsen University Woxsen University Woxsen University Woxsen University';
        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $microlinestr=$str;
        $pdf->SetFont($Arial, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'C');

        $x = 147;
        $y = 264;   
        $pdf->SetFont($timesNewRomanB, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Vice-Chancellor", 0, false, 'L');

        $x = 144.5;
        $y = 270;   
        $pdf->SetFont($timesNewRoman, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Woxen University", 0, false, 'L');


        $x = 90;
        $y = 272;   
        $pdf->SetFont($timesNewRomanB, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "Hyderabad,", 0, false, 'L');

        $x = 85;
        $y = 278;   
        $pdf->SetFont($timesNewRoman, '', 17, '', false);
        $pdf->SetXY($x, $y);
        $pdf->Cell(0, 0, "December, 2021", 0, false, 'L');


        
        $pdf->Output('sample.pdf', 'I');
        //delete temp dir 26-04-2022 
        CoreHelper::rrmdir($tmpDir);
    }

    public function pdfGenerate()
    {

        $domain = \Request::getHost();

        $subdomain = explode('.', $domain);
        $ghostImgArr = array();
        $pdf = new TCPDF('L', 'mm', array(
            '297',
            '420'
        ) , true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        // $pdf->SetCreator('TCPDF');
        $pdf->SetAuthor('TCPDF');
        $pdf->SetTitle('Certificate');
        $pdf->SetSubject('');


        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);


        // add spot colors
        $pdf->AddSpotColor('Spot Red', 30, 100, 90, 10); // For Invisible
        $pdf->AddSpotColor('Spot Dark Green', 100, 50, 80, 45); // clear text on bottom red and in clear text logo
       
        $timesNewRoman = TCPDF_FONTS::addTTFfont(public_path() . '\\' . $subdomain[0] . '\backend\canvas\fonts\Times-New-Roman.TTF', 'TrueTypeUnicode', '', 96);

        $micrb10 = TCPDF_FONTS::addTTFfont(public_path() . '\\' . $subdomain[0] . '\backend\canvas\fonts\micrb10.TTF', 'TrueTypeUnicode', '', 96);
 
        $pdf->AddPage();

        //set background image
        $template_img_generate = public_path().'\\'.$subdomain[0].'\backend\canvas\bg_images\Woxsen_University_Transcript_BG.jpg';

        //dd($template_img_generate);
        
        $pdf->Image($template_img_generate, 0, 0, '420', '297', "JPG", '', 'R', true);
        $pdf->setPageMark();


        //Profile picture
        //$profile_path_org = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$studentData[$photo_col];
        $profile_path_org = public_path().'\\'.$subdomain[0].'\backend\canvas\images\Woxsen_University_Transcript.jpg';
        if(file_exists($profile_path_org)) {
            $path_info = pathinfo($profile_path_org);                            
            $file_name = $path_info['filename'];
            $ext = $path_info['extension'];
            $bw_location = public_path()."/".$subdomain[0]."/backend/canvas/images/".$file_name.'_bw.'.$ext;
            
            if(!file_exists($bw_location)){  
                copy($profile_path_org, $bw_location);
            }
        }
        
        if(file_exists($profile_path_org)) {        
            if($ext == 'png'){
                $im = imagecreatefrompng($bw_location);
                if($im && imagefilter($im, IMG_FILTER_GRAYSCALE))
                {          
                    imagepng($im, $bw_location);
                    imagedestroy($im);
                }
                
            }else if($ext == 'jpeg' || $ext == 'jpg'){
                $im = imagecreatefromjpeg($bw_location);       
                if($im && imagefilter($im, IMG_FILTER_GRAYSCALE))
                {          
                    imagejpeg($im, $bw_location);
                    imagedestroy($im);
                }        
            }
            $profilex = 12;
            $profiley = 12;
            $profileWidth = 28;
            $profileHeight = 31;
            $pdf->image($bw_location,$profilex,$profiley,$profileWidth,$profileHeight,"",'','L',true,3600);
        }
    

        $str="TEST0001";
        $codeContents =$encryptedString = strtoupper(md5($str));
        $qr_code_path = public_path().'\\'.$subdomain[0].'\backend\canvas\images\qr\/'.$encryptedString.'.png';
        $qrCodex = 170;
        $qrCodey = 15;
        $qrCodeWidth =20;
        $qrCodeHeight = 20;
        \QrCode::size(75.6)
            ->backgroundColor(255, 255, 0)
            ->format('png')
            ->generate($codeContents, $qr_code_path);

        $pdf->Image($qr_code_path, $qrCodex, $qrCodey, $qrCodeWidth,  $qrCodeHeight, '', '', '', false, 600);
        
        $nameOrg = "Bhupathiraju Venkata Surya Naga Manoj Varma";

        $str = $nameOrg;
        $str = strtoupper(preg_replace('/\s+/', '', $str)); //added by Mandar
        $microlinestr=$str;
        $pdf->SetFont($timesNewRoman, '', 2, '', false);
        $pdf->SetTextColor(0, 0, 0);
        //$pdf->StartTransform();
        $pdf->SetXY(170, 38);
        $pdf->Cell(0, 0, $microlinestr, 0, false, 'L');


        $fontEBPath = public_path() . '\\' . $subdomain[0] . '\backend\canvas\fonts\E-13B_0.php';
        $pdf->AddFont('E-13B_0', '', $fontEBPath);
        $card_serial_nox = 176;
        $card_serial_noy = 40;
        $pdf->SetFont('E-13B_0', '', 12, '', false);
        $pdf->SetXY($card_serial_nox, $card_serial_noy);
        $pdf->Cell(0, 0, '012345', 0, false, 'L');


        $fontEBPath = public_path() . '\\' . $subdomain[0] . '\backend\canvas\fonts\E-13B_0.php';
        $pdf->AddFont('E-13B_0', '', $fontEBPath);
        $card_serial_nox = 389;
        $card_serial_noy = 40;
        $pdf->SetFont('E-13B_0', '', 12, '', false);
        $pdf->SetXY($card_serial_nox, $card_serial_noy);
        $pdf->Cell(0, 0, '012345', 0, false, 'L');
          
        
        $pdf->SetFont($timesNewRoman, '', 9, '', false);
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
<table id="table1" cellspacing="0" cellpadding="3" rules="rows" border="0.1" width="47%">
  <tr>
    <td width="70%">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Student Name: <b>Bhupathiraju Venkata Surya Naga Manoj Varma </b></td>
    <td width="30%">Father Name: <b>B. Venkatapati Raju</b></td>
   </tr>
   <tr>
    <td width="70%">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Program: <b>Post Graduate Programme for Experienced Professionals (PGPXP)</b></td>
    <td width="30%">Academic Year: <b>2020 - 21</b></td>
   </tr>
   <tr>
    <td width="70%">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PGID No.: <b>2003001</b></td>
    <td width="30%">Admission ID: <b>Wou-2020-2003001</b></td>
   </tr>
  </table>
EOD;
        $pdf->writeHTMLCell($w = 0, $h = 0, $x = '11', $y = '50', $html, $border = 0, $ln = 1, $fill = 0, $reseth = true, $align = '', $autopadding = true);

        
    $strtd = "";
    
        
    $strtd .= '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="6" style="text-align:center;border-right:0px solid black;"><B>Term - 01</B></td>
                  </tr>';
    
    $Course_array = array('','Business Research Methods','Business Strategy','Organizational Behaviour','Marketing Mangement','Accounting for Managers','Business Statistics','Managerial (Mico) Economics','Human Resources Management','Corporate Finace','Opersations Management','Strategic Product Management','Integrated Marketing Communications','Macro Economics','Wealth Management','Machine Learning,Artifical Intelligence 1','Digital Marketing','Strategic HRM and Leadership','Cost and Managerial Accounting','Brand Management','Retail Management');
    
    //spaces
    for ($i = 1;$i < 8;$i++)
    {
        
            $strtd .= '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B>00'.$i.'</B></td>
                                <td style="widtd:45%;text-align:center;"><B>'.$Course_array[$i].'</B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B>3</B></td>
                                <td style="widtd:6%;text-align:center;"><B>A</B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B></B></td>
                       </tr>';

    }

    $strtd .=   '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B></B></td>
                </tr>';

    $strtd .=   '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                                <td style="widtd:45%;text-align:center;"><B></B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B></B></td>
                                <td style="widtd:6%;text-align:center;"><B></B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>GPA - 01 : 3.05</B></td>
                </tr>';                   

    $strtd .= '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B>Term - 02</B></td>
                </tr>';
             

    for ($i = 8;$i < 13;$i++)
    {
        
            $strtd .= '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B>00'.$i.'</B></td>
                                <td style="widtd:45%;text-align:center;"><B>'.$Course_array[$i].'</B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B>3</B></td>
                                <td style="widtd:6%;text-align:center;"><B>A</B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B></B></td>
                       </tr>';

    }

    $strtd .=   '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B></B></td>
                </tr>';

    $strtd .= '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>GPA - 02 : 3.05</B></td>
               </tr>';

    $strtd .= '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>CGPA - 02 : 3.05</B></td>
               </tr>';

    $strtd .= '<tr>
                    <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                    <td colspan="5" style="text-align:center;border-right:0px solid black;"><B>Term - 03</B></td>
               </tr>';

    
    for ($i = 13;$i < 17;$i++)
    {
        
            $strtd .= '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B>00'.$i.'</B></td>
                                <td style="widtd:45%;text-align:center;"><B>'.$Course_array[$i].'</B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B>3</B></td>
                                <td style="widtd:6%;text-align:center;"><B>A</B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B></B></td>
                       </tr>';

    }

    $strtd .=   '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B></B></td>
                </tr>';

    $strtd .= '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>GPA - 03 : 3.05</B></td>
               </tr>';

    $strtd .= '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>CGPA - 03 : 3.05</B></td>
               </tr>';

    $strtd .= '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B>Term - 04</B></td>
                  </tr>';

     for ($i = 18;$i < 22;$i++)
    {
        
            $strtd .= '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B>00'.$i.'</B></td>
                                <td style="widtd:45%;text-align:center;"><B>'.$Course_array[$i].'</B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B>3</B></td>
                                <td style="widtd:6%;text-align:center;"><B>A</B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B></B></td>
                       </tr>';

    }
    
    $strtd .=   '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B></B></td>
                </tr>';

    $strtd .=  '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>GPA - 04 : 3.05</B></td>
                </tr>';

    $strtd .=   '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:13%;text-align:center;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:7%;text-align:center;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:6%;text-align:center;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-bottom:0px solid black;border-right:0px solid black;"><B>CGPA - 04 : 3.05</B></td>
                </tr>';                                                                                    
                                          
$html = <<<EOD
<style>
td{
    font-size:9px;
}
#table2 {
    border-collapse: collapse;
}

</style>
<table id="table2" class="t-table borderspace" cellspacing="0" cellpadding="2" width="47%">
  <tr>
    <th style="width:10%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;border-right:0px solid black;border-left:0px solid black;"><B>Course Code</B></th>
    <th style="width:45%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;"><B>Course Name</B></th>
    <th style="width:13%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;"><B></B></th>
    <th style="width:7%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;"><B>Credits</B></th>
    <th style="width:6%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;"><B>Grade</B></th>
    <th style="width:19%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;border-right:0px solid black;"><B></B></th>
   </tr>
   {$strtd} 
  </table>
EOD;
        $pdf->writeHTMLCell($w = 0, $h = 0, $x = '11', $y = '71', $html, $border = 0, $ln = 1, $fill = 0, $reseth = true, $align = '', $autopadding = true);

    $strtd = "";
    
        
    $strtd .= '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="6" style="text-align:center;border-right:0px solid black;"><B>Term - 05</B></td>
                  </tr>';
    
    $Course_array = array('','Business Research Methods','Business Strategy','Organizational Behaviour','Marketing Mangement','Accounting for Managers','Business Statistics','Managerial (Mico) Economics','Human Resources Management','Corporate Finace','Opersations Management','Strategic Product Management','Integrated Marketing Communications','Macro Economics','Wealth Management','Machine Learning,Artifical Intelligence 1','Digital Marketing','Strategic HRM and Leadership','Cost and Managerial Accounting','Brand Management','Retail Management');
    
    //spaces
    for ($i = 1;$i < 8;$i++)
    {
        
            $strtd .= '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B>00'.$i.'</B></td>
                                <td style="widtd:45%;text-align:center;"><B>'.$Course_array[$i].'</B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B>3</B></td>
                                <td style="widtd:6%;text-align:center;"><B>A</B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B></B></td>
                       </tr>';

    }

    $strtd .=   '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B></B></td>
                </tr>';

    $strtd .=   '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                                <td style="widtd:45%;text-align:center;"><B></B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B></B></td>
                                <td style="widtd:6%;text-align:center;"><B></B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>GPA - 01 : 3.05</B></td>
                </tr>';                   

    $strtd .= '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B>Term - 06</B></td>
                </tr>';
             

    for ($i = 8;$i < 13;$i++)
    {
        
            $strtd .= '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B>00'.$i.'</B></td>
                                <td style="widtd:45%;text-align:center;"><B>'.$Course_array[$i].'</B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B>3</B></td>
                                <td style="widtd:6%;text-align:center;"><B>A</B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B></B></td>
                       </tr>';

    }

    $strtd .=   '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B></B></td>
                </tr>';

    $strtd .= '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>GPA - 02 : 3.05</B></td>
               </tr>';

    $strtd .= '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>CGPA - 02 : 3.05</B></td>
               </tr>';

    $strtd .= '<tr>
                    <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                    <td colspan="5" style="text-align:center;border-right:0px solid black;"><B>Term - 07</B></td>
               </tr>';

    
    for ($i = 13;$i < 17;$i++)
    {
        
            $strtd .= '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B>00'.$i.'</B></td>
                                <td style="widtd:45%;text-align:center;"><B>'.$Course_array[$i].'</B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B>3</B></td>
                                <td style="widtd:6%;text-align:center;"><B>A</B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B></B></td>
                       </tr>';

    }

    $strtd .=   '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B></B></td>
                </tr>';

    $strtd .= '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>GPA - 03 : 3.05</B></td>
               </tr>';

    $strtd .= '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>CGPA - 03 : 3.05</B></td>
               </tr>';

    $strtd .= '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B>Term - 08</B></td>
                  </tr>';

     for ($i = 18;$i < 22;$i++)
    {
        
            $strtd .= '<tr>
                                <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B>00'.$i.'</B></td>
                                <td style="widtd:45%;text-align:center;"><B>'.$Course_array[$i].'</B></td>
                                <td style="widtd:13%;text-align:center;"><B></B></td>
                                <td style="widtd:7%;text-align:center;"><B>3</B></td>
                                <td style="widtd:6%;text-align:center;"><B>A</B></td>
                                <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B></B></td>
                       </tr>';

    }
    
    $strtd .=   '<tr>
                        <td style="text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td colspan="5" style="text-align:center;border-right:0px solid black;"><B></B></td>
                </tr>';

    $strtd .=  '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;"><B></B></td>
                        <td style="widtd:13%;text-align:center;"><B></B></td>
                        <td style="widtd:7%;text-align:center;"><B></B></td>
                        <td style="widtd:6%;text-align:center;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-right:0px solid black;"><B>GPA - 04 : 3.05</B></td>
                </tr>';

    $strtd .=   '<tr>
                        <td style="widtd:10%;text-align:center;border-right:0px solid black;border-left:0px solid black;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:13%;text-align:center;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:7%;text-align:center;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:6%;text-align:center;border-bottom:0px solid black;"><B></B></td>
                        <td style="widtd:19%;text-align:center;border-bottom:0px solid black;border-right:0px solid black;"><B>CGPA - 04 : 3.05</B></td>
                </tr>';  


       $strtd .= '<tr>
                        <td style="widtd:10%;text-align:center;border-left: 0px solid black;border-right: 0px solid black;border-bottom: 0px solid black;"><B></B></td>
                        <td style="widtd:45%;text-align:center;border-bottom: 0px solid black;"><B>Total Credit Score: 102</B></td>
                        <td colspan="4" style="widtd:13%;text-align:center;border-bottom: 0px solid black;border-right: 0px solid black;"><B></B></td>
                 </tr>';  

        $html = <<<EOD
<style>
td{
    font-size:9px;
}
#table3 {
    border-collapse: collapse;
}

</style>
<table id="table3" cellspacing="0" cellpadding="2" width="98.5%">
  <tr>
    <th style="width:10%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;border-right:0px solid black;border-left:0px solid black;"><B>Course Code</B></th>
    <th style="width:45%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;"><B>Course Name</B></th>
    <th style="width:13%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;"><B></B></th>
    <th style="width:7%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;"><B>Credits</B></th>
    <th style="width:6%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;"><B>Grade</B></th>
    <th style="width:19%;text-align:center;border-top:0px solid black;border-bottom:0px solid black;border-right:0px solid black;"><B></B></th>
   </tr>
    {$strtd}
  </table>
EOD;
        $pdf->writeHTMLCell($w = 0, $h = 0, $x = '222', $y = '50', $html, $border = 0, $ln = 1, $fill = 0, $reseth = true, $align = '', $autopadding = true);

       
        $nameOrg = "Bhupathiraju Venkata Surya Naga Manoj Varma";
        // Ghost image
        $ghost_font_size = '13';
        $ghostImagex = 17;
        $ghostImagey = 275;
        $ghostImageWidth = 55; //68
        $ghostImageHeight = 9.8;
        $name = substr(str_replace(' ', '', strtoupper($nameOrg)) , 0, 6);
        // dd($name);
        $tmpDir = $this->createTemp(public_path() . '/backend/images/ghosttemp/temp');
        
        $w = $this->CreateMessage($tmpDir, $name, $ghost_font_size, '');
        
        $pdf->Image("$tmpDir/" . $name . "" . $ghost_font_size . ".png", $ghostImagex, $ghostImagey, $ghostImageWidth, $ghostImageHeight, "PNG", '', 'L', true, 3600);
        

        $pdf->Output('sample.pdf', 'I');
        //delete temp dir 26-04-2022 
            CoreHelper::rrmdir($tmpDir);
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

