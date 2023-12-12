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
use App\models\ExcelUploadHistory;
use App\models\SbExceUploadHistory;

use App\Helpers\CoreHelper;
use Helper;
use App\Jobs\ValidateExcelSdmJob;
use App\Jobs\PdfGenerateSdmResitJob;
use App\Jobs\PdfGenerateSdmPdcJob;
use App\Jobs\PdfGenerateSdmDentalPdcJob;
use App\Jobs\PdfGenerateSdmBdsResitJob;
use App\Jobs\PdfGenerateSdmBptResitJob;
use App\Jobs\PdfGenerateSdmResitMptIJob;
use App\Jobs\PdfGenerateSdmNursingResitJob;
use App\Jobs\PdfGenerateSdmMbbsJob;
use App\Jobs\PdfGenerateSdmMbbsIIIJob;
use App\Jobs\PdfGenerateSdmMbbsIIIPartIIJob;
use App\Jobs\PdfGenerateSdmMscResitJob;
use App\Jobs\PdfGenerateSdmBscResitJob;
use App\Jobs\PdfGenerateSdmMscIIIResitJob;
use App\Jobs\pdfGenerateSdmBscNursingResitJob;
use App\Jobs\pdfGenerateSdmBscNursingIJob;
use App\Jobs\PdfGenerateSdmMbbsResitJob;
use App\Jobs\PdfGenerateSdmMbbsIIIResitJob;
use App\Jobs\PdfGenerateSdmMbbsIIIPartIIResitJob;
use App\Jobs\PdfGenerateSdmMdsIResitJob;
use App\Jobs\PdfGenerateSdmMdsIIResitJob;
class SdmResitController extends Controller
{
    public function index(Request $request)
    {
       return view('admin.sdm.index');
    }

    public function uploadpage(){
        return view('admin.sdm.index-resit');
    }

    public function pdfGenerate(){
        $domain = \Request::getHost();        
        $subdomain = explode('.', $domain);
		$excelfile =  'BDS_sample.xlsx';
		$target_path = public_path().'\\'.$subdomain[0].'\backend\sample_excel';
		$fullpath = $target_path.'/'.$excelfile;		
		$inputFileType = 'Xlsx';	
		$objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
		/**  Load $inputFileName to a Spreadsheet Object  **/
		$objPHPExcel1 = $objReader->load($fullpath);
		$worksheet = $objPHPExcel1->getSheet(0);
		$highestRow = $worksheet->getHighestRow(); 
		$highestColumn = $worksheet->getHighestColumn(); 
		$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); 
		$course=array();    
		$ctr_subject=0;
		for ($row = 1; $row <= 1; $row++) {
			for ($col = 1; $col <= $highestColumnIndex; ++$col) {
				$value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
				if($value){ 
					if (str_contains($value, 'Subject')) { 
						$ctr_subject++;
					}                            
				}
			}                   
		}				
		$course_counts = $ctr_subject;             
		$highestColumnSet=($course_counts+1)*22;
		$last_col=5+($course_counts*23);						
		$check_last_col=5+($course_counts-1)*22;						
		//print_r($highestColumnSet.'|'.$last_col); 		
		$records=array();
		$r=1;
		$subject=1;
		//extract cell values from row 2
		for ($row = 2; $row <= $highestRow; $row++) {
			$value=array();
			$c=1;
			$vc=6;
			$cal=7; 
			for ($col = 1; $col <= $highestColumnIndex; ++$col) {
				//if($col==$vc+2 || $col==$vc+12){
				if($c>=$vc && $vc != 22){ 	
					$th=$worksheet->getCellByColumnAndRow($vc+2, $row)->getValue();
					$pr=$worksheet->getCellByColumnAndRow($vc+12, $row)->getValue();
					/*if ($th != '') { 
						$value["th".$cal] = "TH";
					}else{
						$value["th".$cal] = "";
					}
					if ($pr!='') { 
						$value["pr".$cal] = "PR";
					}else{
						$value["pr".$cal] = "";
					}*/
					if ($th != '' && $pr != '') {
						$value["thpr".$cal] = "TH|PR";
					}
					if ($th != '' && $pr == '') {
						$value["thpr".$cal] = "TH";
					}
					if ($th == '' && $pr != '') {
						$value["thpr".$cal] = "PR";
					}
					if($vc==($check_last_col+1)){ 
						$vc=0;
					} 
					$vc+=22;
				}
				
				$value[$c] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
				$cal+=22;
				$c++;   
			}   
			$records[$r]=array_values($value);
			$r++;    
		}		
		echo "<pre>"; print_r($records); 
		$rowData1=array_values($records); 
        $GT_Max_key=$last_col;
        $GT_Min_key=$last_col+1;
        $GT_Sec_key=$last_col+2;
        $Grand_Total_In_words_key=$last_col+3;
        $Percentage_key=$last_col+4;
        $Result_key=$last_col+5;
        $Date_key=$last_col+6;
		$Student_image_key=$last_col+7;
        $Batch_of_key=$last_col+8;
        $Aadhar_No_key=$last_col+9;
        $DOB_key=$last_col+10;
        $course_key=$last_col+11;
		$subj_col = 23;
        $subj_start = 5; 
		$subj_end = $subj_col*$course_counts; 
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
                $response = $this->dispatch(new ValidateExcelSdmJob($excelData));
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
                if($dropdown_template_id != "3" && $dropdown_template_id != "5" && $dropdown_template_id != "7" && $dropdown_template_id != "11" && $dropdown_template_id != "12" && $dropdown_template_id != "13" && $dropdown_template_id != "16" && $dropdown_template_id != "18"){
					$objPHPExcel1 = $objReader->load($fullpath);
					$sheet1 = $objPHPExcel1->getSheet(0);
					$highestColumn1 = $sheet1->getHighestColumn();
					$highestRow1 = $sheet1->getHighestDataRow();
					$rowData1 = $sheet1->rangeToArray('A1:' . $highestColumn1 . $highestRow1, NULL, TRUE, FALSE);
					unset($rowData1[0]);
				}else{
					$objPHPExcel1 = $objReader->load($fullpath);
					$worksheet = $objPHPExcel1->getSheet(0);
					$highestRow = $worksheet->getHighestRow(); 
					$highestColumn = $worksheet->getHighestColumn(); 
					$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); 
					$course=array();    
					$ctr_subject=0;
					for ($row = 1; $row <= 1; $row++) {
						for ($col = 1; $col <= $highestColumnIndex; ++$col) {
							$value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
							if($value){ 
								if (str_contains(strtolower($value), 'subject')) { 
									$ctr_subject++;
								}                            
							}
						}                   
					}				
					$course_counts = $ctr_subject;             
					
					if($dropdown_template_id != "7" && $dropdown_template_id != "11" && $dropdown_template_id != "13" && $dropdown_template_id != "12"){
						//3,5,16 template
						if($dropdown_template_id==16 || $dropdown_template_id==18){
                            // $subjCols=19;  
                            $highestColumnSet=($course_counts+1)*19;
                            $last_col=6+($course_counts*20);						
                            $check_last_col=6+($course_counts-1)*19; 
                            $subjStart=6; 
                        }else{
                            $highestColumnSet=($course_counts+1)*22;
                            $last_col=5+($course_counts*23);						
                            $check_last_col=5+($course_counts-1)*22;	
                            $subjStart=5;
                        }	
						$records=array();
						$r=1;
						$subject=1;
						//extract cell values from row 2
						for ($row = 2; $row <= $highestRow; $row++) {
							$value=array();
							if($dropdown_template_id==16 || $dropdown_template_id==18){
                                $c=1;
                                $vc=7;
                                $cal=8;   
                                $vcc=19;  
                            }else{
                                $c=1;
                                $vc=6;
                                $cal=7; 
                                $vcc=22;  
                            } 
							for ($col = 1; $col <= $highestColumnIndex; ++$col) {
								if($c>=$vc && $vc != $vcc){ 	
									$th=$worksheet->getCellByColumnAndRow($vc+2, $row)->getValue();
									$pr=$worksheet->getCellByColumnAndRow($vc+12, $row)->getValue();
									if ($th != '' && $pr != '') {
										$value["thpr".$cal] = "TH|PR";
									}
									if ($th != '' && $pr == '') {
										$value["thpr".$cal] = "TH";
									}
									if ($th == '' && $pr != '') {
										$value["thpr".$cal] = "PR";
									}
									if($vc==($check_last_col+1)){ 
										$vc=0;
									} 
									if($dropdown_template_id==16 || $dropdown_template_id==18){
                                        $vc+=$vcc;
                                    }else{
                                        $vc+=$vcc;
                                    }
								}
								
								$value[$c] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
								$cal+=$vcc;
								$c++;   
							}   
							$records[$r]=array_values($value);
							$r++;    
						}		
						$rowData1=array_values($records); 
						if($dropdown_template_id==16 || $dropdown_template_id==18){
                            $sgpa=$last_col;
                            $cgpa=$last_col+1;
                            $class_grade=$last_col+2;
                            // $Grand_Total_In_words_key=$last_col+3;
                            // $Percentage_key=$last_col+4;

                            // $Result_key=$last_col+5;
                            $Date_key=$last_col+3;
                            $Student_image_key=$last_col+4;
                            $Batch_of_key=$last_col+5;
                            $Aadhar_No_key=$last_col+6;
                            $DOB_key=$last_col+7;
                            $course_key=$last_col+8;
                            $subjCols = $subj_col =20;  
                            $subj_start = $subjStart; 
                            $subj_end = $subj_col*$course_counts;
                        } else{
                            $GT_Max_key=$last_col;
                            $GT_Min_key=$last_col+1;
                            $GT_Sec_key=$last_col+2;
                            $Grand_Total_In_words_key=$last_col+3;
                            $Percentage_key=$last_col+4;
                            $Result_key=$last_col+5;
                            $Date_key=$last_col+6;
                            $Student_image_key=$last_col+7;
                            $Batch_of_key=$last_col+8;
                            $Aadhar_No_key=$last_col+9;
                            $DOB_key=$last_col+10;
                            $course_key=$last_col+11;
                            $subj_col = 23;
                            $subj_start = $subjStart; 
                            $subj_end = $subj_col*$course_counts;
                        }						
					}else{
                    
                        //7,11,12,13
						if($dropdown_template_id==7){
                            $subjCols=12;
                        }
                        if($dropdown_template_id==11){
                            $subjCols=17;    
                        }
                        if($dropdown_template_id==13){
                            $subjCols=17;    
                        }
                        if($dropdown_template_id==12){
                            $subjCols=14;    
                        }
                        
                        $highestColumnSet=($course_counts+1)*$subjCols;
						$last_col=6+($course_counts*$subjCols);						
						$check_last_col=6+($course_counts-1)*$subjCols;						
						$records=array();
						$r=1;
						$subject=1;
						//extract cell values from row 2
						for ($row = 2; $row <= $highestRow; $row++) {
							$value=array();
							$c=1;
							$vc=6;
							$cal=7; 
							for ($col = 1; $col <= $highestColumnIndex; ++$col) {
								$value[$c] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
								$cal+=$subjCols;
								$c++;   
							}   
							$records[$r]=array_values($value);
							$r++;    
						}		
						$rowData1=array_values($records); 
						if($dropdown_template_id==11  || $dropdown_template_id==13){
                            $T_CREDITS_CR = $last_col;
                            $T_CREDITS_POINT = $last_col+1;	
                            $SEMESTER_CR = $last_col+2;	
                            $CUMULATIVE_CR = $last_col+3;	
                            $SGPA = $last_col+4;	
                            $CGPA = $last_col+5;	
                            $PERCENTAGE = $last_col+6;	
                            $CGPA_GRADE = $last_col+7;	
                            $PERCENTAGE_GRADE = $last_col+8;
                            $Result_key = $last_col+9;
                            $Date_key=$last_col+10;
                            $Student_image_key=$last_col+11;
                            $Batch_of_key=$last_col+12;
                            $Aadhar_No_key=$last_col+13;
                            $DOB_key=$last_col+14;
                            $course_key=$last_col+15;    
                        }else{
                            $GT_Max_key=$last_col;
                            $GT_Min_key=$last_col+1;
                            $GT_Sec_key=$last_col+2;
                            $Grand_Total_In_words_key=$last_col+3;
                            $Percentage_key=$last_col+4;
                            $Result_key=$last_col+5;
                            $Date_key=$last_col+6;
                            $Student_image_key=$last_col+7;
                            $Batch_of_key=$last_col+8;
                            $Aadhar_No_key=$last_col+9;
                            $DOB_key=$last_col+10;
                            $course_key=$last_col+11;    
                        }
                        $subj_col = $subjCols;
						$subj_start = 6; 
						$subj_end = $subj_col*$course_counts;	
					}	
				}
            }                                   
        }
        else{
            return response()->json(['success'=>false,'message'=>'File not found!']);
        } 
      
        //store ghost image 
        //$tmpDir = $this->createTemp(public_path().'\backend\images\ghosttemp\temp');
        $admin_id = \Auth::guard('admin')->user()->toArray();
		
		if($dropdown_template_id != "3" && $dropdown_template_id != "5" && $dropdown_template_id != "7" && $dropdown_template_id != "11" && $dropdown_template_id != "12" && $dropdown_template_id != "13" && $dropdown_template_id != "16" && $dropdown_template_id != "18"){
			$pdfData=array('studentDataOrg'=>$rowData1, 'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token); //For Custom Loader
        }else{
			if($dropdown_template_id==11 || $dropdown_template_id==13){
                $pdfData=array(
                    'studentDataOrg'=>$rowData1,
                    'T_CREDITS_CR' =>$T_CREDITS_CR,               
                    'T_CREDITS_POINT' =>$T_CREDITS_POINT,	
                    'SEMESTER_CR' =>$SEMESTER_CR,	
                    'CUMULATIVE_CR' =>$CUMULATIVE_CR,	
                    'SGPA' =>$SGPA,	
                    'CGPA' =>$CGPA,	
                    'PERCENTAGE' =>$PERCENTAGE,	
                    'CGPA_GRADE' =>$CGPA_GRADE,	
                    'PERCENTAGE_GRADE' =>$PERCENTAGE_GRADE,
                    'Result_key'=>$Result_key, 'Date_key'=>$Date_key,
                    'Student_image_key'=>$Student_image_key, 'Batch_of_key'=>$Batch_of_key, 'Aadhar_No_key'=>$Aadhar_No_key, 'DOB_key'=>$DOB_key, 'course_key'=>$course_key, 'subj_col'=>$subj_col, 'subj_start'=>$subj_start, 'subj_end'=>$subj_end, 'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token);
            }else if($dropdown_template_id==16 || $dropdown_template_id==18){
                $pdfData=array(
                    'studentDataOrg'=>$rowData1,
                    'SGPA' =>$sgpa,	
                    'CGPA' =>$cgpa,	
                    'class_grade' => $class_grade,
                    'Date_key'=>$Date_key,
                    'Student_image_key'=>$Student_image_key, 
                    'Batch_of_key'=>$Batch_of_key, 
                    'Aadhar_No_key'=>$Aadhar_No_key, 
                    'DOB_key'=>$DOB_key, 
                    'course_key'=>$course_key, 
                    'subj_col'=>$subjCols, 
                    'subj_start'=>$subj_start, 
                    'subj_end'=>$subj_end, 
                    'auth_site_id'=>$auth_site_id,
                    'template_id'=>$template_id,
                    'dropdown_template_id'=>$dropdown_template_id,
                    'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,
                    'loader_token'=>$loader_token
                );
            }
            else{
                $pdfData=array('studentDataOrg'=>$rowData1, 'GT_Max_key'=>$GT_Max_key, 'GT_Min_key'=>$GT_Min_key, 'GT_Sec_key'=>$GT_Sec_key, 'Grand_Total_In_words_key'=>$Grand_Total_In_words_key, 'Percentage_key'=>$Percentage_key, 'Result_key'=>$Result_key, 'Date_key'=>$Date_key, 'Student_image_key'=>$Student_image_key, 'Batch_of_key'=>$Batch_of_key, 'Aadhar_No_key'=>$Aadhar_No_key, 'DOB_key'=>$DOB_key, 'course_key'=>$course_key, 'subj_col'=>$subj_col, 'subj_start'=>$subj_start, 'subj_end'=>$subj_end, 'auth_site_id'=>$auth_site_id,'template_id'=>$template_id,'dropdown_template_id'=>$dropdown_template_id,'previewPdf'=>$previewPdf,'excelfile'=>$excelfile,'loader_token'=>$loader_token); //For Custom Loader
            }
        }
        // print_r($pdfData);
        // exit;
		if($dropdown_template_id==1){
            $link = $this->dispatch(new PdfGenerateSdmResitJob($pdfData));
        }
        elseif($dropdown_template_id==2){
            $link = $this->dispatch(new PdfGenerateSdmPdcJob($pdfData));
        }
		elseif($dropdown_template_id==3){
            $link = $this->dispatch(new PdfGenerateSdmBdsResitJob($pdfData));
        }
		elseif($dropdown_template_id==4){
            $link = $this->dispatch(new PdfGenerateSdmDentalPdcJob($pdfData));
        }
		elseif($dropdown_template_id==5){
            $link = $this->dispatch(new PdfGenerateSdmBptResitJob($pdfData));
        }	
		elseif($dropdown_template_id==6){
            $link = $this->dispatch(new PdfGenerateSdmResitMptIJob($pdfData));
        }
		elseif($dropdown_template_id==7){
            $link = $this->dispatch(new PdfGenerateSdmNursingResitJob($pdfData));
        } 
		elseif($dropdown_template_id==8){
            $link = $this->dispatch(new PdfGenerateSdmMbbsJob($pdfData));
        }
		elseif($dropdown_template_id==9){
            $link = $this->dispatch(new PdfGenerateSdmMbbsIIIJob($pdfData));
        } 
		elseif($dropdown_template_id==10){
            $link = $this->dispatch(new PdfGenerateSdmMbbsIIIPartIIJob($pdfData));
        } 
        elseif($dropdown_template_id==11){
            $link = $this->dispatch(new PdfGenerateSdmMscResitJob($pdfData));
        } 
		elseif($dropdown_template_id==12){
            $link = $this->dispatch(new PdfGenerateSdmBscResitJob($pdfData));
        }
        elseif($dropdown_template_id==13){
            $link = $this->dispatch(new PdfGenerateSdmMscIIIResitJob($pdfData));
        }
		elseif($dropdown_template_id==14){
            $link = $this->dispatch(new PdfGenerateSdmMdsIIResitJob($pdfData));
        }
		elseif($dropdown_template_id==15){
            $link = $this->dispatch(new PdfGenerateSdmMdsIResitJob($pdfData));
        }
        elseif($dropdown_template_id==16){
            $link = $this->dispatch(new pdfGenerateSdmBscNursingResitJob($pdfData));
        }
        elseif($dropdown_template_id==17){
            $link = $this->dispatch(new PdfGenerateSdmNursingPdcJob($pdfData));
        }
        elseif($dropdown_template_id==18){
            $link = $this->dispatch(new pdfGenerateSdmBscNursingIJob($pdfData));
        }
		elseif($dropdown_template_id==18){
            $link = $this->dispatch(new pdfGenerateSdmBscNursingIJob($pdfData));
        }
		elseif($dropdown_template_id==19){
            $link = $this->dispatch(new PdfGenerateSdmMbbsResitJob($pdfData));
        }
		elseif($dropdown_template_id==20){
            $link = $this->dispatch(new PdfGenerateSdmMbbsIIIResitJob($pdfData));
        } 
		elseif($dropdown_template_id==21){
            $link = $this->dispatch(new PdfGenerateSdmMbbsIIIPartIIResitJob($pdfData));
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
        unlink($tmpname);
        mkdir($tmpname);
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
