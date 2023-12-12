<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\models\ExcelMergeLogs;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use Auth;

class processExcelJobRaisoni
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $merge_excel_data;
    public function __construct($merge_excel_data)
    {
        $this->merge_excel_data = $merge_excel_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Request $request,CheckUploadedFileOnAwsORLocalService $checkUploadedFileOnAwsOrLocal)
    {
        $get_file_aws_local_flag = $checkUploadedFileOnAwsOrLocal->checkUploadedFileOnAwsORLocal();
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        $s3=\Storage::disk('s3');

        $merge_excel_data = $this->merge_excel_data;

        //print_r($merge_excel_data);

        if($get_file_aws_local_flag->file_aws_local == '1'){
            $processExcel_directory = '/'.$subdomain[0].'/backend/processExcel/';
            if(!$s3->exists($processExcel_directory))
            {
                $s3->makeDirectory($processExcel_directory, 0777);  
            }
        }
        else{
            $processExcel_directory = public_path().'/'.$subdomain[0].'/backend/processExcel/';

            if(!is_dir($processExcel_directory)){
                //Directory does not exist, then create it.
                mkdir($processExcel_directory, 0777);
            }
        }

        $file_name =  $merge_excel_data['excel_data']->getClientOriginalName();
        $extension = pathinfo($file_name, PATHINFO_EXTENSION);

        $inputType = 'Xls';
        if($extension == 'xlsx' || $extension == 'XLSX'){

         $inputType = 'Xlsx';   
        }

        $file_path = public_path().'/'.$subdomain[0].'/backend/processExcel/'.$file_name;

        $merge_excel_data['excel_data']->move(public_path().'/'.$subdomain[0].'/backend/processExcel/',$file_name);
        if($get_file_aws_local_flag->file_aws_local == '1'){
            $file_path_aws = '/'.$subdomain[0].'/backend/processExcel/'.$file_name;

            $s3->put($file_path_aws,file_get_contents(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$file_name));
            
        }


        // get first sheet
        $objectFirstReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputType);
        $loadFirstSheet = $objectFirstReader->load($file_path);
        $firstSheet = $loadFirstSheet->getSheet(0);
        $secondSheet = $loadFirstSheet->getSheet(1);


        $firstSheetHighestColumn = $firstSheet->getHighestColumn();
        $secondSheetHighestColumn = $secondSheet->getHighestColumn();

        $firstSheetHighestRow = $firstSheet->getHighestRow();
        $secondSheetHighestRow = $secondSheet->getHighestRow();

        $rowDataFirstSheet = $firstSheet->rangeToArray('A1:'.$firstSheetHighestColumn.$firstSheetHighestRow,NULL,TRUE,FALSE);
        $rowDatasecondSheet = $secondSheet->rangeToArray('A1:'.$secondSheetHighestColumn.$secondSheetHighestRow,NULL,TRUE,FALSE);

        $newFileName = $file_name.'_'.date('YmdHis').'.'.$extension;
        
        $objExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $rowCount = 1;
        $registrationData = [];
        $registrationDataKey = [];
        foreach ($rowDataFirstSheet as $key => $rowDataValue) {
            
            $column = 1;
            foreach ($rowDataValue as $key => $columnWiseValue) {
                
                if($columnWiseValue === 'REGISTRATION_NO'){
                    $registrationDataKey[] = $key;
                }
                if(is_numeric($columnWiseValue)){
                    $count = strlen((string) $columnWiseValue);
                    
                    if($count > 12){

                        $columnWiseValue = "'".$columnWiseValue;
                    }
                }
                $cell = $firstSheet->getCellByColumnAndRow($column, $rowCount);
                if(\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)){

                    $val = $cell->getValue();
                    $xls_date = $val;

                    $unix_date = ($xls_date - 25569) * 86400;
                    $xls_date = 25569 + ($unix_date / 86400);
                    $unix_date = ($xls_date - 25569) * 86400;
                    $columnWiseValue = date("d-m-Y", $unix_date);
                }
                $objExcel->getActiveSheet()->setCellValueByColumnAndRow($column, $rowCount, $columnWiseValue);
                $column++;
            }
           
            $registrationData[] = $rowDataValue[$registrationDataKey[0]];
            $rowCount++;
        }

        $excelExtraColumns = $rowDatasecondSheet[0];
        $rowCount2 = 1;
        foreach ($registrationData as $key => $value) {
            
            $column2 = $column;
            $column3 = $column;
            $columnsCounts = 0;
            foreach ($rowDatasecondSheet as $key2 => $value2) {
                
                foreach ($value2 as $key3 => $value3) {
                    
                    if($value3 === "REGISTRATION_NO")
                        $regiKey = $key3;

                    foreach ($excelExtraColumns as $key => $value5) {
                        
                        if($value5 === "REGISTRATION_NO"){
                            $columnsCounts++;
                        }else{

                            if($column3 < 102){

                                
                                $columnValue = $value5.$columnsCounts;
                                $objExcel->getActiveSheet()->setCellValueByColumnAndRow($column3,1,$columnValue);
                                $column3++;
                            }
                        }
                    }
                    if($value2[$regiKey] == $value){
                        
                        if ($value === $value3) {
                                
                        }else{
                            $cell = $firstSheet->getCellByColumnAndRow($column2,$rowCount);
                            if(\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)){

                                $val = $cell->getValue();
                                $xls_date = $val;
                                $unix_date = ($xls_date - 25569) * 86400;
                                $xls_date = 25569 + ($unix_date / 86400);
                                $unix_date = ($xls_date - 25569) * 86400;
                                $columnWiseValue = date("d-m-Y", $unix_date);
                            }
                            $objExcel->getActiveSheet()->setCellValueByColumnAndRow($column2,$rowCount2,$value3);
                            $column2++;
                        }
                    }else{

                    }
                }            
            }
            $rowCount2++;
        }
        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objExcel,$inputType);
        $objWriter->save(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$newFileName);

        if($get_file_aws_local_flag->file_aws_local == '1'){
            $file_path = '/'.$subdomain[0].'/backend/processExcel/'.$newFileName;

            $s3->put($file_path,file_get_contents(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$newFileName));
            unlink(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$newFileName);
            unlink(public_path().'/'.$subdomain[0].'/backend/processExcel/'.$file_name);
        }

        $auth_site_id=Auth::guard('admin')->user()->site_id;

        $excel_merge_logs = new ExcelMergeLogs;
        $excel_merge_logs->raw_Excel = $file_name;
        $excel_merge_logs->processed_excel = $newFileName;
        $excel_merge_logs->total_unique_records = count($registrationData) - 1;
        $excel_merge_logs->username = \Auth::guard('admin')->user()->id;
        $excel_merge_logs->site_id=$auth_site_id;
        $excel_merge_logs->status = 'success';

        $excel_merge_logs->save();

        if($get_file_aws_local_flag->file_aws_local == '1'){
            $path = \Config::get('constant.amazone_path').$subdomain[0].'/backend/processExcel/'.$newFileName;
        }
        else{
            $path = \Config::get('constant.local_base_path').$subdomain[0].'/backend/processExcel/'.$newFileName;
        }

        $message = Array('type' => 'success', 'message' => 'file successfully uploaded','link'=>$path);
        return $message;


    }
}
