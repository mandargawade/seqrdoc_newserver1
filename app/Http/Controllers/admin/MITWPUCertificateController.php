<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExcelValidationRequest;
use App\models\BackgroundTemplateMaster;
use App\models\FieldMaster;
use App\models\FontMaster;
use App\models\IDCardStatus;
use App\models\StudentTable;
use App\models\SystemConfig;
use App\models\pdf2pdf\TemplateMaster;
use File;
use Illuminate\Http\Request;
use Auth,Storage;
use TCPDF;
use QrCode;
use App\Library\Services\CheckUploadedFileOnAwsORLocalService;
use App\models\SbStudentTable;
use App\models\Config;
use App\models\PrintingDetail;
use App\Helpers\CoreHelper;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MITWPUDataExport;

class MITWPUCertificateController extends Controller
{
    public function index(Request $request)
    {
    	
        return response()->json(['success'=>true]);
    }

    public function mintData(Request $request){
    		
    		$domain = \Request::getHost();
        	$subdomain = explode('.', $domain);
           //  CoreHelper::checkContactAddress(2,$templateType='PDF2PDF');
             exit;
        	$filename="2000-2500.xlsx";
    		$pathImport = public_path().'/'.$subdomain[0].'/blockchain/import/';
			$import_filename_import = $pathImport.$filename;
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($import_filename_import);
			$reader->setReadDataOnly(true);
			$spreadsheet = $reader->load($import_filename_import);
			$sheet = $spreadsheet->getSheet(0);
			$highestColumn = $sheet->getHighestColumn();
			$highestRow = $sheet->getHighestRow();
			$rowData = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE);


			//$pathExport = public_path().'/'.$subdomain[0].'/blockchain/export/';
			//$import_filename_export = $pathImport.$filename;
			// $reader2 = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($import_filename_export);
			// //$reader->setReadDataOnly(true);
			// $spreadsheet2 = $reader2->load($import_filename_export);
			// $sheet2 = $spreadsheet2->getSheet(0);
			//echo "<table>";
            $result=array();
            $template_id=2;
            $admin_id = \Auth::guard('admin')->user()->toArray();
            $auth_site_id=Auth::guard('admin')->user()->site_id;
            $systemConfig = SystemConfig::select('sandboxing','printer_name')->where('site_id',$auth_site_id)->first();
            $template_data = TemplateMaster::select('id','template_name','is_block_chain','bc_document_description','bc_document_type','bc_contract_address')->where('id',$template_id)->first();
            // print_r($template_data);
            // exit;
            $template_type=1;
            $certificate_type="Degree Certificate";
            $log_serial_no=1;
            $withoutExt="Blockchain_".date('Y_m_d_h_i_s');
            for($excel_row = 2; $excel_row <= 2; $excel_row++)//$highestRow
            {
                $rowData1 = $sheet->rangeToArray('A'. $excel_row . ':' . $highestColumn . $excel_row, NULL, TRUE, FALSE);
                $studentID = $rowData1[0][0];
                //exit;
                $encryptedKey = strtoupper(md5($rowData1[0][0]));
                $blockchainUrl=CoreHelper::getBCVerificationUrl(encrypt($encryptedKey));
                //echo "<tr><td>".$studentID."</td><td>".$encryptedKey."</td><td>".$blockchainUrl."</td></tr>";

                //$result[] = array("student_id"=>$studentID,"encrypted_key"=>$encryptedKey,"blockchain_url"=>$blockchainUrl);
                //Blockchain fields

                 $check_data = StudentTable::select('id')->where('serial_no',$studentID)->first();
               //  print_r($check_data);
                 if(!$check_data){
                $certName = str_replace("/", "_", $studentID) .".pdf";
                $pdf_path = public_path().'\\'.$subdomain[0].'\backend\pdf_file\\'.$certName;
                if(file_exists($pdf_path)){
                $mintData=array();
                $mintData['documentType']="Degree Certificate";
                $mintData['description']="Student ID :".$studentID;
                $mintData['metadata1']=["label"=> "Student Name", "value"=> $rowData1[0][3]];
                $mintData['metadata2']=["label"=> "Competency Level", "value"=> $rowData1[0][11]];
                $mintData['metadata3']=["label"=> "Specialization", "value"=> $rowData1[0][14]];
                $mintData['metadata4']=["label"=> "CGPA", "value"=> $rowData1[0][17]];
                $mintData['metadata5']=["label"=> "Completion date", "value"=> $rowData1[0][23]];

                $mintData['uniqueHash']=$encryptedKey;
                
                $mintData['pdf_file']=$pdf_path;
                $mintData['template_id']=$template_id;
                $mintData['bc_contract_address']=$template_data['bc_contract_address'];
                 // print_r($mintData);
                $response=CoreHelper::mintPDF($mintData);
               // print_r($response);
               // exit;
               // $response['status']=200;
                
                if($response['status']==200){
                $bc_txn_hash=$response['txnHash'];
                       
                    /*Add data to student table and printing details*/
                    $student_table_id = $this->addCertificate($studentID, $certName, $template_id,$admin_id,$studentID,$template_type,$certificate_type,$bc_txn_hash);
                    $username = $admin_id['username'];
                    date_default_timezone_set('Asia/Kolkata');
                    $print_datetime = date("Y-m-d H:i:s");
                    $print_count = $this->getPrintCount($studentID);
                    $printer_name = $systemConfig['printer_name'];
                    $print_serial_no = $this->nextPrintSerial();
                    $template_name=$template_data['template_name'];
                    $this->addPrintDetails($username, $print_datetime, $printer_name, $print_count, $print_serial_no, $studentID,$template_name,$admin_id,$student_table_id,$studentID);
                    /*End Add data to student table and printing details*/

                    $content = "#".$log_serial_no." serial No :".$studentID." | ".date('Y-m-d H:i:s')." | Success".PHP_EOL;
                    
                }else{
                    $content = "#".$log_serial_no." serial No :".$studentID." | ".date('Y-m-d H:i:s')." | Not deployed on blockchain network.".PHP_EOL;
                }
                }else{
                    $content = "#".$log_serial_no." serial No :".$studentID." | ".date('Y-m-d H:i:s')." | Pdf not found.".PHP_EOL;
                }

                }else{
                    $content = "#".$log_serial_no." serial No :".$studentID." | ".date('Y-m-d H:i:s')." | Data found in student table.".PHP_EOL;
                }
                
                   // $date = date('Y-m-d H:i:s').PHP_EOL;
                    

                    if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
                        $file_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.sandbox').'/'.$template_id;
                    }
                    else{
                        $file_path = public_path().'/'.$subdomain[0].'/'.\Config::get('constant.template').'/'.$template_id;
                    }
                    $fp = fopen($file_path.'/'.$withoutExt.".txt","a");
                    fwrite($fp,$content);
                  //  fwrite($fp,$date);
                    $log_serial_no++;
            }
            //echo "</table>";
			//$sheet_name = 'MITWPUData_'. date('Y_m_d_H_i_s').'.xlsx'; 
        
        	//return Excel::download(new MITWPUDataExport($result),$sheet_name,'Xlsx');


			
    }

    

 
	public function check_file_exist($url){
	    $handle = @fopen($url, 'r');
	    if(!$handle){
	        return false;
	    }else{
	        return true;
	    }
	}
	public function addCertificate($serial_no, $certName, $template_id,$admin_id,$unique_no,$template_type,$certificate_type,$bc_txn_hash)
    {

       
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        

        $sts = 1;
        $datetime  = date("Y-m-d H:i:s");
        $ses_id  = $admin_id["id"];
        $certName = str_replace("/", "_", $certName);

        $get_config_data = Config::select('configuration')->first();
     
        $c = explode(", ", $get_config_data['configuration']);
        $key = "";


        $tempDir = public_path().'/backend/qr';
        $key = strtoupper(md5($unique_no)); 
        
        $codeContents = $key;
        $fileName = $key.'.png'; 
        
        $urlRelativeFilePath = 'qr/'.$fileName; 
        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
        // Mark all previous records of same serial no to inactive if any
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            $resultu = SbStudentTable::where('serial_no',$unique_no)->update(['status'=>'0']);
            // Insert the new record

            $result = SbStudentTable::create(['serial_no'=>$unique_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id]);
        }
        else{

            $check_data = StudentTable::select('id')->where('serial_no',$studentID)->first();
            if($check_data){
                $resultu = StudentTable::where('serial_no',$unique_no)->update(['status'=>'0']);
            }
            
            // Insert the new record

            $result = StudentTable::create(['serial_no'=>$unique_no,'certificate_filename'=>$certName,'template_id'=>$template_id,'key'=>$key,'path'=>$urlRelativeFilePath,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'template_type'=>$template_type,'certificate_type'=>$certificate_type,'bc_txn_hash'=>$bc_txn_hash]);
        }

        return $result['id'];
    }
    public function getPrintCount($serial_no)
    {
        $numCount = PrintingDetail::select('id')->where('sr_no',$serial_no)->count();
        return $numCount + 1;
    }
    public function addPrintDetails($username, $print_datetime, $printer_name, $printer_count, $print_serial_no, $sr_no,$template_name,$admin_id,$student_table_id,$unique_no)
    {
        $sts = 1;
        $datetime = date("Y-m-d H:i:s");
        $ses_id = $admin_id["id"];
        $auth_site_id=\Auth::guard('admin')->user()->site_id;
        $systemConfig = SystemConfig::select('sandboxing')->where('site_id',$auth_site_id)->first();
        if(isset($systemConfig['sandboxing']) && $systemConfig['sandboxing'] == 1){
            // Insert the new record
            $result = SbPrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$unique_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1]);
        }
        else{
            // Insert the new record
            $result = PrintingDetail::create(['username'=>$username,'print_datetime'=>$print_datetime,'printer_name'=>$printer_name,'print_count'=>$printer_count,'print_serial_no'=>$print_serial_no,'sr_no'=>$unique_no,'template_name'=>$template_name,'created_at'=>$datetime,'created_by'=>$ses_id,'updated_at'=>$datetime,'updated_by'=>$ses_id,'status'=>$sts,'site_id'=>$auth_site_id,'publish'=>1,'student_table_id'=>$student_table_id]);
        }
    }
    public function nextPrintSerial()
    {
        $current_year = 'PN/' . $this->getFinancialyear() . '/';
        // find max
        $maxNum = 0;

        $result = \DB::select("SELECT COALESCE(MAX(CONVERT(SUBSTR(print_serial_no, 10), UNSIGNED)), 0) AS next_num "
            . "FROM printing_details WHERE SUBSTR(print_serial_no, 1, 9) = '$current_year'");
        
        //get next num
        $maxNum = $result[0]->next_num + 1;

        return $current_year . $maxNum;
    }
    public function getFinancialyear()
    {
        $yy = date('y');
        $mm = date('m');
        $fy = str_pad($yy, 2, "0", STR_PAD_LEFT);
        if($mm > 3)
            $fy = $fy . "-" . ($yy + 1);
        else
            $fy = str_pad($yy - 1, 2, "0", STR_PAD_LEFT) . "-" . $fy;
        return $fy;
    }
}

