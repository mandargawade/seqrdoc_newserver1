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
class ValidateExcelWOXSENSOMsevenJob
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
        $rowData2=$pdf_data['rowData2'];
        $auth_site_id=$pdf_data['auth_site_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        
              $rowData1[0] = array_filter($rowData1[0]);
                
                $columnsCount1=count($rowData1[0]);
                
                $columnsCount2=count($rowData2[0]);

                if($columnsCount1==28&&$columnsCount2==6){

                    $columnArr=array('Unique_ID', 'Student_Name', 'Mother_Name', 'Father_Name', 'Program', 'Academic_Year', 'PGID', 'Admission_ID', 'TC-1', 'T1_GPA', 'TC-2', 'T2_GPA', 'TC-3', 'T3_GPA', 'TC-4', 'T4_GPA', 'TC-5', 'T5_GPA', 'TC-6', 'T6_GPA', 'TC-7', 'T7_GPA', 'Total_Credits_Earned', 'CGPA', 'Serial_No', 'Major1', 'Major2', 'Major/ Minor');
                        $mismatchColArr=array_diff($rowData1[0], $columnArr);
                    
                        if(count($mismatchColArr)>0){
                            
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                        }

                    $columnArr=array('PGID','Term','Term1 Course Code1','Term1 Course Name1','Term1 Credits1','Term1 Grade1');
                        $mismatchColArr=array_diff($rowData2[0], $columnArr);
                        
                        if(count($mismatchColArr)>0){
                            
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet2 : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                        }

                }elseif($columnsCount1!=24){
                    return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of sheet1 do not matched!']);
                }else{
                    return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of sheet2 do not matched!']);
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
               
                $rowData2[0] = array_filter($rowData2[0]);
                
                $ab = array_count_values($rowData2[0]);

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
                
                $sandboxCheck = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                $old_rows = 0;
                $new_rows = 0;
                foreach ($rowData1 as $key1 => $value1) {
                    $serial_no=$value1[6];
                    array_push($blobArr, $serial_no);
                 //  print_r($value1[6]);
                    $profile_path_jpg = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.trim($value1[7]).'.jpg';
                    $profile_path_png = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.trim($value1[7]).'.png';

                    //$profile_path_jpg = public_path()."/".$subdomain[0]."/backend/templates/100/".trim($value1[6]).'.jpg';

                    //dd($profile_path_jpg);

                    if (!file_exists($profile_path_jpg)) {
                        if(!file_exists($profile_path_png)){
                          array_push($profErrArr, $value1[6]);
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
             
                if(count($profErrArr)>0){
                    return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo does not exists following values : '.implode(',', $profErrArr)]);
                }
                      
                return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);
    }



  
}
