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
class ValidateExcelIscnJob
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
        
        //echo $columnsCount1;
        //exit;
       
        if($dropdown_template_id==1){
            if($columnsCount1==28){
                //$photo_col=23;Department  Semester    Program_Name    Examination_Year    Credit_Type Serial_No COE_Signature Director Name_of_ the_Student



				$columnArr=array("Unique_Id", "Department","Semester","Program_Name","Examination_Year", "Credit_Type", "Serial_No", "Name_of_the_Student", "Mother's_Name", "Exam_Category", "Gender", "Medium", "IRN_Number", "Roll_Number", "Enrollment_Number", "HPE_Grades", "Incentives", "AGPV", "Semester_Credits_(Ci)", "SGPA-T(Out of 10)", "SGPA$(Out of 10)", "Group_Total(Out of 450)", "Marks_Obtained(Out of 600)", "Result", "Number_of_Attempts", "Date", "COE_Signature", "Director");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==2){
            if($columnsCount1==26){

                $columnArr=array("Unique_Id", "Department","Semester","Program_Name","specialization","Examination_Year", "Credit_Type", "Serial_No", "Name_of_the_Student", "Mother's_Name", "Exam_Category", "Gender", "Medium", "IRN_Number", "Roll_Number", "Enrollment_Number", "Incentives", "AGPV", "Semester_Credits(CR)", "Marks_Obtained(Out of 625)", "SGPA(Out of 10)", "Result", "Number of Attempts", "Date", "COE_Signature", "Director");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==3){
            if($columnsCount1==26){
                $columnArr=array("Unique_Id", "Department","Semester","Program_Name","Specialization","Examination_Year", "Credit_Type", "Serial_No", "Name_of_the_Student", "Mother's_Name", "Exam_Category", "Gender", "Medium", "IRN_Number", "Roll_Number", "Enrollment_Number", "Incentives", "AGPV", "Semester_Credits", "SGPA(Out of 10)", "Marks_Obtained(Out of 625)", "Result", "Number_of_Attempts", "Date", "COE_Signature", "Director");
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
            //return json_encode($message);
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

            /*$profile_path = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$value1[$photo_col];
            if (!file_exists($profile_path)) {   
                 //array_push($profErrArr, $value1[$photo_col]);
            }*/
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
            //return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo does not exists following values : '.implode(',', $profErrArr)]);
            }
              
        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);
    }

  
}

