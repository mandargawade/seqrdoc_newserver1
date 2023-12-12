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
class ValidateExcelGhribmjalJob
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
            if($columnsCount1==8){
                $photo_col=151;
				$columnArr=array("Unique id", "Student Name", "Programme", "Month & Year of examination", "Sem", "PRN", "Seat No", "Paper Code1", "Course Title1", "Credit Point1", "Grade1", "Paper Code2", "Course Title2", "Credit Point2", "Grade2", "Paper Code3", "Course Title3", "Credit Point3", "Grade3", "Paper Code4", "Course Title4", "Credit Point4", "Grade4", "Paper Code5", "Course Title5", "Credit Point5", "Grade5", "Paper Code6", "Course Title6", "Credit Point6", "Grade6", "Paper Code7", "Course Title7", "Credit Point7", "Grade7", "Paper Code8", "Course Title8", "Credit Point8", "Grade8", "Paper Code9", "Course Title9", "Credit Point9", "Grade9", "Paper Code10", "Course Title10", "Credit Point10", "Grade10", "Exam Registration Credits", "Exam Credits", "Earn Grade Points1", "SGPA", "Cumulative Credits Earned", "Earn Grade Points2", "CGPA", "Place", "Date", "Photo");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    //return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==2){
            if($columnsCount1==8){
                // $photo_col=151;

                $columnArr=array("Unique id", "Student Name", "Programme", "Month & Year of examination", "Sem", "PRN", "Seat No", "Paper Code1", "Course Title1", "Credit Point1", "Grade1", "Paper Code2", "Course Title2", "Credit Point2", "Grade2", "Paper Code3", "Course Title3", "Credit Point3", "Grade3", "Paper Code4", "Course Title4", "Credit Point4", "Grade4", "Paper Code5", "Course Title5", "Credit Point5", "Grade5", "Paper Code6", "Course Title6", "Credit Point6", "Grade6", "Paper Code7", "Course Title7", "Credit Point7", "Grade7", "Paper Code8", "Course Title8", "Credit Point8", "Grade8", "Paper Code9", "Course Title9", "Credit Point9", "Grade9", "Paper Code10", "Course Title10", "Credit Point10", "Grade10", "Paper Code11", "Course Title11", "Credit Point11", "Grade11", "Paper Code12", "Course Title12", "Credit Point12", "Grade12", "Paper Code13", "Course Title13", "Credit Point13", "Grade13", "Paper Code14", "Course Title14", "Credit Point14", "Grade14", "Paper Code15", "Course Title15", "Credit Point15", "Grade15", "Exam Registration Credits", "Exam Credits", "Earn Grade Points1", "SGPA", "Cumulative Credits Earned", "Earn Grade Points2", "CGPA", "Place", "Date", "Photo");


                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==3){
            if($columnsCount1==74){
                // $photo_col=151;

                $columnArr=array("Unique id", "ROLL NO", "PRN NO", "NAME FOR GRADE CARD", "MOTHER NAME", "FATHER NAME", "REGISTRATION NO", "UNIVERSITY ENROLLMENT NO", "TERM", "ACADEMIC YEAR", "SESSION", "EXAMINATION", "PROGRAMME", "BRANCH", "EXAM REGISTRATION CREDITS", "EARN CREDITS", "GRADE POINTS EARNED", "SGPA", "CUMULATIVE REGISTRATION CREDITS", "CUMULATIVE CREDITS", "CUMULATIVE EGP", "CGPA", "RESULT DATE", "COURSE CODE1", "COURSE NAME1", "CREDITS1", "GRADES1", "COURSE CODE2", "COURSE NAME2", "CREDITS2", "GRADES2", "COURSE CODE3", "COURSE NAME3", "CREDITS3", "GRADES3", "COURSE CODE4", "COURSE NAME4", "CREDITS4", "GRADES4", "COURSE CODE5", "COURSE NAME5", "CREDITS5", "GRADES5", "COURSE CODE6", "COURSE NAME6", "CREDITS6", "GRADES6", "COURSE CODE7", "COURSE NAME7", "CREDITS7", "GRADES7", "COURSE CODE8", "COURSE NAME8", "CREDITS8", "GRADES8", "COURSE CODE9", "COURSE NAME9", "CREDITS9", "GRADES9", "COURSE CODE10", "COURSE NAME10", "CREDITS10", "GRADES10", "COURSE CODE11", "COURSE NAME11", "CREDITS11", "GRADES11", "COURSE CODE12", "COURSE NAME12", "CREDITS12", "GRADES12", "PLACE ", "DATE", "PHOTO");


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

