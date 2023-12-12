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
class ValidateExcelICCSJob
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
              $rowData1[0] = array_filter($rowData1[0]);
                // dd($rowData);

             // print_r($rowData1[0]);
              

                 $columnsCount1=count($rowData1[0]);
               //      print_r($columnsCount1);
             
//exit;
                if($columnsCount1==106){

                    $columnArr=array('UNIQUE ID','STATEMENT OF GRADESS FOR','SEAT NO','PUN Code','Exam','CENTRE','DATE','NAME','MOTHER','PERM REG NO','ELIGIBILITY NO','SUBJECT CODE 1','SUBJECT NAME 1','CREDITS 1','GRADES 1','GRADES POINT 1','CREDITS POINT 1','SUBJECT CODE 2','SUBJECT NAME 2','CREDITS 2','GRADES 2','GRADES POINT 2','CREDITS POINT 2','SUBJECT CODE 3','SUBJECT NAME 3','CREDITS 3','GRADES 3','GRADES POINT 3','CREDITS POINT 3','SUBJECT CODE 4','SUBJECT NAME 4','CREDITS 4','GRADES 4','GRADES POINT 4','CREDITS POINT 4','SUBJECT CODE 5','SUBJECT NAME 5','CREDITS 5','GRADES 5','GRADES POINT 5','CREDITS POINT 5','SUBJECT CODE 6','SUBJECT NAME 6','CREDITS 6','GRADES 6','GRADES POINT 6','CREDITS POINT 6','SUBJECT CODE 7','SUBJECT NAME 7','CREDITS 7','GRADES 7','GRADES POINT 7','CREDITS POINT 7','SUBJECT CODE 8','SUBJECT NAME 8','CREDITS 8','GRADES 8','GRADES POINT 8','CREDITS POINT 8','SUBJECT CODE 9','SUBJECT NAME 9','CREDITS 9','GRADES 9','GRADES POINT 9','CREDITS POINT 9','SUBJECT CODE 10','SUBJECT NAME 10','CREDITS 10','GRADES 10','GRADES POINT 10','CREDITS POINT 10','SUBJECT CODE 11','SUBJECT NAME 11','CREDITS 11','GRADES 11','GRADES POINT 11','CREDITS POINT 11','SUBJECT CODE 12','SUBJECT NAME 12','CREDITS 12','GRADES 12','GRADES POINT 12','CREDITS POINT 12','SUBJECT CODE 13','SUBJECT NAME 13','CREDITS 13','GRADES 13','GRADES POINT 13','CREDITS POINT 13','SUBJECT CODE 14','SUBJECT NAME 14','CREDITS 14','GRADES 14','GRADES POINT 14','CREDITS POINT 14','REGISTERED CREDIT','EARNED CREDIT','TOTAL CREDITS POINTS','SGPA','CUMM. CREDITS EARNED','GRADES POINT EARNED','CGPA','RESULT','TOTAL CREDITS EARNED','MED. OF INSTR','GRACE MARKS');
                        $mismatchColArr=array_diff($rowData1[0], $columnArr);
                        //print_r($mismatchColArr);
                        if(count($mismatchColArr)>0){
                            
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1 : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                        }




                }else{
                    return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
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
                    $message = Array('success'=>false,'type' => 'error', 'message' => 'Excel has more than 1 column having same name. i.e. : '.$duplicate_columns);
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
                    //array_push($profArr, $value1[141]);
                    /*
                    $profile_path = public_path().'\\'.$subdomain[0].'\backend\templates\100\\'.$value1[141];
                    if (!file_exists($profile_path)) {   
                         array_push($profErrArr, $value1[141]);
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
               /*$mismatchProfArr = array_intersect($profArr, array_unique(array_diff_key($profArr, array_unique($profArr))));

                if(count($mismatchProfArr)>0){
                    return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo contains following duplicate values : '.implode(',', $mismatchProfArr)]);
                    }
                 if(count($profErrArr)>0){
                    return response()->json(['success'=>false,'type' => 'error','message' => 'Sheet1 : Photo does not exists following values : '.implode(',', $profErrArr)]);
                    }*/
                      
                return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);
    }

  
}
