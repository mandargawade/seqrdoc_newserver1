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
class ValidateExcelVbitJob
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
            if($columnsCount1==25){
                $photo_col=23;
				$columnArr=array("Unique_Id", "Name", "Parents_Name", "Month_&_Year_of_Exam", "University", "Memo_No", "Examination", "Branch", "Gender", "Hall_Ticket_No", "College_Code", "Serial_No", "Subjects_Registered", "Appeared", "Passed", "Total_Grade_Secured", "Total_GI", "Total_Result", "Total_CI", "SGPA", "CGPA", "Place", "Date", "Image", "Marksheet");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==2){
            if($columnsCount1==18){
                $photo_col=16;
				//$columnArr=array("Unique_Id", "Name", "Parents_Name", "Month_&_Year_of_Exam", "University", "Memo_No", "Degree", "Branch", "Gender", "Hall_Ticket_No", "College_Code", "Examination", "Serial_No", "CGPA Secured", "Place", "Date", "Image", "Marksheet");
				$columnArr=array("Unique_Id", "Name", "Parents_Name", "Month_&_Year_of_Exam", "University", "Memo_No", "Degree", "Branch", "Gender", "Hall_Ticket_No", "College_Code", "Class_Awarded", "Serial_No", "CGPA Secured", "Place", "Date", "Image", "Marksheet");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==3){
            if($columnsCount1==18){
                $photo_col=18;
				//$columnArr=array("Unique_Id", "Name", "Year_of_Admission", "Month_&_Year_of_Exam", "University", "Memo_No", "Degree", "Branch", "Hall_Ticket_No", "College_Code", "Class_Awarded", "Serial_No", "CGPA Secured", "Credits Registered", "Credits Secured", "Place", "Date", "Image");
				$columnArr=array("Unique_Id", "Name", "Parent's_Name", "Month_&_Year_of_Exam", "Memo_No", "Degree", "Branch", "Gender", "Hall_Ticket_No", "College_Code", "Class Awarded", "Serial_No", "CGPA Secured", "Credits Registered", "Credits Secured", "Place", "Date", "Image");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==4){
            if($columnsCount1==25){
                $photo_col=23;
				$columnArr=array("Unique_Id", "Name", "Parents_Name", "Month_&_Year_of_Exam", "University", "Memo_No", "Examination", "Branch", "Gender", "Hall_Ticket_No", "College_Code", "Serial_No", "Subjects_Registered", "Appeared", "Passed", "Total_Grade_Secured", "Total_GI", "Total_Result", "Total_CI", "SGPA", "CGPA", "Place", "Date", "Image", "Marksheet");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==5){
            if($columnsCount1==14){
                $photo_col=10;
				$columnArr=array("Unique_Id", "Name", "Parent_Name", "PC_No", "HT_No", "Serial_No", "Degree", "Year", "Placed_in", "Image", "Memo_No", "Place", "Date", "Certificate");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==6){
            if($columnsCount1==21){
                $photo_col=21;
				//$columnArr=array("Unique_Id" , "Name" , "Parent's_Name" , "Month_&_Year_of_Final_Exam" , "University" , "Memo_No" , "Degree" , "Branch" , "Gender" , "Hall_Ticket_No" , "Collage_Code" , "Examination" , "Serial_ No" , "Honors/Minors" , "CGPA_Secured" , "Secured_Total_Credits(Honors/Minors)" , "Place" , "Date" , "Image");
				$columnArr=array("Unique_Id", "NAME", "PARENT'S_NAME", "MONTH_&_YEAR_OF_FINAL_EXAM", "MEMO_NO", "DEGREE", "BRANCH", "GENDER", "HALL_TICKET_NO", "COLLAGE_CODE", "SERIAL_ NO", "Honors/Minors", "CGPA_Secured", "Class Awarded", "Credits Registered in Program", "Credits Secured in Program", "Credits Registered in H/M", "Credits Secured in H/M", "Place", "Date", "Image");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==7){
            if($columnsCount1==18){
                $photo_col=18;
				$columnArr=array("Unique_Id", "Name", "Parent's_Name", "Month_&_Year_of_Exam", "Memo_No", "Degree", "Branch", "Gender", "Hall_Ticket_No", "College_Code", "Class Awarded", "Serial_No", "CGPA Secured", "Credits Registered", "Credits Secured", "Place", "Date", "Image");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==8){
            if($columnsCount1==21){
                $photo_col=21;
				$columnArr=array("Unique_Id", "NAME", "PARENT'S_NAME", "MONTH_&_YEAR_OF_FINAL_EXAM", "MEMO_NO", "DEGREE", "BRANCH", "GENDER", "HALL_TICKET_NO", "COLLAGE_CODE", "SERIAL_ NO", "Honors/Minors", "CGPA_Secured", "Class Awarded", "Credits Registered in Program", "Credits Secured in Program", "Credits Registered in H/M", "Credits Secured in H/M", "Place", "Date", "Image");
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

