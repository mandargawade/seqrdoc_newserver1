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
class ValidateExcelGalgotiasJob
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

        $pdf_data = $this->pdf_data;

        $rowData1=$pdf_data['rowData1'];
        $auth_site_id=$pdf_data['auth_site_id'];
              $rowData1[0] = array_filter($rowData1[0]);
                
                $columnsCount1=count($rowData1[0]);
                
                
                if($columnsCount1==17){

                        $columnArr=array('GUID','Enrollment_NO','Admission_No','Serial_No','Student_Name_E','Student_Name_H','CGPA_E','CGPA_H','Passing_E','Passing_H','Type','Programme_Name_E','Programme_Name_H','Specialization_E','Specialization_H','Division_E','Division_H');
                   
                        $mismatchColArr=array_diff($rowData1[0], $columnArr);
                    
                        if(count($mismatchColArr)>0){
                             $mismatchColArr2=array_diff($columnArr,$rowData1[0] );
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Excel : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr).'. Columns not found : '.implode(',', $mismatchColArr2)]);
                        }

                }else{
                    return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of sheet do not matched!']);
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


                $sandboxCheck = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
                $old_rows = 0;
                $new_rows = 0;
                foreach ($rowData1 as $key1 => $value1) {
                   
                    $serial_no=$value1[0];
                    if(empty($serial_no)){

                    return response()->json(['success'=>false,'type' => 'error','message' => 'Excel : GUID column contains empty values.']);
                    
                    }
                    array_push($blobArr, $serial_no);
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
                    return response()->json(['success'=>false,'type' => 'error','message' => 'Excel : sr_no contains following duplicate values : '.implode(',', $mismatchArr)]);
                    }
                    
                return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>$old_rows,'new_rows'=>$new_rows]);
    }

  
}
