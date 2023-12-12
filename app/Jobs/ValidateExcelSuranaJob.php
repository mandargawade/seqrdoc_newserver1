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
class ValidateExcelSuranaJob
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
        $columnsCount1=8; //count($rowData1[0]);
        
        //echo $columnsCount1;
        //exit;
       
        if($dropdown_template_id==1){
            if($columnsCount1==8){
                $photo_col=151;
				//$columnArr=array("Unique ID No", "Stundents_Name", "Degree", "Registration_No", "Scheme", "Month_&_Year_of_Examination", "Sem", "MC_NO", "SI_No1", "Course_code1", "TP_Max1", "TP_Min1", "TP_Sec1", "IA_Max1", "IA_Sec1", "CT_Max1", "CT_Min1", "CT_Sec1", "Grade_Points1", "credits_Assigned1", "Credit_Points1", "Remark1", "SI_No2", "Course_code2", "TP_Max2", "TP_Min2", "TP_Sec2", "IA_Max2", "IA_Sec2", "CT_Max2", "CT_Min2", "CT_Sec2", "Grade_Points2", "credits_Assigned2", "Credit_Points2", "Remark2", "SI_No3", "Course_code3", "TP_Max3", "TP_Min3", "TP_Sec3", "IA_Max3", "IA_Sec3", "CT_Max3", "CT_Min3", "CT_Sec3", "Grade_Points3", "credits_Assigned3", "Credit_Points3", "Remark3", "SI_No4", "Course_code4", "TP_Max4", "TP_Min4", "TP_Sec4", "IA_Max4", "IA_Sec4", "CT_Max4", "CT_Min4", "CT_Sec4", "Grade_Points4", "credits_Assigned4", "Credit_Points4", "Remark4", "SI_No5", "Course_code5", "TP_Max5", "TP_Min5", "TP_Sec5", "IA_Max5", "IA_Sec5", "CT_Max5", "CT_Min5", "CT_Sec5", "Grade_Points5", "credits_Assigned5", "Credit_Points5", "Remark5", "SI_No6", "Course_code6", "TP_Max6", "TP_Min6", "TP_Sec6", "IA_Max6", "IA_Sec6", "CT_Max6", "CT_Min6", "CT_Sec6", "Grade_Points6", "credits_Assigned6", "Credit_Points6", "Remark6", "SI_No7", "Course_code7", "TP_Max7", "TP_Min7", "TP_Sec7", "IA_Max7", "IA_Sec7", "CT_Max7", "CT_Min7", "CT_Sec7", "Grade_Points7", "credits_Assigned7", "Credit_Points7", "Remark7", "SI_No8", "Course_code8", "TP_Max8", "TP_Min8", "TP_Sec8", "IA_Max8", "IA_Sec8", "CT_Max8", "CT_Min8", "CT_Sec8", "Grade_Points8", "credits_Assigned8", "Credit_Points8", "Remark8", "SI_No9", "Course_code9", "TP_Max9", "TP_Min9", "TP_Sec9", "IA_Max9", "IA_Sec9", "CT_Max9", "CT_Min9", "CT_Sec9", "Grade_Points9", "credits_Assigned9", "Credit_Points9", "Remark9", "GrandTotal_CT_Max", "GrandTotal_CT_Min", "GrandTotal_CT_Sec", "GrandTotal_GP", "GrandTotal_CA", "GrandTotal_CP", "SR_SGPA", "SR_Alpha-signed_Grade", "SR_Credits_Earned", "SR_semester_%_age_Of_Marks", "SR_Class_description", "PR_SGPA", "PR_Alpha-signed_Grade", "PR_Credits_Earned", "PR_Program_%_age_Of_Marks", "PR_Class_description", "Date", "Student Photo");
				$columnArr=array("Unique ID No", "Stundents_Name", "Degree", "Registration_No", "Scheme", "Month_&_Year_of_Examination", "Sem", "MC_NO");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    //return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==2){
            
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

