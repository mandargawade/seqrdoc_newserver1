<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\models\BackgroundTemplateMaster;
use Auth,DB;
use TCPDF;
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
class ValidateExcelMONADJob
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
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $pdf_data = $this->pdf_data;

        $rowData1=$pdf_data['rowData1'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $rowData1[0] = array_filter($rowData1[0]);
        $columnsCount1=count($rowData1[0]);
        $photo_col=$columnsCount1-2;
        //echo $columnsCount1;
        //exit;
        if($dropdown_template_id==1){
            if($columnsCount1==170){
                $columnArr=array("SerialNo","EnrollmentNo","RollNo","CandidateName","FatherName","CourseName","YearSem","Session","Subject1","IntTheorymm1","IntTheorymo1","IntPracticalmm1","IntPracticalmo1","ExtTheorymm1","ExtTheorymo1","ExtPracticalmm1","ExtPracticalmo1","Grandmm1","Grandmo1","Remark1","grade1","Subject2","IntTheorymm2","IntTheorymo2","IntPracticalmm2","IntPracticalmo2","ExtTheorymm2","ExtTheorymo2","ExtPracticalmm2","ExtPracticalmo2","Grandmm2","Grandmo2","Remark2","grade2","Subject3","IntTheorymm3","IntTheorymo3","IntPracticalmm3","IntPracticalmo3","ExtTheorymm3","ExtTheorymo3","ExtPracticalmm3","ExtPracticalmo3","Grandmm3","Grandmo3","Remark3","grade3","Subject4","IntTheorymm4","IntTheorymo4","IntPracticalmm4","IntPracticalmo4","ExtTheorymm4","ExtTheorymo4","ExtPracticalmm4","ExtPracticalmo4","Grandmm4","Grandmo4","Remark4","grade4","Subject5","IntTheorymm5","IntTheorymo5","IntPracticalmm5","IntPracticalmo5","ExtTheorymm5","ExtTheorymo5","ExtPracticalmm5","ExtPracticalmo5","Grandmm5","Grandmo5","Remark5","grade5","Subject6","IntTheorymm6","IntTheorymo6","IntPracticalmm6","IntPracticalmo6","ExtTheorymm6","ExtTheorymo6","ExtPracticalmm6","ExtPracticalmo6","Grandmm6","Grandmo6","Remark6","grade6","Subject7","IntTheorymm7","IntTheorymo7","IntPracticalmm7","IntPracticalmo7","ExtTheorymm7","ExtTheorymo7","ExtPracticalmm7","ExtPracticalmo7","Grandmm7","Grandmo7","Remark7","grade7","Subject8","IntTheorymm8","IntTheorymo8","IntPracticalmm8","IntPracticalmo8","ExtTheorymm8","ExtTheorymo8","ExtPracticalmm8","ExtPracticalmo8","Grandmm8","Grandmo8","Remark8","grade8","Subject9","IntTheorymm9","IntTheorymo9","IntPracticalmm9","IntPracticalmo9","ExtTheorymm9","ExtTheorymo9","ExtPracticalmm9","ExtPracticalmo9","Grandmm9","Grandmo9","Remark9","grade9","Subject10","IntTheorymm10","IntTheorymo10","IntPracticalmm10","IntPracticalmo10","ExtTheorymm10","ExtTheorymo10","ExtPracticalmm10","ExtPracticalmo10","Grandmm10","Grandmo10","Remark10","grade10","Subject11","IntTheorymm11","IntTheorymo11","IntPracticalmm11","IntPracticalmo11","ExtTheorymm11","ExtTheorymo11","ExtPracticalmm11","ExtPracticalmo11","Grandmm11","Grandmo11","Remark11","grade11","Subject12","IntTheorymm12","IntTheorymo12","IntPracticalmm12","IntPracticalmo12","ExtTheorymm12","ExtTheorymo12","ExtPracticalmm12","ExtPracticalmo12","Grandmm12","Grandmo12","Remark12","grade12","MaxMarks","totalMarksObt","Result","Date","Photo","Date of Issue");
                
				$mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
        elseif($dropdown_template_id==2){
            if($columnsCount1==150){
                $columnArr=array("SerialNo","EnrollmentNo","RollNo","CandidateName","FatherName","CourseName","YearSem","Session","Subject1","IntTheorymm1","IntTheorymo1","IntPracticalmm1","IntPracticalmo1","ExtTheorymm1","ExtTheorymo1","ExtPracticalmm1","ExtPracticalmo1","Grandmm1","Grandmo1","Remark1","Subject2","IntTheorymm2","IntTheorymo2","IntPracticalmm2","IntPracticalmo2","ExtTheorymm2","ExtTheorymo2","ExtPracticalmm2","ExtPracticalmo2","Grandmm2","Grandmo2","Remark2","Subject3","IntTheorymm3","IntTheorymo3","IntPracticalmm3","IntPracticalmo3","ExtTheorymm3","ExtTheorymo3","ExtPracticalmm3","ExtPracticalmo3","Grandmm3","Grandmo3","Remark3","Subject4","IntTheorymm4","IntTheorymo4","IntPracticalmm4","IntPracticalmo4","ExtTheorymm4","ExtTheorymo4","ExtPracticalmm4","ExtPracticalmo4","Grandmm4","Grandmo4","Remark4","Subject5","IntTheorymm5","IntTheorymo5","IntPracticalmm5","IntPracticalmo5","ExtTheorymm5","ExtTheorymo5","ExtPracticalmm5","ExtPracticalmo5","Grandmm5","Grandmo5","Remark5","Subject6","IntTheorymm6","IntTheorymo6","IntPracticalmm6","IntPracticalmo6","ExtTheorymm6","ExtTheorymo6","ExtPracticalmm6","ExtPracticalmo6","Grandmm6","Grandmo6","Remark6","Subject7","IntTheorymm7","IntTheorymo7","IntPracticalmm7","IntPracticalmo7","ExtTheorymm7","ExtTheorymo7","ExtPracticalmm7","ExtPracticalmo7","Grandmm7","Grandmo7","Remark7","Subject8","IntTheorymm8","IntTheorymo8","IntPracticalmm8","IntPracticalmo8","ExtTheorymm8","ExtTheorymo8","ExtPracticalmm8","ExtPracticalmo8","Grandmm8","Grandmo8","Remark8","Subject9","IntTheorymm9","IntTheorymo9","IntPracticalmm9","IntPracticalmo9","ExtTheorymm9","ExtTheorymo9","ExtPracticalmm9","ExtPracticalmo9","Grandmm9","Grandmo9","Remark9","Subject10","IntTheorymm10","IntTheorymo10","IntPracticalmm10","IntPracticalmo10","ExtTheorymm10","ExtTheorymo10","ExtPracticalmm10","ExtPracticalmo10","Grandmm10","Grandmo10","Remark10","Subject11","IntTheorymm11","IntTheorymo11","IntPracticalmm11","IntPracticalmo11","ExtTheorymm11","ExtTheorymo11","ExtPracticalmm11","ExtPracticalmo11","Grandmm11","Grandmo11","Remark11","MaxMarks","totalMarksObt","ob. In thry","ob. In Prac.","M in thry","m in prac.","Result","Date","Photo","Date of Issue");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
        elseif($dropdown_template_id==3){
            if($columnsCount1==166){
                $columnArr=array("SerialNo","EnrollmentNo","RollNo","CandidateName","FatherName","CourseName","YearSem","Session","Subject1","IntTheorymm1","IntTheorymo1","IntPracticalmm1","IntPracticalmo1","ExtTheorymm1","ExtTheorymo1","ExtPracticalmm1","ExtPracticalmo1","Grandmm1","Grandmo1","Remark1","Subject2","IntTheorymm2","IntTheorymo2","IntPracticalmm2","IntPracticalmo2","ExtTheorymm2","ExtTheorymo2","ExtPracticalmm2","ExtPracticalmo2","Grandmm2","Grandmo2","Remark2","Subject3","IntTheorymm3","IntTheorymo3","IntPracticalmm3","IntPracticalmo3","ExtTheorymm3","ExtTheorymo3","ExtPracticalmm3","ExtPracticalmo3","Grandmm3","Grandmo3","Remark3","Subject4","IntTheorymm4","IntTheorymo4","IntPracticalmm4","IntPracticalmo4","ExtTheorymm4","ExtTheorymo4","ExtPracticalmm4","ExtPracticalmo4","Grandmm4","Grandmo4","Remark4","Subject5","IntTheorymm5","IntTheorymo5","IntPracticalmm5","IntPracticalmo5","ExtTheorymm5","ExtTheorymo5","ExtPracticalmm5","ExtPracticalmo5","Grandmm5","Grandmo5","Remark5","Subject6","IntTheorymm6","IntTheorymo6","IntPracticalmm6","IntPracticalmo6","ExtTheorymm6","ExtTheorymo6","ExtPracticalmm6","ExtPracticalmo6","Grandmm6","Grandmo6","Remark6","Subject7","IntTheorymm7","IntTheorymo7","IntPracticalmm7","IntPracticalmo7","ExtTheorymm7","ExtTheorymo7","ExtPracticalmm7","ExtPracticalmo7","Grandmm7","Grandmo7","Remark7","Subject8","IntTheorymm8","IntTheorymo8","IntPracticalmm8","IntPracticalmo8","ExtTheorymm8","ExtTheorymo8","ExtPracticalmm8","ExtPracticalmo8","Grandmm8","Grandmo8","Remark8","Subject9","IntTheorymm9","IntTheorymo9","IntPracticalmm9","IntPracticalmo9","ExtTheorymm9","ExtTheorymo9","ExtPracticalmm9","ExtPracticalmo9","Grandmm9","Grandmo9","Remark9","Subject10","IntTheorymm10","IntTheorymo10","IntPracticalmm10","IntPracticalmo10","ExtTheorymm10","ExtTheorymo10","ExtPracticalmm10","ExtPracticalmo10","Grandmm10","Grandmo10","Remark10","Subject11","IntTheorymm11","IntTheorymo11","IntPracticalmm11","IntPracticalmo11","ExtTheorymm11","ExtTheorymo11","ExtPracticalmm11","ExtPracticalmo11","Grandmm11","Grandmo11","Remark11","ob. In thry","ob. In Prac.","M in thry","m in prac.","Result","1yrextmm","1yrextmo","1yrpcmm","1yrpcm0","session2","2yrextmm","2yrextmo","2yrpcmm","2yrpcm0","session3","totalextmm","totalextmo","totalpracmm","totalpracmo","%the","%prac","divisionthe","divisionprac","Date","Photo","Date of Issue");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
        elseif($dropdown_template_id==4){
            if($columnsCount1==192){
                $columnArr=array("SerialNo","EnrollmentNo","RollNo","CandidateName","FatherName","CourseName","YearSem","Session","Subject1","IntTheorymm1","IntTheorymo1","IntPracticalmm1","IntPracticalmo1","ExtTheorymm1","ExtTheorymo1","ExtPracticalmm1","ExtPracticalmo1","Grandmm1","Grandmo1","Remark1","grade1","Subject2","IntTheorymm2","IntTheorymo2","IntPracticalmm2","IntPracticalmo2","ExtTheorymm2","ExtTheorymo2","ExtPracticalmm2","ExtPracticalmo2","Grandmm2","Grandmo2","Remark2","grade2","Subject3","IntTheorymm3","IntTheorymo3","IntPracticalmm3","IntPracticalmo3","ExtTheorymm3","ExtTheorymo3","ExtPracticalmm3","ExtPracticalmo3","Grandmm3","Grandmo3","Remark3","grade3","Subject4","IntTheorymm4","IntTheorymo4","IntPracticalmm4","IntPracticalmo4","ExtTheorymm4","ExtTheorymo4","ExtPracticalmm4","ExtPracticalmo4","Grandmm4","Grandmo4","Remark4","grade4","Subject5","IntTheorymm5","IntTheorymo5","IntPracticalmm5","IntPracticalmo5","ExtTheorymm5","ExtTheorymo5","ExtPracticalmm5","ExtPracticalmo5","Grandmm5","Grandmo5","Remark5","grade5","Subject6","IntTheorymm6","IntTheorymo6","IntPracticalmm6","IntPracticalmo6","ExtTheorymm6","ExtTheorymo6","ExtPracticalmm6","ExtPracticalmo6","Grandmm6","Grandmo6","Remark6","grade6","Subject7","IntTheorymm7","IntTheorymo7","IntPracticalmm7","IntPracticalmo7","ExtTheorymm7","ExtTheorymo7","ExtPracticalmm7","ExtPracticalmo7","Grandmm7","Grandmo7","Remark7","grade7","Subject8","IntTheorymm8","IntTheorymo8","IntPracticalmm8","IntPracticalmo8","ExtTheorymm8","ExtTheorymo8","ExtPracticalmm8","ExtPracticalmo8","Grandmm8","Grandmo8","Remark8","grade8","Subject9","IntTheorymm9","IntTheorymo9","IntPracticalmm9","IntPracticalmo9","ExtTheorymm9","ExtTheorymo9","ExtPracticalmm9","ExtPracticalmo9","Grandmm9","Grandmo9","Remark9","grade9","Subject10","IntTheorymm10","IntTheorymo10","IntPracticalmm10","IntPracticalmo10","ExtTheorymm10","ExtTheorymo10","ExtPracticalmm10","ExtPracticalmo10","Grandmm10","Grandmo10","Remark10","grade10","Subject11","IntTheorymm11","IntTheorymo11","IntPracticalmm11","IntPracticalmo11","ExtTheorymm11","ExtTheorymo11","ExtPracticalmm11","ExtPracticalmo11","Grandmm11","Grandmo11","Remark11","grade11","MaxMarks","totalMarksObt","Result","sem1MaxMarks","Sem1Obtmarks","sgpasem1","Sem1passingyear","sem2MaxMarks","Sem2Obtmarks","sgpasem2","Sem2passingyear","sem3MaxMarks","Sem3Obtmarks","sgpasem3","Sem3passingyear","sem4MaxMarks","Sem4Obtmarks","sgpasem4","Sem4passingyear","sem5MaxMarks","Sem5Obtmarks","sgpasem5","Sem5passingyear","sem6MaxMarks","Sem6Obtmarks","sgpasem6","Sem6passingyear","sem7MaxMarks","Sem7Obtmarks","sgpasem7","Sem7passingyear","sem8MaxMarks","Sem8Obtmarks","sgpasem8","Sem8passingyear","Maxtotal","ObtTotal","CGPA","Date","Photo","Date of Issue");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
        elseif($dropdown_template_id==5){
            if($columnsCount1==183){
                $columnArr=array("SerialNo","EnrollmentNo","RollNo","CandidateName","FatherName","CourseName","YearSem","Session","coed1","Subject1","IntTheorymm1","IntTheorymo1","IntPracticalmm1","IntPracticalmo1","ExtTheorymm1","ExtTheorymo1","ExtPracticalmm1","ExtPracticalmo1","Grandmm1","Grandmo1","Remark1","grade1","coed2","Subject2","IntTheorymm2","IntTheorymo2","IntPracticalmm2","IntPracticalmo2","ExtTheorymm2","ExtTheorymo2","ExtPracticalmm2","ExtPracticalmo2","Grandmm2","Grandmo2","Remark2","grade2","Code3","Subject3","IntTheorymm3","IntTheorymo3","IntPracticalmm3","IntPracticalmo3","ExtTheorymm3","ExtTheorymo3","ExtPracticalmm3","ExtPracticalmo3","Grandmm3","Grandmo3","Remark3","grade3","Code4","Subject4","IntTheorymm4","IntTheorymo4","IntPracticalmm4","IntPracticalmo4","ExtTheorymm4","ExtTheorymo4","ExtPracticalmm4","ExtPracticalmo4","Grandmm4","Grandmo4","Remark4","grade4","Code5","Subject5","IntTheorymm5","IntTheorymo5","IntPracticalmm5","IntPracticalmo5","ExtTheorymm5","ExtTheorymo5","ExtPracticalmm5","ExtPracticalmo5","Grandmm5","Grandmo5","Remark5","grade5","Code6","Subject6","IntTheorymm6","IntTheorymo6","IntPracticalmm6","IntPracticalmo6","ExtTheorymm6","ExtTheorymo6","ExtPracticalmm6","ExtPracticalmo6","Grandmm6","Grandmo6","Remark6","grade6","Code7","Subject7","IntTheorymm7","IntTheorymo7","IntPracticalmm7","IntPracticalmo7","ExtTheorymm7","ExtTheorymo7","ExtPracticalmm7","ExtPracticalmo7","Grandmm7","Grandmo7","Remark7","grade7","Code8","Subject8","IntTheorymm8","IntTheorymo8","IntPracticalmm8","IntPracticalmo8","ExtTheorymm8","ExtTheorymo8","ExtPracticalmm8","ExtPracticalmo8","Grandmm8","Grandmo8","Remark8","grade8","Code9","Subject9","IntTheorymm9","IntTheorymo9","IntPracticalmm9","IntPracticalmo9","ExtTheorymm9","ExtTheorymo9","ExtPracticalmm9","ExtPracticalmo9","Grandmm9","Grandmo9","Remark9","grade9","Code10","Subject10","IntTheorymm10","IntTheorymo10","IntPracticalmm10","IntPracticalmo10","ExtTheorymm10","ExtTheorymo10","ExtPracticalmm10","ExtPracticalmo10","Grandmm10","Grandmo10","Remark10","grade10","code11","Subject11","IntTheorymm11","IntTheorymo11","IntPracticalmm11","IntPracticalmo11","ExtTheorymm11","ExtTheorymo11","ExtPracticalmm11","ExtPracticalmo11","Grandmm11","Grandmo11","Remark11","grade11","code12","Subject12","IntTheorymm12","IntTheorymo12","IntPracticalmm12","IntPracticalmo12","ExtTheorymm12","ExtTheorymo12","ExtPracticalmm12","ExtPracticalmo12","Grandmm12","Grandmo12","Remark12","grade12","MaxMarks","totalMarksObt","Result","Date","total credit","Photo","Date of Issue");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==6){
            if($columnsCount1==19){
                $columnArr=array('Serial No', 'Roll No', 'Enrollment No', 'Name', 'Hindi Name', 'Father Name', 'Hindi Father Name', 'Mother Name', 'Hindi Mother Name', 'Exam Year', 'Hindi Exam Year', 'Degree', 'Hindi Degree', 'Degree In', 'Hindi Degree In', 'Remark', 'Hindi Remark', 'Date', 'Photo');
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
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
            $message = Array('success'=>false,'type' => 'error', 'message' => 'Excel/Json has more than 1 column having same name. i.e. : '.$duplicate_columns);
            return json_encode($message);
        }
       
        

        unset($rowData1[0]);
        $rowData1=array_values($rowData1);
        $blobArr=array();
        $profArr=array();
        $profErrArr=array();
        /*foreach ($rowData1 as $readblob) {
            array_push($blobArr, $readblob[23]);
        }*/

        $sandboxCheck = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
        $old_rows = 0;
        $new_rows = 0;
        foreach ($rowData1 as $key1 => $value1) {

            $serial_no=$value1[0];
            array_push($blobArr, $serial_no);
           // array_push($profArr, $value1[141]);

            $profile_path = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$value1[$photo_col];
            if (!file_exists($profile_path)) {   
                 array_push($profErrArr, $value1[$photo_col]);
            }
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
            return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Unique Id contains following duplicate values : '.implode(',', $mismatchArr)]);
            }
        /*  $mismatchProfArr = array_intersect($profArr, array_unique(array_diff_key($profArr, array_unique($profArr))));

        if(count($mismatchProfArr)>0){
            return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo contains following duplicate values : '.implode(',', $mismatchProfArr)]);
            }*/
         if(count($profErrArr)>0){
            return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo does not exists following values : '.implode(',', $profErrArr)]);
            }
              
        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);
    }

  
}

