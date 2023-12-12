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

class ValidateExcelBiharEstampCertificateUtilityJob
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
        // code for check required field
        $data = [];
        $pdf_data = $this->pdf_data;
        $dropdown_template_id=$pdf_data['dropdown_template_id'];

        foreach($pdf_data['rowData1'] as $key => $rowData)
        {
            $data[] = $key;
        }
        
        switch ($dropdown_template_id) {
            case 1:
                    $validateArr = ['Certificate No.', 'Certificate Issue Date', 'Total Amount (Rs.)', 'Party Name','Purchased by', 'Printer'];

                    foreach($validateArr as $arr){
                        if(!in_array($arr, $data)){
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : Certificate No., Certificate Issue Date, Total Amount (Rs.)', 'Party Name', 'Purchased by']);
                        }
                    }
                break;
            case 2:
                    $validateArr = ['Date & Time', 'Name of the ACC/ Registered User', 'e-Court Fee Receipt No.', 'Printer'];

                    foreach($validateArr as $arr){
                        if(!in_array($arr, $data)){
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : Date & Time, Name of the ACC/ Registered User, e-Court Fee Receipt No.,Printer']);
                        }
                    }
                break;
            case 3:
                    $validateArr = ['transaction_id','stamp_type'];

                    foreach($validateArr as $arr){
                        if(!in_array($arr, $data)){
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns :transaction_id,stamp_type']);
                        }
                    }
                break;
            case 4:
                   $validateArr = ['transaction_id','stamp_type'];

                    foreach($validateArr as $arr){
                        if(!in_array($arr, $data)){
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns :transaction_id,stamp_type']);
                        }
                    }
                break;
            
            default:
                $validateArr = ['Certificate No.', 'Certificate Issue Date', 'Total Amount (Rs.)', 'Party Name','Purchased by', 'Printer'];

                    foreach($validateArr as $arr){
                        if(!in_array($arr, $data)){
                            return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : Certificate No., Certificate Issue Date, Total Amount (Rs.)', 'Party Name', 'Purchased by']);
                        }
                    }
                break;
        }
        

        return response()->json(['success'=>true,'type' => 'success', 'message' => 'success','old_rows'=>1,'new_rows'=>1]);

        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);

        $auth_site_id=$pdf_data['auth_site_id'];
        $dropdown_template_id=$pdf_data['dropdown_template_id'];
        $rowData1 = array_filter($data);
        $columnsCount1=count($rowData1);
        
       
        if($dropdown_template_id==1){
            if($columnsCount1==18){
                // $photo_col=151;
                $columnArr=array("Certificate No.", "Certificate Issued Date", "Account Reference", "Unique Doc. Reference", "Purchased by", "Description of Document", "Property Description", "Consideration Price (Rs.)", "First Party", "Second Party", "Stamp Duty Paid By", "Stamp Duty Paid (Rs.)", "Reg. fee (Rs.)", "LLR & P Fee (Rs.)", "Miscellaneous Fee (Rs.)", "Discore SC (Rs.)", "Total Amount (Rs.)", "Paper Number","Printer");


                $mismatchColArr=array_diff($data, $columnArr);
                if(count($mismatchColArr)>0){                    
                    return response()->json(['success'=>false,'type' => 'error', 'message' => 'Sheet1/Json : Column names not matching as per requirement. Please check columns : '.implode(',', $mismatchColArr)]);
                }
            }else{
                return response()->json(['success'=>false,'type' => 'error', 'message'=>'Columns count of excel do not matched!']);
            }
        }elseif($dropdown_template_id==2){
            
        }
        $ab = array_count_values($rowData1);

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
        
        unset($rowData1);
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

