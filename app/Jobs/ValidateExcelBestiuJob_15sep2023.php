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
class ValidateExcelBestiuJob
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
            if($columnsCount1==9){
                //$photo_col=23;
				$columnArr=array("UNIQUE ID", "ID NO.", "STUDENT NAME", "FATHERS NAME", "DEGREE", "COMPLETION DATE", "OVERALL GRADE POINT AVERAGE OF MARKS", "DATE", "PLACE");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==2){
            if($columnsCount1==10){
                //$photo_col=16;
				$columnArr=array("Unique ID", "ID NO.", "STUDENT NAME", "FATHERS NAME", "DEGREE", "COMPLETION DATE", "OVERALL GRADE POINT AVERAGE", "PERCENTAGE OF MARKS", "DIVISION", "DATED");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==3){
			if($columnsCount1==384){
                $photo_col=383;
				$columnArr=array("Unique ID", "ID No.", "Name", "Fathers Name", "Programme", "College", "Year of admission", "Academic Year of Admission", "Department", "Date of Declaration of Result", "Specialization", "Date of successful completion", "I SEM", "SEM1_COURSE CODE_1", "SEM1_COURSE TITLE_1", "SEM1_CREDIT HOURS _1", "SEM1_GRADE POINT_1", "SEM1_CREDIT POINTS_1", "SEM1_COURSE CODE_2", "SEM1_COURSE TITLE_2", "SEM1_CREDIT HOURS _2", "SEM1_GRADE POINT_2", "SEM1_CREDIT POINTS_2", "SEM1_COURSE CODE_3", "SEM1_COURSE TITLE_3", "SEM1_CREDIT HOURS _3", "SEM1_GRADE POINT_3", "SEM1_CREDIT POINTS_3", "SEM1_COURSE CODE_4", "SEM1_COURSE TITLE_4", "SEM1_CREDIT HOURS _4", "SEM1_GRADE POINT_4", "SEM1_CREDIT POINTS_4", "SEM1_COURSE CODE_5", "SEM1_COURSE TITLE_5", "SEM1_CREDIT HOURS _5", "SEM1_GRADE POINT_5", "SEM1_CREDIT POINTS_5", "SEM1_COURSE CODE_6", "SEM1_COURSE TITLE_6", "SEM1_CREDIT HOURS _6", "SEM1_GRADE POINT_6", "SEM1_CREDIT POINTS_6", "SEM1_COURSE CODE_7", "SEM1_COURSE TITLE_7", "SEM1_CREDIT HOURS _7", "SEM1_GRADE POINT_7", "SEM1_CREDIT POINTS_7", "SEM1_COURSE CODE_8", "SEM1_COURSE TITLE_8", "SEM1_CREDIT HOURS _8", "SEM1_GRADE POINT_8", "SEM1_CREDIT POINTS_8", "SEM1_COURSE CODE_9", "SEM1_COURSE TITLE_9", "SEM1_CREDIT HOURS _9", "SEM1_GRADE POINT_9", "SEM1_CREDIT POINTS_9", "SEM1_COURSE CODE_10", "SEM1_COURSE TITLE_10", "SEM1_CREDIT HOURS _10", "SEM1_GRADE POINT_10", "SEM1_CREDIT POINTS_10", "SEM1_TOTAL CREDITS_CREDIT HOURS", "SEM1_TOTAL CREDITS_CREDIT POINTS", "II SEM", "SEM2_COURSE CODE_1", "SEM2_COURSE TITLE_1", "SEM2_CREDIT HOURS _1", "SEM2_GRADE POINT_1", "SEM2_CREDIT POINTS_1", "SEM2_COURSE CODE_2", "SEM2_COURSE TITLE_2", "SEM2_CREDIT HOURS _2", "SEM2_GRADE POINT_2", "SEM2_CREDIT POINTS_2", "SEM2_COURSE CODE_3", "SEM2_COURSE TITLE_3", "SEM2_CREDIT HOURS _3", "SEM2_GRADE POINT_3", "SEM2_CREDIT POINTS_3", "SEM2_COURSE CODE_4", "SEM2_COURSE TITLE_4", "SEM2_CREDIT HOURS _4", "SEM2_GRADE POINT_4", "SEM2_CREDIT POINTS_4", "SEM2_COURSE CODE_5", "SEM2_COURSE TITLE_5", "SEM2_CREDIT HOURS _5", "SEM2_GRADE POINT_5", "SEM2_CREDIT POINTS_5", "SEM2_COURSE CODE_6", "SEM2_COURSE TITLE_6", "SEM2_CREDIT HOURS _6", "SEM2_GRADE POINT_6", "SEM2_CREDIT POINTS_6", "SEM2_COURSE CODE_7", "SEM2_COURSE TITLE_7", "SEM2_CREDIT HOURS _7", "SEM2_GRADE POINT_7", "SEM2_CREDIT POINTS_7", "SEM2_COURSE CODE_8", "SEM2_COURSE TITLE_8", "SEM2_CREDIT HOURS _8", "SEM2_GRADE POINT_8", "SEM2_CREDIT POINTS_8", "SEM2_COURSE CODE_9", "SEM2_COURSE TITLE_9", "SEM2_CREDIT HOURS _9", "SEM2_GRADE POINT_9", "SEM2_CREDIT POINTS_9", "SEM2_COURSE CODE_10", "SEM2_COURSE TITLE_10", "SEM2_CREDIT HOURS _10", "SEM2_GRADE POINT_10", "SEM2_CREDIT POINTS_10", "SEM2_COURSE CODE_11", "SEM2_COURSE TITLE_11", "SEM2_CREDIT HOURS _11", "SEM2_GRADE POINT_11", "SEM2_CREDIT POINTS_11", "SEM2_TOTAL CREDITS_CREDIT HOURS", "SEM2_TOTAL CREDITS_CREDIT POINTS", "III SEM", "SEM3_COURSE CODE_1", "SEM3_COURSE TITLE_1", "SEM3_CREDIT HOURS _1", "SEM3_GRADE POINT_1", "SEM3_CREDIT POINTS_1", "SEM3_COURSE CODE_2", "SEM3_COURSE TITLE_2", "SEM3_CREDIT HOURS _2", "SEM3_GRADE POINT_2", "SEM3_CREDIT POINTS_2", "SEM3_COURSE CODE_3", "SEM3_COURSE TITLE_3", "SEM3_CREDIT HOURS _3", "SEM3_GRADE POINT_3", "SEM3_CREDIT POINTS_3", "SEM3_COURSE CODE_4", "SEM3_COURSE TITLE_4", "SEM3_CREDIT HOURS _4", "SEM3_GRADE POINT_4", "SEM3_CREDIT POINTS_4", "SEM3_COURSE CODE_5", "SEM3_COURSE TITLE_5", "SEM3_CREDIT HOURS _5", "SEM3_GRADE POINT_5", "SEM3_CREDIT POINTS_5", "SEM3_COURSE CODE_6", "SEM3_COURSE TITLE_6", "SEM3_CREDIT HOURS _6", "SEM3_GRADE POINT_6", "SEM3_CREDIT POINTS_6", "SEM3_COURSE CODE_7", "SEM3_COURSE TITLE_7", "SEM3_CREDIT HOURS _7", "SEM3_GRADE POINT_7", "SEM3_CREDIT POINTS_7", "SEM3_COURSE CODE_8", "SEM3_COURSE TITLE_8", "SEM3_CREDIT HOURS _8", "SEM3_GRADE POINT_8", "SEM3_CREDIT POINTS_8", "SEM3_COURSE CODE_9", "SEM3_COURSE TITLE_9", "SEM3_CREDIT HOURS _9", "SEM3_GRADE POINT_9", "SEM3_CREDIT POINTS_9", "SEM3_COURSE CODE_10", "SEM3_COURSE TITLE_10", "SEM3_CREDIT HOURS _10", "SEM3_GRADE POINT_10", "SEM3_CREDIT POINTS_10", "SEM3_TOTAL CREDITS_CREDIT HOURS", "SEM3_TOTAL CREDITS_CREDIT POINTS", "IV SEM", "SEM4_COURSE CODE_1", "SEM4_COURSE TITLE_1", "SEM4_CREDIT HOURS _1", "SEM4_GRADE POINT_1", "SEM4_CREDIT POINTS_1", "SEM4_COURSE CODE_2", "SEM4_COURSE TITLE_2", "SEM4_CREDIT HOURS _2", "SEM4_GRADE POINT_2", "SEM4_CREDIT POINTS_2", "SEM4_COURSE CODE_3", "SEM4_COURSE TITLE_3", "SEM4_CREDIT HOURS _3", "SEM4_GRADE POINT_3", "SEM4_CREDIT POINTS_3", "SEM4_COURSE CODE_4", "SEM4_COURSE TITLE_4", "SEM4_CREDIT HOURS _4", "SEM4_GRADE POINT_4", "SEM4_CREDIT POINTS_4", "SEM4_COURSE CODE_5", "SEM4_COURSE TITLE_5", "SEM4_CREDIT HOURS _5", "SEM4_GRADE POINT_5", "SEM4_CREDIT POINTS_5", "SEM4_COURSE CODE_6", "SEM4_COURSE TITLE_6", "SEM4_CREDIT HOURS _6", "SEM4_GRADE POINT_6", "SEM4_CREDIT POINTS_6", "SEM4_COURSE CODE_7", "SEM4_COURSE TITLE_7", "SEM4_CREDIT HOURS _7", "SEM4_GRADE POINT_7", "SEM4_CREDIT POINTS_7", "SEM4_COURSE CODE_8", "SEM4_COURSE TITLE_8", "SEM4_CREDIT HOURS _8", "SEM4_GRADE POINT_8", "SEM4_CREDIT POINTS_8", "SEM4_COURSE CODE_9", "SEM4_COURSE TITLE_9", "SEM4_CREDIT HOURS _9", "SEM4_GRADE POINT_9", "SEM4_CREDIT POINTS_9", "SEM4_COURSE CODE_10", "SEM4_COURSE TITLE_10", "SEM4_CREDIT HOURS _10", "SEM4_GRADE POINT_10", "SEM4_CREDIT POINTS_10", "SEM4_TOTAL CREDITS_CREDIT HOURS", "SEM4_TOTAL CREDITS_CREDIT POINTS", "V SEM", "SEM5_COURSE CODE_1", "SEM5_COURSE TITLE_1", "SEM5_CREDIT HOURS _1", "SEM5_GRADE POINT_1", "SEM5_CREDIT POINTS_1", "SEM5_COURSE CODE_2", "SEM5_COURSE TITLE_2", "SEM5_CREDIT HOURS _2", "SEM5_GRADE POINT_2", "SEM5_CREDIT POINTS_2", "SEM5_COURSE CODE_3", "SEM5_COURSE TITLE_3", "SEM5_CREDIT HOURS _3", "SEM5_GRADE POINT_3", "SEM5_CREDIT POINTS_3", "SEM5_COURSE CODE_4", "SEM5_COURSE TITLE_4", "SEM5_CREDIT HOURS _4", "SEM5_GRADE POINT_4", "SEM5_CREDIT POINTS_4", "SEM5_COURSE CODE_5", "SEM5_COURSE TITLE_5", "SEM5_CREDIT HOURS _5", "SEM5_GRADE POINT_5", "SEM5_CREDIT POINTS_5", "SEM5_COURSE CODE_6", "SEM5_COURSE TITLE_6", "SEM5_CREDIT HOURS _6", "SEM5_GRADE POINT_6", "SEM5_CREDIT POINTS_6", "SEM5_COURSE CODE_7", "SEM5_COURSE TITLE_7", "SEM5_CREDIT HOURS _7", "SEM5_GRADE POINT_7", "SEM5_CREDIT POINTS_7", "SEM5_COURSE CODE_8", "SEM5_COURSE TITLE_8", "SEM5_CREDIT HOURS _8", "SEM5_GRADE POINT_8", "SEM5_CREDIT POINTS_8", "SEM5_COURSE CODE_9", "SEM5_COURSE TITLE_9", "SEM5_CREDIT HOURS _9", "SEM5_GRADE POINT_9", "SEM5_CREDIT POINTS_9", "SEM5_TOTAL CREDITS_CREDIT HOURS", "SEM5_TOTAL CREDITS_CREDIT POINTS", "VI SEM", "SEM6_COURSE CODE_1", "SEM6_COURSE TITLE_1", "SEM6_CREDIT HOURS _1", "SEM6_GRADE POINT_1", "SEM6_CREDIT POINTS_1", "SEM6_COURSE CODE_2", "SEM6_COURSE TITLE_2", "SEM6_CREDIT HOURS _2", "SEM6_GRADE POINT_2", "SEM6_CREDIT POINTS_2", "SEM6_COURSE CODE_3", "SEM6_COURSE TITLE_3", "SEM6_CREDIT HOURS _3", "SEM6_GRADE POINT_3", "SEM6_CREDIT POINTS_3", "SEM6_COURSE CODE_4", "SEM6_COURSE TITLE_4", "SEM6_CREDIT HOURS _4", "SEM6_GRADE POINT_4", "SEM6_CREDIT POINTS_4", "SEM6_COURSE CODE_5", "SEM6_COURSE TITLE_5", "SEM6_CREDIT HOURS _5", "SEM6_GRADE POINT_5", "SEM6_CREDIT POINTS_5", "SEM6_COURSE CODE_6", "SEM6_COURSE TITLE_6", "SEM6_CREDIT HOURS _6", "SEM6_GRADE POINT_6", "SEM6_CREDIT POINTS_6", "SEM6_COURSE CODE_7", "SEM6_COURSE TITLE_7", "SEM6_CREDIT HOURS _7", "SEM6_GRADE POINT_7", "SEM6_CREDIT POINTS_7", "SEM6_COURSE CODE_8", "SEM6_COURSE TITLE_8", "SEM6_CREDIT HOURS _8", "SEM6_GRADE POINT_8", "SEM6_CREDIT POINTS_8", "SEM6_COURSE CODE_9", "SEM6_COURSE TITLE_9", "SEM6_CREDIT HOURS _9", "SEM6_GRADE POINT_9", "SEM6_CREDIT POINTS_9", "SEM6_COURSE CODE_10", "SEM6_COURSE TITLE_10", "SEM6_CREDIT HOURS _10", "SEM6_GRADE POINT_10", "SEM6_CREDIT POINTS_10", "SEM6_COURSE CODE_11", "SEM6_COURSE TITLE_11", "SEM6_CREDIT HOURS _11", "SEM6_GRADE POINT_11", "SEM6_CREDIT POINTS_11", "SEM6_TOTAL CREDITS_CREDIT HOURS", "SEM6_TOTAL CREDITS_CREDIT POINTS", "VII SEM", "SEM7_COURSE CODE_1", "SEM7_COURSE TITLE_1", "SEM7_CREDIT HOURS _1", "SEM7_GRADE POINT_1", "SEM7_CREDIT POINTS_1", "SEM7_COURSE CODE_2", "SEM7_COURSE TITLE_2", "SEM7_CREDIT HOURS _2", "SEM7_GRADE POINT_2", "SEM7_CREDIT POINTS_2", "SEM7_COURSE CODE_3", "SEM7_COURSE TITLE_3", "SEM7_CREDIT HOURS _3", "SEM7_GRADE POINT_3", "SEM7_CREDIT POINTS_3", "SEM7_COURSE CODE_4", "SEM7_COURSE TITLE_4", "SEM7_CREDIT HOURS _4", "SEM7_GRADE POINT_4", "SEM7_CREDIT POINTS_4", "SEM7_COURSE CODE_5", "SEM7_COURSE TITLE_5", "SEM7_CREDIT HOURS _5", "SEM7_GRADE POINT_5", "SEM7_CREDIT POINTS_5", "SEM7_COURSE CODE_6", "SEM7_COURSE TITLE_6", "SEM7_CREDIT HOURS _6", "SEM7_GRADE POINT_6", "SEM7_CREDIT POINTS_6", "SEM7_", "SEM7_TOTAL CREDITS_CREDIT POINTS", "VIII SEM", "SEM8_COURSE CODE_1", "SEM8_COURSE TITLE_1", "SEM8_CREDIT HOURS _1", "SEM8_GRADE POINT_1", "SEM8_CREDIT POINTS_1", "SEM8_COURSE CODE_2", "SEM8_COURSE TITLE_2", "SEM8_CREDIT HOURS _2", "SEM8_GRADE POINT_2", "SEM8_CREDIT POINTS_2", "TOTAL CREDITS_CREDIT HOURS", "TOTAL CREDITS_CREDIT POINTS", "OVERALL GRADE POINT AVERAGE", "DATE", "Photos of students");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
		}
		elseif($dropdown_template_id==4){
            //if($columnsCount1==9){
                $columnArr=array("Unique ID", "STUDENT NAME", "FATHERS NAME", "COURSE", "SEMESTER", "DATE", "ID NO.");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    //return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            //}else{
                //return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            //}
        }
        elseif($dropdown_template_id==5){
            if($columnsCount1==108){
                //$photo_col=16;
                $columnArr=array("Unique_id", "Student_Name", "Father_Name", "Date", "Semester", "ID_No", "Course", "SI.No1", "Course_Code1", "Course_Title1", "Credit_Hours1", "Grade_point1", "Credit_points1", "SI.No2", "Course_Code2", "Course_Title2", "Credit_Hours2", "Grade_point2", "Credit_points2", "SI.No3", "Course_Code3", "Course_Title3", "Credit_Hours3", "Grade_point3", "Credit_points3", "SI.No4", "Course_Code4", "Course_Title4", "Credit_Hours4", "Grade_point4", "Credit_points4", "SI.No5", "Course_Code5", "Course_Title5", "Credit_Hours5", "Grade_point5", "Credit_points5", "SI.No6", "Course_Code6", "Course_Title6", "Credit_Hours6", "Grade_point6", "Credit_points6", "SI.No7", "Course_Code7", "Course_Title7", "Credit_Hours7", "Grade_point7", "Credit_points7", "SI.No8", "Course_Code8", "Course_Title8", "Credit_Hours8", "Grade_point8", "Credit_points8", "SI.No9", "Course_Code9", "Course_Title9", "Credit_Hours9", "Grade_point9", "Credit_points9", "SI.No10", "Course_Code10", "Course_Title10", "Credit_Hours10", "Grade_point10", "Credit_points10", "SI.No11", "Course_Code11", "Course_Title11", "Credit_Hours11", "Grade_point11", "Credit_points11", "SE_SI.No1", "SE_Course_Code1", "SE_Course_Title1", "SE_Credit_Hours1", "SE_Grade_point1", "SE_Credit_points1", "SE_SI.No2", "SE_Course_Code2", "SE_Course_Title2", "SE_Credit_Hours2", "SE_Grade_point2", "SE_Credit_points2", "SE_SI.No3", "SE_Course_Code3", "SE_Course_Title3", "SE_Credit_Hours3", "SE_Grade_point3", "SE_Credit_points3", "SE_SI.No4", "SE_Course_Code4", "SE_Course_Title4", "SE_Credit_Hours4", "SE_Grade_point4", "SE_Credit_points4", "SE_SI.No5", "SE_Course_Code5", "SE_Course_Title5", "SE_Credit_Hours5", "SE_Grade_point5", "SE_Credit_points5", "TOTAL CREDITS_CREDIT HOURS", "TOTAL CREDITS_CREDIT POINTS", "SGPA", "CGPA", "Note");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==6){
			if($columnsCount1==375){
                $photo_col=369;
				$columnArr=array("Unique ID", "ID No.", "Name", "Fathers Name", "Programme", "College", "Year of admission", "Academic Year of Admission", "Department", "Date of Declaration of Result", "Specialization", "Date of successful completion", "I SEM", "SEM1_COURSE CODE_1", "SEM1_COURSE TITLE_1", "SEM1_CREDIT HOURS _1", "SEM1_GRADE POINT_1", "SEM1_CREDIT POINTS_1", "SEM1_COURSE CODE_2", "SEM1_COURSE TITLE_2", "SEM1_CREDIT HOURS _2", "SEM1_GRADE POINT_2", "SEM1_CREDIT POINTS_2", "SEM1_COURSE CODE_3", "SEM1_COURSE TITLE_3", "SEM1_CREDIT HOURS _3", "SEM1_GRADE POINT_3", "SEM1_CREDIT POINTS_3", "SEM1_COURSE CODE_4", "SEM1_COURSE TITLE_4", "SEM1_CREDIT HOURS _4", "SEM1_GRADE POINT_4", "SEM1_CREDIT POINTS_4", "SEM1_COURSE CODE_5", "SEM1_COURSE TITLE_5", "SEM1_CREDIT HOURS _5", "SEM1_GRADE POINT_5", "SEM1_CREDIT POINTS_5", "SEM1_COURSE CODE_6", "SEM1_COURSE TITLE_6", "SEM1_CREDIT HOURS _6", "SEM1_GRADE POINT_6", "SEM1_CREDIT POINTS_6", "SEM1_COURSE CODE_7", "SEM1_COURSE TITLE_7", "SEM1_CREDIT HOURS _7", "SEM1_GRADE POINT_7", "SEM1_CREDIT POINTS_7", "SEM1_COURSE CODE_8", "SEM1_COURSE TITLE_8", "SEM1_CREDIT HOURS _8", "SEM1_GRADE POINT_8", "SEM1_CREDIT POINTS_8", "SEM1_COURSE CODE_9", "SEM1_COURSE TITLE_9", "SEM1_CREDIT HOURS _9", "SEM1_GRADE POINT_9", "SEM1_CREDIT POINTS_9", "SEM1_COURSE CODE_10", "SEM1_COURSE TITLE_10", "SEM1_CREDIT HOURS _10", "SEM1_GRADE POINT_10", "SEM1_CREDIT POINTS_10", "SEM1_COURSE CODE_11", "SEM1_COURSE TITLE_11", "SEM1_CREDIT HOURS _11", "SEM1_GRADE POINT_11", "SEM1_CREDIT POINTS_11", "II SEM", "SEM2_COURSE CODE_1", "SEM2_COURSE TITLE_1", "SEM2_CREDIT HOURS _1", "SEM2_GRADE POINT_1", "SEM2_CREDIT POINTS_1", "SEM2_COURSE CODE_2", "SEM2_COURSE TITLE_2", "SEM2_CREDIT HOURS _2", "SEM2_GRADE POINT_2", "SEM2_CREDIT POINTS_2", "SEM2_COURSE CODE_3", "SEM2_COURSE TITLE_3", "SEM2_CREDIT HOURS _3", "SEM2_GRADE POINT_3", "SEM2_CREDIT POINTS_3", "SEM2_COURSE CODE_4", "SEM2_COURSE TITLE_4", "SEM2_CREDIT HOURS _4", "SEM2_GRADE POINT_4", "SEM2_CREDIT POINTS_4", "SEM2_COURSE CODE_5", "SEM2_COURSE TITLE_5", "SEM2_CREDIT HOURS _5", "SEM2_GRADE POINT_5", "SEM2_CREDIT POINTS_5", "SEM2_COURSE CODE_6", "SEM2_COURSE TITLE_6", "SEM2_CREDIT HOURS _6", "SEM2_GRADE POINT_6", "SEM2_CREDIT POINTS_6", "SEM2_COURSE CODE_7", "SEM2_COURSE TITLE_7", "SEM2_CREDIT HOURS _7", "SEM2_GRADE POINT_7", "SEM2_CREDIT POINTS_7", "SEM2_COURSE CODE_8", "SEM2_COURSE TITLE_8", "SEM2_CREDIT HOURS _8", "SEM2_GRADE POINT_8", "SEM2_CREDIT POINTS_8", "SEM2_COURSE CODE_9", "SEM2_COURSE TITLE_9", "SEM2_CREDIT HOURS _9", "SEM2_GRADE POINT_9", "SEM2_CREDIT POINTS_9", "SEM2_COURSE CODE_10", "SEM2_COURSE TITLE_10", "SEM2_CREDIT HOURS _10", "SEM2_GRADE POINT_10", "SEM2_CREDIT POINTS_10", "III SEM", "SEM3_COURSE CODE_1", "SEM3_COURSE TITLE_1", "SEM3_CREDIT HOURS _1", "SEM3_GRADE POINT_1", "SEM3_CREDIT POINTS_1", "SEM3_COURSE CODE_2", "SEM3_COURSE TITLE_2", "SEM3_CREDIT HOURS _2", "SEM3_GRADE POINT_2", "SEM3_CREDIT POINTS_2", "SEM3_COURSE CODE_3", "SEM3_COURSE TITLE_3", "SEM3_CREDIT HOURS _3", "SEM3_GRADE POINT_3", "SEM3_CREDIT POINTS_3", "SEM3_COURSE CODE_4", "SEM3_COURSE TITLE_4", "SEM3_CREDIT HOURS _4", "SEM3_GRADE POINT_4", "SEM3_CREDIT POINTS_4", "SEM3_COURSE CODE_5", "SEM3_COURSE TITLE_5", "SEM3_CREDIT HOURS _5", "SEM3_GRADE POINT_5", "SEM3_CREDIT POINTS_5", "SEM3_COURSE CODE_6", "SEM3_COURSE TITLE_6", "SEM3_CREDIT HOURS _6", "SEM3_GRADE POINT_6", "SEM3_CREDIT POINTS_6", "SEM3_COURSE CODE_7", "SEM3_COURSE TITLE_7", "SEM3_CREDIT HOURS _7", "SEM3_GRADE POINT_7", "SEM3_CREDIT POINTS_7", "SEM3_COURSE CODE_8", "SEM3_COURSE TITLE_8", "SEM3_CREDIT HOURS _8", "SEM3_GRADE POINT_8", "SEM3_CREDIT POINTS_8", "SEM3_COURSE CODE_9", "SEM3_COURSE TITLE_9", "SEM3_CREDIT HOURS _9", "SEM3_GRADE POINT_9", "SEM3_CREDIT POINTS_9", "IV SEM", "SEM4_COURSE CODE_1", "SEM4_COURSE TITLE_1", "SEM4_CREDIT HOURS _1", "SEM4_GRADE POINT_1", "SEM4_CREDIT POINTS_1", "SEM4_COURSE CODE_2", "SEM4_COURSE TITLE_2", "SEM4_CREDIT HOURS _2", "SEM4_GRADE POINT_2", "SEM4_CREDIT POINTS_2", "SEM4_COURSE CODE_3", "SEM4_COURSE TITLE_3", "SEM4_CREDIT HOURS _3", "SEM4_GRADE POINT_3", "SEM4_CREDIT POINTS_3", "SEM4_COURSE CODE_4", "SEM4_COURSE TITLE_4", "SEM4_CREDIT HOURS _4", "SEM4_GRADE POINT_4", "SEM4_CREDIT POINTS_4", "SEM4_COURSE CODE_5", "SEM4_COURSE TITLE_5", "SEM4_CREDIT HOURS _5", "SEM4_GRADE POINT_5", "SEM4_CREDIT POINTS_5", "SEM4_COURSE CODE_6", "SEM4_COURSE TITLE_6", "SEM4_CREDIT HOURS _6", "SEM4_GRADE POINT_6", "SEM4_CREDIT POINTS_6", "SEM4_COURSE CODE_7", "SEM4_COURSE TITLE_7", "SEM4_CREDIT HOURS _7", "SEM4_GRADE POINT_7", "SEM4_CREDIT POINTS_7", "SEM4_COURSE CODE_8", "SEM4_COURSE TITLE_8", "SEM4_CREDIT HOURS _8", "SEM4_GRADE POINT_8", "SEM4_CREDIT POINTS_8", "SEM4_COURSE CODE_9", "SEM4_COURSE TITLE_9", "SEM4_CREDIT HOURS _9", "SEM4_GRADE POINT_9", "SEM4_CREDIT POINTS_9", "SEM4_COURSE CODE_10", "SEM4_COURSE TITLE_10", "SEM4_CREDIT HOURS _10", "SEM4_GRADE POINT_10", "SEM4_CREDIT POINTS_10", "V SEM", "SEM5_COURSE CODE_1", "SEM5_COURSE TITLE_1", "SEM5_CREDIT HOURS _1", "SEM5_GRADE POINT_1", "SEM5_CREDIT POINTS_1", "SEM5_COURSE CODE_2", "SEM5_COURSE TITLE_2", "SEM5_CREDIT HOURS _2", "SEM5_GRADE POINT_2", "SEM5_CREDIT POINTS_2", "SEM5_COURSE CODE_3", "SEM5_COURSE TITLE_3", "SEM5_CREDIT HOURS _3", "SEM5_GRADE POINT_3", "SEM5_CREDIT POINTS_3", "SEM5_COURSE CODE_4", "SEM5_COURSE TITLE_4", "SEM5_CREDIT HOURS _4", "SEM5_GRADE POINT_4", "SEM5_CREDIT POINTS_4", "SEM5_COURSE CODE_5", "SEM5_COURSE TITLE_5", "SEM5_CREDIT HOURS _5", "SEM5_GRADE POINT_5", "SEM5_CREDIT POINTS_5", "SEM5_COURSE CODE_6", "SEM5_COURSE TITLE_6", "SEM5_CREDIT HOURS _6", "SEM5_GRADE POINT_6", "SEM5_CREDIT POINTS_6", "SEM5_COURSE CODE_7", "SEM5_COURSE TITLE_7", "SEM5_CREDIT HOURS _7", "SEM5_GRADE POINT_7", "SEM5_CREDIT POINTS_7", "SEM5_COURSE CODE_8", "SEM5_COURSE TITLE_8", "SEM5_CREDIT HOURS _8", "SEM5_GRADE POINT_8", "SEM5_CREDIT POINTS_8", "SEM5_COURSE CODE_9", "SEM5_COURSE TITLE_9", "SEM5_CREDIT HOURS _9", "SEM5_GRADE POINT_9", "SEM5_CREDIT POINTS_9", "SEM5_COURSE CODE_10", "SEM5_COURSE TITLE_10", "SEM5_CREDIT HOURS _10", "SEM5_GRADE POINT_10", "SEM5_CREDIT POINTS_10", "VI SEM", "SEM6_COURSE CODE_1", "SEM6_COURSE TITLE_1", "SEM6_CREDIT HOURS _1", "SEM6_GRADE POINT_1", "SEM6_CREDIT POINTS_1", "SEM6_COURSE CODE_2", "SEM6_COURSE TITLE_2", "SEM6_CREDIT HOURS _2", "SEM6_GRADE POINT_2", "SEM6_CREDIT POINTS_2", "SEM6_COURSE CODE_3", "SEM6_COURSE TITLE_3", "SEM6_CREDIT HOURS _3", "SEM6_GRADE POINT_3", "SEM6_CREDIT POINTS_3", "SEM6_COURSE CODE_4", "SEM6_COURSE TITLE_4", "SEM6_CREDIT HOURS _4", "SEM6_GRADE POINT_4", "SEM6_CREDIT POINTS_4", "SEM6_COURSE CODE_5", "SEM6_COURSE TITLE_5", "SEM6_CREDIT HOURS _5", "SEM6_GRADE POINT_5", "SEM6_CREDIT POINTS_5", "SEM6_COURSE CODE_6", "SEM6_COURSE TITLE_6", "SEM6_CREDIT HOURS _6", "SEM6_GRADE POINT_6", "SEM6_CREDIT POINTS_6", "SEM6_COURSE CODE_7", "SEM6_COURSE TITLE_7", "SEM6_CREDIT HOURS _7", "SEM6_GRADE POINT_7", "SEM6_CREDIT POINTS_7", "SEM6_COURSE CODE_8", "SEM6_COURSE TITLE_8", "SEM6_CREDIT HOURS _8", "SEM6_GRADE POINT_8", "SEM6_CREDIT POINTS_8", "SEM6_COURSE CODE_9", "SEM6_COURSE TITLE_9", "SEM6_CREDIT HOURS _9", "SEM6_GRADE POINT_9", "SEM6_CREDIT POINTS_9", "SEM6_COURSE CODE_10", "SEM6_COURSE TITLE_10", "SEM6_CREDIT HOURS _10", "SEM6_GRADE POINT_10", "SEM6_CREDIT POINTS_10", "VII SEM", "SEM7_COURSE CODE_1", "SEM7_COURSE TITLE_1", "SEM7_CREDIT HOURS _1", "SEM7_GRADE POINT_1", "SEM7_CREDIT POINTS_1", "SEM7_COURSE CODE_2", "SEM7_COURSE TITLE_2", "SEM7_CREDIT HOURS _2", "SEM7_GRADE POINT_2", "SEM7_CREDIT POINTS_2", "SEM7_COURSE CODE_3", "SEM7_COURSE TITLE_3", "SEM7_CREDIT HOURS _3", "SEM7_GRADE POINT_3", "SEM7_CREDIT POINTS_3", "SEM7_COURSE CODE_4", "SEM7_COURSE TITLE_4", "SEM7_CREDIT HOURS _4", "SEM7_GRADE POINT_4", "SEM7_CREDIT POINTS_4", "SEM7_COURSE CODE_5", "SEM7_COURSE TITLE_5", "SEM7_CREDIT HOURS _5", "SEM7_GRADE POINT_5", "SEM7_CREDIT POINTS_5", "SEM7_COURSE CODE_6", "SEM7_COURSE TITLE_6", "SEM7_CREDIT HOURS _6", "SEM7_GRADE POINT_6", "SEM7_CREDIT POINTS_6", "SEM7_COURSE CODE_7", "SEM7_COURSE TITLE_7", "SEM7_CREDIT HOURS _7", "SEM7_GRADE POINT_7", "SEM7_CREDIT POINTS_7", "SEM7_COURSE CODE_8", "SEM7_COURSE TITLE_8", "SEM7_CREDIT HOURS _8", "SEM7_GRADE POINT_8", "SEM7_CREDIT POINTS_8", "VIII SEM", "SEM8_COURSE CODE_1", "SEM8_COURSE TITLE_1", "SEM8_CREDIT HOURS _1", "SEM8_GRADE POINT_1", "SEM8_CREDIT POINTS_1", "SEM8_COURSE CODE_2", "SEM8_COURSE TITLE_2", "SEM8_CREDIT HOURS _2", "SEM8_GRADE POINT_2", "SEM8_CREDIT POINTS_2", "TOTAL CREDITS_CREDIT HOURS", "TOTAL CREDITS_CREDIT POINTS", "OVERALL GRADE POINT AVERAGE", "DATE", "Photos of students");
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
			if($dropdown_template_id==3){
				$profile_path = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$value1[$photo_col];
				if (!file_exists($profile_path)) {   
					 array_push($profErrArr, $value1[$photo_col]);
				}
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
            //return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo does not exists following values : '.implode(',', $profErrArr)]);
            }
              
        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);
    }

  
}

