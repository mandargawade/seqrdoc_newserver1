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
class ValidateExcelAnuJob
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
            if($columnsCount1==137){
                $photo_col=132;
				$columnArr=array("Unique ID", "Student ID", "First name", "Last name", "Section", "DOB", "Email", "Full name", "Batch", "Semester", "Programme", "Major", "RANK", "PRE SEM - TotCoreCrEarn", "PRE SEM - TotEleCrEarn", "TotCoreCrEarn", "TotEleCrEarn", "CoreCrEarn", "EleCrEarn", "PRE SEM - Total EarnCr", "PRE SEM - Total EarnCrPt", "TotEarnCr", "TotEarnCrPt", "SGPA", "SGPA Desc", "CGPA", "CGPA Desc", "EarnCrPt", "EnrolCr", "EarnCr", "Crse1Code", "Crse1Title", "Crse1CrEnrol", "Crse1CrEarn", "Crse1LetGr", "Crse1GrPts", "Crse2Code", "Crse2Title", "Crse2CrEnrol", "Crse2CrEarn", "Crse2LetGr", "Crse2GrPts", "Crse3Code", "Crse3Title", "Crse3CrEnrol", "Crse3CrEarn", "Crse3LetGr", "Crse3GrPts", "Crse4Code", "Crse4Title", "Crse4CrEnrol", "Crse4CrEarn", "Crse4LetGr", "Crse4GrPts", "Crse5Code", "Crse5Title", "Crse5CrEnrol", "Crse5CrEarn", "Crse5LetGr", "Crse5GrPts", "Crse6Code", "Crse6Title", "Crse6CrEnrol", "Crse6CrEarn", "Crse6LetGr", "Crse6GrPts", "Crse7Code", "Crse7Title", "Crse7CrEnrol", "Crse7CrEarn", "Crse7LetGr", "Crse7GrPts", "Crse8Code", "Crse8Title", "Crse8CrEnrol", "Crse8CrEarn", "Crse8LetGr", "Crse8GrPts", "Crse9Code", "Crse9Title", "Crse9CrEnrol", "Crse9CrEarn", "Crse9LetGr", "Crse9GrPts", "Crse10Code", "Crse10Title", "Crse10CrEnrol", "Crse10CrEarn", "Crse10LetGr", "Crse10GrPts", "Crse11Code", "Crse11Title", "Crse11CrEnrol", "Crse11CrEarn", "Crse11LetGr", "Crse11GrPts", "Crse12Code", "Crse12Title", "Crse12CrEnrol", "Crse12CrEarn", "Crse12LetGr", "Crse12GrPts", "Crse13Code", "Crse13Title", "Crse13CrEnrol", "Crse13CrEarn", "Crse13LetGr", "Crse13GrPts", "Crse14Code", "Crse14Title", "Crse14CrEnrol", "Crse14CrEarn", "Crse14LetGr", "Crse14GrPts", "Crse15Code", "Crse15Title", "Crse15CrEnrol", "Crse15CrEarn", "Crse15LetGr", "Crse15GrPts", "Crse16Code", "Crse16Title", "Crse16CrEnrol", "Crse16CrEarn", "Crse16LetGr", "Crse16GrPts", "Session", "PDF Name", "QR Output", "QR Badge", "Issue date", "QR Code No", "Photo", "Name1", "Designation1", "Name2", "Designation2");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }
		elseif($dropdown_template_id==2){
            /*if($columnsCount1==128){
                $photo_col=127;
				$columnArr=array("Unique ID", "Student ID", "First name", "Last name", "Section", "DOB", "Email", "Full name", "Batch", "Semester", "Programme", "Major", "RANK", "PRE SEM - TotCoreCrEarn", "PRE SEM - TotEleCrEarn", "TotCoreCrEarn", "TotEleCrEarn", "CoreCrEarn", "EleCrEarn", "PRE SEM - Total EarnCr", "PRE SEM - Total EarnCrPt", "TotEarnCr", "TotEarnCrPt", "SGPA", "SGPA Desc", "CGPA", "CGPA Desc", "EarnCrPt", "EnrolCr", "EarnCr", "Crse1Code", "Crse1Title", "Crse1CrEnrol", "Crse1CrEarn", "Crse1LetGr", "Crse1GrPts", "Crse2Code", "Crse2Title", "Crse2CrEnrol", "Crse2CrEarn", "Crse2LetGr", "Crse2GrPts", "Crse3Code", "Crse3Title", "Crse3CrEnrol", "Crse3CrEarn", "Crse3LetGr", "Crse3GrPts", "Crse4Code", "Crse4Title", "Crse4CrEnrol", "Crse4CrEarn", "Crse4LetGr", "Crse4GrPts", "Crse5Code", "Crse5Title", "Crse5CrEnrol", "Crse5CrEarn", "Crse5LetGr", "Crse5GrPts", "Crse6Code", "Crse6Title", "Crse6CrEnrol", "Crse6CrEarn", "Crse6LetGr", "Crse6GrPts", "Crse7Code", "Crse7Title", "Crse7CrEnrol", "Crse7CrEarn", "Crse7LetGr", "Crse7GrPts", "Crse8Code", "Crse8Title", "Crse8CrEnrol", "Crse8CrEarn", "Crse8LetGr", "Crse8GrPts", "Crse9Code", "Crse9Title", "Crse9CrEnrol", "Crse9CrEarn", "Crse9LetGr", "Crse9GrPts", "Crse10Code", "Crse10Title", "Crse10CrEnrol", "Crse10CrEarn", "Crse10LetGr", "Crse10GrPts", "Crse11Code", "Crse11Title", "Crse11CrEnrol", "Crse11CrEarn", "Crse11LetGr", "Crse11GrPts", "Crse12Code", "Crse12Title", "Crse12CrEnrol", "Crse12CrEarn", "Crse12LetGr", "Crse12GrPts", "Crse13Code", "Crse13Title", "Crse13CrEnrol", "Crse13CrEarn", "Crse13LetGr", "Crse13GrPts", "Crse14Code", "Crse14Title", "Crse14CrEnrol", "Crse14CrEarn", "Crse14LetGr", "Crse14GrPts", "Crse15Code", "Crse16Code", "Crse16Title", "Crse16CrEnrol", "Crse16CrEarn", "Crse16LetGr", "Crse16GrPts", "Session", "PDF Name", "QR Output", "QR Badge", "Issue date", "QR Code No", "Photo");
                $mismatchColArr=array_diff($rowData1[0], $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }*/
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

