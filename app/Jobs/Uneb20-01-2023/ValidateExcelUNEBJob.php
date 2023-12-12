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

class ValidateExcelUNEBJob
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
       
        if($dropdown_template_id==1||$dropdown_template_id==2||$dropdown_template_id==3){
            if($columnsCount1==31){//32
                // $photo_col=151;
               /* $columnArr=array("Unique No", "CentreNo", "CentreName", "IndexNo", "ExamYear", "Gender", "EntryCode", "Age", "Candidate_Name", "Result", "Aggregate", "Subject1", "Result1", "Subject2", "Result2", "Subject3", "Result3", "Subject4", "Result4", "Subject5", "Result5", "Subject6", "Result6", "Subject7", "Result7", "Subject8", "Result8", "Subject9", "Result9", "Subject10", "Result10", "Date");*/
                $columnArr=array("Unique No", "CentreNo", "CentreName", "IndexNo", "ExamYear", "Gender", "EntryCode", "Age", "Date_of_Birth", "Candidate_Name", "Result", "Aggregate", "Subject1", "Result1", "Subject2", "Result2", "Subject3", "Result3", "Subject4", "Result4", "Subject5", "Result5", "Subject6", "Result6", "Subject7", "Result7", "Subject8", "Result8", "Subject9", "Result9", "Subject10", "Result10");


                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }else{
            return response()->json(['success'=>false,'type' => 'error', 'message'=>'Template not found!']);
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

            $serial_no=str_replace('/', '_', $value1[2]).'_'.$value1[3].'_'.$dropdown_template_id;
            array_push($blobArr, $serial_no);
           // array_push($profArr, $value1[141]);

            /*$profile_path = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$value1[$photo_col];
            if (!file_exists($profile_path)) {   
                 //array_push($profErrArr, $value1[$photo_col]);
            }*/

            if($dropdown_template_id==2){
            $profile_path_org_v = '';

            $profilePicName= str_replace('/', '_', $value1[2]);
                //Indiviual Student ROW from SHEET 1
                
                $profile_path_org_jpg = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$profilePicName.'.jpg';
                $profile_path_org_jpeg = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$profilePicName.'.jpeg';
                $profile_path_org_png = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$profilePicName.'.png';
                
                if(file_exists($profile_path_org_jpg)){
                    $profile_path_org_v = $profile_path_org_jpg;
                }else if(file_exists($profile_path_org_jpeg)){
                    $profile_path_org_v = $profile_path_org_jpeg;
                }else if(file_exists($profile_path_org_png)){
                    $profile_path_org_v = $profile_path_org_png;
                }

              //  echo $profile_path_org;

                if (empty($profile_path_org_v)) {   
                 array_push($profErrArr, $value1[2]);
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
            return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo does not exists following values : '.implode(',', $profErrArr)]);
            }
              
        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);
    }

  
}

