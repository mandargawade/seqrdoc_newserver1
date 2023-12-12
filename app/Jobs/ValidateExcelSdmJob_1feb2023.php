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
class ValidateExcelSdmJob
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
            if($columnsCount1==92){
                $columnArr=array("MC_No.", "Name_of_the_Student", "University_Reg_No.", "Year", "Specialization", "MONTH YEAR - MAIN EXAMINATION", "Sr_no1", "Subject1", "UET_Max1", "UET_Min1", "UET_Sec1", "P_Max1", "P_Min1", "P_Sec1", "V_Max1", "V_Min1", "V_Sec1", "Sr_no2", "Subject2", "UET_Max2", "UET_Min2", "UET_Sec2", "P_Max2", "P_Min2", "P_Sec2", "V_Max2", "V_Min2", "V_Sec2", "Sr_no3", "Subject3", "UET_Max3", "UET_Min3", "UET_Sec3", "P_Max3", "P_Min3", "P_Sec3", "V_Max3", "V_Min3", "V_Sec3", "Sr_no4", "Subject4", "UET_Max4", "UET_Min4", "UET_Sec4", "P_Max4", "P_Min4", "P_Sec4", "V_Max4", "V_Min4", "V_Sec4", "Sr_no5", "Subject5", "UET_Max5", "UET_Min5", "UET_Sec5", "P_Max5", "P_Min5", "P_Sec5", "V_Max5", "V_Min5", "V_Sec5", "Sr_no6", "Subject6", "UET_Max6", "UET_Min6", "UET_Sec6", "P_Max6", "P_Min6", "P_Sec6", "V_Max6", "V_Min6", "V_Sec6", "T_Max1", "T_Min1", "T_Sec1", "T_Max2", "T_Min2", "T_Sec2", "Remark1", "Remark2", "Grand_Total_Max", "Grand_Total_Min", "Grand_Total_Sec", "Grand_Total_In_Words", "Percentage", "Result", "Date", "Photo", "Batch_of", "Aadhar_Number", "DOB", "Course");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==2){
            if($columnsCount1==12){
                $columnArr=array("Registration_No", "Student_Name", "Programme_Name", "Examination_held_in", "PDC_No", "Date", "Student_Image", "Batch_of", "Aadhar_No", "Course", "Year", "DOB");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==3){
            if($columnsCount1>5){
                $columnArr=array("MC_NO", "Year", "Examination", "Name_of_the_Student", "University_Reg_no");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    //return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==4 || $dropdown_template_id==17){
            if($columnsCount1==13){
                $columnArr=array("Registration_No", "Student_Name", "Programme_Name", "Examination_held_in", "Degree", "PDC_No", "Date", "Student_Image", "Batch_of", "Aadhar_No", "Course", "Year", "DOB");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==5){
            if($columnsCount1>5){
                $columnArr=array("MC_NO", "Year", "Examination", "Name_of_the_Student", "University_Reg_no");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    //return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==6){
            if($columnsCount1==81){
                $columnArr=array("MC_No.", "Name_of_the_Student", "University_Reg_No.", "Year", "Specialization", "MONTH YEAR - MAIN EXAMINATION", "Sr_no1", "Subject1", "UET_Max1", "UET_Min1", "UET_Sec1", "P_Max1", "P_Min1", "P_Sec1", "V_Max1", "V_Min1", "V_Sec1", "Sr_no2", "Subject2", "UET_Max2", "UET_Min2", "UET_Sec2", "P_Max2", "P_Min2", "P_Sec2", "V_Max2", "V_Min2", "V_Sec2", "Sr_no3", "Subject3", "UET_Max3", "UET_Min3", "UET_Sec3", "P_Max3", "P_Min3", "P_Sec3", "V_Max3", "V_Min3", "V_Sec3", "Sr_no4", "Subject4", "UET_Max4", "UET_Min4", "UET_Sec4", "P_Max4", "P_Min4", "P_Sec4", "V_Max4", "V_Min4", "V_Sec4", "Sr_no5", "Subject5", "UET_Max5", "UET_Min5", "UET_Sec5", "P_Max5", "P_Min5", "P_Sec5", "V_Max5", "V_Min5", "V_Sec5", "T_Max1", "T_Min1", "T_Sec1", "T_Max2", "T_Min2", "T_Sec2", "Remark1", "Remark2", "Grand_Total_Max", "Grand_Total_Min", "Grand_Total_Sec", "Grand_Total_In_Words", "Percentage", "Result", "Date", "Photo", "Batch_of", "Aadhar_Number", "DOB", "Course");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==7){
            if($columnsCount1>5){
                $columnArr=array("MC_NO", "Year", "Examination", "Name_of_the_Student", "University_Reg_no");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    //return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==8){
            if($columnsCount1==121){
                $columnArr=array("MC_No.", "Name_of_the_Student", "University_Reg_No.", "Year", "Specialization", "MONTH YEAR - MAIN EXAMINATION", "Sr_no1", "Subject1", "T _Max1", "T_Min1", "T_OBT 1", "Sr_no2", "Subject2", "T _Max2", "T_Min2", "T_OBT 2", "TA_MAX1", "TA_Min1", "TA_OBT1", "PV_MAX1", "PV_MIN1", "PV_OBT 1", "T_MAX1", "T_MIN1", "T_OBT1", "REMARK1", "Sr_no3", "Subject3", "T _Max3", "T_Min3", "T_OBT 3", "Sr_no4", "Subject4", "T _Max4", "T_Min4", "T_OBT 4", "TA_MAX2", "TA_Min2", "TA_OBT2", "PV_MAX2", "PV_MIN2", "PV_OBT 2", "T_MAX2", "T_MIN2", "T_OBT2", "REMARK2", "Sr_no5", "Subject5", "T _Max5", "T_Min5", "T_OBT 5", "Sr_no6", "Subject6", "T _Max6", "T_Min6", "T_OBT 6", "TA_MAX3", "TA_Min3", "TA_OBT3", "PV_MAX3", "PV_MIN3", "PV_OBT 3", "T_MAX3", "T_MIN3", "T_OBT3", "REMARK3", "Total_(UE)MAX", "Total_(UE)MIN", "Total_(UE)OBT", "Total_(IA)MAX", "Total_(IA)MIN", "Total_(IA)OBT", "GRAND_TOTAL_(UE+IA)MAX", "GRAND_TOTAL_(UE+IA)MIN", "GRAND_TOTAL_(UE+IA)OBT", "Grand_Total_In_Words(UE+IA)", "Percentage(UE+IA)", "Result", "(IA)SL_no1", "(IA)Subject1", "T_Max1", "T_Min1", "T_OBT1", "P_Max1", "P_Min1", "P_OBT1", "T_MAX1", "T_MIN1", "T_OBT1", "(IA)SL_no2", "(IA)Subject2", "T_Max2", "T_Min2", "T_OBT2", "P_Max2", "P_Min2", "P_OBT2", "T_MAX2", "T_MIN2", "T_OBT2", "(IA)SL_no3", "(IA)Subject3", "T_Max3", "T_Min3", "T_OBT3", "P_Max3", "P_Min3", "P_OBT3", "T_MAX3", "T_MIN3", "T_OBT3", "ELIGIBILITY FOR UE ", "GRAND TOTAL MAX ", "GRAND TOTAL MIN ", "GRAND TOTAL OBT ", "Date", "Photo", "Batch_of", "Aadhar_Number", "DOB", "Course");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==9){
            if($columnsCount1==137){
                $columnArr=array("MC_No.", "Name_of_the_Student", "University_Reg_No.", "Year", "Specialization", "MONTH YEAR - MAIN EXAMINATION", "Sr_no1", "Subject1", "T_Max1", "T_Min1", "T_OBT ", "TG_Max1", "TG_Min1", "TG_OBT", "PV_MAX1", "PV_MIN1", "PV_OBT1", "T_MAX1", "T_MIN1", "T_OBT 1", "REMARK1", "Sr_no2", "Subject2", "T_Max2", "T_Min2", "T_OBT 2", "TG_Max1", "TG_Min2", "TG_OBT2", "PV_MAX2", "PV_MIN2", "PV_OBT2", "T_MAX2", "T_MIN2", "T_OBT 2", "REMARK2", "Sr_no3", "Subject3", "T_Max3", "T_Min3", "T_OBT3 ", "TG_Max3", "TG_Min3", "TG_OBT3", "PV_MAX3", "PV_MIN3", "PV_OBT3", "T_MAX3", "T_MIN3", "T_OBT 3", "REMARK3", "Sr_no4", "Subject4", "T_Max4", "T_Min4", "T_OBT 4", "Sr_no5", "Subject5", "T_Max5", "T_Min5", "T_OBT 5", "TG_Max5", "TG_Min5", "TG_OBT5", "PV_MAX5", "PV_MIN5", "PV_OBT5", "T_MAX5", "T_MIN5", "T_OBT 5", "REMARK5", "Total_(UE)MAX", "Total_(UE)MIN", "Total_(UE)OBT", "Total_(IA)MAX", "Total_(IA)MIN", "Total_(IA)OBT", "GRAND_TOTAL_(UE+IA)MAX", "GRAND_TOTAL_(UE+IA)MIN", "GRAND_TOTAL_(UE+IA)OBT", "Grand_Total_In_Words(UE+IA)", "Percentage(UE+IA)", "Result", "(IA)SL_no1", "(IA)Subject1", "T_Max1", "T_Min1", "T_OBT1", "P_Max1", "P_Min1", "P_OBT1", "T_MAX1", "T_MIN1", "T_OBT1", "(IA)SL_no2", "(IA)Subject2", "T_Max2", "T_Min2", "T_OBT2", "P_Max2", "P_Min2", "P_OBT2", "T_MAX2", "T_MIN2", "T_OBT2", "(IA)SL_no3", "(IA)Subject3", "T_Max3", "T_Min3", "T_OBT3", "P_Max3", "P_Min3", "P_OBT3", "T_MAX3", "T_MIN3", "T_OBT3", "(IA)SL_no4", "(IA)Subject4", "T_Max4", "T_Min4", "T_OBT4", "P_Max4", "P_Min4", "P_OBT4", "T_MAX4", "T_MIN4", "T_OBT4", "ELIGIBILITY FOR UE ", "GRAND TOTAL MAX ", "GRAND TOTAL MIN ", "GRAND TOTAL OBT ", "Date", "Photo", "Batch_of", "Aadhar_Number", "DOB", "Course");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==10){
            if($columnsCount1==147){
                $columnArr=array("MC_No.", "Name_of_the_Student", "University_Reg_No.", "Year", "Specialization", "MONTH YEAR - MAIN EXAMINATION", "Sr_no1", "Subject1", "T_Max1", "T_Min1", "T_OBT ", "TG_Max1", "TG_Min1", "TG_OBT", "PV_MAX1", "PV_MIN1", "PV_OBT1", "T_MAX1", "T_MIN1", "T_OBT 1", "REMARK1", "Sr_no2", "Subject2", "T _Max2", "T_Min2", "T_OBT 2", "Sr_no3", "Subject3", "T _Max3", "T_Min3", "T_OBT 3", "TA_MAX3", "TA_Min3", "TA_OBT2", "PV_MAX2", "PV_MIN2", "PV_OBT 2", "T_MAX2", "T_MIN2", "T_OBT2", "REMARK2", "Sr_no4", "Subject4", "T _Max4", "T_Min4", "T_OBT 4", "Sr_no5", "Subject5", "T _Max5", "T_Min5", "T_OBT 5", "TA_MAX3", "TA_Min3", "TA_OBT3", "PV_MAX3", "PV_MIN3", "PV_OBT 3", "T_MAX3", "T_MIN3", "T_OBT3", "REMARK3", "Sr_no6", "Subject6", "T _Max6", "T_Min6", "T_OBT 6", "Sr_no7", "Subject7", "T _Max7", "T_Min7", "T_OBT 7", "TA_MAX4", "TA_Min4", "TA_OBT4", "PV_MAX4", "PV_MIN4", "PV_OBT 4", "T_MAX4", "T_MIN4", "T_OBT4", "REMARK4", "Total_(UE)MAX", "Total_(UE)MIN", "Total_(UE)OBT", "Total_(IA)MAX", "Total_(IA)MIN", "Total_(IA)OBT", "GRAND_TOTAL_(UE+IA)MAX", "GRAND_TOTAL_(UE+IA)MIN", "GRAND_TOTAL_(UE+IA)OBT", "Grand_Total_In_Words(UE+IA)", "Percentage(UE+IA)", "Result", "(IA)SL_no1", "(IA)Subject1", "T_Max1", "T_Min1", "T_OBT1", "P_Max1", "P_Min1", "P_OBT1", "T_MAX1", "T_MIN1", "T_OBT1", "(IA)SL_no2", "(IA)Subject2", "T_Max2", "T_Min2", "T_OBT2", "P_Max2", "P_Min2", "P_OBT2", "T_MAX2", "T_MIN2", "T_OBT2", "(IA)SL_no3", "(IA)Subject3", "T_Max3", "T_Min3", "T_OBT3", "P_Max3", "P_Min3", "P_OBT3", "T_MAX3", "T_MIN3", "T_OBT3", "(IA)SL_no4", "(IA)Subject4", "T_Max4", "T_Min4", "T_OBT4", "P_Max4", "P_Min4", "P_OBT4", "T_MAX4", "T_MIN4", "T_OBT4", "ELIGIBILITY FOR UE", "GRAND TOTAL MAX", "GRAND TOTAL MIN", "GRAND TOTAL OBT", "Date", "Photo", "Batch_of", "Aadhar_Number", "DOB", "Course");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==11 || $dropdown_template_id==13){
            if($columnsCount1>6){
                $columnArr=array("MC_No.", "Name_of_the_Student", "University_Reg_No.", "Year", "Specialization", "MONTH YEAR - MAIN EXAMINATION");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    //return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==12){
            if($columnsCount1>5){
                $columnArr=array("MC_NO", "Year", "Examination", "Name_of_the_Student", "University_Reg_no");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    //return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==14){
            if($columnsCount1==51){
                $columnArr=array("MC_No.", "Name_of_the_Student", "University_Reg_No.", "Year", "Programme", "MONTH YEAR - MAIN EXAMINATION", "Sr_no1", "Subject1", "UET/P/V_Max1", "UET/P/V_Min1", "UET/P/V_Sec1", "Sr_no2", "Subject2", "UET/P/V_Max2", "UET/P/V_Min2", "UET/P/V_Sec2", "Sr_no3", "Subject3", "UET/P/V_Max3", "UET/P/V_Min3", "UET/P/V_Sec3", "Sr_no4", "Subject4", "UET/P/V_Max4", "UET/P/V_Min4", "UET/P/V_Sec4", "Sr_no5", "Subject5", "UET/P/V_Max5", "UET/P/V_Min5", "UET/P/V_Sec5", "T_Max1", "T_Min1", "T_Sec1", "T_Max2", "T_Min2", "T_Sec2", "Remarks1", "Remarks2", "Grand_Total_Max", "Grand_Total_Min", "Grand_Total_Sec", "Grand_Total_In_Words", "Percentage", "Result", "Date", "Photo", "Batch_of", "Aadhar_Number", "DOB", "Course");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==15){
            if($columnsCount1==27){
                $columnArr=array("MC_No.", "Name_of_the_Student", "University_Reg_No.", "Year", "Programme", "MONTH YEAR - MAIN EXAMINATION", "Sr_no1", "Subject1", "UET/P/V_Max1", "UET/P/V_Min1", "UET/P/V_Sec1", "T_Max1", "T_Min1", "T_Sec1", "Remarks1", "Grand_Total_Max", "Grand_Total_Min", "Grand_Total_Sec", "Grand_Total_In_Words", "Percentage", "Result", "Date", "Photo", "Batch_of", "Aadhar_Number", "DOB", "Course");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==16){
            if($columnsCount1>5){
                $columnArr=array("MC_NO", "Year", "Examination", "Name_of_the_Student", "University_Reg_no");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    //return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
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

